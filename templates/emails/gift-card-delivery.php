<?php
/**
 * Gift Card Delivery Email (HTML).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/gift-card-delivery.php
 *
 * @package GiftCards
 * @var object   $gift_card     Gift card data.
 * @var WC_Order $order         Order object (may be null).
 * @var string   $email_heading Email heading.
 * @var bool     $sent_to_admin Whether sent to admin.
 * @var bool     $plain_text    Whether plain text.
 * @var WC_Email $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: sender name */
		esc_html__( 'From: %s', 'smart-gift-cards-for-woocommerce' ),
		esc_html( $gift_card->sender_name )
	);
	?>
</p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
	<p style="font-style: italic; color: #555; padding: 10px 20px; border-left: 3px solid #ddd; margin: 15px 0;">
		&ldquo;<?php echo esc_html( $gift_card->message ); ?>&rdquo;
	</p>
<?php endif; ?>

<?php
/**
 * Fires before the gift card design section in the email.
 *
 * @param object         $gift_card Gift card data.
 * @param WC_Order|null  $order     Order object.
 */
do_action( 'wcgc_email_before_card_design', $gift_card, $order );
?>

<div style="text-align: center; margin: 30px 0;">
	<p style="font-size: 32px; font-weight: bold; margin: 0 0 10px;">
		<?php echo wp_kses_post( wc_price( $gift_card->initial_amount, [ 'currency' => $gift_card->currency ] ) ); ?>
	</p>
	<div style="background: #f5f5f5; padding: 15px 25px; display: inline-block; border-radius: 6px; margin: 10px 0;">
		<span style="font-family: monospace; font-size: 20px; letter-spacing: 3px; font-weight: bold;">
			<?php echo esc_html( $gift_card->code ); ?>
		</span>
	</div>
	<?php if ( ! empty( $gift_card->expires_at ) ) : ?>
		<p style="font-size: 13px; color: #888; margin-top: 10px;">
			<?php
			printf(
				/* translators: %s: expiry date */
				esc_html__( 'Expires: %s', 'smart-gift-cards-for-woocommerce' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $gift_card->expires_at ) ) )
			);
			?>
		</p>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the gift card design section in the email.
 *
 * @param object         $gift_card Gift card data.
 * @param WC_Order|null  $order     Order object.
 */
do_action( 'wcgc_email_after_card_design', $gift_card, $order );
?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable, short-lived scope.
$base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
?>
<p style="text-align: center; margin: 25px 0;">
	<a href="<?php echo esc_url( add_query_arg( 'wcgc_apply', rawurlencode( $gift_card->code ), wc_get_page_permalink( 'shop' ) ) ); ?>"
	   style="display: inline-block; background: <?php echo esc_attr( $base_color ); ?>; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
		<?php esc_html_e( 'Shop Now', 'smart-gift-cards-for-woocommerce' ); ?>
	</a>
</p>

<p style="font-size: 13px; color: #888; text-align: center;">
	<?php esc_html_e( 'Click "Shop Now" to apply your gift card automatically, or enter the code at checkout in the coupon/gift card field.', 'smart-gift-cards-for-woocommerce' ); ?>
</p>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_footer', $email );
