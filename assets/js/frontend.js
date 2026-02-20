/**
 * Smart Gift Cards for WooCommerce - Frontend JS
 */
(function($) {
	'use strict';

	$(function() {

		/* ── Product Page: Amount Buttons ─────────────────────── */

		var $amounts = $('.wcgc-amounts');
		if ($amounts.length) {
			// Select first predefined button on load.
			$amounts.find('.wcgc-amount-btn:not(.wcgc-custom-btn)').first().addClass('active');

			$amounts.on('click', '.wcgc-amount-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);

				$amounts.find('.wcgc-amount-btn').removeClass('active');
				$btn.addClass('active');

				if ($btn.hasClass('wcgc-custom-btn')) {
					$('.wcgc-custom-amount').slideDown(150);
					$('#wcgc_amount').val('');
				} else {
					$('.wcgc-custom-amount').slideUp(150);
					$('#wcgc_amount').val($btn.data('amount'));
					$('#wcgc_custom_amount').val('');
				}
			});

			// Sync custom amount input → hidden field.
			$('#wcgc_custom_amount').on('input', function() {
				$('#wcgc_amount').val($(this).val());
			});
		}

		/* ── Dedicated Field: AJAX Apply / Remove ────────────── */

		var $field = $('.wcgc-apply-field');
		if ($field.length && typeof wcgc_params !== 'undefined') {

			// Apply gift card.
			$field.on('click', '.wcgc-apply-btn', function() {
				var $btn    = $(this);
				var $input  = $field.find('.wcgc-code-input');
				var $notice = $field.find('.wcgc-field-notice');
				var code    = $.trim($input.val());

				if (!code) {
					$notice.text(wcgc_params.i18n.enter_code).removeClass('success').addClass('error').show();
					return;
				}

				$btn.prop('disabled', true).text(wcgc_params.i18n.applying);
				$notice.hide();

				$.ajax({
					url: wcgc_params.ajax_url.replace('%%endpoint%%', 'wcgc_apply_card'),
					type: 'POST',
					data: {
						security: wcgc_params.nonce,
						code: code
					},
					success: function(res) {
						if (res.success) {
							$notice.text(res.data.message).removeClass('error').addClass('success').show();
							$input.val('');
							// Refresh page to update cart totals.
							location.reload();
						} else {
							$notice.text(res.data.message).removeClass('success').addClass('error').show();
						}
					},
					error: function() {
						$notice.text(wcgc_params.i18n.request_error).removeClass('success').addClass('error').show();
					},
					complete: function() {
						$btn.prop('disabled', false).text(wcgc_params.i18n.apply || 'Apply');
					}
				});
			});

			// Apply on Enter key.
			$field.on('keypress', '.wcgc-code-input', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					$field.find('.wcgc-apply-btn').trigger('click');
				}
			});

			// Remove gift card.
			$field.on('click', '.wcgc-ajax-remove', function() {
				var $btn    = $(this);
				var index   = $btn.data('index');
				var $notice = $field.find('.wcgc-field-notice');

				$btn.prop('disabled', true);

				$.ajax({
					url: wcgc_params.ajax_url.replace('%%endpoint%%', 'wcgc_remove_card'),
					type: 'POST',
					data: {
						security: wcgc_params.nonce,
						index: index
					},
					success: function(res) {
						if (res.success) {
							location.reload();
						} else {
							$notice.text(res.data.message).removeClass('success').addClass('error').show();
						}
					},
					error: function() {
						$notice.text(wcgc_params.i18n.request_error).removeClass('success').addClass('error').show();
					},
					complete: function() {
						$btn.prop('disabled', false);
					}
				});
			});
		}

		/* ── My Account: Toggle Transaction Rows ─────────────── */

		$('.wcgc-toggle-transactions').on('click', function() {
			var $btn   = $(this);
			var cardId = $btn.closest('tr').data('card-id');
			var $row   = $('.wcgc-transactions-row[data-card-id="' + cardId + '"]');

			$row.toggle();
			$btn.html($row.is(':visible') ? '&#9650;' : '&#9660;');
		});

	});
})(jQuery);
