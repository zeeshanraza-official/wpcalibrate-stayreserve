<?php
/**
 * Email Template renderer with safe token substitution.
 *
 * @package WPCalibrate\StayReserve\Email
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Email;

use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class EmailTemplate
 */
class EmailTemplate {

	/**
	 * Build token replacement dictionary from a booking.
	 *
	 * @param BookingModel $booking Booking model.
	 * @param string|null  $raw_token Raw guest management token.
	 * @return array<string, string>
	 */
	public static function get_tokens( BookingModel $booking, ?string $raw_token = null ): array {
		$room_title = __( 'Accommodation', 'wpcalibrate-stayreserve' );
		if ( ! empty( $booking->items ) ) {
			$post = get_post( (int) $booking->items[0]['room_type_id'] );
			if ( $post ) {
				$room_title = $post->post_title;
			}
		}

		// Build management URL
		$manage_url = home_url( '/manage-booking/' );
		if ( ! empty( $raw_token ) ) {
			$manage_url = add_query_arg(
				array(
					'ref'   => $booking->public_reference,
					'token' => $raw_token,
				),
				$manage_url
			);
		}

		return array(
			'{booking_reference}'  => $booking->public_reference,
			'{guest_name}'         => $booking->get_customer_name(),
			'{guest_email}'        => $booking->customer_email,
			'{check_in}'           => $booking->check_in,
			'{check_out}'          => $booking->check_out,
			'{nights}'             => (string) $booking->get_nights(),
			'{room_name}'          => $room_title,
			'{booking_total}'      => $booking->get_formatted_total(),
			'{booking_status}'     => BookingStatus::get_label( $booking->status ),
			'{manage_booking_url}' => esc_url_raw( $manage_url ),
			'{site_name}'          => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Render template string replacing placeholders with escaped values.
	 *
	 * @param string $template Text with placeholders.
	 * @param array  $tokens Replacement dictionary.
	 * @return string
	 */
	public static function render( string $template, array $tokens ): string {
		return str_replace( array_keys( $tokens ), array_values( $tokens ), $template );
	}

	/**
	 * Wrap body in responsive HTML email boilerplate.
	 *
	 * @param string $content HTML body content.
	 * @param string $heading Email heading.
	 * @return string Complete HTML document.
	 */
	public static function wrap_html( string $content, string $heading ): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$site_name}</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 24px; color: #2d3748; }
.card { background-color: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
.header { background-color: #0073aa; color: #ffffff; padding: 28px 24px; text-align: center; }
.header h1 { margin: 0; font-size: 22px; font-weight: 600; }
.body { padding: 32px 24px; line-height: 1.6; font-size: 15px; }
.details-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0; }
.details-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
.details-label { font-weight: 600; color: #4a5568; }
.footer { background-color: #f8fafc; text-align: center; padding: 16px 24px; font-size: 13px; color: #718096; border-top: 1px solid #e2e8f0; }
.button { display: inline-block; background-color: #0073aa; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin-top: 16px; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>{$heading}</h1>
  </div>
  <div class="body">
    {$content}
  </div>
  <div class="footer">
    &copy; {$site_name}. All rights reserved.
  </div>
</div>
</body>
</html>
HTML;
	}
}
