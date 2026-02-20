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

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function init() {
		Installer::maybe_upgrade();

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

			if ( Options::get( 'show_dedicated_field' ) === '1' ) {
				AjaxHandler::init();
			}
		}

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
	 * Admin CSS/JS - only on our pages and product edit.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin_assets( $hook ) {
		$our_pages = [ 'woocommerce_page_wcgc-gift-cards' ];

		if ( in_array( $hook, $our_pages, true ) || ( $hook === 'post.php' && get_post_type() === 'product' ) ) {
			wp_enqueue_style( 'wcgc-admin', WCGC_URL . 'assets/css/admin.css', [], WCGC_VERSION );
			wp_enqueue_script( 'wcgc-admin', WCGC_URL . 'assets/js/admin.js', [ 'jquery' ], WCGC_VERSION, true );
		}
	}

	/**
	 * Frontend CSS/JS.
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		wp_enqueue_style( 'wcgc-frontend', WCGC_URL . 'assets/css/frontend.css', [], WCGC_VERSION );

		wp_enqueue_script( 'wcgc-frontend', WCGC_URL . 'assets/js/frontend.js', [ 'jquery' ], WCGC_VERSION, true );
		wp_localize_script( 'wcgc-frontend', 'wcgc_params', [
			'ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'nonce'    => wp_create_nonce( 'wcgc-ajax' ),
			'i18n'     => [
				'enter_code'    => __( 'Please enter a gift card code.', 'smart-gift-cards-for-woocommerce' ),
				'applying'      => __( 'Applying...', 'smart-gift-cards-for-woocommerce' ),
				'removing'      => __( 'Removing...', 'smart-gift-cards-for-woocommerce' ),
				'request_error' => __( 'Request failed. Please try again.', 'smart-gift-cards-for-woocommerce' ),
			],
		] );
	}
}
