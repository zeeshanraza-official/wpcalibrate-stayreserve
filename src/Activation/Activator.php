<?php
/**
 * Plugin Activator for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Activation
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Activation;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Scheduling\CronScheduler;
use WPCalibrate\StayReserve\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run plugin activation routines.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// 1. Verify minimum environment requirements
		if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( WPCSR_FILE ) );
			}
			wp_die(
				esc_html__( 'WPCalibrate StayReserve requires PHP 8.2 or higher. Activation aborted.', 'wpcalibrate-stayreserve' )
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version ?? '0.0.0', '6.2', '<' ) ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( WPCSR_FILE ) );
			}
			wp_die(
				esc_html__( 'WPCalibrate StayReserve requires WordPress 6.2 or higher. Activation aborted.', 'wpcalibrate-stayreserve' )
			);
		}

		// 2. Install database tables
		Schema::create_tables();

		// 3. Store database and plugin versions
		update_option( 'wpcsr_db_version', WPCSR_DB_VERSION, false );
		update_option( 'wpcsr_version', WPCSR_VERSION, false );

		// 4. Register custom capabilities and roles
		CapabilityManager::register_capabilities();

		// 5. Initialize default settings if not already present
		SettingsManager::initialize_defaults();

		// 6. Schedule background cron tasks
		CronScheduler::schedule_events();

		// 7. Flag rewrite rules for flush on next load
		set_transient( 'wpcsr_flush_rewrite_rules', 1, 60 );
	}
}
