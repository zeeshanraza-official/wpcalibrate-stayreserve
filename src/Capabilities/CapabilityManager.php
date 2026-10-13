<?php
/**
 * Capability and Role Management for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Capabilities
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Capabilities;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CapabilityManager
 */
class CapabilityManager {

	public const CAP_MANAGE_SETTINGS     = 'wpcsr_manage_settings';
	public const CAP_MANAGE_ROOMS        = 'wpcsr_manage_rooms';
	public const CAP_MANAGE_RATES        = 'wpcsr_manage_rates';
	public const CAP_MANAGE_AVAILABILITY = 'wpcsr_manage_availability';
	public const CAP_VIEW_BOOKINGS       = 'wpcsr_view_bookings';
	public const CAP_CREATE_BOOKINGS     = 'wpcsr_create_bookings';
	public const CAP_EDIT_BOOKINGS       = 'wpcsr_edit_bookings';
	public const CAP_CANCEL_BOOKINGS     = 'wpcsr_cancel_bookings';
	public const CAP_VIEW_CUSTOMER_DATA  = 'wpcsr_view_customer_data';
	public const CAP_MANAGE_INTEGRATIONS = 'wpcsr_manage_integrations';
	public const CAP_VIEW_LOGS           = 'wpcsr_view_logs';

	public const ROLE_HOTEL_MANAGER      = 'wpcsr_hotel_manager';

	/**
	 * Get all plugin-defined capabilities.
	 *
	 * @return string[]
	 */
	public static function get_all_capabilities(): array {
		return array(
			self::CAP_MANAGE_SETTINGS,
			self::CAP_MANAGE_ROOMS,
			self::CAP_MANAGE_RATES,
			self::CAP_MANAGE_AVAILABILITY,
			self::CAP_VIEW_BOOKINGS,
			self::CAP_CREATE_BOOKINGS,
			self::CAP_EDIT_BOOKINGS,
			self::CAP_CANCEL_BOOKINGS,
			self::CAP_VIEW_CUSTOMER_DATA,
			self::CAP_MANAGE_INTEGRATIONS,
			self::CAP_VIEW_LOGS,
		);
	}

	/**
	 * Get capabilities granted to Hotel Manager role.
	 *
	 * @return string[]
	 */
	public static function get_hotel_manager_capabilities(): array {
		return array(
			self::CAP_MANAGE_ROOMS,
			self::CAP_MANAGE_RATES,
			self::CAP_MANAGE_AVAILABILITY,
			self::CAP_VIEW_BOOKINGS,
			self::CAP_CREATE_BOOKINGS,
			self::CAP_EDIT_BOOKINGS,
			self::CAP_CANCEL_BOOKINGS,
			self::CAP_VIEW_CUSTOMER_DATA,
			'read', // Standard WP read capability
		);
	}

	/**
	 * Register capabilities to administrator and create Hotel Manager role.
	 *
	 * @return void
	 */
	public static function register_capabilities(): void {
		// 1. Grant all capabilities to Administrator
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}

		// 2. Create or update Hotel Manager role
		$manager_role = get_role( self::ROLE_HOTEL_MANAGER );
		if ( ! $manager_role ) {
			$manager_caps = array();
			foreach ( self::get_hotel_manager_capabilities() as $cap ) {
				$manager_caps[ $cap ] = true;
			}
			add_role(
				self::ROLE_HOTEL_MANAGER,
				__( 'Hotel Manager', 'wpcalibrate-stayreserve' ),
				$manager_caps
			);
		} else {
			foreach ( self::get_hotel_manager_capabilities() as $cap ) {
				$manager_role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove capabilities and Hotel Manager role on complete uninstall.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}

		remove_role( self::ROLE_HOTEL_MANAGER );
	}

	/**
	 * Helper to check current user capability with fallback.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public static function can( string $capability ): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( $capability ) || current_user_can( 'manage_options' );
	}
}
