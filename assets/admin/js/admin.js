/**
 * Admin JavaScript for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Confirm deletion handlers
		$('.wpcsr-action-delete').on('click', function(e) {
			if (!confirm(wpcsrAdminData.strings.confirmDelete)) {
				e.preventDefault();
			}
		});

		// Dynamic date validation on stay date inputs
		var $checkIn = $('#check_in');
		var $checkOut = $('#check_out');

		if ($checkIn.length && $checkOut.length) {
			$checkIn.on('change', function() {
				var inVal = $(this).val();
				if (inVal) {
					$checkOut.attr('min', inVal);
					if ($checkOut.val() && $checkOut.val() <= inVal) {
						var d = new Date(inVal);
						d.setDate(d.getDate() + 1);
						$checkOut.val(d.toISOString().split('T')[0]);
					}
				}
			});
		}
	});
})(jQuery);
