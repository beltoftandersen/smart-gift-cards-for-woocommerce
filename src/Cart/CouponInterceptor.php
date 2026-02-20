<?php

namespace GiftCards\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon interception is no longer needed.
 *
 * Gift card codes are now handled as virtual WooCommerce coupons via
 * CartHandler::virtual_coupon_data(). This class is kept as a no-op stub
 * for backward compatibility.
 */
class CouponInterceptor {

	/**
	 * No-op. Virtual coupon hooks in CartHandler handle everything.
	 */
	public static function init() {}
}
