<?php

namespace GiftCards\Cart;

use GiftCards\Support\Options;

defined( 'ABSPATH' ) || exit;

class GiftCardField {

	/** @var bool Guard against duplicate rendering from hooks. */
	private static $rendered = false;

	/**
	 * Initialize hooks based on settings.
	 */
	public static function init() {
		// Always register the shortcode (so it works if someone types it).
		add_shortcode( 'wcgc_apply_field', [ __CLASS__, 'shortcode_output' ] );

		$show = Options::get( 'show_dedicated_field' );
		if ( $show !== '1' ) {
			return;
		}

		$placement = Options::get( 'dedicated_field_placement' );

		if ( $placement === 'auto' || $placement === 'both' ) {
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
		if ( ! $force && self::$rendered ) {
			return;
		}
		self::$rendered = true;

		$applied = CartHandler::get_applied_codes();
		?>
		<div class="wcgc-apply-field">
			<h3><?php esc_html_e( 'Have a gift card?', 'smart-gift-cards-for-woocommerce' ); ?></h3>

			<?php if ( ! empty( $applied ) ) : ?>
				<div class="wcgc-applied-list">
					<?php foreach ( $applied as $index => $code ) : ?>
						<?php $gc = \GiftCards\GiftCard\Repository::find_by_code( $code ); ?>
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
