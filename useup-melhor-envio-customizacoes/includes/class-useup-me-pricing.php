<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Pricing {

	public static function get_retail_markup_percent() {
		return (float) USEUP_ME_Settings::get( 'retail_markup_percent', 50 );
	}

	public static function get_retail_markup_fixed() {
		return (float) USEUP_ME_Settings::get( 'retail_markup_fixed', 10 );
	}

	public static function get_retail_price_from_wholesale( $wholesale_price ) {
		$wholesale_price = max( 0, (float) $wholesale_price );
		$percent         = self::get_retail_markup_percent();
		$fixed           = self::get_retail_markup_fixed();
		$retail_price    = ( $wholesale_price * ( 1 + ( $percent / 100 ) ) ) + $fixed;

		return round( max( 0, (float) $retail_price ), wc_get_price_decimals() );
	}

	public static function get_product_wholesale_price( WC_Product $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$price = (float) $product->get_variation_price( 'min', true );
		} else {
			$price = (float) wc_get_price_to_display( $product );
		}

		return max( 0, (float) $price );
	}

	public static function get_product_price_data( WC_Product $product ) {
		$wholesale_value = self::get_product_wholesale_price( $product );
		$retail_value    = self::get_retail_price_from_wholesale( $wholesale_value );

		return array(
			'wholesale_value' => $wholesale_value,
			'retail_value'    => $retail_value,
		);
	}

	public static function get_wholesale_tooltip_lines() {
		return array(
			'Preço de atacado da peça.',
			'O valor de varejo aparece logo abaixo.',
		);
	}
}
