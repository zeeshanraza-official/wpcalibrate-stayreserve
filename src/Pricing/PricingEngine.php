<?php
/**
 * Authoritative Server-Side Pricing Engine for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Pricing
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Pricing;

use WPCalibrate\StayReserve\Coupons\CouponManager;
use WPCalibrate\StayReserve\Extras\ExtraRepository;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class PricingEngine
 */
class PricingEngine {

	/**
	 * Rate rule repository.
	 *
	 * @var RateRuleRepository
	 */
	private RateRuleRepository $rate_rules;

	/**
	 * Constructor.
	 *
	 * @param RateRuleRepository $rate_rules Rate rules repository.
	 */
	public function __construct( RateRuleRepository $rate_rules ) {
		$this->rate_rules = $rate_rules;
	}

	/**
	 * Calculate authoritative stay price quote.
	 *
	 * Deterministic rule priority order:
	 * 1. Explicit date override
	 * 2. Applicable seasonal/date-range rate
	 * 3. Day-of-week / weekend adjustment
	 * 4. Base nightly rate
	 * 5. Extras / add-ons
	 * 6. Discounts / coupons
	 * 7. Configured fees
	 * 8. Taxes
	 *
	 * @param int         $room_type_id Room Type CPT ID.
	 * @param string      $check_in Check-in date (YYYY-MM-DD).
	 * @param string      $check_out Check-out date (YYYY-MM-DD).
	 * @param int         $rooms Number of rooms.
	 * @param int         $adults Adults count.
	 * @param int         $children Children count.
	 * @param array       $selected_extras Selected extras array [ extra_id => quantity ].
	 * @param string|null $coupon_code Optional coupon code.
	 * @return array<string, mixed> Structured price breakdown with integer minor units.
	 */
	public function calculate_quote(
		int $room_type_id,
		string $check_in,
		string $check_out,
		int $rooms = 1,
		int $adults = 1,
		int $children = 0,
		array $selected_extras = array(),
		?string $coupon_code = null
	): array {
		$clean_in   = DateTimeUtil::format_date( $check_in );
		$clean_out  = DateTimeUtil::format_date( $check_out );
		$stay_nights = DateTimeUtil::get_stay_nights( $clean_in, $clean_out );
		$num_nights = count( $stay_nights );
		$rooms_qty  = max( 1, $rooms );
		$currency   = (string) SettingsManager::get( 'pricing', 'currency', 'USD' );

		// 4. Base rate
		$base_price_minor = (int) get_post_meta( $room_type_id, RoomTypePostType::META_BASE_PRICE, true );

		// Load active rules for this room type
		$rules = $this->rate_rules->get_active_rules_for_room_type( $room_type_id );

		$nightly_breakdown      = array();
		$single_room_accommodation_subtotal = 0;

		foreach ( $stay_nights as $night_date ) {
			$night_rate = $this->calculate_night_rate(
				$base_price_minor,
				$night_date,
				$num_nights,
				$rules
			);

			$nightly_breakdown[] = array(
				'date'          => $night_date,
				'base_rate'     => $base_price_minor,
				'rate'          => $night_rate['rate'],
				'rule_applied'  => $night_rate['rule_applied'],
			);

			$single_room_accommodation_subtotal += $night_rate['rate'];
		}

		// Total accommodation subtotal for all requested rooms
		$accommodation_subtotal = $single_room_accommodation_subtotal * $rooms_qty;

		// 5. Extras
		$extras_repo     = new ExtraRepository();
		$itemized_extras = array();
		$extras_total    = 0;
		$total_guests    = $adults + $children;

		foreach ( $selected_extras as $extra_id => $qty ) {
			$extra_id = (int) $extra_id;
			$quantity = (int) $qty;
			if ( $quantity <= 0 ) {
				continue;
			}

			$extra = $extras_repo->get( $extra_id );
			if ( ! $extra || empty( $extra['enabled'] ) ) {
				continue;
			}

			$extra_price = ExtraRepository::calculate_price( $extra, $quantity, $num_nights, $total_guests );
			$extras_total += $extra_price;

			$itemized_extras[] = array(
				'extra_id'     => $extra_id,
				'name'         => $extra['name'],
				'pricing_type' => $extra['pricing_type'],
				'quantity'     => $quantity,
				'unit_price'   => (int) $extra['price'],
				'total_price'  => $extra_price,
				'is_taxable'   => ! empty( $extra['is_taxable'] ),
			);
		}

		// 6. Discounts & coupons
		$discount_total = 0;
		$applied_coupon = null;
		if ( ! empty( $coupon_code ) ) {
			$coupon_manager = new CouponManager();
			$coupon_result  = $coupon_manager->validate_and_calculate(
				$coupon_code,
				$accommodation_subtotal,
				$num_nights,
				$room_type_id
			);

			if ( $coupon_result['valid'] ) {
				$discount_total = (int) $coupon_result['discount'];
				$applied_coupon = array(
					'code'     => strtoupper( trim( $coupon_code ) ),
					'discount' => $discount_total,
					'message'  => $coupon_result['message'],
				);
			}
		}

		// 7. Fees (fixed or percentage)
		$fee_total = 0;

		// 8. Taxes
		$tax_total  = 0;
		$is_tax_enabled = (bool) SettingsManager::get( 'pricing', 'enable_taxes', false );
		$is_room_taxable = ( '0' !== get_post_meta( $room_type_id, RoomTypePostType::META_IS_TAXABLE, true ) );

		if ( $is_tax_enabled && $is_room_taxable ) {
			$tax_rate_percent = (float) SettingsManager::get( 'pricing', 'tax_rate', 0.0 );
			if ( $tax_rate_percent > 0 ) {
				$taxable_base = max( 0, $accommodation_subtotal - $discount_total );
				// Add taxable extras
				foreach ( $itemized_extras as $extra_item ) {
					if ( $extra_item['is_taxable'] ) {
						$taxable_base += $extra_item['total_price'];
					}
				}
				$tax_total = (int) round( $taxable_base * ( $tax_rate_percent / 100.0 ) );
			}
		}

		// Grand Total
		$grand_total = max( 0, ( $accommodation_subtotal + $extras_total + $fee_total + $tax_total ) - $discount_total );

		return array(
			'room_type_id'           => $room_type_id,
			'rooms'                  => $rooms_qty,
			'nights'                 => $num_nights,
			'adults'                 => $adults,
			'children'               => $children,
			'currency'               => $currency,
			'nightly_breakdown'      => $nightly_breakdown,
			'accommodation_subtotal' => $accommodation_subtotal,
			'extras'                 => $itemized_extras,
			'extras_total'           => $extras_total,
			'discount_total'         => $discount_total,
			'coupon'                 => $applied_coupon,
			'fee_total'              => $fee_total,
			'tax_total'              => $tax_total,
			'grand_total'            => $grand_total,
		);
	}

