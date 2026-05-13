<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Checkout_Polish {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'polish_shipping_method_label' ), 20, 2 );
	}

	public function enqueue_assets() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-checkout-polish',
			USEUP_ME_URL . 'assets/css/checkout-shipping-polish.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-checkout-polish',
			USEUP_ME_URL . 'assets/js/checkout-shipping-polish.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-checkout-polish',
			'useupMeCheckoutPolish',
			array(
				'enabled'     => true,
				'tagsText'    => 'Enviar tags lisas com o pedido',
				'tipPrefix'   => 'Dica:',
				'tipContains' => 'Adicione pelo menos 5 dias úteis',
				'pixBadge'    => '5% no PIX',
				'cardBadge'   => 'até 12x',
				'cardHelper'  => 'Você será redirecionado para concluir o pagamento com segurança.',
			)
		);
	}

	public function add_body_class( $classes ) {
		if ( $this->is_enabled() && $this->is_checkout_context() ) {
			$classes[] = 'useup-checkout-polish';
		}

		return $classes;
	}

	public function polish_shipping_method_label( $label, $method ) {
		if ( ! $this->is_enabled() ) {
			return $label;
		}

		if ( ! $this->is_checkout_context() && ! $this->is_checkout_ajax_request() ) {
			return $label;
		}

		$label = preg_replace( '/\bJeT\b/i', 'J&T Express', $label );
		$label = preg_replace( '/\bJ&T\b(?!\s*Express)/i', 'J&T Express', $label );
		$label = preg_replace( '/Jadlog\s*\.?\s*Com/i', 'Jadlog', $label );
		$label = preg_replace( '/\bSedex\b/i', 'SEDEX', $label );

		return $label;
	}

	private function is_enabled() {
		return USEUP_ME_Settings::is_checkout_polish_enabled();
	}

	private function is_checkout_context() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
	}

	private function is_checkout_ajax_request() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['wc-ajax'] ) ? sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';

		if ( 'update_order_review' === $action ) {
			return true;
		}

		$post_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return 'woocommerce_update_order_review' === $post_action;
	}
}
