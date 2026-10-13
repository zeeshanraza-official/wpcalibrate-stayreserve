<?php
/**
 * Fired when the plugin is deleted.
 *
 * @package WPCalibrate\StayReserve
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader.
$autoloader_file = __DIR__ . '/src/Autoloader.php';
if ( file_exists( $autoloader_file ) ) {
	require_once $autoloader_file;
	\WPCalibrate\StayReserve\Autoloader::register( __DIR__ . '/src' );

	\WPCalibrate\StayReserve\Activation\Uninstaller::uninstall();
}
