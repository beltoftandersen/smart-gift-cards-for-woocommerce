<?php

namespace Bgcw\Cart;

use Bgcw\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class AjaxHandler {

	/**
	 * Verify AJAX nonce from supported request keys.
	 */
	private static function verify_nonce() {
		if ( check_ajax_referer( 'bgcw-ajax', 'nonce', false ) || check_ajax_referer( 'bgcw-ajax', 'security', false ) ) {
			return;
		}

		wp_send_json_error(
			[
				'message' => __( 'Security check failed. Please refresh and try again.', 'beltoft-gift-cards' ),
			],
			403
		);
	}

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init() {
		add_action( 'wc_ajax_bgcw_apply_card', [ __CLASS__, 'apply_card' ] );
		add_action( 'wc_ajax_bgcw_remove_card', [ __CLASS__, 'remove_card' ] );
	}

	/**
	 * AJAX: Apply a gift card.
	 */
	public static function apply_card() {
		self::verify_nonce();

		if ( ! WC()->session ) {
			wp_send_json_error( [ 'message' => __( 'Session not available. Please refresh.', 'beltoft-gift-cards' ) ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in self::verify_nonce().
		$code = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
		if ( empty( $code ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a gift card code.', 'beltoft-gift-cards' ) ] );
		}

		// Rate limiting: max 5 lookups per minute per session.
		$transient_key = 'bgcw_rate_' . md5( WC()->session->get_customer_id() );
		$attempts      = (int) get_transient( $transient_key );
		if ( $attempts >= 5 ) {
			wp_send_json_error( [ 'message' => __( 'Too many attempts. Please wait a moment.', 'beltoft-gift-cards' ) ] );
		}
		set_transient( $transient_key, $attempts + 1, MINUTE_IN_SECONDS );

		$gc = Repository::find_by_code( $code );

		$valid = CartHandler::validate_gift_card( $gc );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( [ 'message' => $valid->get_error_message() ] );
		}

		// Apply via WC coupon system.
		CartHandler::add_gift_card_to_session( $code );

		wp_send_json_success( [
			'message' => __( 'Gift card applied successfully!', 'beltoft-gift-cards' ),
		] );
	}

	/**
	 * AJAX: Remove a gift card by session index.
	 */
	public static function remove_card() {
		self::verify_nonce();

		if ( ! WC()->session ) {
			wp_send_json_error( [ 'message' => __( 'Session not available. Please refresh.', 'beltoft-gift-cards' ) ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in self::verify_nonce().
		$index   = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : -1;
		$applied = CartHandler::get_applied_codes();

		if ( ! isset( $applied[ $index ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid gift card.', 'beltoft-gift-cards' ) ] );
		}

		// Remove via WC coupon system.
		CartHandler::remove_gift_card_from_session( $applied[ $index ] );

		wp_send_json_success( [
			'message' => __( 'Gift card removed.', 'beltoft-gift-cards' ),
		] );
	}
}
