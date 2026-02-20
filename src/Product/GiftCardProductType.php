<?php

namespace GiftCards\Product;

defined( 'ABSPATH' ) || exit;

class GiftCardProductType {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Register product type class.
		add_filter( 'woocommerce_product_class', [ __CLASS__, 'product_class' ], 10, 2 );

		// Add to product type selector.
		add_filter( 'product_type_selector', [ __CLASS__, 'add_to_selector' ] );

		// Register the add-to-cart template for gift-card product type.
		add_action( 'woocommerce_gift-card_add_to_cart', [ __CLASS__, 'add_to_cart_template' ] );

		// Admin: add data panel.
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'data_tabs' ] );
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'data_panel' ] );
		add_action( 'woocommerce_process_product_meta_gift-card', [ __CLASS__, 'save_meta' ] );

		// Hide irrelevant tabs for gift card products.
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'hide_tabs' ] );

		// Show/hide price fields: gift cards use amounts from options, not regular price.
		add_action( 'admin_footer', [ __CLASS__, 'admin_js' ] );
	}

	/**
	 * Map 'gift-card' type to our product class.
	 *
	 * @param string $classname Product class name.
	 * @param string $type      Product type.
	 * @return string
	 */
	public static function product_class( $classname, $type ) {
		if ( $type === 'gift-card' ) {
			return WC_Product_Gift_Card::class;
		}
		return $classname;
	}

	/**
	 * Add gift card to product type dropdown.
	 *
	 * @param array $types Existing product types.
	 * @return array
	 */
	public static function add_to_selector( $types ) {
		$types['gift-card'] = __( 'Gift card', 'smart-gift-cards-for-woocommerce' );
		return $types;
	}

	/**
	 * Render the add-to-cart form for gift card products.
	 *
	 * This is the template action that WooCommerce fires for each product type.
	 * It loads the simple product add-to-cart template which contains the
	 * woocommerce_before_add_to_cart_button hook where our fields render.
	 *
	 * When product form placement is set to "shortcode", we still load the
	 * simple template so the add-to-cart button works — the fields themselves
	 * are controlled by ProductPage::init().
	 */
	public static function add_to_cart_template() {
		wc_get_template( 'single-product/add-to-cart/simple.php' );
	}

	/**
	 * Add Gift Card data tab.
	 *
	 * @param array $tabs Data tabs.
	 * @return array
	 */
	public static function data_tabs( $tabs ) {
		$tabs['gift_card'] = [
			'label'    => __( 'Gift Card', 'smart-gift-cards-for-woocommerce' ),
			'target'   => 'gift_card_product_data',
			'class'    => [ 'show_if_gift-card' ],
			'priority' => 11,
		];
		return $tabs;
	}

	/**
	 * Hide shipping and inventory tabs for gift cards.
	 *
	 * @param array $tabs Data tabs.
	 * @return array
	 */
	public static function hide_tabs( $tabs ) {
		if ( isset( $tabs['shipping'] ) ) {
			$tabs['shipping']['class'][] = 'hide_if_gift-card';
		}
		return $tabs;
	}

	/**
	 * Render the Gift Card data panel.
	 */
	public static function data_panel() {
		global $post;

		$product_id = $post ? $post->ID : 0;
		$amounts    = get_post_meta( $product_id, '_wcgc_amounts', true );
		if ( empty( $amounts ) ) {
			$amounts = \GiftCards\Support\Options::get( 'predefined_amounts' );
		}
		?>
		<div id="gift_card_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input( [
					'id'          => '_wcgc_amounts',
					'value'       => $amounts,
					'label'       => __( 'Predefined Amounts', 'smart-gift-cards-for-woocommerce' ),
					'description' => __( 'Comma-separated amounts (e.g., 25,50,75,100).', 'smart-gift-cards-for-woocommerce' ),
					'desc_tip'    => true,
				] );
				?>
			</div>
			<div class="options_group">
				<p class="form-field">
					<em><?php esc_html_e( 'Gift cards are always virtual and non-taxable. Custom amount and expiry settings are controlled from the Gift Cards settings page.', 'smart-gift-cards-for-woocommerce' ); ?></em>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save gift card product meta.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_meta( $product_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
		$amounts = isset( $_POST['_wcgc_amounts'] ) ? sanitize_text_field( wp_unslash( $_POST['_wcgc_amounts'] ) ) : '';
		update_post_meta( $product_id, '_wcgc_amounts', $amounts );
	}

	/**
	 * Admin JS to show/hide panels for gift card product type.
	 */
	public static function admin_js() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'product' ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(function($) {
			var showHide = function() {
				var isGiftCard = $('select#product-type').val() === 'gift-card';
				if (isGiftCard) {
					$('.pricing').hide();
					$('#gift_card_product_data').show();
				} else {
					$('#gift_card_product_data').hide();
				}
			};
			$('select#product-type').on('change', showHide);
			showHide();
		});
		</script>
		<?php
	}
}
