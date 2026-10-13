/**
 * Frontend JavaScript for WPCalibrate StayReserve.
 *
 * @package WPCalibrate\StayReserve
 */

(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		// Enforce checkout date > checkin date dynamically
		var checkInInput = document.getElementById('wpcsr_check_in');
		var checkOutInput = document.getElementById('wpcsr_check_out');

		if (checkInInput && checkOutInput) {
			checkInInput.addEventListener('change', function() {
				var inDate = this.value;
				if (inDate) {
					checkOutInput.min = inDate;
					if (checkOutInput.value && checkOutInput.value <= inDate) {
						var d = new Date(inDate);
						d.setDate(d.getDate() + 1);
						var nextDay = d.toISOString().split('T')[0];
						checkOutInput.value = nextDay;
					}
				}
			});
		}

		// Terms agreement validation before submission
		var bookingForm = document.querySelector('form[action*="wpcsr_step=confirm"]');
		if (bookingForm) {
			bookingForm.addEventListener('submit', function(e) {
				var termsCheck = bookingForm.querySelector('input[name="agree_terms"]');
				if (termsCheck && !termsCheck.checked) {
					e.preventDefault();
					alert('Please agree to the terms and cancellation policy before completing your reservation.');
				}
			});
		}
	});
})();
