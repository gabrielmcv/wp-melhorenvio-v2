<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Product_Shipping_Calculator {

	/**
	 * @var string
	 */
	private $current_capture_key = '';

	/**
	 * @var array
	 */
	private $captured_delivery_windows = array();

	public function init() {
		$position_hook = apply_filters( 'useup_me_product_shipping_position_hook', 'woocommerce_after_add_to_cart_button' );

		if ( is_string( $position_hook ) && '' !== $position_hook ) {
			add_action( $position_hook, array( $this, 'render' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_useup_me_product_shipping_quote', array( $this, 'ajax_quote' ) );
		add_action( 'wp_ajax_nopriv_useup_me_product_shipping_quote', array( $this, 'ajax_quote' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'filter_checkout_postcode_value' ), 10, 2 );
	}

	public function enqueue_assets() {
		$product = $this->get_current_product();

		if ( ! $this->should_render_for_product( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-product-shipping',
			USEUP_ME_URL . 'assets/css/product-shipping-calculator.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-product-shipping',
			USEUP_ME_URL . 'assets/js/product-shipping-calculator.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		$saved_postcode = $this->get_saved_postcode();

		wp_localize_script(
			'useup-me-product-shipping',
			'useupMeProductShipping',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'useup_me_product_shipping_quote' ),
				'productId'        => $product ? $product->get_id() : 0,
				'hasSavedPostcode' => ! empty( $saved_postcode ),
				'savedPostcode'    => $this->format_postcode( $saved_postcode ),
				'i18n'             => array(
					'invalidPostcode' => 'Informe um CEP válido com 8 números.',
					'calculating'     => 'Calculando...',
					'changePostcode'  => 'Trocar CEP',
					'seeOptions'      => 'Ver opções',
					'noRates'         => 'Não encontramos opções de entrega para este CEP.',
					'genericError'    => 'Não foi possível calcular o frete agora. Tente novamente em instantes.',
					'selectVariation' => 'Selecione uma variação para calcular a entrega.',
				),
			)
		);
	}

	public function render() {
		$product = $this->get_current_product();

		if ( ! $this->should_render_for_product( $product ) ) {
			return;
		}

		$saved_postcode     = $this->get_saved_postcode();
		$formatted_postcode = $this->format_postcode( $saved_postcode );
		?>
		<div
			class="useup-me-product-shipping"
			data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
			data-has-postcode="<?php echo ! empty( $saved_postcode ) ? '1' : '0'; ?>"
			data-postcode="<?php echo esc_attr( $formatted_postcode ); ?>"
		>
			<div class="useup-me-product-shipping__header">
				<div class="useup-me-product-shipping__title">
					<span class="useup-me-product-shipping__icon useup-me-product-shipping__title-icon" aria-hidden="true">
						<?php echo wp_kses( $this->get_icon_svg(), $this->get_allowed_svg_tags() ); ?>
					</span>
					<span>Entrega e prazo</span>
				</div>

				<button
					type="button"
					class="useup-me-product-shipping__toggle"
					aria-label="Recolher ou expandir entrega e prazo"
					aria-expanded="true"
				>
					<span aria-hidden="true">^</span>
				</button>
			</div>

			<div class="useup-me-product-shipping__body">
				<div class="useup-me-product-shipping__saved" <?php echo empty( $saved_postcode ) ? 'hidden' : ''; ?>>
					<div class="useup-me-product-shipping__current-cep">
						<span>Sua entrega será calculada para o CEP <strong class="useup-me-product-shipping__postcode-value"><?php echo esc_html( $formatted_postcode ); ?></strong>.</span>
						<button type="button" class="useup-me-product-shipping__change-cep">Trocar CEP</button>
					</div>
				</div>

				<div class="useup-me-product-shipping__manual" <?php echo ! empty( $saved_postcode ) ? 'hidden' : ''; ?>>
					<p class="useup-me-product-shipping__description">Informe seu CEP para ver quando sua joia chega até você.</p>

					<div class="useup-me-product-shipping__form">
						<input
							type="text"
							class="useup-me-product-shipping__input"
							inputmode="numeric"
							autocomplete="postal-code"
							maxlength="9"
							placeholder="00000-000"
							aria-label="CEP"
							value="<?php echo esc_attr( $formatted_postcode ); ?>"
						/>
						<button type="button" class="useup-me-product-shipping__button">Ver opções</button>
					</div>
				</div>

				<div class="useup-me-product-shipping__error" role="alert" hidden></div>
				<div class="useup-me-product-shipping__results" hidden></div>
			</div>
		</div>
		<?php
	}

	public function ajax_quote() {
		try {
			check_ajax_referer( 'useup_me_product_shipping_quote', 'nonce' );

			$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
			$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
			$quantity     = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
			$postcode     = isset( $_POST['postcode'] ) ? $this->sanitize_postcode( wp_unslash( $_POST['postcode'] ) ) : '';

			if ( $product_id < 1 ) {
				$this->send_error( 'Não foi possível identificar o produto para cálculo.' );
			}

			if ( ! $this->is_valid_postcode( $postcode ) ) {
				$this->send_error( 'Informe um CEP válido com 8 números.' );
			}

			$product = $this->resolve_product_for_quote( $product_id, $variation_id );

			if ( is_wp_error( $product ) ) {
				$this->send_error( $product->get_error_message() );
			}

			$this->persist_postcode( $postcode );

			$package = $this->build_shipping_package( $product, $quantity, $postcode );
			$result  = $this->calculate_shipping_rates( $package, $product );

			if ( empty( $result['rates'] ) ) {
				$this->send_error( 'Não encontramos opções de entrega para este CEP.' );
			}

			wp_send_json_success(
				array(
					'postcode'           => $this->format_postcode( $postcode ),
					'raw_postcode'       => $postcode,
					'estimate_label'     => $result['estimate_label'],
					'rates'              => $result['rates'],
					'free_shipping_note' => $this->get_free_shipping_notice( $product ),
				)
			);
		} catch ( Exception $exception ) {
			$this->send_error( 'Não foi possível calcular o frete agora. Tente novamente em instantes.' );
		}
	}

	public function filter_checkout_postcode_value( $value, $input ) {
		if ( ! in_array( $input, array( 'billing_postcode', 'shipping_postcode' ), true ) ) {
			return $value;
		}

		if ( ! empty( $value ) ) {
			return $value;
		}

		$postcode = $this->get_saved_postcode();

		return ! empty( $postcode ) ? $postcode : $value;
	}

	public function capture_melhor_envio_delivery_data( $default_label, $delivery_range, $time_extra, $package, $products, $quotation, $service ) {
		if ( '' === $this->current_capture_key ) {
			return $default_label;
		}

		$window = USEUP_ME_Delivery_Label::build_window_from_business_days( $delivery_range, $time_extra );

		if ( ! empty( $window ) ) {
			$this->captured_delivery_windows[ $this->current_capture_key ] = $window;
		}

		return $default_label;
	}

	private function should_render_for_product( $product ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		if ( ! $product->exists() || ! $product->needs_shipping() ) {
			return false;
		}

		return USEUP_ME_Settings::is_product_shipping_enabled( $product );
	}

	private function get_current_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		$product_id = get_the_ID();

		return $product_id ? wc_get_product( $product_id ) : null;
	}

	private function resolve_product_for_quote( $product_id, $variation_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->exists() ) {
			return new WP_Error( 'useup_me_invalid_product', 'Não foi possível identificar o produto para cálculo.' );
		}

		if ( 'publish' !== $product->get_status() ) {
			return new WP_Error( 'useup_me_unavailable_product', 'Este produto não está disponível para cálculo no momento.' );
		}

		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || ! $variation->exists() || 'variation' !== $variation->get_type() ) {
				return new WP_Error( 'useup_me_invalid_variation', 'Selecione uma variação para calcular a entrega.' );
			}

			if ( (int) $variation->get_parent_id() !== (int) $product_id ) {
				return new WP_Error( 'useup_me_invalid_variation', 'Selecione uma variação para calcular a entrega.' );
			}

			$product = $variation;
		} elseif ( $product->is_type( 'variable' ) ) {
			return new WP_Error( 'useup_me_variation_required', 'Selecione uma variação para calcular a entrega.' );
		}

		if ( ! $product->needs_shipping() ) {
			return new WP_Error( 'useup_me_virtual_product', 'Este produto não possui cálculo de entrega.' );
		}

		return $product;
	}

	private function build_shipping_package( WC_Product $product, $quantity, $postcode ) {
		$product_id     = $product->get_id();
		$catalog_id     = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
		$product_price  = (float) $product->get_price();
		$contents_total = $product_price * max( 1, (int) $quantity );
		$item_key       = sprintf( 'useup-me-%d-%d', $product_id, max( 1, (int) $quantity ) );
		$customer       = function_exists( 'WC' ) ? WC()->customer : null;
		$state          = ( $customer && method_exists( $customer, 'get_shipping_state' ) ) ? $customer->get_shipping_state() : '';

		$package_item = array(
			'key'               => $item_key,
			'data'              => $product,
			'product_id'        => $catalog_id,
			'variation_id'      => $product->is_type( 'variation' ) ? $product_id : 0,
			'variation'         => $product->is_type( 'variation' ) ? $product->get_variation_attributes() : array(),
			'quantity'          => max( 1, (int) $quantity ),
			'line_total'        => $contents_total,
			'line_subtotal'     => $contents_total,
			'line_tax'          => 0,
			'line_subtotal_tax' => 0,
		);

		$formatted_data = $this->get_melhor_envio_formatted_data( $product, $quantity );

		if ( ! empty( $formatted_data ) ) {
			$package_item['formatted_data'] = $formatted_data;
		}

		return array(
			'ship_via'                 => array(),
			'applied_coupons'          => array(),
			'user'                     => array( 'ID' => get_current_user_id() ),
			'contents'                 => array( $item_key => $package_item ),
			'contents_cost'            => $contents_total,
			'cart_subtotal'            => $contents_total,
			'product_page_calculation' => true,
			'destination'              => array(
				'country'   => 'BR',
				'state'     => $state,
				'postcode'  => $postcode,
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
		);
	}

	private function calculate_shipping_rates( $package, WC_Product $product ) {
		$zone             = WC_Shipping_Zones::get_zone_matching_package( $package );
		$shipping_methods = $zone ? $zone->get_shipping_methods( true ) : array();
		$formatted_rates  = array();
		$rate_windows     = array();

		$this->captured_delivery_windows = array();

		if ( function_exists( 'has_filter' ) && has_filter( 'useup_melhor_envio_delivery_deadline_label' ) ) {
			add_filter(
				'useup_melhor_envio_delivery_deadline_label',
				array( $this, 'capture_melhor_envio_delivery_data' ),
				5,
				7
			);
		}

		foreach ( $shipping_methods as $shipping_method ) {
			if ( ! is_object( $shipping_method ) || ! method_exists( $shipping_method, 'get_rates_for_package' ) ) {
				continue;
			}

			$method_key                = $this->get_shipping_method_key( $shipping_method );
			$this->current_capture_key = $method_key;
			$rates                     = $shipping_method->get_rates_for_package( $package );
			$this->current_capture_key = '';

			if ( empty( $rates ) ) {
				continue;
			}

			$window = isset( $this->captured_delivery_windows[ $method_key ] )
				? $this->captured_delivery_windows[ $method_key ]
				: array();

			foreach ( $rates as $rate ) {
				$delivery_time = $this->build_rate_delivery_time( $rate, $window, $shipping_method, $product );

				if ( ! empty( $window ) ) {
					$rate_windows[] = $window;
				}

				$formatted_rates[] = array(
					'id'            => method_exists( $rate, 'get_id' ) ? $rate->get_id() : $method_key,
					'label'         => $this->get_shipping_method_label( $shipping_method, $rate ),
					'cost'          => $this->get_shipping_rate_price( $rate ),
					'delivery_time' => $delivery_time,
					'raw_cost'      => method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0,
				);
			}
		}

		remove_filter(
			'useup_melhor_envio_delivery_deadline_label',
			array( $this, 'capture_melhor_envio_delivery_data' ),
			5
		);

		usort(
			$formatted_rates,
			function ( $first, $second ) {
				if ( $first['raw_cost'] === $second['raw_cost'] ) {
					return 0;
				}

				return ( $first['raw_cost'] < $second['raw_cost'] ) ? -1 : 1;
			}
		);

		$formatted_rates = apply_filters(
			'useup_me_product_shipping_result_rates',
			$formatted_rates,
			$product,
			$package
		);

		return array(
			'rates'          => is_array( $formatted_rates ) ? array_values( $formatted_rates ) : array(),
			'estimate_label' => $this->build_estimate_label( $rate_windows, $formatted_rates, $product ),
		);
	}

	private function build_rate_delivery_time( $rate, $window, $shipping_method, WC_Product $product ) {
		if ( ! empty( $window ) ) {
			return USEUP_ME_Delivery_Label::build_product_shipping_label_from_window(
				$window,
				array(
					'scope'           => 'rate',
					'product'         => $product,
					'shipping_method' => $shipping_method,
					'rate'            => $rate,
				)
			);
		}

		$meta_data = $this->get_rate_meta_data( $rate );

		if ( ! empty( $meta_data['delivery_time'] ) ) {
			return $this->normalize_delivery_time_text(
				$meta_data['delivery_time'],
				array(
					'scope'           => 'rate',
					'product'         => $product,
					'shipping_method' => $shipping_method,
					'rate'            => $rate,
				)
			);
		}

		return '';
	}

	private function build_estimate_label( $windows, $rates, WC_Product $product ) {
		if ( ! empty( $windows ) ) {
			$min_date = null;
			$max_date = null;

			foreach ( $windows as $window ) {
				if ( empty( $window['min_date'] ) || empty( $window['max_date'] ) ) {
					continue;
				}

				if ( ! $min_date || $window['min_date'] < $min_date ) {
					$min_date = $window['min_date'];
				}

				if ( ! $max_date || $window['max_date'] > $max_date ) {
					$max_date = $window['max_date'];
				}
			}

			if ( $min_date && $max_date ) {
				return USEUP_ME_Delivery_Label::build_product_shipping_label_from_window(
					array(
						'min_date' => $min_date,
						'max_date' => $max_date,
					),
					array(
						'scope'   => 'estimate',
						'product' => $product,
					)
				);
			}
		}

		foreach ( $rates as $rate ) {
			if ( ! empty( $rate['delivery_time'] ) ) {
				return $rate['delivery_time'];
			}
		}

		return '';
	}

	private function get_melhor_envio_formatted_data( WC_Product $product, $quantity ) {
		if ( ! class_exists( 'MelhorEnvio\\Factory\\ProductServiceFactory' ) ) {
			return null;
		}

		try {
			$product_service = \MelhorEnvio\Factory\ProductServiceFactory::fromId( $product->get_id() );

			return $product_service->getProduct( $product->get_id(), max( 1, (int) $quantity ) );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	private function get_saved_postcode() {
		$candidates = array();
		$customer   = function_exists( 'WC' ) ? WC()->customer : null;

		if ( $customer ) {
			if ( method_exists( $customer, 'get_shipping_postcode' ) ) {
				$candidates[] = $customer->get_shipping_postcode();
			}

			if ( method_exists( $customer, 'get_billing_postcode' ) ) {
				$candidates[] = $customer->get_billing_postcode();
			}
		}

		$user_id = get_current_user_id();

		if ( $user_id ) {
			$candidates[] = get_user_meta( $user_id, 'shipping_postcode', true );
			$candidates[] = get_user_meta( $user_id, 'billing_postcode', true );
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$candidates[] = WC()->session->get( 'useup_me_product_shipping_postcode' );
		}

		foreach ( $candidates as $candidate ) {
			$postcode = $this->sanitize_postcode( $candidate );

			if ( $this->is_valid_postcode( $postcode ) ) {
				return $postcode;
			}
		}

		return '';
	}

	private function persist_postcode( $postcode ) {
		if ( ! $this->is_valid_postcode( $postcode ) || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}

		$customer          = WC()->customer;
		$current_billing   = method_exists( $customer, 'get_billing_postcode' ) ? $this->sanitize_postcode( $customer->get_billing_postcode() ) : '';
		$current_shipping  = method_exists( $customer, 'get_shipping_postcode' ) ? $this->sanitize_postcode( $customer->get_shipping_postcode() ) : '';
		$should_fill_billing = empty( $current_billing ) && empty( $current_shipping );

		if ( method_exists( $customer, 'set_shipping_country' ) ) {
			$customer->set_shipping_country( 'BR' );
		}

		if ( method_exists( $customer, 'set_shipping_postcode' ) ) {
			$customer->set_shipping_postcode( $postcode );
		}

		if ( $should_fill_billing && method_exists( $customer, 'set_billing_country' ) ) {
			$customer->set_billing_country( 'BR' );
		}

		if ( $should_fill_billing && method_exists( $customer, 'set_billing_postcode' ) ) {
			$customer->set_billing_postcode( $postcode );
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'useup_me_product_shipping_postcode', $postcode );
		}

		$user_id = get_current_user_id();

		if ( $user_id ) {
			update_user_meta( $user_id, 'shipping_postcode', $postcode );

			if ( empty( get_user_meta( $user_id, 'billing_postcode', true ) ) ) {
				update_user_meta( $user_id, 'billing_postcode', $postcode );
			}
		}

		if ( method_exists( $customer, 'save' ) ) {
			$customer->save();
		}
	}

	private function sanitize_postcode( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	private function is_valid_postcode( $postcode ) {
		return 8 === strlen( $postcode );
	}

	private function format_postcode( $postcode ) {
		$postcode = $this->sanitize_postcode( $postcode );

		if ( ! $this->is_valid_postcode( $postcode ) ) {
			return '';
		}

		return substr( $postcode, 0, 5 ) . '-' . substr( $postcode, 5, 3 );
	}

	private function send_error( $message ) {
		wp_send_json_error(
			array(
				'message' => $message,
			),
			400
		);
	}

	private function get_shipping_method_key( $shipping_method ) {
		$method_id   = isset( $shipping_method->id ) ? (string) $shipping_method->id : '';
		$instance_id = isset( $shipping_method->instance_id ) ? (int) $shipping_method->instance_id : 0;

		return $method_id . ':' . $instance_id;
	}

	private function get_shipping_method_label( $shipping_method, $rate ) {
		if ( isset( $shipping_method->title ) && '' !== $shipping_method->title ) {
			return wp_strip_all_tags( $shipping_method->title );
		}

		if ( method_exists( $rate, 'get_label' ) ) {
			return wp_strip_all_tags( $rate->get_label() );
		}

		return 'Entrega';
	}

	private function get_shipping_rate_price( $rate ) {
		$meta_data = $this->get_rate_meta_data( $rate );

		if ( ! empty( $meta_data['price'] ) ) {
			return wp_strip_all_tags( (string) $meta_data['price'] );
		}

		$raw_cost = method_exists( $rate, 'get_cost' ) ? (float) $rate->get_cost() : 0.0;

		return wp_strip_all_tags( wc_price( $raw_cost ) );
	}

	private function get_rate_meta_data( $rate ) {
		if ( method_exists( $rate, 'get_meta_data' ) ) {
			$meta_data = $rate->get_meta_data();

			if ( is_array( $meta_data ) ) {
				return $meta_data;
			}
		}

		if ( property_exists( $rate, 'meta_data' ) && is_array( $rate->meta_data ) ) {
			return $rate->meta_data;
		}

		return array();
	}

	private function normalize_delivery_time_text( $text, $context = array() ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );

		if ( '' === $text ) {
			return '';
		}

		if ( '(' === substr( $text, 0, 1 ) && ')' === substr( $text, -1 ) ) {
			$text = trim( $text, '() ' );
		}

		if ( '.' !== substr( $text, -1 ) ) {
			$text .= '.';
		}

		return apply_filters( 'useup_me_product_shipping_date_label', $text, array(), $context );
	}

	private function get_free_shipping_notice( WC_Product $product ) {
		$template = USEUP_ME_Settings::get( 'product_shipping_free_shipping_message', 'Frete grátis acima de {amount}.' );
		$template = apply_filters( 'useup_me_free_shipping_message', $template, $product );

		if ( '' === trim( (string) $template ) ) {
			return '';
		}

		$threshold = USEUP_ME_Settings::get( 'product_shipping_free_shipping_threshold', 199.00 );
		$threshold = apply_filters( 'useup_me_free_shipping_threshold', (float) $threshold, $product );

		if ( false !== strpos( $template, '{amount}' ) && ( ! is_numeric( $threshold ) || (float) $threshold <= 0 ) ) {
			return '';
		}

		return str_replace( '{amount}', $this->format_price_text( (float) $threshold ), $template );
	}

	private function format_price_text( $amount ) {
		$formatted = wc_price( (float) $amount );
		$formatted = wp_strip_all_tags( $formatted );
		$formatted = html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );
		$formatted = str_replace( "\xc2\xa0", ' ', $formatted );

		return trim( $formatted );
	}

	private function get_icon_svg() {
		return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M3 7.5h11v7H3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 10h3l3 3v1.5h-6z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7.5" cy="17.5" r="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="17.5" cy="17.5" r="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
	}

	private function get_allowed_svg_tags() {
		return array(
			'svg'  => array(
				'viewBox'     => true,
				'focusable'   => true,
				'aria-hidden' => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'circle' => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
		);
	}
}
