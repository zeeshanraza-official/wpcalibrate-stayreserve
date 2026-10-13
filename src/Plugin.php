<?php
/**
 * Main Plugin Orchestrator for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service registry.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Whether plugin has initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		$this->register_services();
		$this->init_hooks();
	}

	/**
	 * Register core services into the registry.
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Core utility & logging
		$logger = new Logging\Logger();
		$this->set_service( 'logger', $logger );

		// Database schema & migrator
		$schema   = new Database\Schema();
		$migrator = new Database\Migrator( $schema, $logger );
		$this->set_service( 'schema', $schema );
		$this->set_service( 'migrator', $migrator );

		// Security & tokens
		$token_manager = new Security\TokenManager();
		$this->set_service( 'token_manager', $token_manager );

		// Capabilities
		$capabilities = new Capabilities\CapabilityManager();
		$this->set_service( 'capabilities', $capabilities );

		// CPT & Taxonomies
		$room_type_cpt   = new Rooms\RoomTypePostType();
		$amenity_tax     = new Rooms\AmenityTaxonomy();
		$unit_repository = new Rooms\RoomUnitRepository();
		$this->set_service( 'room_types', $room_type_cpt );
		$this->set_service( 'amenities', $amenity_tax );
		$this->set_service( 'room_units', $unit_repository );

		// Blocks & unavailable periods
		$block_repository = new Availability\BlockRepository();
		$this->set_service( 'blocks', $block_repository );

		// Concurrency locker
		$concurrency = new Availability\ConcurrencyLocker();
		$this->set_service( 'concurrency', $concurrency );

		// Inventory holds
		$hold_manager = new Inventory\HoldManager();
		$this->set_service( 'holds', $hold_manager );

		// Rate rules & Pricing engine
		$rate_rules     = new Pricing\RateRuleRepository();
		$pricing_engine = new Pricing\PricingEngine( $rate_rules );
		$this->set_service( 'rate_rules', $rate_rules );
		$this->set_service( 'pricing', $pricing_engine );

		// Extras & Coupons
		$extras_repo    = new Extras\ExtraRepository();
		$coupon_manager = new Coupons\CouponManager();
		$this->set_service( 'extras', $extras_repo );
		$this->set_service( 'coupons', $coupon_manager );

		// Availability engine
		$availability = new Availability\AvailabilityEngine(
			$unit_repository,
			$block_repository,
			$hold_manager
		);
		$this->set_service( 'availability', $availability );

		// Bookings repository & service
		$booking_repo    = new Booking\BookingRepository();
		$booking_service = new Booking\BookingService(
			$booking_repo,
			$availability,
			$concurrency,
			$pricing_engine,
			$hold_manager,
			$logger
		);
		$this->set_service( 'booking_repo', $booking_repo );
		$this->set_service( 'booking_service', $booking_service );

		// Email manager
		$email_manager = new Email\EmailManager( $logger );
		$this->set_service( 'emails', $email_manager );

		// Checkout service
		$checkout_service = new Checkout\CheckoutService(
			$booking_service,
			$hold_manager,
			$pricing_engine
		);
		$this->set_service( 'checkout', $checkout_service );

		// Admin management
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$admin_menu     = new Admin\MenuManager();
			$settings_page  = new Admin\SettingsPage();
			$bookings_admin = new Admin\BookingAdminController( $booking_repo, $booking_service, $availability );
			$calendar_admin = new Admin\CalendarScreen( $availability, $booking_repo );
			$units_admin    = new Admin\UnitsScreen( $unit_repository, $room_type_cpt );
			$pricing_admin  = new Admin\PricingScreen( $rate_rules, $extras_repo, $coupon_manager );
			$tools_admin    = new Admin\ToolsScreen( $migrator, $logger, $booking_repo );
			$overview_admin = new Admin\OverviewScreen();

			$this->set_service( 'admin_menu', $admin_menu );
			$this->set_service( 'admin_settings', $settings_page );
			$this->set_service( 'admin_bookings', $bookings_admin );
			$this->set_service( 'admin_calendar', $calendar_admin );
			$this->set_service( 'admin_units', $units_admin );
			$this->set_service( 'admin_pricing', $pricing_admin );
			$this->set_service( 'admin_tools', $tools_admin );
			$this->set_service( 'admin_overview', $overview_admin );
		}

		// Frontend
		$shortcodes       = new Frontend\Shortcodes();
		$booking_flow     = new Frontend\BookingFlowController( $availability, $pricing_engine, $checkout_service );
		$customer_portal  = new Frontend\CustomerPortalController( $booking_repo, $booking_service, $token_manager );
		$blocks           = new Frontend\BlockRegistry();
		$this->set_service( 'shortcodes', $shortcodes );
		$this->set_service( 'booking_flow', $booking_flow );
		$this->set_service( 'customer_portal', $customer_portal );
		$this->set_service( 'blocks', $blocks );

		// REST API
		$rest = new REST\RestController( $availability, $pricing_engine, $checkout_service, $booking_service, $coupon_manager );
		$this->set_service( 'rest', $rest );

		// Scheduling
		$scheduler = new Scheduling\CronScheduler( $hold_manager, $logger );
		$this->set_service( 'scheduler', $scheduler );

		// Privacy
		$privacy = new Privacy\PrivacyManager( $booking_repo );
		$this->set_service( 'privacy', $privacy );

		// Licensing adapter
		$license = new Integrations\Licensing\LicenseService();
		$this->set_service( 'license', $license );

		// Support info
		$support = new Support\SupportInfo();
		$this->set_service( 'support', $support );

		// Integrations: WooCommerce
		$wc_integration = new Integrations\WooCommerce\WooCommerceIntegration( $booking_service, $hold_manager, $pricing_engine, $logger );
		$this->set_service( 'woocommerce', $wc_integration );

		// Integrations: Elementor
		$elementor_integration = new Integrations\Elementor\ElementorIntegration();
		$this->set_service( 'elementor', $elementor_integration );
	}

	/**
	 * Initialize WordPress action & filter hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Translation loading
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		// Check database migrations on admin_init if needed
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybe_migrate_db' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_filter( 'plugin_action_links_' . WPCSR_BASENAME, array( $this, 'add_plugin_action_links' ) );
		}

		// Frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Initialize services that register WP hooks
		foreach ( $this->services as $service ) {
			if ( method_exists( $service, 'register_hooks' ) ) {
				$service->register_hooks();
			}
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-stayreserve',
			false,
			dirname( WPCSR_BASENAME ) . '/languages'
		);
	}

	/**
	 * Conditionally run database migrations if version mismatch.
	 *
	 * @return void
	 */
	public function maybe_migrate_db(): void {
		/** @var Database\Migrator|null $migrator */
		$migrator = $this->get_service( 'migrator' );
		if ( $migrator instanceof Database\Migrator ) {
			$migrator->check_and_migrate();
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Only enqueue on our plugin pages or room type CPT
		$screen = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		$is_stayreserve_screen = false;
		if ( false !== strpos( $screen_id, 'wpcalibrate' ) || false !== strpos( $screen_id, 'wpcsr_' ) || false !== strpos( $hook_suffix, 'wpcalibrate-stayreserve' ) ) {
			$is_stayreserve_screen = true;
		}

		if ( ! $is_stayreserve_screen ) {
			return;
		}

		wp_enqueue_style(
			'wpcsr-admin-css',
			WPCSR_URL . 'assets/admin/css/admin.css',
			array(),
			WPCSR_VERSION
		);

		wp_enqueue_script(
			'wpcsr-admin-js',
			WPCSR_URL . 'assets/admin/js/admin.js',
			array( 'jquery' ),
			WPCSR_VERSION,
			true
		);

		wp_localize_script(
			'wpcsr-admin-js',
			'wpcsrAdminData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'wpcalibrate-stayreserve/v1/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'nonce'     => wp_create_nonce( 'wpcsr_admin_nonce' ),
				'strings'   => array(
					'confirmDelete' => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'wpcalibrate-stayreserve' ),
					'confirmCancel' => __( 'Are you sure you want to cancel this booking? Reserved inventory will be released.', 'wpcalibrate-stayreserve' ),
					'saving'        => __( 'Saving...', 'wpcalibrate-stayreserve' ),
					'saved'         => __( 'Saved successfully.', 'wpcalibrate-stayreserve' ),
					'error'         => __( 'An error occurred. Please try again.', 'wpcalibrate-stayreserve' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		wp_register_style(
			'wpcsr-frontend-css',
			WPCSR_URL . 'assets/frontend/css/frontend.css',
			array(),
			WPCSR_VERSION
		);

		wp_register_script(
			'wpcsr-frontend-js',
			WPCSR_URL . 'assets/frontend/js/frontend.js',
			array(),
			WPCSR_VERSION,
			true
		);

		wp_localize_script(
			'wpcsr-frontend-js',
			'wpcsrFrontendData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wpcalibrate-stayreserve/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpcsr_frontend_nonce' ),
				'strings'   => array(
					'selectDates'    => __( 'Please select check-in and check-out dates.', 'wpcalibrate-stayreserve' ),
					'invalidDates'   => __( 'Check-out date must be after check-in date.', 'wpcalibrate-stayreserve' ),
					'searching'      => __( 'Searching available rooms...', 'wpcalibrate-stayreserve' ),
					'noRoomsFound'   => __( 'No available rooms found for the selected dates and guest count.', 'wpcalibrate-stayreserve' ),
					'confirmBooking' => __( 'Confirming your reservation...', 'wpcalibrate-stayreserve' ),
					'processing'     => __( 'Processing...', 'wpcalibrate-stayreserve' ),
				),
			)
		);
	}

	/**
	 * Add plugin action links on plugins.php.
	 *
	 * @param array<string, string> $links Current links.
	 * @return array<string, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$custom_links = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=settings' ) ),
				esc_html__( 'Settings', 'wpcalibrate-stayreserve' )
			),
			'bookings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings' ) ),
				esc_html__( 'Bookings', 'wpcalibrate-stayreserve' )
			),
			'support'  => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=support' ) ),
				esc_html__( 'Support', 'wpcalibrate-stayreserve' )
			),
		);
		return array_merge( $custom_links, $links );
	}

	/**
	 * Get a registered service.
	 *
	 * @param string $key Service identifier.
	 * @return object|null
	 */
	public function get_service( string $key ): ?object {
		return $this->services[ $key ] ?? null;
	}

	/**
	 * Set a service into registry.
	 *
	 * @param string $key Service identifier.
	 * @param object $service Service instance.
	 * @return void
	 */
	public function set_service( string $key, object $service ): void {
		$this->services[ $key ] = $service;
	}
}
