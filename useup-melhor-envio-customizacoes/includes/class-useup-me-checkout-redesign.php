<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Checkout_Redesign {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'polish_shipping_method_label' ), 20, 2 );
		add_filter( 'woocommerce_order_button_text', array( $this, 'filter_order_button_text' ) );
		add_filter( 'woocommerce_checkout_coupon_message', array( $this, 'filter_coupon_message' ) );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'filter_cart_item_name' ), 20, 3 );
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'filter_checkout_cart_item_quantity' ), 20, 3 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_back_to_cart_link' ), 5 );
		add_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'render_mobile_summary_toggle' ), 5 );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'render_security_note' ), 20 );
	}

	public function enqueue_assets() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-checkout-redesign',
			USEUP_ME_URL . 'assets/css/checkout-redesign.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-checkout-redesign',
			USEUP_ME_URL . 'assets/js/checkout-redesign.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-checkout-redesign',
			'useupMeCheckoutRedesign',
			array(
				'tipPrefix'          => 'Dica:',
				'tipContains'        => 'Adicione pelo menos 5 dias úteis',
				'tagsText'           => 'Enviar tags lisas com o pedido',
				'mobileSummaryLabel' => 'Ver resumo do pedido',
				'hideSummaryLabel'   => 'Ocultar resumo do pedido',
				'couponPrefix'       => 'Você tem um cupom de desconto?',
				'pixBadge'           => '5% no PIX',
				'cardBadge'          => 'até 12x',
				'cardHelper'         => 'Você será redirecionado para concluir o pagamento com segurança.',
				'sectionTitles'      => array(
					'personal' => 'Dados pessoais',
					'address'  => 'Endereço',
					'contact'  => 'Contato',
				),
			)
		);
	}

	public function add_body_class( $classes ) {
		if ( $this->is_enabled() && $this->is_checkout_context() ) {
			$classes[] = 'useup-checkout-redesign';
		}

		return $classes;
	}

	public function render_back_to_cart_link() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}

		printf(
			'<p class="useup-checkout-back-link-wrap"><a class="useup-checkout-back-link" href="%s">%s</a></p>',
			esc_url( wc_get_cart_url() ),
			esc_html__( 'Voltar ao carrinho', 'useup-melhor-envio-customizacoes' )
		);
	}

	public function render_mobile_summary_toggle() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}
		?>
		<div class="useup-checkout-mobile-summary" hidden>
			<button type="button" class="useup-checkout-mobile-summary__button" aria-expanded="false">
				<span class="useup-checkout-mobile-summary__label">Ver resumo do pedido</span>
				<span class="useup-checkout-mobile-summary__amount"></span>
			</button>
		</div>
		<?php
	}

	public function render_security_note() {
		if ( ! $this->is_enabled() || ! $this->is_checkout_context() ) {
			return;
		}
		?>
		<p class="useup-checkout-security-note">
			<span class="useup-checkout-security-note__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 3 5.5 6v5.7c0 4.2 2.8 7.9 6.5 9.3 3.7-1.4 6.5-5.1 6.5-9.3V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m9.5 12 1.7 1.7 3.3-3.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php echo esc_html__( 'Pagamento processado em ambiente seguro', 'useup-melhor-envio-customizacoes' ); ?>
		</p>
		<?php
	}

	public function filter_order_button_text( $text ) {
		if ( ! $this->is_enabled() ) {
			return $text;
		}

		if ( ! $this->is_checkout_context() && ! $this->is_checkout_ajax_request() ) {
			return $text;
		}

		return 'Finalizar pedido';
	}

	public function filter_coupon_message( $message ) {
		if ( ! $this->is_enabled() ) {
			return $message;
		}

		if ( ! $this->is_checkout_context() && ! $this->is_checkout_ajax_request() ) {
			return $message;
		}

		return 'Você tem um cupom de desconto? <a href="#" class="showcoupon">Clique aqui</a> e informe o código do seu cupom';
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

	public function filter_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
		if ( ! $this->is_enabled() ) {
			return $product_name;
		}

		if ( ! $this->is_checkout_context() && ! $this->is_checkout_ajax_request() ) {
			return $product_name;
		}

		if ( false !== strpos( $product_name, 'useup-checkout-order-card' ) ) {
			return $product_name;
		}

		$product   = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ? $cart_item['data'] : null;
		$thumbnail = $product ? $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'useup-checkout-order-card__image' ) ) : '';
		$quantity  = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

		return sprintf(
			'<span class="useup-checkout-order-card">%s<span class="useup-checkout-order-card__content"><span class="useup-checkout-order-card__name">%s</span><span class="useup-checkout-order-card__qty">x%s</span></span></span>',
			$thumbnail ? '<span class="useup-checkout-order-card__thumb">' . $thumbnail . '</span>' : '',
			wp_kses_post( $product_name ),
			esc_html( $quantity )
		);
	}

	public function filter_checkout_cart_item_quantity( $quantity_html, $cart_item, $cart_item_key ) {
		if ( ! $this->is_enabled() ) {
			return $quantity_html;
		}

		if ( ! $this->is_checkout_context() && ! $this->is_checkout_ajax_request() ) {
			return $quantity_html;
		}

		return '';
	}

	private function is_enabled() {
		return USEUP_ME_Settings::is_checkout_polish_enabled();
	}

	private function is_checkout_context() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_order_received_page();
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
