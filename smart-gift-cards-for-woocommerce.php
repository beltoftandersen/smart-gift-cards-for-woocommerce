<?php
/**
 * Plugin Name:       Smart Gift Cards for WooCommerce
 * Plugin URI:        https://developer.wordpress.org/plugins/smart-gift-cards-for-woocommerce/
 * Description:       Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Chimkins IT
 * Author URI:        https://chimkins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-gift-cards-for-woocommerce
 * Domain Path:       /languages/
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.6
 *
 * @package GiftCards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── PSR-4 autoloader ──────────────────────────────────────────── */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'GiftCards\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'GiftCards\\' ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
		$file     = plugin_dir_path( __FILE__ ) . 'src/' . $relative;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* ── Constants ─────────────────────────────────────────────────── */
define( 'WCGC_VERSION', '1.0.0' );
define( 'WCGC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCGC_URL', plugin_dir_url( __FILE__ ) );
define( 'WCGC_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCGC_DB_VERSION', '1.0' );

/* ── Activation / deactivation ─────────────────────────────────── */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			/* translators: %s: WooCommerce plugin name */
			wp_die( esc_html__( 'Smart Gift Cards for WooCommerce requires WooCommerce to be installed and active.', 'smart-gift-cards-for-woocommerce' ) );
		}
		GiftCards\Support\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
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
		GiftCards\Plugin::init();
	}
);

/* ── Settings link on Plugins page ─────────────────────────────── */
add_filter(
	'plugin_action_links_' . WCGC_BASENAME,
	function ( $links ) {
		$url           = admin_url( 'admin.php?page=wcgc-gift-cards' );
		$settings_link = '<a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Settings', 'smart-gift-cards-for-woocommerce' )
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
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);
