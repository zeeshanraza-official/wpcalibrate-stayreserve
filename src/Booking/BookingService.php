<?php
/**
 * Booking Service coordinating domain logic, concurrency locks, and state transitions.
 *
 * @package WPCalibrate\StayReserve\Booking
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Booking;

use WPCalibrate\StayReserve\Availability\AvailabilityEngine;
use WPCalibrate\StayReserve\Availability\ConcurrencyLocker;
use WPCalibrate\StayReserve\Inventory\HoldManager;
use WPCalibrate\StayReserve\Logging\Logger;
use WPCalibrate\StayReserve\Pricing\PricingEngine;
use WPCalibrate\StayReserve\Security\TokenManager;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingService
 */
class BookingService {

	private BookingRepository $repository;
	private AvailabilityEngine $availability;
	private ConcurrencyLocker $concurrency;
	private PricingEngine $pricing;
	private HoldManager $hold_manager;
	private Logger $logger;

	/**
	 * Constructor.
	 */
	public function __construct(
		BookingRepository $repository,
		AvailabilityEngine $availability,
		ConcurrencyLocker $concurrency,
		PricingEngine $pricing,
		HoldManager $hold_manager,
		Logger $logger
	) {
		$this->repository   = $repository;
		$this->availability = $availability;
		$this->concurrency  = $concurrency;
		$this->pricing      = $pricing;
		$this->hold_manager = $hold_manager;
		$this->logger       = $logger;
	}

