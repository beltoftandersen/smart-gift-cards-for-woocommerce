<?php

namespace GiftCards;

use GiftCards\Support\Installer;
use GiftCards\Support\Options;
use GiftCards\Admin\SettingsPage;
use GiftCards\Admin\OrderMetaBox;
use GiftCards\Product\GiftCardProductType;
use GiftCards\Frontend\ProductPage;
use GiftCards\Frontend\MyAccount;
use GiftCards\GiftCard\GiftCardCreator;
use GiftCards\Cart\CouponInterceptor;
use GiftCards\Cart\CartHandler;
use GiftCards\Cart\GiftCardField;
use GiftCards\Cart\AjaxHandler;
use GiftCards\Checkout\OrderProcessor;
use GiftCards\Email\GiftCardDeliveryEmail;
use GiftCards\Blocks\StoreApiExtension;
use GiftCards\Blocks\BlockIntegration;
use GiftCards\GiftCard\Repository as GiftCardRepository;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function init() {
		Installer::maybe_upgrade();

		// Throttle expiry sync to once per hour.
		if ( false === get_transient( 'wcgc_expiry_sync' ) ) {
			GiftCardRepository::sync_expired_statuses();
			set_transient( 'wcgc_expiry_sync', 1, HOUR_IN_SECONDS );
		}

		// Invalidate Options cache when changed externally (e.g., via wp option update).
		add_action( 'update_option_' . Options::OPTION, [ Options::class, 'invalidate_cache' ] );

		// Always load: product type, gift card creation, coupon interception, cart handling, order processing.
		GiftCardProductType::init();
		GiftCardCreator::init();
		CouponInterceptor::init();
		CartHandler::init();
		OrderProcessor::init();

		// Register email class.
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email_class' ] );

		// Admin-only classes.
		if ( is_admin() ) {
			SettingsPage::init();
			OrderMetaBox::init();
		}

		// Frontend + AJAX.
		if ( ! is_admin() || wp_doing_ajax() ) {
			ProductPage::init();
			MyAccount::init();
			GiftCardField::init();
			AjaxHandler::init();
		}

		// Loyalty Rewards integration — block points for gift card purchases when disabled.
		if ( class_exists( 'LoyaltyRewards\\Plugin' ) ) {
			add_filter( 'wclr_max_redeemable_points', [ __CLASS__, 'maybe_block_points_for_gift_cards' ], 10, 3 );
			add_action( 'wclr_redeem_form_after_earn', [ __CLASS__, 'loyalty_points_blocked_notice' ] );
		}

		// WooCommerce Blocks: Store API extension + blocks JS integration.
		StoreApiExtension::init();
		add_action( 'woocommerce_blocks_cart_block_registration', [ __CLASS__, 'register_block_integration' ] );
		add_action( 'woocommerce_blocks_checkout_block_registration', [ __CLASS__, 'register_block_integration' ] );

		// Assets.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Register the gift card delivery email class.
	 *
	 * @param array $email_classes WooCommerce email classes.
	 * @return array
	 */
	public static function register_email_class( $email_classes ) {
		$email_classes['WCGC_Gift_Card_Delivery'] = new GiftCardDeliveryEmail();
		return $email_classes;
	}

	/**
	 * Register WooCommerce Blocks integration.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $registry Integration registry.
	 */
	public static function register_block_integration( $registry ) {
		$registry->register( new BlockIntegration() );
	}

	/**
	 * Block loyalty point redemption when cart contains gift card products.
	 *
	 * @param int   $redeemable Max redeemable points.
	 * @param float $cart_total Cart total.
	 * @param int   $balance    User point balance.
	 * @return int
	 */
	public static function maybe_block_points_for_gift_cards( $redeemable, $cart_total, $balance ) {
		if ( Options::get( 'allow_points_for_gift_cards' ) === '1' ) {
			return $redeemable;
		}

		if ( ! WC()->cart ) {
			return $redeemable;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && $product->get_type() === 'gift-card' ) {
				return 0;
			}
		}

		return $redeemable;
	}

	/**
	 * Show notice inside the loyalty redeem form when points are blocked for gift cards.
	 */
	public static function loyalty_points_blocked_notice() {
		if ( Options::get( 'allow_points_for_gift_cards' ) === '1' ) {
			return;
		}

		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && $product->get_type() === 'gift-card' ) {
				echo '<div class="wclr-redeem-notice" style="padding:12px 16px;margin:8px 0 12px;border:1px solid #c3c4c7;border-radius:4px;color:#50575e;font-size:0.875em;background-color:#f0f0f1;">'
					. esc_html__( 'Loyalty points cannot be used to purchase gift cards.', 'smart-gift-cards-for-woocommerce' )
					. '</div>';
				return;
			}
		}
	}

	/**
	 * Build a cache-busting asset version from file modification time.
	 *
	 * @param string $relative_path Relative path from plugin root.
	 * @return string
	 */
	private static function asset_version( $relative_path ) {
		$file = WCGC_PATH . ltrim( $relative_path, '/' );
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}

		return WCGC_VERSION;
	}

	/**
	 * Admin CSS/JS - only on our pages and product edit.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin_assets( $hook ) {
		$our_pages = [ 'woocommerce_page_wcgc-gift-cards' ];

		if ( in_array( $hook, $our_pages, true ) || ( $hook === 'post.php' && get_post_type() === 'product' ) ) {
			wp_enqueue_style( 'wcgc-admin', WCGC_URL . 'assets/css/admin.css', [], self::asset_version( 'assets/css/admin.css' ) );
			wp_enqueue_script( 'wcgc-admin', WCGC_URL . 'assets/js/admin.js', [ 'jquery' ], self::asset_version( 'assets/js/admin.js' ), true );
		}
	}

	/**
	 * Frontend CSS/JS.
	 */
	public static function enqueue_frontend_assets() {
		$should_enqueue = is_product() || is_cart() || is_checkout() || is_account_page();

		if ( ! $should_enqueue && is_singular() ) {
			$post = get_post();
			if ( $post ) {
				$content = $post->post_content;
				if ( has_shortcode( $content, 'wcgc_apply_field' ) || has_shortcode( $content, 'wcgc_product_form' ) ) {
					$should_enqueue = true;
				}
			}
		}

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style( 'wcgc-frontend', WCGC_URL . 'assets/css/frontend.css', [], self::asset_version( 'assets/css/frontend.css' ) );

		wp_enqueue_script( 'wcgc-frontend', WCGC_URL . 'assets/js/frontend.js', [ 'jquery' ], self::asset_version( 'assets/js/frontend.js' ), true );
		wp_localize_script( 'wcgc-frontend', 'wcgc_params', [
			'ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'nonce'    => wp_create_nonce( 'wcgc-ajax' ),
			'i18n'     => [
				'enter_code'    => __( 'Please enter a gift card code.', 'smart-gift-cards-for-woocommerce' ),
				'apply'         => __( 'Apply', 'smart-gift-cards-for-woocommerce' ),
				'applying'      => __( 'Applying...', 'smart-gift-cards-for-woocommerce' ),
				'removing'      => __( 'Removing...', 'smart-gift-cards-for-woocommerce' ),
				'request_error' => __( 'Request failed. Please try again.', 'smart-gift-cards-for-woocommerce' ),
			],
		] );
	}
}
