<?php

namespace GiftCards\Admin;

use GiftCards\GiftCard\Repository;
use GiftCards\Cart\CartHandler;

defined( 'ABSPATH' ) || exit;

class OrderMetaBox {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'display_order_gift_cards' ] );
	}

	/**
	 * Display gift card info on order edit screen.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function display_order_gift_cards( $order ) {
		// Gift cards created by this order.
		$created_cards = Repository::get_by_order( $order->get_id() );

		// Gift cards used on this order.
		$deductions = $order->get_meta( '_wcgc_pending_deductions' );
		$status_labels = [
			'active'   => __( 'Active', 'smart-gift-cards-for-woocommerce' ),
			'disabled' => __( 'Disabled', 'smart-gift-cards-for-woocommerce' ),
			'expired'  => __( 'Expired', 'smart-gift-cards-for-woocommerce' ),
			'redeemed' => __( 'Redeemed', 'smart-gift-cards-for-woocommerce' ),
		];

		if ( empty( $created_cards ) && empty( $deductions ) ) {
			return;
		}

		echo '<div class="wcgc-order-meta" style="margin-top: 16px; padding: 12px; background: #f9f9f9; border: 1px solid #ddd;">';
		echo '<h3 style="margin-top: 0;">' . esc_html__( 'Gift Cards', 'smart-gift-cards-for-woocommerce' ) . '</h3>';

		if ( ! empty( $created_cards ) ) {
			echo '<h4>' . esc_html__( 'Created by this order:', 'smart-gift-cards-for-woocommerce' ) . '</h4>';
			echo '<table class="widefat striped" style="margin-bottom: 12px;">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Code', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Balance', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Recipient', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $created_cards as $gc ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $gc->code ) . '</code></td>';
				echo '<td>' . wp_kses_post( wc_price( $gc->initial_amount ) ) . '</td>';
				echo '<td>' . wp_kses_post( wc_price( $gc->balance ) ) . '</td>';
				echo '<td>' . esc_html( $gc->recipient_email ) . '</td>';
				$status = $status_labels[ $gc->status ] ?? $gc->status;
				echo '<td><span class="wcgc-status wcgc-status--' . esc_attr( $gc->status ) . '">' . esc_html( $status ) . '</span></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		if ( ! empty( $deductions ) && is_array( $deductions ) ) {
			echo '<h4>' . esc_html__( 'Used on this order:', 'smart-gift-cards-for-woocommerce' ) . '</h4>';
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Code', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount Used', 'smart-gift-cards-for-woocommerce' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $deductions as $code => $amount ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $code ) . '</code></td>';
				echo '<td>' . wp_kses_post( wc_price( $amount ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}
}
