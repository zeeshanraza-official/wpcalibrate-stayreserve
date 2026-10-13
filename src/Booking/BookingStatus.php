<?php
/**
 * Centralized Booking Status Definitions and Transitions.
 *
 * @package WPCalibrate\StayReserve\Booking
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Booking;

use WPCalibrate\StayReserve\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingStatus
 */
class BookingStatus {

	public const PENDING          = 'pending';
	public const ON_HOLD          = 'on_hold';
	public const AWAITING_PAYMENT = 'awaiting_payment';
	public const CONFIRMED        = 'confirmed';
	public const CANCELLED        = 'cancelled';
	public const COMPLETED        = 'completed';
	public const FAILED           = 'failed';
	public const REFUNDED         = 'refunded';

	/**
	 * Get all registered booking statuses with localized labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_all(): array {
		return array(
			self::PENDING          => _x( 'Pending', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::ON_HOLD          => _x( 'On Hold', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::AWAITING_PAYMENT => _x( 'Awaiting Payment', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::CONFIRMED        => _x( 'Confirmed', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::CANCELLED        => _x( 'Cancelled', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::COMPLETED        => _x( 'Completed', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::FAILED           => _x( 'Failed', 'Booking status', 'wpcalibrate-stayreserve' ),
			self::REFUNDED         => _x( 'Refunded', 'Booking status', 'wpcalibrate-stayreserve' ),
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

	/**
	 * Get statuses that reserve room inventory.
	 *
	 * @return string[]
	 */
	public static function get_inventory_reserving_statuses(): array {
		$configured = SettingsManager::get(
			'inventory',
			'inventory_reserving_statuses',
			array( self::PENDING, self::ON_HOLD, self::AWAITING_PAYMENT, self::CONFIRMED )
		);
		return is_array( $configured ) ? $configured : array( self::PENDING, self::ON_HOLD, self::AWAITING_PAYMENT, self::CONFIRMED );
	}

	/**
	 * Check whether a status claims room inventory.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	public static function reserves_inventory( string $status ): bool {
		return in_array( $status, self::get_inventory_reserving_statuses(), true );
	}

	/**
	 * Check whether a status represents an active or finalized booking.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	public static function is_active( string $status ): bool {
		return ! in_array( $status, array( self::CANCELLED, self::FAILED, self::REFUNDED ), true );
	}
}
