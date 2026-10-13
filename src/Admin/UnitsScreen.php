<?php
/**
 * Admin Units Screen for managing physical room units.
 *
 * @package WPCalibrate\StayReserve\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\StayReserve\Admin;

use WPCalibrate\StayReserve\Capabilities\CapabilityManager;
use WPCalibrate\StayReserve\Rooms\RoomTypePostType;
use WPCalibrate\StayReserve\Rooms\RoomUnitRepository;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCSR_TEST_ENV' ) ) {
	exit;
}

/**
 * Class UnitsScreen
 */
class UnitsScreen {

	private RoomUnitRepository $repository;
	private RoomTypePostType $room_type_cpt;

	/**
	 * Constructor.
	 */
	public function __construct( RoomUnitRepository $repository, RoomTypePostType $room_type_cpt ) {
		$this->repository    = $repository;
		$this->room_type_cpt = $room_type_cpt;
	}

	/**
	 * Render and handle unit actions.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! CapabilityManager::can( CapabilityManager::CAP_MANAGE_ROOMS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-stayreserve' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['unit_id'] ) ? (int) $_GET['unit_id'] : 0;

		// Handle Delete
		if ( 'delete' === $action && $id > 0 ) {
			check_admin_referer( 'wpcsr_delete_unit_' . $id );
			$this->repository->delete( $id );
			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&deleted=1' ) );
			exit;
		}

		// Handle Save / Add
		if ( 'save' === $action && ! empty( $_POST ) ) {
			check_admin_referer( 'wpcsr_save_unit', 'wpcsr_unit_nonce' );

			$data = array(
				'room_type_id' => (int) sanitize_text_field( wp_unslash( $_POST['room_type_id'] ?? 0 ) ),
				'unit_name'    => sanitize_text_field( wp_unslash( $_POST['unit_name'] ?? '' ) ),
				'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
				'sort_order'   => (int) sanitize_text_field( wp_unslash( $_POST['sort_order'] ?? 0 ) ),
				'notes'        => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			);

			if ( $id > 0 ) {
				$this->repository->update( $id, $data );
			} else {
				$this->repository->create( $data );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&saved=1' ) );
			exit;
		}

		$units      = $this->repository->get_all( array( 'per_page' => 100 ) );
		$room_types = get_posts(
			array(
				'post_type'      => RoomTypePostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$editing_unit = $id > 0 ? $this->repository->get( $id ) : null;
		?>
		<div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
			<!-- Add / Edit Form -->
			<div class="postbox" style="padding:16px;">
				<h3 style="margin-top:0; border-bottom:1px solid #ddd; padding-bottom:8px;">
					<?php echo $editing_unit ? esc_html__( 'Edit Physical Room Unit', 'wpcalibrate-stayreserve' ) : esc_html__( 'Add Physical Room Unit', 'wpcalibrate-stayreserve' ); ?>
				</h3>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&action=save' . ( $id > 0 ? '&unit_id=' . $id : '' ) ) ); ?>">
					<?php wp_nonce_field( 'wpcsr_save_unit', 'wpcsr_unit_nonce' ); ?>

					<p>
						<label for="room_type_id"><strong><?php esc_html_e( 'Belongs to Room Type:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<select name="room_type_id" id="room_type_id" required style="width:100%;margin-top:4px;">
							<option value=""><?php esc_html_e( '— Select Room Type —', 'wpcalibrate-stayreserve' ); ?></option>
							<?php foreach ( $room_types as $rt ) : ?>
								<option value="<?php echo esc_attr( (string) $rt->ID ); ?>" <?php selected( (int) ( $editing_unit['room_type_id'] ?? 0 ), $rt->ID ); ?>>
									<?php echo esc_html( $rt->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="unit_name"><strong><?php esc_html_e( 'Unit Name / Number:', 'wpcalibrate-stayreserve' ); ?> *</strong></label><br>
						<input type="text" name="unit_name" id="unit_name" value="<?php echo esc_attr( $editing_unit['unit_name'] ?? '' ); ?>" required style="width:100%;margin-top:4px;" placeholder="<?php esc_attr_e( 'e.g. Room 101 or Villa Sunset', 'wpcalibrate-stayreserve' ); ?>">
					</p>

					<p>
						<label for="unit_status"><strong><?php esc_html_e( 'Inventory Status:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<select name="status" id="unit_status" style="width:100%;margin-top:4px;">
							<option value="active" <?php selected( $editing_unit['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active (Bookable)', 'wpcalibrate-stayreserve' ); ?></option>
							<option value="inactive" <?php selected( $editing_unit['status'] ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive (Disabled)', 'wpcalibrate-stayreserve' ); ?></option>
						</select>
					</p>

					<p>
						<label for="sort_order"><strong><?php esc_html_e( 'Sort Order:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr( (string) ( $editing_unit['sort_order'] ?? 0 ) ); ?>" style="width:100%;margin-top:4px;">
					</p>

					<p>
						<label for="notes"><strong><?php esc_html_e( 'Internal Staff Notes:', 'wpcalibrate-stayreserve' ); ?></strong></label><br>
						<textarea name="notes" id="notes" rows="3" style="width:100%;margin-top:4px;"><?php echo esc_textarea( $editing_unit['notes'] ?? '' ); ?></textarea>
					</p>

					<p>
						<button type="submit" class="button button-primary">
							<?php echo $editing_unit ? esc_html__( 'Update Unit', 'wpcalibrate-stayreserve' ) : esc_html__( 'Add Unit', 'wpcalibrate-stayreserve' ); ?>
						</button>
						<?php if ( $editing_unit ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms' ) ); ?>" class="button">
								<?php esc_html_e( 'Cancel', 'wpcalibrate-stayreserve' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</form>
			</div>

			<!-- Units List Table -->
			<div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Unit Name / Number', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Room Type', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wpcalibrate-stayreserve' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wpcalibrate-stayreserve' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $units ) ) : ?>
							<tr>
								<td colspan="4" style="color:#646970; text-align:center; padding:16px;">
									<?php esc_html_e( 'No physical units registered. Add physical units on the left.', 'wpcalibrate-stayreserve' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $units as $u ) : ?>
								<?php
								$type_post  = get_post( (int) $u['room_type_id'] );
								$type_title = $type_post ? $type_post->post_title : '—';
								$edit_url   = admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&action=edit&unit_id=' . $u['id'] );
								$delete_url = wp_nonce_url(
									admin_url( 'admin.php?page=wpcalibrate-stayreserve&tab=rooms&action=delete&unit_id=' . $u['id'] ),
									'wpcsr_delete_unit_' . $u['id']
								);
								?>
								<tr>
									<td><strong><?php echo esc_html( $u['unit_name'] ); ?></strong></td>
									<td><?php echo esc_html( $type_title ); ?></td>
									<td>
										<span class="wpcsr-status-pill status-<?php echo esc_attr( $u['status'] ); ?>">
											<?php echo esc_html( ucfirst( $u['status'] ) ); ?>
										</span>
									</td>
									<td>
										<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'wpcalibrate-stayreserve' ); ?></a>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Delete this unit?', 'wpcalibrate-stayreserve' ); ?>');" style="color:#b32d2e;">
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
}
