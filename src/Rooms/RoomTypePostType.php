<?php
/**
 * Custom Post Type for Accommodation / Room Types.
 *
 * @package WPCalibrate\StayReserve\Rooms
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Rooms;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class RoomTypePostType
 */
class RoomTypePostType {

	public const POST_TYPE = 'wpcsr_room_type';

	/**
	 * Meta field keys.
	 */
	public const META_BASE_PRICE      = '_wpcsr_base_price'; // stored in minor units (integer cents)
	public const META_ADULTS_CAPACITY = '_wpcsr_adults_capacity';
	public const META_CHILD_CAPACITY  = '_wpcsr_children_capacity';
	public const META_TOTAL_CAPACITY  = '_wpcsr_total_capacity';
	public const META_MAX_ROOMS       = '_wpcsr_max_rooms_per_booking';
	public const META_BED_INFO        = '_wpcsr_bed_info';
	public const META_ROOM_SIZE       = '_wpcsr_room_size';
	public const META_MIN_STAY        = '_wpcsr_min_stay';
	public const META_MAX_STAY        = '_wpcsr_max_stay';
	public const META_CHECKIN_TIME    = '_wpcsr_checkin_time';
	public const META_CHECKOUT_TIME   = '_wpcsr_checkout_time';
	public const META_IS_FEATURED     = '_wpcsr_is_featured';
	public const META_IS_TAXABLE      = '_wpcsr_is_taxable';
	public const META_GALLERY_IDS     = '_wpcsr_gallery_attachment_ids';

