<?php
/**
 * Booking Domain Entity for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Booking
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Booking;

use WPCalibrate\StayReserve\Utilities\DateTimeUtil;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingModel
 */
class BookingModel {

	public ?int $id = null;
	public string $public_reference = '';
	public ?int $user_id = null;
	public string $status = BookingStatus::PENDING;
	public string $payment_status = PaymentStatus::UNPAID;
	public string $check_in = '';
	public string $check_out = '';
	public int $adults = 1;
	public int $children = 0;
	public string $currency = 'USD';
	public int $accommodation_subtotal = 0;
	public int $extras_total = 0;
	public int $discount_total = 0;
	public int $fee_total = 0;
	public int $tax_total = 0;
	public int $grand_total = 0;
	public string $customer_first_name = '';
	public string $customer_last_name = '';
	public string $customer_email = '';
	public string $customer_phone = '';
	public ?string $customer_address = null;
	public ?string $customer_company = null;
	public ?string $customer_note = null;
	public ?string $admin_notes = null;
	public ?int $wc_order_id = null;
	public string $source = 'website';
	public ?string $management_token_hash = null;
	public ?string $management_token_expires_at = null;
	public string $created_at = '';
	public string $updated_at = '';

	/**
	 * Line items (reserved units).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $items = array();

	/**
	 * Booking extras.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $extras = array();

	/**
	 * Hydrate model from database array.
	 *
	 * @param array<string, mixed> $data DB row.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$model = new self();
		$model->id                         = isset( $data['id'] ) ? (int) $data['id'] : null;
		$model->public_reference           = (string) ( $data['public_reference'] ?? '' );
		$model->user_id                    = ! empty( $data['user_id'] ) ? (int) $data['user_id'] : null;
		$model->status                     = (string) ( $data['status'] ?? BookingStatus::PENDING );
		$model->payment_status             = (string) ( $data['payment_status'] ?? PaymentStatus::UNPAID );
		$model->check_in                   = (string) ( $data['check_in'] ?? '' );
		$model->check_out                  = (string) ( $data['check_out'] ?? '' );
		$model->adults                     = (int) ( $data['adults'] ?? 1 );
		$model->children                   = (int) ( $data['children'] ?? 0 );
		$model->currency                   = (string) ( $data['currency'] ?? 'USD' );
		$model->accommodation_subtotal     = (int) ( $data['accommodation_subtotal'] ?? 0 );
		$model->extras_total               = (int) ( $data['extras_total'] ?? 0 );
		$model->discount_total             = (int) ( $data['discount_total'] ?? 0 );
		$model->fee_total                  = (int) ( $data['fee_total'] ?? 0 );
		$model->tax_total                  = (int) ( $data['tax_total'] ?? 0 );
		$model->grand_total                = (int) ( $data['grand_total'] ?? 0 );
		$model->customer_first_name        = (string) ( $data['customer_first_name'] ?? '' );
		$model->customer_last_name         = (string) ( $data['customer_last_name'] ?? '' );
		$model->customer_email             = (string) ( $data['customer_email'] ?? '' );
		$model->customer_phone             = (string) ( $data['customer_phone'] ?? '' );
		$model->customer_address           = isset( $data['customer_address'] ) ? (string) $data['customer_address'] : null;
		$model->customer_company           = isset( $data['customer_company'] ) ? (string) $data['customer_company'] : null;
		$model->customer_note              = isset( $data['customer_note'] ) ? (string) $data['customer_note'] : null;
		$model->admin_notes                = isset( $data['admin_notes'] ) ? (string) $data['admin_notes'] : null;
		$model->wc_order_id                = ! empty( $data['wc_order_id'] ) ? (int) $data['wc_order_id'] : null;
		$model->source                     = (string) ( $data['source'] ?? 'website' );
		$model->management_token_hash       = isset( $data['management_token_hash'] ) ? (string) $data['management_token_hash'] : null;
		$model->management_token_expires_at= isset( $data['management_token_expires_at'] ) ? (string) $data['management_token_expires_at'] : null;
		$model->created_at                 = (string) ( $data['created_at'] ?? '' );
		$model->updated_at                 = (string) ( $data['updated_at'] ?? '' );

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$model->items = $data['items'];
		}
		if ( isset( $data['extras'] ) && is_array( $data['extras'] ) ) {
			$model->extras = $data['extras'];
		}

		return $model;
	}

	/**
	 * Convert model to array for database insertion / update.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'public_reference'            => $this->public_reference,
			'user_id'                     => $this->user_id,
			'status'                      => $this->status,
			'payment_status'              => $this->payment_status,
			'check_in'                    => $this->check_in,
			'check_out'                   => $this->check_out,
			'adults'                      => $this->adults,
			'children'                    => $this->children,
			'currency'                    => $this->currency,
			'accommodation_subtotal'      => $this->accommodation_subtotal,
			'extras_total'                => $this->extras_total,
			'discount_total'              => $this->discount_total,
			'fee_total'                   => $this->fee_total,
			'tax_total'                   => $this->tax_total,
			'grand_total'                 => $this->grand_total,
			'customer_first_name'         => $this->customer_first_name,
			'customer_last_name'          => $this->customer_last_name,
			'customer_email'              => $this->customer_email,
			'customer_phone'              => $this->customer_phone,
			'customer_address'            => $this->customer_address,
			'customer_company'            => $this->customer_company,
			'customer_note'               => $this->customer_note,
			'admin_notes'                 => $this->admin_notes,
			'wc_order_id'                 => $this->wc_order_id,
			'source'                      => $this->source,
			'management_token_hash'        => $this->management_token_hash,
			'management_token_expires_at' => $this->management_token_expires_at,
		);
	}

	/**
	 * Get customer full name.
	 *
	 * @return string
	 */
	public function get_customer_name(): string {
		return trim( $this->customer_first_name . ' ' . $this->customer_last_name );
	}

	/**
	 * Get formatted grand total.
	 *
	 * @return string
	 */
	public function get_formatted_total(): string {
		return MoneyUtil::format_minor( $this->grand_total, $this->currency );
	}

	/**
	 * Calculate nights count.
	 *
	 * @return int
	 */
	public function get_nights(): int {
		return DateTimeUtil::calculate_nights( $this->check_in, $this->check_out );
	}
}
