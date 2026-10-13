<?php
/**
 * Frontend Shortcodes for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Frontend;

use WPCalibrate\StayReserve\Plugin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Shortcodes
 */
class Shortcodes {

	/**
	 * Register WordPress shortcodes.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'wpcsr_search', array( $this, 'render_search_shortcode' ) );
		add_shortcode( 'wpcsr_rooms', array( $this, 'render_rooms_shortcode' ) );
		add_shortcode( 'wpcsr_booking', array( $this, 'render_booking_shortcode' ) );
		add_shortcode( 'wpcsr_manage_booking', array( $this, 'render_manage_booking_shortcode' ) );
	}

	/**
	 * Render [wpcsr_search] shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_search_shortcode( $atts = array() ): string {
		wp_enqueue_style( 'wpcsr-frontend-css' );
		wp_enqueue_script( 'wpcsr-frontend-js' );

		/** @var BookingFlowController|null $controller */
		$controller = Plugin::instance()->get_service( 'booking_flow' );
		if ( ! $controller ) {
			return '';
		}

		ob_start();
		$controller->render_search_form();
		return (string) ob_get_clean();
	}

	/**
	 * Render [wpcsr_rooms] shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_rooms_shortcode( $atts = array() ): string {
		wp_enqueue_style( 'wpcsr-frontend-css' );
		wp_enqueue_script( 'wpcsr-frontend-js' );

		/** @var BookingFlowController|null $controller */
		$controller = Plugin::instance()->get_service( 'booking_flow' );
		if ( ! $controller ) {
			return '';
		}

		ob_start();
		$controller->render_available_rooms();
		return (string) ob_get_clean();
	}

	/**
	 * Render [wpcsr_booking] complete booking flow wizard.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_booking_shortcode( $atts = array() ): string {
		wp_enqueue_style( 'wpcsr-frontend-css' );
		wp_enqueue_script( 'wpcsr-frontend-js' );

		/** @var BookingFlowController|null $controller */
		$controller = Plugin::instance()->get_service( 'booking_flow' );
		if ( ! $controller ) {
			return '';
		}

		ob_start();
		$controller->render();
		return (string) ob_get_clean();
	}

	/**
	 * Render [wpcsr_manage_booking] customer booking portal.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_manage_booking_shortcode( $atts = array() ): string {
		wp_enqueue_style( 'wpcsr-frontend-css' );
		wp_enqueue_script( 'wpcsr-frontend-js' );

		/** @var CustomerPortalController|null $controller */
		$controller = Plugin::instance()->get_service( 'customer_portal' );
		if ( ! $controller ) {
			return '';
		}

		ob_start();
		$controller->render();
		return (string) ob_get_clean();
	}
}