	/**
	 * Create a reservation with strict concurrency protection.
	 *
	 * @param array{
	 *     room_type_id: int,
	 *     check_in: string,
	 *     check_out: string,
	 *     room_unit_id?: int|null,
	 *     hold_token?: string|null,
	 *     adults?: int,
	 *     children?: int,
	 *     rooms?: int,
	 *     customer_first_name: string,
	 *     customer_last_name: string,
	 *     customer_email: string,
	 *     customer_phone?: string,
	 *     customer_address?: string|null,
	 *     customer_company?: string|null,
	 *     customer_note?: string|null,
	 *     admin_notes?: string|null,
	 *     user_id?: int|null,
	 *     status?: string,
	 *     payment_status?: string,
	 *     wc_order_id?: int|null,
	 *     source?: string,
	 *     extras?: array,
	 *     coupon_code?: string|null
	 * } $data Reservation payload.
	 * @return BookingModel
	 * @throws \RuntimeException If availability fails, lock fails, or persistence fails.
	 */
	public function create_booking( array $data ): BookingModel {
		$room_type_id = (int) $data['room_type_id'];
		$check_in     = DateTimeUtil::format_date( $data['check_in'] );
		$check_out    = DateTimeUtil::format_date( $data['check_out'] );
		$hold_token   = ! empty( $data['hold_token'] ) ? (string) $data['hold_token'] : null;

		if ( $check_out <= $check_in ) {
			throw new \RuntimeException( esc_html__( 'Check-out date must be strictly after check-in date.', 'wpcalibrate-stayreserve' ) );
		}

		$adults   = max( 1, (int) ( $data['adults'] ?? 1 ) );
		$children = max( 0, (int) ( $data['children'] ?? 0 ) );
		$rooms    = max( 1, (int) ( $data['rooms'] ?? 1 ) );

		// Hook before quote calculation
		do_action( 'wpcsr_before_booking_quote', $room_type_id, $check_in, $check_out );

		// Authoritative server-side quote calculation
		$quote = $this->pricing->calculate_quote(
			$room_type_id,
			$check_in,
			$check_out,
			$rooms,
			$adults,
			$children,
			(array) ( $data['extras'] ?? array() ),
			! empty( $data['coupon_code'] ) ? (string) $data['coupon_code'] : null
		);

		// Determine target physical unit
		$target_unit_id = ! empty( $data['room_unit_id'] ) ? (int) $data['room_unit_id'] : null;

		// Concurrency protection: allocate & commit under lock
		$booking = null;

		// If a unit is specified, lock on it. Otherwise find available unit and lock on it
		if ( ! $target_unit_id ) {
			$available_units = $this->availability->get_available_units( $room_type_id, $check_in, $check_out, null, $hold_token );
			if ( empty( $available_units ) ) {
				throw new \RuntimeException( esc_html__( 'The selected accommodation is no longer available for these dates. Please choose another option.', 'wpcalibrate-stayreserve' ) );
			}
			$target_unit_id = (int) $available_units[0]['id'];
		}

		$booking = $this->concurrency->with_unit_lock(
			$target_unit_id,
			function () use ( $target_unit_id, $room_type_id, $check_in, $check_out, $hold_token, $data, $quote, $adults, $children, $rooms ) {
				// Re-verify availability inside the critical section
				if ( ! $this->availability->is_unit_available( $target_unit_id, $check_in, $check_out, null, $hold_token ) ) {
					throw new \RuntimeException(
						esc_html__( 'The selected room was just reserved by another guest. Please select another available option.', 'wpcalibrate-stayreserve' )
					);
				}

				// Build Model
				$raw_mgmt_token = TokenManager::generate_token( 32 );
				$model          = new BookingModel();

				$current_uid = get_current_user_id();
				$model->user_id                     = ! empty( $data['user_id'] ) ? (int) $data['user_id'] : ( $current_uid > 0 ? $current_uid : null );
				$model->status                      = $data['status'] ?? ( SettingsManager::get( 'general', 'booking_mode' ) === 'manual_approval' ? BookingStatus::PENDING : BookingStatus::CONFIRMED );
				$model->payment_status              = $data['payment_status'] ?? PaymentStatus::UNPAID;
				$model->check_in                    = $check_in;
				$model->check_out                   = $check_out;
				$model->adults                      = $adults;
				$model->children                    = $children;
				$model->currency                    = $quote['currency'];
				$model->accommodation_subtotal      = $quote['accommodation_subtotal'];
				$model->extras_total                = $quote['extras_total'];
				$model->discount_total              = $quote['discount_total'];
				$model->fee_total                   = $quote['fee_total'];
				$model->tax_total                   = $quote['tax_total'];
				$model->grand_total                 = $quote['grand_total'];
				$model->customer_first_name         = sanitize_text_field( $data['customer_first_name'] );
				$model->customer_last_name          = sanitize_text_field( $data['customer_last_name'] );
				$model->customer_email              = sanitize_email( $data['customer_email'] );
				$model->customer_phone              = sanitize_text_field( $data['customer_phone'] ?? '' );
				$model->customer_address            = isset( $data['customer_address'] ) ? sanitize_textarea_field( $data['customer_address'] ) : null;
				$model->customer_company            = isset( $data['customer_company'] ) ? sanitize_text_field( $data['customer_company'] ) : null;
				$model->customer_note               = isset( $data['customer_note'] ) ? sanitize_textarea_field( $data['customer_note'] ) : null;
				$model->admin_notes                 = isset( $data['admin_notes'] ) ? sanitize_textarea_field( $data['admin_notes'] ) : null;
				$model->wc_order_id                 = ! empty( $data['wc_order_id'] ) ? (int) $data['wc_order_id'] : null;
				$model->source                      = sanitize_text_field( $data['source'] ?? 'website' );
				$model->management_token_hash       = TokenManager::hash_token( $raw_mgmt_token );
				$model->management_token_expires_at = TokenManager::get_expiration_datetime( 60 );

				// Unit item
				$model->items = array(
					array(
						'room_type_id'     => $room_type_id,
						'room_unit_id'     => $target_unit_id,
						'quantity'         => $rooms,
						'adults'           => $adults,
						'children'         => $children,
						'nightly_subtotal' => (int) round( $quote['accommodation_subtotal'] / max( 1, $quote['nights'] ) ),
						'line_total'       => $quote['accommodation_subtotal'],
					),
				);

				// Extras
				$model->extras = $quote['extras'];

				// Persist
				$booking_id = $this->repository->create( $model );
				if ( ! $booking_id ) {
					throw new \RuntimeException( esc_html__( 'Failed to record reservation in database.', 'wpcalibrate-stayreserve' ) );
				}

				// Store raw token on transient for immediate return to customer
				set_transient( 'wpcsr_raw_token_' . $booking_id, $raw_mgmt_token, 300 );

				// Release hold if one was used
				if ( ! empty( $hold_token ) ) {
					$this->hold_manager->release_hold( $hold_token );
				}

				// Log event
				$this->repository->add_event(
					$booking_id,
					'booking_created',
					sprintf( 'Booking %s created with status %s', $model->public_reference, $model->status ),
					array( 'unit_id' => $target_unit_id, 'grand_total' => $model->grand_total )
				);

				return $model;
			}
		);

		// Record coupon usage if applied
		if ( ! empty( $quote['coupon']['code'] ) ) {
			$coupon_manager = new \WPCalibrate\StayReserve\Coupons\CouponManager();
			$coupon_manager->record_usage( $quote['coupon']['code'] );
		}

		// Trigger notifications safely
		do_action( 'wpcsr_booking_created', $booking->id, $booking );

		return $booking;
	}

