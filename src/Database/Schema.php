<?php
/**
 * Database Schema Definition for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Database
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Schema
 */
class Schema {

	/**
	 * Get table names mapping without prefix.
	 *
	 * @return array<string, string>
	 */
	public static function get_table_keys(): array {
		return array(
			'room_units'     => 'wpcsr_room_units',
			'bookings'       => 'wpcsr_bookings',
			'booking_items'  => 'wpcsr_booking_items',
			'inventory_holds'=> 'wpcsr_inventory_holds',
			'blocks'         => 'wpcsr_blocks',
			'rate_rules'     => 'wpcsr_rate_rules',
			'extras'         => 'wpcsr_extras',
			'booking_extras' => 'wpcsr_booking_extras',
			'events'         => 'wpcsr_events',
		);
	}

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @param string $key Table key (e.g., 'bookings').
	 * @return string Full table name.
	 */
	public static function get_table_name( string $key ): string {
		global $wpdb;
		$keys = self::get_table_keys();
		$suffix = $keys[ $key ] ?? 'wpcsr_' . $key;
		return ( $wpdb ? $wpdb->prefix : 'wp_' ) . $suffix;
	}

	/**
	 * Create all custom plugin tables using dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = '';
		if ( $wpdb && method_exists( $wpdb, 'get_charset_collate' ) ) {
			$charset_collate = $wpdb->get_charset_collate();
		} else {
			$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
		}

		$units_table          = self::get_table_name( 'room_units' );
		$bookings_table       = self::get_table_name( 'bookings' );
		$booking_items_table  = self::get_table_name( 'booking_items' );
		$holds_table          = self::get_table_name( 'inventory_holds' );
		$blocks_table         = self::get_table_name( 'blocks' );
		$rate_rules_table     = self::get_table_name( 'rate_rules' );
		$extras_table         = self::get_table_name( 'extras' );
		$booking_extras_table = self::get_table_name( 'booking_extras' );
		$events_table         = self::get_table_name( 'events' );

		$queries = array();

		// 1. Room Units
		$queries[] = "CREATE TABLE {$units_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NOT NULL,
			unit_name VARCHAR(191) NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'active',
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY room_type_id (room_type_id),
			KEY status (status)
		) {$charset_collate};";

		// 2. Bookings
		$queries[] = "CREATE TABLE {$bookings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_reference VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'pending',
			payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
			check_in DATE NOT NULL,
			check_out DATE NOT NULL,
			adults INT UNSIGNED NOT NULL DEFAULT 1,
			children INT UNSIGNED NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			accommodation_subtotal BIGINT NOT NULL DEFAULT 0,
			extras_total BIGINT NOT NULL DEFAULT 0,
			discount_total BIGINT NOT NULL DEFAULT 0,
			fee_total BIGINT NOT NULL DEFAULT 0,
			tax_total BIGINT NOT NULL DEFAULT 0,
			grand_total BIGINT NOT NULL DEFAULT 0,
			customer_first_name VARCHAR(100) NOT NULL,
			customer_last_name VARCHAR(100) NOT NULL,
			customer_email VARCHAR(191) NOT NULL,
			customer_phone VARCHAR(50) NOT NULL DEFAULT '',
			customer_address TEXT NULL,
			customer_company VARCHAR(191) NULL,
			customer_note TEXT NULL,
			admin_notes TEXT NULL,
			wc_order_id BIGINT UNSIGNED NULL,
			source VARCHAR(50) NOT NULL DEFAULT 'website',
			management_token_hash VARCHAR(64) NULL,
			management_token_expires_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_reference (public_reference),
			KEY user_id (user_id),
			KEY status (status),
			KEY payment_status (payment_status),
			KEY check_in_out (check_in, check_out),
			KEY customer_email (customer_email),
			KEY wc_order_id (wc_order_id)
		) {$charset_collate};";

		// 3. Booking Items
		$queries[] = "CREATE TABLE {$booking_items_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			room_type_id BIGINT UNSIGNED NOT NULL,
			room_unit_id BIGINT UNSIGNED NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			adults INT UNSIGNED NOT NULL DEFAULT 1,
			children INT UNSIGNED NOT NULL DEFAULT 0,
			nightly_subtotal BIGINT NOT NULL DEFAULT 0,
			line_total BIGINT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY room_type_id (room_type_id),
			KEY room_unit_id (room_unit_id)
		) {$charset_collate};";

		// 4. Inventory Holds
		$queries[] = "CREATE TABLE {$holds_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(64) NOT NULL,
			session_id VARCHAR(128) NOT NULL,
			room_type_id BIGINT UNSIGNED NOT NULL,
			room_unit_id BIGINT UNSIGNED NOT NULL,
			check_in DATE NOT NULL,
			check_out DATE NOT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY lookup_dates_unit (room_unit_id, check_in, check_out),
			KEY expires_at (expires_at),
			KEY session_id (session_id)
		) {$charset_collate};";

		// 5. Blocks / Maintenance
		$queries[] = "CREATE TABLE {$blocks_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NULL,
			room_unit_id BIGINT UNSIGNED NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			reason VARCHAR(255) NOT NULL DEFAULT '',
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY dates_unit (room_unit_id, start_date, end_date),
			KEY dates_type (room_type_id, start_date, end_date)
		) {$charset_collate};";

		// 6. Rate Rules
		$queries[] = "CREATE TABLE {$rate_rules_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			rule_type VARCHAR(50) NOT NULL,
			priority INT NOT NULL DEFAULT 10,
			start_date DATE NULL,
			end_date DATE NULL,
			weekdays VARCHAR(50) NULL,
			adjustment_type VARCHAR(50) NOT NULL DEFAULT 'fixed',
			amount BIGINT NOT NULL DEFAULT 0,
			minimum_stay INT UNSIGNED NULL,
			maximum_stay INT UNSIGNED NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY room_type_rules (room_type_id, enabled, priority),
			KEY dates (start_date, end_date)
		) {$charset_collate};";

		// 7. Extras Catalog
		$queries[] = "CREATE TABLE {$extras_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			description TEXT NULL,
			price BIGINT NOT NULL DEFAULT 0,
			pricing_type VARCHAR(50) NOT NULL DEFAULT 'per_stay',
			is_optional TINYINT(1) NOT NULL DEFAULT 1,
			applicable_room_types TEXT NULL,
			max_quantity INT UNSIGNED NOT NULL DEFAULT 10,
			is_taxable TINYINT(1) NOT NULL DEFAULT 1,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY enabled (enabled)
		) {$charset_collate};";

		// 8. Booking Extras Line Items
		$queries[] = "CREATE TABLE {$booking_extras_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			extra_id BIGINT UNSIGNED NULL,
			name VARCHAR(191) NOT NULL,
			pricing_type VARCHAR(50) NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			unit_price BIGINT NOT NULL DEFAULT 0,
			total_price BIGINT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id)
		) {$charset_collate};";

		// 9. Operational Events Audit Log
		$queries[] = "CREATE TABLE {$events_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NULL,
			event_type VARCHAR(100) NOT NULL,
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		if ( function_exists( 'dbDelta' ) ) {
			foreach ( $queries as $sql ) {
				dbDelta( $sql );
			}
		} elseif ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( $queries as $sql ) {
				dbDelta( $sql );
			}
		} elseif ( $wpdb ) {
			foreach ( $queries as $sql ) {
				$wpdb->query( $sql );
			}
		}
	}

	/**
	 * Drop all custom plugin tables.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		$tables = array(
			self::get_table_name( 'events' ),
			self::get_table_name( 'booking_extras' ),
			self::get_table_name( 'extras' ),
			self::get_table_name( 'rate_rules' ),
			self::get_table_name( 'blocks' ),
			self::get_table_name( 'inventory_holds' ),
			self::get_table_name( 'booking_items' ),
			self::get_table_name( 'bookings' ),
			self::get_table_name( 'room_units' ),
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`;" );
		}
	}
}
