<?php
/**
 * Transactional Email Manager for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Email
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Email;

use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Logging\Logger;
use WPCalibrate\StayReserve\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class EmailManager
 */
class EmailManager {

	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Register email notification hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpcsr_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'wpcsr_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
		add_action( 'wpcsr_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 3 );
	}

	/**
	 * Handle booking created notification.
	 *
	 * @param int          $booking_id Booking ID.
	 * @param BookingModel $booking Booking model.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, BookingModel $booking ): void {
		$raw_token = get_transient( 'wpcsr_raw_token_' . $booking_id );
		$tokens    = EmailTemplate::get_tokens( $booking, $raw_token ?: null );

		// 1. Notify Admin
		if ( SettingsManager::get( 'emails', 'notify_admin_new_booking', true ) ) {
			$admin_to = SettingsManager::get( 'emails', 'admin_recipient', get_bloginfo( 'admin_email' ) );
			$subject  = sprintf( '[%s] New Reservation Received - %s', $tokens['{site_name}'], $tokens['{booking_reference}'] );
			$body     = sprintf(
				'<p>A new reservation has been placed by <strong>%s</strong> (%s).</p>
				<div class="details-box">
					<div class="details-row"><span class="details-label">Reference:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Accommodation:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Stay Dates:</span> <span>%s to %s (%s nights)</span></div>
					<div class="details-row"><span class="details-label">Total:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Status:</span> <span>%s</span></div>
				</div>',
				esc_html( $tokens['{guest_name}'] ),
				esc_html( $tokens['{guest_email}'] ),
				esc_html( $tokens['{booking_reference}'] ),
				esc_html( $tokens['{room_name}'] ),
				esc_html( $tokens['{check_in}'] ),
				esc_html( $tokens['{check_out}'] ),
				esc_html( $tokens['{nights}'] ),
				esc_html( $tokens['{booking_total}'] ),
				esc_html( $tokens['{booking_status}'] )
			);

			$this->send_email( (string) $admin_to, $subject, $body, __( 'New Reservation Received', 'wpcalibrate-stayreserve' ) );
		}

		// 2. Notify Customer
		if ( SettingsManager::get( 'emails', 'notify_customer_booking_rcvd', true ) ) {
			$subject = sprintf( 'Reservation Confirmation - %s (%s)', $tokens['{booking_reference}'], $tokens['{site_name}'] );
			$body    = sprintf(
				'<p>Dear %s,</p>
				<p>Thank you for choosing %s! Your reservation has been received.</p>
				<div class="details-box">
					<div class="details-row"><span class="details-label">Reference:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Accommodation:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Check-in:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Check-out:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Total Amount:</span> <span>%s</span></div>
				</div>
				<p><a href="%s" class="button">View & Manage Reservation</a></p>',
				esc_html( $tokens['{guest_name}'] ),
				esc_html( $tokens['{site_name}'] ),
				esc_html( $tokens['{booking_reference}'] ),
				esc_html( $tokens['{room_name}'] ),
				esc_html( $tokens['{check_in}'] ),
				esc_html( $tokens['{check_out}'] ),
				esc_html( $tokens['{booking_total}'] ),
				esc_url( $tokens['{manage_booking_url}'] )
			);

			$this->send_email( $booking->customer_email, $subject, $body, __( 'Reservation Received', 'wpcalibrate-stayreserve' ) );
		}
	}

	/**
	 * Handle status change notification.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function on_status_changed( int $booking_id, string $new_status, string $old_status ): void {
		if ( 'confirmed' === $new_status && SettingsManager::get( 'emails', 'notify_customer_confirmed', true ) ) {
			$repo    = new BookingRepository();
			$booking = $repo->get_by_id( $booking_id );
			if ( ! $booking ) {
				return;
			}

			$tokens  = EmailTemplate::get_tokens( $booking );
			$subject = sprintf( 'Your Reservation is Confirmed! - %s', $tokens['{booking_reference}'] );
			$body    = sprintf(
				'<p>Dear %s,</p>
				<p>Good news! Your reservation at %s is now <strong>Confirmed</strong>.</p>
				<div class="details-box">
					<div class="details-row"><span class="details-label">Reference:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Stay:</span> <span>%s to %s</span></div>
					<div class="details-row"><span class="details-label">Accommodation:</span> <span>%s</span></div>
					<div class="details-row"><span class="details-label">Total:</span> <span>%s</span></div>
				</div>',
				esc_html( $tokens['{guest_name}'] ),
				esc_html( $tokens['{site_name}'] ),
				esc_html( $tokens['{booking_reference}'] ),
				esc_html( $tokens['{check_in}'] ),
				esc_html( $tokens['{check_out}'] ),
				esc_html( $tokens['{room_name}'] ),
				esc_html( $tokens['{booking_total}'] )
			);

			$this->send_email( $booking->customer_email, $subject, $body, __( 'Reservation Confirmed', 'wpcalibrate-stayreserve' ) );
		}
	}

	/**
	 * Handle cancellation email.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param bool   $is_customer Whether customer initiated.
	 * @param string $reason Cancellation reason.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id, bool $is_customer, string $reason ): void {
		if ( ! SettingsManager::get( 'emails', 'notify_customer_cancelled', true ) ) {
			return;
		}

		$repo    = new BookingRepository();
		$booking = $repo->get_by_id( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$tokens  = EmailTemplate::get_tokens( $booking );
		$subject = sprintf( 'Reservation Cancelled - %s', $tokens['{booking_reference}'] );
		$body    = sprintf(
			'<p>Dear %s,</p>
			<p>Your reservation <strong>%s</strong> for %s to %s has been cancelled.</p>
			%s',
			esc_html( $tokens['{guest_name}'] ),
			esc_html( $tokens['{booking_reference}'] ),
			esc_html( $tokens['{check_in}'] ),
			esc_html( $tokens['{check_out}'] ),
			$reason ? '<p><em>Reason: ' . esc_html( $reason ) . '</em></p>' : ''
		);

		$this->send_email( $booking->customer_email, $subject, $body, __( 'Reservation Cancelled', 'wpcalibrate-stayreserve' ) );
	}

	/**
	 * Send an email safely using WordPress mail API.
	 *
	 * Failure to send does NOT throw or abort the transaction.
	 *
	 * @param string $to Recipient address.
	 * @param string $subject Email subject.
	 * @param string $content HTML content.
	 * @param string $heading Heading title for wrapper.
	 * @return bool True if accepted for delivery, false otherwise.
	 */
	private function send_email( string $to, string $subject, string $content, string $heading ): bool {
		$sender_name  = (string) SettingsManager::get( 'emails', 'sender_name', get_bloginfo( 'name' ) );
		$sender_email = (string) SettingsManager::get( 'emails', 'sender_email', get_bloginfo( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $sender_name, $sender_email ),
		);

		$html_body = EmailTemplate::wrap_html( $content, $heading );

		try {
			$sent = wp_mail( $to, $subject, $html_body, $headers );
			if ( ! $sent ) {
				$this->logger->warning(
					sprintf( 'Failed to send transactional email to %s: subject "%s"', $to, $subject )
				);
			}
			return $sent;
		} catch ( \Throwable $e ) {
			$this->logger->error(
				sprintf( 'Exception while sending email to %s: %s', $to, $e->getMessage() )
			);
			return false;
		}
	}
}
