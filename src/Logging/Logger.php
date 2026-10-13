<?php
/**
 * Structured Logger for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Logging
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Logging;

use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Logger
 */
class Logger {

	public const LEVEL_DEBUG   = 'debug';
	public const LEVEL_INFO    = 'info';
	public const LEVEL_WARNING = 'warning';
	public const LEVEL_ERROR   = 'error';

	private const LEVEL_PRIORITY = array(
		self::LEVEL_DEBUG   => 10,
		self::LEVEL_INFO    => 20,
		self::LEVEL_WARNING => 30,
		self::LEVEL_ERROR   => 40,
	);

	/**
	 * Log entries storage option key.
	 */
	public const LOG_OPTION = 'wpcsr_log_entries';
	public const MAX_ENTRIES = 500;

	/**
	 * Log an error message.
	 *
	 * @param string $message Message text.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message text.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message Message text.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message text.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Core logging method.
	 *
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$configured_level = SettingsManager::get( 'advanced', 'log_level', 'info' );
		$min_prio         = self::LEVEL_PRIORITY[ $configured_level ] ?? 20;
		$curr_prio        = self::LEVEL_PRIORITY[ $level ] ?? 20;

		if ( $curr_prio < $min_prio ) {
			return;
		}

		$sanitized_context = $this->sanitize_context( $context );

		$entry = array(
			'timestamp' => DateTimeUtil::now_mysql( false ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $sanitized_context,
		);

		// If WooCommerce WC_Logger is active, also pipe through it
		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			$wc_logger->log( $level, $message, array( 'source' => 'wpcalibrate-stayreserve' ) );
		}

		$entries = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		$entries[] = $entry;
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		update_option( self::LOG_OPTION, $entries, false );
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit Maximum entries to fetch.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_entries( int $limit = 100 ): array {
		$entries = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		return array_slice( array_reverse( $entries ), 0, $limit );
	}

	/**
	 * Clear stored logs.
	 *
	 * @return void
	 */
	public function clear(): void {
		update_option( self::LOG_OPTION, array(), false );
	}

	/**
	 * Mask sensitive fields in context (passwords, tokens, cards, license keys).
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	private function sanitize_context( array $context ): array {
		$sensitive_patterns = array( 'password', 'token', 'secret', 'card', 'cvv', 'license_key', 'api_key', 'nonce' );
		$clean = array();

		foreach ( $context as $key => $value ) {
			$key_str = strtolower( (string) $key );
			$is_sensitive = false;
			foreach ( $sensitive_patterns as $p ) {
				if ( false !== strpos( $key_str, $p ) ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( $is_sensitive ) {
				$clean[ $key ] = '***REDACTED***';
			} elseif ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_context( $value );
			} else {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}
}
