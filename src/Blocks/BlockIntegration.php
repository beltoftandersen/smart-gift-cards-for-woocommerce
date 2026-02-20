<?php

namespace GiftCards\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Blocks integration.
 *
 * Registers a small JS that shows a notice in the blocks checkout
 * when loyalty point redemption is blocked because the cart contains
 * a gift card product.
 */
class BlockIntegration implements IntegrationInterface {

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'smart-gift-cards-for-woocommerce';
	}

	/**
	 * Bootstrap the integration.
	 */
	public function initialize() {
		$script_url  = WCGC_URL . 'assets/js/blocks/gift-card-blocks.js';
		$script_path = WCGC_PATH . 'assets/js/blocks/gift-card-blocks.js';
		$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : WCGC_VERSION;

		wp_register_script(
			'wcgc-blocks',
			$script_url,
			[ 'wp-element', 'wp-plugins', 'wp-data', 'wc-blocks-checkout' ],
			$version,
			true
		);
	}

	/**
	 * Frontend script handles.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'wcgc-blocks' ];
	}

	/**
	 * Editor script handles.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
	 * Script data injected into the page.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [];
	}
}
