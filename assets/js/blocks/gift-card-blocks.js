/**
 * Smart Gift Cards for WooCommerce — Block Cart/Checkout Components.
 *
 * Vanilla JS (no build step) using wp.element.createElement.
 *
 * Shows a notice in the blocks checkout when loyalty point redemption
 * is blocked because the cart contains a gift card product.
 *
 * Also registers a coupon label filter so gift card virtual coupons
 * display a friendly label instead of the raw code.
 */
( function () {
	'use strict';

	var el             = wp.element.createElement;
	var useSelect      = wp.data.useSelect;
	var registerPlugin = wp.plugins.registerPlugin;

	var ExperimentalOrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;
	var TotalsWrapper         = wc.blocksCheckout.TotalsWrapper;

	var NS = 'smart-gift-cards-for-woocommerce';

	/* ── Points blocked notice ───────────────────────────────── */

	function PointsBlockedNotice() {
		var gcData = useSelect( function ( select ) {
			var cart = select( 'wc/store/cart' ).getCartData();
			return ( cart.extensions || {} )[ NS ] || {};
		} );

		if ( ! gcData.points_blocked ) {
			return null;
		}

		var Wrapper = TotalsWrapper || 'div';

		return el( Wrapper, null,
			el( 'div', {
				className: 'wcgc-blocks-points-notice',
				style: {
					padding: '12px 16px',
					margin: '0 0 4px',
					border: '1px solid #d63638',
					borderRadius: '4px',
					color: '#b32d2e',
					fontSize: '0.875em',
					backgroundColor: '#fcf0f1',
				},
			}, gcData.points_blocked_message )
		);
	}

	registerPlugin( 'wcgc-points-blocked', {
		render: function () {
			return el( ExperimentalOrderMeta, {}, el( PointsBlockedNotice ) );
		},
		scope: 'woocommerce-checkout',
	} );

	/* ── Coupon label filter ─────────────────────────────────── */

	var registerFilters = wc.blocksCheckout.registerCheckoutFilters
		|| wc.blocksCheckout.__experimentalRegisterCheckoutFilters;

	if ( registerFilters ) {
		registerFilters( NS, {
			coupons: function ( coupons ) {
				return coupons.map( function ( coupon ) {
					var code = ( coupon.code || '' ).toUpperCase();

					// Gift card codes follow PREFIX-XXXX-XXXX-XXXX pattern.
					// Check against the Store API extension data to confirm.
					if ( code.indexOf( '-' ) !== -1 && code.length >= 14 ) {
						// Use the label WC already assigned (from CartHandler::coupon_label).
						// If WC Blocks stripped it, at least keep the masked code.
						if ( ! coupon.label || coupon.label === coupon.code ) {
							coupon = Object.assign( {}, coupon, {
								label: 'Gift Card (\u00B7\u00B7\u00B7\u00B7' + code.slice( -4 ) + ')',
							} );
						}
					}

					return coupon;
				} );
			},
		} );
	}

} )();
