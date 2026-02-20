<?php

namespace GiftCards\Frontend;

use GiftCards\Support\Options;

defined( 'ABSPATH' ) || exit;

class ProductPage {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Only auto-render fields when placement is 'auto' (not shortcode-only).
		if ( Options::get( 'product_form_placement' ) !== 'shortcode' ) {
			add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'render_fields' ] );
		}
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate' ], 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'set_cart_price' ] );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );

		// Shortcode for page builders (Bricks, Elementor, etc.).
		add_shortcode( 'wcgc_product_form', [ __CLASS__, 'shortcode_output' ] );
	}

	/**
	 * Shortcode to render gift card fields for page builders.
	 *
	 * Usage: [wcgc_product_form] on a single product page.
	 *
	 * @return string
	 */
	public static function shortcode_output() {
		global $product;
		if ( ! $product || $product->get_type() !== 'gift-card' ) {
			return '';
		}

		ob_start();
		self::render_fields();
		return ob_get_clean();
	}

	/**
	 * Render amount selector and recipient fields on product page.
	 */
	public static function render_fields() {
		global $product;

		if ( ! $product || $product->get_type() !== 'gift-card' ) {
			return;
		}

		$amounts_str = get_post_meta( $product->get_id(), '_wcgc_amounts', true );
		if ( empty( $amounts_str ) ) {
			$amounts_str = Options::get( 'predefined_amounts' );
		}

		$amounts       = array_filter( array_map( 'floatval', explode( ',', $amounts_str ) ) );
		$allow_custom  = Options::get( 'allow_custom_amount' ) === '1';
		$min_custom    = (float) Options::get( 'min_custom_amount' );
		$max_custom    = (float) Options::get( 'max_custom_amount' );

		// Guard: no amounts and no custom amount means nothing to sell.
		if ( empty( $amounts ) && ! $allow_custom ) {
			echo '<p class="wcgc-no-amounts">' . esc_html__( 'This gift card is not currently available for purchase.', 'smart-gift-cards-for-woocommerce' ) . '</p>';
			return;
		}
		?>
		<div class="wcgc-product-fields">
			<div class="wcgc-amount-selector">
				<label><?php esc_html_e( 'Amount', 'smart-gift-cards-for-woocommerce' ); ?></label>
				<div class="wcgc-amounts">
					<?php foreach ( $amounts as $amount ) : ?>
						<button type="button" class="wcgc-amount-btn" data-amount="<?php echo esc_attr( $amount ); ?>">
							<?php echo wp_kses_post( wc_price( $amount ) ); ?>
						</button>
					<?php endforeach; ?>
					<?php if ( $allow_custom ) : ?>
						<button type="button" class="wcgc-amount-btn wcgc-custom-btn">
							<?php esc_html_e( 'Custom', 'smart-gift-cards-for-woocommerce' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<input type="hidden" name="wcgc_amount" id="wcgc_amount" value="<?php echo esc_attr( ! empty( $amounts ) ? $amounts[0] : '' ); ?>" />
				<?php if ( $allow_custom ) : ?>
					<div class="wcgc-custom-amount" style="display:none;">
						<label for="wcgc_custom_amount">
							<?php
							printf(
								/* translators: 1: minimum amount, 2: maximum amount */
								esc_html__( 'Enter amount (%1$s – %2$s)', 'smart-gift-cards-for-woocommerce' ),
								wp_kses_post( wc_price( $min_custom ) ),
								wp_kses_post( wc_price( $max_custom ) )
							);
							?>
						</label>
						<input type="number" name="wcgc_custom_amount" id="wcgc_custom_amount"
							min="<?php echo esc_attr( $min_custom ); ?>"
							max="<?php echo esc_attr( $max_custom ); ?>"
							step="0.01" />
					</div>
				<?php endif; ?>
			</div>

			<div class="wcgc-recipient-fields">
				<h4><?php esc_html_e( 'Recipient Details', 'smart-gift-cards-for-woocommerce' ); ?></h4>
				<p class="form-row form-row-first">
					<label for="wcgc_recipient_name"><?php esc_html_e( 'Recipient Name', 'smart-gift-cards-for-woocommerce' ); ?></label>
					<input type="text" name="wcgc_recipient_name" id="wcgc_recipient_name" class="input-text" />
				</p>
				<p class="form-row form-row-last">
					<label for="wcgc_recipient_email"><?php esc_html_e( 'Recipient Email', 'smart-gift-cards-for-woocommerce' ); ?> <abbr class="required" title="required">*</abbr></label>
					<input type="email" name="wcgc_recipient_email" id="wcgc_recipient_email" class="input-text" required />
				</p>
				<p class="form-row form-row-wide">
					<label for="wcgc_message"><?php esc_html_e( 'Personal Message (optional)', 'smart-gift-cards-for-woocommerce' ); ?></label>
					<textarea name="wcgc_message" id="wcgc_message" rows="3" class="input-text"></textarea>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate gift card fields.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public static function validate( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->get_type() !== 'gift-card' ) {
			return $passed;
		}

		// Nonce is handled by WooCommerce add to cart form.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$amount        = isset( $_POST['wcgc_amount'] ) ? (float) $_POST['wcgc_amount'] : 0;
		$custom_amount = ! empty( $_POST['wcgc_custom_amount'] ) ? (float) $_POST['wcgc_custom_amount'] : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$allow_custom = Options::get( 'allow_custom_amount' ) === '1';

		// Determine which amount to use.
		if ( $custom_amount > 0 && $allow_custom ) {
			$amount = $custom_amount;
		}

		if ( $amount <= 0 ) {
			wc_add_notice( __( 'Please select a gift card amount.', 'smart-gift-cards-for-woocommerce' ), 'error' );
			return false;
		}

		// Validate: amount must be a predefined amount OR a valid custom amount.
		$amounts_str = get_post_meta( $product_id, '_wcgc_amounts', true );
		if ( empty( $amounts_str ) ) {
			$amounts_str = Options::get( 'predefined_amounts' );
		}
		$predefined = array_filter( array_map( 'floatval', explode( ',', $amounts_str ) ) );
		$is_predefined = in_array( $amount, $predefined, true );

		if ( ! $is_predefined ) {
			// Must be a valid custom amount.
			if ( ! $allow_custom ) {
				wc_add_notice( __( 'Please select a valid gift card amount.', 'smart-gift-cards-for-woocommerce' ), 'error' );
				return false;
			}

			$min = (float) Options::get( 'min_custom_amount' );
			$max = (float) Options::get( 'max_custom_amount' );
			if ( $amount < $min || $amount > $max ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: minimum amount, 2: maximum amount */
						__( 'Gift card amount must be between %1$s and %2$s.', 'smart-gift-cards-for-woocommerce' ),
						wc_price( $min ),
						wc_price( $max )
					),
					'error'
				);
				return false;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['wcgc_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['wcgc_recipient_email'] ) ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			wc_add_notice( __( 'Please enter a valid recipient email address.', 'smart-gift-cards-for-woocommerce' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add gift card data to cart item.
	 *
	 * @param array $cart_data Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_cart_data( $cart_data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->get_type() !== 'gift-card' ) {
			return $cart_data;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$amount = isset( $_POST['wcgc_amount'] ) ? (float) $_POST['wcgc_amount'] : 0;
		if ( ! empty( $_POST['wcgc_custom_amount'] ) && Options::get( 'allow_custom_amount' ) === '1' ) {
			$amount = (float) $_POST['wcgc_custom_amount'];
		}

		$cart_data['wcgc_amount']          = $amount;
		$cart_data['wcgc_recipient_name']  = isset( $_POST['wcgc_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgc_recipient_name'] ) ) : '';
		$cart_data['wcgc_recipient_email'] = isset( $_POST['wcgc_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['wcgc_recipient_email'] ) ) : '';
		$cart_data['wcgc_message']         = isset( $_POST['wcgc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcgc_message'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $cart_data;
	}

	/**
	 * Display gift card data in cart.
	 *
	 * @param array $item_data Cart item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_cart_data( $item_data, $cart_item ) {
		if ( ! isset( $cart_item['wcgc_amount'] ) ) {
			return $item_data;
		}

		$item_data[] = [
			'key'   => __( 'Gift Card Amount', 'smart-gift-cards-for-woocommerce' ),
			'value' => wc_price( $cart_item['wcgc_amount'] ),
		];

		if ( ! empty( $cart_item['wcgc_recipient_email'] ) ) {
			$item_data[] = [
				'key'   => __( 'Recipient', 'smart-gift-cards-for-woocommerce' ),
				'value' => esc_html( $cart_item['wcgc_recipient_email'] ),
			];
		}

		if ( ! empty( $cart_item['wcgc_recipient_name'] ) ) {
			$item_data[] = [
				'key'   => __( 'Recipient Name', 'smart-gift-cards-for-woocommerce' ),
				'value' => esc_html( $cart_item['wcgc_recipient_name'] ),
			];
		}

		return $item_data;
	}

	/**
	 * Set cart item price to the selected amount.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public static function set_cart_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['wcgc_amount'] ) && $cart_item['wcgc_amount'] > 0 ) {
				$cart_item['data']->set_price( $cart_item['wcgc_amount'] );
			}
		}
	}

	/**
	 * Save gift card data to order line item meta.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item data.
	 * @param \WC_Order              $order         Order object.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['wcgc_amount'] ) ) {
			$item->add_meta_data( '_wcgc_amount', $values['wcgc_amount'] );
			$item->add_meta_data( '_wcgc_recipient_name', $values['wcgc_recipient_name'] ?? '' );
			$item->add_meta_data( '_wcgc_recipient_email', $values['wcgc_recipient_email'] ?? '' );
			$item->add_meta_data( '_wcgc_message', $values['wcgc_message'] ?? '' );
			$item->add_meta_data( '_wcgc_sender_name', $order->get_billing_first_name() );
			$item->add_meta_data( '_wcgc_sender_email', $order->get_billing_email() );
		}
	}
}
