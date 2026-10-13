<?php
/**
 * Room Unit Repository for managing physical room inventory units.
 *
 * @package WPCalibrate\StayReserve\Rooms
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Rooms;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class RoomUnitRepository
 */
class RoomUnitRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Schema::get_table_name( 'room_units' );
	}

	/**
	 * Create a physical room unit.
	 *
	 * @param array{
	 *     room_type_id: int,
	 *     unit_name: string,
	 *     status?: string,
	 *     sort_order?: int,
	 *     notes?: string
	 * } $data Unit data.
	 * @return int Inserted ID or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$now = DateTimeUtil::now_mysql();
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'room_type_id' => (int) $data['room_type_id'],
				'unit_name'    => sanitize_text_field( $data['unit_name'] ),
				'status'       => sanitize_text_field( $data['status'] ?? 'active' ),
				'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
				'notes'        => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing physical unit.
	 *
	 * @param int   $id Unit ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return false;
		}

		$update_data = array(
			'updated_at' => DateTimeUtil::now_mysql(),
		);
		$formats     = array( '%s' );

		if ( isset( $data['room_type_id'] ) ) {
			$update_data['room_type_id'] = (int) $data['room_type_id'];
			$formats[]                   = '%d';
		}
		if ( isset( $data['unit_name'] ) ) {
			$update_data['unit_name'] = sanitize_text_field( $data['unit_name'] );
			$formats[]                = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
			$formats[]             = '%s';
		}
		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = (int) $data['sort_order'];
			$formats[]                 = '%d';
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$update_data['notes'] = null !== $data['notes'] ? sanitize_textarea_field( (string) $data['notes'] ) : null;
			$formats[]            = '%s';
		}

		$res = $wpdb->update(
			$this->get_table_name(),
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $res;
	}

	/**
	 * Delete a unit by ID.
	 *
	 * @param int $id Unit ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return false;
		}

		$res = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $res && $res > 0;
	}

	/**
	 * Get a unit by ID.
	 *
	 * @param int $id Unit ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return null;
		}

		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get units by room type ID.
	 *
	 * @param int  $room_type_id Room Type CPT ID.
	 * @param bool $active_only Filter to only active units.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_room_type( int $room_type_id, bool $active_only = false ): array {
		global $wpdb;
		if ( ! $wpdb || $room_type_id <= 0 ) {
			return array();
		}

		$table = $this->get_table_name();
		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE room_type_id = %d AND status = 'active' ORDER BY sort_order ASC, unit_name ASC",
				$room_type_id
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE room_type_id = %d ORDER BY sort_order ASC, unit_name ASC",
				$room_type_id
			);
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );
		return $results ?: array();
	}

	/**
	 * Count units for a room type.
	 *
	 * @param int  $room_type_id Room Type CPT ID.
	 * @param bool $active_only Count only active units.
	 * @return int
	 */
	public function count_by_room_type( int $room_type_id, bool $active_only = false ): int {
		global $wpdb;
		if ( ! $wpdb || $room_type_id <= 0 ) {
			return 0;
		}

		$table = $this->get_table_name();
		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE room_type_id = %d AND status = 'active'",
				$room_type_id
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE room_type_id = %d",
				$room_type_id
			);
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Query room units with pagination and filters.
	 *
	 * @param array $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		$table  = $this->get_table_name();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['room_type_id'] ) ) {
			$where[]  = 'room_type_id = %d';
			$params[] = (int) $args['room_type_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'unit_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$where_clause = implode( ' AND ', $where );
		$limit        = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$offset       = isset( $args['page'] ) ? ( max( 1, (int) $args['page'] ) - 1 ) * $limit : 0;

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY sort_order ASC, unit_name ASC LIMIT %d OFFSET %d",
			...$params
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		return $results ?: array();
	}
}
