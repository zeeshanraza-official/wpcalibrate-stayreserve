<?php
/**
 * Money and Currency Utilities for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Utilities
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Utilities;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class MoneyUtil
 */
class MoneyUtil {

	/**
	 * Map of currencies with zero or three decimal places.
	 *
	 * Standard currencies default to 2 decimal places.
	 */
	private const ZERO_DECIMAL_CURRENCIES = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
	private const THREE_DECIMAL_CURRENCIES = array( 'BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND' );

	/**
	 * Get decimal places for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'EUR', 'JPY').
	 * @return int Number of decimal places.
	 */
	public static function get_currency_decimals( string $currency = 'USD' ): int {
		$code = strtoupper( trim( $currency ) );
		if ( in_array( $code, self::ZERO_DECIMAL_CURRENCIES, true ) ) {
			return 0;
		}
		if ( in_array( $code, self::THREE_DECIMAL_CURRENCIES, true ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * Convert decimal amount into minor units (integer cents).
	 *
	 * @param float|int|string $decimal_amount Amount in major units.
	 * @param string           $currency Currency code.
	 * @return int Minor units as integer.
	 */
	public static function decimal_to_minor( float|int|string $decimal_amount, string $currency = 'USD' ): int {
		$decimals = self::get_currency_decimals( $currency );
		$multiplier = 10 ** $decimals;
		return (int) round( ( (float) $decimal_amount ) * $multiplier );
	}

	/**
	 * Convert integer minor units back to standard float decimal.
	 *
	 * @param int    $minor_units Amount in minor units.
	 * @param string $currency Currency code.
	 * @return float Major unit amount.
	 */
	public static function minor_to_decimal( int $minor_units, string $currency = 'USD' ): float {
		$decimals = self::get_currency_decimals( $currency );
		if ( 0 === $decimals ) {
			return (float) $minor_units;
		}
		return (float) ( $minor_units / ( 10 ** $decimals ) );
	}

	/**
	 * Get currency symbol.
	 *
	 * @param string $currency Currency code.
	 * @return string Symbol or code.
	 */
	public static function get_currency_symbol( string $currency = 'USD' ): string {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'CAD' => 'CA$',
			'AUD' => 'AU$',
			'JPY' => '¥',
			'CHF' => 'CHF',
			'CNY' => '¥',
			'INR' => '₹',
			'NZD' => 'NZ$',
		);
		$code = strtoupper( trim( $currency ) );
		return $symbols[ $code ] ?? $code . ' ';
	}

	/**
	 * Format minor units into display string with currency symbol.
	 *
	 * @param int         $minor_units Amount in minor units.
	 * @param string|null $currency Currency code (defaults to setting).
	 * @return string Formatted currency string.
	 */
	public static function format_minor( int $minor_units, ?string $currency = null ): string {
		if ( null === $currency ) {
			$settings = get_option( 'wpcsr_settings', array() );
			$currency = ! empty( $settings['pricing']['currency'] ) ? (string) $settings['pricing']['currency'] : 'USD';
		}

		$decimals = self::get_currency_decimals( $currency );
		$decimal_amount = self::minor_to_decimal( $minor_units, $currency );
		$symbol = self::get_currency_symbol( $currency );

		$formatted_number = number_format( $decimal_amount, $decimals );
		return $symbol . $formatted_number;
	}
}
