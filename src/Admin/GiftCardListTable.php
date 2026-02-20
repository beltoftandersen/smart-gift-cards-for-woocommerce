<?php

namespace GiftCards\Admin;

use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GiftCardListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'gift_card',
			'plural'   => 'gift_cards',
			'ajax'     => false,
		] );
	}

	/**
	 * Define columns.
	 */
	public function get_columns() {
		return [
			'cb'         => '<input type="checkbox" />',
			'code'       => __( 'Code', 'smart-gift-cards-for-woocommerce' ),
			'amount'     => __( 'Amount', 'smart-gift-cards-for-woocommerce' ),
			'balance'    => __( 'Balance', 'smart-gift-cards-for-woocommerce' ),
			'status'     => __( 'Status', 'smart-gift-cards-for-woocommerce' ),
			'recipient'  => __( 'Recipient', 'smart-gift-cards-for-woocommerce' ),
			'order'      => __( 'Order', 'smart-gift-cards-for-woocommerce' ),
			'created_at' => __( 'Created', 'smart-gift-cards-for-woocommerce' ),
			'expires_at' => __( 'Expires', 'smart-gift-cards-for-woocommerce' ),
		];
	}

	/**
	 * Sortable columns.
	 */
	public function get_sortable_columns() {
		return [
			'code'       => [ 'code', false ],
			'balance'    => [ 'balance', false ],
			'status'     => [ 'status', false ],
			'created_at' => [ 'created_at', true ],
			'expires_at' => [ 'expires_at', false ],
		];
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="gift_card_ids[]" value="%d" />', $item->id );
	}

	/**
	 * Code column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_code( $item ) {
		return '<code>' . esc_html( $item->code ) . '</code>';
	}

	/**
	 * Amount column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_amount( $item ) {
		return wp_kses_post( wc_price( $item->initial_amount, [ 'currency' => $item->currency ] ) );
	}

	/**
	 * Balance column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_balance( $item ) {
		return wp_kses_post( wc_price( $item->balance, [ 'currency' => $item->currency ] ) );
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_status( $item ) {
		$labels = [
			'active'   => __( 'Active', 'smart-gift-cards-for-woocommerce' ),
			'disabled' => __( 'Disabled', 'smart-gift-cards-for-woocommerce' ),
			'expired'  => __( 'Expired', 'smart-gift-cards-for-woocommerce' ),
			'redeemed' => __( 'Redeemed', 'smart-gift-cards-for-woocommerce' ),
		];
		$label = $labels[ $item->status ] ?? $item->status;
		return '<span class="wcgc-status wcgc-status--' . esc_attr( $item->status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Recipient column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_recipient( $item ) {
		$parts = [];
		if ( ! empty( $item->recipient_name ) ) {
			$parts[] = esc_html( $item->recipient_name );
		}
		if ( ! empty( $item->recipient_email ) ) {
			$parts[] = '<small>' . esc_html( $item->recipient_email ) . '</small>';
		}
		return implode( '<br>', $parts ) ?: '&mdash;';
	}

	/**
	 * Order column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_order( $item ) {
		if ( empty( $item->order_id ) ) {
			return __( 'Manual', 'smart-gift-cards-for-woocommerce' );
		}
		$order = wc_get_order( $item->order_id );
		if ( ! $order ) {
			return '#' . esc_html( $item->order_id );
		}
		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
	}

	/**
	 * Created column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );
	}

	/**
	 * Expires column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_expires_at( $item ) {
		if ( empty( $item->expires_at ) ) {
			return __( 'Never', 'smart-gift-cards-for-woocommerce' );
		}
		$expired = strtotime( $item->expires_at ) < time();
		$date    = date_i18n( get_option( 'date_format' ), strtotime( $item->expires_at ) );
		return $expired ? '<span style="color:#d63638;">' . esc_html( $date ) . '</span>' : esc_html( $date );
	}

	/**
	 * Bulk actions.
	 */
	public function get_bulk_actions() {
		return [
			'disable' => __( 'Disable', 'smart-gift-cards-for-woocommerce' ),
			'delete'  => __( 'Delete', 'smart-gift-cards-for-woocommerce' ),
		];
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// Verify the bulk-action nonce.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$ids = isset( $_REQUEST['gift_card_ids'] ) ? array_map( 'absint', (array) $_REQUEST['gift_card_ids'] ) : [];
		if ( empty( $ids ) ) {
			return;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			if ( 'disable' === $action ) {
				if ( Repository::update_status( $id, 'disabled' ) ) {
					$count++;
				}
			} elseif ( 'delete' === $action ) {
				\GiftCards\GiftCard\TransactionRepository::delete_by_gift_card( $id );
				if ( Repository::delete( $id ) ) {
					$count++;
				}
			}
		}

		if ( $count > 0 ) {
			$message = 'disable' === $action
				? sprintf(
					/* translators: %d: number of gift cards disabled */
					_n( '%d gift card disabled.', '%d gift cards disabled.', $count, 'smart-gift-cards-for-woocommerce' ),
					$count
				)
				: sprintf(
					/* translators: %d: number of gift cards deleted */
					_n( '%d gift card deleted.', '%d gift cards deleted.', $count, 'smart-gift-cards-for-woocommerce' ),
					$count
				);
			add_settings_error( 'wcgc_messages', 'wcgc_bulk', $message, 'success' );
		}
	}

	/**
	 * Status filter views.
	 */
	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status filter.
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		$base_url = admin_url( 'admin.php?page=' . SettingsPage::SLUG . '&tab=gift-cards' );
		$statuses = [
			''         => __( 'All', 'smart-gift-cards-for-woocommerce' ),
			'active'   => __( 'Active', 'smart-gift-cards-for-woocommerce' ),
			'redeemed' => __( 'Redeemed', 'smart-gift-cards-for-woocommerce' ),
			'disabled' => __( 'Disabled', 'smart-gift-cards-for-woocommerce' ),
			'expired'  => __( 'Expired', 'smart-gift-cards-for-woocommerce' ),
		];

		// Single GROUP BY query instead of 5 separate COUNT queries.
		$counts = Repository::get_status_counts();

		$views = [];
		foreach ( $statuses as $slug => $label ) {
			$url     = $slug ? add_query_arg( 'status', $slug, $base_url ) : $base_url;
			$class   = $current === $slug ? 'current' : '';
			$count   = $counts[ $slug ] ?? 0;
			$views[ $slug ?: 'all' ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label ),
				number_format_i18n( $count )
			);
		}

		return $views;
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$per_page = 20;
		$page_num = $this->get_pagenum();
		$offset   = ( $page_num - 1 ) * $per_page;

		$args = [
			'per_page' => $per_page,
			'offset'   => $offset,
		];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list table display.
		if ( ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_key( wp_unslash( $_GET['status'] ) );
		}

		if ( ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_key( wp_unslash( $_GET['orderby'] ) );
		}
		if ( isset( $_GET['order'] ) ) {
			$args['order'] = sanitize_key( wp_unslash( $_GET['order'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->items = Repository::get_all_paginated( $args );
		$total       = Repository::count_all( $args );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	/**
	 * No items message.
	 */
	public function no_items() {
		esc_html_e( 'No gift cards found.', 'smart-gift-cards-for-woocommerce' );
	}
}
