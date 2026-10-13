<?php
/**
 * Plugin Deactivator for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Activation
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Activation;

use WPCalibrate\StayReserve\Scheduling\CronScheduler;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run plugin deactivation routines.
	 *
	 * Note: Never delete customer data, settings, or database tables on deactivation!
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// 1. Unschedule cron tasks
		CronScheduler::unschedule_events();

		// 2. Clear plugin temporary transients
		delete_transient( 'wpcsr_flush_rewrite_rules' );
		delete_transient( 'wpcsr_system_status_cache' );

		// 3. Clear rewrite rules
		flush_rewrite_rules( false );
	}
}
