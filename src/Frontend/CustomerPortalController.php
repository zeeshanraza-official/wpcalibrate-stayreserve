<?php
/**
 * Customer Booking Management Portal Controller.
 *
 * @package WPCalibrate\StayReserve\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Frontend;

use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingService;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Security\TokenManager;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CustomerPortalController
 */
class CustomerPortalController {

	private BookingRepository $repository;
	private BookingService $service;
	private TokenManager $token_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		BookingRepository $repository,
		BookingService $service,
		TokenManager $token_manager
	) {
		$this->repository    = $repository;
		$this->service       = $service;
		$this->token_manager = $token_manager;
	}

	/**
	 * Render portal interface.
	 *
	 * @return void
	 */
	public function render(): void {
		$ref   = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		// Handle cancellation submission
		if ( isset( $_POST['wpcsr_portal_cancel'] ) && ! empty( $_POST['booking_id'] ) ) {
			$this->handle_customer_cancellation();
			return;
		}

		$booking = null;

		// 1. Check guest management token if provided
		if ( ! empty( $ref ) && ! empty( $token ) ) {
			$candidate = $this->repository->get_by_reference( $ref );
			if ( $candidate && ! empty( $candidate->management_token_hash ) ) {
				$is_valid = TokenManager::verify_token(
					$token,
					$candidate->management_token_hash,
					$candidate->management_token_expires_at
				);
				if ( $is_valid ) {
					$booking = $candidate;
				}
			}
		}

		// 2. Check logged-in user if no valid token
		$current_user_id = get_current_user_id();
		if ( ! $booking && $current_user_id > 0 && ! empty( $ref ) ) {
			$candidate = $this->repository->get_by_reference( $ref );
			if ( $candidate && (int) $candidate->user_id === $current_user_id ) {
				$booking = $candidate;
			}
		}

		echo '<div class="wpcsr-portal-wrap" style="max-width:800px; margin:0 auto; padding:20px;">';

		if ( $booking ) {
			$this->render_booking_details( $booking, $token );
		} elseif ( $current_user_id > 0 ) {
			$this->render_user_bookings_list( $current_user_id );
		} else {
			$this->render_lookup_form();
		}

		echo '</div>';
	}

	/**
	 * Render single booking details.
	 *
	 * @param BookingModel $booking Booking.
	 * @param string       $token Current guest access token.
	 * @return void
	 */
	private function render_booking_details( BookingModel $booking, string $token = '' ): void {
		$is_cancellable = BookingStatus::is_active( $booking->status );
		?>
		<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px; box-shadow:0 4px 16px rgba(0,0,0,0.04);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:16px;">
				<h2 style="margin:0; font-size:22px;">
					<?php
					/* translators: %s: Booking Reference */
					echo esc_html( sprintf( __( 'Reservation #%s', 'wpcalibrate-stayreserve' ), $booking->public_reference ) );
					?>
				</h2>
				<span class="wpcsr-status-pill status-<?php echo esc_attr( $booking->status ); ?>" style="padding:6px 14px; border-radius:20px; font-weight:600; font-size:13px;">
					<?php echo esc_html( BookingStatus::get_label( $booking->status ) ); ?>
				</span>
			</div>

			<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
				<div style="background:#f8fafc; padding:16px; border-radius:8px;">
					<h4 style="margin-top:0;"><?php esc_html_e( 'Stay Information', 'wpcalibrate-stayreserve' ); ?></h4>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Check-in:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $booking->check_in ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Check-out:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $booking->check_out ); ?> (<?php echo esc_html( (string) $booking->get_nights() ); ?> nights)</p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Guests:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( "{$booking->adults} Adults, {$booking->children} Children" ); ?></p>
				</div>
				<div style="background:#f8fafc; padding:16px; border-radius:8px;">
					<h4 style="margin-top:0;"><?php esc_html_e( 'Payment Summary', 'wpcalibrate-stayreserve' ); ?></h4>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Total Amount:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $booking->get_formatted_total() ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Payment Status:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( ucfirst( $booking->payment_status ) ); ?></p>
				</div>
			</div>

			<?php if ( $is_cancellable ) : ?>
				<div style="border-top:1px solid #e2e8f0; padding-top:20px; margin-top:20px;">
					<h4 style="color:#b32d2e; margin-top:0;"><?php esc_html_e( 'Cancel Reservation', 'wpcalibrate-stayreserve' ); ?></h4>
					<p style="font-size:14px; color:#646970;"><?php esc_html_e( 'Need to change your plans? You may cancel your reservation below according to our cancellation policy.', 'wpcalibrate-stayreserve' ); ?></p>

					<form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel this booking? This will immediately release your reserved room.', 'wpcalibrate-stayreserve' ); ?>');">
						<?php wp_nonce_field( 'wpcsr_cancel_booking_portal_' . $booking->id, 'wpcsr_portal_nonce' ); ?>
						<input type="hidden" name="booking_id" value="<?php echo esc_attr( (string) $booking->id ); ?>">
						<input type="hidden" name="ref" value="<?php echo esc_attr( $booking->public_reference ); ?>">
						<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
						<input type="hidden" name="wpcsr_portal_cancel" value="1">

						<p>
							<label for="cancel_reason" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Reason for cancellation (optional):', 'wpcalibrate-stayreserve' ); ?></label>
							<textarea name="cancel_reason" id="cancel_reason" rows="2" style="width:100%; border:1px solid #cbd5e0; border-radius:6px; padding:8px;"></textarea>
						</p>

						<button type="submit" class="button" style="background:#b32d2e; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:600; cursor:pointer;">
							<?php esc_html_e( 'Cancel My Reservation', 'wpcalibrate-stayreserve' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render all bookings belonging to a logged-in user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function render_user_bookings_list( int $user_id ): void {
		$bookings = $this->repository->query( array( 'user_id' => $user_id, 'per_page' => 20 ) );
		?>
		<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:28px;">
			<h2 style="margin-top:0; font-size:22px;"><?php esc_html_e( 'Your Bookings', 'wpcalibrate-stayreserve' ); ?></h2>

			<?php if ( empty( $bookings ) ) : ?>
				<p style="color:#718096;"><?php esc_html_e( 'You have no reservations linked to your account.', 'wpcalibrate-stayreserve' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top:16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reference', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Stay Dates', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Total', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Action', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bookings as $b ) : ?>
							<?php $view_url = add_query_arg( 'ref', $b->public_reference ); ?>
							<tr>
								<td><strong><?php echo esc_html( $b->public_reference ); ?></strong></td>
								<td><?php echo esc_html( $b->check_in . ' to ' . $b->check_out ); ?></td>
								<td><span class="wpcsr-status-pill status-<?php echo esc_attr( $b->status ); ?>"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
								<td><?php echo esc_html( $b->get_formatted_total() ); ?></td>
								<td><a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'View Details', 'wpcalibrate-stayreserve' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render lookup form for guest access.
	 *
	 * @return void
	 */
	private function render_lookup_form(): void {
		?>
		<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px; max-width:500px; margin:40px auto; text-align:center;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Manage Your Reservation', 'wpcalibrate-stayreserve' ); ?></h3>
			<p style="color:#646970; font-size:14px; margin-bottom:20px;">
				<?php esc_html_e( 'Please click the personalized secure link sent to your confirmation email to access your reservation.', 'wpcalibrate-stayreserve' ); ?>
			</p>
			<?php if ( is_user_logged_in() ) : ?>
				<p><a href="<?php echo esc_url( remove_query_arg( array( 'ref', 'token' ) ) ); ?>" class="button"><?php esc_html_e( 'View Your Account Bookings', 'wpcalibrate-stayreserve' ); ?></a></p>
			<?php else : ?>
				<p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button"><?php esc_html_e( 'Log in to view account bookings', 'wpcalibrate-stayreserve' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle cancellation request from portal.
	 *
	 * @return void
	 */
	private function handle_customer_cancellation(): void {
		$booking_id = (int) ( $_POST['booking_id'] ?? 0 );
		check_admin_referer( 'wpcsr_cancel_booking_portal_' . $booking_id, 'wpcsr_portal_nonce' );

		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Invalid booking.', 'wpcalibrate-stayreserve' ) );
		}

		// Security authorization check:
		$authorized = false;
		$user_id    = get_current_user_id();
		if ( $user_id > 0 && (int) $booking->user_id === $user_id ) {
			$authorized = true;
		}

		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		if ( ! $authorized && ! empty( $token ) && ! empty( $booking->management_token_hash ) ) {
			if ( TokenManager::verify_token( $token, $booking->management_token_hash, $booking->management_token_expires_at ) ) {
				$authorized = true;
			}
		}

		if ( ! $authorized ) {
			wp_die( esc_html__( 'Security authorization failed. You are not authorized to cancel this booking.', 'wpcalibrate-stayreserve' ) );
		}

		$reason = sanitize_textarea_field( wp_unslash( $_POST['cancel_reason'] ?? 'Customer initiated cancellation' ) );
		$this->service->cancel_booking( $booking_id, $reason, true );

		wp_safe_redirect( add_query_arg( array( 'ref' => $booking->public_reference, 'token' => $token, 'cancelled' => 1 ) ) );
		exit;
	}
}
