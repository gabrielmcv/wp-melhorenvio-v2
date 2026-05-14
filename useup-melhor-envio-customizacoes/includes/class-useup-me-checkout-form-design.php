<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Checkout_Form_Design {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ) );
	}

	public function enqueue_assets() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-checkout-form-design',
			USEUP_ME_URL . 'assets/css/checkout-form-design.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-checkout-form-design',
			USEUP_ME_URL . 'assets/js/checkout-form-design.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-checkout-form-design',
			'useupMeCheckoutFormDesign',
			array(
				'enabled'  => true,
				'sections' => array(
					'personal' => array(
						'number' => '1.',
						'title'  => 'Dados pessoais',
					),
					'address'  => array(
						'number' => '2.',
						'title'  => 'Endereço',
					),
					'contact'  => array(
						'number' => '3.',
						'title'  => 'Contato',
					),
				),
				'selectors' => array(
					'personal' => array(
						'firstName'  => array( '#billing_first_name_field' ),
						'lastName'   => array( '#billing_last_name_field' ),
						'personType' => array( '#billing_persontype_field', '#billing_person_type_field' ),
						'cpf'        => array( '#billing_cpf_field', '#billing_cpfcnpj_field', '#billing_cpf_cnpj_field' ),
						'birthdate'  => array( '#billing_birthdate_field', '#billing_birth_date_field', '#billing_date_of_birth_field' ),
					),
					'address'  => array(
						'country'      => array( '#billing_country_field' ),
						'postcode'     => array( '#billing_postcode_field' ),
						'address'      => array( '#billing_address_1_field' ),
						'number'       => array( '#billing_number_field' ),
						'complement'   => array( '#billing_address_2_field' ),
						'neighborhood' => array( '#billing_neighborhood_field' ),
						'city'         => array( '#billing_city_field' ),
						'state'        => array( '#billing_state_field' ),
					),
					'contact'  => array(
						'phone'           => array( '#billing_phone_field' ),
						'email'           => array( '#billing_email_field' ),
						'shippingSection' => array( '.woocommerce-shipping-fields' ),
						'orderNotes'      => array( '#order_comments_field' ),
					),
				),
			)
		);
	}

	public function add_body_class( $classes ) {
		if ( $this->is_enabled() && $this->is_checkout_context() ) {
			$classes[] = 'useup-checkout-form-design';
		}

		return $classes;
	}

	public function filter_checkout_fields( $fields ) {
		if ( ! $this->is_enabled() ) {
			return $fields;
		}

		$this->append_field_class( $fields, 'billing', 'billing_first_name', 'useup-field-first-name' );
		$this->append_field_class( $fields, 'billing', 'billing_last_name', 'useup-field-last-name' );
		$this->append_field_class( $fields, 'billing', 'billing_persontype', 'useup-field-person-type' );
		$this->append_field_class( $fields, 'billing', 'billing_person_type', 'useup-field-person-type' );
		$this->append_field_class( $fields, 'billing', 'billing_cpf', 'useup-field-cpf' );
		$this->append_field_class( $fields, 'billing', 'billing_cpfcnpj', 'useup-field-cpf' );
		$this->append_field_class( $fields, 'billing', 'billing_cpf_cnpj', 'useup-field-cpf' );
		$this->append_field_class( $fields, 'billing', 'billing_birthdate', 'useup-field-birthdate' );
		$this->append_field_class( $fields, 'billing', 'billing_birth_date', 'useup-field-birthdate' );
		$this->append_field_class( $fields, 'billing', 'billing_date_of_birth', 'useup-field-birthdate' );
		$this->append_field_class( $fields, 'billing', 'billing_country', 'useup-field-country' );
		$this->append_field_class( $fields, 'billing', 'billing_postcode', 'useup-field-postcode' );
		$this->append_field_class( $fields, 'billing', 'billing_address_1', 'useup-field-address' );
		$this->append_field_class( $fields, 'billing', 'billing_number', 'useup-field-number' );
		$this->append_field_class( $fields, 'billing', 'billing_address_2', 'useup-field-complement' );
		$this->append_field_class( $fields, 'billing', 'billing_neighborhood', 'useup-field-neighborhood' );
		$this->append_field_class( $fields, 'billing', 'billing_city', 'useup-field-city' );
		$this->append_field_class( $fields, 'billing', 'billing_state', 'useup-field-state' );
		$this->append_field_class( $fields, 'billing', 'billing_phone', 'useup-field-phone' );
		$this->append_field_class( $fields, 'billing', 'billing_email', 'useup-field-email' );
		$this->append_field_class( $fields, 'order', 'order_comments', 'useup-field-order-notes' );

		return $fields;
	}

	private function append_field_class( &$fields, $group, $key, $class_name ) {
		if ( empty( $fields[ $group ][ $key ] ) || empty( $class_name ) ) {
			return;
		}

		if ( empty( $fields[ $group ][ $key ]['class'] ) || ! is_array( $fields[ $group ][ $key ]['class'] ) ) {
			$fields[ $group ][ $key ]['class'] = array();
		}

		if ( ! in_array( $class_name, $fields[ $group ][ $key ]['class'], true ) ) {
			$fields[ $group ][ $key ]['class'][] = $class_name;
		}
	}

	private function is_enabled() {
		return USEUP_ME_Settings::is_checkout_form_design_enabled();
	}

	private function is_checkout_context() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
	}
}
