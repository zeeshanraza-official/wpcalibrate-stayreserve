<?php
/**
 * Block Repository for managing manual maintenance & closed periods.
 *
 * @package WPCalibrate\StayReserve\Availability
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Availability;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BlockRepository
 */
class BlockRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Schema::get_table_name( 'blocks' );
	}

	/**
	 * Create a block period.
	 *
	 * Dates use hotel stay semantics [start_date, end_date).
	 *
	 * @param array{
	 *     room_type_id?: int|null,
	 *     room_unit_id?: int|null,
	 *     start_date: string,
	 *     end_date: string,
	 *     reason?: string,
	 *     created_by?: int|null
	 * } $data Block data.
	 * @return int Inserted ID or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'room_type_id' => ! empty( $data['room_type_id'] ) ? (int) $data['room_type_id'] : null,
				'room_unit_id' => ! empty( $data['room_unit_id'] ) ? (int) $data['room_unit_id'] : null,
				'start_date'   => DateTimeUtil::format_date( $data['start_date'] ),
				'end_date'     => DateTimeUtil::format_date( $data['end_date'] ),
				'reason'       => sanitize_text_field( $data['reason'] ?? '' ),
				'created_by'   => ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id(),
				'created_at'   => DateTimeUtil::now_mysql(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete a block.
	 *
	 * @param int $id Block ID.
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
	 * Check if a unit or room type is blocked during given stay dates.
	 *
	 * Stay dates [check_in, check_out) overlap block [start_date, end_date) if:
	 * start_date < check_out AND end_date > check_in
	 *
	 * @param int|null $room_type_id Room type ID.
	 * @param int|null $room_unit_id Room unit ID.
	 * @param string   $check_in Check-in date (YYYY-MM-DD).
	 * @param string   $check_out Check-out date (YYYY-MM-DD).
	 * @return bool True if blocked, false if available.
	 */
	public function is_blocked( ?int $room_type_id, ?int $room_unit_id, string $check_in, string $check_out ): bool {
		global $wpdb;
		if ( ! $wpdb ) {
			return false;
		}

		$table = $this->get_table_name();

		// A block matches if it targets the specific unit, OR the room type broadly, OR is a global property block
		$conditions = array();
		$params     = array( $check_out, $check_in );

		if ( $room_unit_id && $room_type_id ) {
			$conditions[] = '(room_unit_id = %d OR room_type_id = %d OR (room_unit_id IS NULL AND room_type_id IS NULL))';
			$params[]     = $room_unit_id;
			$params[]     = $room_type_id;
		} elseif ( $room_unit_id ) {
			$conditions[] = '(room_unit_id = %d OR (room_unit_id IS NULL AND room_type_id IS NULL))';
			$params[]     = $room_unit_id;
		} elseif ( $room_type_id ) {
			$conditions[] = '(room_type_id = %d OR (room_unit_id IS NULL AND room_type_id IS NULL))';
			$params[]     = $room_type_id;
		} else {
			$conditions[] = '(room_unit_id IS NULL AND room_type_id IS NULL)';
		}

		$cond_sql = implode( ' AND ', $conditions );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE start_date < %s AND end_date > %s AND {$cond_sql}",
			...$params
		);

		return ( (int) $wpdb->get_var( $sql ) ) > 0;
	}

	/**
	 * Get all blocks with optional filtering.
	 *
	 * @param array $args Filter args.
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

		if ( ! empty( $args['room_unit_id'] ) ) {
			$where[]  = 'room_unit_id = %d';
			$params[] = (int) $args['room_unit_id'];
		}

		$where_clause = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = empty( $params )
			? "SELECT * FROM `{$table}` ORDER BY start_date DESC"
			: $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY start_date DESC", ...$params );

		$results = $wpdb->get_results( $sql, ARRAY_A );
		return $results ?: array();
	}
}
