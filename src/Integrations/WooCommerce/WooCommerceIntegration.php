<?php
/**
 * Optional WooCommerce Checkout & Payment Integration.
 *
 * @package WPCalibrate\StayReserve\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Integrations\WooCommerce;

use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingService;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Booking\PaymentStatus;
use WPCalibrate\StayReserve\Inventory\HoldManager;
use WPCalibrate\StayReserve\Logging\Logger;
use WPCalibrate\StayReserve\Pricing\PricingEngine;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class WooCommerceIntegration
 */
class WooCommerceIntegration {

	public const VIRTUAL_PRODUCT_OPTION = 'wpcsr_wc_virtual_product_id';
	public const CART_ITEM_KEY          = 'wpcsr_booking_data';
	public const ORDER_META_BOOKING_ID  = '_wpcsr_booking_id';

	private BookingService $booking_service;
	private HoldManager $hold_manager;
	private PricingEngine $pricing_engine;
	private Logger $logger;

	/**
	 * Constructor.
	 */
	public function __construct(
		BookingService $booking_service,
		HoldManager $hold_manager,
		PricingEngine $pricing_engine,
		Logger $logger
	) {
		$this->booking_service = $booking_service;
		$this->hold_manager    = $hold_manager;
		$this->pricing_engine  = $pricing_engine;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks if WooCommerce is present and integration enabled.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$is_enabled = (bool) SettingsManager::get( 'integrations', 'enable_woocommerce', false );
		if ( ! $is_enabled ) {
			return;
		}

		// Cart item pricing & display
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_price' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

		// Order creation & synchronization
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_checkout_order_processed' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
	}

