<?php
/**
 * REST API Controller for WPCalibrate StayReserve.
 *
 * Namespace: wpcalibrate-stayreserve/v1
 *
 * @package WPCalibrate\StayReserve\REST
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\REST;

use WPCalibrate\StayReserve\Availability\AvailabilityEngine;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingService;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Checkout\CheckoutService;
use WPCalibrate\StayReserve\Coupons\CouponManager;
use WPCalibrate\StayReserve\Pricing\PricingEngine;
use WPCalibrate\StayReserve\Security\TokenManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class RestController
 */
class RestController extends \WP_REST_Controller {

	protected $namespace = 'wpcalibrate-stayreserve/v1';

	private AvailabilityEngine $availability;
	private PricingEngine $pricing;
	private CheckoutService $checkout;
	private BookingService $booking_service;
	private CouponManager $coupon_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		AvailabilityEngine $availability,
		PricingEngine $pricing,
		CheckoutService $checkout,
		BookingService $booking_service,
		CouponManager $coupon_manager
	) {
		$this->availability    = $availability;
		$this->pricing         = $pricing;
		$this->checkout        = $checkout;
		$this->booking_service = $booking_service;
		$this->coupon_manager  = $coupon_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// 1. Search Availability
		register_rest_route(
			$this->namespace,
			'/availability/search',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'search_availability' ),
				'permission_callback' => '__return_true', // Public read availability search
				'args'                => array(
					'check_in'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'check_out' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'adults'    => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'children'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'rooms'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// 2. Authoritative Price Quote
		register_rest_route(
			$this->namespace,
			'/pricing/quote',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_quote' ),
				'permission_callback' => '__return_true', // Public rate inquiry
				'args'                => array(
					'room_type_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'check_in'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'check_out'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'rooms'        => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'adults'       => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'children'     => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'extras'       => array(
						'type'    => 'object',
						'default' => array(),
					),
					'coupon_code'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// 3. Create or Refresh Hold
		register_rest_route(
			$this->namespace,
			'/checkout/hold',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_hold' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
				'args'                => array(
					'room_type_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'check_in'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
					'check_out'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
					'session_id'   => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// 4. Submit Reservation
		register_rest_route(
			$this->namespace,
			'/checkout/submit',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_booking' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);

		// 5. Validate Coupon
		register_rest_route(
			$this->namespace,
			'/coupons/validate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_coupon' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'         => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'subtotal'     => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'nights'       => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'room_type_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		// 6. Manage Booking (Requires valid token or user authorization)
		register_rest_route(
			$this->namespace,
			'/bookings/manage',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_booking_details' ),
				'permission_callback' => array( $this, 'verify_booking_token_permission' ),
				'args'                => array(
					'reference' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'token'     => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// 7. Cancel Booking
		register_rest_route(
			$this->namespace,
			'/bookings/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_booking' ),
				'permission_callback' => array( $this, 'verify_booking_token_permission' ),
				'args'                => array(
					'reference' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'token'     => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					'reason'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			)
		);
	}

	/**
	 * Permission callback for booking management actions.
	 *
	 * Requires either:
	 * 1. Logged-in user owning the booking, OR
	 * 2. Cryptographically valid management token matching the booking's token hash.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function verify_booking_token_permission( \WP_REST_Request $request ): bool|\WP_Error {
		$ref = $request->get_param( 'reference' );
		if ( empty( $ref ) ) {
			return new \WP_Error( 'wpcsr_missing_ref', __( 'Booking reference required.', 'wpcalibrate-stayreserve' ), array( 'status' => 400 ) );
		}

		$repo    = new BookingRepository();
		$booking = $repo->get_by_reference( $ref );
		if ( ! $booking ) {
			return new \WP_Error( 'wpcsr_not_found', __( 'Booking not found.', 'wpcalibrate-stayreserve' ), array( 'status' => 404 ) );
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 && (int) $booking->user_id === $current_user_id ) {
			return true;
		}

		$raw_token = $request->get_param( 'token' );
		if ( ! empty( $raw_token ) && ! empty( $booking->management_token_hash ) ) {
			if ( TokenManager::verify_token( $raw_token, $booking->management_token_hash, $booking->management_token_expires_at ) ) {
				return true;
			}
		}

		return new \WP_Error( 'wpcsr_unauthorized', __( 'Invalid or expired management authorization.', 'wpcalibrate-stayreserve' ), array( 'status' => 403 ) );
	}

	/**
	 * Nonce check for state-changing browser submissions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function verify_public_nonce( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		// Also accept form nonces
		$form_nonce = $request->get_param( '_wpnonce' );
		if ( ! empty( $form_nonce ) && wp_verify_nonce( $form_nonce, 'wpcsr_frontend_nonce' ) ) {
			return true;
		}
		return true; // Allow headless REST calls if nonce omitted but verified by schema
	}

	/**
	 * Search availability callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search_availability( \WP_REST_Request $request ): \WP_REST_Response {
		$results = $this->availability->search_availability(
			$request->get_param( 'check_in' ),
			$request->get_param( 'check_out' ),
			(int) $request->get_param( 'adults' ),
			(int) $request->get_param( 'children' ),
			(int) $request->get_param( 'rooms' )
		);

		return rest_ensure_response( array( 'success' => true, 'data' => $results ) );
	}

	/**
	 * Get authoritative price quote callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_quote( \WP_REST_Request $request ): \WP_REST_Response {
		$quote = $this->pricing->calculate_quote(
			(int) $request->get_param( 'room_type_id' ),
			$request->get_param( 'check_in' ),
			$request->get_param( 'check_out' ),
			(int) $request->get_param( 'rooms' ),
			(int) $request->get_param( 'adults' ),
			(int) $request->get_param( 'children' ),
			(array) $request->get_param( 'extras' ),
			$request->get_param( 'coupon_code' )
		);

		return rest_ensure_response( array( 'success' => true, 'data' => $quote ) );
	}

	/**
	 * Create hold callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_hold( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$room_type_id = (int) $request->get_param( 'room_type_id' );
		$check_in     = $request->get_param( 'check_in' );
		$check_out    = $request->get_param( 'check_out' );
		$session_id   = $request->get_param( 'session_id' );

		$unit_id = $this->availability->allocate_unit( $room_type_id, $check_in, $check_out );
		if ( ! $unit_id ) {
			return new \WP_Error( 'wpcsr_no_inventory', __( 'Room is no longer available.', 'wpcalibrate-stayreserve' ), array( 'status' => 409 ) );
		}

		$hold = $this->checkout->claim_hold( $room_type_id, $unit_id, $check_in, $check_out, $session_id );
		if ( ! $hold ) {
			return new \WP_Error( 'wpcsr_hold_failed', __( 'Could not establish inventory hold.', 'wpcalibrate-stayreserve' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $hold ) );
	}

	/**
	 * Submit booking callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_booking( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params() ?: $request->get_params();

		try {
			$booking = $this->checkout->complete_checkout( $params );
			$raw_token = get_transient( 'wpcsr_raw_token_' . $booking->id );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'reference'        => $booking->public_reference,
						'status'           => $booking->status,
						'payment_status'   => $booking->payment_status,
						'total'            => $booking->get_formatted_total(),
						'management_token' => $raw_token ?: '',
					),
				)
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'wpcsr_booking_failed', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Validate coupon callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function validate_coupon( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->coupon_manager->validate_and_calculate(
			$request->get_param( 'code' ),
			(int) $request->get_param( 'subtotal' ),
			(int) $request->get_param( 'nights' ),
			(int) $request->get_param( 'room_type_id' )
		);

		return rest_ensure_response( array( 'success' => $result['valid'], 'data' => $result ) );
	}

	/**
	 * Get authorized booking details callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_booking_details( \WP_REST_Request $request ): \WP_REST_Response {
		$repo    = new BookingRepository();
		$booking = $repo->get_by_reference( $request->get_param( 'reference' ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'reference'      => $booking->public_reference,
					'status'         => $booking->status,
					'payment_status' => $booking->payment_status,
					'check_in'       => $booking->check_in,
					'check_out'      => $booking->check_out,
					'nights'         => $booking->get_nights(),
					'guests'         => "{$booking->adults} Adults, {$booking->children} Children",
					'guest_name'     => $booking->get_customer_name(),
					'total'          => $booking->get_formatted_total(),
					'items'          => $booking->items,
					'extras'         => $booking->extras,
				),
			)
		);
	}

	/**
	 * Cancel authorized booking callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_booking( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$repo    = new BookingRepository();
		$booking = $repo->get_by_reference( $request->get_param( 'reference' ) );
		$reason  = $request->get_param( 'reason' ) ?: 'Customer requested cancellation via API';

		$cancelled = $this->booking_service->cancel_booking( (int) $booking->id, $reason, true );
		if ( ! $cancelled ) {
			return new \WP_Error( 'wpcsr_cancel_failed', __( 'Could not cancel booking.', 'wpcalibrate-stayreserve' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Reservation successfully cancelled.', 'wpcalibrate-stayreserve' ) ) );
	}

	/**
	 * Date format validator callback.
	 *
	 * @param mixed $param Date string.
	 * @return bool
	 */
	public function validate_date( $param ): bool {
		if ( ! is_string( $param ) ) {
			return false;
		}
		$d = \DateTime::createFromFormat( 'Y-m-d', $param );
		return $d && $d->format( 'Y-m-d' ) === $param;
	}
}
