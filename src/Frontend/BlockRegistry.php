<?php
/**
 * Gutenberg Block Registration for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Frontend;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BlockRegistry
 */
class BlockRegistry {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register dynamic Gutenberg blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// 1. Availability Search Block
		register_block_type(
			'wpcalibrate-stayreserve/search',
			array(
				'title'           => __( 'StayReserve Search Bar', 'wpcalibrate-stayreserve' ),
				'description'     => __( 'Accommodation availability search form.', 'wpcalibrate-stayreserve' ),
				'category'        => 'widgets',
				'icon'            => 'search',
				'render_callback' => array( $this, 'render_search_block' ),
			)
		);

		// 2. Available Rooms Block
		register_block_type(
			'wpcalibrate-stayreserve/rooms',
			array(
				'title'           => __( 'StayReserve Available Rooms', 'wpcalibrate-stayreserve' ),
				'description'     => __( 'Grid of available room types and stay prices.', 'wpcalibrate-stayreserve' ),
				'category'        => 'widgets',
				'icon'            => 'grid-view',
				'render_callback' => array( $this, 'render_rooms_block' ),
			)
		);

		// 3. Complete Booking Wizard Block
		register_block_type(
			'wpcalibrate-stayreserve/booking',
			array(
				'title'           => __( 'StayReserve Booking Flow', 'wpcalibrate-stayreserve' ),
				'description'     => __( 'Multi-step reservation and checkout flow.', 'wpcalibrate-stayreserve' ),
				'category'        => 'widgets',
				'icon'            => 'calendar-alt',
				'render_callback' => array( $this, 'render_booking_block' ),
			)
		);

		// 4. Booking Management Portal Block
		register_block_type(
			'wpcalibrate-stayreserve/manage-booking',
			array(
				'title'           => __( 'StayReserve Manage Booking', 'wpcalibrate-stayreserve' ),
				'description'     => __( 'Customer booking management portal.', 'wpcalibrate-stayreserve' ),
				'category'        => 'widgets',
				'icon'            => 'id-alt',
				'render_callback' => array( $this, 'render_manage_block' ),
			)
		);
	}

	public function render_search_block(): string {
		return do_shortcode( '[wpcsr_search]' );
	}

	public function render_rooms_block(): string {
		return do_shortcode( '[wpcsr_rooms]' );
	}

	public function render_booking_block(): string {
		return do_shortcode( '[wpcsr_booking]' );
	}

	public function render_manage_block(): string {
		return do_shortcode( '[wpcsr_manage_booking]' );
	}
}
