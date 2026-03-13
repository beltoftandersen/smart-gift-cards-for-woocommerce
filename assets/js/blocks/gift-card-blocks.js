/**
 * Beltoft Gift Cards for WooCommerce — Block Cart/Checkout Components.
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

	var NS = 'beltoft-gift-cards';

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

		// Render a <style> tag to hide the loyalty panel — no DOM
		// manipulation needed, React manages the lifecycle automatically.
		return el( Wrapper, null,
			el( 'style', null,
				'.wclr-blocks-panel { display: none !important; }'
			),
			el( 'div', {
				className: 'bgcw-blocks-points-notice',
				style: {
					padding: '12px 16px',
					margin: '0 0 12px',
					border: '1px solid #c3c4c7',
					borderRadius: '4px',
					color: '#50575e',
					fontSize: '0.875em',
					backgroundColor: '#f0f0f1',
				},
			}, gcData.points_blocked_message )
		);
	}

	registerPlugin( 'bgcw-points-blocked', {
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
				// Read applied gift card codes from the Store API extension data.
				var cartStore = wp.data.select( 'wc/store/cart' );
				var cartData  = cartStore && cartStore.getCartData ? cartStore.getCartData() : null;
				var gcData    = ( ( cartData && cartData.extensions ) || {} )[ NS ] || {};
				var gcCodes   = Array.isArray( gcData.gift_card_codes ) ? gcData.gift_card_codes : [];
				var gcSet     = {};

				gcCodes.forEach( function ( value ) {
					gcSet[ String( value ).toUpperCase() ] = true;
				} );

				return coupons.map( function ( coupon ) {
					var code = ( coupon.code || '' ).toUpperCase();

					// Only relabel coupons confirmed as gift cards by the Store API extension.
					if ( gcSet[ code ] ) {
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
