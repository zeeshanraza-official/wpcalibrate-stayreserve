<?php
/**
 * Booking Admin Controller for managing, creating, and editing bookings in wp-admin.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Availability\AvailabilityEngine;
use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingService;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Booking\PaymentStatus;
use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Rooms\RoomUnitRepository;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingAdminController
 */
class BookingAdminController {

	private BookingRepository $repository;
	private BookingService $service;
	private AvailabilityEngine $availability;

	/**
	 * Constructor.
	 */
	public function __construct(
		BookingRepository $repository,
		BookingService $service,
		AvailabilityEngine $availability
	) {
		$this->repository   = $repository;
		$this->service      = $service;
		$this->availability = $availability;
	}

	/**
	 * Route and render action.
	 *
	 * @return void
	 */
	public function render(): void {
		$action     = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;

		// Handle cancellation
		if ( 'cancel' === $action && $booking_id > 0 ) {
			$this->handle_cancel( $booking_id );
			return;
		}

		// Handle save edit
		if ( 'save_edit' === $action && $booking_id > 0 && ! empty( $_POST ) ) {
			$this->handle_save_edit( $booking_id );
			return;
		}

		// Handle manual booking creation
		if ( 'create_manual' === $action && ! empty( $_POST ) ) {
			$this->handle_create_manual();
			return;
		}

		if ( 'new' === $action ) {
			$this->render_new_booking_screen();
		} elseif ( 'edit' === $action && $booking_id > 0 ) {
			$this->render_edit_screen( $booking_id );
		} else {
			$this->render_list_screen();
		}
	}

