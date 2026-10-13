<?php
/**
 * Custom Taxonomy for Room Amenities.
 *
 * @package WPCalibrate\StayReserve\Rooms
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Rooms;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class AmenityTaxonomy
 */
class AmenityTaxonomy {

	public const TAXONOMY = 'wpcsr_amenity';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Amenities', 'taxonomy general name', 'wpcalibrate-stayreserve' ),
			'singular_name'     => _x( 'Amenity', 'taxonomy singular name', 'wpcalibrate-stayreserve' ),
			'search_items'      => __( 'Search Amenities', 'wpcalibrate-stayreserve' ),
			'all_items'         => __( 'All Amenities', 'wpcalibrate-stayreserve' ),
			'parent_item'       => __( 'Parent Amenity', 'wpcalibrate-stayreserve' ),
			'parent_item_colon' => __( 'Parent Amenity:', 'wpcalibrate-stayreserve' ),
			'edit_item'         => __( 'Edit Amenity', 'wpcalibrate-stayreserve' ),
			'update_item'       => __( 'Update Amenity', 'wpcalibrate-stayreserve' ),
			'add_new_item'      => __( 'Add New Amenity', 'wpcalibrate-stayreserve' ),
			'new_item_name'     => __( 'New Amenity Name', 'wpcalibrate-stayreserve' ),
			'menu_name'         => __( 'Amenities', 'wpcalibrate-stayreserve' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'amenity' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( RoomTypePostType::POST_TYPE ), $args );
	}
}