	/**
	 * Declare High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPCSR_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WPCSR_FILE,
				true
			);
		}
	}

	/**
	 * Get or create the hidden virtual product for accommodation checkout.
	 *
	 * @return int Product ID.
	 */
	public function get_or_create_virtual_product(): int {
		$product_id = (int) get_option( self::VIRTUAL_PRODUCT_OPTION, 0 );

		if ( $product_id > 0 && get_post( $product_id ) ) {
			return $product_id;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		// Create virtual product using WooCommerce CRUD
		$product = new \WC_Product_Simple();
		$product->set_name( __( 'Accommodation Reservation', 'wpcalibrate-stayreserve' ) );
		$product->set_status( 'publish' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$new_id = $product->save();

		update_option( self::VIRTUAL_PRODUCT_OPTION, $new_id, false );
		return $new_id;
	}

	/**
	 * Add booking data to cart item.
	 *
	 * @param array $cart_item_data Existing item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id ): array {
		if ( isset( $_POST['wpcsr_booking_data'] ) ) {
			$raw_data = json_decode( sanitize_text_field( wp_unslash( $_POST['wpcsr_booking_data'] ) ), true );
			if ( is_array( $raw_data ) ) {
				$cart_item_data[ self::CART_ITEM_KEY ] = $raw_data;
			}
		}
		return $cart_item_data;
	}

	/**
	 * Override cart item price with authoritative server-calculated stay quote.
	 *
	 * @param \WC_Cart $cart WooCommerce Cart.
	 * @return void
	 */
	public function calculate_cart_item_price( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ self::CART_ITEM_KEY ] ) ) {
				$b_data       = $cart_item[ self::CART_ITEM_KEY ];
				$room_type_id = (int) ( $b_data['room_type_id'] ?? 0 );
				$check_in     = (string) ( $b_data['check_in'] ?? '' );
				$check_out    = (string) ( $b_data['check_out'] ?? '' );

				// Server-side authoritative quote
				$quote = $this->pricing_engine->calculate_quote(
					$room_type_id,
					$check_in,
					$check_out,
					(int) ( $b_data['rooms'] ?? 1 ),
					(int) ( $b_data['adults'] ?? 1 ),
					(int) ( $b_data['children'] ?? 0 ),
					(array) ( $b_data['extras'] ?? array() ),
					! empty( $b_data['coupon_code'] ) ? (string) $b_data['coupon_code'] : null
				);

				$decimal_price = MoneyUtil::minor_to_decimal( $quote['grand_total'], $quote['currency'] );
				$cart_item['data']->set_price( $decimal_price );
			}
		}
	}

	/**
	 * Display stay details in cart and checkout.
	 *
	 * @param array $item_data Display data.
	 * @param array $cart_item Cart item row.
	 * @return array
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( ! empty( $cart_item[ self::CART_ITEM_KEY ] ) ) {
			$b = $cart_item[ self::CART_ITEM_KEY ];
			$item_data[] = array(
				'key'   => __( 'Stay Dates', 'wpcalibrate-stayreserve' ),
				'value' => esc_html( "{$b['check_in']} — {$b['check_out']}" ),
			);
			$item_data[] = array(
				'key'   => __( 'Guests', 'wpcalibrate-stayreserve' ),
				'value' => esc_html( "{$b['adults']} Adults, {$b['children']} Children" ),
			);
		}
		return $item_data;
	}

	/**
	 * Copy booking metadata to order line item using HPOS-compatible CRUD.
	 *
	 * @param \WC_Order_Item_Product $item Line item.
	 * @param string                 $cart_item_key Cart key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order.
	 * @return void
	 */
	public function save_order_line_item_meta( $item, $cart_item_key, $values, $order ): void {
		if ( ! empty( $values[ self::CART_ITEM_KEY ] ) ) {
			$b = $values[ self::CART_ITEM_KEY ];
			$item->add_meta_data( __( 'Check-in', 'wpcalibrate-stayreserve' ), $b['check_in'] );
			$item->add_meta_data( __( 'Check-out', 'wpcalibrate-stayreserve' ), $b['check_out'] );
			$item->add_meta_data( '_wpcsr_booking_raw_data', wp_json_encode( $b ) );
		}
	}

	/**
	 * Synchronize booking record creation when WC checkout order is processed.
	 *
	 * @param int       $order_id WC Order ID.
	 * @param array     $posted_data Form data.
	 * @param \WC_Order $order WC Order.
	 * @return void
	 */
	public function on_checkout_order_processed( int $order_id, array $posted_data, $order ): void {
		foreach ( $order->get_items() as $item ) {
			$raw_json = $item->get_meta( '_wpcsr_booking_raw_data' );
			if ( ! empty( $raw_json ) ) {
				$b_data = json_decode( (string) $raw_json, true );
				if ( is_array( $b_data ) ) {
					$b_data['wc_order_id']         = $order_id;
					$b_data['customer_first_name'] = $order->get_billing_first_name();
					$b_data['customer_last_name']  = $order->get_billing_last_name();
					$b_data['customer_email']      = $order->get_billing_email();
					$b_data['customer_phone']      = $order->get_billing_phone();
					$b_data['status']              = BookingStatus::PENDING;
					$b_data['payment_status']      = PaymentStatus::PENDING;
					$b_data['source']              = 'woocommerce';

					try {
						$booking = $this->booking_service->create_booking( $b_data );
						// Store booking ID on WC Order using CRUD
						$order->update_meta_data( self::ORDER_META_BOOKING_ID, $booking->id );
						$order->save();
					} catch ( \Throwable $e ) {
						$this->logger->error(
							sprintf( 'Failed to create StayReserve booking for WC order #%d: %s', $order_id, $e->getMessage() )
						);
					}
				}
			}
		}
	}

	/**
	 * Synchronize order status changes (processing/completed/cancelled/refunded).
	 *
	 * Idempotent: safe if executed multiple times.
	 *
	 * @param int       $order_id Order ID.
	 * @param string    $old_status Previous status.
	 * @param string    $new_status New status.
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function on_order_status_changed( int $order_id, string $old_status, string $new_status, $order ): void {
		$booking_id = (int) $order->get_meta( self::ORDER_META_BOOKING_ID );
		if ( $booking_id <= 0 ) {
			return;
		}

		$repo    = new BookingRepository();
		$booking = $repo->get_by_id( $booking_id );
		if ( ! $booking ) {
			return;
		}

		if ( in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			$this->booking_service->transition_status( $booking_id, BookingStatus::CONFIRMED, 'WooCommerce payment received' );
			$repo->update( $booking_id, array( 'payment_status' => PaymentStatus::PAID ) );
		} elseif ( in_array( $new_status, array( 'cancelled', 'failed' ), true ) ) {
			$this->booking_service->transition_status( $booking_id, BookingStatus::CANCELLED, 'WooCommerce order cancelled/failed' );
			$repo->update( $booking_id, array( 'payment_status' => PaymentStatus::FAILED ) );
		} elseif ( 'refunded' === $new_status ) {
			$this->booking_service->transition_status( $booking_id, BookingStatus::REFUNDED, 'WooCommerce order refunded' );
			$repo->update( $booking_id, array( 'payment_status' => PaymentStatus::REFUNDED ) );
		}
	}
}
