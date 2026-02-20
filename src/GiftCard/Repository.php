<?php

namespace GiftCards\GiftCard;

defined( 'ABSPATH' ) || exit;

class Repository {

	/**
	 * Get the gift cards table name.
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wcgc_gift_cards';
	}

	/**
	 * Insert a new gift card.
	 *
	 * @param array $data Gift card data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$defaults = [
			'code'            => '',
			'initial_amount'  => 0,
			'balance'         => 0,
			'currency'        => get_woocommerce_currency(),
			'sender_name'     => '',
			'sender_email'    => '',
			'recipient_name'  => '',
			'recipient_email' => '',
			'message'         => '',
			'order_id'        => null,
			'customer_id'     => null,
			'status'          => 'active',
			'expires_at'      => null,
		];

		$data = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$result = $wpdb->insert( self::table(), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Find a gift card by code.
	 *
	 * @param string $code Gift card code.
	 * @return object|null
	 */
	public static function find_by_code( $code ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards WHERE code = %s",
				$code
			)
		);
	}

	/**
	 * Find a gift card by ID.
	 *
	 * @param int $id Gift card ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get gift cards by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards WHERE order_id = %d ORDER BY id ASC",
				$order_id
			)
		);
	}

	/**
	 * Get gift cards by customer ID (purchased).
	 *
	 * @param int $customer_id Customer/user ID.
	 * @return array
	 */
	public static function get_by_customer( $customer_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards WHERE customer_id = %d ORDER BY created_at DESC",
				$customer_id
			)
		);
	}

	/**
	 * Get gift cards by recipient email.
	 *
	 * @param string $email Recipient email.
	 * @return array
	 */
	public static function get_by_recipient( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards WHERE recipient_email = %s ORDER BY created_at DESC",
				$email
			)
		);
	}

	/**
	 * Update gift card balance.
	 *
	 * @param int   $id      Gift card ID.
	 * @param float $balance New balance.
	 * @return bool
	 */
	public static function update_balance( $id, $balance ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (bool) $wpdb->update(
			self::table(),
			[ 'balance' => $balance ],
			[ 'id' => $id ],
			[ '%f' ],
			[ '%d' ]
		);
	}

	/**
	 * Atomically deduct from gift card balance.
	 *
	 * Uses a single UPDATE query to prevent race conditions from
	 * concurrent redemptions on the same gift card.
	 *
	 * @param int   $id     Gift card ID.
	 * @param float $amount Amount to deduct.
	 * @return bool True if balance was deducted.
	 */
	public static function deduct_balance( $id, $amount ) {
		global $wpdb;

		$amount = abs( (float) $amount );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic balance deduction on custom table.
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wcgc_gift_cards SET balance = GREATEST(0, balance - %f) WHERE id = %d AND balance > 0",
				$amount,
				$id
			)
		);

		return false !== $rows && $rows > 0;
	}

	/**
	 * Valid gift card statuses.
	 */
	const VALID_STATUSES = [ 'active', 'disabled', 'expired', 'redeemed' ];

	/**
	 * Update gift card status.
	 *
	 * @param int    $id     Gift card ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (bool) $wpdb->update(
			self::table(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Build WHERE clause and parameter values from filter args.
	 *
	 * @param array $args Query args with optional 'status' and 'search' keys.
	 * @return array { string $sql, array $values }
	 */
	private static function build_where( $args ) {
		global $wpdb;

		$clauses = [];
		$values  = [];

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$values[]  = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$clauses[] = '(code LIKE %s OR recipient_email LIKE %s OR sender_email LIKE %s)';
			$like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}

		$sql = ! empty( $clauses ) ? 'WHERE ' . implode( ' AND ', $clauses ) : '';

		return [ $sql, $values ];
	}

	/**
	 * Get paginated gift cards for admin list.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_all_paginated( $args = [] ) {
		global $wpdb;

		$defaults = [
			'per_page' => 20,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'status'   => '',
			'search'   => '',
		];

		$args = wp_parse_args( $args, $defaults );

		list( $where_sql, $values ) = self::build_where( $args );

		$allowed_orderby = [ 'id', 'code', 'balance', 'initial_amount', 'status', 'created_at', 'expires_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$values[] = (int) $args['per_page'];
		$values[] = (int) $args['offset'];

		// phpcs:disable WordPress.DB -- Dynamic WHERE + ORDER BY built from validated values; splat operator for prepare().
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_gift_cards {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				...$values
			)
		);
		// phpcs:enable WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Count gift cards for admin list.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public static function count_all( $args = [] ) {
		global $wpdb;

		list( $where_sql, $values ) = self::build_where( $args );

		if ( ! empty( $values ) ) {
			// phpcs:disable WordPress.DB -- Dynamic WHERE built from validated values; splat operator for prepare().
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wcgc_gift_cards {$where_sql}",
					...$values
				)
			);
			// phpcs:enable WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, no user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcgc_gift_cards" );
	}

	/**
	 * Get summary stats for dashboard.
	 *
	 * @return array
	 */
	public static function get_summary_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, no user input.
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_issued,
				COALESCE(SUM(CASE WHEN status = 'active' THEN balance ELSE 0 END), 0) as outstanding_balance,
				COALESCE(SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END), 0) as total_redeemed,
				COALESCE(SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END), 0) as total_expired
			FROM {$wpdb->prefix}wcgc_gift_cards"
		);

		return [
			'total_issued'        => (int) ( $row->total_issued ?? 0 ),
			'outstanding_balance' => (float) ( $row->outstanding_balance ?? 0 ),
			'total_redeemed'      => (int) ( $row->total_redeemed ?? 0 ),
			'total_expired'       => (int) ( $row->total_expired ?? 0 ),
		];
	}

	/**
	 * Delete a gift card by ID.
	 *
	 * @param int $id Gift card ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
	}
}
