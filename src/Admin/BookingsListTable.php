<?php
/**
 * Bookings List Table for WordPress Admin.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Booking\PaymentStatus;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class BookingsListTable
 */
class BookingsListTable extends \WP_List_Table {

	private BookingRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param BookingRepository $repository Repository.
	 */
	public function __construct( BookingRepository $repository ) {
		parent::__construct(
			array(
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			)
		);
		$this->repository = $repository;
	}

	/**
	 * Define columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'               => '<input type="checkbox" />',
			'public_reference' => __( 'Reference', 'wpcalibrate-stayreserve' ),
			'customer'         => __( 'Guest', 'wpcalibrate-stayreserve' ),
			'dates'            => __( 'Stay Dates', 'wpcalibrate-stayreserve' ),
			'room'             => __( 'Room', 'wpcalibrate-stayreserve' ),
			'total'            => __( 'Total', 'wpcalibrate-stayreserve' ),
			'status'           => __( 'Booking Status', 'wpcalibrate-stayreserve' ),
			'payment_status'   => __( 'Payment Status', 'wpcalibrate-stayreserve' ),
			'created_at'       => __( 'Booked Date', 'wpcalibrate-stayreserve' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'public_reference' => array( 'public_reference', false ),
			'dates'            => array( 'check_in', false ),
			'total'            => array( 'grand_total', false ),
			'created_at'       => array( 'created_at', true ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="booking_ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Reference column with row actions.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_public_reference( $item ): string {
		$edit_url = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=edit&booking_id=' . $item->id );
		$cancel_url = wp_nonce_url(
			admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=cancel&booking_id=' . $item->id ),
			'wpcsr_cancel_booking_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'View / Edit', 'wpcalibrate-stayreserve' ) ),
		);

		if ( BookingStatus::is_active( $item->status ) ) {
			$actions['cancel'] = sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');" style="color:#b32d2e;">%s</a>',
				esc_url( $cancel_url ),
				esc_attr__( 'Are you sure you want to cancel this booking? Reserved inventory will be released.', 'wpcalibrate-stayreserve' ),
				esc_html__( 'Cancel', 'wpcalibrate-stayreserve' )
			);
		}

		return sprintf(
			'<strong><a href="%s" class="row-title">%s</a></strong> %s',
			esc_url( $edit_url ),
			esc_html( $item->public_reference ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Guest column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_customer( $item ): string {
		$name  = esc_html( $item->get_customer_name() );
		$email = esc_html( $item->customer_email );
		$phone = esc_html( $item->customer_phone );

		$output = "<strong>{$name}</strong><br><span style='color:#646970;'>{$email}</span>";
		if ( ! empty( $phone ) ) {
			$output .= "<br><span style='color:#646970;'>{$phone}</span>";
		}
		return $output;
	}

	/**
	 * Dates column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_dates( $item ): string {
		$nights = $item->get_nights();
		return sprintf(
			'<strong>%s</strong> &rarr; <strong>%s</strong><br><span style="color:#646970;">%s</span>',
			esc_html( $item->check_in ),
			esc_html( $item->check_out ),
			/* translators: %d: Number of nights */
			esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'wpcalibrate-stayreserve' ), $nights ) )
		);
	}

	/**
	 * Room column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_room( $item ): string {
		if ( empty( $item->items ) ) {
			return '&mdash;';
		}
		$first_item = $item->items[0];
		$type_post  = get_post( (int) $first_item['room_type_id'] );
		$type_name  = $type_post ? $type_post->post_title : __( 'Unknown Room', 'wpcalibrate-stayreserve' );

		$unit_repo  = new \WPCalibrate\StayReserve\Rooms\RoomUnitRepository();
		$unit       = $unit_repo->get( (int) $first_item['room_unit_id'] );
		$unit_name  = $unit ? $unit['unit_name'] : '';

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $type_name ),
			$unit_name ? '<br><span style="color:#2271b1;font-size:12px;">' . esc_html( $unit_name ) . '</span>' : ''
		);
	}

	/**
	 * Total column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_total( $item ): string {
		return '<strong>' . esc_html( $item->get_formatted_total() ) . '</strong>';
	}

	/**
	 * Status column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$label = BookingStatus::get_label( $item->status );
		return sprintf( '<span class="wpcsr-status-pill status-%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );
	}

	/**
	 * Payment status column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_payment_status( $item ): string {
		$label = PaymentStatus::get_label( $item->payment_status );
		return sprintf( '<span class="wpcsr-status-pill payment-%s">%s</span>', esc_attr( $item->payment_status ), esc_html( $label ) );
	}

	/**
	 * Created at column.
	 *
	 * @param BookingModel $item Booking.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		return esc_html( substr( $item->created_at, 0, 16 ) );
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$page     = $this->get_pagenum();

		$filter_status         = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$filter_payment_status = isset( $_REQUEST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) : '';
		$search_query          = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby               = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order                 = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$query_args = array(
			'page'     => $page,
			'per_page' => $per_page,
			'orderby'  => $orderby,
			'order'    => $order,
		);

		if ( ! empty( $filter_status ) ) {
			$query_args['status'] = $filter_status;
		}
		if ( ! empty( $filter_payment_status ) ) {
			$query_args['payment_status'] = $filter_payment_status;
		}
		if ( ! empty( $search_query ) ) {
			$query_args['search'] = $search_query;
		}

		$total_items = $this->repository->count( $query_args );
		$items       = $this->repository->query( $query_args );

		$this->items = $items;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