	/**
	 * Render booking list table.
	 *
	 * @return void
	 */
	private function render_list_screen(): void {
		$table = new BookingsListTable( $this->repository );
		$table->prepare_items();

		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$all_statuses   = BookingStatus::get_all();
		?>
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
			<div class="subsubsub" style="margin:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings' ) ); ?>" class="<?php echo empty( $current_status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'wpcalibrate-stayreserve' ); ?>
				</a>
				<?php foreach ( $all_statuses as $st_key => $st_label ) : ?>
					| <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&status=' . $st_key ) ); ?>" class="<?php echo $current_status === $st_key ? 'current' : ''; ?>">
						<?php echo esc_html( $st_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( CapabilityManager::can( CapabilityManager::CAP_CREATE_BOOKINGS ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=new' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '+ Add New Booking', 'wpcalibrate-stayreserve' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<form method="get">
			<input type="hidden" name="page" value="wpcalibrate-stayreserve">
			<input type="hidden" name="tab" value="bookings">
			<?php $table->search_box( __( 'Search Bookings', 'wpcalibrate-stayreserve' ), 'wpcsr_search' ); ?>
			<?php $table->display(); ?>
		</form>
		<?php
	}

	/**
	 * Render manual booking creation form.
	 *
	 * @return void
	 */
	private function render_new_booking_screen(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_CREATE_BOOKINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to create bookings.', 'wpcalibrate-stayreserve' ) );
		}

		$room_types = get_posts(
			array(
				'post_type'      => RoomTypePostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		?>
		<div style="max-width: 800px;">
			<h2><?php esc_html_e( 'Create Manual Reservation', 'wpcalibrate-stayreserve' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Add a new offline, telephone, or walk-in booking. Availability is verified server-side before confirmation.', 'wpcalibrate-stayreserve' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=create_manual' ) ); ?>" style="margin-top: 20px;">
				<?php wp_nonce_field( 'wpcsr_create_manual_booking', 'wpcsr_manual_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="room_type_id"><?php esc_html_e( 'Room Type', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<select name="room_type_id" id="room_type_id" required style="width:100%;max-width:400px;">
								<option value=""><?php esc_html_e( '— Select Accommodation —', 'wpcalibrate-stayreserve' ); ?></option>
								<?php foreach ( $room_types as $rt ) : ?>
									<option value="<?php echo esc_attr( (string) $rt->ID ); ?>"><?php echo esc_html( $rt->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="check_in"><?php esc_html_e( 'Check-in Date', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<input type="date" name="check_in" id="check_in" required value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="check_out"><?php esc_html_e( 'Check-out Date', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<input type="date" name="check_out" id="check_out" required value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="adults"><?php esc_html_e( 'Adults & Children', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<input type="number" name="adults" id="adults" min="1" max="50" value="2" style="width:80px;"> <?php esc_html_e( 'Adults', 'wpcalibrate-stayreserve' ); ?>
							&nbsp;&nbsp;
							<input type="number" name="children" id="children" min="0" max="50" value="0" style="width:80px;"> <?php esc_html_e( 'Children', 'wpcalibrate-stayreserve' ); ?>
						</td>
					</tr>
					<tr>
						<th><label for="customer_first_name"><?php esc_html_e( 'Guest First Name', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<input type="text" name="customer_first_name" id="customer_first_name" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="customer_last_name"><?php esc_html_e( 'Guest Last Name', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<input type="text" name="customer_last_name" id="customer_last_name" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="customer_email"><?php esc_html_e( 'Guest Email', 'wpcalibrate-stayreserve' ); ?> *</label></th>
						<td>
							<input type="email" name="customer_email" id="customer_email" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="customer_phone"><?php esc_html_e( 'Guest Phone', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<input type="text" name="customer_phone" id="customer_phone" class="regular-text">
						</td>
					</tr>
					<tr>
						<th><label for="status"><?php esc_html_e( 'Initial Status', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<select name="status" id="status">
								<option value="confirmed"><?php esc_html_e( 'Confirmed', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Approval', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="on_hold"><?php esc_html_e( 'On Hold', 'wpcalibrate-stayreserve' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="payment_status"><?php esc_html_e( 'Payment Status', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<select name="payment_status" id="payment_status">
								<option value="unpaid"><?php esc_html_e( 'Unpaid (Pay at Property)', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="paid"><?php esc_html_e( 'Paid', 'wpcalibrate-stayreserve' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="admin_notes"><?php esc_html_e( 'Staff Notes', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<textarea name="admin_notes" id="admin_notes" rows="3" class="large-text"></textarea>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save & Confirm Booking', 'wpcalibrate-stayreserve' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wpcalibrate-stayreserve' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render View/Edit Booking screen.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function render_edit_screen( int $booking_id ): void {
		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'wpcalibrate-stayreserve' ) . '</p></div>';
			return;
		}

		$events = $this->repository->get_events( $booking_id );
		$all_statuses = BookingStatus::get_all();
		$payment_statuses = PaymentStatus::get_all();
		?>
		<div style="max-width: 1000px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
				<h2>
					<?php
					/* translators: %s: Booking Reference */
					echo esc_html( sprintf( __( 'Booking Details: %s', 'wpcalibrate-stayreserve' ), $booking->public_reference ) );
					?>
				</h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings' ) ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Bookings', 'wpcalibrate-stayreserve' ); ?>
				</a>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=save_edit&booking_id=' . $booking_id ) ); ?>">
				<?php wp_nonce_field( 'wpcsr_save_booking_edit_' . $booking_id, 'wpcsr_edit_nonce' ); ?>

				<div style="display:grid; grid-template-columns: 2fr 1fr; gap: 24px;">
					<!-- Left Column: Details & Dates -->
					<div>
						<div class="postbox" style="padding:16px;">
							<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Stay Information', 'wpcalibrate-stayreserve' ); ?></h3>

							<table class="form-table" style="margin-top:0;">
								<tr>
									<th><?php esc_html_e( 'Check-in Date', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<input type="date" name="check_in" value="<?php echo esc_attr( $booking->check_in ); ?>" required>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Check-out Date', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<input type="date" name="check_out" value="<?php echo esc_attr( $booking->check_out ); ?>" required>
										<p class="description"><?php esc_html_e( 'Modifying dates will revalidate room inventory before saving.', 'wpcalibrate-stayreserve' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Reserved Room(s)', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<?php foreach ( $booking->items as $item ) : ?>
											<?php
											$post = get_post( (int) $item['room_type_id'] );
											$unit_repo = new RoomUnitRepository();
											$unit = $unit_repo->get( (int) $item['room_unit_id'] );
											?>
											<div style="background:#f9f9f9; padding:8px 12px; border-radius:4px; margin-bottom:8px;">
												<strong><?php echo esc_html( $post ? $post->post_title : 'Room' ); ?></strong>
												&mdash; <?php esc_html_e( 'Assigned Unit:', 'wpcalibrate-stayreserve' ); ?>
												<span style="color:#2271b1;font-weight:600;"><?php echo esc_html( $unit ? $unit['unit_name'] : 'Auto-allocated' ); ?></span>
											</div>
										<?php endforeach; ?>
									</td>
								</tr>
							</table>
						</div>

						<div class="postbox" style="padding:16px; margin-top:20px;">
							<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Guest Contact Details', 'wpcalibrate-stayreserve' ); ?></h3>
							<table class="form-table" style="margin-top:0;">
								<tr>
									<th><?php esc_html_e( 'Name', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<input type="text" name="customer_first_name" value="<?php echo esc_attr( $booking->customer_first_name ); ?>" required style="width:48%;">
										<input type="text" name="customer_last_name" value="<?php echo esc_attr( $booking->customer_last_name ); ?>" required style="width:48%;">
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Email', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<input type="email" name="customer_email" value="<?php echo esc_attr( $booking->customer_email ); ?>" required class="regular-text">
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Phone', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<input type="text" name="customer_phone" value="<?php echo esc_attr( $booking->customer_phone ); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Staff Notes', 'wpcalibrate-stayreserve' ); ?></th>
									<td>
										<textarea name="admin_notes" rows="3" class="large-text"><?php echo esc_textarea( (string) $booking->admin_notes ); ?></textarea>
									</td>
								</tr>
							</table>
						</div>

						<!-- Audit Trail Events -->
						<div class="postbox" style="padding:16px; margin-top:20px;">
							<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Audit Trail / Events Log', 'wpcalibrate-stayreserve' ); ?></h3>
							<?php if ( empty( $events ) ) : ?>
								<p style="color:#646970;"><?php esc_html_e( 'No event logs recorded.', 'wpcalibrate-stayreserve' ); ?></p>
							<?php else : ?>
								<ul style="margin:0; padding-left:18px;">
									<?php foreach ( $events as $ev ) : ?>
										<li style="margin-bottom:8px; font-size:13px;">
											<span style="color:#646970;"><?php echo esc_html( $ev['created_at'] ); ?></span> &mdash;
											<strong><?php echo esc_html( $ev['event_type'] ); ?>:</strong>
											<?php echo esc_html( $ev['message'] ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>

					<!-- Right Column: Status & Financials -->
					<div>
						<div class="postbox" style="padding:16px;">
							<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Status & Actions', 'wpcalibrate-stayreserve' ); ?></h3>

							<p>
								<label for="edit_status"><strong><?php esc_html_e( 'Booking Status:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
								<select name="status" id="edit_status" style="width:100%; margin-top:4px;">
									<?php foreach ( $all_statuses as $st_key => $st_label ) : ?>
										<option value="<?php echo esc_attr( $st_key ); ?>" <?php selected( $booking->status, $st_key ); ?>>
											<?php echo esc_html( $st_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>

							<p>
								<label for="edit_payment_status"><strong><?php esc_html_e( 'Payment Status:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
								<select name="payment_status" id="edit_payment_status" style="width:100%; margin-top:4px;">
									<?php foreach ( $payment_statuses as $pst_key => $pst_label ) : ?>
										<option value="<?php echo esc_attr( $pst_key ); ?>" <?php selected( $booking->payment_status, $pst_key ); ?>>
											<?php echo esc_html( $pst_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>

							<div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; padding:12px; margin: 16px 0;">
								<div style="display:flex; justify-content:space-between; margin-bottom:4px;">
									<span><?php esc_html_e( 'Accommodation:', 'wpcalibrate-stayreserve' ); ?></span>
									<strong><?php echo esc_html( \WPCalibrate\StayReserve\Utilities\MoneyUtil::format_minor( $booking->accommodation_subtotal, $booking->currency ) ); ?></strong>
								</div>
								<?php if ( $booking->extras_total > 0 ) : ?>
									<div style="display:flex; justify-content:space-between; margin-bottom:4px;">
										<span><?php esc_html_e( 'Extras:', 'wpcalibrate-stayreserve' ); ?></span>
										<strong>+ <?php echo esc_html( \WPCalibrate\StayReserve\Utilities\MoneyUtil::format_minor( $booking->extras_total, $booking->currency ) ); ?></strong>
									</div>
								<?php endif; ?>
								<?php if ( $booking->discount_total > 0 ) : ?>
									<div style="display:flex; justify-content:space-between; margin-bottom:4px; color:#b32d2e;">
										<span><?php esc_html_e( 'Discount:', 'wpcalibrate-stayreserve' ); ?></span>
										<strong>- <?php echo esc_html( \WPCalibrate\StayReserve\Utilities\MoneyUtil::format_minor( $booking->discount_total, $booking->currency ) ); ?></strong>
									</div>
								<?php endif; ?>
								<?php if ( $booking->tax_total > 0 ) : ?>
									<div style="display:flex; justify-content:space-between; margin-bottom:4px;">
										<span><?php esc_html_e( 'Taxes:', 'wpcalibrate-stayreserve' ); ?></span>
										<strong>+ <?php echo esc_html( \WPCalibrate\StayReserve\Utilities\MoneyUtil::format_minor( $booking->tax_total, $booking->currency ) ); ?></strong>
									</div>
								<?php endif; ?>
								<div style="display:flex; justify-content:space-between; margin-top:8px; border-top:1px solid #ccd0d4; padding-top:8px; font-size:16px;">
									<span><strong><?php esc_html_e( 'Total:', 'wpcalibrate-stayreserve' ); ?></strong></span>
									<span style="color:#0073aa; font-weight:700;"><?php echo esc_html( $booking->get_formatted_total() ); ?></span>
								</div>
							</div>

							<button type="submit" class="button button-primary button-large" style="width:100%;">
								<?php esc_html_e( 'Save Changes', 'wpcalibrate-stayreserve' ); ?>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle cancel action.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function handle_cancel( int $booking_id ): void {
		check_admin_referer( 'wpcsr_cancel_booking_' . $booking_id );

		if ( ! CapabilityManager::can( CapabilityManager::CAP_CANCEL_BOOKINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$cancelled = $this->service->cancel_booking( $booking_id, 'Cancelled via admin dashboard' );
		$redirect  = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings' );

		if ( $cancelled ) {
			wp_safe_redirect( add_query_arg( 'wpcsr_notice', 'cancelled', $redirect ) );
		} else {
			wp_safe_redirect( add_query_arg( 'wpcsr_error', 'cancel_failed', $redirect ) );
		}
		exit;
	}

	/**
	 * Handle saving edits.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function handle_save_edit( int $booking_id ): void {
		check_admin_referer( 'wpcsr_save_booking_edit_' . $booking_id, 'wpcsr_edit_nonce' );

		if ( ! CapabilityManager::can( CapabilityManager::CAP_EDIT_BOOKINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Booking not found.', 'wpcalibrate-stayreserve' ) );
		}

		$new_check_in  = sanitize_text_field( wp_unslash( $_POST['check_in'] ?? $booking->check_in ) );
		$new_check_out = sanitize_text_field( wp_unslash( $_POST['check_out'] ?? $booking->check_out ) );

		// Update stay dates if changed
		if ( $new_check_in !== $booking->check_in || $new_check_out !== $booking->check_out ) {
			$date_updated = $this->service->update_booking_dates( $booking_id, $new_check_in, $new_check_out );
			if ( ! $date_updated ) {
				wp_die( esc_html__( 'Requested dates are not available for this room unit.', 'wpcalibrate-stayreserve' ) );
			}
		}

		// Update status
		$new_status = sanitize_text_field( wp_unslash( $_POST['status'] ?? $booking->status ) );
		if ( $new_status !== $booking->status ) {
			$this->service->transition_status( $booking_id, $new_status, 'Status updated by administrator' );
		}

		// Update guest info & payment status
		$update_data = array(
			'customer_first_name' => sanitize_text_field( wp_unslash( $_POST['customer_first_name'] ?? $booking->customer_first_name ) ),
			'customer_last_name'  => sanitize_text_field( wp_unslash( $_POST['customer_last_name'] ?? $booking->customer_last_name ) ),
			'customer_email'      => sanitize_email( wp_unslash( $_POST['customer_email'] ?? $booking->customer_email ) ),
			'customer_phone'      => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? $booking->customer_phone ) ),
			'payment_status'      => sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? $booking->payment_status ) ),
			'admin_notes'         => sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) ),
		);

		$this->repository->update( $booking_id, $update_data );

		wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=edit&booking_id=' . $booking_id . '&saved=1' ) );
		exit;
	}

	/**
	 * Handle manual booking creation.
	 *
	 * @return void
	 */
	private function handle_create_manual(): void {
		check_admin_referer( 'wpcsr_create_manual_booking', 'wpcsr_manual_nonce' );

		if ( ! CapabilityManager::can( CapabilityManager::CAP_CREATE_BOOKINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		try {
			$booking = $this->service->create_booking(
				array(
					'room_type_id'        => (int) sanitize_text_field( wp_unslash( $_POST['room_type_id'] ?? 0 ) ),
					'check_in'            => sanitize_text_field( wp_unslash( $_POST['check_in'] ?? '' ) ),
					'check_out'           => sanitize_text_field( wp_unslash( $_POST['check_out'] ?? '' ) ),
					'adults'              => (int) sanitize_text_field( wp_unslash( $_POST['adults'] ?? 1 ) ),
					'children'            => (int) sanitize_text_field( wp_unslash( $_POST['children'] ?? 0 ) ),
					'customer_first_name' => sanitize_text_field( wp_unslash( $_POST['customer_first_name'] ?? '' ) ),
					'customer_last_name'  => sanitize_text_field( wp_unslash( $_POST['customer_last_name'] ?? '' ) ),
					'customer_email'      => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
					'customer_phone'      => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
					'status'              => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'confirmed' ) ),
					'payment_status'      => sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? 'unpaid' ) ),
					'admin_notes'         => sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) ),
					'source'              => 'manual',
				)
			);

			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=edit&booking_id=' . $booking->id . '&created=1' ) );
			exit;
		} catch ( \Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}
}
