<?php
/**
 * Plugin Name: USEUP! Melhor Envio Customizacoes
 * Plugin URI: https://useup.com.br
 * Description: Centraliza as customizacoes da USEUP! para o plugin Melhor Envio, incluindo regras de prazo e exibicao amigavel da entrega.
 * Version: 1.0.0
 * Author: USEUP!
 * License: GPLv2 or later
 * Text Domain: useup-melhor-envio-customizacoes
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USEUP_ME_VERSION', '1.0.0' );
define( 'USEUP_ME_FILE', __FILE__ );
define( 'USEUP_ME_PATH', plugin_dir_path( __FILE__ ) );
define( 'USEUP_ME_URL', plugin_dir_url( __FILE__ ) );

require_once USEUP_ME_PATH . 'includes/class-useup-me-loader.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-settings.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-pricing.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-admin.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-checkout-polish.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-checkout-form-design.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-product-page-polish.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-melhor-envio-hooks.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-rules.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-delivery-label.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-time-extra.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-shipping-quantity-discount.php';
require_once USEUP_ME_PATH . 'includes/class-useup-me-product-shipping-calculator.php';

function useup_me_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new USEUP_ME_Loader();
	}

	return $plugin;
}

function useup_me_bootstrap() {
	useup_me_plugin()->boot();
}

add_action( 'plugins_loaded', 'useup_me_bootstrap', 20 );
