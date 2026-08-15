<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Pricing {

	const META_DISABLE_WHOLESALE_PRICE = '_useup_me_disable_wholesale_price';

	public static function get_retail_markup_percent() {
		return (float) USEUP_ME_Settings::get( 'retail_markup_percent', 50 );
	}

	public static function get_retail_markup_fixed() {
		return (float) USEUP_ME_Settings::get( 'retail_markup_fixed', 10 );
	}

	public static function is_wholesale_disabled_for_product( WC_Product $product ) {
		$meta_product = self::get_wholesale_visibility_meta_product( $product );

		if ( ! $meta_product instanceof WC_Product ) {
			return false;
		}

		return 'yes' === (string) $meta_product->get_meta( self::META_DISABLE_WHOLESALE_PRICE, true );
	}

	public static function uses_wholesale_pricing( WC_Product $product ) {
		return ! self::is_wholesale_disabled_for_product( $product );
	}

	public static function get_retail_price_from_wholesale( $wholesale_price ) {
		$wholesale_price = max( 0, (float) $wholesale_price );

		if ( $wholesale_price <= 0 ) {
			return 0;
		}

		if ( USEUP_ME_Settings::is_quantity_price_control_enabled() ) {
			return self::apply_quantity_price_adjustments( $wholesale_price );
		}

		if ( self::has_active_quantity_price_adjustments() ) {
			return self::apply_quantity_price_adjustments( $wholesale_price );
		}

		$percent      = self::get_retail_markup_percent();
		$fixed        = self::get_retail_markup_fixed();
		$retail_price = ( $wholesale_price * ( 1 + ( $percent / 100 ) ) ) + $fixed;

		return round( max( 0, (float) $retail_price ), wc_get_price_decimals() );
	}

	public static function apply_quantity_price_adjustments( $base_price, $adjustments = null ) {
		$price = max( 0, (float) $base_price );

		if ( null === $adjustments ) {
			$adjustments = USEUP_ME_Settings::get_quantity_price_adjustments();
		}

		if ( empty( $adjustments ) || ! is_array( $adjustments ) ) {
			return round( $price, wc_get_price_decimals() );
		}

		foreach ( $adjustments as $adjustment ) {
			$type  = isset( $adjustment['type'] ) ? (string) $adjustment['type'] : 'fixed';
			$value = isset( $adjustment['value'] ) ? max( 0, (float) $adjustment['value'] ) : 0;

			if ( $value <= 0 ) {
				continue;
			}

			if ( 'percent' === $type ) {
				$price += $price * ( $value / 100 );
				continue;
			}

			$price += $value;
		}

		return round( max( 0, (float) $price ), wc_get_price_decimals() );
	}

	public static function has_active_quantity_price_adjustments() {
		return ! empty( USEUP_ME_Settings::get_quantity_price_adjustments() );
	}

	public static function get_product_wholesale_price( WC_Product $product ) {
		if ( $product instanceof WC_Product_Variation || $product->is_type( 'variation' ) ) {
			$price = self::get_raw_product_price( $product );
		} elseif ( $product->is_type( 'variable' ) ) {
			$price = self::get_variable_product_wholesale_price( $product );
		} else {
			$price = self::get_raw_product_price( $product );
		}

		return max( 0, (float) $price );
	}

	public static function get_variation_price_data( $variation_id ) {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation instanceof WC_Product_Variation ) {
			return array(
				'wholesale_value' => 0,
				'retail_value'    => 0,
				'display_value'   => 0,
				'show_wholesale'  => true,
			);
		}

		return self::get_product_price_data( $variation );
	}

	public static function get_product_price_data( WC_Product $product ) {
		$base_value      = self::get_product_wholesale_price( $product );
		$show_wholesale  = self::uses_wholesale_pricing( $product );
		$retail_value    = $show_wholesale ? self::get_retail_price_from_wholesale( $base_value ) : $base_value;
		$wholesale_value = $show_wholesale ? $base_value : 0;
		$display_value   = $show_wholesale ? $wholesale_value : $retail_value;

		return array(
			'wholesale_value' => $wholesale_value,
			'retail_value'    => $retail_value,
			'display_value'   => $display_value,
			'show_wholesale'  => $show_wholesale,
		);
	}

	private static function get_variable_product_wholesale_price( WC_Product $product ) {
		$prices = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			$price = self::get_raw_product_price( $variation );

			if ( $price > 0 ) {
				$prices[] = $price;
			}
		}

		if ( empty( $prices ) ) {
			return 0;
		}

		return (float) min( $prices );
	}

	private static function get_raw_product_price( WC_Product $product ) {
		$price = $product->get_price( 'edit' );

		if ( '' === $price || null === $price ) {
			$price = $product->get_regular_price( 'edit' );
		}

		if ( '' === $price || null === $price ) {
			$price = 0;
		}

		return max( 0, (float) wc_format_decimal( $price, wc_get_price_decimals() ) );
	}

	private static function get_wholesale_visibility_meta_product( WC_Product $product ) {
		if ( $product instanceof WC_Product_Variation ) {
			$parent_product = wc_get_product( $product->get_parent_id() );

			if ( $parent_product instanceof WC_Product ) {
				return $parent_product;
			}
		}

		return $product;
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
