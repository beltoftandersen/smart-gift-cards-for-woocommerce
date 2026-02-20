<?php

namespace GiftCards\GiftCard;

use GiftCards\Support\Options;

defined( 'ABSPATH' ) || exit;

class GiftCardCreator {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'maybe_create_gift_cards' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'maybe_create_gift_cards' ] );
	}

	/**
	 * Create gift cards for gift-card line items in the order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function maybe_create_gift_cards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Idempotency check.
		if ( $order->get_meta( '_wcgc_cards_created' ) ) {
			return;
		}

		$has_gift_card_items = false;
		$all_items_created   = true;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || $product->get_type() !== 'gift-card' ) {
				continue;
			}

			$amount = (float) $item->get_meta( '_wcgc_amount' );
			if ( $amount <= 0 ) {
				continue;
			}

			$qty = max( 0, (int) $item->get_quantity() );
			if ( $qty <= 0 ) {
				continue;
			}

			$has_gift_card_items = true;
			$created_qty         = absint( $item->get_meta( '_wcgc_created_qty', true ) );

			if ( $created_qty < $qty ) {
				for ( $i = $created_qty; $i < $qty; $i++ ) {
					if ( self::create_single( $order, $item, $amount ) ) {
						$created_qty++;
					} else {
						$all_items_created = false;
						break;
					}
				}

				$item->update_meta_data( '_wcgc_created_qty', $created_qty );
				$item->save();
			}

			if ( $created_qty < $qty ) {
				$all_items_created = false;
			}
		}

		if ( $has_gift_card_items && $all_items_created ) {
			$order->update_meta_data( '_wcgc_cards_created', '1' );
			$order->save();
		}
	}

	/**
	 * Calculate the expiry date based on settings.
	 *
	 * @return string|null Datetime string or null for no expiry.
	 */
	private static function calculate_expiry() {
		$days = (int) Options::get( 'default_expiry_days' );
		return $days > 0 ? gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) ) : null;
	}

	/**
	 * Create a single gift card.
	 *
	 * @param \WC_Order      $order  Order object.
	 * @param \WC_Order_Item $item   Line item.
	 * @param float          $amount Gift card amount.
	 * @return bool True when the gift card row is created.
	 */
	private static function create_single( $order, $item, $amount ) {
		$code       = CodeGenerator::generate();
		$expires_at = self::calculate_expiry();

		$gc_data = [
			'code'            => $code,
			'initial_amount'  => $amount,
			'balance'         => $amount,
			'currency'        => $order->get_currency(),
			'sender_name'     => $item->get_meta( '_wcgc_sender_name' ) ?: $order->get_billing_first_name(),
			'sender_email'    => $item->get_meta( '_wcgc_sender_email' ) ?: $order->get_billing_email(),
			'recipient_name'  => $item->get_meta( '_wcgc_recipient_name' ) ?: '',
			'recipient_email' => $item->get_meta( '_wcgc_recipient_email' ) ?: $order->get_billing_email(),
			'message'         => $item->get_meta( '_wcgc_message' ) ?: '',
			'order_id'        => $order->get_id(),
			'customer_id'     => $order->get_customer_id(),
			'status'          => 'active',
			'expires_at'      => $expires_at,
		];

		/**
		 * Filter gift card data before insertion.
		 *
		 * @param array           $gc_data Gift card data array.
		 * @param \WC_Order       $order   Order object.
		 * @param \WC_Order_Item  $item    Line item.
		 */
		$gc_data = apply_filters( 'wcgc_gift_card_creation_args', $gc_data, $order, $item );

		$gc_id = Repository::insert( $gc_data );

		if ( ! $gc_id ) {
			return false;
		}

		// Record initial credit transaction.
		TransactionRepository::insert( [
			'gift_card_id'  => $gc_id,
			'order_id'      => $order->get_id(),
			'type'          => 'credit',
			'amount'        => $amount,
			'balance_after' => $amount,
			'note'          => sprintf(
				/* translators: %s: order number */
				__( 'Gift card created from order #%s', 'smart-gift-cards-for-woocommerce' ),
				$order->get_order_number()
			),
		] );

		/**
		 * Fires after a gift card is created.
		 *
		 * @param int       $gc_id Gift card ID.
		 * @param \WC_Order $order Order object.
		 */
		do_action( 'wcgc_gift_card_created', $gc_id, $order );

		return true;
	}

	/**
	 * Manually create a gift card (admin).
	 *
	 * @param array $data Gift card data.
	 * @return int|false Gift card ID or false.
	 */
	public static function create_manual( $data ) {
		$amount = (float) ( $data['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return false;
		}

		$code = CodeGenerator::generate();

		$gc_id = Repository::insert( [
			'code'            => $code,
			'initial_amount'  => $amount,
			'balance'         => $amount,
			'currency'        => get_woocommerce_currency(),
			'sender_name'     => sanitize_text_field( $data['sender_name'] ?? '' ),
			'sender_email'    => sanitize_email( $data['sender_email'] ?? '' ),
			'recipient_name'  => sanitize_text_field( $data['recipient_name'] ?? '' ),
			'recipient_email' => sanitize_email( $data['recipient_email'] ?? '' ),
			'message'         => sanitize_textarea_field( $data['message'] ?? '' ),
			'order_id'        => null,
			'customer_id'     => null,
			'status'          => 'active',
			'expires_at'      => self::calculate_expiry(),
		] );

		if ( ! $gc_id ) {
			return false;
		}

		TransactionRepository::insert( [
			'gift_card_id'  => $gc_id,
			'type'          => 'credit',
			'amount'        => $amount,
			'balance_after' => $amount,
			'note'          => __( 'Manually created by admin', 'smart-gift-cards-for-woocommerce' ),
		] );

		if ( ! empty( $data['recipient_email'] ) ) {
			do_action( 'wcgc_gift_card_created', $gc_id, null );
		}

		return $gc_id;
	}
}
