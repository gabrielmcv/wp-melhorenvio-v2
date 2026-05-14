<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Shipping_Quantity_Discount {

	const DEBUG_SOURCE = 'useup-me-shipping-discount';

	/**
	 * @var array<string,float>
	 */
	private $cart_rate_discounts = array();

	public function init() {
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'decorate_shipping_packages' ), 20, 1 );
		add_filter( 'woocommerce_package_rates', array( $this, 'apply_shipping_quantity_discount' ), 999, 2 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'filter_cart_shipping_method_full_label' ), 20, 2 );
		add_filter( 'useup_me_product_shipping_result_rates', array( $this, 'filter_product_shipping_result_rates' ), 999, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function decorate_shipping_packages( $packages ) {
		if ( empty( $packages ) || ! is_array( $packages ) ) {
			return $packages;
		}

		$context = $this->get_discount_settings_context();

		foreach ( $packages as $index => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}

			$package['useup_me_shipping_quantity_discount'] = $context;
			$package['useup_me_shipping_quantity_discount']['package_index'] = (int) $index;
			$package['useup_me_shipping_quantity_discount']['items_count']   = self::get_items_count_for_package( $package );

			$packages[ $index ] = $package;
		}

		return $packages;
	}

	public function enqueue_assets() {
		if ( ! USEUP_ME_Settings::is_shipping_quantity_discount_enabled() ) {
			return;
		}

		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-shipping-quantity-discount',
			USEUP_ME_URL . 'assets/css/shipping-quantity-discount.css',
			array(),
			USEUP_ME_VERSION
		);
	}

	public function apply_shipping_quantity_discount( $rates, $package ) {
		if ( ! USEUP_ME_Settings::is_shipping_quantity_discount_enabled() || empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		$this->cart_rate_discounts = array();

		$discount = self::get_discount_amount_for_package( $package );

		if ( $discount <= 0 ) {
			return $rates;
		}

		foreach ( $rates as $rate_key => $rate ) {
			if ( ! is_object( $rate ) ) {
				continue;
			}

			$rate_identifier = $this->get_rate_identifier( $rate, $rate_key );
			$this->cart_rate_discounts[ $rate_identifier ] = 0.0;

			$method_id     = $this->get_rate_method_id( $rate, $rate_identifier );
			$original_cost = $this->get_object_rate_cost( $rate );
			$this->debug(
				'Rate before eligibility check',
				array(
					'rate_id'       => $rate_identifier,
					'method_id'     => $method_id,
					'original_cost' => $original_cost,
					'discount'      => $discount,
					'items_count'   => self::get_items_count_for_package( $package ),
				)
			);

			if ( ! $this->is_rate_eligible( $rate, $package, $method_id, false, $original_cost ) ) {
				$this->debug(
					'Rate not eligible for shipping quantity discount',
					array(
						'rate_id'       => $rate_identifier,
						'method_id'     => $method_id,
						'original_cost' => $original_cost,
					)
				);
				continue;
			}

			$taxes         = $this->get_object_rate_taxes( $rate );
			$discount_data = $this->build_discounted_cost_data( $original_cost, $discount, $taxes );

			if ( $discount_data['applied_discount'] <= 0 ) {
				continue;
			}

			$rates[ $rate_key ]->cost  = $discount_data['new_cost'];
			$rates[ $rate_key ]->taxes = $discount_data['taxes'];

			if ( method_exists( $rates[ $rate_key ], 'set_cost' ) ) {
				$rates[ $rate_key ]->set_cost( $discount_data['new_cost'] );
			}

			if ( method_exists( $rates[ $rate_key ], 'set_taxes' ) ) {
				$rates[ $rate_key ]->set_taxes( $discount_data['taxes'] );
			}

			$this->cart_rate_discounts[ $rate_identifier ] = $discount_data['applied_discount'];
			$this->debug(
				'Rate discounted successfully',
				array(
					'rate_id'          => $rate_identifier,
					'method_id'        => $method_id,
					'original_cost'    => $original_cost,
					'new_cost'         => $discount_data['new_cost'],
					'applied_discount' => $discount_data['applied_discount'],
				)
			);
		}

		return $rates;
	}

	public function filter_cart_shipping_method_full_label( $label, $rate ) {
		if ( ! USEUP_ME_Settings::is_shipping_quantity_discount_enabled() || ! is_object( $rate ) ) {
			return $label;
		}

		$rate_identifier = $this->get_rate_identifier( $rate );
		$discount_amount = 0.0;

		if ( isset( $this->cart_rate_discounts[ $rate_identifier ] ) ) {
			$discount_amount = (float) $this->cart_rate_discounts[ $rate_identifier ];
		}

		if ( $discount_amount <= 0 ) {
			return $label;
		}

		$rate_cost = $this->get_object_rate_cost( $rate );

		if ( $rate_cost <= 0 ) {
			$base_label = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : wp_strip_all_tags( (string) $label );

			return trim( $base_label ) . ' <small class="useup-shipping-free-label">FRETE GRÁTIS</small>';
		}

		$note_label = self::get_discount_label_text();
		$note       = sprintf(
			'%s: %s',
			$note_label,
			self::format_price_text( $discount_amount )
		);

		return $label . '<br><small class="useup-shipping-quantity-discount-note">' . esc_html( $note ) . '</small>';
	}

	public function filter_product_shipping_result_rates( $rates, $product, $package ) {
		if ( ! USEUP_ME_Settings::is_shipping_quantity_discount_enabled() || empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		$should_apply = (bool) apply_filters(
			'useup_me_apply_shipping_quantity_discount_to_product_quote',
			true,
			$product,
			$package
		);

		if ( ! $should_apply ) {
			return $rates;
		}

		$discount = self::get_discount_amount_for_package( $package );

		if ( $discount <= 0 ) {
			return $rates;
		}

		foreach ( $rates as &$rate ) {
			if ( ! is_array( $rate ) ) {
				continue;
			}

			$rate_identifier = isset( $rate['id'] ) ? (string) $rate['id'] : '';
			$method_id       = $this->get_rate_method_id( $rate, $rate_identifier );
			$original_cost   = isset( $rate['raw_cost'] ) ? (float) $rate['raw_cost'] : 0.0;

			if ( ! $this->is_rate_eligible( $rate, $package, $method_id, true, $original_cost ) ) {
				continue;
			}

			$discount_data = $this->build_discounted_cost_data( $original_cost, $discount, array() );

			if ( $discount_data['applied_discount'] <= 0 ) {
				continue;
			}

			$rate['raw_cost'] = $discount_data['new_cost'];
			$rate['cost']     = self::format_price_text( $discount_data['new_cost'] );
		}
		unset( $rate );

		usort(
			$rates,
			function ( $first, $second ) {
				$first_cost  = isset( $first['raw_cost'] ) ? (float) $first['raw_cost'] : 0.0;
				$second_cost = isset( $second['raw_cost'] ) ? (float) $second['raw_cost'] : 0.0;

				if ( $first_cost === $second_cost ) {
					return 0;
				}

				return ( $first_cost < $second_cost ) ? -1 : 1;
			}
		);

		return $rates;
	}

	public static function get_discount_amount_for_package( $package = array() ) {
		if ( ! USEUP_ME_Settings::is_shipping_quantity_discount_enabled() ) {
			return 0.0;
		}

		$items_count = self::get_items_count_for_package( $package );
		$per_item    = (float) apply_filters(
			'useup_me_shipping_quantity_discount_per_item',
			(float) USEUP_ME_Settings::get( 'shipping_discount_per_item', 8.0 ),
			$package
		);
		$max_items   = (int) apply_filters(
			'useup_me_shipping_quantity_discount_max_items',
			(int) USEUP_ME_Settings::get( 'shipping_discount_max_items', 4 ),
			$package
		);

		$per_item  = max( 0, $per_item );
		$max_items = max( 0, $max_items );

		if ( $items_count < 1 || $per_item <= 0 || $max_items < 1 ) {
			return 0.0;
		}

		if ( $items_count > $max_items ) {
			return 0.0;
		}

		$eligible_items = $items_count;
		$discount       = (float) $eligible_items * $per_item;

		return max(
			0,
			(float) apply_filters(
				'useup_me_shipping_quantity_discount_amount',
				$discount,
				$items_count,
				$eligible_items,
				$package
			)
		);
	}

	public static function get_discount_label_text() {
		$label = (string) USEUP_ME_Settings::get( 'shipping_discount_label', 'Desconto de frete por quantidade' );
		$label = '' !== $label ? $label : 'Desconto de frete por quantidade';

		return (string) apply_filters( 'useup_me_shipping_quantity_discount_label', $label );
	}

	private static function get_items_count_for_package( $package ) {
		$fallback_quantity = 0;
		$is_product_quote  = ! empty( $package['product_page_calculation'] );

		if ( isset( $package['useup_me_rule_context']['quantity'] ) ) {
			$fallback_quantity = absint( $package['useup_me_rule_context']['quantity'] );
		}

		if ( $fallback_quantity < 1 && ! empty( $package['contents'] ) && is_array( $package['contents'] ) ) {
			foreach ( $package['contents'] as $content ) {
				if ( ! is_array( $content ) ) {
					continue;
				}

				$fallback_quantity += max( 0, isset( $content['quantity'] ) ? (int) $content['quantity'] : 0 );
			}
		}

		if ( $is_product_quote ) {
			return max( 0, (int) $fallback_quantity );
		}

		return max( 0, (int) useup_me_get_cart_items_count_for_extra_days( $fallback_quantity ) );
	}

	private function is_rate_eligible( $rate, $package, $method_id, $is_product_quote, $original_cost ) {
		$method_id       = sanitize_text_field( (string) $method_id );
		$original_cost   = max( 0, (float) $original_cost );
		$apply_to_free   = ! empty( USEUP_ME_Settings::get( 'shipping_discount_apply_to_free_shipping', false ) );
		$allowed_methods = $this->get_allowed_methods();
		$is_free_method  = 'free_shipping' === $method_id;
		$is_eligible     = true;

		if ( ! empty( $allowed_methods ) && ! in_array( $method_id, $allowed_methods, true ) ) {
			$is_eligible = false;
		}

		if ( $is_free_method && ! $apply_to_free ) {
			$is_eligible = false;
		}

		if ( $original_cost <= 0 ) {
			$is_eligible = false;
		}

		return (bool) apply_filters(
			'useup_me_shipping_quantity_discount_rate_is_eligible',
			$is_eligible,
			$rate,
			$package,
			$method_id,
			$is_product_quote,
			$original_cost
		);
	}

	private function get_allowed_methods() {
		$methods = USEUP_ME_Settings::get( 'shipping_discount_allowed_methods', array() );
		$methods = is_array( $methods ) ? $methods : array();
		$methods = array_map( 'sanitize_text_field', $methods );
		$methods = array_values( array_filter( array_unique( $methods ) ) );

		return apply_filters( 'useup_me_shipping_quantity_discount_allowed_methods', $methods );
	}

	private function debug( $message, $context = array() ) {
		$enabled = (bool) apply_filters( 'useup_me_shipping_quantity_discount_debug', false, $message, $context );

		if ( ! $enabled || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->debug(
			$message . ' | ' . wp_json_encode( $context ),
			array( 'source' => self::DEBUG_SOURCE )
		);
	}

	private function get_discount_settings_context() {
		return array(
			'enabled'       => USEUP_ME_Settings::is_shipping_quantity_discount_enabled(),
			'per_item'      => (float) USEUP_ME_Settings::get( 'shipping_discount_per_item', 8.0 ),
			'max_items'     => (int) USEUP_ME_Settings::get( 'shipping_discount_max_items', 4 ),
			'apply_to_free' => ! empty( USEUP_ME_Settings::get( 'shipping_discount_apply_to_free_shipping', false ) ),
			'methods'       => $this->get_allowed_methods(),
		);
	}

	private function build_discounted_cost_data( $original_cost, $discount, $taxes ) {
		$original_cost    = max( 0, (float) $original_cost );
		$discount         = max( 0, (float) $discount );
		$new_cost         = max( 0, $original_cost - $discount );
		$applied_discount = max( 0, $original_cost - $new_cost );
		$new_taxes        = array();

		if ( ! empty( $taxes ) && is_array( $taxes ) ) {
			if ( $original_cost > 0 && $new_cost > 0 ) {
				$ratio = $new_cost / $original_cost;

				foreach ( $taxes as $tax_id => $tax_amount ) {
					$new_taxes[ $tax_id ] = round( (float) $tax_amount * $ratio, wc_get_price_decimals() );
				}
			} else {
				foreach ( $taxes as $tax_id => $tax_amount ) {
					$new_taxes[ $tax_id ] = 0.0;
				}
			}
		}

		return array(
			'new_cost'         => round( $new_cost, wc_get_price_decimals() ),
			'applied_discount' => round( $applied_discount, wc_get_price_decimals() ),
			'taxes'            => $new_taxes,
		);
	}

	private function get_rate_identifier( $rate, $fallback = '' ) {
		if ( is_object( $rate ) && method_exists( $rate, 'get_id' ) ) {
			return (string) $rate->get_id();
		}

		if ( is_array( $rate ) && isset( $rate['id'] ) ) {
			return (string) $rate['id'];
		}

		return (string) $fallback;
	}

	private function get_rate_method_id( $rate, $fallback = '' ) {
		if ( is_object( $rate ) ) {
			if ( method_exists( $rate, 'get_method_id' ) ) {
				return (string) $rate->get_method_id();
			}

			if ( isset( $rate->method_id ) ) {
				return (string) $rate->method_id;
			}
		}

		if ( is_array( $rate ) && ! empty( $rate['id'] ) ) {
			$fallback = (string) $rate['id'];
		}

		$fallback = (string) $fallback;

		if ( false !== strpos( $fallback, ':' ) ) {
			return (string) substr( $fallback, 0, strpos( $fallback, ':' ) );
		}

		return $fallback;
	}

	private function get_object_rate_cost( $rate ) {
		if ( isset( $rate->cost ) ) {
			return (float) $rate->cost;
		}

		if ( method_exists( $rate, 'get_cost' ) ) {
			return (float) $rate->get_cost();
		}

		return 0.0;
	}

	private function get_object_rate_taxes( $rate ) {
		if ( isset( $rate->taxes ) && is_array( $rate->taxes ) ) {
			return $rate->taxes;
		}

		if ( method_exists( $rate, 'get_taxes' ) ) {
			$taxes = $rate->get_taxes();

			return is_array( $taxes ) ? $taxes : array();
		}

		return array();
	}

	private static function format_price_text( $amount ) {
		$formatted = wc_price( (float) $amount );
		$formatted = wp_strip_all_tags( $formatted );
		$formatted = html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );
		$formatted = str_replace( "\xc2\xa0", ' ', $formatted );
		$formatted = preg_replace( '/^R\\$(?=\\S)/u', 'R$ ', trim( $formatted ) );

		return trim( $formatted );
	}
}

if ( ! function_exists( 'useup_me_get_shipping_quantity_discount_amount' ) ) {
	function useup_me_get_shipping_quantity_discount_amount( $package = array() ) {
		return USEUP_ME_Shipping_Quantity_Discount::get_discount_amount_for_package( $package );
	}
}
