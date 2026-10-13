<?php
/**
 * Frontend Booking Flow Controller managing search and multi-step reservation wizard.
 *
 * @package WPCalibrate\StayReserve\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Frontend;

use WPCalibrate\StayReserve\Availability\AvailabilityEngine;
use WPCalibrate\StayReserve\Checkout\CheckoutService;
use WPCalibrate\StayReserve\Coupons\CouponManager;
use WPCalibrate\StayReserve\Extras\ExtraRepository;
use WPCalibrate\StayReserve\Pricing\PricingEngine;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Settings\SettingsManager;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class BookingFlowController
 */
class BookingFlowController {

	private AvailabilityEngine $availability;
	private PricingEngine $pricing;
	private CheckoutService $checkout;

	/**
	 * Constructor.
	 */
	public function __construct(
		AvailabilityEngine $availability,
		PricingEngine $pricing,
		CheckoutService $checkout
	) {
		$this->availability = $availability;
		$this->pricing      = $pricing;
		$this->checkout     = $checkout;
	}

	/**
	 * Main entry point for [wpcsr_booking].
	 *
	 * @return void
	 */
	public function render(): void {
		$step = isset( $_REQUEST['wpcsr_step'] ) ? sanitize_key( $_REQUEST['wpcsr_step'] ) : 'search';

		// Handle reservation form submission
		if ( 'confirm' === $step && ! empty( $_POST ) ) {
			$this->process_reservation_submission();
			return;
		}

		echo '<div class="wpcsr-booking-container" id="wpcsr-booking-engine">';

		switch ( $step ) {
			case 'configure':
				$this->render_room_configuration();
				break;

			case 'review':
				$this->render_booking_review();
				break;

			case 'complete':
				$this->render_booking_confirmation();
				break;

			case 'search':
			default:
				$this->render_search_and_results();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render Step 1: Availability Search Form.
	 *
	 * @return void
	 */
	public function render_search_form(): void {
		$check_in  = isset( $_GET['check_in'] ) ? DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_GET['check_in'] ) ) ) : date( 'Y-m-d' );
		$check_out = isset( $_GET['check_out'] ) ? DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_GET['check_out'] ) ) ) : date( 'Y-m-d', strtotime( '+1 day' ) );
		$adults    = isset( $_GET['adults'] ) ? max( 1, (int) $_GET['adults'] ) : 2;
		$children  = isset( $_GET['children'] ) ? max( 0, (int) $_GET['children'] ) : 0;
		$rooms     = isset( $_GET['rooms'] ) ? max( 1, (int) $_GET['rooms'] ) : 1;
		?>
		<form method="get" class="wpcsr-search-form" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; box-shadow:0 4px 12px rgba(0,0,0,0.04);">
			<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; align-items:end;">
				<div>
					<label for="wpcsr_check_in" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#4a5568;">
						<?php esc_html_e( 'Check-in Date', 'wpcalibrate-stayreserve' ); ?>
					</label>
					<input type="date" id="wpcsr_check_in" name="check_in" value="<?php echo esc_attr( $check_in ); ?>" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
				</div>

				<div>
					<label for="wpcsr_check_out" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#4a5568;">
						<?php esc_html_e( 'Check-out Date', 'wpcalibrate-stayreserve' ); ?>
					</label>
					<input type="date" id="wpcsr_check_out" name="check_out" value="<?php echo esc_attr( $check_out ); ?>" min="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
				</div>

				<div>
					<label for="wpcsr_adults" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#4a5568;">
						<?php esc_html_e( 'Adults', 'wpcalibrate-stayreserve' ); ?>
					</label>
					<input type="number" id="wpcsr_adults" name="adults" min="1" max="20" value="<?php echo esc_attr( (string) $adults ); ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
				</div>

				<div>
					<label for="wpcsr_children" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#4a5568;">
						<?php esc_html_e( 'Children', 'wpcalibrate-stayreserve' ); ?>
					</label>
					<input type="number" id="wpcsr_children" name="children" min="0" max="10" value="<?php echo esc_attr( (string) $children ); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
				</div>

				<div>
					<label for="wpcsr_rooms" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#4a5568;">
						<?php esc_html_e( 'Rooms', 'wpcalibrate-stayreserve' ); ?>
					</label>
					<input type="number" id="wpcsr_rooms" name="rooms" min="1" max="10" value="<?php echo esc_attr( (string) $rooms ); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
				</div>

				<div>
					<input type="hidden" name="wpcsr_search_submitted" value="1">
					<button type="submit" class="wpcsr-btn-primary" style="width:100%; background:#0073aa; color:#fff; border:none; border-radius:6px; padding:12px 18px; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.2s;">
						<?php esc_html_e( 'Search Availability', 'wpcalibrate-stayreserve' ); ?>
					</button>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Render Step 1 & Step 2 together (Search Form + Results list).
	 *
	 * @return void
	 */
	public function render_search_and_results(): void {
		$this->render_search_form();

		$check_in  = isset( $_GET['check_in'] ) ? sanitize_text_field( wp_unslash( $_GET['check_in'] ) ) : date( 'Y-m-d' );
		$check_out = isset( $_GET['check_out'] ) ? sanitize_text_field( wp_unslash( $_GET['check_out'] ) ) : date( 'Y-m-d', strtotime( '+1 day' ) );
		$adults    = isset( $_GET['adults'] ) ? max( 1, (int) $_GET['adults'] ) : 2;
		$children  = isset( $_GET['children'] ) ? max( 0, (int) $_GET['children'] ) : 0;
		$rooms     = isset( $_GET['rooms'] ) ? max( 1, (int) $_GET['rooms'] ) : 1;

		$this->render_results_list( $check_in, $check_out, $adults, $children, $rooms );
	}

	/**
	 * Standalone [wpcsr_rooms] shortcode output.
	 *
	 * @return void
	 */
	public function render_available_rooms(): void {
		$check_in  = isset( $_GET['check_in'] ) ? sanitize_text_field( wp_unslash( $_GET['check_in'] ) ) : date( 'Y-m-d' );
		$check_out = isset( $_GET['check_out'] ) ? sanitize_text_field( wp_unslash( $_GET['check_out'] ) ) : date( 'Y-m-d', strtotime( '+1 day' ) );
		$adults    = isset( $_GET['adults'] ) ? max( 1, (int) $_GET['adults'] ) : 2;
		$children  = isset( $_GET['children'] ) ? max( 0, (int) $_GET['children'] ) : 0;
		$rooms     = isset( $_GET['rooms'] ) ? max( 1, (int) $_GET['rooms'] ) : 1;

		$this->render_results_list( $check_in, $check_out, $adults, $children, $rooms );
	}

	/**
	 * Render results list of available rooms with calculated prices and capacity.
	 *
	 * @param string $check_in Check-in.
	 * @param string $check_out Check-out.
	 * @param int    $adults Adults.
	 * @param int    $children Children.
	 * @param int    $rooms Rooms.
	 * @return void
	 */
	private function render_results_list( string $check_in, string $check_out, int $adults, int $children, int $rooms ): void {
		$clean_in  = DateTimeUtil::format_date( $check_in );
		$clean_out = DateTimeUtil::format_date( $check_out );

		if ( $clean_out <= $clean_in ) {
			echo '<div class="wpcsr-notice wpcsr-notice-warning" style="margin-top:20px; padding:16px; background:#fff8e5; border-left:4px solid #f0b849; border-radius:4px;">'
				. esc_html__( 'Please select a check-out date later than the check-in date.', 'wpcalibrate-stayreserve' )
				. '</div>';
			return;
		}

		$nights  = DateTimeUtil::calculate_nights( $clean_in, $clean_out );
		$results = $this->availability->search_availability( $clean_in, $clean_out, $adults, $children, $rooms );

		echo '<div class="wpcsr-results-wrap" style="margin-top:32px;">';
		echo '<h3 style="font-size:20px; font-weight:700; margin-bottom:8px;">'
			. sprintf(
				/* translators: 1: Count of available room types, 2: Nights count */
				esc_html__( '%1$d Accommodation Options Available (%2$d Nights)', 'wpcalibrate-stayreserve' ),
				count( $results ),
				$nights
			)
			. '</h3>';

		if ( empty( $results ) ) {
			echo '<div class="wpcsr-no-results" style="padding:32px; text-align:center; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; margin-top:16px;">'
				. '<p style="font-size:16px; color:#4a5568; margin:0;">'
				. esc_html__( 'No rooms are available matching your exact dates and party size. Please try alternate dates.', 'wpcalibrate-stayreserve' )
				. '</p></div></div>';
			return;
		}

		echo '<div style="display:flex; flex-direction:column; gap:20px; margin-top:20px;">';

		foreach ( $results as $r ) {
			$quote = $this->pricing->calculate_quote( (int) $r['id'], $clean_in, $clean_out, $rooms, $adults, $children );
			$total_price_formatted = MoneyUtil::format_minor( $quote['accommodation_subtotal'], $quote['currency'] );

			$config_url = add_query_arg(
				array(
					'wpcsr_step'    => 'configure',
					'room_type_id'  => $r['id'],
					'check_in'      => $clean_in,
					'check_out'     => $clean_out,
					'adults'        => $adults,
					'children'      => $children,
					'rooms'         => $rooms,
				)
			);
			?>
			<div class="wpcsr-room-card" style="display:grid; grid-template-columns: 280px 1fr 220px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
				<!-- Room Image -->
				<div style="background:#f1f5f9; min-height:180px; position:relative;">
					<?php if ( ! empty( $r['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $r['image_url'] ); ?>" alt="<?php echo esc_attr( $r['name'] ); ?>" style="width:100%; height:100%; object-fit:cover;">
					<?php else : ?>
						<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#94a3b8; font-size:13px;">
							<?php esc_html_e( 'No photo available', 'wpcalibrate-stayreserve' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Room Details -->
				<div style="padding:20px 24px; display:flex; flex-direction:column; justify-content:space-between;">
					<div>
						<h4 style="margin:0 0 8px; font-size:20px; font-weight:700; color:#1a202c;"><?php echo esc_html( $r['name'] ); ?></h4>
						<p style="margin:0 0 12px; color:#4a5568; font-size:14px; line-height:1.5;"><?php echo esc_html( $r['excerpt'] ); ?></p>

						<div style="display:flex; flex-wrap:wrap; gap:16px; font-size:13px; color:#718096; margin-bottom:12px;">
							<span>&bull; <?php echo esc_html( sprintf( __( 'Max %d Guests (%d Adults)', 'wpcalibrate-stayreserve' ), $r['total_capacity'], $r['adults_capacity'] ) ); ?></span>
							<?php if ( ! empty( $r['bed_info'] ) ) : ?>
								<span>&bull; <?php echo esc_html( $r['bed_info'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $r['room_size'] ) ) : ?>
								<span>&bull; <?php echo esc_html( $r['room_size'] ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $r['amenities'] ) ) : ?>
							<div style="display:flex; flex-wrap:wrap; gap:6px;">
								<?php foreach ( $r['amenities'] as $am ) : ?>
									<span style="background:#edf2f7; color:#4a5568; font-size:11px; padding:3px 8px; border-radius:4px;"><?php echo esc_html( $am ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div style="margin-top:12px; font-size:12px; color:#2e7d32; font-weight:600;">
						<?php
						/* translators: %d: Rooms available */
						echo esc_html( sprintf( _n( 'Only %d room remaining!', '%d rooms available for these dates', $r['available_units'], 'wpcalibrate-stayreserve' ), $r['available_units'] ) );
						?>
					</div>
				</div>

				<!-- Pricing & Action -->
				<div style="padding:20px; background:#f8fafc; border-left:1px solid #edf2f7; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
					<div style="font-size:12px; color:#718096;"><?php esc_html_e( 'Total for Stay', 'wpcalibrate-stayreserve' ); ?></div>
					<div style="font-size:24px; font-weight:800; color:#0073aa; margin:4px 0;">
						<?php echo esc_html( $total_price_formatted ); ?>
					</div>
					<div style="font-size:12px; color:#a0aec0; margin-bottom:16px;">
						<?php echo esc_html( sprintf( __( '%d Nights, %d Room(s)', 'wpcalibrate-stayreserve' ), $nights, $rooms ) ); ?>
					</div>

					<a href="<?php echo esc_url( $config_url ); ?>" class="wpcsr-btn-primary" style="display:inline-block; background:#0073aa; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; font-weight:600; font-size:14px;">
						<?php esc_html_e( 'Select Room &rarr;', 'wpcalibrate-stayreserve' ); ?>
					</a>
				</div>
			</div>
			<?php
		}

		echo '</div></div>';
	}

	/**
	 * Step 3: Room Configuration & Extras.
	 *
	 * @return void
	 */
	private function render_room_configuration(): void {
		$room_type_id = (int) ( $_GET['room_type_id'] ?? 0 );
		$check_in     = DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_GET['check_in'] ?? '' ) ) );
		$check_out    = DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_GET['check_out'] ?? '' ) ) );
		$adults       = max( 1, (int) ( $_GET['adults'] ?? 2 ) );
		$children     = max( 0, (int) ( $_GET['children'] ?? 0 ) );
		$rooms        = max( 1, (int) ( $_GET['rooms'] ?? 1 ) );

		$post = get_post( $room_type_id );
		if ( ! $post || RoomTypePostType::POST_TYPE !== $post->post_type ) {
			echo '<p>' . esc_html__( 'Invalid room selection.', 'wpcalibrate-stayreserve' ) . '</p>';
			return;
		}

		$extras_repo = new ExtraRepository();
		$available_extras = $extras_repo->get_available_for_room( $room_type_id );

		$nights = DateTimeUtil::calculate_nights( $check_in, $check_out );
		?>
		<div style="max-width:800px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px; box-shadow:0 4px 16px rgba(0,0,0,0.04);">
			<h2 style="margin-top:0; font-size:24px; font-weight:700; color:#1a202c;">
				<?php esc_html_e( 'Configure Your Stay', 'wpcalibrate-stayreserve' ); ?> &mdash; <?php echo esc_html( $post->post_title ); ?>
			</h2>

			<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:20px 0; font-size:14px; color:#4a5568;">
				<strong><?php esc_html_e( 'Stay Summary:', 'wpcalibrate-stayreserve' ); ?></strong>
				<?php echo esc_html( sprintf( __( '%s to %s (%d nights) &bull; %d Adults, %d Children', 'wpcalibrate-stayreserve' ), $check_in, $check_out, $nights, $adults, $children ) ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( add_query_arg( 'wpcsr_step', 'review' ) ); ?>">
				<input type="hidden" name="wpcsr_step" value="review">
				<input type="hidden" name="room_type_id" value="<?php echo esc_attr( (string) $room_type_id ); ?>">
				<input type="hidden" name="check_in" value="<?php echo esc_attr( $check_in ); ?>">
				<input type="hidden" name="check_out" value="<?php echo esc_attr( $check_out ); ?>">
				<input type="hidden" name="adults" value="<?php echo esc_attr( (string) $adults ); ?>">
				<input type="hidden" name="children" value="<?php echo esc_attr( (string) $children ); ?>">
				<input type="hidden" name="rooms" value="<?php echo esc_attr( (string) $rooms ); ?>">

				<?php if ( ! empty( $available_extras ) ) : ?>
					<h3 style="font-size:18px; font-weight:600; margin-bottom:12px; color:#2d3748;"><?php esc_html_e( 'Optional Extras & Add-ons', 'wpcalibrate-stayreserve' ); ?></h3>

					<div style="display:flex; flex-direction:column; gap:12px; margin-bottom:28px;">
						<?php foreach ( $available_extras as $extra ) : ?>
							<?php $price_label = MoneyUtil::format_minor( (int) $extra['price'] ) . ' (' . ucfirst( str_replace( '_', ' ', $extra['pricing_type'] ) ) . ')'; ?>
							<label style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border:1px solid #cbd5e0; border-radius:8px; cursor:pointer;">
								<div>
									<strong><?php echo esc_html( $extra['name'] ); ?></strong>
									<?php if ( ! empty( $extra['description'] ) ) : ?>
										<div style="font-size:12px; color:#718096;"><?php echo esc_html( $extra['description'] ); ?></div>
									<?php endif; ?>
								</div>
								<div style="display:flex; align-items:center; gap:12px;">
									<span style="font-weight:600; color:#0073aa;"><?php echo esc_html( $price_label ); ?></span>
									<input type="checkbox" name="extras[<?php echo esc_attr( (string) $extra['id'] ); ?>]" value="1" style="width:18px; height:18px;">
								</div>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<!-- Step 4 Guest Information Fields -->
				<h3 style="font-size:18px; font-weight:600; margin-bottom:12px; color:#2d3748;"><?php esc_html_e( 'Primary Guest Contact Details', 'wpcalibrate-stayreserve' ); ?></h3>

				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
					<div>
						<label for="cust_fn" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'First Name', 'wpcalibrate-stayreserve' ); ?> *</label>
						<input type="text" id="cust_fn" name="customer_first_name" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
					</div>
					<div>
						<label for="cust_ln" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Last Name', 'wpcalibrate-stayreserve' ); ?> *</label>
						<input type="text" id="cust_ln" name="customer_last_name" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
					</div>
				</div>

				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
					<div>
						<label for="cust_em" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Email Address', 'wpcalibrate-stayreserve' ); ?> *</label>
						<input type="email" id="cust_em" name="customer_email" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
					</div>
					<div>
						<label for="cust_ph" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">
							<?php esc_html_e( 'Phone Number', 'wpcalibrate-stayreserve' ); ?>
							<?php echo SettingsManager::get( 'guest', 'require_phone', true ) ? '*' : ''; ?>
						</label>
						<input type="tel" id="cust_ph" name="customer_phone" <?php echo SettingsManager::get( 'guest', 'require_phone', true ) ? 'required' : ''; ?> style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;">
					</div>
				</div>

				<div style="margin-bottom:20px;">
					<label for="cust_note" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Special Requests / Notes', 'wpcalibrate-stayreserve' ); ?></label>
					<textarea id="cust_note" name="customer_note" rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px;"></textarea>
				</div>

				<!-- Promo code -->
				<div style="margin-bottom:28px;">
					<label for="wpcsr_promo_code" style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;"><?php esc_html_e( 'Promotional / Coupon Code', 'wpcalibrate-stayreserve' ); ?></label>
					<input type="text" id="wpcsr_promo_code" name="coupon_code" placeholder="Optional" style="width:200px; padding:8px 12px; border:1px solid #cbd5e0; border-radius:6px; text-transform:uppercase;">
				</div>

				<div style="display:flex; justify-content:space-between; align-items:center;">
					<a href="<?php echo esc_url( remove_query_arg( array( 'wpcsr_step', 'room_type_id' ) ) ); ?>" style="color:#718096; text-decoration:none; font-size:14px;">
						&larr; <?php esc_html_e( 'Change Dates', 'wpcalibrate-stayreserve' ); ?>
					</a>
					<button type="submit" class="wpcsr-btn-primary" style="background:#0073aa; color:#fff; border:none; padding:12px 28px; border-radius:6px; font-size:16px; font-weight:600; cursor:pointer;">
						<?php esc_html_e( 'Continue to Review &rarr;', 'wpcalibrate-stayreserve' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Step 5: Review & Price Calculation.
	 *
	 * @return void
	 */
	private function render_booking_review(): void {
		$room_type_id   = (int) ( $_POST['room_type_id'] ?? 0 );
		$check_in       = DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_POST['check_in'] ?? '' ) ) );
		$check_out      = DateTimeUtil::format_date( sanitize_text_field( wp_unslash( $_POST['check_out'] ?? '' ) ) );
		$adults         = max( 1, (int) ( $_POST['adults'] ?? 2 ) );
		$children       = max( 0, (int) ( $_POST['children'] ?? 0 ) );
		$rooms          = max( 1, (int) ( $_POST['rooms'] ?? 1 ) );
		$selected_extras= (array) ( $_POST['extras'] ?? array() );
		$coupon_code    = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );

		$first_name     = sanitize_text_field( wp_unslash( $_POST['customer_first_name'] ?? '' ) );
		$last_name      = sanitize_text_field( wp_unslash( $_POST['customer_last_name'] ?? '' ) );
		$email          = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$phone          = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
		$note           = sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) );

		// Authoritative server-side price calculation
		$quote = $this->pricing->calculate_quote(
			$room_type_id,
			$check_in,
			$check_out,
			$rooms,
			$adults,
			$children,
			$selected_extras,
			$coupon_code ?: null
		);

		$post = get_post( $room_type_id );
		?>
		<div style="max-width:800px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px; box-shadow:0 4px 16px rgba(0,0,0,0.04);">
			<h2 style="margin-top:0; font-size:24px; font-weight:700; color:#1a202c;"><?php esc_html_e( 'Review Your Reservation', 'wpcalibrate-stayreserve' ); ?></h2>

			<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin:24px 0;">
				<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px;">
					<h4 style="margin-top:0; margin-bottom:12px; font-size:16px; font-weight:600;"><?php esc_html_e( 'Stay Details', 'wpcalibrate-stayreserve' ); ?></h4>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Room:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $post ? $post->post_title : '' ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Check-in:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $check_in ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Check-out:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $check_out ); ?> (<?php echo esc_html( (string) $quote['nights'] ); ?> nights)</p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Guests:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( "{$adults} Adults, {$children} Children" ); ?></p>
				</div>

				<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px;">
					<h4 style="margin-top:0; margin-bottom:12px; font-size:16px; font-weight:600;"><?php esc_html_e( 'Guest Details', 'wpcalibrate-stayreserve' ); ?></h4>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Name:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( "{$first_name} {$last_name}" ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Email:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $email ); ?></p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Phone:', 'wpcalibrate-stayreserve' ); ?></strong> <?php echo esc_html( $phone ?: '—' ); ?></p>
				</div>
			</div>

			<!-- Price Breakdown -->
			<div style="border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:28px;">
				<h4 style="margin-top:0; margin-bottom:16px; font-size:16px; font-weight:600;"><?php esc_html_e( 'Price Summary', 'wpcalibrate-stayreserve' ); ?></h4>

				<div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px;">
					<span><?php esc_html_e( 'Accommodation Subtotal', 'wpcalibrate-stayreserve' ); ?>:</span>
					<strong><?php echo esc_html( MoneyUtil::format_minor( $quote['accommodation_subtotal'], $quote['currency'] ) ); ?></strong>
				</div>

				<?php if ( ! empty( $quote['extras'] ) ) : ?>
					<?php foreach ( $quote['extras'] as $ex_item ) : ?>
						<div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; color:#4a5568;">
							<span>+ <?php echo esc_html( $ex_item['name'] ); ?>:</span>
							<strong><?php echo esc_html( MoneyUtil::format_minor( $ex_item['total_price'], $quote['currency'] ) ); ?></strong>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( $quote['discount_total'] > 0 ) : ?>
					<div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; color:#b32d2e;">
						<span>- <?php esc_html_e( 'Discount Applied', 'wpcalibrate-stayreserve' ); ?> (<?php echo esc_html( $quote['coupon']['code'] ?? '' ); ?>):</span>
						<strong>- <?php echo esc_html( MoneyUtil::format_minor( $quote['discount_total'], $quote['currency'] ) ); ?></strong>
					</div>
				<?php endif; ?>

				<?php if ( $quote['tax_total'] > 0 ) : ?>
					<div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px;">
						<span>+ <?php esc_html_e( 'Taxes', 'wpcalibrate-stayreserve' ); ?>:</span>
						<strong><?php echo esc_html( MoneyUtil::format_minor( $quote['tax_total'], $quote['currency'] ) ); ?></strong>
					</div>
				<?php endif; ?>

				<div style="display:flex; justify-content:space-between; margin-top:16px; padding-top:16px; border-top:2px solid #e2e8f0; font-size:18px;">
					<span><strong><?php esc_html_e( 'Total Amount Due', 'wpcalibrate-stayreserve' ); ?>:</strong></span>
					<span style="color:#0073aa; font-weight:800;"><?php echo esc_html( MoneyUtil::format_minor( $quote['grand_total'], $quote['currency'] ) ); ?></span>
				</div>
			</div>

			<!-- Final Submission Form -->
			<form method="post" action="<?php echo esc_url( add_query_arg( 'wpcsr_step', 'confirm' ) ); ?>">
				<?php wp_nonce_field( 'wpcsr_submit_reservation', 'wpcsr_res_nonce' ); ?>
				<input type="hidden" name="wpcsr_step" value="confirm">
				<input type="hidden" name="room_type_id" value="<?php echo esc_attr( (string) $room_type_id ); ?>">
				<input type="hidden" name="check_in" value="<?php echo esc_attr( $check_in ); ?>">
				<input type="hidden" name="check_out" value="<?php echo esc_attr( $check_out ); ?>">
				<input type="hidden" name="adults" value="<?php echo esc_attr( (string) $adults ); ?>">
				<input type="hidden" name="children" value="<?php echo esc_attr( (string) $children ); ?>">
				<input type="hidden" name="rooms" value="<?php echo esc_attr( (string) $rooms ); ?>">
				<input type="hidden" name="customer_first_name" value="<?php echo esc_attr( $first_name ); ?>">
				<input type="hidden" name="customer_last_name" value="<?php echo esc_attr( $last_name ); ?>">
				<input type="hidden" name="customer_email" value="<?php echo esc_attr( $email ); ?>">
				<input type="hidden" name="customer_phone" value="<?php echo esc_attr( $phone ); ?>">
				<input type="hidden" name="customer_note" value="<?php echo esc_attr( $note ); ?>">
				<input type="hidden" name="coupon_code" value="<?php echo esc_attr( $coupon_code ); ?>">
				<?php foreach ( $selected_extras as $eid => $eqty ) : ?>
					<input type="hidden" name="extras[<?php echo esc_attr( (string) $eid ); ?>]" value="1">
				<?php endforeach; ?>

				<div style="margin-bottom:24px;">
					<label style="font-size:14px; cursor:pointer;">
						<input type="checkbox" name="agree_terms" value="1" required>
						<?php esc_html_e( 'I have read and agree to the booking terms and accommodation cancellation policy.', 'wpcalibrate-stayreserve' ); ?>
					</label>
				</div>

				<button type="submit" class="wpcsr-btn-primary" style="width:100%; background:#0073aa; color:#fff; border:none; padding:16px; border-radius:8px; font-size:18px; font-weight:700; cursor:pointer;">
					<?php esc_html_e( 'Confirm Reservation Now', 'wpcalibrate-stayreserve' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Process final reservation submission.
	 *
	 * @return void
	 */
	private function process_reservation_submission(): void {
		check_admin_referer( 'wpcsr_submit_reservation', 'wpcsr_res_nonce' );

		try {
			$booking = $this->checkout->complete_checkout(
				array(
					'room_type_id'        => (int) sanitize_text_field( wp_unslash( $_POST['room_type_id'] ?? 0 ) ),
					'check_in'            => sanitize_text_field( wp_unslash( $_POST['check_in'] ?? '' ) ),
					'check_out'           => sanitize_text_field( wp_unslash( $_POST['check_out'] ?? '' ) ),
					'adults'              => (int) sanitize_text_field( wp_unslash( $_POST['adults'] ?? 1 ) ),
					'children'            => (int) sanitize_text_field( wp_unslash( $_POST['children'] ?? 0 ) ),
					'rooms'               => (int) sanitize_text_field( wp_unslash( $_POST['rooms'] ?? 1 ) ),
					'customer_first_name' => sanitize_text_field( wp_unslash( $_POST['customer_first_name'] ?? '' ) ),
					'customer_last_name'  => sanitize_text_field( wp_unslash( $_POST['customer_last_name'] ?? '' ) ),
					'customer_email'      => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
					'customer_phone'      => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
					'customer_note'       => sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) ),
					'extras'              => (array) ( $_POST['extras'] ?? array() ),
					'coupon_code'         => sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) ),
				)
			);

			// Retrieve the raw token from transient for the immediate redirect
			$raw_token = get_transient( 'wpcsr_raw_token_' . $booking->id );

			$redirect_url = add_query_arg(
				array(
					'wpcsr_step' => 'complete',
					'ref'        => $booking->public_reference,
					'token'      => $raw_token ?: '',
				)
			);

			wp_safe_redirect( $redirect_url );
			exit;
		} catch ( \Throwable $e ) {
			echo '<div class="wpcsr-error-box" style="max-width:800px;margin:20px auto;padding:20px;background:#fef2f2;border-left:4px solid #ef4444;border-radius:4px;color:#991b1b;">'
				. '<strong>' . esc_html__( 'Reservation Error:', 'wpcalibrate-stayreserve' ) . '</strong> '
				. esc_html( $e->getMessage() )
				. '<br><a href="javascript:history.back()" style="display:inline-block;margin-top:12px;color:#991b1b;text-decoration:underline;">&larr; ' . esc_html__( 'Go back and choose another option', 'wpcalibrate-stayreserve' ) . '</a>'
				. '</div>';
		}
	}

	/**
	 * Step 7: Confirmation Page.
	 *
	 * @return void
	 */
	private function render_booking_confirmation(): void {
		$ref = sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) );
		/** @var \WPCalibrate\StayReserve\Booking\BookingRepository $repo */
		$repo = \WPCalibrate\StayReserve\Plugin::instance()->get_service( 'booking_repo' );
		$booking = $repo ? $repo->get_by_reference( $ref ) : null;

		if ( ! $booking ) {
			echo '<p>' . esc_html__( 'Booking confirmation details unavailable.', 'wpcalibrate-stayreserve' ) . '</p>';
			return;
		}

		$raw_token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$manage_url = home_url( '/manage-booking/' );
		if ( ! empty( $raw_token ) ) {
			$manage_url = add_query_arg(
				array(
					'ref'   => $booking->public_reference,
					'token' => $raw_token,
				),
				$manage_url
			);
		}
		?>
		<div style="max-width:700px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px; text-align:center; box-shadow:0 4px 16px rgba(0,0,0,0.04);">
			<div style="width:64px; height:64px; background:#e8f5e9; color:#2e7d32; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:16px;">
				&#10003;
			</div>
			<h2 style="margin:0 0 8px; font-size:26px; font-weight:700; color:#1a202c;"><?php esc_html_e( 'Reservation Confirmed!', 'wpcalibrate-stayreserve' ); ?></h2>
			<p style="color:#718096; margin:0 0 24px; font-size:15px;"><?php esc_html_e( 'Thank you for your booking. A confirmation email has been dispatched to your email address.', 'wpcalibrate-stayreserve' ); ?></p>

			<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px; text-align:left; margin-bottom:28px;">
				<div style="display:flex; justify-content:space-between; margin-bottom:8px;">
					<span style="color:#718096;"><?php esc_html_e( 'Booking Reference:', 'wpcalibrate-stayreserve' ); ?></span>
					<strong style="color:#0073aa; font-size:16px;"><?php echo esc_html( $booking->public_reference ); ?></strong>
				</div>
				<div style="display:flex; justify-content:space-between; margin-bottom:8px;">
					<span style="color:#718096;"><?php esc_html_e( 'Dates:', 'wpcalibrate-stayreserve' ); ?></span>
					<strong><?php echo esc_html( $booking->check_in . ' to ' . $booking->check_out . ' (' . $booking->get_nights() . ' nights)' ); ?></strong>
				</div>
				<div style="display:flex; justify-content:space-between; margin-bottom:8px;">
					<span style="color:#718096;"><?php esc_html_e( 'Lead Guest:', 'wpcalibrate-stayreserve' ); ?></span>
					<strong><?php echo esc_html( $booking->get_customer_name() ); ?></strong>
				</div>
				<div style="display:flex; justify-content:space-between; margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px; font-size:16px;">
					<span><strong><?php esc_html_e( 'Total Amount:', 'wpcalibrate-stayreserve' ); ?></strong></span>
					<strong style="color:#2e7d32;"><?php echo esc_html( $booking->get_formatted_total() ); ?></strong>
				</div>
			</div>

			<div style="background:#fff8e5; border-left:4px solid #f0b849; padding:16px; text-align:left; border-radius:4px; font-size:14px; margin-bottom:28px;">
				<strong><?php esc_html_e( 'Next Steps:', 'wpcalibrate-stayreserve' ); ?></strong><br>
				<?php echo esc_html( (string) SettingsManager::get( 'general', 'offline_instructions' ) ); ?>
			</div>

			<p>
				<a href="<?php echo esc_url( $manage_url ); ?>" class="wpcsr-btn-primary" style="display:inline-block; background:#0073aa; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-weight:600;">
					<?php esc_html_e( 'Manage Your Booking Online', 'wpcalibrate-stayreserve' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
