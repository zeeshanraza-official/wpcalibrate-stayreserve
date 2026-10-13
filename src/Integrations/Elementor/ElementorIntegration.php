<?php
/**
 * Elementor Page Builder Integration for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Integrations\Elementor
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class ElementorIntegration
 */
class ElementorIntegration {

	/**
	 * Register hooks if Elementor is active.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register custom Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 * @return void
	 */
	public function add_widget_category( $elements_manager ): void {
		$elements_manager->add_category(
			'wpcalibrate-stayreserve',
			array(
				'title' => __( 'WPCalibrate StayReserve', 'wpcalibrate-stayreserve' ),
				'icon'  => 'fa fa-calendar',
			)
		);
	}

	/**
	 * Register Elementor widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once __DIR__ . '/SearchWidget.php';
		require_once __DIR__ . '/RoomsWidget.php';
		require_once __DIR__ . '/BookingWidget.php';

		$widgets_manager->register( new SearchWidget() );
		$widgets_manager->register( new RoomsWidget() );
		$widgets_manager->register( new BookingWidget() );
	}
}
