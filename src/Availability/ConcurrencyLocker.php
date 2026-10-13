<?php
/**
 * Concurrency Locker for atomic inventory allocation.
 *
 * @package WPCalibrate\StayReserve\Availability
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Availability;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class ConcurrencyLocker
 */
class ConcurrencyLocker {

	/**
	 * Active fallback memory locks for test environments.
	 *
	 * @var array<string, bool>
	 */
	private static array $memory_locks = array();

	/**
	 * Execute an operation under a unit-specific exclusive lock.
	 *
	 * @param int      $room_unit_id Physical unit ID.
	 * @param callable $operation Operation to execute while lock is held.
	 * @param int      $timeout_seconds Maximum wait time in seconds.
	 * @return mixed Return value of the callable.
	 * @throws \RuntimeException If the lock cannot be acquired.
	 */
	public function with_unit_lock( int $room_unit_id, callable $operation, int $timeout_seconds = 10 ): mixed {
		$lock_name = 'wpcsr_unit_' . $room_unit_id;
		$acquired  = $this->acquire_lock( $lock_name, $timeout_seconds );

		if ( ! $acquired ) {
			throw new \RuntimeException(
				esc_html__( 'Unable to acquire inventory lock. Another reservation is currently processing for this room.', 'wpcalibrate-stayreserve' )
			);
		}

		try {
			return $operation();
		} finally {
			$this->release_lock( $lock_name );
		}
	}

	/**
	 * Acquire an exclusive lock.
	 *
	 * @param string $lock_name Lock identifier.
	 * @param int    $timeout_seconds Wait timeout.
	 * @return bool True if acquired.
	 */
	public function acquire_lock( string $lock_name, int $timeout_seconds = 10 ): bool {
		global $wpdb;

		// Use MySQL GET_LOCK when available
		if ( $wpdb && method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $wpdb->get_var(
				$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $timeout_seconds )
			);
			if ( '1' === (string) $res ) {
				return true;
			}
		}

		// Fallback memory mutex
		if ( isset( self::$memory_locks[ $lock_name ] ) ) {
			return false;
		}

		self::$memory_locks[ $lock_name ] = true;
		return true;
	}

	/**
	 * Release an exclusive lock.
	 *
	 * @param string $lock_name Lock identifier.
	 * @return bool
	 */
	public function release_lock( string $lock_name ): bool {
		global $wpdb;

		if ( $wpdb && method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $wpdb->get_var(
				$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name )
			);
			unset( self::$memory_locks[ $lock_name ] );
			return '1' === (string) $res;
		}

		unset( self::$memory_locks[ $lock_name ] );
		return true;
	}
}
