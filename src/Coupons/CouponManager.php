<?php
/**
 * Coupon Manager for promo codes and discounts.
 *
 * @package WPCalibrate\StayReserve\Coupons
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Coupons;

use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CouponManager
 */
class CouponManager {

	public const OPTION_NAME     = 'wpcsr_coupons';
	public const TYPE_FIXED      = 'fixed';
	public const TYPE_PERCENTAGE = 'percentage';

	/**
	 * Get all configured coupons.
	 *
	 * @return array<string, array<string, mixed>> Keyed by uppercase code.
	 */
	public function get_all(): array {
		$coupons = get_option( self::OPTION_NAME, array() );
		return is_array( $coupons ) ? $coupons : array();
	}

	/**
	 * Save or update a coupon.
	 *
	 * @param array $data Coupon data.
	 * @return bool
	 */
	public function save( array $data ): bool {
		$code = strtoupper( trim( (string) ( $data['code'] ?? '' ) ) );
		if ( empty( $code ) ) {
			return false;
		}

		$all = $this->get_all();
		$all[ $code ] = array(
			'code'                  => $code,
			'type'                  => sanitize_text_field( $data['type'] ?? self::TYPE_PERCENTAGE ),
			'amount'                => (int) ( $data['amount'] ?? 0 ), // minor units for fixed, or whole percent (e.g. 10 for 10%)
			'min_amount'            => (int) ( $data['min_amount'] ?? 0 ), // minor units
			'min_nights'            => (int) ( $data['min_nights'] ?? 0 ),
			'start_date'            => ! empty( $data['start_date'] ) ? DateTimeUtil::format_date( $data['start_date'] ) : null,
			'expiry_date'           => ! empty( $data['expiry_date'] ) ? DateTimeUtil::format_date( $data['expiry_date'] ) : null,
			'usage_limit'           => ! empty( $data['usage_limit'] ) ? (int) $data['usage_limit'] : 0,
			'usage_count'           => (int) ( $all[ $code ]['usage_count'] ?? 0 ),
			'applicable_room_types' => ! empty( $data['applicable_room_types'] ) ? (array) $data['applicable_room_types'] : array(),
			'enabled'               => ! empty( $data['enabled'] ),
		);

		return update_option( self::OPTION_NAME, $all, false );
	}

	/**
	 * Delete a coupon by code.
	 *
	 * @param string $code Promo code.
	 * @return bool
	 */
	public function delete( string $code ): bool {
		$code = strtoupper( trim( $code ) );
		$all  = $this->get_all();
		if ( ! isset( $all[ $code ] ) ) {
			return false;
		}

		unset( $all[ $code ] );
		return update_option( self::OPTION_NAME, $all, false );
	}

	/**
	 * Get coupon details by code.
	 *
	 * @param string $code Promo code.
	 * @return array<string, mixed>|null
	 */
	public function get( string $code ): ?array {
		$code = strtoupper( trim( $code ) );
		$all  = $this->get_all();
		return $all[ $code ] ?? null;
	}

	/**
	 * Validate coupon and calculate discount amount in minor units.
	 *
	 * @param string $code Promo code.
	 * @param int    $subtotal Accommodation subtotal in minor units.
	 * @param int    $nights Stay nights.
	 * @param int    $room_type_id Room type ID.
	 * @return array{
	 *     valid: bool,
	 *     discount: int,
	 *     message: string,
	 *     coupon?: array<string, mixed>
	 * }
	 */
	public function validate_and_calculate( string $code, int $subtotal, int $nights, int $room_type_id ): array {
		$coupon = $this->get( $code );

		if ( ! $coupon || empty( $coupon['enabled'] ) ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'Invalid or inactive coupon code.', 'wpcalibrate-stayreserve' ),
			);
		}

		$today = date( 'Y-m-d' );

		// Date validity
		if ( ! empty( $coupon['start_date'] ) && $today < $coupon['start_date'] ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'This coupon is not active yet.', 'wpcalibrate-stayreserve' ),
			);
		}

		if ( ! empty( $coupon['expiry_date'] ) && $today > $coupon['expiry_date'] ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'This coupon has expired.', 'wpcalibrate-stayreserve' ),
			);
		}

		// Usage limits
		if ( ! empty( $coupon['usage_limit'] ) && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit'] ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'This coupon has reached its maximum usage limit.', 'wpcalibrate-stayreserve' ),
			);
		}

		// Minimum booking amount
		if ( ! empty( $coupon['min_amount'] ) && $subtotal < (int) $coupon['min_amount'] ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'Booking total does not meet the minimum amount for this coupon.', 'wpcalibrate-stayreserve' ),
			);
		}

		// Minimum nights
		if ( ! empty( $coupon['min_nights'] ) && $nights < (int) $coupon['min_nights'] ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => sprintf(
					/* translators: %d: Minimum nights */
					__( 'This coupon requires a minimum stay of %d nights.', 'wpcalibrate-stayreserve' ),
					(int) $coupon['min_nights']
				),
			);
		}

		// Applicable room types
		if ( ! empty( $coupon['applicable_room_types'] ) && ! in_array( $room_type_id, $coupon['applicable_room_types'], true ) ) {
			return array(
				'valid'    => false,
				'discount' => 0,
				'message'  => __( 'This coupon is not valid for the selected room type.', 'wpcalibrate-stayreserve' ),
			);
		}

		// Calculate discount amount in minor units
		$discount = 0;
		if ( self::TYPE_PERCENTAGE === $coupon['type'] ) {
			$percent  = (float) $coupon['amount'];
			$discount = (int) round( $subtotal * ( $percent / 100.0 ) );
		} else {
			$discount = min( (int) $coupon['amount'], $subtotal );
		}

		// Cap discount so it cannot exceed subtotal
		$discount = min( $discount, $subtotal );

		return array(
			'valid'    => true,
			'discount' => max( 0, $discount ),
			'message'  => __( 'Coupon applied successfully.', 'wpcalibrate-stayreserve' ),
			'coupon'   => $coupon,
		);
	}

	/**
	 * Increment coupon usage counter.
	 *
	 * @param string $code Promo code.
	 * @return void
	 */
	public function record_usage( string $code ): void {
		$code = strtoupper( trim( $code ) );
		$all  = $this->get_all();
		if ( isset( $all[ $code ] ) ) {
			$all[ $code ]['usage_count'] = ( (int) ( $all[ $code ]['usage_count'] ?? 0 ) ) + 1;
			update_option( self::OPTION_NAME, $all, false );
		}
	}
}
