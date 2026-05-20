<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Settings {

	const OPTION_KEY = 'useup_me_settings';

	public function init() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public function register_setting() {
		register_setting(
			'useup_me_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_callback' )
		);
	}

	public function sanitize_callback( $settings ) {
		return self::sanitize( $settings );
	}

	public static function get_defaults() {
		return array(
			'combine_mode'                             => 'max',
			'show_product_shipping_calculator'        => false,
			'product_shipping_free_shipping_threshold' => 199.0,
			'product_shipping_free_shipping_message'   => 'Frete grátis acima de {amount}.',
			'enable_quantity_price_control'           => false,
			'wholesale_min_quantity'                  => 5,
			'wholesale_quantity_count_mode'           => 'total_cart_items',
			'quantity_price_adjustments'              => self::get_default_quantity_price_adjustments(),
			'enable_shipping_quantity_discount'       => false,
			'shipping_discount_per_item'              => 8.0,
			'shipping_discount_max_items'             => 4,
			'shipping_discount_label'                 => 'Desconto de frete por quantidade',
			'shipping_discount_apply_to_free_shipping' => false,
			'shipping_discount_allowed_methods'       => array(),
			'enable_complementary_products'           => false,
			'complementary_products_title'            => 'Complete com uma corrente',
			'complementary_products_subtitle'         => 'O pingente e vendido separadamente. Escolha uma ou mais correntes, se desejar.',
			'complementary_product_ids'               => array(),
			'complementary_display_mode'              => 'selected_categories',
			'complementary_category_ids'              => array(),
			'complementary_tag_ids'                   => array(),
			'complementary_target_product_ids'        => array(),
			'enable_ajax_shop_filter'                 => false,
			'ajax_shop_filter_items'                  => self::get_default_ajax_shop_filter_items(),
			'ajax_shop_products_per_page'             => 12,
			'ajax_shop_pagination_mode'               => 'pagination',
			'enable_premium_category_pages'          => false,
			'category_use_description_subtitle'      => true,
			'category_show_title_ornament'           => true,
			'enable_product_page_polish'              => true,
			'enable_product_installment_badge'        => true,
			'product_installment_badge_text'          => 'Até 12x',
			'enable_product_pix_badge'                => true,
			'product_pix_badge_text'                  => '5% no PIX',
			'product_wholesale_tooltip_line_1'        => 'Preço de atacado da peça.',
			'product_wholesale_tooltip_line_2'        => 'O valor de varejo aparece logo abaixo.',
			'retail_markup_percent'                   => 50.0,
			'retail_markup_fixed'                     => 10.0,
			'enable_checkout_polish'                  => true,
			'enable_checkout_form_design'             => false,
			'rules'                                   => array(),
		);
	}

	public static function get_all() {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( self::sanitize( $settings ), self::get_defaults() );
	}

	public static function get( $key, $default = null ) {
		$settings = self::get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function sanitize( $settings ) {
		$defaults = self::get_defaults();
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'combine_mode'                             => ( isset( $settings['combine_mode'] ) && 'sum' === $settings['combine_mode'] ) ? 'sum' : 'max',
			'show_product_shipping_calculator'        => ! empty( $settings['show_product_shipping_calculator'] ),
			'product_shipping_free_shipping_threshold' => self::sanitize_free_shipping_threshold(
				isset( $settings['product_shipping_free_shipping_threshold'] ) ? $settings['product_shipping_free_shipping_threshold'] : $defaults['product_shipping_free_shipping_threshold']
			),
			'product_shipping_free_shipping_message'   => sanitize_text_field(
				isset( $settings['product_shipping_free_shipping_message'] ) ? $settings['product_shipping_free_shipping_message'] : $defaults['product_shipping_free_shipping_message']
			),
			'enable_quantity_price_control'           => ! empty( $settings['enable_quantity_price_control'] ),
			'wholesale_min_quantity'                  => self::sanitize_positive_int(
				isset( $settings['wholesale_min_quantity'] ) ? $settings['wholesale_min_quantity'] : $defaults['wholesale_min_quantity'],
				$defaults['wholesale_min_quantity']
			),
			'wholesale_quantity_count_mode'           => self::sanitize_enum(
				isset( $settings['wholesale_quantity_count_mode'] ) ? $settings['wholesale_quantity_count_mode'] : $defaults['wholesale_quantity_count_mode'],
				array( 'total_cart_items', 'eligible_products_only' ),
				$defaults['wholesale_quantity_count_mode']
			),
			'quantity_price_adjustments'              => self::sanitize_quantity_price_adjustments(
				isset( $settings['quantity_price_adjustments'] ) ? $settings['quantity_price_adjustments'] : $defaults['quantity_price_adjustments']
			),
			'enable_shipping_quantity_discount'       => ! empty( $settings['enable_shipping_quantity_discount'] ),
			'shipping_discount_per_item'              => self::sanitize_decimal(
				isset( $settings['shipping_discount_per_item'] ) ? $settings['shipping_discount_per_item'] : $defaults['shipping_discount_per_item'],
				$defaults['shipping_discount_per_item']
			),
			'shipping_discount_max_items'             => self::sanitize_positive_int(
				isset( $settings['shipping_discount_max_items'] ) ? $settings['shipping_discount_max_items'] : $defaults['shipping_discount_max_items'],
				$defaults['shipping_discount_max_items']
			),
			'shipping_discount_label'                 => sanitize_text_field(
				isset( $settings['shipping_discount_label'] ) ? $settings['shipping_discount_label'] : $defaults['shipping_discount_label']
			),
			'shipping_discount_apply_to_free_shipping' => ! empty( $settings['shipping_discount_apply_to_free_shipping'] ),
			'shipping_discount_allowed_methods'       => self::sanitize_string_list(
				isset( $settings['shipping_discount_allowed_methods'] ) ? $settings['shipping_discount_allowed_methods'] : $defaults['shipping_discount_allowed_methods']
			),
			'enable_complementary_products'           => ! empty( $settings['enable_complementary_products'] ),
			'complementary_products_title'            => sanitize_text_field(
				isset( $settings['complementary_products_title'] ) ? $settings['complementary_products_title'] : $defaults['complementary_products_title']
			),
			'complementary_products_subtitle'         => sanitize_textarea_field(
				isset( $settings['complementary_products_subtitle'] ) ? $settings['complementary_products_subtitle'] : $defaults['complementary_products_subtitle']
			),
			'complementary_product_ids'               => self::sanitize_positive_id_list(
				isset( $settings['complementary_product_ids'] ) ? $settings['complementary_product_ids'] : $defaults['complementary_product_ids']
			),
			'complementary_display_mode'              => self::sanitize_enum(
				isset( $settings['complementary_display_mode'] ) ? $settings['complementary_display_mode'] : $defaults['complementary_display_mode'],
				array( 'all_products', 'selected_categories', 'selected_tags', 'selected_products' ),
				$defaults['complementary_display_mode']
			),
			'complementary_category_ids'              => self::sanitize_positive_id_list(
				isset( $settings['complementary_category_ids'] ) ? $settings['complementary_category_ids'] : $defaults['complementary_category_ids']
			),
			'complementary_tag_ids'                   => self::sanitize_positive_id_list(
				isset( $settings['complementary_tag_ids'] ) ? $settings['complementary_tag_ids'] : $defaults['complementary_tag_ids']
			),
			'complementary_target_product_ids'        => self::sanitize_positive_id_list(
				isset( $settings['complementary_target_product_ids'] ) ? $settings['complementary_target_product_ids'] : $defaults['complementary_target_product_ids']
			),
			'enable_ajax_shop_filter'                 => ! empty( $settings['enable_ajax_shop_filter'] ),
			'ajax_shop_filter_items'                  => self::sanitize_ajax_shop_filter_items(
				isset( $settings['ajax_shop_filter_items'] ) ? $settings['ajax_shop_filter_items'] : $defaults['ajax_shop_filter_items']
			),
			'ajax_shop_products_per_page'             => self::sanitize_positive_int(
				isset( $settings['ajax_shop_products_per_page'] ) ? $settings['ajax_shop_products_per_page'] : $defaults['ajax_shop_products_per_page'],
				$defaults['ajax_shop_products_per_page']
			),
			'ajax_shop_pagination_mode'               => self::sanitize_enum(
				isset( $settings['ajax_shop_pagination_mode'] ) ? $settings['ajax_shop_pagination_mode'] : $defaults['ajax_shop_pagination_mode'],
				array( 'pagination', 'load_more' ),
				$defaults['ajax_shop_pagination_mode']
			),
			'enable_premium_category_pages'          => ! empty( $settings['enable_premium_category_pages'] ),
			'category_use_description_subtitle'      => ! isset( $settings['category_use_description_subtitle'] ) || ! empty( $settings['category_use_description_subtitle'] ),
			'category_show_title_ornament'           => ! isset( $settings['category_show_title_ornament'] ) || ! empty( $settings['category_show_title_ornament'] ),
			'enable_product_page_polish'              => ! isset( $settings['enable_product_page_polish'] ) || ! empty( $settings['enable_product_page_polish'] ),
			'enable_product_installment_badge'        => ! isset( $settings['enable_product_installment_badge'] ) || ! empty( $settings['enable_product_installment_badge'] ),
			'product_installment_badge_text'          => sanitize_text_field(
				isset( $settings['product_installment_badge_text'] ) ? $settings['product_installment_badge_text'] : $defaults['product_installment_badge_text']
			),
			'enable_product_pix_badge'                => ! isset( $settings['enable_product_pix_badge'] ) || ! empty( $settings['enable_product_pix_badge'] ),
			'product_pix_badge_text'                  => sanitize_text_field(
				isset( $settings['product_pix_badge_text'] ) ? $settings['product_pix_badge_text'] : $defaults['product_pix_badge_text']
			),
			'product_wholesale_tooltip_line_1'        => sanitize_text_field(
				isset( $settings['product_wholesale_tooltip_line_1'] ) ? $settings['product_wholesale_tooltip_line_1'] : $defaults['product_wholesale_tooltip_line_1']
			),
			'product_wholesale_tooltip_line_2'        => sanitize_text_field(
				isset( $settings['product_wholesale_tooltip_line_2'] ) ? $settings['product_wholesale_tooltip_line_2'] : $defaults['product_wholesale_tooltip_line_2']
			),
			'retail_markup_percent'                   => self::sanitize_decimal(
				isset( $settings['retail_markup_percent'] ) ? $settings['retail_markup_percent'] : $defaults['retail_markup_percent'],
				$defaults['retail_markup_percent']
			),
			'retail_markup_fixed'                     => self::sanitize_decimal(
				isset( $settings['retail_markup_fixed'] ) ? $settings['retail_markup_fixed'] : $defaults['retail_markup_fixed'],
				$defaults['retail_markup_fixed']
			),
			'enable_checkout_polish'                  => ! isset( $settings['enable_checkout_polish'] ) || ! empty( $settings['enable_checkout_polish'] ),
			'enable_checkout_form_design'             => ! empty( $settings['enable_checkout_form_design'] ),
			'rules'                                   => USEUP_ME_Rules::sanitize_rules(
				isset( $settings['rules'] ) ? $settings['rules'] : array()
			),
		);
	}

	public static function is_product_shipping_enabled( $product = null ) {
		$enabled = (bool) self::get( 'show_product_shipping_calculator', false );

		return (bool) apply_filters( 'useup_me_product_shipping_enabled', $enabled, $product );
	}

	public static function is_product_page_polish_enabled() {
		$enabled = (bool) self::get( 'enable_product_page_polish', true );

		return (bool) apply_filters( 'useup_me_product_page_polish_enabled', $enabled );
	}

	public static function is_checkout_polish_enabled() {
		$enabled = (bool) self::get( 'enable_checkout_polish', true );

		return (bool) apply_filters( 'useup_me_enable_checkout_polish', $enabled );
	}

	public static function is_checkout_form_design_enabled() {
		$enabled = (bool) self::get( 'enable_checkout_form_design', false );

		return (bool) apply_filters( 'useup_me_enable_checkout_form_design', $enabled );
	}

	public static function is_quantity_price_control_enabled() {
		$enabled = (bool) self::get( 'enable_quantity_price_control', false );

		return (bool) apply_filters( 'useup_me_enable_quantity_price_control', $enabled );
	}

	public static function is_shipping_quantity_discount_enabled() {
		$enabled = (bool) self::get( 'enable_shipping_quantity_discount', false );

		return (bool) apply_filters( 'useup_me_shipping_quantity_discount_enabled', $enabled );
	}

	public static function is_complementary_products_enabled() {
		$enabled = (bool) self::get( 'enable_complementary_products', false );

		return (bool) apply_filters( 'useup_me_enable_complementary_products', $enabled );
	}

	public static function is_ajax_shop_filter_enabled() {
		$enabled = (bool) self::get( 'enable_ajax_shop_filter', false );

		return (bool) apply_filters( 'useup_me_enable_ajax_shop_filter', $enabled );
	}

	public static function is_premium_category_pages_enabled() {
		$enabled = (bool) self::get( 'enable_premium_category_pages', false );

		return (bool) apply_filters( 'useup_me_enable_premium_category_pages', $enabled );
	}

	public static function get_wholesale_min_quantity() {
		return max( 1, (int) self::get( 'wholesale_min_quantity', 5 ) );
	}

	public static function get_wholesale_quantity_count_mode() {
		return self::sanitize_enum(
			(string) self::get( 'wholesale_quantity_count_mode', 'total_cart_items' ),
			array( 'total_cart_items', 'eligible_products_only' ),
			'total_cart_items'
		);
	}

	public static function get_quantity_price_adjustments() {
		$adjustments = self::sanitize_quantity_price_adjustments(
			self::get( 'quantity_price_adjustments', self::get_default_quantity_price_adjustments() )
		);

		return array_values(
			array_filter(
				$adjustments,
				static function ( $adjustment ) {
					return ! empty( $adjustment['enabled'] );
				}
			)
		);
	}

	private static function sanitize_free_shipping_threshold( $value ) {
		if ( '' === $value || null === $value ) {
			return 0.0;
		}

		$value = is_string( $value ) ? str_replace( ',', '.', $value ) : $value;

		if ( ! is_numeric( $value ) ) {
			return (float) self::get_defaults()['product_shipping_free_shipping_threshold'];
		}

		return max( 0, (float) $value );
	}

	private static function sanitize_decimal( $value, $default ) {
		if ( '' === $value || null === $value ) {
			return (float) $default;
		}

		$value = is_string( $value ) ? str_replace( ',', '.', $value ) : $value;

		if ( ! is_numeric( $value ) ) {
			return (float) $default;
		}

		return max( 0, (float) $value );
	}

	private static function sanitize_positive_int( $value, $default ) {
		$value = absint( $value );

		if ( $value < 1 ) {
			return max( 1, (int) $default );
		}

		return $value;
	}

	private static function sanitize_positive_id_list( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $values as $value ) {
			$value = absint( $value );

			if ( $value > 0 ) {
				$sanitized[] = $value;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}

	private static function sanitize_enum( $value, $allowed_values, $default ) {
		$value = sanitize_text_field( (string) $value );

		if ( ! in_array( $value, $allowed_values, true ) ) {
			return $default;
		}

		return $value;
	}

	private static function sanitize_string_list( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $values as $value ) {
			$value = sanitize_text_field( (string) $value );

			if ( '' !== $value ) {
				$sanitized[] = $value;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}

	private static function sanitize_ajax_shop_filter_items( $items ) {
		$allowed_types = array( 'all', 'category', 'tag', 'best_sellers', 'custom' );
		$allowed_icons = array( 'grid', 'flame', 'sparkle', 'necklace', 'escapulario', 'foto', 'bracelet', 'pendant', 'link', 'heart', 'cross' );

		if ( ! is_array( $items ) ) {
			return self::get_default_ajax_shop_filter_items();
		}

		$sanitized = array();

		foreach ( $items as $item ) {
			$type = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'all';
			$icon = isset( $item['icon'] ) ? sanitize_key( (string) $item['icon'] ) : '';

			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'all';
			}

			if ( ! in_array( $icon, $allowed_icons, true ) ) {
				$icon = '';
			}

			$sanitized[] = array(
				'label'         => sanitize_text_field( isset( $item['label'] ) ? $item['label'] : '' ),
				'type'          => $type,
				'category_slug' => sanitize_title( isset( $item['category_slug'] ) ? $item['category_slug'] : '' ),
				'tag_slug'      => sanitize_title( isset( $item['tag_slug'] ) ? $item['tag_slug'] : '' ),
				'custom_key'    => sanitize_key( isset( $item['custom_key'] ) ? $item['custom_key'] : '' ),
				'icon'          => $icon,
				'order'         => absint( isset( $item['order'] ) ? $item['order'] : 0 ),
				'enabled'       => ! empty( $item['enabled'] ),
			);
		}

		if ( empty( $sanitized ) ) {
			return self::get_default_ajax_shop_filter_items();
		}

		return array_values( $sanitized );
	}

	private static function get_default_ajax_shop_filter_items() {
		return array(
			array(
				'label'         => 'Todos',
				'type'          => 'all',
				'category_slug' => '',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'grid',
				'order'         => 10,
				'enabled'       => true,
			),
			array(
				'label'         => 'Mais vendidos',
				'type'          => 'best_sellers',
				'category_slug' => '',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'flame',
				'order'         => 20,
				'enabled'       => true,
			),
			array(
				'label'         => 'Personalizados',
				'type'          => 'category',
				'category_slug' => 'personalizados',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'sparkle',
				'order'         => 30,
				'enabled'       => true,
			),
			array(
				'label'         => 'Colares',
				'type'          => 'category',
				'category_slug' => 'colares',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'necklace',
				'order'         => 40,
				'enabled'       => true,
			),
			array(
				'label'         => 'Pulseiras',
				'type'          => 'category',
				'category_slug' => 'pulseiras',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'bracelet',
				'order'         => 50,
				'enabled'       => true,
			),
			array(
				'label'         => 'Pingentes',
				'type'          => 'category',
				'category_slug' => 'pingentes',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'pendant',
				'order'         => 60,
				'enabled'       => true,
			),
			array(
				'label'         => 'Correntes',
				'type'          => 'category',
				'category_slug' => 'correntes',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'link',
				'order'         => 70,
				'enabled'       => true,
			),
			array(
				'label'         => 'Berloques',
				'type'          => 'category',
				'category_slug' => 'berloques',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'heart',
				'order'         => 80,
				'enabled'       => true,
			),
			array(
				'label'         => 'Religiosos',
				'type'          => 'category',
				'category_slug' => 'religiosos',
				'tag_slug'      => '',
				'custom_key'    => '',
				'icon'          => 'cross',
				'order'         => 90,
				'enabled'       => true,
			),
		);
	}

	private static function sanitize_quantity_price_adjustments( $adjustments ) {
		if ( ! is_array( $adjustments ) ) {
			return self::get_default_quantity_price_adjustments();
		}

		$sanitized = array();

		foreach ( $adjustments as $adjustment ) {
			$type    = isset( $adjustment['type'] ) ? sanitize_key( (string) $adjustment['type'] ) : 'fixed';
			$value   = self::sanitize_decimal( isset( $adjustment['value'] ) ? $adjustment['value'] : 0, 0 );
			$order   = absint( isset( $adjustment['order'] ) ? $adjustment['order'] : 0 );
			$enabled = ! empty( $adjustment['enabled'] );

			if ( ! in_array( $type, array( 'percent', 'fixed' ), true ) ) {
				$type = 'fixed';
			}

			if ( $value <= 0 ) {
				continue;
			}

			$sanitized[] = array(
				'type'    => $type,
				'value'   => $value,
				'order'   => $order,
				'enabled' => $enabled,
			);
		}

		usort(
			$sanitized,
			static function ( $left, $right ) {
				return (int) $left['order'] <=> (int) $right['order'];
			}
		);

		return $sanitized;
	}

	private static function get_default_quantity_price_adjustments() {
		return array(
			array(
				'type'    => 'percent',
				'value'   => 50,
				'order'   => 10,
				'enabled' => true,
			),
			array(
				'type'    => 'fixed',
				'value'   => 10,
				'order'   => 20,
				'enabled' => true,
			),
		);
	}

}
