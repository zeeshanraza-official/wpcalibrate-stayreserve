<?php
/**
 * Settings Manager for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Settings
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Settings;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class SettingsManager
 */
class SettingsManager {

	public const OPTION_NAME = 'wpcsr_settings';

	/**
	 * Default plugin configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'general'      => array(
				'currency'              => 'USD',
				'default_checkin_time'  => '14:00',
				'default_checkout_time' => '11:00',
				'min_advance_days'      => 0,
				'max_advance_days'      => 365,
				'default_min_stay'      => 1,
				'default_max_stay'      => 30,
				'booking_mode'          => 'instant', // 'instant', 'manual_approval', 'payment_required'
				'offline_instructions'  => __( 'Payment is due upon arrival. Please contact us if you need to make special arrangements.', 'wpcalibrate-stayreserve' ),
			),
			'inventory'    => array(
				'hold_duration_minutes'        => 15,
				'enable_holds'                 => true,
				'inventory_reserving_statuses' => array( 'pending', 'on_hold', 'awaiting_payment', 'confirmed' ),
			),
			'pricing'      => array(
				'currency'       => 'USD',
				'enable_taxes'   => false,
				'tax_rate'       => 0.0, // e.g. 10.0 for 10%
				'tax_label'      => __( 'Accommodation Tax', 'wpcalibrate-stayreserve' ),
				'enable_coupons' => true,
			),
			'guest'        => array(
				'allow_guest_checkout' => true,
				'require_phone'        => true,
				'require_address'      => false,
				'require_company'      => false,
			),
			'emails'       => array(
				'sender_name'     => get_bloginfo( 'name' ),
				'sender_email'    => get_bloginfo( 'admin_email' ),
				'admin_recipient' => get_bloginfo( 'admin_email' ),
				'notify_admin_new_booking'     => true,
				'notify_customer_booking_rcvd' => true,
				'notify_customer_confirmed'    => true,
				'notify_customer_cancelled'    => true,
			),
			'pages'        => array(
				'search_page_id'     => 0,
				'booking_page_id'    => 0,
				'manage_booking_id'  => 0,
				'terms_page_id'      => 0,
			),
			'integrations' => array(
				'enable_woocommerce' => false,
			),
			'advanced'     => array(
				'log_level'             => 'info', // debug, info, warning, error
				'uninstall_delete_data' => false,
			),
		);
	}

	/**
	 * Initialize default settings if not already present.
	 *
	 * @return void
	 */
	public static function initialize_defaults(): void {
		$existing = get_option( self::OPTION_NAME, null );
		if ( null === $existing || ! is_array( $existing ) ) {
			update_option( self::OPTION_NAME, self::get_defaults(), false );
		}
	}

	/**
	 * Get all merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$defaults = self::get_defaults();
		$stored   = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		$merged = array();
		foreach ( $defaults as $group => $settings ) {
			$merged[ $group ] = array_merge( $settings, (array) ( $stored[ $group ] ?? array() ) );
		}
		return $merged;
	}

	/**
	 * Get a specific configuration value.
	 *
	 * @param string $group Setting category.
	 * @param string $key Setting name.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $group, string $key, mixed $default = null ): mixed {
		$all = self::get_all();
		return $all[ $group ][ $key ] ?? $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $new_settings Settings array to merge and save.
	 * @return bool
	 */
	public static function update( array $new_settings ): bool {
		$current = self::get_all();

		foreach ( $new_settings as $group => $items ) {
			if ( is_array( $items ) && isset( $current[ $group ] ) ) {
				$current[ $group ] = array_merge( $current[ $group ], $items );
			}
		}

		return update_option( self::OPTION_NAME, $current, false );
	}
}