	/**
	 * Register hooks for this post type.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'register_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Register the accommodation room type CPT.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Room Types', 'Post type general name', 'wpcalibrate-stayreserve' ),
			'singular_name'         => _x( 'Room Type', 'Post type singular name', 'wpcalibrate-stayreserve' ),
			'menu_name'             => _x( 'Room Types', 'Admin Menu text', 'wpcalibrate-stayreserve' ),
			'name_admin_bar'        => _x( 'Room Type', 'Add New on Toolbar', 'wpcalibrate-stayreserve' ),
			'add_new'               => __( 'Add New Room Type', 'wpcalibrate-stayreserve' ),
			'add_new_item'          => __( 'Add New Room Type', 'wpcalibrate-stayreserve' ),
			'new_item'              => __( 'New Room Type', 'wpcalibrate-stayreserve' ),
			'edit_item'             => __( 'Edit Room Type', 'wpcalibrate-stayreserve' ),
			'view_item'             => __( 'View Room Type', 'wpcalibrate-stayreserve' ),
			'all_items'             => __( 'All Room Types', 'wpcalibrate-stayreserve' ),
			'search_items'          => __( 'Search Room Types', 'wpcalibrate-stayreserve' ),
			'parent_item_colon'     => __( 'Parent Room Types:', 'wpcalibrate-stayreserve' ),
			'not_found'             => __( 'No room types found.', 'wpcalibrate-stayreserve' ),
			'not_found_in_trash'    => __( 'No room types found in Trash.', 'wpcalibrate-stayreserve' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Shown under StayReserve menu
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'accommodation', 'with_front' => false ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add meta boxes for room configuration.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'wpcsr_room_type_settings',
			__( 'Room Type Configuration & Pricing', 'wpcalibrate-stayreserve' ),
			array( $this, 'render_settings_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_settings_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'wpcsr_save_room_type_' . $post->ID, 'wpcsr_room_type_nonce' );

		$base_price_minor = (int) get_post_meta( $post->ID, self::META_BASE_PRICE, true );
		$base_price_dec   = MoneyUtil::minor_to_decimal( $base_price_minor );
		$adults           = (int) ( get_post_meta( $post->ID, self::META_ADULTS_CAPACITY, true ) ?: 2 );
		$children         = (int) ( get_post_meta( $post->ID, self::META_CHILD_CAPACITY, true ) ?: 0 );
		$total_guests     = (int) ( get_post_meta( $post->ID, self::META_TOTAL_CAPACITY, true ) ?: 2 );
		$max_rooms        = (int) ( get_post_meta( $post->ID, self::META_MAX_ROOMS, true ) ?: 5 );
		$bed_info         = (string) get_post_meta( $post->ID, self::META_BED_INFO, true );
		$room_size        = (string) get_post_meta( $post->ID, self::META_ROOM_SIZE, true );
		$min_stay         = (int) get_post_meta( $post->ID, self::META_MIN_STAY, true );
		$max_stay         = (int) get_post_meta( $post->ID, self::META_MAX_STAY, true );
		$checkin          = (string) get_post_meta( $post->ID, self::META_CHECKIN_TIME, true );
		$checkout         = (string) get_post_meta( $post->ID, self::META_CHECKOUT_TIME, true );
		$is_featured      = (bool) get_post_meta( $post->ID, self::META_IS_FEATURED, true );
		$is_taxable       = ( '0' !== get_post_meta( $post->ID, self::META_IS_TAXABLE, true ) );
		?>
		<div class="wpcsr-meta-box-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; padding: 12px 0;">
			<div>
				<label for="wpcsr_base_price"><strong><?php esc_html_e( 'Base Nightly Price', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" step="0.01" min="0" id="wpcsr_base_price" name="wpcsr_base_price" value="<?php echo esc_attr( (string) $base_price_dec ); ?>" class="regular-text" style="width:100%;margin-top:4px;" required>
				<p class="description"><?php esc_html_e( 'Standard nightly rate before seasonal rules.', 'wpcalibrate-stayreserve' ); ?></p>
			</div>

			<div>
				<label for="wpcsr_adults_capacity"><strong><?php esc_html_e( 'Adult Capacity', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" min="1" max="100" id="wpcsr_adults_capacity" name="wpcsr_adults_capacity" value="<?php echo esc_attr( (string) $adults ); ?>" class="regular-text" style="width:100%;margin-top:4px;" required>
				<p class="description"><?php esc_html_e( 'Maximum number of adults.', 'wpcalibrate-stayreserve' ); ?></p>
			</div>

			<div>
				<label for="wpcsr_children_capacity"><strong><?php esc_html_e( 'Children Capacity', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" min="0" max="50" id="wpcsr_children_capacity" name="wpcsr_children_capacity" value="<?php echo esc_attr( (string) $children ); ?>" class="regular-text" style="width:100%;margin-top:4px;">
				<p class="description"><?php esc_html_e( 'Maximum number of children.', 'wpcalibrate-stayreserve' ); ?></p>
			</div>

			<div>
				<label for="wpcsr_total_capacity"><strong><?php esc_html_e( 'Total Guests Maximum', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" min="1" max="100" id="wpcsr_total_capacity" name="wpcsr_total_capacity" value="<?php echo esc_attr( (string) $total_guests ); ?>" class="regular-text" style="width:100%;margin-top:4px;" required>
				<p class="description"><?php esc_html_e( 'Total max occupancy (adults + children).', 'wpcalibrate-stayreserve' ); ?></p>
			</div>

			<div>
				<label for="wpcsr_bed_info"><strong><?php esc_html_e( 'Beds Configuration', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="text" id="wpcsr_bed_info" name="wpcsr_bed_info" value="<?php echo esc_attr( $bed_info ); ?>" class="regular-text" style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'e.g. 1 King Bed or 2 Twin Beds', 'wpcalibrate-stayreserve' ); ?>">
			</div>

			<div>
				<label for="wpcsr_room_size"><strong><?php esc_html_e( 'Room Size', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="text" id="wpcsr_room_size" name="wpcsr_room_size" value="<?php echo esc_attr( $room_size ); ?>" class="regular-text" style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'e.g. 35 m² / 375 sq ft', 'wpcalibrate-stayreserve' ); ?>">
			</div>

			<div>
				<label for="wpcsr_min_stay"><strong><?php esc_html_e( 'Minimum Stay (Nights)', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" min="0" max="365" id="wpcsr_min_stay" name="wpcsr_min_stay" value="<?php echo esc_attr( $min_stay > 0 ? (string) $min_stay : '' ); ?>" class="regular-text" style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'Default from settings if empty', 'wpcalibrate-stayreserve' ); ?>">
			</div>

			<div>
				<label for="wpcsr_max_stay"><strong><?php esc_html_e( 'Maximum Stay (Nights)', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="number" min="0" max="365" id="wpcsr_max_stay" name="wpcsr_max_stay" value="<?php echo esc_attr( $max_stay > 0 ? (string) $max_stay : '' ); ?>" class="regular-text" style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'Default from settings if empty', 'wpcalibrate-stayreserve' ); ?>">
			</div>

			<div>
				<label for="wpcsr_checkin_time"><strong><?php esc_html_e( 'Check-in Time Override', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="time" id="wpcsr_checkin_time" name="wpcsr_checkin_time" value="<?php echo esc_attr( $checkin ); ?>" class="regular-text" style="width:100%;margin-top:4px;">
			</div>

			<div>
				<label for="wpcsr_checkout_time"><strong><?php esc_html_e( 'Check-out Time Override', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
				<input type="time" id="wpcsr_checkout_time" name="wpcsr_checkout_time" value="<?php echo esc_attr( $checkout ); ?>" class="regular-text" style="width:100%;margin-top:4px;">
			</div>
		</div>

		<div style="margin-top: 16px; border-top: 1px solid #ddd; padding-top: 12px; display: flex; gap: 24px;">
			<label>
				<input type="checkbox" name="wpcsr_is_taxable" value="1" <?php checked( $is_taxable ); ?>>
				<?php esc_html_e( 'Subject to accommodation tax', 'wpcalibrate-stayreserve' ); ?>
			</label>
			<label>
				<input type="checkbox" name="wpcsr_is_featured" value="1" <?php checked( $is_featured ); ?>>
				<?php esc_html_e( 'Featured accommodation', 'wpcalibrate-stayreserve' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Save meta boxes safely with capability & nonce checks.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['wpcsr_room_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpcsr_room_type_nonce'] ) ), 'wpcsr_save_room_type_' . $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_ROOMS ) ) {
			return;
		}

		// Save Base Price in minor units
		$raw_price = isset( $_POST['wpcsr_base_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['wpcsr_base_price'] ) ) : 0.0;
		$price_minor = MoneyUtil::decimal_to_minor( $raw_price );
		update_post_meta( $post_id, self::META_BASE_PRICE, $price_minor );

		// Capacities
		$adults = isset( $_POST['wpcsr_adults_capacity'] ) ? max( 1, (int) sanitize_text_field( wp_unslash( $_POST['wpcsr_adults_capacity'] ) ) ) : 2;
		$children = isset( $_POST['wpcsr_children_capacity'] ) ? max( 0, (int) sanitize_text_field( wp_unslash( $_POST['wpcsr_children_capacity'] ) ) ) : 0;
		$total = isset( $_POST['wpcsr_total_capacity'] ) ? max( $adults, (int) sanitize_text_field( wp_unslash( $_POST['wpcsr_total_capacity'] ) ) ) : $adults;

		update_post_meta( $post_id, self::META_ADULTS_CAPACITY, $adults );
		update_post_meta( $post_id, self::META_CHILD_CAPACITY, $children );
		update_post_meta( $post_id, self::META_TOTAL_CAPACITY, $total );

		// Beds & Size
		$bed_info = isset( $_POST['wpcsr_bed_info'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcsr_bed_info'] ) ) : '';
		$room_size = isset( $_POST['wpcsr_room_size'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcsr_room_size'] ) ) : '';
		update_post_meta( $post_id, self::META_BED_INFO, $bed_info );
		update_post_meta( $post_id, self::META_ROOM_SIZE, $room_size );

		// Stay limits
		$min_stay = isset( $_POST['wpcsr_min_stay'] ) && '' !== $_POST['wpcsr_min_stay'] ? max( 0, (int) sanitize_text_field( wp_unslash( $_POST['wpcsr_min_stay'] ) ) ) : 0;
		$max_stay = isset( $_POST['wpcsr_max_stay'] ) && '' !== $_POST['wpcsr_max_stay'] ? max( 0, (int) sanitize_text_field( wp_unslash( $_POST['wpcsr_max_stay'] ) ) ) : 0;
		update_post_meta( $post_id, self::META_MIN_STAY, $min_stay );
		update_post_meta( $post_id, self::META_MAX_STAY, $max_stay );

		// Times
		$checkin = isset( $_POST['wpcsr_checkin_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcsr_checkin_time'] ) ) : '';
		$checkout = isset( $_POST['wpcsr_checkout_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcsr_checkout_time'] ) ) : '';
		update_post_meta( $post_id, self::META_CHECKIN_TIME, $checkin );
		update_post_meta( $post_id, self::META_CHECKOUT_TIME, $checkout );

		// Flags
		$is_featured = isset( $_POST['wpcsr_is_featured'] ) ? 1 : 0;
		$is_taxable = isset( $_POST['wpcsr_is_taxable'] ) ? 1 : 0;
		update_post_meta( $post_id, self::META_IS_FEATURED, $is_featured );
		update_post_meta( $post_id, self::META_IS_TAXABLE, $is_taxable );
	}

	/**
	 * Register admin list table columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function register_columns( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['base_price'] = __( 'Base Price', 'wpcalibrate-stayreserve' );
				$new_columns['capacity']   = __( 'Capacity', 'wpcalibrate-stayreserve' );
				$new_columns['units']      = __( 'Physical Units', 'wpcalibrate-stayreserve' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render column content.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'base_price' === $column ) {
			$minor = (int) get_post_meta( $post_id, self::META_BASE_PRICE, true );
			echo esc_html( MoneyUtil::format_minor( $minor ) . ' / night' );
		} elseif ( 'capacity' === $column ) {
			$adults = (int) ( get_post_meta( $post_id, self::META_ADULTS_CAPACITY, true ) ?: 1 );
			$children = (int) get_post_meta( $post_id, self::META_CHILD_CAPACITY, true );
			/* translators: 1: Adults count, 2: Children count */
			echo esc_html( sprintf( __( '%1$d Adults, %2$d Children', 'wpcalibrate-stayreserve' ), $adults, $children ) );
		} elseif ( 'units' === $column ) {
			$repo = new RoomUnitRepository();
			$count = $repo->count_by_room_type( $post_id );
			$link = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&room_type_id=' . $post_id );
			/* translators: %d: Unit count */
			printf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( sprintf( _n( '%d Unit', '%d Units', $count, 'wpcalibrate-stayreserve' ), $count ) ) );
		}
	}
}
