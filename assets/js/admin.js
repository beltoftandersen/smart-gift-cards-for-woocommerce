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

		// Keep color picker + hex text input in sync.
		$('.wcgc-color-picker').each(function() {
			var $picker   = $(this);
			var targetId  = $picker.data('target');
			var $hexInput = $('#' + targetId);
			if (!$hexInput.length) {
				return;
			}

			$picker.on('input change', function() {
				$hexInput.val($picker.val());
			});

			$hexInput.on('input change blur', function() {
				var value = $.trim($hexInput.val());
				if (value && value.charAt(0) !== '#') {
					value = '#' + value;
				}
				if (/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/.test(value)) {
					$picker.val(value);
					$hexInput.val(value.toLowerCase());
				}
			});
		});
	});
})(jQuery);
