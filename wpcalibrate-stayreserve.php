<?php
/**
 * Plugin Name:       WPCalibrate StayReserve
 * Plugin URI:        https://marketplace.wpcalibrate.com/
 * Description:       A professional hotel and accommodation booking engine for WordPress with real-time inventory, date-based pricing, and optional WooCommerce checkout.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-stayreserve
 * Domain Path:       /languages
 *
 * @package WPCalibrate\StayReserve
 */

declare(strict_types=1);

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPCSR_VERSION', '1.0.0' );
define( 'WPCSR_DB_VERSION', '1.0.0' );
define( 'WPCSR_MIN_PHP_VERSION', '8.2.0' );
define( 'WPCSR_MIN_WP_VERSION', '6.2' );
define( 'WPCSR_FILE', __FILE__ );
define( 'WPCSR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCSR_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCSR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check system environment requirements before bootstrapping.
 *
 * @return bool True if requirements are satisfied, false otherwise.
 */
function wpcsr_check_environment(): bool {
	$php_valid = version_compare( PHP_VERSION, WPCSR_MIN_PHP_VERSION, '>=' );
	global $wp_version;
	$wp_valid  = version_compare( $wp_version ?? '0.0.0', WPCSR_MIN_WP_VERSION, '>=' );

	if ( ! $php_valid || ! $wp_valid ) {
		add_action(
			'admin_notices',
			function () use ( $php_valid, $wp_valid ) {
				$messages = array();
				if ( ! $php_valid ) {
					/* translators: 1: Required PHP version, 2: Current PHP version */
					$messages[] = sprintf(
						esc_html__( 'WPCalibrate StayReserve requires PHP %1$s or higher. Your server is running PHP %2$s.', 'wpcalibrate-stayreserve' ),
						WPCSR_MIN_PHP_VERSION,
						PHP_VERSION
					);
				}
				if ( ! $wp_valid ) {
					global $wp_version;
					/* translators: 1: Required WP version, 2: Current WP version */
					$messages[] = sprintf(
						esc_html__( 'WPCalibrate StayReserve requires WordPress %1$s or higher. Your site is running WordPress %2$s.', 'wpcalibrate-stayreserve' ),
						WPCSR_MIN_WP_VERSION,
						$wp_version ?? 'unknown'
					);
				}
				?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'WPCalibrate StayReserve Error:', 'wpcalibrate-stayreserve' ); ?></strong></p>
					<?php foreach ( $messages as $msg ) : ?>
						<p><?php echo esc_html( $msg ); ?></p>
					<?php endforeach; ?>
				</div>
				<?php
			}
		);
		return false;
	}

	return true;
}

if ( ! wpcsr_check_environment() ) {
	return;
}

// Require PSR-4 Autoloader.
require_once WPCSR_PATH . 'src/Autoloader.php';
\WPCalibrate\StayReserve\Autoloader::register( WPCSR_PATH . 'src' );

// Register Activation Hook.
register_activation_hook(
	__FILE__,
	static function () {
		\WPCalibrate\StayReserve\Activation\Activator::activate();
	}
);

// Register Deactivation Hook.
register_deactivation_hook(
	__FILE__,
	static function () {
		\WPCalibrate\StayReserve\Activation\Deactivator::deactivate();
	}
);

/**
 * Bootstrap the plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		\WPCalibrate\StayReserve\Plugin::instance()->init();
	}
);
