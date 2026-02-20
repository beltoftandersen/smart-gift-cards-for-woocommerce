<?php

namespace GiftCards\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Gift Card product type.
 *
 * Virtual, non-taxable, non-shippable product.
 */
class WC_Product_Gift_Card extends \WC_Product {

	/**
	 * Get product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'gift-card';
	}

	/**
	 * Gift cards are always virtual.
	 *
	 * @param string $context Context.
	 * @return bool
	 */
	public function get_virtual( $context = 'view' ) {
		return true;
	}

	/**
	 * Gift cards are never taxable.
	 *
	 * @param string $context Context.
	 * @return string
	 */
	public function get_tax_status( $context = 'view' ) {
		return 'none';
	}

	/**
	 * Gift cards are always purchasable.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		return $this->exists() && $this->get_status() === 'publish';
	}

	/**
	 * Gift cards don't need stock management.
	 *
	 * @return bool
	 */
	public function managing_stock() {
		return false;
	}

	/**
	 * Gift cards are always in stock.
	 *
	 * @return bool
	 */
	public function is_in_stock() {
		return true;
	}
}
