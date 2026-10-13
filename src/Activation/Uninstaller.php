<?php
/**
 * Plugin Uninstaller for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Activation
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Activation;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Scheduling\CronScheduler;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Uninstaller
 */
class Uninstaller {

	/**
	 * Run plugin uninstall procedures.
	 *
	 * Only removes data if explicitly opted-in via settings.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$delete_data = get_option( 'wpcsr_uninstall_delete_data', 'no' );

		// Default safety check: preserve data unless explicitly instructed
		if ( 'yes' !== $delete_data ) {
			return;
		}

		// 1. Drop plugin custom tables
		Schema::drop_tables();

		// 2. Remove plugin-specific options
		$options_to_delete = array(
			'wpcsr_version',
			'wpcsr_db_version',
			'wpcsr_settings',
			'wpcsr_uninstall_delete_data',
			'wpcsr_log_entries',
			'wpcsr_license_data',
		);

		foreach ( $options_to_delete as $opt ) {
			delete_option( $opt );
		}

		// 3. Remove custom capabilities & roles
		CapabilityManager::remove_capabilities();

		// 4. Unschedule cron tasks
		CronScheduler::unschedule_events();

		// 5. Delete plugin transients
		delete_transient( 'wpcsr_flush_rewrite_rules' );
		delete_transient( 'wpcsr_system_status_cache' );
	}
}
