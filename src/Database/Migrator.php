<?php
/**
 * Database Migrator for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Database
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Database;

use WPCalibrate\StayReserve\Logging\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Migrator
 */
class Migrator {

	/**
	 * Schema handler.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Schema service.
	 * @param Logger $logger Logger service.
	 */
	public function __construct( Schema $schema, Logger $logger ) {
		$this->schema = $schema;
		$this->logger = $logger;
	}

	/**
	 * Check installed version against code version and run migrations if needed.
	 *
	 * @return bool True if migration was executed or up to date, false on error.
	 */
	public function check_and_migrate(): bool {
		$installed_ver = get_option( 'wpcsr_db_version', '0.0.0' );

		if ( version_compare( $installed_ver, WPCSR_DB_VERSION, '<' ) ) {
			return $this->run_migration( $installed_ver, WPCSR_DB_VERSION );
		}

		return true;
	}

	/**
	 * Run migration sequence forward from installed version to target version.
	 *
	 * @param string $from_version Starting database version.
	 * @param string $to_version Target database version.
	 * @return bool
	 */
	public function run_migration( string $from_version, string $to_version ): bool {
		$this->logger->info(
			sprintf( 'Starting database migration from %s to %s', $from_version, $to_version ),
			array( 'source' => 'migrator' )
		);

		try {
			// Always ensure tables match the current dbDelta schema definitions
			Schema::create_tables();

			// Specific version upgrades can be handled here sequentially
			if ( version_compare( $from_version, '1.0.0', '<' ) ) {
				$this->migrate_to_1_0_0();
			}

			// Update option to new version
			update_option( 'wpcsr_db_version', $to_version, false );

			$this->logger->info(
				sprintf( 'Database migration to %s completed successfully', $to_version ),
				array( 'source' => 'migrator' )
			);
			return true;
		} catch ( \Throwable $e ) {
			$this->logger->error(
				sprintf( 'Database migration failed: %s', $e->getMessage() ),
				array(
					'exception' => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
				)
			);
			return false;
		}
	}

	/**
	 * Initial schema setup (v1.0.0).
	 *
	 * @return void
	 */
	private function migrate_to_1_0_0(): void {
		// Initial setup verification: ensure tables are initialized
		Schema::create_tables();
	}
}
