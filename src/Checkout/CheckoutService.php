<?php
/**
 * Checkout Service orchestrating holds, quotes, and reservation finalization.
 *
 * @package WPCalibrate\StayReserve\Checkout
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Checkout;

use WPCalibrate\StayReserve\Booking\BookingModel;
use WPCalibrate\StayReserve\Booking\BookingService;
use WPCalibrate\StayReserve\Inventory\HoldManager;
use WPCalibrate\StayReserve\Pricing\PricingEngine;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CheckoutService
 */
class CheckoutService {

	private BookingService $booking_service;
	private HoldManager $hold_manager;
	private PricingEngine $pricing_engine;

	/**
	 * Constructor.
	 */
	public function __construct(
		BookingService $booking_service,
		HoldManager $hold_manager,
		PricingEngine $pricing_engine
	) {
		$this->booking_service = $booking_service;
		$this->hold_manager    = $hold_manager;
		$this->pricing_engine  = $pricing_engine;
	}

	/**
	 * Create temporary inventory hold for checkout.
	 *
	 * @param int    $room_type_id Room Type CPT ID.
	 * @param int    $room_unit_id Physical unit ID.
	 * @param string $check_in Check-in date.
	 * @param string $check_out Check-out date.
	 * @param string $session_id Session identifier.
	 * @return array{ token: string, expires_at: string, hold_id: int }|null
	 */
	public function claim_hold( int $room_type_id, int $room_unit_id, string $check_in, string $check_out, string $session_id ): ?array {
		return $this->hold_manager->create_hold( $room_type_id, $room_unit_id, $check_in, $check_out, $session_id );
	}

	/**
	 * Finalize checkout and commit reservation.
	 *
	 * @param array $payload Customer submission payload.
	 * @return BookingModel
	 */
	public function complete_checkout( array $payload ): BookingModel {
		$is_wc_enabled = (bool) SettingsManager::get( 'integrations', 'enable_woocommerce', false );

		if ( $is_wc_enabled && class_exists( 'WooCommerce' ) ) {
			// In WooCommerce mode, this is called after order creation or payment synchronization
			$payload['source'] = 'woocommerce';
		} else {
			$payload['source'] = 'website';
		}

		return $this->booking_service->create_booking( $payload );
	}
}
