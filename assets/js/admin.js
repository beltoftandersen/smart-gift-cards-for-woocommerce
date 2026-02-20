/**
 * Gift Cards for WooCommerce - Admin JS
 */
(function($) {
	'use strict';

	$(function() {
		// Toggle add gift card form.
		$('.wcgc-toggle-add-form').on('click', function() {
			$('.wcgc-add-form').slideToggle(200);
		});
	});
})(jQuery);
