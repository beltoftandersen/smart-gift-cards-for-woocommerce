<?php

namespace GiftCards\Cart;

use GiftCards\Support\Options;
use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class GiftCardField {

	/** @var bool Guard against duplicate rendering from hooks. */
	private static $rendered = false;

	/**
	 * Initialize hooks based on settings.
	 */
	public static function init() {
		$show = Options::get( 'show_dedicated_field' );
		if ( $show !== '1' ) {
			return;
		}

		$placement = Options::get( 'dedicated_field_placement' );

		// Mutually exclusive: automatic OR shortcode, not both.
		if ( $placement === 'shortcode' ) {
			add_shortcode( 'wcgc_apply_field', [ __CLASS__, 'shortcode_output' ] );
		} else {
			// Multiple hooks for compatibility with page builders (Bricks, Elementor, etc.)
			// that replace WooCommerce templates. The $rendered guard prevents duplicates.
			add_action( 'woocommerce_before_cart', [ __CLASS__, 'render_form' ] );
			add_action( 'woocommerce_before_cart_totals', [ __CLASS__, 'render_form' ] );
			add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_form' ], 15 );
		}
	}

	/**
	 * Shortcode output — always renders (bypasses hook guard).
	 *
	 * @return string
	 */
	public static function shortcode_output() {
		ob_start();
		self::render_form( true );
		return ob_get_clean();
	}

	/**
	 * Render the gift card application form.
	 *
	 * @param bool $force Force rendering (bypass hook guard).
	 */
	public static function render_form( $force = false ) {
		$force = ( true === $force );
		if ( ! $force && self::$rendered ) {
			return;
		}
		self::$rendered = true;

		// Get applied gift card codes from WC cart coupons.
		$applied = [];
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $code ) {
				if ( CartHandler::is_gift_card_coupon( $code ) ) {
					$applied[] = $code;
				}
			}
		}
		?>
		<div class="wcgc-apply-field">
			<h3><?php esc_html_e( 'Have a gift card?', 'smart-gift-cards-for-woocommerce' ); ?></h3>

			<?php if ( ! empty( $applied ) ) : ?>
				<div class="wcgc-applied-list">
					<?php foreach ( $applied as $index => $code ) : ?>
						<?php $gc = Repository::find_by_code( $code ); ?>
						<?php if ( $gc ) : ?>
							<div class="wcgc-applied-item">
								<span class="wcgc-applied-code"><?php echo esc_html( CartHandler::mask_code( $code ) ); ?></span>
								<span class="wcgc-applied-balance"><?php echo wp_kses_post( wc_price( $gc->balance, [ 'currency' => $gc->currency ] ) ); ?></span>
								<button type="button" class="wcgc-ajax-remove" data-index="<?php echo esc_attr( $index ); ?>">&times;</button>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="wcgc-apply-form">
				<input type="text" class="wcgc-code-input" placeholder="<?php esc_attr_e( 'Gift card code', 'smart-gift-cards-for-woocommerce' ); ?>" />
				<button type="button" class="button wcgc-apply-btn"><?php esc_html_e( 'Apply', 'smart-gift-cards-for-woocommerce' ); ?></button>
			</div>
			<div class="wcgc-field-notice" style="display:none;"></div>
		</div>
		<?php
	}
}
