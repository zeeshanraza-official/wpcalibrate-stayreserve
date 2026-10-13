<?php
/**
 * Booking Repository for persistence and querying of reservation records.
 *
 * @package WPCalibrate\StayReserve\Booking
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Booking;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Security\TokenManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingRepository
 */
class BookingRepository {

	/**
	 * Create a new booking record in the database.
	 *
	 * @param BookingModel $booking Model to insert.
	 * @return int Inserted booking ID or 0 on failure.
	 */
	public function create( BookingModel $booking ): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$now = DateTimeUtil::now_mysql();
		$data = $booking->to_array();
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$inserted = $wpdb->insert(
			Schema::get_table_name( 'bookings' ),
			$data,
			array(
				'%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s',
				'%d', '%d', '%d', '%d', '%d', '%d',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
				'%s', '%s'
			)
		);

		if ( ! $inserted ) {
			return 0;
		}

		$booking_id  = (int) $wpdb->insert_id;
		$booking->id = $booking_id;

		// Insert booking items
		foreach ( $booking->items as $item ) {
			$this->add_item( $booking_id, $item );
		}

		// Insert booking extras
		foreach ( $booking->extras as $extra ) {
			$this->add_extra( $booking_id, $extra );
		}

		return $booking_id;
	}

	/**
	 * Update booking fields.
	 *
	 * @param int   $id Booking ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return false;
		}

		$data['updated_at'] = DateTimeUtil::now_mysql();

		$res = $wpdb->update(
			Schema::get_table_name( 'bookings' ),
			$data,
			array( 'id' => $id )
		);

		return false !== $res;
	}

	/**
	 * Get booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return BookingModel|null
	 */
	public function get_by_id( int $id ): ?BookingModel {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return null;
		}

		$table = Schema::get_table_name( 'bookings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$model         = BookingModel::from_array( $row );
		$model->items  = $this->get_items( $id );
		$model->extras = $this->get_extras( $id );

		return $model;
	}

	/**
	 * Get booking by public reference (e.g. 'SR-8F4A2B9C').
	 *
	 * @param string $reference Public reference.
	 * @return BookingModel|null
	 */
	public function get_by_reference( string $reference ): ?BookingModel {
		global $wpdb;
		if ( ! $wpdb || empty( $reference ) ) {
			return null;
		}

		$table = Schema::get_table_name( 'bookings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE public_reference = %s LIMIT 1", sanitize_text_field( $reference ) ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$model         = BookingModel::from_array( $row );
		$model->items  = $this->get_items( (int) $model->id );
		$model->extras = $this->get_extras( (int) $model->id );

		return $model;
	}

	/**
	 * Get booking by WooCommerce order ID.
	 *
	 * @param int $wc_order_id WC Order ID.
	 * @return BookingModel|null
	 */
	public function get_by_wc_order_id( int $wc_order_id ): ?BookingModel {
		global $wpdb;
		if ( ! $wpdb || $wc_order_id <= 0 ) {
			return null;
		}

		$table = Schema::get_table_name( 'bookings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE wc_order_id = %d LIMIT 1", $wc_order_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$model         = BookingModel::from_array( $row );
		$model->items  = $this->get_items( (int) $model->id );
		$model->extras = $this->get_extras( (int) $model->id );

		return $model;
	}

	/**
	 * Get booking using raw guest management token.
	 *
	 * @param string $raw_token Raw guest management token.
	 * @return BookingModel|null
	 */
	public function get_by_management_token( string $raw_token ): ?BookingModel {
		global $wpdb;
		if ( ! $wpdb || empty( $raw_token ) ) {
			return null;
		}

		$hash  = TokenManager::hash_token( $raw_token );
		$table = Schema::get_table_name( 'bookings' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE management_token_hash = %s LIMIT 1", $hash ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		// Verify token expiration
		if ( ! empty( $row['management_token_expires_at'] ) ) {
			$exp_time = strtotime( $row['management_token_expires_at'] . ' UTC' );
			if ( false !== $exp_time && time() > $exp_time ) {
				return null;
			}
		}

		$model         = BookingModel::from_array( $row );
		$model->items  = $this->get_items( (int) $model->id );
		$model->extras = $this->get_extras( (int) $model->id );

		return $model;
	}

	/**
	 * Query bookings with filtering and pagination.
	 *
	 * @param array $args Query parameters.
	 * @return array<int, BookingModel>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		$table  = Schema::get_table_name( 'bookings' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'payment_status = %s';
			$params[] = sanitize_text_field( $args['payment_status'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['customer_email'] ) ) {
			$where[]  = 'customer_email = %s';
			$params[] = sanitize_email( $args['customer_email'] );
		}

		if ( ! empty( $args['check_in_from'] ) ) {
			$where[]  = 'check_in >= %s';
			$params[] = DateTimeUtil::format_date( $args['check_in_from'] );
		}

		if ( ! empty( $args['check_in_to'] ) ) {
			$where[]  = 'check_in <= %s';
			$params[] = DateTimeUtil::format_date( $args['check_in_to'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$term     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(public_reference LIKE %s OR customer_first_name LIKE %s OR customer_last_name LIKE %s OR customer_email LIKE %s)';
			$params[] = $term;
			$params[] = $term;
			$params[] = $term;
			$params[] = $term;
		}

		$where_clause = implode( ' AND ', $where );

		// Ordering
		$orderby = 'created_at';
		$allowed_orders = array( 'id', 'created_at', 'check_in', 'check_out', 'grand_total', 'status' );
		if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orders, true ) ) {
			$orderby = $args['orderby'];
		}
		$order = ( ! empty( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$limit  = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$offset = isset( $args['page'] ) ? ( max( 1, (int) $args['page'] ) - 1 ) * $limit : 0;

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
			...$params
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		$models = array();
		foreach ( $rows as $row ) {
			$model         = BookingModel::from_array( $row );
			$model->items  = $this->get_items( (int) $model->id );
			$model->extras = $this->get_extras( (int) $model->id );
			$models[]      = $model;
		}

		return $models;
	}

	/**
	 * Count bookings matching query criteria.
	 *
	 * @param array $args Query parameters.
	 * @return int Total records count.
	 */
	public function count( array $args = array() ): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$table  = Schema::get_table_name( 'bookings' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'payment_status = %s';
			$params[] = sanitize_text_field( $args['payment_status'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$term     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(public_reference LIKE %s OR customer_first_name LIKE %s OR customer_last_name LIKE %s OR customer_email LIKE %s)';
			$params[] = $term;
			$params[] = $term;
			$params[] = $term;
			$params[] = $term;
		}

		$where_clause = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = empty( $params )
			? "SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}"
			: $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}", ...$params );

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Add reserved room item to booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $item_data Item data.
	 * @return int Inserted ID.
	 */
	public function add_item( int $booking_id, array $item_data ): int {
		global $wpdb;
		if ( ! $wpdb || $booking_id <= 0 ) {
			return 0;
		}

		$wpdb->insert(
			Schema::get_table_name( 'booking_items' ),
			array(
				'booking_id'       => $booking_id,
				'room_type_id'     => (int) $item_data['room_type_id'],
				'room_unit_id'     => (int) $item_data['room_unit_id'],
				'quantity'         => (int) ( $item_data['quantity'] ?? 1 ),
				'adults'           => (int) ( $item_data['adults'] ?? 1 ),
				'children'         => (int) ( $item_data['children'] ?? 0 ),
				'nightly_subtotal' => (int) ( $item_data['nightly_subtotal'] ?? 0 ),
				'line_total'       => (int) ( $item_data['line_total'] ?? 0 ),
				'created_at'       => DateTimeUtil::now_mysql(),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all items for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_items( int $booking_id ): array {
		global $wpdb;
		if ( ! $wpdb || $booking_id <= 0 ) {
			return array();
		}

		$table = Schema::get_table_name( 'booking_items' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE booking_id = %d ORDER BY id ASC", $booking_id ),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Add extra add-on line item to a booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $extra_data Extra data.
	 * @return int Inserted ID.
	 */
	public function add_extra( int $booking_id, array $extra_data ): int {
		global $wpdb;
		if ( ! $wpdb || $booking_id <= 0 ) {
			return 0;
		}

		$wpdb->insert(
			Schema::get_table_name( 'booking_extras' ),
			array(
				'booking_id'   => $booking_id,
				'extra_id'     => ! empty( $extra_data['extra_id'] ) ? (int) $extra_data['extra_id'] : null,
				'name'         => sanitize_text_field( $extra_data['name'] ),
				'pricing_type' => sanitize_text_field( $extra_data['pricing_type'] ?? 'per_stay' ),
				'quantity'     => (int) ( $extra_data['quantity'] ?? 1 ),
				'unit_price'   => (int) ( $extra_data['unit_price'] ?? 0 ),
				'total_price'  => (int) ( $extra_data['total_price'] ?? 0 ),
				'created_at'   => DateTimeUtil::now_mysql(),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all extra line items for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_extras( int $booking_id ): array {
		global $wpdb;
		if ( ! $wpdb || $booking_id <= 0 ) {
			return array();
		}

		$table = Schema::get_table_name( 'booking_extras' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE booking_id = %d ORDER BY id ASC", $booking_id ),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Log operational event to audit trail.
	 *
	 * @param int|null   $booking_id Booking ID.
	 * @param string     $event_type Event type code.
	 * @param string     $message Descriptive message.
	 * @param array|null $context Context data.
	 * @return int Inserted event ID.
	 */
	public function add_event( ?int $booking_id, string $event_type, string $message, ?array $context = null ): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$wpdb->insert(
			Schema::get_table_name( 'events' ),
			array(
				'booking_id' => $booking_id,
				'event_type' => sanitize_text_field( $event_type ),
				'message'    => sanitize_text_field( $message ),
				'context'    => $context ? wp_json_encode( $context ) : null,
				'created_at' => DateTimeUtil::now_mysql(),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get audit events for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_events( int $booking_id ): array {
		global $wpdb;
		if ( ! $wpdb || $booking_id <= 0 ) {
			return array();
		}

		$table = Schema::get_table_name( 'events' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE booking_id = %d ORDER BY created_at DESC", $booking_id ),
			ARRAY_A
		);

		return $results ?: array();
	}
}
