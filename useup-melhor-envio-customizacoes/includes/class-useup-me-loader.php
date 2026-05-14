<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Loader {

	const CAPABILITY = 'manage_woocommerce';

	/**
	 * @var bool
	 */
	private $booted = false;

	/**
	 * @var array
	 */
	private $dependency_notices = array();

	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		if ( $this->has_woocommerce() ) {
			( new USEUP_ME_Settings() )->init();
			( new USEUP_ME_Admin() )->init();
			( new USEUP_ME_Checkout_Polish() )->init();
			( new USEUP_ME_Checkout_Form_Design() )->init();
			( new USEUP_ME_Product_Page_Polish() )->init();
			( new USEUP_ME_Complementary_Products() )->init();
			( new USEUP_ME_Melhor_Envio_Hooks() )->init();
			( new USEUP_ME_Shipping_Quantity_Discount() )->init();
			( new USEUP_ME_Product_Shipping_Calculator() )->init();
		}

		$this->prepare_dependency_notices();

		if ( ! empty( $this->dependency_notices ) && is_admin() ) {
			add_action( 'admin_notices', array( $this, 'render_dependency_notices' ) );
		}

		if ( ! $this->has_woocommerce() || ! $this->has_melhor_envio() ) {
			return;
		}

		( new USEUP_ME_Time_Extra() )->init();
		( new USEUP_ME_Delivery_Label() )->init();
	}

	public function render_dependency_notices() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		foreach ( $this->dependency_notices as $notice ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html( $notice )
			);
		}
	}

	private function prepare_dependency_notices() {
		if ( ! $this->has_woocommerce() ) {
			$this->dependency_notices[] = 'USEUP! Melhor Envio Customizacoes requer o WooCommerce ativo para funcionar.';
		}

		if ( ! $this->has_melhor_envio() ) {
			$this->dependency_notices[] = 'USEUP! Melhor Envio Customizacoes nao encontrou o plugin Melhor Envio ativo. As configuracoes continuam disponiveis, mas os recursos especificos do Melhor Envio podem ficar limitados enquanto ele estiver ausente.';
		}
	}

	private function has_woocommerce() {
		return class_exists( 'WooCommerce' );
	}

	private function has_melhor_envio() {
		return class_exists( 'Melhor_Envio_Plugin' ) || class_exists( 'MelhorEnvio\\Services\\CalculateShippingMethodService' );
	}
}
