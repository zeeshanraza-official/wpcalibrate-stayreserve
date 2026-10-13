<?php
/**
 * Elementor Rooms Widget.
 *
 * @package WPCalibrate\StayReserve\Integrations\Elementor
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class RoomsWidget
 */
class RoomsWidget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'wpcsr_rooms_widget';
	}

	public function get_title(): string {
		return __( 'Available Rooms Grid', 'wpcalibrate-stayreserve' );
	}

	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	public function get_categories(): array {
		return array( 'wpcalibrate-stayreserve' );
	}

	protected function render(): void {
		echo do_shortcode( '[wpcsr_rooms]' );
	}
}
