<?php
/**
 * Inventory Hold Manager for temporary checkout claims.
 *
 * @package WPCalibrate\StayReserve\Inventory
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Inventory;

use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Security\TokenManager;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class HoldManager
 */
class HoldManager {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return Schema::get_table_name( 'inventory_holds' );
	}

	/**
	 * Create a temporary inventory hold for a physical room unit.
	 *
	 * @param int    $room_type_id Room Type CPT ID.
	 * @param int    $room_unit_id Physical unit ID.
	 * @param string $check_in Check-in date (YYYY-MM-DD).
	 * @param string $check_out Check-out date (YYYY-MM-DD).
	 * @param string $session_id Session or customer correlation ID.
	 * @return array{ token: string, expires_at: string, hold_id: int }|null
	 */
	public function create_hold( int $room_type_id, int $room_unit_id, string $check_in, string $check_out, string $session_id ): ?array {
		global $wpdb;
		if ( ! $wpdb ) {
			return null;
		}

		$enabled = SettingsManager::get( 'inventory', 'enable_holds', true );
		if ( ! $enabled ) {
			return null;
		}

		// Opportunistically purge expired holds
		$this->cleanup_expired_holds();

		$duration_mins = (int) SettingsManager::get( 'inventory', 'hold_duration_minutes', 15 );
		if ( $duration_mins <= 0 ) {
			$duration_mins = 15;
		}

		$raw_token  = TokenManager::generate_token( 24 );
		$token_hash = TokenManager::hash_token( $raw_token );

		$dt_expires = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$dt_expires->add( new \DateInterval( "PT{$duration_mins}M" ) );
		$expires_at = $dt_expires->format( 'Y-m-d H:i:s' );
		$now        = DateTimeUtil::now_mysql( true );

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'token_hash'   => $token_hash,
				'session_id'   => sanitize_text_field( $session_id ),
				'room_type_id' => $room_type_id,
				'room_unit_id' => $room_unit_id,
				'check_in'     => DateTimeUtil::format_date( $check_in ),
				'check_out'    => DateTimeUtil::format_date( $check_out ),
				'expires_at'   => $expires_at,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return array(
			'token'      => $raw_token,
			'expires_at' => $expires_at,
			'hold_id'    => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Verify if a hold token is valid and active.
	 *
	 * @param string $token Raw token.
	 * @return array<string, mixed>|null Hold row or null.
	 */
	public function get_hold_by_token( string $token ): ?array {
		global $wpdb;
		if ( ! $wpdb || empty( $token ) ) {
			return null;
		}

		$table      = $this->get_table_name();
		$token_hash = TokenManager::hash_token( $token );
		$now_utc    = DateTimeUtil::now_mysql( true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE token_hash = %s AND expires_at > %s LIMIT 1",
				$token_hash,
				$now_utc
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Release (delete) a specific hold.
	 *
	 * @param string $token Raw hold token.
	 * @return bool
	 */
	public function release_hold( string $token ): bool {
		global $wpdb;
		if ( ! $wpdb || empty( $token ) ) {
			return false;
		}

		$token_hash = TokenManager::hash_token( $token );
		$res        = $wpdb->delete(
			$this->get_table_name(),
			array( 'token_hash' => $token_hash ),
			array( '%s' )
		);

		return false !== $res && $res > 0;
	}

	/**
	 * Release holds by session ID.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Number of holds released.
	 */
	public function release_session_holds( string $session_id ): int {
		global $wpdb;
		if ( ! $wpdb || empty( $session_id ) ) {
			return 0;
		}

		$res = $wpdb->delete(
			$this->get_table_name(),
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		return false !== $res ? (int) $res : 0;
	}

	/**
	 * Check if a unit has an active, non-expired hold overlapping the requested dates.
	 *
	 * @param int         $room_unit_id Physical unit ID.
	 * @param string      $check_in Check-in date.
	 * @param string      $check_out Check-out date.
	 * @param string|null $exclude_token Raw hold token to ignore (e.g. current user's hold).
	 * @return bool True if held by someone else, false if free.
	 */
	public function is_unit_held( int $room_unit_id, string $check_in, string $check_out, ?string $exclude_token = null ): bool {
		global $wpdb;
		if ( ! $wpdb || $room_unit_id <= 0 ) {
			return false;
		}

		$table       = $this->get_table_name();
		$now_utc     = DateTimeUtil::now_mysql( true );
		$clean_in    = DateTimeUtil::format_date( $check_in );
		$clean_out   = DateTimeUtil::format_date( $check_out );

		$params = array( $room_unit_id, $now_utc, $clean_in, $clean_out );
		$exclude_sql = '';

		if ( ! empty( $exclude_token ) ) {
			$exclude_hash = TokenManager::hash_token( $exclude_token );
			$exclude_sql  = ' AND token_hash != %s';
			$params[]     = $exclude_hash;
		}

		// Overlap condition for stay: hold.check_in < req.check_out AND hold.check_out > req.check_in
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE room_unit_id = %d AND expires_at > %s AND check_in < %s AND check_out > %s {$exclude_sql}",
			$room_unit_id,
			$now_utc,
			$clean_out,
			$clean_in,
			... ( ! empty( $exclude_token ) ? array( TokenManager::hash_token( $exclude_token ) ) : array() )
		);

		return ( (int) $wpdb->get_var( $sql ) ) > 0;
	}

	/**
	 * Purge all expired holds from the database.
	 *
	 * Idempotent: can run repeatedly via cron, Action Scheduler, or opportunistically.
	 *
	 * @return int Number of expired holds deleted.
	 */
	public function cleanup_expired_holds(): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$table   = $this->get_table_name();
		$now_utc = DateTimeUtil::now_mysql( true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at <= %s", $now_utc )
		);

		return false !== $deleted ? (int) $deleted : 0;
	}
}
