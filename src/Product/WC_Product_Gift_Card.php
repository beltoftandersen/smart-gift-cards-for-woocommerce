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
	 * Return the lowest predefined amount for catalog price display.
	 *
	 * @param string $context Context.
	 * @return string
	 */
	public function get_price( $context = 'view' ) {
		$amounts_str = get_post_meta( $this->get_id(), '_wcgc_amounts', true );
		$amounts     = array_filter( array_map( 'floatval', explode( ',', (string) $amounts_str ) ) );
		if ( ! empty( $amounts ) ) {
			return (string) min( $amounts );
		}

		return '';
	}

	/**
	 * Show price range in catalog (e.g. "25,00 kr. – 100,00 kr.").
	 *
	 * @param string $price  Price HTML.
	 * @param object $product Product object.
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		$amounts_str = get_post_meta( $this->get_id(), '_wcgc_amounts', true );
		$amounts     = array_filter( array_map( 'floatval', explode( ',', (string) $amounts_str ) ) );

		if ( empty( $amounts ) ) {
			return '';
		}

		$min = min( $amounts );
		$max = max( $amounts );

		if ( $min === $max ) {
			return wc_price( $min );
		}

		return wc_format_price_range( $min, $max );
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
