<?php
/**
 * Licensing Service Adapter for WPCalibrate StayReserve.
 *
 * Notice: Licensing provider API details are pending supply.
 * This class provides a clean adapter interface without simulating remote activations or faking success.
 *
 * @package WPCalibrate\StayReserve\Integrations\Licensing
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Integrations\Licensing;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class LicenseService
 */
class LicenseService {

	public const OPTION_KEY = 'wpcsr_license_data';

	/**
	 * Get current stored license status.
	 *
	 * @return array<string, mixed>
	 */
	public function get_license_data(): array {
		$data = get_option(
			self::OPTION_KEY,
			array(
				'key'             => '',
				'status'          => 'unconnected',
				'expires_at'      => '',
				'last_checked'    => '',
				'renewal_url'     => '',
				'provider_status' => 'not_configured',
			)
		);
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get masked version of saved key (e.g. 'WPCSR-****-****-9F2B').
	 *
	 * @return string
	 */
	public function get_masked_key(): string {
		$data = $this->get_license_data();
		$key  = trim( (string) ( $data['key'] ?? '' ) );
		if ( empty( $key ) ) {
			return '';
		}

		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		$prefix = substr( $key, 0, 4 );
		$suffix = substr( $key, -4 );
		$masked = $prefix . str_repeat( '*', $len - 8 ) . $suffix;
		return $masked;
	}

	/**
	 * Save license key.
	 *
	 * @param string $key Plain license key.
	 * @return bool
	 */
	public function save_key( string $key ): bool {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_SETTINGS ) ) {
			return false;
		}

		$data        = $this->get_license_data();
		$data['key'] = sanitize_text_field( trim( $key ) );
		return update_option( self::OPTION_KEY, $data, false );
	}

	/**
	 * Attempt license activation.
	 *
	 * Since remote licensing provider contract has not been supplied,
	 * this returns an explicit informational response without simulating fake activation.
	 *
	 * @return array{ success: bool, message: string }
	 */
	public function activate_license(): array {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_SETTINGS ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied.', 'wpcalibrate-stayreserve' ),
			);
		}

		$data = $this->get_license_data();
		if ( empty( $data['key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid license key.', 'wpcalibrate-stayreserve' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Licensing server provider is currently not connected. No fake activation was performed. Please configure your licensing provider API endpoint in configuration.', 'wpcalibrate-stayreserve' ),
		);
	}

	/**
	 * Deactivate license.
	 *
	 * @return bool
	 */
	public function deactivate_license(): bool {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_SETTINGS ) ) {
			return false;
		}

		$data                    = $this->get_license_data();
		$data['status']          = 'unconnected';
		$data['provider_status'] = 'not_configured';
		return update_option( self::OPTION_KEY, $data, false );
	}
}
