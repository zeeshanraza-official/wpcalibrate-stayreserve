<?php
/**
 * Settings Page Controller for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class SettingsPage
 */
class SettingsPage {

	/**
	 * Render settings form.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		if ( ! empty( $_POST ) ) {
			check_admin_referer( 'wpcsr_save_settings', 'wpcsr_settings_nonce' );

			$post = wp_unslash( $_POST );
			$updated_settings = array(
				'general'      => array(
					'currency'              => sanitize_text_field( $post['currency'] ?? 'USD' ),
					'default_checkin_time'  => sanitize_text_field( $post['default_checkin_time'] ?? '14:00' ),
					'default_checkout_time' => sanitize_text_field( $post['default_checkout_time'] ?? '11:00' ),
					'min_advance_days'      => (int) ( $post['min_advance_days'] ?? 0 ),
					'max_advance_days'      => (int) ( $post['max_advance_days'] ?? 365 ),
					'default_min_stay'      => max( 1, (int) ( $post['default_min_stay'] ?? 1 ) ),
					'default_max_stay'      => max( 1, (int) ( $post['default_max_stay'] ?? 30 ) ),
					'booking_mode'          => sanitize_text_field( $post['booking_mode'] ?? 'instant' ),
					'offline_instructions'  => sanitize_textarea_field( $post['offline_instructions'] ?? '' ),
				),
				'inventory'    => array(
					'hold_duration_minutes' => max( 5, (int) ( $post['hold_duration_minutes'] ?? 15 ) ),
					'enable_holds'          => ! empty( $post['enable_holds'] ),
				),
				'pricing'      => array(
					'currency'       => sanitize_text_field( $post['currency'] ?? 'USD' ),
					'enable_taxes'   => ! empty( $post['enable_taxes'] ),
					'tax_rate'       => (float) ( $post['tax_rate'] ?? 0.0 ),
					'tax_label'      => sanitize_text_field( $post['tax_label'] ?? 'Tax' ),
					'enable_coupons' => ! empty( $post['enable_coupons'] ),
				),
				'guest'        => array(
					'allow_guest_checkout' => ! empty( $post['allow_guest_checkout'] ),
					'require_phone'        => ! empty( $post['require_phone'] ),
					'require_address'      => ! empty( $post['require_address'] ),
					'require_company'      => ! empty( $post['require_company'] ),
				),
				'emails'       => array(
					'sender_name'     => sanitize_text_field( $post['sender_name'] ?? get_bloginfo( 'name' ) ),
					'sender_email'    => sanitize_email( $post['sender_email'] ?? get_bloginfo( 'admin_email' ) ),
					'admin_recipient' => sanitize_email( $post['admin_recipient'] ?? get_bloginfo( 'admin_email' ) ),
					'notify_admin_new_booking'     => ! empty( $post['notify_admin_new_booking'] ),
					'notify_customer_booking_rcvd' => ! empty( $post['notify_customer_booking_rcvd'] ),
					'notify_customer_confirmed'    => ! empty( $post['notify_customer_confirmed'] ),
					'notify_customer_cancelled'    => ! empty( $post['notify_customer_cancelled'] ),
				),
				'pages'        => array(
					'search_page_id'    => (int) ( $post['search_page_id'] ?? 0 ),
					'booking_page_id'   => (int) ( $post['booking_page_id'] ?? 0 ),
					'manage_booking_id' => (int) ( $post['manage_booking_id'] ?? 0 ),
				),
				'integrations' => array(
					'enable_woocommerce' => ! empty( $post['enable_woocommerce'] ),
				),
				'advanced'     => array(
					'log_level'             => sanitize_text_field( $post['log_level'] ?? 'info' ),
					'uninstall_delete_data' => ! empty( $post['uninstall_delete_data'] ),
				),
			);

			SettingsManager::update( $updated_settings );
			update_option( 'wpcsr_uninstall_delete_data', ! empty( $post['uninstall_delete_data'] ) ? 'yes' : 'no' );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'wpcalibrate-stayreserve' ) . '</p></div>';
		}

		$settings = SettingsManager::get_all();
		$pages    = get_pages();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=settings' ) ); ?>" style="max-width: 900px;">
			<?php wp_nonce_field( 'wpcsr_save_settings', 'wpcsr_settings_nonce' ); ?>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'General Booking Settings', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><label for="currency"><?php esc_html_e( 'Currency', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<select name="currency" id="currency">
								<option value="USD" <?php selected( $settings['general']['currency'], 'USD' ); ?>>USD ($)</option>
								<option value="EUR" <?php selected( $settings['general']['currency'], 'EUR' ); ?>>EUR (€)</option>
								<option value="GBP" <?php selected( $settings['general']['currency'], 'GBP' ); ?>>GBP (£)</option>
								<option value="CAD" <?php selected( $settings['general']['currency'], 'CAD' ); ?>>CAD ($)</option>
								<option value="AUD" <?php selected( $settings['general']['currency'], 'AUD' ); ?>>AUD ($)</option>
								<option value="JPY" <?php selected( $settings['general']['currency'], 'JPY' ); ?>>JPY (¥)</option>
								<option value="CHF" <?php selected( $settings['general']['currency'], 'CHF' ); ?>>CHF</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="booking_mode"><?php esc_html_e( 'Booking Confirmation Mode', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<select name="booking_mode" id="booking_mode">
								<option value="instant" <?php selected( $settings['general']['booking_mode'], 'instant' ); ?>><?php esc_html_e( 'Instant Confirmation (Auto-confirmed)', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="manual_approval" <?php selected( $settings['general']['booking_mode'], 'manual_approval' ); ?>><?php esc_html_e( 'Manual Approval (Pending review by staff)', 'wpcalibrate-stayreserve' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="default_checkin_time"><?php esc_html_e( 'Standard Times', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<?php esc_html_e( 'Check-in:', 'wpcalibrate-stayreserve' ); ?>
							<input type="time" name="default_checkin_time" id="default_checkin_time" value="<?php echo esc_attr( $settings['general']['default_checkin_time'] ); ?>">
							&nbsp;&nbsp;&nbsp;&nbsp;
							<?php esc_html_e( 'Check-out:', 'wpcalibrate-stayreserve' ); ?>
							<input type="time" name="default_checkout_time" id="default_checkout_time" value="<?php echo esc_attr( $settings['general']['default_checkout_time'] ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="default_min_stay"><?php esc_html_e( 'Stay Length Limits', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<?php esc_html_e( 'Default Min Nights:', 'wpcalibrate-stayreserve' ); ?>
							<input type="number" name="default_min_stay" id="default_min_stay" min="1" value="<?php echo esc_attr( (string) $settings['general']['default_min_stay'] ); ?>" style="width:70px;">
							&nbsp;&nbsp;&nbsp;&nbsp;
							<?php esc_html_e( 'Default Max Nights:', 'wpcalibrate-stayreserve' ); ?>
							<input type="number" name="default_max_stay" id="default_max_stay" min="1" value="<?php echo esc_attr( (string) $settings['general']['default_max_stay'] ); ?>" style="width:70px;">
						</td>
					</tr>
					<tr>
						<th><label for="offline_instructions"><?php esc_html_e( 'Offline Payment Instructions', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<textarea name="offline_instructions" id="offline_instructions" rows="3" class="large-text"><?php echo esc_textarea( $settings['general']['offline_instructions'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Displayed on confirmation page and emails when booking without WooCommerce.', 'wpcalibrate-stayreserve' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Inventory & Checkout Holds', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Temporary Checkout Holds', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_holds" value="1" <?php checked( $settings['inventory']['enable_holds'] ); ?>>
								<?php esc_html_e( 'Hold room availability temporarily while guest is completing checkout', 'wpcalibrate-stayreserve' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="hold_duration_minutes"><?php esc_html_e( 'Hold Duration', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<input type="number" name="hold_duration_minutes" id="hold_duration_minutes" min="5" max="120" value="<?php echo esc_attr( (string) $settings['inventory']['hold_duration_minutes'] ); ?>" style="width:80px;">
							<?php esc_html_e( 'minutes (Default: 15 minutes)', 'wpcalibrate-stayreserve' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Taxes & Fees', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Taxes', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_taxes" value="1" <?php checked( $settings['pricing']['enable_taxes'] ); ?>>
								<?php esc_html_e( 'Calculate tax on room rates and taxable add-ons', 'wpcalibrate-stayreserve' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="tax_rate"><?php esc_html_e( 'Tax Rate (%)', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" name="tax_rate" id="tax_rate" value="<?php echo esc_attr( (string) $settings['pricing']['tax_rate'] ); ?>" style="width:100px;"> %
						</td>
					</tr>
					<tr>
						<th><label for="tax_label"><?php esc_html_e( 'Tax Label', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<input type="text" name="tax_label" id="tax_label" value="<?php echo esc_attr( $settings['pricing']['tax_label'] ); ?>" class="regular-text">
						</td>
					</tr>
				</table>
			</div>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Guest Details & Checkout Form', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Guest Checkout', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_guest_checkout" value="1" <?php checked( $settings['guest']['allow_guest_checkout'] ); ?>>
								<?php esc_html_e( 'Allow reservations without requiring a WordPress user account', 'wpcalibrate-stayreserve' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Required Fields', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<label><input type="checkbox" name="require_phone" value="1" <?php checked( $settings['guest']['require_phone'] ); ?>> <?php esc_html_e( 'Require Phone Number', 'wpcalibrate-stayreserve' ); ?></label><br>
							<label><input type="checkbox" name="require_address" value="1" <?php checked( $settings['guest']['require_address'] ); ?>> <?php esc_html_e( 'Require Billing / Physical Address', 'wpcalibrate-stayreserve' ); ?></label><br>
							<label><input type="checkbox" name="require_company" value="1" <?php checked( $settings['guest']['require_company'] ); ?>> <?php esc_html_e( 'Require Company Name', 'wpcalibrate-stayreserve' ); ?></label>
						</td>
					</tr>
				</table>
			</div>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'WooCommerce Integration', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'WooCommerce Checkout', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<?php $wc_installed = class_exists( 'WooCommerce' ); ?>
							<label>
								<input type="checkbox" name="enable_woocommerce" value="1" <?php checked( $settings['integrations']['enable_woocommerce'] ); ?> <?php disabled( ! $wc_installed ); ?>>
								<?php esc_html_e( 'Route reservation checkout and payments through WooCommerce', 'wpcalibrate-stayreserve' ); ?>
							</label>
							<?php if ( ! $wc_installed ) : ?>
								<p class="description" style="color:#b32d2e;">
									<?php esc_html_e( 'WooCommerce is not currently active. WPCalibrate StayReserve handles bookings and manual offline payments natively.', 'wpcalibrate-stayreserve' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="postbox" style="padding: 20px; margin-bottom: 20px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Data Retention & Uninstall', 'wpcalibrate-stayreserve' ); ?></h3>

				<table class="form-table">
					<tr>
						<th><label for="log_level"><?php esc_html_e( 'Logging Level', 'wpcalibrate-stayreserve' ); ?></label></th>
						<td>
							<select name="log_level" id="log_level">
								<option value="debug" <?php selected( $settings['advanced']['log_level'], 'debug' ); ?>><?php esc_html_e( 'Debug (Detailed troubleshooting)', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="info" <?php selected( $settings['advanced']['log_level'], 'info' ); ?>><?php esc_html_e( 'Info (Standard operational events)', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="warning" <?php selected( $settings['advanced']['log_level'], 'warning' ); ?>><?php esc_html_e( 'Warning (Operational warnings only)', 'wpcalibrate-stayreserve' ); ?></option>
								<option value="error" <?php selected( $settings['advanced']['log_level'], 'error' ); ?>><?php esc_html_e( 'Error (Errors only)', 'wpcalibrate-stayreserve' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uninstall Behaviour', 'wpcalibrate-stayreserve' ); ?></th>
						<td>
							<label style="color:#b32d2e;">
								<input type="checkbox" name="uninstall_delete_data" value="1" <?php checked( $settings['advanced']['uninstall_delete_data'] ); ?>>
								<strong><?php esc_html_e( 'Completely remove all custom booking tables, rate rules, and plugin options upon plugin deletion.', 'wpcalibrate-stayreserve' ); ?></strong>
							</label>
							<p class="description"><?php esc_html_e( 'Default is UNCHECKED (preserves customer booking records and inventory configuration on deletion).', 'wpcalibrate-stayreserve' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save All Settings', 'wpcalibrate-stayreserve' ); ?></button>
			</p>
		</form>
		<?php
	}
}
