<?php
/**
 * Cryptographic Token Manager for secure guest booking management.
 *
 * @package WPCalibrate\StayReserve\Security
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Security;

use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class TokenManager
 */
class TokenManager {

	/**
	 * Default token validity duration in days.
	 */
	public const TOKEN_LIFETIME_DAYS = 30;

	/**
	 * Generate a high-entropy random token.
	 *
	 * @param int $length Byte length.
	 * @return string Hexadecimal token string.
	 */
	public static function generate_token( int $length = 32 ): string {
		return bin2hex( random_bytes( $length ) );
	}

	/**
	 * Compute SHA-256 hash of a plaintext token for safe storage.
	 *
	 * @param string $token Plaintext token.
	 * @return string 64-char hex hash.
	 */
	public static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Verify a raw token against a stored hash using timing-safe comparison.
	 *
	 * @param string      $raw_token Plaintext token from request.
	 * @param string      $stored_hash Hash stored in database.
	 * @param string|null $expires_at Expiration MySQL datetime.
	 * @return bool True if valid and not expired, false otherwise.
	 */
	public static function verify_token( string $raw_token, string $stored_hash, ?string $expires_at = null ): bool {
		if ( empty( $raw_token ) || empty( $stored_hash ) ) {
			return false;
		}

		// Check expiration if provided
		if ( ! empty( $expires_at ) ) {
			$exp_time = strtotime( $expires_at . ' UTC' );
			if ( false !== $exp_time && time() > $exp_time ) {
				return false;
			}
		}

		$calculated_hash = self::hash_token( $raw_token );
		return hash_equals( $stored_hash, $calculated_hash );
	}

	/**
	 * Calculate token expiry timestamp.
	 *
	 * @param int $days Days until expiration.
	 * @return string MySQL DATETIME in UTC.
	 */
	public static function get_expiration_datetime( int $days = self::TOKEN_LIFETIME_DAYS ): string {
		$dt = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$dt->add( new \DateInterval( "P{$days}D" ) );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Generate public booking reference (e.g., 'SR-8F4A2B9C').
	 *
	 * Format: Non-sequential, human-friendly, uppercase alphanumeric.
	 *
	 * @return string
	 */
	public static function generate_public_reference(): string {
		$random = strtoupper( bin2hex( random_bytes( 4 ) ) );
		return 'SR-' . $random;
	}
}
