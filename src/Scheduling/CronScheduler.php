<?php
/**
 * Background Task and Cron Scheduler for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Scheduling
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Scheduling;

use WPCalibrate\StayReserve\Inventory\HoldManager;
use WPCalibrate\StayReserve\Logging\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CronScheduler
 */
class CronScheduler {

	public const HOURLY_CLEANUP_HOOK = 'wpcsr_clean_expired_holds';

	private HoldManager $hold_manager;
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param HoldManager $hold_manager Hold manager instance.
	 * @param Logger      $logger Logger instance.
	 */
	public function __construct( HoldManager $hold_manager, Logger $logger ) {
		$this->hold_manager = $hold_manager;
		$this->logger       = $logger;
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::HOURLY_CLEANUP_HOOK, array( $this, 'run_expired_holds_cleanup' ) );
	}

	/**
	 * Schedule plugin recurring events.
	 *
	 * @return void
	 */
	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( self::HOURLY_CLEANUP_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::HOURLY_CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule recurring events on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_events(): void {
		$timestamp = wp_next_scheduled( self::HOURLY_CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOURLY_CLEANUP_HOOK );
		}
	}

	/**
	 * Execute idempotent purge of expired checkout holds.
	 *
	 * @return void
	 */
	public function run_expired_holds_cleanup(): void {
		$purged_count = $this->hold_manager->cleanup_expired_holds();
		if ( $purged_count > 0 ) {
			$this->logger->info(
				sprintf( 'Cron job purged %d expired checkout inventory hold(s).', $purged_count ),
				array( 'source' => 'cron' )
			);
		}
	}
}
