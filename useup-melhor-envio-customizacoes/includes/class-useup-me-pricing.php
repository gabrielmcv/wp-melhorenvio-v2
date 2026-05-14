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
			$price = (float) $product->get_variation_price( 'min', false );
		} else {
			$price = (float) $product->get_price( 'edit' );
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
		$lines = array(
			trim( (string) USEUP_ME_Settings::get( 'product_wholesale_tooltip_line_1', 'Preço de atacado da peça.' ) ),
			trim( (string) USEUP_ME_Settings::get( 'product_wholesale_tooltip_line_2', 'O valor de varejo aparece logo abaixo.' ) ),
		);

		$lines = array_values(
			array_filter(
				$lines,
				static function ( $line ) {
					return '' !== $line;
				}
			)
		);

		if ( empty( $lines ) ) {
			$lines[] = 'Preço de atacado da peça.';
		}

		return $lines;
	}
}
