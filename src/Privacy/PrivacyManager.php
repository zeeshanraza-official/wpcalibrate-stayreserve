<?php
/**
 * Privacy and GDPR Personal Data Exporter and Eraser for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Privacy
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Privacy;

use WPCalibrate\StayReserve\Booking\BookingRepository;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class PrivacyManager
 */
class PrivacyManager {

	private BookingRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param BookingRepository $repository Booking repository.
	 */
	public function __construct( BookingRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array $exporters Exporters list.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wpcalibrate-stayreserve'] = array(
			'exporter_friendly_name' => __( 'WPCalibrate StayReserve Accommodation Bookings', 'wpcalibrate-stayreserve' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array $erasers Erasers list.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wpcalibrate-stayreserve'] = array(
			'eraser_friendly_name' => __( 'WPCalibrate StayReserve Personal Data Eraser', 'wpcalibrate-stayreserve' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export customer personal booking data.
	 *
	 * @param string $email_address Customer email.
	 * @param int    $page Page number.
	 * @return array{ data: array, done: bool }
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$bookings = $this->repository->query(
			array(
				'customer_email' => $email_address,
				'page'           => $page,
				'per_page'       => 20,
			)
		);

		$export_items = array();

		foreach ( $bookings as $b ) {
			$export_items[] = array(
				'group_id'    => 'wpcsr_bookings',
				'group_label' => __( 'StayReserve Bookings', 'wpcalibrate-stayreserve' ),
				'item_id'     => "booking-{$b->id}",
				'data'        => array(
					array( 'name' => __( 'Reference', 'wpcalibrate-stayreserve' ), 'value' => $b->public_reference ),
					array( 'name' => __( 'Guest Name', 'wpcalibrate-stayreserve' ), 'value' => $b->get_customer_name() ),
					array( 'name' => __( 'Email', 'wpcalibrate-stayreserve' ), 'value' => $b->customer_email ),
					array( 'name' => __( 'Phone', 'wpcalibrate-stayreserve' ), 'value' => $b->customer_phone ),
					array( 'name' => __( 'Check-in', 'wpcalibrate-stayreserve' ), 'value' => $b->check_in ),
					array( 'name' => __( 'Check-out', 'wpcalibrate-stayreserve' ), 'value' => $b->check_out ),
					array( 'name' => __( 'Grand Total', 'wpcalibrate-stayreserve' ), 'value' => $b->get_formatted_total() ),
					array( 'name' => __( 'Status', 'wpcalibrate-stayreserve' ), 'value' => $b->status ),
					array( 'name' => __( 'Booked Date', 'wpcalibrate-stayreserve' ), 'value' => $b->created_at ),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $bookings ) < 20,
		);
	}

	/**
	 * Anonymize customer personal data while retaining accounting financial totals.
	 *
	 * @param string $email_address Customer email.
	 * @param int    $page Page number.
	 * @return array{ items_removed: int, items_retained: int, messages: array, done: bool }
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$bookings = $this->repository->query(
			array(
				'customer_email' => $email_address,
				'page'           => $page,
				'per_page'       => 20,
			)
		);

		$items_removed  = 0;
		$items_retained = 0;
		$messages       = array();

		foreach ( $bookings as $b ) {
			// Anonymize personal fields
			$anonymized = $this->repository->update(
				(int) $b->id,
				array(
					'customer_first_name'         => __( 'Anonymized', 'wpcalibrate-stayreserve' ),
					'customer_last_name'          => __( 'Guest', 'wpcalibrate-stayreserve' ),
					'customer_email'              => 'anonymized@example.invalid',
					'customer_phone'              => '',
					'customer_address'            => null,
					'customer_company'            => null,
					'customer_note'               => null,
					'management_token_hash'       => null,
					'management_token_expires_at' => null,
				)
			);

			if ( $anonymized ) {
				$items_removed++;
				$messages[] = sprintf(
					/* translators: %s: Booking Reference */
					__( 'Personal data for StayReserve booking %s has been anonymized. Financial transaction history retained for accounting.', 'wpcalibrate-stayreserve' ),
					$b->public_reference
				);
			} else {
				$items_retained++;
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => count( $bookings ) < 20,
		);
	}
}
