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
			'combine_mode'                    => 'max',
			'show_product_shipping_calculator' => false,
			'rules'                           => array(),
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
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'combine_mode'                    => ( isset( $settings['combine_mode'] ) && 'sum' === $settings['combine_mode'] ) ? 'sum' : 'max',
			'show_product_shipping_calculator' => ! empty( $settings['show_product_shipping_calculator'] ),
			'rules'                           => USEUP_ME_Rules::sanitize_rules(
				isset( $settings['rules'] ) ? $settings['rules'] : array()
			),
		);
	}

	public static function is_product_shipping_enabled( $product = null ) {
		$enabled = (bool) self::get( 'show_product_shipping_calculator', false );

		return (bool) apply_filters( 'useup_me_product_shipping_enabled', $enabled, $product );
	}
}
