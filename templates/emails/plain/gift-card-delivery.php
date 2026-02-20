<?php
/**
 * Gift Card Delivery Email (Plain Text).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/gift-card-delivery.php
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

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
	/* translators: %s: sender name */
	esc_html__( 'From: %s', 'smart-gift-cards-for-woocommerce' ),
	esc_html( $gift_card->sender_name )
);
echo "\n\n";

if ( ! empty( $gift_card->message ) ) {
	echo '"' . esc_html( $gift_card->message ) . '"';
	echo "\n\n";
}

echo "----------------------------------------\n";
printf(
	/* translators: %s: gift card amount */
	esc_html__( 'Amount: %s', 'smart-gift-cards-for-woocommerce' ),
	esc_html( wp_strip_all_tags( wc_price( $gift_card->initial_amount, [ 'currency' => $gift_card->currency ] ) ) )
);
echo "\n";

printf(
	/* translators: %s: gift card code */
	esc_html__( 'Code: %s', 'smart-gift-cards-for-woocommerce' ),
	esc_html( $gift_card->code )
);
echo "\n";

if ( ! empty( $gift_card->expires_at ) ) {
	printf(
		/* translators: %s: expiry date */
		esc_html__( 'Expires: %s', 'smart-gift-cards-for-woocommerce' ),
		esc_html( date_i18n( get_option( 'date_format' ), strtotime( $gift_card->expires_at ) ) )
	);
	echo "\n";
}
echo "----------------------------------------\n\n";

printf(
	/* translators: %s: shop URL */
	esc_html__( 'Shop now: %s', 'smart-gift-cards-for-woocommerce' ),
	esc_url( add_query_arg( 'wcgc_apply', rawurlencode( $gift_card->code ), wc_get_page_permalink( 'shop' ) ) )
);
echo "\n\n";

esc_html_e( 'Click the link above to apply your gift card automatically, or enter the code at checkout in the coupon/gift card field.', 'smart-gift-cards-for-woocommerce' );
echo "\n\n";

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
