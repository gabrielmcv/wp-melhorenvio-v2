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
			'enable_product_page_polish'              => true,
			'enable_product_installment_badge'        => true,
			'product_installment_badge_text'          => 'Até 12x',
			'enable_product_pix_badge'                => true,
			'product_pix_badge_text'                  => '5% no PIX',
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
			'enable_product_page_polish'              => ! isset( $settings['enable_product_page_polish'] ) || ! empty( $settings['enable_product_page_polish'] ),
			'enable_product_installment_badge'        => ! isset( $settings['enable_product_installment_badge'] ) || ! empty( $settings['enable_product_installment_badge'] ),
			'product_installment_badge_text'          => sanitize_text_field(
				isset( $settings['product_installment_badge_text'] ) ? $settings['product_installment_badge_text'] : $defaults['product_installment_badge_text']
			),
			'enable_product_pix_badge'                => ! isset( $settings['enable_product_pix_badge'] ) || ! empty( $settings['enable_product_pix_badge'] ),
			'product_pix_badge_text'                  => sanitize_text_field(
				isset( $settings['product_pix_badge_text'] ) ? $settings['product_pix_badge_text'] : $defaults['product_pix_badge_text']
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

}
