<?php

namespace GiftCards\Cart;

use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class CouponInterceptor {

	/**
	 * Initialize hooks.
	 *
	 * Hooks into wp_loaded at priority 9 (before WC form handler at 20)
	 * to intercept coupon submissions that match a gift card code.
	 */
	public static function init() {
		add_action( 'wp_loaded', [ __CLASS__, 'intercept_coupon_form' ], 9 );
		add_action( 'wc_ajax_apply_coupon', [ __CLASS__, 'intercept_ajax_coupon' ], 1 );
	}

	/**
	 * Intercept the WooCommerce coupon form submission.
	 *
	 * If the submitted code matches a gift card, apply it and prevent
	 * WooCommerce from trying to look it up as a coupon.
	 */
	public static function intercept_coupon_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Initial check; nonce verified below.
		if ( ! isset( $_POST['apply_coupon'] ) || empty( $_POST['coupon_code'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Verify WooCommerce cart nonce before processing.
		$nonce = '';
		if ( isset( $_POST['woocommerce-cart-nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-cart-nonce'] ) );
		} elseif ( isset( $_POST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
			return;
		}

		$code = strtoupper( wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) );

		$gc = Repository::find_by_code( $code );
		if ( ! $gc ) {
			return; // Not a gift card — let WooCommerce handle it as a coupon.
		}

		// Validate the gift card.
		$valid = CartHandler::validate_gift_card( $gc );
		if ( is_wp_error( $valid ) ) {
			wc_add_notice( $valid->get_error_message(), 'error' );
		} else {
			CartHandler::add_gift_card_to_session( $code );
			wc_add_notice( __( 'Gift card applied successfully!', 'smart-gift-cards-for-woocommerce' ) );
		}

		// Prevent WooCommerce from processing this as a coupon.
		unset( $_POST['apply_coupon'], $_POST['coupon_code'] );
	}

	/**
	 * Intercept WooCommerce AJAX coupon apply requests.
	 *
	 * Handles checkout/cart AJAX coupon flow so gift cards work in the standard
	 * coupon field outside the classic cart form submission path.
	 */
	public static function intercept_ajax_coupon() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( empty( $_POST['coupon_code'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! check_ajax_referer( 'apply-coupon', 'security', false ) ) {
			return;
		}

		$code = strtoupper( wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) );
		$gc   = Repository::find_by_code( $code );
		if ( ! $gc ) {
			return;
		}

		$valid = CartHandler::validate_gift_card( $gc );
		if ( is_wp_error( $valid ) ) {
			wc_add_notice( $valid->get_error_message(), 'error' );
		} else {
			CartHandler::add_gift_card_to_session( $code );
			wc_add_notice( __( 'Gift card applied successfully!', 'smart-gift-cards-for-woocommerce' ) );
		}

		wc_print_notices();
		wp_die();
	}
}
