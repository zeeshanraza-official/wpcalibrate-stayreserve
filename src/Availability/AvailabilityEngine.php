<?php
/**
 * Server-Side Availability Engine for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Availability
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Availability;

use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Inventory\HoldManager;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Rooms\RoomUnitRepository;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class AvailabilityEngine
 */
class AvailabilityEngine {

	/**
	 * Room units repository.
	 *
	 * @var RoomUnitRepository
	 */
	private RoomUnitRepository $unit_repo;

	/**
	 * Maintenance blocks repository.
	 *
	 * @var BlockRepository
	 */
	private BlockRepository $block_repo;

	/**
	 * Inventory hold manager.
	 *
	 * @var HoldManager
	 */
	private HoldManager $hold_manager;

	/**
	 * Constructor.
	 *
	 * @param RoomUnitRepository $unit_repo Unit repo.
	 * @param BlockRepository    $block_repo Block repo.
	 * @param HoldManager        $hold_manager Hold manager.
	 */
	public function __construct(
		RoomUnitRepository $unit_repo,
		BlockRepository $block_repo,
		HoldManager $hold_manager
	) {
		$this->unit_repo    = $unit_repo;
		$this->block_repo   = $block_repo;
		$this->hold_manager = $hold_manager;
	}

	/**
	 * Check whether a specific physical room unit is available for stay dates [check_in, check_out).
	 *
	 * @param int         $room_unit_id Unit ID.
	 * @param string      $check_in Check-in date (YYYY-MM-DD).
	 * @param string      $check_out Check-out date (YYYY-MM-DD).
	 * @param int|null    $exclude_booking_id Booking ID to exclude (when editing an existing booking).
	 * @param string|null $exclude_hold_token Hold token to exclude (user's current hold).
	 * @return bool True if completely available, false otherwise.
	 */
	public function is_unit_available(
		int $room_unit_id,
		string $check_in,
		string $check_out,
		?int $exclude_booking_id = null,
		?string $exclude_hold_token = null
	): bool {
		global $wpdb;
		if ( ! $wpdb || $room_unit_id <= 0 ) {
			return false;
		}

		$clean_in  = DateTimeUtil::format_date( $check_in );
		$clean_out = DateTimeUtil::format_date( $check_out );

		// Validate dates
		if ( $clean_out <= $clean_in ) {
			return false;
		}

		// 1. Verify unit exists and is active
		$unit = $this->unit_repo->get( $room_unit_id );
		if ( ! $unit || 'active' !== $unit['status'] ) {
			return false;
		}

		$room_type_id = (int) $unit['room_type_id'];

		// 2. Check maintenance blocks
		if ( $this->block_repo->is_blocked( $room_type_id, $room_unit_id, $clean_in, $clean_out ) ) {
			return false;
		}

		// 3. Check active non-expired inventory holds
		if ( $this->hold_manager->is_unit_held( $room_unit_id, $clean_in, $clean_out, $exclude_hold_token ) ) {
			return false;
		}

		// 4. Check overlapping active bookings
		$bookings_table = Schema::get_table_name( 'bookings' );
		$items_table    = Schema::get_table_name( 'booking_items' );

		$statuses = BookingStatus::get_inventory_reserving_statuses();
		if ( empty( $statuses ) ) {
			$statuses = array( 'pending', 'on_hold', 'awaiting_payment', 'confirmed' );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array( $room_unit_id );
		$params       = array_merge( $params, $statuses );
		$params[]     = $clean_out;
		$params[]     = $clean_in;

		$exclude_sql = '';
		if ( $exclude_booking_id && $exclude_booking_id > 0 ) {
			$exclude_sql = ' AND b.id != %d';
			$params[]    = $exclude_booking_id;
		}

		// Hotel half-open overlap: b.check_in < req.check_out AND b.check_out > req.check_in
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$items_table}` bi
			 INNER JOIN `{$bookings_table}` b ON bi.booking_id = b.id
			 WHERE bi.room_unit_id = %d
			   AND b.status IN ({$placeholders})
			   AND b.check_in < %s
			   AND b.check_out > %s
			   {$exclude_sql}",
			...$params
		);

		$conflicts = (int) $wpdb->get_var( $sql );
		return 0 === $conflicts;
	}

	/**
	 * Get all currently available physical units for a room type over the given stay dates.
	 *
	 * @param int         $room_type_id Room Type CPT ID.
	 * @param string      $check_in Check-in date.
	 * @param string      $check_out Check-out date.
	 * @param int|null    $exclude_booking_id Booking to exclude.
	 * @param string|null $exclude_hold_token Hold token to exclude.
	 * @return array<int, array<string, mixed>> Available unit rows.
	 */
	public function get_available_units(
		int $room_type_id,
		string $check_in,
		string $check_out,
		?int $exclude_booking_id = null,
		?string $exclude_hold_token = null
	): array {
		$all_units = $this->unit_repo->get_by_room_type( $room_type_id, true );
		$available = array();

		foreach ( $all_units as $unit ) {
			$unit_id = (int) $unit['id'];
			if ( $this->is_unit_available( $unit_id, $check_in, $check_out, $exclude_booking_id, $exclude_hold_token ) ) {
				$available[] = $unit;
			}
		}

		return $available;
	}

	/**
	 * Count available units for a room type.
	 *
	 * @param int         $room_type_id Room Type CPT ID.
	 * @param string      $check_in Check-in date.
	 * @param string      $check_out Check-out date.
	 * @param int|null    $exclude_booking_id Booking ID to exclude.
	 * @param string|null $exclude_hold_token Hold token to exclude.
	 * @return int Number of free physical units.
	 */
	public function count_available_units(
		int $room_type_id,
		string $check_in,
		string $check_out,
		?int $exclude_booking_id = null,
		?string $exclude_hold_token = null
	): int {
		$available = $this->get_available_units( $room_type_id, $check_in, $check_out, $exclude_booking_id, $exclude_hold_token );
		return count( $available );
	}

	/**
	 * Allocate an available physical unit for a room type.
	 *
	 * Returns the first available unit ID, or null if fully booked.
	 *
	 * @param int         $room_type_id Room Type CPT ID.
	 * @param string      $check_in Check-in date.
	 * @param string      $check_out Check-out date.
	 * @param int|null    $exclude_booking_id Booking ID to exclude.
	 * @param string|null $exclude_hold_token Hold token to exclude.
	 * @return int|null Unit ID or null if none available.
	 */
	public function allocate_unit(
		int $room_type_id,
		string $check_in,
		string $check_out,
		?int $exclude_booking_id = null,
		?string $exclude_hold_token = null
	): ?int {
		$available = $this->get_available_units( $room_type_id, $check_in, $check_out, $exclude_booking_id, $exclude_hold_token );
		if ( empty( $available ) ) {
			return null;
		}
		return (int) $available[0]['id'];
	}

	/**
	 * Search for all available room types matching guest count, dates, and required room quantity.
	 *
	 * @param string $check_in Check-in date.
	 * @param string $check_out Check-out date.
	 * @param int    $adults Adults count.
	 * @param int    $children Children count.
	 * @param int    $required_rooms Number of rooms needed (default 1).
	 * @return array<int, array<string, mixed>> Matching room types with inventory count and meta.
	 */
	public function search_availability(
		string $check_in,
		string $check_out,
		int $adults = 1,
		int $children = 0,
		int $required_rooms = 1
	): array {
		$clean_in  = DateTimeUtil::format_date( $check_in );
		$clean_out = DateTimeUtil::format_date( $check_out );

		if ( $clean_out <= $clean_in ) {
			return array();
		}

		$nights = DateTimeUtil::calculate_nights( $clean_in, $clean_out );
		if ( $nights <= 0 ) {
			return array();
		}

		// Query published room types
		$room_type_posts = get_posts(
			array(
				'post_type'      => RoomTypePostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$results = array();
		$total_guests = $adults + $children;

		foreach ( $room_type_posts as $post ) {
			$post_id = $post->ID;

			// Capacity check
			$type_adults = (int) ( get_post_meta( $post_id, RoomTypePostType::META_ADULTS_CAPACITY, true ) ?: 2 );
			$type_total  = (int) ( get_post_meta( $post_id, RoomTypePostType::META_TOTAL_CAPACITY, true ) ?: $type_adults );

			// Check per-room capacity
			if ( $adults > ( $type_adults * $required_rooms ) || $total_guests > ( $type_total * $required_rooms ) ) {
				continue;
			}

			// Stay duration rules check
			$min_stay = (int) get_post_meta( $post_id, RoomTypePostType::META_MIN_STAY, true );
			if ( 0 === $min_stay ) {
				$min_stay = (int) SettingsManager::get( 'general', 'default_min_stay', 1 );
			}
			if ( $min_stay > 0 && $nights < $min_stay ) {
				continue;
			}

			$max_stay = (int) get_post_meta( $post_id, RoomTypePostType::META_MAX_STAY, true );
			if ( 0 === $max_stay ) {
				$max_stay = (int) SettingsManager::get( 'general', 'default_max_stay', 30 );
			}
			if ( $max_stay > 0 && $nights > $max_stay ) {
				continue;
			}

			// Available units count
			$available_count = $this->count_available_units( $post_id, $clean_in, $clean_out );
			if ( $available_count < $required_rooms ) {
				continue;
			}

			// Thumbnail image
			$thumb_id  = get_post_thumbnail_id( $post_id );
			$image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';

			// Amenities
			$amenity_terms = get_the_terms( $post_id, 'wpcsr_amenity' );
			$amenities     = array();
			if ( ! empty( $amenity_terms ) && ! is_wp_error( $amenity_terms ) ) {
				foreach ( $amenity_terms as $term ) {
					$amenities[] = $term->name;
				}
			}

			$base_price_minor = (int) get_post_meta( $post_id, RoomTypePostType::META_BASE_PRICE, true );

			$results[] = array(
				'id'              => $post_id,
				'name'            => $post->post_title,
				'excerpt'         => wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 25 ) ),
				'image_url'       => $image_url,
				'base_price'      => $base_price_minor,
				'available_units' => $available_count,
				'adults_capacity' => $type_adults,
				'total_capacity'  => $type_total,
				'bed_info'        => (string) get_post_meta( $post_id, RoomTypePostType::META_BED_INFO, true ),
				'room_size'       => (string) get_post_meta( $post_id, RoomTypePostType::META_ROOM_SIZE, true ),
				'amenities'       => $amenities,
			);
		}

		return $results;
	}
}
