<?php
/**
 * Extra Repository for managing optional accommodation add-ons.
 *
 * @package WPCalibrate\StayReserve\Extras
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Extras;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class ExtraRepository
 */
class ExtraRepository {

	public const TYPE_PER_STAY                = 'per_stay';
	public const TYPE_PER_NIGHT               = 'per_night';
	public const TYPE_PER_GUEST               = 'per_guest';
	public const TYPE_PER_GUEST_PER_NIGHT     = 'per_guest_per_night';
	public const TYPE_QUANTITY_BASED          = 'quantity_based';

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Schema::get_table_name( 'extras' );
	}

	/**
	 * Create an extra add-on.
	 *
	 * @param array $data Extra data.
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
				'name'                  => sanitize_text_field( $data['name'] ),
				'description'           => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'price'                 => (int) $data['price'],
				'pricing_type'          => sanitize_text_field( $data['pricing_type'] ?? self::TYPE_PER_STAY ),
				'is_optional'           => ! empty( $data['is_optional'] ) ? 1 : 0,
				'applicable_room_types' => ! empty( $data['applicable_room_types'] ) ? wp_json_encode( (array) $data['applicable_room_types'] ) : null,
				'max_quantity'          => (int) ( $data['max_quantity'] ?? 10 ),
				'is_taxable'            => ! empty( $data['is_taxable'] ) ? 1 : 0,
				'enabled'               => ! empty( $data['enabled'] ) ? 1 : 0,
				'sort_order'            => (int) ( $data['sort_order'] ?? 0 ),
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an extra.
	 *
	 * @param int   $id Extra ID.
	 * @param array $data Updated fields.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		if ( ! $wpdb || $id <= 0 ) {
			return false;
		}

		$fields  = array( 'updated_at' => DateTimeUtil::now_mysql() );
		$formats = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$formats[]      = '%s';
		}
		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = null !== $data['description'] ? sanitize_textarea_field( (string) $data['description'] ) : null;
			$formats[]            = '%s';
		}
		if ( isset( $data['price'] ) ) {
			$fields['price'] = (int) $data['price'];
			$formats[]       = '%d';
		}
		if ( isset( $data['pricing_type'] ) ) {
			$fields['pricing_type'] = sanitize_text_field( $data['pricing_type'] );
			$formats[]              = '%s';
		}
		if ( isset( $data['is_optional'] ) ) {
			$fields['is_optional'] = ! empty( $data['is_optional'] ) ? 1 : 0;
			$formats[]             = '%d';
		}
		if ( array_key_exists( 'applicable_room_types', $data ) ) {
			$fields['applicable_room_types'] = ! empty( $data['applicable_room_types'] ) ? wp_json_encode( (array) $data['applicable_room_types'] ) : null;
			$formats[]                       = '%s';
		}
		if ( isset( $data['max_quantity'] ) ) {
			$fields['max_quantity'] = (int) $data['max_quantity'];
			$formats[]              = '%d';
		}
		if ( isset( $data['is_taxable'] ) ) {
			$fields['is_taxable'] = ! empty( $data['is_taxable'] ) ? 1 : 0;
			$formats[]            = '%d';
		}
		if ( isset( $data['enabled'] ) ) {
			$fields['enabled'] = ! empty( $data['enabled'] ) ? 1 : 0;
			$formats[]         = '%d';
		}
		if ( isset( $data['sort_order'] ) ) {
			$fields['sort_order'] = (int) $data['sort_order'];
			$formats[]            = '%d';
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
	 * Delete an extra.
	 *
	 * @param int $id Extra ID.
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
	 * Get an extra by ID.
	 *
	 * @param int $id Extra ID.
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
	 * Get all enabled extras available for a room type.
	 *
	 * @param int|null $room_type_id Specific room type or null for all.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_available_for_room( ?int $room_type_id = null ): array {
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT * FROM `{$table}` WHERE enabled = 1 ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		if ( null === $room_type_id ) {
			return $results;
		}

		// Filter matching room types
		$filtered = array();
		foreach ( $results as $item ) {
			if ( empty( $item['applicable_room_types'] ) ) {
				$filtered[] = $item;
				continue;
			}
			$types = json_decode( $item['applicable_room_types'], true );
			if ( ! is_array( $types ) || empty( $types ) || in_array( $room_type_id, $types, true ) ) {
				$filtered[] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Calculate total price in minor units for an extra given stay parameters.
	 *
	 * @param array $extra Extra record.
	 * @param int   $quantity Quantity selected.
	 * @param int   $nights Stay nights count.
	 * @param int   $guests Total guests count.
	 * @return int Total minor units.
	 */
	public static function calculate_price( array $extra, int $quantity, int $nights, int $guests ): int {
		$unit_price = (int) $extra['price'];
		$type       = (string) $extra['pricing_type'];
		$qty        = max( 1, $quantity );
		$nights_cnt = max( 1, $nights );
		$guests_cnt = max( 1, $guests );

		return match ( $type ) {
			self::TYPE_PER_STAY            => $unit_price * $qty,
			self::TYPE_PER_NIGHT           => $unit_price * $nights_cnt * $qty,
			self::TYPE_PER_GUEST           => $unit_price * $guests_cnt * $qty,
			self::TYPE_PER_GUEST_PER_NIGHT => $unit_price * $guests_cnt * $nights_cnt * $qty,
			self::TYPE_QUANTITY_BASED      => $unit_price * $qty,
			default                        => $unit_price * $qty,
		};
	}
}
