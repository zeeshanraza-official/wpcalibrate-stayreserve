<?php
/**
 * PSR-4 Autoloader for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Prefix namespace to match.
	 *
	 * @var string
	 */
	private const PREFIX = 'WPCalibrate\\StayReserve\\';

	/**
	 * Base directory for namespace prefix.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Root directory containing classes.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Root directory containing classes.
	 * @return self
	 */
	public static function register( string $base_dir ): self {
		$loader = new self( $base_dir );
		spl_autoload_register( array( $loader, 'autoload' ) );
		return $loader;
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Fully qualified class name.
	 * @return bool True if loaded, false otherwise.
	 */
	public function autoload( string $class ): bool {
		$len = strlen( self::PREFIX );
		if ( strncmp( self::PREFIX, $class, $len ) !== 0 ) {
			return false;
		}

		$relative_class = substr( $class, $len );
		$file           = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
			return true;
		}

		return false;
	}
}
