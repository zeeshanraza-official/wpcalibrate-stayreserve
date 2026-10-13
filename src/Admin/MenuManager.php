<?php
/**
 * Admin Menu Manager for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class MenuManager
 */
class MenuManager {

	public const PARENT_SLUG = 'wpcalibrate';
	public const PAGE_SLUG   = 'wpcalibrate-stayreserve';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ), 9 );
		add_action( 'admin_head', array( $this, 'render_menu_icon_styles' ) );
	}

	/**
	 * Register top-level WPCalibrate parent menu (if not existing) and StayReserve submenu.
	 *
	 * @return void
	 */
	public function register_admin_menus(): void {
		global $menu, $admin_page_hooks;

		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_SLUG === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		// Register parent WPCalibrate menu only if not already registered by another WPCalibrate plugin
		if ( ! $parent_exists && ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			// Vector SVG menu icon matching standard WordPress admin dashboard icon dimensions (20x20) and crisp color (#f0f0f1)
			$icon_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIiBmaWxsPSIjZjBmMGYxIj4KICA8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTUuNSAxLjVDMy4yOSAxLjUgMS41IDMuMjkgMS41IDUuNXY5YzAgMi4yMSAxLjc5IDQgNCA0aDljMi4yMSAwIDQtMS43OSA0LTR2LTEuOGgtMi40djEuOGMwIC44OC0uNzIgMS42LTEuNiAxLjZoLTljLS44OCAwLTEuNi0uNzItMS42LTEuNnYtOWMwLS44OC43Mi0xLjYgMS42LTEuNmg5Yy44OCAwIDEuNi43MiAxLjYgMS42djEuOGgyLjRWNS41YzAtMi4yMS0xLjc5LTQtNC00aC05eiIvPgogIDxyZWN0IHg9IjE1LjgiIHk9IjcuNiIgd2lkdGg9IjIuNCIgaGVpZ2h0PSI0LjgiIHJ4PSIxLjIiLz4KICA8cGF0aCBkPSJNNC4yIDYuNWgyLjJsMS40IDUuMiAxLjMtNC4yaDEuNmwxLjMgNC4yIDEuNC01LjJoMi4yTDEzLjcgMTRoLTIuMWwtMS40LTQuNUw4LjggMTRINi43TDQuMiA2LjV6Ii8+CiAgPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMS4yIDYuNWgzLjZjMS40IDAgMi40LjkgMi40IDIuMnMtMSAyLjItMi40IDIuMmgtMS42VjE0aC0yVjYuNXptMiAxLjZ2MS4yaDEuNWMuNCAwIC43LS4zLjctLjZzLS4zLS42LS43LS42aC0xLjV6Ii8+Cjwvc3ZnPgo=';
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				'read',
				self::PARENT_SLUG,
				array( $this, 'render_main_page' ),
				$icon_svg,
				58
			);
		}

		// Add StayReserve Submenu
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'StayReserve Accommodation Booking', 'wpcalibrate-stayreserve' ),
			__( 'StayReserve', 'wpcalibrate-stayreserve' ),
			CapabilityManager::CAP_VIEW_BOOKINGS,
			self::PAGE_SLUG,
			array( $this, 'render_main_page' )
		);

		// Also attach Room Types CPT and Amenities submenu under WPCalibrate parent
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Room Types', 'wpcalibrate-stayreserve' ),
			__( '— Room Types', 'wpcalibrate-stayreserve' ),
			CapabilityManager::CAP_MANAGE_ROOMS,
			'edit.php?post_type=' . RoomTypePostType::POST_TYPE
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Room Amenities', 'wpcalibrate-stayreserve' ),
			__( '— Amenities', 'wpcalibrate-stayreserve' ),
			CapabilityManager::CAP_MANAGE_ROOMS,
			'edit-tags.php?taxonomy=wpcsr_amenity&post_type=' . RoomTypePostType::POST_TYPE
		);
	}

	/**
	 * Route and render the plugin interface based on active tab.
	 *
	 * @return void
	 */
	public function render_main_page(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_VIEW_BOOKINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpcalibrate-stayreserve' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

		// Render header with navigation tabs
		$this->render_header( $tab );

		echo '<div class="wpcsr-tab-content" style="background:#fff; border:1px solid #ccd0d4; padding:20px; box-shadow:0 1px 1px rgba(0,0,0,.04); min-height:400px;">';

		switch ( $tab ) {
			case 'overview':
				$overview = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_overview' );
				if ( $overview instanceof OverviewScreen ) {
					$overview->render();
				}
				break;

			case 'bookings':
				$admin_bookings = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_bookings' );
				if ( $admin_bookings instanceof BookingAdminController ) {
					$admin_bookings->render();
				}
				break;

			case 'calendar':
				$calendar = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_calendar' );
				if ( $calendar instanceof CalendarScreen ) {
					$calendar->render();
				}
				break;

			case 'rooms':
				$units = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_units' );
				if ( $units instanceof UnitsScreen ) {
					$units->render();
				}
				break;

			case 'pricing':
				$pricing = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_pricing' );
				if ( $pricing instanceof PricingScreen ) {
					$pricing->render();
				}
				break;

			case 'settings':
				$settings = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_settings' );
				if ( $settings instanceof SettingsPage ) {
					$settings->render();
				}
				break;

			case 'tools':
				$tools = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'admin_tools' );
				if ( $tools instanceof ToolsScreen ) {
					$tools->render();
				}
				break;

			case 'support':
				$support = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'support' );
				if ( $support instanceof \WPCalibrate\StayReserve\Support\SupportInfo ) {
					$support->render();
				}
				break;

			case 'dashboard':
			default:
				$this->render_dashboard_tab();
				break;
		}

		echo '</div>';
		echo '</div>'; // close wrap
	}

	/**
	 * Render header and navigation tabs.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_header( string $active_tab ): void {
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'wpcalibrate-stayreserve' ),
			'overview'  => __( 'Overview', 'wpcalibrate-stayreserve' ),
			'bookings'  => __( 'Bookings', 'wpcalibrate-stayreserve' ),
			'calendar'  => __( 'Calendar', 'wpcalibrate-stayreserve' ),
			'rooms'     => __( 'Physical Units', 'wpcalibrate-stayreserve' ),
			'pricing'   => __( 'Rates & Extras', 'wpcalibrate-stayreserve' ),
			'settings'  => __( 'Settings', 'wpcalibrate-stayreserve' ),
			'tools'     => __( 'Tools & Status', 'wpcalibrate-stayreserve' ),
			'support'   => __( 'Support', 'wpcalibrate-stayreserve' ),
		);

		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap wpcsr-admin-wrap" style="max-width: 1400px;">
			<div style="display:flex; align-items:center; gap:16px; margin: 16px 0 20px;">
				<img src="<?php echo esc_url( WPCSR_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" style="height:48px; width:48px; border-radius:6px; object-fit:contain;">
				<div>
					<h1 style="margin:0; font-size:24px; font-weight:700; line-height:1.2;">
						<?php esc_html_e( 'WPCalibrate StayReserve', 'wpcalibrate-stayreserve' ); ?>
						<span style="font-size:12px; font-weight:400; background:#0073aa; color:#fff; padding:2px 8px; border-radius:12px; vertical-align:middle; margin-left:8px;">
							v<?php echo esc_html( WPCSR_VERSION ); ?>
						</span>
					</h1>
					<p style="margin:4px 0 0; color:#646970; font-size:13px;">
						<?php esc_html_e( 'Professional accommodation booking, inventory, and rates management.', 'wpcalibrate-stayreserve' ); ?>
					</p>
				</div>
			</div>

			<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php
	}

	/**
	 * Render the dashboard overview tab.
	 *
	 * @return void
	 */
	private function render_dashboard_tab(): void {
		/** @var \WPCalibrate\StayReserve\Booking\BookingRepository $booking_repo */
		$booking_repo = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'booking_repo' );
		/** @var \WPCalibrate\StayReserve\Rooms\RoomUnitRepository $unit_repo */
		$unit_repo = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'room_units' );

		$total_bookings = $booking_repo ? $booking_repo->count() : 0;
		$pending_bookings = $booking_repo ? $booking_repo->count( array( 'status' => 'pending' ) ) : 0;
		$confirmed_bookings = $booking_repo ? $booking_repo->count( array( 'status' => 'confirmed' ) ) : 0;
		$recent_bookings = $booking_repo ? $booking_repo->query( array( 'per_page' => 5 ) ) : array();
		?>
		<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
			<div style="background:#f0f6fc; border:1px solid #c8d7e1; border-radius:6px; padding:16px;">
				<div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#0073aa;"><?php esc_html_e( 'Total Bookings', 'wpcalibrate-stayreserve' ); ?></div>
				<div style="font-size:28px; font-weight:700; color:#1d2327; margin-top:8px;"><?php echo esc_html( (string) $total_bookings ); ?></div>
			</div>
			<div style="background:#fff8e5; border:1px solid #f0b849; border-radius:6px; padding:16px;">
				<div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#b26200;"><?php esc_html_e( 'Pending Bookings', 'wpcalibrate-stayreserve' ); ?></div>
				<div style="font-size:28px; font-weight:700; color:#1d2327; margin-top:8px;"><?php echo esc_html( (string) $pending_bookings ); ?></div>
			</div>
			<div style="background:#edf7ed; border:1px solid #72a275; border-radius:6px; padding:16px;">
				<div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#2e7d32;"><?php esc_html_e( 'Confirmed Bookings', 'wpcalibrate-stayreserve' ); ?></div>
				<div style="font-size:28px; font-weight:700; color:#1d2327; margin-top:8px;"><?php echo esc_html( (string) $confirmed_bookings ); ?></div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Recent Reservations', 'wpcalibrate-stayreserve' ); ?></h3>
		<?php if ( empty( $recent_bookings ) ) : ?>
			<p style="color:#646970;"><?php esc_html_e( 'No reservations recorded yet.', 'wpcalibrate-stayreserve' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reference', 'wpcalibrate-stayreserve' ); ?></th>
						<th><?php esc_html_e( 'Guest', 'wpcalibrate-stayreserve' ); ?></th>
						<th><?php esc_html_e( 'Check-in', 'wpcalibrate-stayreserve' ); ?></th>
						<th><?php esc_html_e( 'Check-out', 'wpcalibrate-stayreserve' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpcalibrate-stayreserve' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wpcalibrate-stayreserve' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_bookings as $b ) : ?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=edit&booking_id=' . $b->id ) ); ?>">
										<?php echo esc_html( $b->public_reference ); ?>
									</a>
								</strong>
							</td>
							<td><?php echo esc_html( $b->get_customer_name() ); ?></td>
							<td><?php echo esc_html( $b->check_in ); ?></td>
							<td><?php echo esc_html( $b->check_out ); ?></td>
							<td><span class="wpcsr-status-pill status-<?php echo esc_attr( $b->status ); ?>"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
							<td><?php echo esc_html( $b->get_formatted_total() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Output inline CSS to style and strictly constrain the WPCalibrate admin menu icon.
	 *
	 * Ensures the menu icon is strictly 20px x 20px, matches core dashboard icon colors (#a7aaad),
	 * and highlights on hover/active to match standard WordPress dashboard icons.
	 *
	 * @return void
	 */
	public function render_menu_icon_styles(): void {
		?>
		<style id="wpcsr-admin-menu-icon-css">
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image {
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image svg {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding: 0 !important;
				margin: auto !important;
				opacity: 1 !important;
				color: #f0f0f1 !important;
				fill: #f0f0f1 !important;
				transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out;
			}
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image.svg {
				background-size: 20px 20px !important;
				background-position: center center !important;
				background-repeat: no-repeat !important;
				opacity: 1 !important;
				transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out;
			}
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image,
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image svg,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image svg,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image svg {
				opacity: 1 !important;
				filter: brightness(1.25) !important;
			}
		</style>
		<?php
	}
}
