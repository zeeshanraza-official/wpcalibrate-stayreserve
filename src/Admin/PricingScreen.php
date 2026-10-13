<?php
/**
 * Pricing and Extras Admin Management Screen.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Coupons\CouponManager;
use WPCalibrate\StayReserve\Extras\ExtraRepository;
use WPCalibrate\StayReserve\Pricing\RateRuleRepository;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Utilities\MoneyUtil;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class PricingScreen
 */
class PricingScreen {

	private RateRuleRepository $rate_rules;
	private ExtraRepository $extras_repo;
	private CouponManager $coupon_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		RateRuleRepository $rate_rules,
		ExtraRepository $extras_repo,
		CouponManager $coupon_manager
	) {
		$this->rate_rules     = $rate_rules;
		$this->extras_repo    = $extras_repo;
		$this->coupon_manager = $coupon_manager;
	}

	/**
	 * Render screen with tabs for Rate Rules, Extras, and Coupons.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_RATES ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'rules';
		$action  = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		// Handle actions
		if ( 'delete_rule' === $action && ! empty( $_GET['rule_id'] ) ) {
			$rule_id = (int) $_GET['rule_id'];
			check_admin_referer( 'wpcsr_delete_rule_' . $rule_id );
			$this->rate_rules->delete( $rule_id );
			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=rules&deleted=1' ) );
			exit;
		}

		if ( 'delete_extra' === $action && ! empty( $_GET['extra_id'] ) ) {
			$extra_id = (int) $_GET['extra_id'];
			check_admin_referer( 'wpcsr_delete_extra_' . $extra_id );
			$this->extras_repo->delete( $extra_id );
			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=extras&deleted=1' ) );
			exit;
		}

		if ( 'delete_coupon' === $action && ! empty( $_GET['code'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			check_admin_referer( 'wpcsr_delete_coupon_' . $code );
			$this->coupon_manager->delete( $code );
			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=coupons&deleted=1' ) );
			exit;
		}

		// Handle Form Submissions
		if ( ! empty( $_POST ) ) {
			if ( 'save_rule' === $action ) {
				$this->handle_save_rule();
				return;
			}
			if ( 'save_extra' === $action ) {
				$this->handle_save_extra();
				return;
			}
			if ( 'save_coupon' === $action ) {
				$this->handle_save_coupon();
				return;
			}
		}

		$base_url = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing' );
		?>
		<div>
			<ul class="subsubsub" style="margin-bottom:16px;">
				<li><a href="<?php echo esc_url( add_query_arg( 'section', 'rules', $base_url ) ); ?>" class="<?php echo 'rules' === $section ? 'current' : ''; ?>"><?php esc_html_e( 'Rate Rules (Seasonal & Day-of-Week)', 'wpcalibrate-stayreserve' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( add_query_arg( 'section', 'extras', $base_url ) ); ?>" class="<?php echo 'extras' === $section ? 'current' : ''; ?>"><?php esc_html_e( 'Extras & Add-ons', 'wpcalibrate-stayreserve' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( add_query_arg( 'section', 'coupons', $base_url ) ); ?>" class="<?php echo 'coupons' === $section ? 'current' : ''; ?>"><?php esc_html_e( 'Coupons & Promos', 'wpcalibrate-stayreserve' ); ?></a></li>
			</ul>
			<div class="clear"></div>

			<?php
			if ( 'extras' === $section ) {
				$this->render_extras_section();
			} elseif ( 'coupons' === $section ) {
				$this->render_coupons_section();
			} else {
				$this->render_rules_section();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Rate Rules section.
	 *
	 * @return void
	 */
	private function render_rules_section(): void {
		$rules = $this->rate_rules->get_all();
		$room_types = get_posts(
			array(
				'post_type'      => RoomTypePostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		?>
		<div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
			<div class="postbox" style="padding:16px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Add Rate Rule', 'wpcalibrate-stayreserve' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=rules&action=save_rule' ) ); ?>">
					<?php wp_nonce_field( 'wpcsr_save_rate_rule', 'wpcsr_rule_nonce' ); ?>
					<p>
						<label for="rule_room_type"><strong><?php esc_html_e( 'Target Accommodation:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<select name="room_type_id" id="rule_room_type" required style="width:100%;margin-top:4px;">
							<option value=""><?php esc_html_e( '— Select Room Type —', 'wpcalibrate-stayreserve' ); ?></option>
							<?php foreach ( $room_types as $rt ) : ?>
								<option value="<?php echo esc_attr( (string) $rt->ID ); ?>"><?php echo esc_html( $rt->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="rule_name"><strong><?php esc_html_e( 'Rule Name:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="text" name="name" id="rule_name" required style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'e.g. Summer Season or Weekend Surcharge', 'wpcalibrate-stayreserve' ); ?>">
					</p>
					<p>
						<label for="rule_type"><strong><?php esc_html_e( 'Rule Type:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<select name="rule_type" id="rule_type" style="width:100%;margin-top:4px;">
							<option value="season"><?php esc_html_e( 'Seasonal / Date Range', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="date_override"><?php esc_html_e( 'Specific Date Override', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="weekend"><?php esc_html_e( 'Weekend (Fri/Sat)', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="day_of_week"><?php esc_html_e( 'Specific Day of Week', 'wpcalibrate-stayreserve' ); ?></option>
						</select>
					</p>
					<p>
						<label for="rule_start"><strong><?php esc_html_e( 'Start Date & End Date:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<input type="date" name="start_date" id="rule_start" style="width:48%;">
						<input type="date" name="end_date" id="rule_end" style="width:48%;">
					</p>
					<p>
						<label for="adjustment_type"><strong><?php esc_html_e( 'Adjustment Type:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<select name="adjustment_type" id="adjustment_type" style="width:100%;margin-top:4px;">
							<option value="override_price"><?php esc_html_e( 'Override Fixed Price', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="fixed"><?php esc_html_e( 'Add / Subtract Fixed Amount (+/-)', 'wpcalibrate-stayreserve' ); ?></option>
						</select>
					</p>
					<p>
						<label for="amount"><strong><?php esc_html_e( 'Amount ($):', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="number" step="0.01" name="amount" id="amount" required style="width:100%;margin-top:4px;" placeholder="0.00">
					</p>
					<p>
						<label for="priority"><strong><?php esc_html_e( 'Priority (Lower number = higher priority):', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<input type="number" name="priority" id="priority" value="10" style="width:100%;margin-top:4px;">
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Rate Rule', 'wpcalibrate-stayreserve' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rule Name', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Room Type', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Type', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Dates / Days', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rules ) ) : ?>
							<tr><td colspan="6" style="padding:16px;text-align:center;color:#646970;"><?php esc_html_e( 'No rate rules created yet. Base room prices will apply.', 'wpcalibrate-stayreserve' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $rules as $r ) : ?>
								<?php
								$rt_post = get_post( (int) $r['room_type_id'] );
								$delete_url = wp_nonce_url(
									admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=rules&action=delete_rule&rule_id=' . $r['id'] ),
									'wpcsr_delete_rule_' . $r['id']
								);
								?>
								<tr>
									<td><strong><?php echo esc_html( $r['name'] ); ?></strong></td>
									<td><?php echo esc_html( $rt_post ? $rt_post->post_title : '—' ); ?></td>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $r['rule_type'] ) ) ); ?></td>
									<td><?php echo esc_html( $r['start_date'] ? $r['start_date'] . ' to ' . $r['end_date'] : 'All days' ); ?></td>
									<td><?php echo esc_html( MoneyUtil::format_minor( (int) $r['amount'] ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Delete this rate rule?', 'wpcalibrate-stayreserve' ); ?>');" style="color:#b32d2e;">
											<?php esc_html_e( 'Delete', 'wpcalibrate-stayreserve' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Extras section.
	 *
	 * @return void
	 */
	private function render_extras_section(): void {
		$extras = $this->extras_repo->get_available_for_room( null );
		?>
		<div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
			<div class="postbox" style="padding:16px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Add Extra / Add-on', 'wpcalibrate-stayreserve' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=extras&action=save_extra' ) ); ?>">
					<?php wp_nonce_field( 'wpcsr_save_extra', 'wpcsr_extra_nonce' ); ?>
					<p>
						<label for="extra_name"><strong><?php esc_html_e( 'Extra Name:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="text" name="name" id="extra_name" required style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'e.g. Daily Breakfast or Airport Transfer', 'wpcalibrate-stayreserve' ); ?>">
					</p>
					<p>
						<label for="extra_price"><strong><?php esc_html_e( 'Price ($):', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="number" step="0.01" name="price" id="extra_price" required style="width:100%;margin-top:4px;" placeholder="15.00">
					</p>
					<p>
						<label for="pricing_type"><strong><?php esc_html_e( 'Pricing Structure:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<select name="pricing_type" id="pricing_type" style="width:100%;margin-top:4px;">
							<option value="per_stay"><?php esc_html_e( 'Per Stay (One-off)', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="per_night"><?php esc_html_e( 'Per Night', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="per_guest"><?php esc_html_e( 'Per Guest', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="per_guest_per_night"><?php esc_html_e( 'Per Guest / Per Night', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="quantity_based"><?php esc_html_e( 'Quantity-based Custom Multiplier', 'wpcalibrate-stayreserve' ); ?></option>
						</select>
					</p>
					<p>
						<label for="extra_desc"><strong><?php esc_html_e( 'Description:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<textarea name="description" id="extra_desc" rows="3" style="width:100%;margin-top:4px;"></textarea>
					</p>
					<p>
						<label><input type="checkbox" name="is_taxable" value="1" checked> <?php esc_html_e( 'Subject to accommodation tax', 'wpcalibrate-stayreserve' ); ?></label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Extra Service', 'wpcalibrate-stayreserve' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Extra Name', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Pricing Type', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Price', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $extras ) ) : ?>
							<tr><td colspan="4" style="padding:16px;text-align:center;color:#646970;"><?php esc_html_e( 'No extras configured yet.', 'wpcalibrate-stayreserve' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $extras as $ex ) : ?>
								<?php
								$delete_url = wp_nonce_url(
									admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=extras&action=delete_extra&extra_id=' . $ex['id'] ),
									'wpcsr_delete_extra_' . $ex['id']
								);
								?>
								<tr>
									<td><strong><?php echo esc_html( $ex['name'] ); ?></strong></td>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $ex['pricing_type'] ) ) ); ?></td>
									<td><?php echo esc_html( MoneyUtil::format_minor( (int) $ex['price'] ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Delete this extra?', 'wpcalibrate-stayreserve' ); ?>');" style="color:#b32d2e;">
											<?php esc_html_e( 'Delete', 'wpcalibrate-stayreserve' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Coupons section.
	 *
	 * @return void
	 */
	private function render_coupons_section(): void {
		$coupons = $this->coupon_manager->get_all();
		?>
		<div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
			<div class="postbox" style="padding:16px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php esc_html_e( 'Add Coupon Code', 'wpcalibrate-stayreserve' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=coupons&action=save_coupon' ) ); ?>">
					<?php wp_nonce_field( 'wpcsr_save_coupon', 'wpcsr_coupon_nonce' ); ?>
					<p>
						<label for="coupon_code"><strong><?php esc_html_e( 'Coupon Code:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="text" name="code" id="coupon_code" required style="width:100%;margin-top:4px;text-transform:uppercase;" placeholder="SUMMER20">
					</p>
					<p>
						<label for="coupon_type"><strong><?php esc_html_e( 'Discount Type:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<select name="type" id="coupon_type" style="width:100%;margin-top:4px;">
							<option value="percentage"><?php esc_html_e( 'Percentage (%)', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="fixed"><?php esc_html_e( 'Fixed Amount ($)', 'wpcalibrate-stayreserve' ); ?></option>
						</select>
					</p>
					<p>
						<label for="coupon_amount"><strong><?php esc_html_e( 'Discount Value:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="number" step="0.01" name="amount" id="coupon_amount" required style="width:100%;margin-top:4px;" placeholder="10">
						<span class="description"><?php esc_html_e( 'Enter e.g. 10 for 10% or 25.00 for $25.', 'wpcalibrate-stayreserve' ); ?></span>
					</p>
					<p>
						<label for="coupon_min_nights"><strong><?php esc_html_e( 'Minimum Stay (Nights):', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<input type="number" name="min_nights" id="coupon_min_nights" value="0" style="width:100%;margin-top:4px;">
					</p>
					<p>
						<label for="coupon_expiry"><strong><?php esc_html_e( 'Expiration Date:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<input type="date" name="expiry_date" id="coupon_expiry" style="width:100%;margin-top:4px;">
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Coupon', 'wpcalibrate-stayreserve' ); ?></button>
					</p>
				</form>
			</div>

			<div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Type', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Discount', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Usage Count', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $coupons ) ) : ?>
							<tr><td colspan="5" style="padding:16px;text-align:center;color:#646970;"><?php esc_html_e( 'No promo codes registered.', 'wpcalibrate-stayreserve' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $coupons as $c ) : ?>
								<?php
								$delete_url = wp_nonce_url(
									admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=coupons&action=delete_coupon&code=' . $c['code'] ),
									'wpcsr_delete_coupon_' . $c['code']
								);
								?>
								<tr>
									<td><strong><?php echo esc_html( $c['code'] ); ?></strong></td>
									<td><?php echo esc_html( ucfirst( $c['type'] ) ); ?></td>
									<td><?php echo 'percentage' === $c['type'] ? esc_html( $c['amount'] . '%' ) : esc_html( MoneyUtil::format_minor( (int) $c['amount'] ) ); ?></td>
									<td><?php echo esc_html( (string) $c['usage_count'] ); ?></td>
									<td>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Delete this coupon?', 'wpcalibrate-stayreserve' ); ?>');" style="color:#b32d2e;">
											<?php esc_html_e( 'Delete', 'wpcalibrate-stayreserve' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function handle_save_rule(): void {
		check_admin_referer( 'wpcsr_save_rate_rule', 'wpcsr_rule_nonce' );
		$amount_dec = (float) sanitize_text_field( wp_unslash( $_POST['amount'] ?? 0 ) );
		$this->rate_rules->create(
			array(
				'room_type_id'    => (int) sanitize_text_field( wp_unslash( $_POST['room_type_id'] ?? 0 ) ),
				'name'            => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'rule_type'       => sanitize_text_field( wp_unslash( $_POST['rule_type'] ?? 'season' ) ),
				'start_date'      => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
				'end_date'        => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
				'adjustment_type' => sanitize_text_field( wp_unslash( $_POST['adjustment_type'] ?? 'fixed' ) ),
				'amount'          => MoneyUtil::decimal_to_minor( $amount_dec ),
				'priority'        => (int) sanitize_text_field( wp_unslash( $_POST['priority'] ?? 10 ) ),
				'enabled'         => 1,
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=rules&saved=1' ) );
		exit;
	}

	private function handle_save_extra(): void {
		check_admin_referer( 'wpcsr_save_extra', 'wpcsr_extra_nonce' );
		$price_dec = (float) sanitize_text_field( wp_unslash( $_POST['price'] ?? 0 ) );
		$this->extras_repo->create(
			array(
				'name'         => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
				'price'        => MoneyUtil::decimal_to_minor( $price_dec ),
				'pricing_type' => sanitize_text_field( wp_unslash( $_POST['pricing_type'] ?? 'per_stay' ) ),
				'is_taxable'   => ! empty( $_POST['is_taxable'] ),
				'enabled'      => 1,
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=extras&saved=1' ) );
		exit;
	}

	private function handle_save_coupon(): void {
		check_admin_referer( 'wpcsr_save_coupon', 'wpcsr_coupon_nonce' );
		$type   = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'percentage' ) );
		$amount = (float) sanitize_text_field( wp_unslash( $_POST['amount'] ?? 0 ) );
		$val    = 'percentage' === $type ? (int) $amount : MoneyUtil::decimal_to_minor( $amount );

		$this->coupon_manager->save(
			array(
				'code'        => sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ),
				'type'        => $type,
				'amount'      => $val,
				'min_nights'  => (int) sanitize_text_field( wp_unslash( $_POST['min_nights'] ?? 0 ) ),
				'expiry_date' => sanitize_text_field( wp_unslash( $_POST['expiry_date'] ?? '' ) ),
				'enabled'     => true,
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=pricing&section=coupons&saved=1' ) );
		exit;
	}
}
