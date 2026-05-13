<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Rules {

	public static function get_settings() {
		return USEUP_ME_Settings::get_all();
	}

	public static function get_rules() {
		return USEUP_ME_Settings::get( 'rules', array() );
	}

	public static function get_empty_rule() {
		return array(
			'name'               => '',
			'enabled'            => true,
			'days'               => 0,
			'operator'           => 'and',
			'match_mode'         => 'any',
			'category_ids'       => array(),
			'tag_ids'            => array(),
			'shipping_class_ids' => array(),
			'product_ids'        => array(),
			'skus'               => array(),
		);
	}

	public static function normalize_rule( $rule ) {
		$rule = is_array( $rule ) ? $rule : array();

		return wp_parse_args( $rule, self::get_empty_rule() );
	}

	public static function sanitize_rules( $rules ) {
		$sanitized_rules = array();

		if ( ! empty( $rules ) && is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				$sanitized_rules[] = self::sanitize_rule( $rule );
			}
		}

		return array_values( $sanitized_rules );
	}

	public static function get_extra_days_for_package( $package ) {
		$settings = self::get_settings();
		$items    = self::normalize_package_items( $package );

		if ( empty( $items ) || empty( $settings['rules'] ) ) {
			return 0;
		}

		$matched_rules_days = array();

		foreach ( $settings['rules'] as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$days = max( 0, (int) $rule['days'] );

			if ( $days < 1 ) {
				continue;
			}

			if ( self::rule_matches_package( $rule, $items ) ) {
				$matched_rules_days[] = $days;
			}
		}

		if ( empty( $matched_rules_days ) ) {
			return 0;
		}

		if ( 'sum' === $settings['combine_mode'] ) {
			return array_sum( $matched_rules_days );
		}

		return max( $matched_rules_days );
	}

	private static function sanitize_rule( $rule ) {
		$raw_rule = is_array( $rule ) ? $rule : array();
		$rule     = self::normalize_rule( $raw_rule );

		return array(
			'name'               => sanitize_text_field( $rule['name'] ),
			'enabled'            => ! empty( $raw_rule['enabled'] ),
			'days'               => max( 0, (int) $rule['days'] ),
			'operator'           => ( 'or' === $rule['operator'] ) ? 'or' : 'and',
			'match_mode'         => ( 'all' === $rule['match_mode'] ) ? 'all' : 'any',
			'category_ids'       => self::sanitize_id_list( $rule['category_ids'] ),
			'tag_ids'            => self::sanitize_id_list( $rule['tag_ids'] ),
			'shipping_class_ids' => self::sanitize_id_list( $rule['shipping_class_ids'] ),
			'product_ids'        => self::sanitize_id_list( $rule['product_ids'] ),
			'skus'               => self::sanitize_skus( $rule['skus'] ),
		);
	}

	private static function sanitize_id_list( $values ) {
		if ( is_string( $values ) ) {
			$values = self::split_text_list( $values );
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$sanitized = array_map( 'absint', $values );
		$sanitized = array_filter( $sanitized );
		$sanitized = array_values( array_unique( $sanitized ) );

		return $sanitized;
	}

	private static function sanitize_skus( $values ) {
		if ( is_string( $values ) ) {
			$values = self::split_text_list( $values );
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $values as $value ) {
			$sku = wc_clean( $value );

			if ( '' === $sku ) {
				continue;
			}

			$sanitized[] = self::lowercase( $sku );
		}

		return array_values( array_unique( $sanitized ) );
	}

	private static function split_text_list( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		$value = str_replace( "\n", ',', $value );

		return array_map( 'trim', explode( ',', $value ) );
	}

	private static function normalize_package_items( $package ) {
		if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
			return array();
		}

		$items = array();

		foreach ( $package['contents'] as $content ) {
			if ( ! is_array( $content ) || empty( $content['data'] ) || ! is_a( $content['data'], 'WC_Product' ) ) {
				continue;
			}

			$product            = $content['data'];
			$catalog_product_id = self::get_catalog_product_id( $product );
			$product_ids        = array_filter(
				array(
					absint( $product->get_id() ),
					absint( $catalog_product_id ),
					absint( $product->get_parent_id() ),
				)
			);
			$sku                = $product->get_sku();

			if ( '' === $sku && $product->is_type( 'variation' ) ) {
				$parent_product = wc_get_product( $product->get_parent_id() );
				$sku            = $parent_product ? $parent_product->get_sku() : '';
			}

			$items[] = array(
				'product_ids'        => array_values( array_unique( $product_ids ) ),
				'category_ids'       => self::get_term_ids( $catalog_product_id, 'product_cat' ),
				'tag_ids'            => self::get_term_ids( $catalog_product_id, 'product_tag' ),
				'shipping_class_ids' => array_filter( array( absint( $product->get_shipping_class_id() ) ) ),
				'sku'                => self::lowercase( wc_clean( $sku ) ),
			);
		}

		return $items;
	}

	private static function get_catalog_product_id( $product ) {
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			return absint( $product->get_parent_id() );
		}

		return absint( $product->get_id() );
	}

	private static function rule_matches_package( $rule, $items ) {
		$rule = self::normalize_rule( $rule );

		if ( empty( $items ) || ! self::rule_has_conditions( $rule ) ) {
			return false;
		}

		$item_matches = array_map(
			function ( $item ) use ( $rule ) {
				return self::rule_matches_item( $rule, $item );
			},
			$items
		);

		if ( 'all' === $rule['match_mode'] ) {
			return ! in_array( false, $item_matches, true );
		}

		return in_array( true, $item_matches, true );
	}

	private static function rule_has_conditions( $rule ) {
		return ! empty( $rule['category_ids'] )
			|| ! empty( $rule['tag_ids'] )
			|| ! empty( $rule['shipping_class_ids'] )
			|| ! empty( $rule['product_ids'] )
			|| ! empty( $rule['skus'] );
	}

	private static function rule_matches_item( $rule, $item ) {
		$checks = array();

		if ( ! empty( $rule['category_ids'] ) ) {
			$checks[] = ! empty( array_intersect( $rule['category_ids'], $item['category_ids'] ) );
		}

		if ( ! empty( $rule['tag_ids'] ) ) {
			$checks[] = ! empty( array_intersect( $rule['tag_ids'], $item['tag_ids'] ) );
		}

		if ( ! empty( $rule['shipping_class_ids'] ) ) {
			$checks[] = ! empty( array_intersect( $rule['shipping_class_ids'], $item['shipping_class_ids'] ) );
		}

		if ( ! empty( $rule['product_ids'] ) ) {
			$checks[] = ! empty( array_intersect( $rule['product_ids'], $item['product_ids'] ) );
		}

		if ( ! empty( $rule['skus'] ) ) {
			$checks[] = in_array( $item['sku'], $rule['skus'], true );
		}

		if ( empty( $checks ) ) {
			return false;
		}

		if ( 'or' === $rule['operator'] ) {
			return in_array( true, $checks, true );
		}

		return ! in_array( false, $checks, true );
	}

	private static function get_term_ids( $product_id, $taxonomy ) {
		$term_ids = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
			return array();
		}

		return array_map( 'absint', $term_ids );
	}

	private static function lowercase( $value ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $value, 'UTF-8' );
		}

		return strtolower( $value );
	}
}
