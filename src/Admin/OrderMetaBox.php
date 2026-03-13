<?php

namespace Bgcw\Admin;

use Bgcw\GiftCard\Repository;
use Bgcw\Cart\CartHandler;

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

		// Gift cards used on this order (prefer actual deducted amounts).
		$deductions = $order->get_meta( '_bgcw_deducted_amounts' );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			$deductions = $order->get_meta( '_bgcw_pending_deductions' );
		}
		$status_labels = [
			'active'   => __( 'Active', 'beltoft-gift-cards' ),
			'disabled' => __( 'Disabled', 'beltoft-gift-cards' ),
			'expired'  => __( 'Expired', 'beltoft-gift-cards' ),
			'redeemed' => __( 'Redeemed', 'beltoft-gift-cards' ),
		];

		if ( empty( $created_cards ) && empty( $deductions ) ) {
			return;
		}

		echo '<div class="bgcw-order-meta">';
		echo '<h3>' . esc_html__( 'Gift Cards', 'beltoft-gift-cards' ) . '</h3>';

		if ( ! empty( $created_cards ) ) {
			echo '<h4>' . esc_html__( 'Created by this order:', 'beltoft-gift-cards' ) . '</h4>';
			echo '<table class="widefat striped bgcw-order-meta-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Code', 'beltoft-gift-cards' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount', 'beltoft-gift-cards' ) . '</th>';
			echo '<th>' . esc_html__( 'Balance', 'beltoft-gift-cards' ) . '</th>';
			echo '<th>' . esc_html__( 'Recipient', 'beltoft-gift-cards' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'beltoft-gift-cards' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $created_cards as $gc ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $gc->code ) . '</code></td>';
				echo '<td>' . wp_kses_post( wc_price( $gc->initial_amount ) ) . '</td>';
				echo '<td>' . wp_kses_post( wc_price( $gc->balance ) ) . '</td>';
				echo '<td>' . esc_html( $gc->recipient_email ) . '</td>';
				$status = $status_labels[ $gc->status ] ?? $gc->status;
				echo '<td><span class="bgcw-status bgcw-status--' . esc_attr( $gc->status ) . '">' . esc_html( $status ) . '</span></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		if ( ! empty( $deductions ) && is_array( $deductions ) ) {
			echo '<h4>' . esc_html__( 'Used on this order:', 'beltoft-gift-cards' ) . '</h4>';
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Code', 'beltoft-gift-cards' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount Used', 'beltoft-gift-cards' ) . '</th>';
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
