<?php
/**
 * Admin Calendar Screen for visual room/unit occupancy.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Availability\AvailabilityEngine;
use WPCalibrate\StayReserve\Booking\BookingRepository;
use WPCalibrate\StayReserve\Booking\BookingStatus;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Rooms\RoomUnitRepository;
use WPCalibrate\StayReserve\Utilities\DateTimeUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class CalendarScreen
 */
class CalendarScreen {

	private AvailabilityEngine $availability;
	private BookingRepository $bookings;

	/**
	 * Constructor.
	 */
	public function __construct( AvailabilityEngine $availability, BookingRepository $bookings ) {
		$this->availability = $availability;
		$this->bookings     = $bookings;
	}

	/**
	 * Render the visual booking calendar.
	 *
	 * @return void
	 */
	public function render(): void {
		$year  = isset( $_GET['cal_year'] ) ? (int) $_GET['cal_year'] : (int) date( 'Y' );
		$month = isset( $_GET['cal_month'] ) ? (int) $_GET['cal_month'] : (int) date( 'n' );

		if ( $month < 1 ) {
			$month = 12;
			$year--;
		} elseif ( $month > 12 ) {
			$month = 1;
			$year++;
		}

		$first_day = sprintf( '%04d-%02d-01', $year, $month );
		$num_days  = (int) date( 't', strtotime( $first_day ) );
		$last_day  = sprintf( '%04d-%02d-%02d', $year, $month, $num_days );

		// Navigation dates
		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) {
			$prev_month = 12;
			$prev_year--;
		}

		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) {
			$next_month = 1;
			$next_year++;
		}

		$base_url = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=calendar' );
		$prev_url = add_query_arg( array( 'cal_year' => $prev_year, 'cal_month' => $prev_month ), $base_url );
		$next_url = add_query_arg( array( 'cal_year' => $next_year, 'cal_month' => $next_month ), $base_url );

		// Load room units
		$unit_repo  = new RoomUnitRepository();
		$room_units = $unit_repo->get_all( array( 'per_page' => 50 ) );

		// Query bookings overlapping this month
		$month_bookings = $this->bookings->query(
			array(
				'check_in_from' => date( 'Y-m-d', strtotime( '-30 days', strtotime( $first_day ) ) ),
				'check_in_to'   => $last_day,
				'per_page'      => 100,
			)
		);
		?>
		<div class="wpcsr-calendar-wrap">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
				<h2 style="margin:0;">
					<?php echo esc_html( date( 'F Y', strtotime( $first_day ) ) ); ?>
				</h2>
				<div class="button-group">
					<a href="<?php echo esc_url( $prev_url ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous Month', 'wpcalibrate-stayreserve' ); ?></a>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Today', 'wpcalibrate-stayreserve' ); ?></a>
					<a href="<?php echo esc_url( $next_url ); ?>" class="button"><?php esc_html_e( 'Next Month', 'wpcalibrate-stayreserve' ); ?> &raquo;</a>
				</div>
			</div>

			<!-- Accessible Legend -->
			<div style="display:flex; gap:16px; margin-bottom:16px; font-size:13px; align-items:center;" role="region" aria-label="<?php esc_attr_e( 'Calendar Status Legend', 'wpcalibrate-stayreserve' ); ?>">
				<span style="font-weight:600;"><?php esc_html_e( 'Legend:', 'wpcalibrate-stayreserve' ); ?></span>
				<span><span class="wpcsr-status-dot status-confirmed" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#2e7d32;margin-right:4px;"></span> [C] <?php esc_html_e( 'Confirmed', 'wpcalibrate-stayreserve' ); ?></span>
				<span><span class="wpcsr-status-dot status-pending" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#e65100;margin-right:4px;"></span> [P] <?php esc_html_e( 'Pending/Hold', 'wpcalibrate-stayreserve' ); ?></span>
				<span><span class="wpcsr-status-dot status-blocked" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#616161;margin-right:4px;"></span> [B] <?php esc_html_e( 'Blocked/Maintenance', 'wpcalibrate-stayreserve' ); ?></span>
				<span><span class="wpcsr-status-dot status-free" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ffffff;border:1px solid #ccc;margin-right:4px;"></span> [Free] <?php esc_html_e( 'Available', 'wpcalibrate-stayreserve' ); ?></span>
			</div>

			<!-- Occupancy Grid / Timeline -->
			<div style="overflow-x:auto; border:1px solid #ccd0d4; background:#fff;">
				<table class="wpcsr-calendar-table widefat fixed" style="border-collapse:collapse; min-width:800px;">
					<thead>
						<tr style="background:#f6f7f7;">
							<th style="width:160px; padding:10px; border-right:1px solid #ccd0d4;"><?php esc_html_e( 'Unit / Room', 'wpcalibrate-stayreserve' ); ?></th>
							<?php for ( $d = 1; $d <= $num_days; $d++ ) : ?>
								<?php
								$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
								$day_name = substr( date( 'D', strtotime( $date_str ) ), 0, 1 );
								$is_weekend = in_array( date( 'w', strtotime( $date_str ) ), array( '0', '6' ), true );
								?>
								<th style="padding:6px 2px; text-align:center; font-size:11px; border-right:1px solid #e2e4e7; <?php echo $is_weekend ? 'background:#edf2f7;' : ''; ?>">
									<?php echo esc_html( (string) $d ); ?><br>
									<span style="color:#646970;"><?php echo esc_html( $day_name ); ?></span>
								</th>
							<?php endfor; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $room_units ) ) : ?>
							<tr>
								<td colspan="<?php echo esc_attr( (string) ( $num_days + 1 ) ); ?>" style="padding:24px; text-align:center; color:#646970;">
									<?php esc_html_e( 'No physical units configured yet. Please add room units under Physical Units tab.', 'wpcalibrate-stayreserve' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $room_units as $unit ) : ?>
								<?php
								$unit_id   = (int) $unit['id'];
								$unit_name = $unit['unit_name'];
								$type_post = get_post( (int) $unit['room_type_id'] );
								$type_name = $type_post ? $type_post->post_title : '';
								?>
								<tr>
									<td style="padding:8px 10px; border-right:1px solid #ccd0d4; border-top:1px solid #e2e4e7;">
										<strong><?php echo esc_html( $unit_name ); ?></strong>
										<br><span style="color:#646970;font-size:11px;"><?php echo esc_html( $type_name ); ?></span>
									</td>
									<?php for ( $d = 1; $d <= $num_days; $d++ ) : ?>
										<?php
										$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
										$is_weekend = in_array( date( 'w', strtotime( $date_str ) ), array( '0', '6' ), true );

										// Check if unit has reservation on this occupied night
										$matching_booking = null;
										foreach ( $month_bookings as $mb ) {
											if ( ! BookingStatus::is_active( $mb->status ) ) {
												continue;
											}
											if ( $mb->check_in <= $date_str && $mb->check_out > $date_str ) {
												foreach ( $mb->items as $it ) {
													if ( (int) $it['room_unit_id'] === $unit_id ) {
														$matching_booking = $mb;
														break 2;
													}
												}
											}
										}
										?>
										<td style="padding:2px; text-align:center; border-right:1px solid #e2e4e7; border-top:1px solid #e2e4e7; <?php echo $is_weekend ? 'background:#fbfbfb;' : ''; ?>">
											<?php if ( $matching_booking ) : ?>
												<?php
												$is_confirmed = ( 'confirmed' === $matching_booking->status );
												$bg_color     = $is_confirmed ? '#e8f5e9' : '#fff3e0';
												$border_color = $is_confirmed ? '#2e7d32' : '#e65100';
												$text_color   = $is_confirmed ? '#1b5e20' : '#bf360c';
												$label_badge  = $is_confirmed ? '[C]' : '[P]';
												$edit_url     = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=bookings&action=edit&booking_id=' . $matching_booking->id );
												?>
												<a href="<?php echo esc_url( $edit_url ); ?>"
												   title="<?php echo esc_attr( $matching_booking->public_reference . ' - ' . $matching_booking->get_customer_name() ); ?>"
												   style="display:block; background:<?php echo esc_attr( $bg_color ); ?>; border-left:3px solid <?php echo esc_attr( $border_color ); ?>; color:<?php echo esc_attr( $text_color ); ?>; font-size:10px; font-weight:600; padding:4px 0; text-decoration:none; border-radius:2px;">
													<?php echo esc_html( $label_badge ); ?>
												</a>
											<?php else : ?>
												<span style="color:#dcdcde; font-size:10px;">&bull;</span>
											<?php endif; ?>
										</td>
									<?php endfor; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
