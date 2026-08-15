<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Product_Wholesale_Visibility {

	public function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_field' ) );
	}

	public function render_field() {
		echo '<div class="options_group show_if_simple show_if_variable">';

		woocommerce_wp_checkbox(
			array(
				'id'            => USEUP_ME_Pricing::META_DISABLE_WHOLESALE_PRICE,
				'label'         => 'Desativar valor de atacado',
				'description'   => 'Se marcado, o preço cadastrado neste produto será tratado como varejo e a exibição não mostrará informações de atacado.',
				'desc_tip'      => true,
				'wrapper_class' => 'show_if_simple show_if_variable',
			)
		);

		echo '</div>';
	}

	public function save_field( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$value = isset( $_POST[ USEUP_ME_Pricing::META_DISABLE_WHOLESALE_PRICE ] ) ? 'yes' : 'no';

		$product->update_meta_data( USEUP_ME_Pricing::META_DISABLE_WHOLESALE_PRICE, $value );
	}
}
