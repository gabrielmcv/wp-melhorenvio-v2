<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_TikTok_Pricing_Sync {

	/**
	 * @var string[]
	 */
	private $sync_hooks = array(
		'tt4b_catalog_sync',
		'tt4b_catalog_sync_helper',
		'tt4b_variation_sync_helper',
	);

	public function init() {
		$price_filters = array(
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_product_variation_get_price',
			'woocommerce_product_variation_get_regular_price',
			'woocommerce_product_variation_get_sale_price',
		);

		foreach ( $price_filters as $filter_name ) {
			add_filter( $filter_name, array( $this, 'filter_tiktok_sync_price' ), 999, 2 );
		}
	}

	public function filter_tiktok_sync_price( $price, $product ) {
		if ( ! $this->is_tiktok_sync_context() || ! $product instanceof WC_Product ) {
			return $price;
		}

		$price_data = USEUP_ME_Pricing::get_product_price_data( $product );
		$retail     = isset( $price_data['retail_value'] ) ? (float) $price_data['retail_value'] : 0;

		if ( $retail <= 0 ) {
			return $price;
		}

		return wc_format_decimal( $retail, wc_get_price_decimals() );
	}

	private function is_tiktok_sync_context() {
		if ( ! class_exists( 'TiktokForBusiness' ) ) {
			return false;
		}

		foreach ( $this->sync_hooks as $hook_name ) {
			if ( doing_action( $hook_name ) ) {
				return true;
			}
		}

		return false;
	}
}
