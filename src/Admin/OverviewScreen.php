<?php
/**
 * Overview & User Guide Screen for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class OverviewScreen
 */
class OverviewScreen {

	/**
	 * Render the Overview and How-to-Use Screen.
	 *
	 * @return void
	 */
	public function render(): void {
		$admin_url = admin_url( 'admin.php?page=wpcalibrate-stayreserve' );
		?>
		<div class="wpcsr-overview-screen" style="max-width: 1200px;">
			
			<!-- Hero Header -->
			<div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; border-radius: 12px; padding: 32px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
				<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:20px;">
					<div style="max-width:760px;">
						<div style="display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.1); padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; color:#38bdf8;">
							<span class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></span>
							<?php esc_html_e( 'Plugin Overview & User Guide', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<h2 style="color:#fff; font-size:26px; font-weight:700; margin:0 0 10px; line-height:1.2;">
							<?php esc_html_e( 'Direct Accommodation & Hotel Booking Engine', 'wpcalibrate-stayreserve' ); ?>
						</h2>
						<p style="color:#94a3b8; font-size:15px; line-height:1.6; margin:0;">
							<?php esc_html_e( 'WPCalibrate StayReserve empowers hotels, guest houses, resorts, B&Bs, and boutique apartments to accept commission-free direct bookings. The plugin owns complete inventory, real-time availability, server-side pricing, and stay rules, while offering optional WooCommerce checkout integration.', 'wpcalibrate-stayreserve' ); ?>
						</p>
					</div>
					<div style="display:flex; flex-direction:column; gap:8px;">
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'bookings', $admin_url ) ); ?>" class="button button-primary button-hero" style="background:#0284c7; border-color:#0284c7; text-align:center;">
							<?php esc_html_e( 'View Bookings', 'wpcalibrate-stayreserve' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpcsr_room_type' ) ); ?>" class="button button-secondary" style="text-align:center; background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.3);">
							+ <?php esc_html_e( 'Add Room Type', 'wpcalibrate-stayreserve' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Core Architecture & Method -->
			<div class="postbox" style="padding:24px; margin-bottom:24px; border-radius:8px;">
				<h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:12px; font-size:18px; color:#0f172a;">
					<span class="dashicons dashicons-admin-settings" style="color:#0284c7; vertical-align:text-bottom; margin-right:6px;"></span>
					<?php esc_html_e( 'Core Booking Method & Architectural Foundation', 'wpcalibrate-stayreserve' ); ?>
				</h3>
				<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; margin-top:16px;">
					
					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px;">
						<div style="font-weight:700; color:#1e293b; font-size:14px; margin-bottom:6px;">
							<?php esc_html_e( '1. Room Types vs. Physical Units', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<p style="font-size:13px; color:#475569; line-height:1.5; margin:0;">
							<?php esc_html_e( 'Sellable accommodation categories (e.g., Deluxe King, Family Suite) are created as Room Types. Each category contains physical units (e.g., Room 101, Villa A) that represent independent reservable inventory.', 'wpcalibrate-stayreserve' ); ?>
						</p>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px;">
						<div style="font-weight:700; color:#1e293b; font-size:14px; margin-bottom:6px;">
							<?php esc_html_e( '2. Half-Open Stay Semantics [Check-in, Check-out)', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<p style="font-size:13px; color:#475569; line-height:1.5; margin:0;">
							<?php esc_html_e( 'Calculates occupied nights accurately. Check-out day is freed immediately for incoming guest check-ins, eliminating false calendar blocks and turnover deadlocks.', 'wpcalibrate-stayreserve' ); ?>
						</p>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px;">
						<div style="font-weight:700; color:#1e293b; font-size:14px; margin-bottom:6px;">
							<?php esc_html_e( '3. Concurrency & Anti-Overbooking Lock', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<p style="font-size:13px; color:#475569; line-height:1.5; margin:0;">
							<?php esc_html_e( 'Atomic MySQL named locks protect critical sections. If two concurrent guests attempt to book the final available unit simultaneously, only one obtains the reservation while the other safely receives an availability message.', 'wpcalibrate-stayreserve' ); ?>
						</p>
					</div>

					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px;">
						<div style="font-weight:700; color:#1e293b; font-size:14px; margin-bottom:6px;">
							<?php esc_html_e( '4. Deterministic Pricing Engine', 'wpcalibrate-stayreserve' ); ?>
						</div>
						<p style="font-size:13px; color:#475569; line-height:1.5; margin:0;">
							<?php esc_html_e( 'All totals are computed strictly server-side using minor units (integer cents). Rule order: Date Overrides → Seasonal Rates → Weekend Adjustments → Base Rate → Extras → Coupons → Fees → Taxes.', 'wpcalibrate-stayreserve' ); ?>
						</p>
					</div>

				</div>
			</div>

			<!-- How to Use: 6-Step Quickstart Guide -->
			<div class="postbox" style="padding:24px; margin-bottom:24px; border-radius:8px;">
				<h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:12px; font-size:18px; color:#0f172a;">
					<span class="dashicons dashicons-welcome-learn-more" style="color:#0284c7; vertical-align:text-bottom; margin-right:6px;"></span>
					<?php esc_html_e( 'Step-by-Step Setup & Operational Workflow', 'wpcalibrate-stayreserve' ); ?>
				</h3>
				
				<div style="display:flex; flex-direction:column; gap:16px; margin-top:16px;">
					
					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">1</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Create Room Types & Amenities', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Navigate to WPCalibrate → Room Types. Add your categories, upload photos, set adult/child capacities, room size, and base nightly price. Tag each room type with reusable amenities (e.g. High-Speed WiFi, Ocean View, King Bed).', 'wpcalibrate-stayreserve' ); ?>
								<br><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpcsr_room_type' ) ); ?>"><?php esc_html_e( 'Manage Room Types →', 'wpcalibrate-stayreserve' ); ?></a>
							</p>
						</div>
					</div>

					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">2</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Add Physical Room Units', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Open StayReserve → Physical Units. Create physical inventory units belonging to each Room Type (e.g. Room 101, Room 102). If you have 5 Deluxe Rooms, register 5 physical units to allow 5 simultaneous reservations.', 'wpcalibrate-stayreserve' ); ?>
								<br><a href="<?php echo esc_url( add_query_arg( 'tab', 'rooms', $admin_url ) ); ?>"><?php esc_html_e( 'Manage Physical Units →', 'wpcalibrate-stayreserve' ); ?></a>
							</p>
						</div>
					</div>

					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">3</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Configure Pricing Rules, Seasons & Extras', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Open Rates & Extras. Define peak holiday rates, weekend surcharges, promo coupons, and optional guest extras (e.g. Airport Transfer, Breakfast Buffet, Spa Access).', 'wpcalibrate-stayreserve' ); ?>
								<br><a href="<?php echo esc_url( add_query_arg( 'tab', 'pricing', $admin_url ) ); ?>"><?php esc_html_e( 'Configure Rates & Extras →', 'wpcalibrate-stayreserve' ); ?></a>
							</p>
						</div>
					</div>

					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">4</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Set Booking Rules, Policies & Notifications', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Under Settings, specify currency, default check-in/out times, minimum/maximum advance booking windows, temporary hold duration (default 15 mins), and customizable transactional confirmation emails.', 'wpcalibrate-stayreserve' ); ?>
								<br><a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $admin_url ) ); ?>"><?php esc_html_e( 'Adjust Settings →', 'wpcalibrate-stayreserve' ); ?></a>
							</p>
						</div>
					</div>

					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">5</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Publish Frontend Booking Forms & Search Bars', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Create or edit pages using Gutenberg Dynamic Blocks, Elementor Widgets, or standard shortcodes to embed the availability search bar, room grid, and booking flow.', 'wpcalibrate-stayreserve' ); ?>
							</p>
						</div>
					</div>

					<div style="display:flex; gap:16px; align-items:flex-start;">
						<div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">6</div>
						<div>
							<strong style="font-size:15px; color:#1e293b;"><?php esc_html_e( 'Manage Bookings & Visual Calendar', 'wpcalibrate-stayreserve' ); ?></strong>
							<p style="font-size:13px; color:#475569; margin:4px 0 0; line-height:1.5;">
								<?php esc_html_e( 'Staff can view, create manual phone/walk-in reservations, edit stay dates with automated availability revalidation, view the monthly occupancy grid, and safely export CSV records.', 'wpcalibrate-stayreserve' ); ?>
								<br><a href="<?php echo esc_url( add_query_arg( 'tab', 'calendar', $admin_url ) ); ?>"><?php esc_html_e( 'Open Calendar →', 'wpcalibrate-stayreserve' ); ?></a>
							</p>
						</div>
					</div>

				</div>
			</div>

			<!-- Shortcodes & Blocks Reference -->
			<div class="postbox" style="padding:24px; border-radius:8px;">
				<h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:12px; font-size:18px; color:#0f172a;">
					<span class="dashicons dashicons-shortcode" style="color:#0284c7; vertical-align:text-bottom; margin-right:6px;"></span>
					<?php esc_html_e( 'Shortcode & Embedding Quick Reference', 'wpcalibrate-stayreserve' ); ?>
				</h3>
				<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
					<thead>
						<tr>
							<th style="width:240px;"><?php esc_html_e( 'Shortcode', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Description & Purpose', 'wpcalibrate-stayreserve' ); ?></th>
							<th style="width:200px;"><?php esc_html_e( 'Gutenberg / Elementor', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[wpcsr_search]</code></td>
							<td><?php esc_html_e( 'Renders the responsive date & guest availability search form (Check-in, Check-out, Adults, Children).', 'wpcalibrate-stayreserve' ); ?></td>
							<td><strong>Stay Search Block / Widget</strong></td>
						</tr>
						<tr>
							<td><code>[wpcsr_rooms]</code></td>
							<td><?php esc_html_e( 'Displays the interactive catalog of available room types with live rates, amenities, and photos.', 'wpcalibrate-stayreserve' ); ?></td>
							<td><strong>Stay Rooms Block / Widget</strong></td>
						</tr>
						<tr>
							<td><code>[wpcsr_booking]</code></td>
							<td><?php esc_html_e( 'Embeds the complete 7-step booking wizard (Selection → Guest Details → Review → Payment → Confirmation).', 'wpcalibrate-stayreserve' ); ?></td>
							<td><strong>Stay Booking Block / Widget</strong></td>
						</tr>
						<tr>
							<td><code>[wpcsr_manage_booking]</code></td>
							<td><?php esc_html_e( 'Secure guest self-management portal allowing guests to view reservation details or cancel according to policy.', 'wpcalibrate-stayreserve' ); ?></td>
							<td><strong>Stay Management Block</strong></td>
						</tr>
					</tbody>
				</table>
			</div>

		</div>
		<?php
	}
}
