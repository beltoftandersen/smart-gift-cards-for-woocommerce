<?php
/**
 * Plugin Name:       Beltoft Gift Cards for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/beltoft-gift-cards/
 * Description:       Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            beltoft.net
 * Author URI:        https://beltoft.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beltoft-gift-cards
 * Domain Path:       /languages/
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.6
 *
 * @package Bgcw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── PSR-4 autoloader ──────────────────────────────────────────── */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'Bgcw\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'Bgcw\\' ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
		$file     = plugin_dir_path( __FILE__ ) . 'src/' . $relative;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* ── Constants ─────────────────────────────────────────────────── */
define( 'BGCW_VERSION', '1.4.0' );
define( 'BGCW_PATH', plugin_dir_path( __FILE__ ) );
define( 'BGCW_URL', plugin_dir_url( __FILE__ ) );
define( 'BGCW_BASENAME', plugin_basename( __FILE__ ) );
define( 'BGCW_DB_VERSION', '1.1' );

/* ── Activation / deactivation ─────────────────────────────────── */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			/* translators: %s: WooCommerce plugin name */
			wp_die( esc_html__( 'Beltoft Gift Cards for WooCommerce requires WooCommerce to be installed and active.', 'beltoft-gift-cards' ) );
		}
		Bgcw\Support\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'bgcw_expiry_sync' );
		flush_rewrite_rules();
	}
);

/* ── Bootstrap ─────────────────────────────────────────────────── */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Bgcw\Plugin::init();
	}
);

/* ── Settings link on Plugins page ─────────────────────────────── */
add_filter(
	'plugin_action_links_' . BGCW_BASENAME,
	function ( $links ) {
		$url           = admin_url( 'admin.php?page=bgcw-gift-cards' );
		$settings_link = '<a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Settings', 'beltoft-gift-cards' )
			. '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/* ── HPOS compatibility ────────────────────────────────────────── */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
