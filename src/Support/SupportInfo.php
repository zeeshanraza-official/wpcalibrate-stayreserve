<?php
/**
 * Official WPCalibrate Support & Licensing Screen.
 *
 * @package WPCalibrate\StayReserve\Support
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Support;

use WPCalibrate\StayReserve\Integrations\Licensing\LicenseService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class SupportInfo
 */
class SupportInfo {

	public const AUTHOR_EMAIL       = 'support@wpcalibrate.com';
	public const AUTHOR_WEBSITE     = 'https://wpcalibrate.com';
	public const AUTHOR_MARKETPLACE = 'https://marketplace.wpcalibrate.com/';
	public const AUTHOR_PHONE       = '+447474795976';

	/**
	 * Render support & license section in wp-admin.
	 *
	 * @return void
	 */
	public function render(): void {
		$license_service = new LicenseService();

		// Handle license key update
		if ( isset( $_POST['wpcsr_license_save'] ) ) {
			check_admin_referer( 'wpcsr_save_license', 'wpcsr_license_nonce' );
			$raw_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
			$license_service->save_key( $raw_key );
			$res = $license_service->activate_license();
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
		}

		$masked_key = $license_service->get_masked_key();
		$lic_data   = $license_service->get_license_data();
		?>
		<div style="max-width:900px;">
			<!-- License Section -->
			<div class="postbox" style="padding:24px; margin-bottom:24px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px; font-size:18px;">
					<?php esc_html_e( 'License Management', 'wpcalibrate-stayreserve' ); ?>
				</h3>

				<div style="background:#fff8e5; border-left:4px solid #f0b849; padding:12px 16px; margin-bottom:20px; font-size:13px; line-height:1.5;">
					<strong><?php esc_html_e( 'Licensing Status:', 'wpcalibrate-stayreserve' ); ?></strong>
					<?php esc_html_e( 'Remote license provider endpoint is not connected. All core reservation features remain 100% active and unhindered on your site without simulated checks.', 'wpcalibrate-stayreserve' ); ?>
				</div>

				<form method="post">
					<?php wp_nonce_field( 'wpcsr_save_license', 'wpcsr_license_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th style="width:180px;"><label for="license_key"><?php esc_html_e( 'Product License Key', 'wpcalibrate-stayreserve' ); ?></label></th>
							<td>
								<input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( $masked_key ); ?>" placeholder="Enter your license key" class="regular-text" style="font-family:monospace;">
								<p class="description"><?php esc_html_e( 'Stored license keys are masked for security.', 'wpcalibrate-stayreserve' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Current Status', 'wpcalibrate-stayreserve' ); ?></th>
							<td>
								<span style="display:inline-block; padding:4px 10px; background:#f0f0f1; border-radius:4px; font-weight:600; color:#50575e;">
									<?php echo esc_html( ucfirst( $lic_data['status'] ?? 'Unconnected' ) ); ?>
								</span>
							</td>
						</tr>
					</table>

					<p class="submit" style="margin-bottom:0; padding-bottom:0;">
						<button type="submit" name="wpcsr_license_save" value="1" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'wpcalibrate-stayreserve' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Official Support Info -->
			<div class="postbox" style="padding:24px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px; font-size:18px;">
					<?php esc_html_e( 'WPCalibrate Support & Assistance', 'wpcalibrate-stayreserve' ); ?>
				</h3>

				<p style="font-size:14px; line-height:1.6; color:#4a5568;">
					<?php esc_html_e( 'Need assistance configuring your hotel rooms, seasonal rate adjustments, or WooCommerce checkout integration? Our dedicated engineering team is here to assist you.', 'wpcalibrate-stayreserve' ); ?>
				</p>

				<div class="wpcsr-support-channels" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-top:20px; box-sizing:border-box;">
					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; min-height:85px;">
						<div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
							<span class="dashicons dashicons-email" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom; margin-right:4px; color:#0284c7;"></span>
							<?php esc_html_e( 'Support Email', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<div style="margin-top:8px; font-size:14px; font-weight:600; word-break:break-all; overflow-wrap:anywhere;">
							<a href="mailto:<?php echo esc_attr( self::AUTHOR_EMAIL ); ?>" style="color:#0284c7; text-decoration:none;">
								<?php echo esc_html( self::AUTHOR_EMAIL ); ?>
							</a>
						</div>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; min-height:85px;">
						<div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
							<span class="dashicons dashicons-phone" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom; margin-right:4px; color:#16a34a;"></span>
							<?php esc_html_e( 'Phone / WhatsApp', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<div style="margin-top:8px; font-size:14px; font-weight:600; word-break:break-all; overflow-wrap:anywhere;">
							<a href="tel:<?php echo esc_attr( self::AUTHOR_PHONE ); ?>" style="color:#16a34a; text-decoration:none;">
								<?php echo esc_html( self::AUTHOR_PHONE ); ?>
							</a>
						</div>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; min-height:85px;">
						<div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
							<span class="dashicons dashicons-admin-site-alt3" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom; margin-right:4px; color:#2563eb;"></span>
							<?php esc_html_e( 'Official Website', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<div style="margin-top:8px; font-size:14px; font-weight:600; word-break:break-all; overflow-wrap:anywhere;">
							<a href="<?php echo esc_url( self::AUTHOR_WEBSITE ); ?>" target="_blank" rel="noopener noreferrer" style="color:#2563eb; text-decoration:none;">
								<?php echo esc_html( self::AUTHOR_WEBSITE ); ?>
							</a>
						</div>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; min-height:85px;">
						<div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
							<span class="dashicons dashicons-cart" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom; margin-right:4px; color:#7c3aed;"></span>
							<?php esc_html_e( 'Marketplace', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<div style="margin-top:8px; font-size:14px; font-weight:600; word-break:break-all; overflow-wrap:anywhere;">
							<a href="<?php echo esc_url( self::AUTHOR_MARKETPLACE ); ?>" target="_blank" rel="noopener noreferrer" style="color:#7c3aed; text-decoration:none;">
								<?php echo esc_html( self::AUTHOR_MARKETPLACE ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
