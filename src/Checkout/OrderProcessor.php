<?php

namespace Bgcw\Checkout;

use Bgcw\Cart\CartHandler;
use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;

defined( 'ABSPATH' ) || exit;

class OrderProcessor {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Save pending deductions when order is created.
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'save_pending_deductions' ] );

		// Deduct balances on payment / status change (idempotency-guarded).
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'deduct_gift_card_balances' ] );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'deduct_gift_card_balances' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'deduct_gift_card_balances' ] );

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
	 * Reads gift card coupon discount amounts from the WC order's coupon items.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function save_pending_deductions( $order ) {
		$deductions = [];

		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			$code = strtoupper( $coupon_item->get_code() );
			if ( CartHandler::is_gift_card_coupon( $code ) ) {
				// Include coupon tax so stored deductions match the full order-level discount impact.
				$discount = round( (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax(), 2 );
				if ( $discount > 0 ) {
					$deductions[ $code ] = $discount;
				}
			}
		}

		if ( empty( $deductions ) ) {
			return;
		}

		$order->update_meta_data( '_bgcw_pending_deductions', $deductions );
		$order->save();
	}

	/**
	 * Deduct gift card balances for the order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function deduct_gift_card_balances( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Idempotency check.
		if ( $order->get_meta( '_bgcw_deducted' ) ) {
			return;
		}

		// Atomic advisory lock keyed by order ID. Timeout 0 = non-blocking (fail immediately if held).
		$lock_name = 'bgcw_deduct_' . (int) $order_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock, no table involved.
		$acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );
		if ( 1 !== $acquired ) {
			return;
		}

		try {
			// Re-check idempotency inside lock (another process may have completed while we waited).
			$order = wc_get_order( $order_id );
			if ( ! $order || $order->get_meta( '_bgcw_deducted' ) ) {
				return;
			}

			$deductions = $order->get_meta( '_bgcw_pending_deductions' );
			if ( empty( $deductions ) || ! is_array( $deductions ) ) {
				return;
			}

			$processed = $order->get_meta( '_bgcw_deducted_amounts' );
			$processed = is_array( $processed ) ? $processed : [];
			$failures  = [];

			foreach ( $deductions as $code => $amount ) {
				$amount = round( (float) $amount, 2 );
				if ( $amount <= 0 ) {
					continue;
				}

				if ( isset( $processed[ $code ] ) && (float) $processed[ $code ] >= $amount ) {
					continue;
				}

				$gc = Repository::find_by_code( $code );
				if ( ! $gc ) {
					$failures[] = $code;
					continue;
				}

				// Re-validate status and expiry at deduction time.
				if ( $gc->status !== 'active' ) {
					$failures[] = $code;
					continue;
				}
				if ( ! empty( $gc->expires_at ) && strtotime( $gc->expires_at ) < time() ) {
					$failures[] = $code;
					continue;
				}

				$old_balance = (float) $gc->balance;

				// Atomic balance deduction to prevent race conditions.
				if ( ! Repository::deduct_balance( $gc->id, $amount ) ) {
					$failures[] = $code;
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
						__( 'Used on order #%s', 'beltoft-gift-cards-for-woocommerce' ),
						$order->get_order_number()
					),
				] );

				// Mark as redeemed if balance is zero.
				if ( $new_balance <= 0 ) {
					Repository::update_status( $gc->id, 'redeemed' );
				}

				/**
				 * Fires after a gift card balance is deducted for an order.
				 *
				 * @param int   $gc_id    Gift card ID.
				 * @param float $amount   Amount deducted.
				 * @param int   $order_id Order ID.
				 */
				do_action( 'bgcw_after_deduct_balance', $gc->id, $amount, $order_id );

				$processed[ $code ] = $amount;
			}

			$order->update_meta_data( '_bgcw_deducted_amounts', $processed );

			$complete = true;
			foreach ( $deductions as $code => $amount ) {
				$amount = round( (float) $amount, 2 );
				if ( $amount <= 0 ) {
					continue;
				}
				if ( ! isset( $processed[ $code ] ) || (float) $processed[ $code ] < $amount ) {
					$complete = false;
					break;
				}
			}

			if ( $complete ) {
				$order->update_meta_data( '_bgcw_deducted', '1' );
				$order->delete_meta_data( '_bgcw_deduction_failures' );
			} else {
				$order->delete_meta_data( '_bgcw_deducted' );
				if ( ! empty( $failures ) ) {
					$order->update_meta_data( '_bgcw_deduction_failures', array_values( array_unique( $failures ) ) );
					$order->add_order_note(
						sprintf(
							/* translators: %s: comma-separated list of gift card codes */
							__( 'Gift card deduction failed for: %s. Manual review required.', 'beltoft-gift-cards-for-woocommerce' ),
							implode( ', ', array_unique( $failures ) )
						)
					);
				} else {
					$order->delete_meta_data( '_bgcw_deduction_failures' );
				}
			}

			$order->save();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock release.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
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
		if ( $order->get_meta( '_bgcw_restored' ) ) {
			return;
		}

		$deductions = self::get_effective_deductions( $order );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			return;
		}

		foreach ( $deductions as $code => $amount ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			$amount = round( (float) $amount, 2 );
			if ( $amount <= 0 ) {
				continue;
			}

			/**
			 * Filter whether this refund should create store credit instead.
			 *
			 * @param bool   $as_credit Whether to handle as store credit.
			 * @param int    $order_id  Order ID.
			 * @param int    $gc_id     Gift card ID.
			 * @param float  $amount    Amount to restore.
			 */
			if ( apply_filters( 'bgcw_refund_as_store_credit', false, $order_id, $gc->id, $amount ) ) {
				continue; // Pro plugin handles this via the filter.
			}

			$old_balance = (float) $gc->balance;

			// Cap restored balance at initial_amount.
			$new_balance = min( (float) $gc->initial_amount, $old_balance + $amount );
			$restored    = round( $new_balance - $old_balance, 2 );
			if ( $restored <= 0 ) {
				continue;
			}

			Repository::update_balance( $gc->id, $new_balance );

			// Reactivate if it was marked as redeemed.
			if ( $gc->status === 'redeemed' ) {
				Repository::update_status( $gc->id, 'active' );
			}

			TransactionRepository::insert( [
				'gift_card_id'  => $gc->id,
				'order_id'      => $order_id,
				'type'          => 'refund',
				'amount'        => $restored,
				'balance_after' => $new_balance,
				'note'          => sprintf(
					/* translators: %s: order number */
					__( 'Refunded from order #%s', 'beltoft-gift-cards-for-woocommerce' ),
					$order->get_order_number()
				),
			] );

			/**
			 * Fires after a gift card balance is restored from a refund.
			 *
			 * @param int   $gc_id    Gift card ID.
			 * @param float $restored Amount restored.
			 * @param int   $order_id Order ID.
			 */
			do_action( 'bgcw_after_restore_balance', $gc->id, $restored, $order_id );
		}

		$order->update_meta_data( '_bgcw_restored', '1' );
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
		if ( $order->get_meta( '_bgcw_restored' ) ) {
			return;
		}

		$deductions = self::get_effective_deductions( $order );
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
		$already_restored     = (float) $order->get_meta( '_bgcw_partial_restored' );
		$remaining_to_restore = $total_deducted - $already_restored;
		$gc_restore           = min( $gc_restore, $remaining_to_restore );
		$gc_restore           = round( $gc_restore, 2 );

		if ( $gc_restore <= 0 ) {
			return;
		}

		$allocations      = self::allocate_restore_amounts( $deductions, $gc_restore );
		$actual_restored  = 0.0;

		// Apply the calculated restore amounts.
		foreach ( $allocations as $code => $restore ) {
			$gc = Repository::find_by_code( $code );
			if ( ! $gc ) {
				continue;
			}

			if ( $restore <= 0 ) {
				continue;
			}

			$old_balance = (float) $gc->balance;

			// Cap at initial amount.
			$new_balance = min( (float) $gc->initial_amount, $old_balance + $restore );
			$restored    = round( $new_balance - $old_balance, 2 );
			if ( $restored <= 0 ) {
				continue;
			}

			Repository::update_balance( $gc->id, $new_balance );

			if ( $gc->status === 'redeemed' && $new_balance > 0 ) {
				Repository::update_status( $gc->id, 'active' );
			}

			TransactionRepository::insert( [
				'gift_card_id'  => $gc->id,
				'order_id'      => $order_id,
				'type'          => 'refund',
				'amount'        => $restored,
				'balance_after' => $new_balance,
				'note'          => sprintf(
					/* translators: %s: order number */
					__( 'Partial refund from order #%s', 'beltoft-gift-cards-for-woocommerce' ),
					$order->get_order_number()
				),
			] );

			$actual_restored += $restored;
		}

		if ( $actual_restored > 0 ) {
			$order->update_meta_data( '_bgcw_partial_restored', round( $already_restored + $actual_restored, 2 ) );
			$order->save();
		}
	}

	/**
	 * Clear applied gift cards from session after order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function clear_session( $order ) {
		if ( WC()->session ) {
			WC()->session->set( 'bgcw_applied_codes', [] );
		}
	}

	/**
	 * Get the deductions that were actually used for this order.
	 *
	 * Falls back to pending deductions only when the order was fully deducted
	 * (legacy orders before _bgcw_deducted_amounts was introduced).
	 *
	 * @param \WC_Order $order Order object.
	 * @return array<string,float>
	 */
	private static function get_effective_deductions( $order ) {
		$deductions = $order->get_meta( '_bgcw_deducted_amounts' );
		if ( empty( $deductions ) || ! is_array( $deductions ) ) {
			// Only fall back to pending if the deduction flag confirms balances were taken.
			// Without this guard, a cancel on a failed-deduction order would restore money never taken.
			if ( ! $order->get_meta( '_bgcw_deducted' ) ) {
				return [];
			}
			$deductions = $order->get_meta( '_bgcw_pending_deductions' );
		}

		$out = [];
		foreach ( (array) $deductions as $code => $amount ) {
			$amount = round( (float) $amount, 2 );
			if ( $amount > 0 ) {
				$out[ (string) $code ] = $amount;
			}
		}

		return $out;
	}

	/**
	 * Allocate a restore target across codes using cent-accurate rounding.
	 *
	 * @param array<string,float> $deductions Per-code deducted amounts.
	 * @param float               $target     Total amount to restore.
	 * @return array<string,float> Per-code restore amounts.
	 */
	private static function allocate_restore_amounts( $deductions, $target ) {
		$target_cents = (int) round( max( 0, (float) $target ) * 100 );
		if ( $target_cents <= 0 ) {
			return [];
		}

		$weights = [];
		foreach ( (array) $deductions as $code => $amount ) {
			$amount = round( (float) $amount, 2 );
			if ( $amount > 0 ) {
				$weights[ (string) $code ] = $amount;
			}
		}

		$total_weight = array_sum( $weights );
		if ( $total_weight <= 0 ) {
			return [];
		}

		$cents      = [];
		$remainders = [];
		$allocated  = 0;

		foreach ( $weights as $code => $weight ) {
			$raw_cents         = ( $weight / $total_weight ) * $target_cents;
			$base_cents        = (int) floor( $raw_cents );
			$cents[ $code ]    = $base_cents;
			$remainders[ $code ] = $raw_cents - $base_cents;
			$allocated        += $base_cents;
		}

		$leftover = $target_cents - $allocated;
		if ( $leftover > 0 ) {
			arsort( $remainders, SORT_NUMERIC );
			foreach ( array_keys( $remainders ) as $code ) {
				if ( $leftover <= 0 ) {
					break;
				}
				$cents[ $code ]++;
				$leftover--;
			}
		}

		$out = [];
		foreach ( $cents as $code => $value ) {
			if ( $value > 0 ) {
				$out[ $code ] = $value / 100;
			}
		}

		return $out;
	}
}
