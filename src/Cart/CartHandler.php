<?php

namespace GiftCards\Cart;

use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class CartHandler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Virtual coupon hooks — makes gift card codes work as WC coupons.
		add_filter( 'woocommerce_get_shop_coupon_data', [ __CLASS__, 'virtual_coupon_data' ], 10, 2 );
		add_filter( 'woocommerce_cart_totals_coupon_label', [ __CLASS__, 'coupon_label' ], 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', [ __CLASS__, 'validate_coupon' ], 10, 2 );
		add_filter( 'woocommerce_coupon_message', [ __CLASS__, 'coupon_message' ], 10, 3 );

		// Sync session tracking when coupons are added/removed.
		add_action( 'woocommerce_applied_coupon', [ __CLASS__, 'on_coupon_applied' ] );
		add_action( 'woocommerce_removed_coupon', [ __CLASS__, 'on_coupon_removed' ] );

		// URL-based actions.
		add_action( 'wp_loaded', [ __CLASS__, 'handle_remove_gift_card' ], 15 );
		add_action( 'wp_loaded', [ __CLASS__, 'handle_auto_apply' ], 16 );

		// Clear on cart empty.
		add_action( 'woocommerce_cart_emptied', [ __CLASS__, 'clear_applied_codes' ] );
	}

	/**
	 * Supply virtual coupon data so WooCommerce treats gift card codes as valid coupons.
	 *
	 * @param mixed  $data Existing coupon data.
	 * @param string $code Coupon code.
	 * @return mixed
	 */
	public static function virtual_coupon_data( $data, $code ) {
		if ( $data ) {
			return $data;
		}

		$gc = Repository::find_by_code( $code );
		if ( ! $gc || $gc->status !== 'active' || (float) $gc->balance <= 0 ) {
			return $data;
		}

		// Check expiry.
		if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
			return $data;
		}

		// Currency mismatch.
		if ( function_exists( 'get_woocommerce_currency' ) && $gc->currency !== get_woocommerce_currency() ) {
			return $data;
		}

		return [
			'id'                          => 0,
			'amount'                      => (float) $gc->balance,
			'discount_type'               => 'fixed_cart',
			'individual_use'              => false,
			'usage_limit'                 => 0,
			'usage_count'                 => 0,
			'date_created'                => '',
			'date_modified'               => '',
			'date_expires'                => null,
			'free_shipping'               => false,
			'product_ids'                 => [],
			'excluded_product_ids'        => [],
			'product_categories'          => [],
			'excluded_product_categories' => [],
			'exclude_sale_items'          => false,
			'minimum_amount'              => '',
			'maximum_amount'              => '',
			'email_restrictions'          => [],
			'virtual'                     => true,
		];
	}

	/**
	 * Show friendly label instead of the raw coupon code.
	 *
	 * @param string     $label  Default coupon label.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return string
	 */
	public static function coupon_label( $label, $coupon ) {
		if ( self::is_gift_card_coupon( $coupon->get_code() ) ) {
			return sprintf(
				/* translators: %s: masked gift card code */
				__( 'Gift Card (%s)', 'smart-gift-cards-for-woocommerce' ),
				self::mask_code( $coupon->get_code() )
			);
		}
		return $label;
	}

	/**
	 * Validate the virtual coupon (prevent "coupon doesn't exist" errors).
	 *
	 * @param bool       $valid  Current validity.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return bool
	 */
	public static function validate_coupon( $valid, $coupon ) {
		$code = $coupon->get_code();
		$gc   = Repository::find_by_code( $code );
		if ( ! $gc ) {
			return $valid; // Not a gift card — pass through.
		}

		if ( $gc->status !== 'active' || (float) $gc->balance <= 0 ) {
			return false;
		}

		if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
			return false;
		}

		if ( function_exists( 'get_woocommerce_currency' ) && $gc->currency !== get_woocommerce_currency() ) {
			return false;
		}

		return true;
	}

	/**
	 * Customize the "Coupon applied" message for gift cards.
	 *
	 * @param string $msg     Message.
	 * @param int    $msg_code Message code.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return string
	 */
	public static function coupon_message( $msg, $msg_code, $coupon ) {
		if ( \WC_Coupon::WC_COUPON_SUCCESS === $msg_code && self::is_gift_card_coupon( $coupon->get_code() ) ) {
			return __( 'Gift card applied successfully!', 'smart-gift-cards-for-woocommerce' );
		}
		return $msg;
	}

	/**
	 * When WC applies a coupon, track gift card codes in our session.
	 *
	 * @param string $code Coupon code.
	 */
	public static function on_coupon_applied( $code ) {
		if ( self::is_gift_card_coupon( $code ) ) {
			self::add_code_to_session( $code );
		}
	}

	/**
	 * When WC removes a coupon, remove from our session tracking.
	 *
	 * @param string $code Coupon code.
	 */
	public static function on_coupon_removed( $code ) {
		if ( self::is_gift_card_coupon( $code ) ) {
			self::remove_code_from_session( $code );
		}
	}

	/**
	 * Check if a coupon code corresponds to a gift card.
	 *
	 * @param string $code Coupon code.
	 * @return bool
	 */
	public static function is_gift_card_coupon( $code ) {
		$gc = Repository::find_by_code( $code );
		return $gc !== null;
	}

	/**
	 * Validate a gift card for redemption (used by AjaxHandler and auto-apply).
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

		// Check if already applied in WC cart.
		if ( WC()->cart && WC()->cart->has_discount( $gc->code ) ) {
			return new \WP_Error( 'already_applied', __( 'This gift card is already applied.', 'smart-gift-cards-for-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Add a gift card code to WC cart as a coupon.
	 *
	 * @param string $code Gift card code.
	 */
	public static function add_gift_card_to_session( $code ) {
		$code = strtoupper( trim( $code ) );
		if ( WC()->cart ) {
			WC()->cart->apply_coupon( $code );
		}
	}

	/**
	 * Remove a gift card code from WC cart.
	 *
	 * @param string $code Gift card code.
	 */
	public static function remove_gift_card_from_session( $code ) {
		$code = strtolower( trim( $code ) );
		if ( WC()->cart ) {
			WC()->cart->remove_coupon( $code );
			WC()->cart->calculate_totals();
		}
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
	 * Get deduction amounts from WC cart coupon discount data.
	 *
	 * @return array [ code => amount ]
	 */
	public static function get_deduction_amounts() {
		if ( ! WC()->cart ) {
			return [];
		}

		$deductions = [];
		foreach ( WC()->cart->get_coupon_discount_totals() as $code => $discount ) {
			if ( self::is_gift_card_coupon( $code ) ) {
				$deductions[ strtoupper( $code ) ] = (float) $discount;
			}
		}

		return $deductions;
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
	 * Handle auto-apply via URL parameter (e.g., from email "Shop Now" link).
	 *
	 * Detects ?wcgc_apply=CODE, validates, adds to cart as coupon, and redirects to
	 * the shop page with the code stripped from the URL.
	 */
	public static function handle_auto_apply() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public link from email; code is validated against DB.
		if ( ! isset( $_GET['wcgc_apply'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['wcgc_apply'] ) );
		if ( empty( $code ) ) {
			return;
		}

		// Ensure WC session is available.
		if ( ! WC()->session ) {
			return;
		}

		$gc = Repository::find_by_code( $code );

		$validation = self::validate_gift_card( $gc );
		if ( is_wp_error( $validation ) ) {
			if ( $validation->get_error_code() === 'already_applied' ) {
				wc_add_notice( __( 'This gift card is already applied to your cart.', 'smart-gift-cards-for-woocommerce' ), 'notice' );
			} else {
				wc_add_notice( __( 'This gift card code is invalid or cannot be applied.', 'smart-gift-cards-for-woocommerce' ), 'error' );
			}
		} else {
			self::add_gift_card_to_session( $code );
			wc_add_notice(
				sprintf(
					/* translators: %s: formatted gift card balance */
					__( 'Gift card applied! Balance: %s', 'smart-gift-cards-for-woocommerce' ),
					wp_strip_all_tags( wc_price( $gc->balance, [ 'currency' => $gc->currency ] ) )
				),
				'success'
			);
		}

		// Redirect to shop page (strip the code from URL).
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
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
		$clean = str_replace( '-', '', strtoupper( $code ) );
		$last4 = substr( $clean, -4 );
		return str_repeat( "\u{00B7}", strlen( $clean ) - 4 ) . $last4;
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
	}

	/**
	 * Add a code to session tracking (for OrderProcessor).
	 *
	 * @param string $code Gift card code.
	 */
	private static function add_code_to_session( $code ) {
		if ( ! WC()->session ) {
			return;
		}
		$applied   = WC()->session->get( 'wcgc_applied_codes', [] );
		$applied[] = strtoupper( trim( $code ) );
		WC()->session->set( 'wcgc_applied_codes', array_values( array_unique( $applied ) ) );
	}

	/**
	 * Remove a code from session tracking.
	 *
	 * @param string $code Gift card code.
	 */
	private static function remove_code_from_session( $code ) {
		if ( ! WC()->session ) {
			return;
		}
		$applied = WC()->session->get( 'wcgc_applied_codes', [] );
		$applied = array_filter( $applied, function ( $c ) use ( $code ) {
			return strtoupper( $c ) !== strtoupper( $code );
		} );
		WC()->session->set( 'wcgc_applied_codes', array_values( $applied ) );
	}
}
