<?php
/**
 * Tools, Diagnostics, and System Status Screen.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Database\Migrator;
use WPCalibrate\StayReserve\Database\Schema;
use WPCalibrate\StayReserve\Logging\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class ToolsScreen
 */
class ToolsScreen {

	private Migrator $migrator;
	private Logger $logger;
	private BookingRepository $bookings;

	/**
	 * Constructor.
	 */
	public function __construct( Migrator $migrator, Logger $logger, BookingRepository $bookings ) {
		$this->migrator = $migrator;
		$this->logger   = $logger;
		$this->bookings = $bookings;
	}

	/**
	 * Render tools screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$action = isset( $_GET['tool_action'] ) ? sanitize_key( $_GET['tool_action'] ) : '';

		// Handle Clear Logs
		if ( 'clear_logs' === $action ) {
			check_admin_referer( 'wpcsr_clear_logs' );
			$this->logger->clear();
			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=tools&cleared=1' ) );
			exit;
		}

		// Handle CSV Export
		if ( 'export_csv' === $action ) {
			check_admin_referer( 'wpcsr_export_csv' );
			$this->handle_csv_export();
			exit;
		}

		global $wp_version, $wpdb;

		// Table checks
		$tables = array(
			'room_units'      => Schema::get_table_name( 'room_units' ),
			'bookings'        => Schema::get_table_name( 'bookings' ),
			'booking_items'   => Schema::get_table_name( 'booking_items' ),
			'inventory_holds' => Schema::get_table_name( 'inventory_holds' ),
			'blocks'          => Schema::get_table_name( 'blocks' ),
			'rate_rules'      => Schema::get_table_name( 'rate_rules' ),
			'extras'          => Schema::get_table_name( 'extras' ),
			'booking_extras'  => Schema::get_table_name( 'booking_extras' ),
			'events'          => Schema::get_table_name( 'events' ),
		);

		$table_status = array();
		foreach ( $tables as $key => $table_name ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			$table_status[ $key ] = ( $exists === $table_name );
		}

		$wc_active = class_exists( 'WooCommerce' );
		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A';
		$hpos_active = false;
		if ( $wc_active && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$hpos_active = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		$elementor_active = did_action( 'elementor/loaded' ) || did_action( 'elementor_loaded' );

		// Compile system report
		$system_report = array(
			'Plugin Version'        => WPCSR_VERSION,
			'Database Schema Ver'   => get_option( 'wpcsr_db_version', '1.0.0' ),
			'WordPress Version'     => $wp_version ?? 'unknown',
			'PHP Version'           => PHP_VERSION,
			'Site Timezone'         => wp_timezone_string(),
			'WooCommerce Active'    => $wc_active ? 'Yes (' . $wc_version . ')' : 'No',
			'WooCommerce HPOS'      => $hpos_active ? 'Enabled' : 'Disabled / N/A',
			'Elementor Active'      => $elementor_active ? 'Yes' : 'No',
			'Cron Hold Cleanup'     => wp_next_scheduled( 'wpcsr_clean_expired_holds' ) ? 'Scheduled' : 'Pending',
		);

		$logs = $this->logger->get_entries( 50 );
		?>
		<div style="max-width: 1000px;">
			<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:24px;">
				<!-- System Diagnostic Status -->
				<div class="postbox" style="padding:16px;">
					<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'System Diagnostics', 'wpcalibrate-stayreserve' ); ?></h3>
					<table class="widefat striped" style="border:none;">
						<tbody>
							<?php foreach ( $system_report as $k => $v ) : ?>
								<tr>
									<td style="font-weight:600; width:45%;"><?php echo esc_html( $k ); ?></td>
									<td><?php echo esc_html( $v ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Database Tables Status & Tools -->
				<div class="postbox" style="padding:16px;">
					<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Database Tables & Operations', 'wpcalibrate-stayreserve' ); ?></h3>
					<ul style="margin-bottom:16px;">
						<?php foreach ( $table_status as $tbl_key => $is_ok ) : ?>
							<li style="margin-bottom:4px;">
								<span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:<?php echo $is_ok ? '#2e7d32' : '#b32d2e'; ?>; vertical-align:middle; margin-right:6px;"></span>
								<code><?php echo esc_html( $tbl_key ); ?></code>:
								<span style="color:<?php echo $is_ok ? '#2e7d32' : '#b32d2e'; ?>; font-weight:600;">
									<?php echo $is_ok ? esc_html__( 'Active', 'wpcalibrate-stayreserve' ) : esc_html__( 'Missing', 'wpcalibrate-stayreserve' ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>

					<div style="border-top:1px solid #eee; padding-top:12px;">
						<p><strong><?php esc_html_e( 'Export Reservations to CSV:', 'wpcalibrate-stayreserve' ); ?></strong></p>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=tools&tool_action=export_csv' ), 'wpcsr_export_csv' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Download CSV Export', 'wpcalibrate-stayreserve' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- System Report Box -->
			<div class="postbox" style="padding:16px; margin-bottom:24px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Copyable System Report', 'wpcalibrate-stayreserve' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Copy this report to share with WPCalibrate support when requesting technical assistance. Secrets and sensitive customer data are automatically excluded.', 'wpcalibrate-stayreserve' ); ?></p>
				<textarea readonly rows="8" class="large-text code" style="background:#f6f7f7;" onclick="this.select();"><?php
					foreach ( $system_report as $key => $val ) {
						echo esc_textarea( $key . ': ' . $val . "\n" );
					}
					echo esc_textarea( "Database Tables:\n" );
					foreach ( $table_status as $key => $status ) {
						echo esc_textarea( "  - {$key}: " . ( $status ? 'OK' : 'MISSING' ) . "\n" );
					}
				?></textarea>
			</div>

			<!-- Recent Activity Logs -->
			<div class="postbox" style="padding:16px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
					<h3 style="margin:0;"><?php esc_html_e( 'Recent Operational Logs', 'wpcalibrate-stayreserve' ); ?></h3>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=tools&tool_action=clear_logs' ), 'wpcsr_clear_logs' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Clear stored logs?', 'wpcalibrate-stayreserve' ); ?>');">
						<?php esc_html_e( 'Clear Logs', 'wpcalibrate-stayreserve' ); ?>
					</a>
				</div>

				<?php if ( empty( $logs ) ) : ?>
					<p style="color:#646970;"><?php esc_html_e( 'No log entries recorded yet.', 'wpcalibrate-stayreserve' ); ?></p>
				<?php else : ?>
					<table class="widefat striped fixed" style="font-size:12px;">
						<thead>
							<tr>
								<th style="width:140px;"><?php esc_html_e( 'Time', 'wpcalibrate-stayreserve' ); ?></th>
								<th style="width:80px;"><?php esc_html_e( 'Level', 'wpcalibrate-stayreserve' ); ?></th>
								<th><?php esc_html_e( 'Message', 'wpcalibrate-stayreserve' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $l ) : ?>
								<tr>
									<td><?php echo esc_html( $l['timestamp'] ?? '' ); ?></td>
									<td><strong><?php echo esc_html( strtoupper( $l['level'] ?? '' ) ); ?></strong></td>
									<td><?php echo esc_html( $l['message'] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle CSV export safely protecting against spreadsheet formula injection.
	 *
	 * @return void
	 */
	private function handle_csv_export(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_VIEW_BOOKINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$filename = 'stayreserve-bookings-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		// CSV Headers
		fputcsv(
			$output,
			array(
				'Reference',
				'Status',
				'Payment Status',
				'Check-in',
				'Check-out',
				'Adults',
				'Children',
				'Total',
				'Currency',
				'Guest First Name',
				'Guest Last Name',
				'Guest Email',
				'Guest Phone',
				'Created At',
			)
		);

		$bookings = $this->bookings->query( array( 'per_page' => 1000 ) );

		foreach ( $bookings as $b ) {
			fputcsv(
				$output,
				array(
					$this->escape_csv_formula( $b->public_reference ),
					$b->status,
					$b->payment_status,
					$b->check_in,
					$b->check_out,
					$b->adults,
					$b->children,
					\WPCalibrate\StayReserve\Utilities\MoneyUtil::minor_to_decimal( $b->grand_total, $b->currency ),
					$b->currency,
					$this->escape_csv_formula( $b->customer_first_name ),
					$this->escape_csv_formula( $b->customer_last_name ),
					$this->escape_csv_formula( $b->customer_email ),
					$this->escape_csv_formula( $b->customer_phone ),
					$b->created_at,
				)
			);
		}

		fclose( $output );
	}

	/**
	 * Escape spreadsheet formula injection characters (=, +, -, @).
	 *
	 * @param string $value Cell string value.
	 * @return string Escaped string.
	 */
	private function escape_csv_formula( string $value ): string {
		$first = substr( $value, 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
