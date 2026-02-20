<?php

namespace GiftCards\GiftCard;

defined( 'ABSPATH' ) || exit;

class TransactionRepository {

	/**
	 * Get the transactions table name.
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wcgc_transactions';
	}

	/**
	 * Insert a new transaction.
	 *
	 * @param array $data Transaction data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$defaults = [
			'gift_card_id' => 0,
			'order_id'     => null,
			'type'         => 'credit',
			'amount'       => 0,
			'balance_after' => 0,
			'note'         => '',
		];

		$data = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$result = $wpdb->insert( self::table(), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get transactions by gift card ID.
	 *
	 * @param int $gift_card_id Gift card ID.
	 * @return array
	 */
	public static function get_by_gift_card( $gift_card_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_transactions WHERE gift_card_id = %d ORDER BY created_at DESC",
				$gift_card_id
			)
		);
	}

	/**
	 * Get transactions by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_transactions WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);
	}

	/**
	 * Delete transactions by gift card ID.
	 *
	 * @param int $gift_card_id Gift card ID.
	 * @return bool
	 */
	public static function delete_by_gift_card( $gift_card_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (bool) $wpdb->delete( self::table(), [ 'gift_card_id' => $gift_card_id ], [ '%d' ] );
	}
}
