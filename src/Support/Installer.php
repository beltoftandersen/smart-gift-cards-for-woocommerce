<?php

namespace GiftCards\Support;

defined( 'ABSPATH' ) || exit;

class Installer {

	const DB_VERSION_KEY = 'wcgc_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::create_tables();

		if ( false === get_option( Options::OPTION ) ) {
			add_option( Options::OPTION, Options::defaults(), '', false );
		}

		update_option( self::DB_VERSION_KEY, WCGC_DB_VERSION );

		// Flush rewrite rules for My Account endpoint.
		add_rewrite_endpoint( 'gift-cards', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$gift_cards_table   = $wpdb->prefix . 'wcgc_gift_cards';
		$transactions_table = $wpdb->prefix . 'wcgc_transactions';

		$sql = "CREATE TABLE {$gift_cards_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			initial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			balance decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT '',
			sender_name varchar(255) NOT NULL DEFAULT '',
			sender_email varchar(255) NOT NULL DEFAULT '',
			recipient_name varchar(255) NOT NULL DEFAULT '',
			recipient_email varchar(255) NOT NULL DEFAULT '',
			message text,
			order_id bigint(20) unsigned DEFAULT NULL,
			customer_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY recipient_email (recipient_email),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY status (status)
		) {$charset};

		CREATE TABLE {$transactions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			gift_card_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			type varchar(20) NOT NULL DEFAULT 'credit',
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			balance_after decimal(10,2) NOT NULL DEFAULT 0.00,
			note varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY gift_card_id (gift_card_id),
			KEY order_id (order_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run on every load to handle upgrades.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::DB_VERSION_KEY, '0' );

		if ( version_compare( $installed, WCGC_DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::DB_VERSION_KEY, WCGC_DB_VERSION );
		}
	}
}