	/**
	 * Calculate single night rate evaluating rules in strict priority.
	 *
	 * Priority:
	 * 1. Explicit date override
	 * 2. Applicable seasonal/date-range rate
	 * 3. Day-of-week / weekend adjustment
	 * 4. Base rate fallback
	 *
	 * @param int    $base_price Base nightly price.
	 * @param string $night_date Specific night (YYYY-MM-DD).
	 * @param int    $stay_nights Total stay nights.
	 * @param array  $rules Active rate rules for this room type.
	 * @return array{ rate: int, rule_applied: string }
	 */
	private function calculate_night_rate( int $base_price, string $night_date, int $stay_nights, array $rules ): array {
		$day_of_week = (int) ( new \DateTime( $night_date ) )->format( 'w' ); // 0 = Sun, 6 = Sat

		// 1. Explicit date override
		foreach ( $rules as $rule ) {
			if ( RateRuleRepository::TYPE_DATE_OVERRIDE === $rule['rule_type'] ) {
				if ( $rule['start_date'] <= $night_date && $rule['end_date'] >= $night_date ) {
					return array(
						'rate'         => $this->apply_adjustment( $base_price, $rule ),
						'rule_applied' => $rule['name'],
					);
				}
			}
		}

		// 2. Applicable seasonal/date-range rate
		foreach ( $rules as $rule ) {
			if ( RateRuleRepository::TYPE_SEASON === $rule['rule_type'] ) {
				if ( $rule['start_date'] <= $night_date && $rule['end_date'] >= $night_date ) {
					// Check stay duration condition if rule specifies min/max stay
					if ( ! empty( $rule['minimum_stay'] ) && $stay_nights < (int) $rule['minimum_stay'] ) {
						continue;
					}
					if ( ! empty( $rule['maximum_stay'] ) && $stay_nights > (int) $rule['maximum_stay'] ) {
						continue;
					}

					return array(
						'rate'         => $this->apply_adjustment( $base_price, $rule ),
						'rule_applied' => $rule['name'],
					);
				}
			}
		}

		// 3. Day-of-week / weekend adjustment
		foreach ( $rules as $rule ) {
			if ( RateRuleRepository::TYPE_DAY_OF_WEEK === $rule['rule_type'] || RateRuleRepository::TYPE_WEEKEND === $rule['rule_type'] ) {
				$weekdays = array();
				if ( RateRuleRepository::TYPE_WEEKEND === $rule['rule_type'] ) {
					$weekdays = array( 5, 6 ); // Friday, Saturday
				} elseif ( ! empty( $rule['weekdays'] ) ) {
					$weekdays = array_map( 'intval', explode( ',', (string) $rule['weekdays'] ) );
				}

				if ( in_array( $day_of_week, $weekdays, true ) ) {
					return array(
						'rate'         => $this->apply_adjustment( $base_price, $rule ),
						'rule_applied' => $rule['name'],
					);
				}
			}
		}

		// 4. Base rate fallback
		return array(
			'rate'         => $base_price,
			'rule_applied' => __( 'Base Rate', 'wpcalibrate-stayreserve' ),
		);
	}

	/**
	 * Apply adjustment to base price.
	 *
	 * @param int   $base_price Base price.
	 * @param array $rule Rule record.
	 * @return int Adjusted price in minor units.
	 */
	private function apply_adjustment( int $base_price, array $rule ): int {
		$adj_type = $rule['adjustment_type'];
		$amount   = (int) $rule['amount'];

		return match ( $adj_type ) {
			RateRuleRepository::ADJ_OVERRIDE_PRICE => max( 0, $amount ),
			RateRuleRepository::ADJ_FIXED          => max( 0, $base_price + $amount ),
			RateRuleRepository::ADJ_PERCENTAGE     => max( 0, (int) round( $base_price * ( 1.0 + ( $amount / 10000.0 ) ) ) ), // basis points or percent
			default                                => $base_price,
		};
	}
}
