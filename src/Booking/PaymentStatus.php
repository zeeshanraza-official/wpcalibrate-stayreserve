<?php
/**
 * Payment Status Definitions for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Booking
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Booking;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class PaymentStatus
 */
class PaymentStatus {

	public const NOT_REQUIRED       = 'not_required';
	public const UNPAID             = 'unpaid';
	public const PENDING            = 'pending';
	public const PARTIALLY_PAID     = 'partially_paid';
	public const PAID               = 'paid';
	public const FAILED             = 'failed';
	public const REFUNDED           = 'refunded';
	public const PARTIALLY_REFUNDED = 'partially_refunded';

	/**
	 * Get all payment statuses with localized labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_all(): array {
		return array(
			self::NOT_REQUIRED       => _x( 'Not Required', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::UNPAID             => _x( 'Unpaid', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::PENDING            => _x( 'Pending', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::PARTIALLY_PAID     => _x( 'Partially Paid', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::PAID               => _x( 'Paid', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::FAILED             => _x( 'Failed', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::REFUNDED           => _x( 'Refunded', 'Payment status', 'wpcalibrate-stayreserve' ),
			self::PARTIALLY_REFUNDED => _x( 'Partially Refunded', 'Payment status', 'wpcalibrate-stayreserve' ),
		);
	}

	/**
	 * Get human readable label for status.
	 *
	 * @param string $status Status key.
	 * @return string Localized label.
	 */
	public static function get_label( string $status ): string {
		$all = self::get_all();
		return $all[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}
}
