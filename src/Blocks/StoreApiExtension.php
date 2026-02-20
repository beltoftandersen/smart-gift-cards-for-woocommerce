<?php

namespace GiftCards\Blocks;

use GiftCards\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Extends the WooCommerce Store API cart response with gift-card-specific data.
 *
 * Lets the blocks checkout JS know when loyalty point redemption
 * is blocked because the cart contains a gift card product.
 */
class StoreApiExtension {

	/**
	 * Register the extension.
	 */
	public static function init() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data( [
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => 'smart-gift-cards-for-woocommerce',
			'data_callback'   => [ __CLASS__, 'extend_cart_data' ],
			'schema_callback' => [ __CLASS__, 'extend_cart_schema' ],
			'schema_type'     => ARRAY_A,
		] );
	}

	/**
	 * Data added to the Store API cart response.
	 *
	 * @return array
	 */
	public static function extend_cart_data() {
		$blocked = false;
		$message = '';

		if ( Options::get( 'allow_points_for_gift_cards' ) !== '1'
			&& class_exists( 'LoyaltyRewards\\Plugin' )
			&& WC()->cart
		) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$product = $item['data'] ?? null;
				if ( $product && $product->get_type() === 'gift-card' ) {
					$blocked = true;
					$message = __( 'Loyalty points cannot be used to purchase gift cards.', 'smart-gift-cards-for-woocommerce' );
					break;
				}
			}
		}

		return [
			'points_blocked'         => $blocked,
			'points_blocked_message' => $message,
		];
	}

	/**
	 * Schema for the extension data (required by Store API).
	 *
	 * @return array
	 */
	public static function extend_cart_schema() {
		return [
			'points_blocked'         => [
				'description' => 'Whether loyalty point redemption is blocked for this cart.',
				'type'        => 'boolean',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'points_blocked_message' => [
				'description' => 'Reason why loyalty points are blocked.',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
		];
	}
}
