<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Quantity_Price_Control {

	/**
	 * @var bool
	 */
	private $is_processing = false;

	/**
	 * @var WC_Logger|null
	 */
	private $logger = null;

	public function init() {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_quantity_price_control' ), PHP_INT_MAX );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'log_cart_totals_snapshot' ), PHP_INT_MAX );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'capture_cart_item_source_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item_source_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price_html' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_cart_item_subtotal_html' ), 20, 3 );
	}

	public function capture_cart_item_source_data( $cart_item_data, $product_id, $variation_id ) {
		$cart_item_data['useup_me_source_product_id']   = absint( $product_id );
		$cart_item_data['useup_me_source_variation_id'] = absint( $variation_id );

		return $cart_item_data;
	}

	public function restore_cart_item_source_data( $cart_item, $session_values ) {
		if ( isset( $session_values['useup_me_source_product_id'] ) ) {
			$cart_item['useup_me_source_product_id'] = absint( $session_values['useup_me_source_product_id'] );
		}

		if ( isset( $session_values['useup_me_source_variation_id'] ) ) {
			$cart_item['useup_me_source_variation_id'] = absint( $session_values['useup_me_source_variation_id'] );
		}

		if ( isset( $session_values['useup_me_base_wholesale_price'] ) ) {
			$cart_item['useup_me_base_wholesale_price'] = (float) $session_values['useup_me_base_wholesale_price'];
		}

		if ( isset( $session_values['useup_me_base_wholesale_product_id'] ) ) {
			$cart_item['useup_me_base_wholesale_product_id'] = absint( $session_values['useup_me_base_wholesale_product_id'] );
		}

		if ( isset( $session_values['useup_me_effective_price'] ) ) {
			$cart_item['useup_me_effective_price'] = (float) $session_values['useup_me_effective_price'];
		}

		return $cart_item;
	}

	public function apply_quantity_price_control( $cart ) {
		$cart_quantity = 0;

		if (
			! USEUP_ME_Settings::is_quantity_price_control_enabled() ||
			$this->is_processing ||
			! $cart instanceof WC_Cart ||
			( is_admin() && ! wp_doing_ajax() ) ||
			empty( $cart->get_cart() )
		) {
			return;
		}

		$this->is_processing = true;
		$cart_quantity       = $this->get_cart_quantity_for_threshold( $cart );

		try {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product             = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
				$source_product      = $this->get_source_product( $cart_item );
				$base_price          = $this->get_base_wholesale_price( $cart_item_key, $cart_item, $cart, $source_product );
				$should_apply        = $this->should_apply_adjustments( $cart_quantity );
				$is_eligible         = $product instanceof WC_Product ? $this->is_product_eligible( $product, $cart_item ) : false;
				$adjusted_price      = $base_price > 0 ? USEUP_ME_Pricing::apply_quantity_price_adjustments( $base_price ) : 0;
				$final_price         = $base_price;
				$price_before_set    = $product instanceof WC_Product ? (float) $product->get_price() : 0;
				$line_subtotal       = isset( $cart->cart_contents[ $cart_item_key ]['line_subtotal'] ) ? (float) $cart->cart_contents[ $cart_item_key ]['line_subtotal'] : 0;
				$line_total          = isset( $cart->cart_contents[ $cart_item_key ]['line_total'] ) ? (float) $cart->cart_contents[ $cart_item_key ]['line_total'] : 0;

				if ( ! $product instanceof WC_Product || $base_price <= 0 ) {
					$this->maybe_log_item( $cart_item_key, $cart_item, $product, $source_product, $cart_quantity, $base_price, $adjusted_price, $final_price, $is_eligible, $should_apply, $price_before_set, $price_before_set, $line_subtotal, $line_total );
					continue;
				}

				if ( $should_apply && $is_eligible ) {
					$final_price = $adjusted_price;
				}

				$final_price = (float) wc_format_decimal( $final_price, wc_get_price_decimals() );

				$product->set_price( $final_price );
				$cart->cart_contents[ $cart_item_key ]['data']                   = $product;
				$cart->cart_contents[ $cart_item_key ]['useup_me_effective_price'] = $final_price;

				unset(
					$cart->cart_contents[ $cart_item_key ]['line_subtotal'],
					$cart->cart_contents[ $cart_item_key ]['line_subtotal_tax'],
					$cart->cart_contents[ $cart_item_key ]['line_total'],
					$cart->cart_contents[ $cart_item_key ]['line_tax']
				);

				$this->maybe_log_item(
					$cart_item_key,
					$cart_item,
					$product,
					$source_product,
					$cart_quantity,
					$base_price,
					$adjusted_price,
					$final_price,
					$is_eligible,
					$should_apply,
					$price_before_set,
					(float) $product->get_price(),
					$line_subtotal,
					$line_total
				);
			}
		} finally {
			$this->is_processing = false;
		}
	}

	public function log_cart_totals_snapshot( $cart ) {
		if ( ! $this->is_debug_enabled() || ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			$this->get_logger()->debug(
				wp_json_encode(
					array(
						'stage'             => 'after_calculate_totals',
						'cart_item_key'     => $cart_item_key,
						'product_id'        => isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0,
						'variation_id'      => isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0,
						'data_price'        => $product instanceof WC_Product ? (float) $product->get_price() : 0,
						'effective_price'   => isset( $cart_item['useup_me_effective_price'] ) ? (float) $cart_item['useup_me_effective_price'] : 0,
						'line_subtotal'     => isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0,
						'line_subtotal_tax' => isset( $cart_item['line_subtotal_tax'] ) ? (float) $cart_item['line_subtotal_tax'] : 0,
						'line_total'        => isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : 0,
						'line_tax'          => isset( $cart_item['line_tax'] ) ? (float) $cart_item['line_tax'] : 0,
						'cart_contents_total' => method_exists( $cart, 'get_cart_contents_total' ) ? (float) $cart->get_cart_contents_total() : 0,
						'cart_total'        => method_exists( $cart, 'get_total' ) ? wp_strip_all_tags( (string) $cart->get_total() ) : '',
					)
				),
				array( 'source' => 'useup-me-quantity-price-control' )
			);
		}
	}

	public function filter_cart_item_price_html( $price_html, $cart_item, $cart_item_key ) {
		if ( ! $this->should_filter_cart_item_display() ) {
			return $price_html;
		}

		$effective_price = $this->get_effective_display_price( $cart_item_key, $cart_item );

		if ( null === $effective_price ) {
			return $price_html;
		}

		return wc_price( wc_get_price_to_display( $cart_item['data'], array( 'price' => $effective_price ) ) );
	}

	public function filter_cart_item_subtotal_html( $subtotal_html, $cart_item, $cart_item_key ) {
		if ( ! $this->should_filter_cart_item_display() ) {
			return $subtotal_html;
		}

		$effective_price = $this->get_effective_display_price( $cart_item_key, $cart_item );
		$quantity        = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

		if ( null === $effective_price ) {
			return $subtotal_html;
		}

		return wc_price(
			wc_get_price_to_display(
				$cart_item['data'],
				array(
					'price' => $effective_price,
					'qty'   => $quantity,
				)
			)
		);
	}

	private function should_apply_adjustments( $cart_quantity ) {
		return $cart_quantity < USEUP_ME_Settings::get_wholesale_min_quantity();
	}

	private function get_cart_quantity_for_threshold( WC_Cart $cart ) {
		$mode     = USEUP_ME_Settings::get_wholesale_quantity_count_mode();
		$quantity = 0;

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( 'eligible_products_only' === $mode && ! $this->is_product_eligible( $product, $cart_item ) ) {
				continue;
			}

			$quantity += isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
		}

		return max( 0, (int) $quantity );
	}

	private function get_base_wholesale_price( $cart_item_key, $cart_item, WC_Cart $cart, $source_product ) {
		$source_id = $source_product instanceof WC_Product ? $source_product->get_id() : 0;

		$price = $source_product instanceof WC_Product ? USEUP_ME_Pricing::get_product_wholesale_price( $source_product ) : 0;

		if ( $price <= 0 && $source_product instanceof WC_Product ) {
			$fallback_price = $source_product->get_regular_price( 'edit' );

			if ( '' !== $fallback_price && null !== $fallback_price ) {
				$price = (float) wc_format_decimal( $fallback_price, wc_get_price_decimals() );
			}
		}

		$cart->cart_contents[ $cart_item_key ]['useup_me_base_wholesale_price'] = max( 0, (float) $price );
		$cart->cart_contents[ $cart_item_key ]['useup_me_base_wholesale_product_id'] = $source_id;

		return max( 0, (float) $price );
	}

	private function get_source_product( $cart_item ) {
		$variation_id = ! empty( $cart_item['useup_me_source_variation_id'] )
			? absint( $cart_item['useup_me_source_variation_id'] )
			: absint( isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0 );
		$product_id = ! empty( $cart_item['useup_me_source_product_id'] )
			? absint( $cart_item['useup_me_source_product_id'] )
			: absint( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
		$source_id = $variation_id > 0 ? $variation_id : $product_id;

		if ( $source_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $source_id );

		return $product instanceof WC_Product ? $product : null;
	}

	private function get_effective_display_price( $cart_item_key, $cart_item ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || empty( WC()->cart->get_cart() ) ) {
			return null;
		}

		if ( isset( $cart_item['useup_me_effective_price'] ) && (float) $cart_item['useup_me_effective_price'] > 0 ) {
			return (float) wc_format_decimal( $cart_item['useup_me_effective_price'], wc_get_price_decimals() );
		}

		$product        = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$source_product = $this->get_source_product( $cart_item );

		if ( ! $product instanceof WC_Product || ! $source_product instanceof WC_Product ) {
			return null;
		}

		$cart_quantity   = $this->get_cart_quantity_for_threshold( WC()->cart );
		$base_price      = $this->get_base_wholesale_price( $cart_item_key, $cart_item, WC()->cart, $source_product );
		$should_apply    = $this->should_apply_adjustments( $cart_quantity );
		$is_eligible     = $this->is_product_eligible( $product, $cart_item );
		$effective_price = $base_price;

		if ( $should_apply && $is_eligible ) {
			$effective_price = USEUP_ME_Pricing::apply_quantity_price_adjustments( $base_price );
		}

		return (float) wc_format_decimal( $effective_price, wc_get_price_decimals() );
	}

	private function is_product_eligible( WC_Product $product, $cart_item ) {
		if ( ! USEUP_ME_Pricing::uses_wholesale_pricing( $product ) ) {
			return false;
		}

		$is_eligible = (bool) apply_filters( 'useup_me_quantity_price_control_product_is_eligible', true, $product, $cart_item );

		if ( $is_eligible || ! $product instanceof WC_Product_Variation ) {
			return $is_eligible;
		}

		$parent_product = wc_get_product( $product->get_parent_id() );

		if ( ! $parent_product instanceof WC_Product ) {
			return false;
		}

		return (bool) apply_filters( 'useup_me_quantity_price_control_product_is_eligible', true, $parent_product, $cart_item );
	}

	private function maybe_log_item( $cart_item_key, $cart_item, $product, $source_product, $cart_quantity, $base_price, $adjusted_price, $final_price, $is_eligible, $should_apply, $price_before_set, $price_after_set, $line_subtotal, $line_total ) {
		if ( ! $this->is_debug_enabled() ) {
			return;
		}

		$this->get_logger()->debug(
			wp_json_encode(
				array(
					'cart_item_key'   => $cart_item_key,
					'product_id'      => isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0,
					'variation_id'    => isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0,
					'data_class'      => is_object( $product ) ? get_class( $product ) : null,
					'data_id'         => $product instanceof WC_Product ? $product->get_id() : 0,
					'parent_id'       => $product instanceof WC_Product_Variation ? $product->get_parent_id() : 0,
					'source_id'       => $source_product instanceof WC_Product ? $source_product->get_id() : 0,
					'source_class'    => is_object( $source_product ) ? get_class( $source_product ) : null,
					'quantity'        => isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0,
					'cart_quantity'   => (int) $cart_quantity,
					'min_quantity'    => USEUP_ME_Settings::get_wholesale_min_quantity(),
					'should_apply'    => (bool) $should_apply,
					'eligible'        => (bool) $is_eligible,
					'base_price'      => (float) $base_price,
					'adjusted_price'  => (float) $adjusted_price,
					'final_price'     => (float) $final_price,
					'price_before_set' => (float) $price_before_set,
					'price_after_set'  => (float) $price_after_set,
					'data_price_edit' => $product instanceof WC_Product ? $product->get_price( 'edit' ) : null,
					'data_price'      => $product instanceof WC_Product ? $product->get_price() : null,
					'line_subtotal'   => (float) $line_subtotal,
					'line_total'      => (float) $line_total,
					'hook_priority'   => PHP_INT_MAX,
					'stored_source_product_id' => isset( $cart_item['useup_me_source_product_id'] ) ? absint( $cart_item['useup_me_source_product_id'] ) : 0,
					'stored_source_variation_id' => isset( $cart_item['useup_me_source_variation_id'] ) ? absint( $cart_item['useup_me_source_variation_id'] ) : 0,
					'effective_price' => isset( $cart_item['useup_me_effective_price'] ) ? (float) $cart_item['useup_me_effective_price'] : 0,
				)
			),
			array( 'source' => 'useup-me-quantity-price-control' )
		);
	}

	private function should_filter_cart_item_display() {
		if ( ! USEUP_ME_Settings::is_quantity_price_control_enabled() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		return wp_doing_ajax();
	}

	private function is_debug_enabled() {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || apply_filters( 'useup_me_quantity_price_control_debug', false );
	}

	private function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = wc_get_logger();
		}

		return $this->logger;
	}
}
