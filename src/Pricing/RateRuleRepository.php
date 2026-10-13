<?php
/**
 * Rate Rule Repository for date-based, seasonal, and day-of-week pricing adjustments.
 *
 * @package WPCalibrate\StayReserve\Pricing
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Pricing;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class RateRuleRepository
 */
class RateRuleRepository {

	public const TYPE_DATE_OVERRIDE = 'date_override';
	public const TYPE_SEASON        = 'season';
	public const TYPE_DAY_OF_WEEK   = 'day_of_week';
	public const TYPE_WEEKEND       = 'weekend';

	public const ADJ_OVERRIDE_PRICE = 'override_price';
	public const ADJ_FIXED          = 'fixed';
	public const ADJ_PERCENTAGE     = 'percentage';

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Schema::get_table_name( 'rate_rules' );
	}

	/**
	 * Create a rate rule.
	 *
	 * @param array $data Rule data.
	 * @return int Inserted ID or 0.
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
				'room_type_id'    => (int) $data['room_type_id'],
				'name'            => sanitize_text_field( $data['name'] ),
				'rule_type'       => sanitize_text_field( $data['rule_type'] ),
				'priority'        => (int) ( $data['priority'] ?? 10 ),
				'start_date'      => ! empty( $data['start_date'] ) ? DateTimeUtil::format_date( $data['start_date'] ) : null,
				'end_date'        => ! empty( $data['end_date'] ) ? DateTimeUtil::format_date( $data['end_date'] ) : null,
				'weekdays'        => ! empty( $data['weekdays'] ) ? sanitize_text_field( (string) $data['weekdays'] ) : null,
				'adjustment_type' => sanitize_text_field( $data['adjustment_type'] ?? self::ADJ_FIXED ),
				'amount'          => (int) $data['amount'],
				'minimum_stay'    => ! empty( $data['minimum_stay'] ) ? (int) $data['minimum_stay'] : null,
				'maximum_stay'    => ! empty( $data['maximum_stay'] ) ? (int) $data['maximum_stay'] : null,
				'enabled'         => ! empty( $data['enabled'] ) ? 1 : 0,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing rule.
	 *
	 * @param int   $id Rule ID.
	 * @param array $data Fields.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return false;
		}

		$fields = array( 'updated_at' => DateTimeUtil::now_mysql() );
		$formats = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$formats[]      = '%s';
		}
		if ( isset( $data['priority'] ) ) {
			$fields['priority'] = (int) $data['priority'];
			$formats[]          = '%d';
		}
		if ( isset( $data['start_date'] ) ) {
			$fields['start_date'] = ! empty( $data['start_date'] ) ? DateTimeUtil::format_date( $data['start_date'] ) : null;
			$formats[]            = '%s';
		}
		if ( isset( $data['end_date'] ) ) {
			$fields['end_date'] = ! empty( $data['end_date'] ) ? DateTimeUtil::format_date( $data['end_date'] ) : null;
			$formats[]          = '%s';
		}
		if ( array_key_exists( 'weekdays', $data ) ) {
			$fields['weekdays'] = ! empty( $data['weekdays'] ) ? sanitize_text_field( (string) $data['weekdays'] ) : null;
			$formats[]          = '%s';
		}
		if ( isset( $data['adjustment_type'] ) ) {
			$fields['adjustment_type'] = sanitize_text_field( $data['adjustment_type'] );
			$formats[]                 = '%s';
		}
		if ( isset( $data['amount'] ) ) {
			$fields['amount'] = (int) $data['amount'];
			$formats[]        = '%d';
		}
		if ( array_key_exists( 'minimum_stay', $data ) ) {
			$fields['minimum_stay'] = ! empty( $data['minimum_stay'] ) ? (int) $data['minimum_stay'] : null;
			$formats[]              = '%d';
		}
		if ( array_key_exists( 'maximum_stay', $data ) ) {
			$fields['maximum_stay'] = ! empty( $data['maximum_stay'] ) ? (int) $data['maximum_stay'] : null;
			$formats[]              = '%d';
		}
		if ( isset( $data['enabled'] ) ) {
			$fields['enabled'] = ! empty( $data['enabled'] ) ? 1 : 0;
			$formats[]         = '%d';
		}

		$res = $wpdb->update(
			$this->get_table_name(),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $res;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule ID.
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
	 * Get rule by ID.
	 *
	 * @param int $id Rule ID.
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
	 * Get active rate rules for a specific room type, ordered by priority ASC.
	 *
	 * @param int $room_type_id Room type ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active_rules_for_room_type( int $room_type_id ): array {
		global $wpdb;
		if ( ! $wpdb || $room_type_id <= 0 ) {
			return array();
		}

		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE room_type_id = %d AND enabled = 1 ORDER BY priority ASC, id ASC",
			$room_type_id
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		return $results ?: array();
	}

	/**
	 * Get all rules with filtering.
	 *
	 * @param array $args Query parameters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		$table = $this->get_table_name();
		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['room_type_id'] ) ) {
			$where[]  = 'room_type_id = %d';
			$params[] = (int) $args['room_type_id'];
		}

		$where_clause = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = empty( $params )
			? "SELECT * FROM `{$table}` ORDER BY room_type_id ASC, priority ASC"
			: $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY room_type_id ASC, priority ASC", ...$params );

		$results = $wpdb->get_results( $sql, ARRAY_A );
		return $results ?: array();
	}
}
