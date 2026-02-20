<?php

namespace GiftCards\Checkout;

use GiftCards\Cart\CartHandler;
use GiftCards\GiftCard\Repository;
use GiftCards\GiftCard\TransactionRepository;

defined( 'ABSPATH' ) || exit;

class OrderProcessor {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Save pending deductions when order is created.
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'save_pending_deductions' ] );

		// Deduct balances on payment.
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'deduct_gift_card_balances' ] );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'deduct_gift_card_balances' ] );

		// Restore balances on cancel/full refund.
		add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'restore_gift_card_balances' ] );
		add_action( 'woocommerce_order_status_refunded', [ __CLASS__, 'restore_gift_card_balances' ] );

		// Handle partial refunds.
		add_action( 'woocommerce_order_partially_refunded', [ __CLASS__, 'handle_partial_refund' ], 10, 2 );

		// Clear session after order is placed.
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'clear_session' ], 100 );
	}

	/**
	 * Save pending deductions to order meta.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function save_pending_deductions( $order ) {
		$deductions = CartHandler::get_deduction_amounts();
		if ( empty( $deductions ) ) {
			return;
		}

		$order->update_meta_data( '_wcgc_pending_deductions', $deductions );
		$order->save();
	}

	/**
	 * Deduct gift card balances for the order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function deduct_gift_card_balances( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Idempotency check.
		if ( $order->get_meta( '_wcgc_deducted' ) ) {
			return;
		}

		$deductions = $order->get_meta( '_wcgc_pending_deductions' );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			return;
		}

		foreach ( $deductions as $code => $amount ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			// Re-validate status and expiry at deduction time.
			if ( $gc->status !== 'active' ) {
				continue;
			}
			if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
				continue;
			}

			$amount      = (float) $amount;
			$old_balance = (float) $gc->balance;

			// Atomic balance deduction to prevent race conditions.
			if ( ! Repository::deduct_balance( $gc->id, $amount ) ) {
				continue;
			}

			// Read back the new balance.
			$updated     = Repository::find( $gc->id );
			$new_balance = $updated ? (float) $updated->balance : max( 0, $old_balance - $amount );

			TransactionRepository::insert( [
				'gift_card_id'  => $gc->id,
				'order_id'      => $order_id,
				'type'          => 'debit',
				'amount'        => $amount,
				'balance_after' => $new_balance,
				'note'          => sprintf(
					/* translators: %s: order number */
					__( 'Used on order #%s', 'smart-gift-cards-for-woocommerce' ),
					$order->get_order_number()
				),
			] );

			// Mark as redeemed if balance is zero.
			if ( $new_balance <= 0 ) {
				Repository::update_status( $gc->id, 'redeemed' );
			}
		}

		$order->update_meta_data( '_wcgc_deducted', '1' );
		$order->save();
	}

	/**
	 * Restore gift card balances on cancel/full refund.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function restore_gift_card_balances( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Idempotency check.
		if ( $order->get_meta( '_wcgc_restored' ) ) {
			return;
		}

		// Only restore if we actually deducted.
		if ( ! $order->get_meta( '_wcgc_deducted' ) ) {
			return;
		}

		$deductions = $order->get_meta( '_wcgc_pending_deductions' );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			return;
		}

		foreach ( $deductions as $code => $amount ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			$amount = (float) $amount;

			// Cap restored balance at initial_amount.
			$new_balance = min( (float) $gc->initial_amount, (float) $gc->balance + $amount );

			Repository::update_balance( $gc->id, $new_balance );

			// Reactivate if it was marked as redeemed.
			if ( $gc->status === 'redeemed' ) {
				Repository::update_status( $gc->id, 'active' );
			}

			TransactionRepository::insert( [
				'gift_card_id'  => $gc->id,
				'order_id'      => $order_id,
				'type'          => 'refund',
				'amount'        => $amount,
				'balance_after' => $new_balance,
				'note'          => sprintf(
					/* translators: %s: order number */
					__( 'Refunded from order #%s', 'smart-gift-cards-for-woocommerce' ),
					$order->get_order_number()
				),
			] );
		}

		$order->update_meta_data( '_wcgc_restored', '1' );
		$order->save();
	}

	/**
	 * Handle partial refund — proportionally restore gift card balances.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function handle_partial_refund( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order || ! $refund ) {
			return;
		}

		// Skip if already fully restored (from cancellation).
		if ( $order->get_meta( '_wcgc_restored' ) ) {
			return;
		}

		// Only process if we deducted.
		if ( ! $order->get_meta( '_wcgc_deducted' ) ) {
			return;
		}

		$deductions = $order->get_meta( '_wcgc_pending_deductions' );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			return;
		}

		$refund_amount  = abs( (float) $refund->get_total() );
		$total_deducted = array_sum( array_map( 'floatval', $deductions ) );

		if ( $total_deducted <= 0 || $refund_amount <= 0 ) {
			return;
		}

		// Calculate proportional gift card restoration.
		$order_total_before_gc = (float) $order->get_total() + $total_deducted;
		if ( $order_total_before_gc <= 0 ) {
			return;
		}

		$gc_restore = min( $total_deducted, $refund_amount * ( $total_deducted / $order_total_before_gc ) );

		// Account for any previously partially restored amounts.
		$already_restored     = (float) $order->get_meta( '_wcgc_partial_restored' );
		$remaining_to_restore = $total_deducted - $already_restored;
		$gc_restore           = min( $gc_restore, $remaining_to_restore );

		if ( $gc_restore <= 0 ) {
			return;
		}

		// Distribute proportionally across gift cards.
		foreach ( $deductions as $code => $amount ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			$proportion = (float) $amount / $total_deducted;
			$restore    = round( $gc_restore * $proportion, 2 );
			if ( $restore <= 0 ) {
				continue;
			}

			// Cap at initial amount.
			$new_balance = min( (float) $gc->initial_amount, (float) $gc->balance + $restore );
			Repository::update_balance( $gc->id, $new_balance );

			if ( $gc->status === 'redeemed' && $new_balance > 0 ) {
				Repository::update_status( $gc->id, 'active' );
			}

			TransactionRepository::insert( [
				'gift_card_id'  => $gc->id,
				'order_id'      => $order_id,
				'type'          => 'refund',
				'amount'        => $restore,
				'balance_after' => $new_balance,
				'note'          => sprintf(
					/* translators: %s: order number */
					__( 'Partial refund from order #%s', 'smart-gift-cards-for-woocommerce' ),
					$order->get_order_number()
				),
			] );
		}

		$order->update_meta_data( '_wcgc_partial_restored', $already_restored + $gc_restore );
		$order->save();
	}

	/**
	 * Clear applied gift cards from session after order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function clear_session( $order ) {
		if ( WC()->session ) {
			WC()->session->set( 'wcgc_applied_codes', [] );
			WC()->session->set( 'wcgc_deduction_amounts', [] );
		}
	}
}
