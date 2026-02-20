<?php

namespace GiftCards\Cart;

use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class CartHandler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', [ __CLASS__, 'apply_gift_card_fees' ] );
		add_action( 'woocommerce_cart_totals_after_order_total', [ __CLASS__, 'display_applied_cards' ] );
		add_action( 'woocommerce_review_order_after_order_total', [ __CLASS__, 'display_applied_cards' ] );
		add_action( 'wp_loaded', [ __CLASS__, 'handle_remove_gift_card' ], 15 );
		add_action( 'woocommerce_cart_emptied', [ __CLASS__, 'clear_applied_codes' ] );
	}

	/**
	 * Validate a gift card for redemption.
	 *
	 * @param object $gc Gift card row.
	 * @return true|\WP_Error
	 */
	/**
	 * Generic invalid-code message to prevent enumeration.
	 *
	 * @return string
	 */
	private static function invalid_code_message() {
		return __( 'This gift card code is invalid or cannot be applied.', 'smart-gift-cards-for-woocommerce' );
	}

	public static function validate_gift_card( $gc ) {
		if ( ! $gc ) {
			return new \WP_Error( 'invalid', self::invalid_code_message() );
		}

		if ( $gc->status !== 'active' ) {
			return new \WP_Error( 'invalid', self::invalid_code_message() );
		}

		if ( (float) $gc->balance <= 0 ) {
			return new \WP_Error( 'invalid', self::invalid_code_message() );
		}

		if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
			return new \WP_Error( 'invalid', self::invalid_code_message() );
		}

		// Currency mismatch check.
		if ( function_exists( 'get_woocommerce_currency' ) && $gc->currency !== get_woocommerce_currency() ) {
			return new \WP_Error( 'invalid', self::invalid_code_message() );
		}

		// Check if already applied.
		$applied = self::get_applied_codes();
		if ( in_array( $gc->code, $applied, true ) ) {
			return new \WP_Error( 'already_applied', __( 'This gift card is already applied.', 'smart-gift-cards-for-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Add a gift card code to the session.
	 *
	 * @param string $code Gift card code.
	 */
	public static function add_gift_card_to_session( $code ) {
		if ( ! WC()->session ) {
			return;
		}
		$applied   = self::get_applied_codes();
		$applied[] = strtoupper( trim( $code ) );
		WC()->session->set( 'wcgc_applied_codes', array_unique( $applied ) );
	}

	/**
	 * Remove a gift card code from the session.
	 *
	 * @param string $code Gift card code.
	 */
	public static function remove_gift_card_from_session( $code ) {
		if ( ! WC()->session ) {
			return;
		}
		$applied = self::get_applied_codes();
		$applied = array_filter( $applied, function ( $c ) use ( $code ) {
			return strtoupper( $c ) !== strtoupper( $code );
		} );
		WC()->session->set( 'wcgc_applied_codes', array_values( $applied ) );
	}

	/**
	 * Get applied gift card codes from session.
	 *
	 * @return array
	 */
	public static function get_applied_codes() {
		if ( ! WC()->session ) {
			return [];
		}
		return WC()->session->get( 'wcgc_applied_codes', [] );
	}

	/**
	 * Apply gift cards as negative fees.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public static function apply_gift_card_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$codes = self::get_applied_codes();
		if ( empty( $codes ) ) {
			return;
		}

		// Get subtotal + shipping - coupons as the base.
		$remaining = (float) $cart->get_subtotal()
			+ (float) $cart->get_subtotal_tax()
			+ (float) $cart->get_shipping_total()
			+ (float) $cart->get_shipping_tax()
			- (float) $cart->get_discount_total()
			- (float) $cart->get_discount_tax();

		$deductions = [];

		foreach ( $codes as $code ) {
			if ( $remaining <= 0 ) {
				break;
			}

			$gc = Repository::find_by_code( $code );
			if ( ! $gc || $gc->status !== 'active' || (float) $gc->balance <= 0 ) {
				continue;
			}

			// Check expiry.
			if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
				continue;
			}

			$discount   = min( (float) $gc->balance, $remaining );
			$remaining -= $discount;

			$deductions[ $code ] = $discount;

			$cart->add_fee(
				sprintf(
					/* translators: %s: gift card code (masked) */
					__( 'Gift Card (%s)', 'smart-gift-cards-for-woocommerce' ),
					self::mask_code( $code )
				),
				-$discount,
				false // Non-taxable.
			);
		}

		// Store deduction amounts in session for OrderProcessor.
		if ( WC()->session ) {
			WC()->session->set( 'wcgc_deduction_amounts', $deductions );
		}
	}

	/**
	 * Display applied gift cards in cart/checkout totals.
	 */
	public static function display_applied_cards() {
		$codes = self::get_applied_codes();
		if ( empty( $codes ) ) {
			return;
		}

		$deductions = self::get_deduction_amounts();

		foreach ( $codes as $code ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			// Show actual discount applied, not full balance.
			$discount = isset( $deductions[ $code ] ) ? (float) $deductions[ $code ] : (float) $gc->balance;

			$remove_url = wp_nonce_url(
				add_query_arg( 'remove_gift_card', rawurlencode( $code ) ),
				'wcgc_remove_' . $code,
				'wcgc_nonce'
			);

			?>
			<tr class="wcgc-applied-card">
				<th>
					<?php
					printf(
						/* translators: %s: masked gift card code */
						esc_html__( 'Gift Card %s', 'smart-gift-cards-for-woocommerce' ),
						'<small>(' . esc_html( self::mask_code( $code ) ) . ')</small>'
					);
					?>
				</th>
				<td>
					<?php echo wp_kses_post( wc_price( $discount, [ 'currency' => $gc->currency ] ) ); ?>
					<a href="<?php echo esc_url( $remove_url ); ?>" class="wcgc-remove-card" title="<?php esc_attr_e( 'Remove', 'smart-gift-cards-for-woocommerce' ); ?>">[&times;]</a>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Handle gift card removal via URL.
	 */
	public static function handle_remove_gift_card() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce is verified below via wp_verify_nonce.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_GET['remove_gift_card'] ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['remove_gift_card'] ) );
		$nonce = isset( $_GET['wcgc_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['wcgc_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! wp_verify_nonce( $nonce, 'wcgc_remove_' . $code ) ) {
			return;
		}

		self::remove_gift_card_from_session( $code );
		wc_add_notice( __( 'Gift card removed.', 'smart-gift-cards-for-woocommerce' ) );

		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Mask a gift card code for display.
	 *
	 * Shows only last 4 characters. E.g., GIFT-XXXX-XXXX-AB3D → ····AB3D
	 *
	 * @param string $code Full code.
	 * @return string Masked code.
	 */
	public static function mask_code( $code ) {
		$clean = str_replace( '-', '', $code );
		$last4 = substr( $clean, -4 );
		return str_repeat( "\u{00B7}", strlen( $clean ) - 4 ) . $last4;
	}

	/**
	 * Get the amounts that will be deducted from each applied gift card.
	 *
	 * Reads from the session map populated by apply_gift_card_fees().
	 *
	 * @return array [ code => amount ]
	 */
	public static function get_deduction_amounts() {
		if ( ! WC()->session ) {
			return [];
		}
		return WC()->session->get( 'wcgc_deduction_amounts', [] );
	}

	/**
	 * Clear all applied gift card codes from session.
	 *
	 * Hooked to woocommerce_cart_emptied.
	 */
	public static function clear_applied_codes() {
		if ( ! WC()->session ) {
			return;
		}
		WC()->session->set( 'wcgc_applied_codes', [] );
		WC()->session->set( 'wcgc_deduction_amounts', [] );
	}
}