	/**
	 * Transition booking status safely.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $new_status Target status.
	 * @param string|null $reason Optional audit reason.
	 * @return bool
	 */
	public function transition_status( int $booking_id, string $new_status, ?string $reason = null ): bool {
		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$old_status = $booking->status;
		if ( $old_status === $new_status ) {
			return true;
		}

		// If restoring from cancelled to confirmed, revalidate availability!
		if ( ! BookingStatus::reserves_inventory( $old_status ) && BookingStatus::reserves_inventory( $new_status ) ) {
			foreach ( $booking->items as $item ) {
				$unit_id = (int) $item['room_unit_id'];
				if ( ! $this->availability->is_unit_available( $unit_id, $booking->check_in, $booking->check_out, $booking_id ) ) {
					$this->logger->error(
						sprintf( 'Cannot transition booking #%d to %s: unit #%d is no longer available.', $booking_id, $new_status, $unit_id )
					);
					return false;
				}
			}
		}

		$updated = $this->repository->update( $booking_id, array( 'status' => $new_status ) );
		if ( $updated ) {
			$this->repository->add_event(
				$booking_id,
				'status_transition',
				sprintf( 'Status transitioned from %s to %s', $old_status, $new_status ),
				array( 'reason' => $reason )
			);

			do_action( 'wpcsr_booking_status_changed', $booking_id, $new_status, $old_status );
		}

		return $updated;
	}

	/**
	 * Safely update booking dates with inventory revalidation.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_check_in New check-in date.
	 * @param string $new_check_out New check-out date.
	 * @return bool
	 */
	public function update_booking_dates( int $booking_id, string $new_check_in, string $new_check_out ): bool {
		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$clean_in  = DateTimeUtil::format_date( $new_check_in );
		$clean_out = DateTimeUtil::format_date( $new_check_out );

		if ( $clean_out <= $clean_in ) {
			return false;
		}

		// Revalidate availability for each unit, explicitly excluding this booking's current reservation
		foreach ( $booking->items as $item ) {
			$unit_id = (int) $item['room_unit_id'];
			if ( ! $this->availability->is_unit_available( $unit_id, $clean_in, $clean_out, $booking_id ) ) {
				return false;
			}
		}

		// Recalculate quote
		$first_item   = $booking->items[0] ?? array();
		$room_type_id = (int) ( $first_item['room_type_id'] ?? 0 );

		$quote = $this->pricing->calculate_quote(
			$room_type_id,
			$clean_in,
			$clean_out,
			count( $booking->items ),
			$booking->adults,
			$booking->children
		);

		$update_data = array(
			'check_in'               => $clean_in,
			'check_out'              => $clean_out,
			'accommodation_subtotal' => $quote['accommodation_subtotal'],
			'grand_total'            => $quote['grand_total'],
		);

		$success = $this->repository->update( $booking_id, $update_data );
		if ( $success ) {
			$this->repository->add_event(
				$booking_id,
				'dates_modified',
				sprintf( 'Stay dates changed to %s - %s', $clean_in, $clean_out ),
				array( 'previous_in' => $booking->check_in, 'previous_out' => $booking->check_out )
			);
		}

		return $success;
	}

	/**
	 * Cancel a reservation and release inventory.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $reason Cancellation reason.
	 * @param bool   $is_customer Whether cancelled by customer.
	 * @return bool
	 */
	public function cancel_booking( int $booking_id, string $reason = '', bool $is_customer = false ): bool {
		$booking = $this->repository->get_by_id( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$success = $this->transition_status( $booking_id, BookingStatus::CANCELLED, $reason );
		if ( $success ) {
			do_action( 'wpcsr_booking_cancelled', $booking_id, $is_customer, $reason );
		}

		return $success;
	}
}
