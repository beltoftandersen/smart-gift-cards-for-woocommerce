<?php
/**
 * Smart Gift Cards for WooCommerce - Uninstall
 *
 * Runs when the plugin is deleted from WordPress admin.
 *
 * @package GiftCards
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Check if user opted in to cleanup.
$wcgc_options = get_option( 'wcgc_options', [] );
if ( ( $wcgc_options['cleanup_on_uninstall'] ?? '' ) !== '1' ) {
	return;
}

global $wpdb;

// Drop custom tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup; hardcoded table names.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcgc_transactions" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup; hardcoded table names.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcgc_gift_cards" );

// Delete plugin options.
delete_option( 'wcgc_options' );
delete_option( 'wcgc_db_version' );

// Delete all order meta (HPOS-compatible).
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
	&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup; HPOS orders meta table.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_wcgc_' ) . '%'
		)
	);
} else {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup; core postmeta table.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_wcgc_' ) . '%'
		)
	);
}

// Flush rewrite rules.
flush_rewrite_rules();
