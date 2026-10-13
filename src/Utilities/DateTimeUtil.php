<?php
/**
 * Date and Time Utilities for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Utilities
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Utilities;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class DateTimeUtil
 */
class DateTimeUtil {

	/**
	 * Get WordPress site DateTimeZone.
	 *
	 * @return \DateTimeZone
	 */
	public static function get_site_timezone(): \DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		$timezone_string = get_option( 'timezone_string' );
		if ( ! empty( $timezone_string ) ) {
			return new \DateTimeZone( $timezone_string );
		}

		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = (int) ( ( $offset - $hours ) * 60 );
		$sign    = $offset < 0 ? '-' : '+';

		return new \DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), abs( $minutes ) ) );
	}

	/**
	 * Get current MySQL datetime in site timezone or UTC.
	 *
	 * @param bool $utc Whether to return UTC timestamp.
	 * @return string MySQL DATETIME format (YYYY-MM-DD HH:MM:SS).
	 */
	public static function now_mysql( bool $utc = true ): string {
		$tz = $utc ? new \DateTimeZone( 'UTC' ) : self::get_site_timezone();
		$dt = new \DateTime( 'now', $tz );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Format date to standard YYYY-MM-DD.
	 *
	 * @param string $date Raw date string.
	 * @return string YYYY-MM-DD format.
	 */
	public static function format_date( string $date ): string {
		$clean = trim( $date );
		$dt    = \DateTime::createFromFormat( 'Y-m-d', $clean );
		if ( $dt && $dt->format( 'Y-m-d' ) === $clean ) {
			return $clean;
		}

		try {
			$fallback = new \DateTime( $clean );
			return $fallback->format( 'Y-m-d' );
		} catch ( \Throwable $e ) {
			return date( 'Y-m-d' );
		}
	}

	/**
	 * Calculate number of stay nights between check-in and check-out.
	 *
	 * Check-out date is departure day, NOT an occupied night.
	 *
	 * @param string $check_in Check-in date (YYYY-MM-DD).
	 * @param string $check_out Check-out date (YYYY-MM-DD).
	 * @return int Number of nights. Returns 0 if check_out <= check_in.
	 */
	public static function calculate_nights( string $check_in, string $check_out ): int {
		$dt_in  = new \DateTime( self::format_date( $check_in ) );
		$dt_out = new \DateTime( self::format_date( $check_out ) );

		if ( $dt_out <= $dt_in ) {
			return 0;
		}

		$diff = $dt_in->diff( $dt_out );
		return (int) $diff->days;
	}

	/**
	 * Get list of all occupied calendar nights in stay range [check_in, check_out).
	 *
	 * For example, check-in 2026-12-10 and check-out 2026-12-12 returns:
	 * ['2026-12-10', '2026-12-11'].
	 *
	 * @param string $check_in Check-in date.
	 * @param string $check_out Check-out date.
	 * @return array<int, string> List of night dates (YYYY-MM-DD).
	 */
	public static function get_stay_nights( string $check_in, string $check_out ): array {
		$dt_in  = new \DateTime( self::format_date( $check_in ) );
		$dt_out = new \DateTime( self::format_date( $check_out ) );

		if ( $dt_out <= $dt_in ) {
			return array();
		}

		$nights  = array();
		$current = clone $dt_in;
		$one_day = new \DateInterval( 'P1D' );

		while ( $current < $dt_out ) {
			$nights[] = $current->format( 'Y-m-d' );
			$current->add( $one_day );
		}

		return $nights;
	}

	/**
	 * Check if two half-open date ranges [start1, end1) and [start2, end2) overlap.
	 *
	 * Overlap occurs if and only if start1 < end2 AND end1 > start2.
	 *
	 * @param string $start1 Start of range 1.
	 * @param string $end1 End of range 1.
	 * @param string $start2 Start of range 2.
	 * @param string $end2 End of range 2.
	 * @return bool True if overlap exists, false otherwise.
	 */
	public static function dates_overlap( string $start1, string $end1, string $start2, string $end2 ): bool {
		$s1 = self::format_date( $start1 );
		$e1 = self::format_date( $end1 );
		$s2 = self::format_date( $start2 );
		$e2 = self::format_date( $end2 );

		return ( $s1 < $e2 ) && ( $e1 > $s2 );
	}

	/**
	 * Format a date for human display in site's configured format.
	 *
	 * @param string $date Date string.
	 * @return string Localized date string.
	 */
	public static function display_date( string $date ): string {
		$format = get_option( 'date_format', 'Y-m-d' );
		try {
			$dt = new \DateTime( $date, self::get_site_timezone() );
			return $dt->format( $format );
		} catch ( \Throwable $e ) {
			return $date;
		}
	}
}
