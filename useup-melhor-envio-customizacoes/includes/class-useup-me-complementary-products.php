<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Complementary_Products {

	/**
	 * @var bool
	 */
	private $should_enqueue = false;

	/**
	 * @var bool
	 */
	private $is_adding_complementary = false;

	/**
	 * @var bool
	 */
	private $render_hook_registered = false;

	/**
	 * @var array<int,array>
	 */
	private $prepared_products_cache = array();

	/**
	 * @var array<int,array>
	 */
	private $validated_request_complements = array();

	public function init() {
		add_action( 'wp', array( $this, 'setup_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_complementary_products' ), 10, 6 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_selected_complementary_products' ), 20, 6 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	public function setup_hooks() {
		$product = $this->get_current_product();

		if ( ! $this->should_render_for_product( $product ) ) {
			return;
		}

		$position_hook = apply_filters( 'useup_me_complementary_products_position_hook', 'woocommerce_before_add_to_cart_quantity', $product );

		if ( ! $this->render_hook_registered && is_string( $position_hook ) && '' !== $position_hook ) {
			add_action( $position_hook, array( $this, 'render' ), 50 );
			$this->render_hook_registered = true;
		}

		$this->should_enqueue = true;
	}

	public function enqueue_assets() {
		if ( ! $this->should_enqueue || is_admin() ) {
			return;
		}

		$product = $this->get_current_product();

		if ( ! $this->should_render_for_product( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-complementary-products',
			USEUP_ME_URL . 'assets/css/complementary-products.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-complementary-products',
			USEUP_ME_URL . 'assets/js/complementary-products.js',
			array(),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-complementary-products',
			'useupMeComplementaryProducts',
			array(
				'chooseVariationText' => 'Escolha o tamanho da corrente para continuar.',
			)
		);
	}

	public function render() {
		$product = $this->get_current_product();

		if ( ! $this->should_render_for_product( $product ) ) {
			return;
		}

		$items = $this->get_prepared_products( $product );

		if ( empty( $items ) ) {
			return;
		}

		$title    = trim( (string) USEUP_ME_Settings::get( 'complementary_products_title', 'Complete com uma corrente' ) );
		$subtitle = trim( (string) USEUP_ME_Settings::get( 'complementary_products_subtitle', 'O pingente e vendido separadamente. Escolha uma ou mais correntes, se desejar.' ) );
		?>
		<div class="useup-complementary-products" data-useup-complementary-products="1">
			<div class="useup-complementary-products__header">
				<?php if ( '' !== $title ) : ?>
					<h3 class="useup-complementary-products__title"><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>

				<?php if ( '' !== $subtitle ) : ?>
					<p class="useup-complementary-products__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>

			<div class="useup-complementary-products__list">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$is_variable            = 'variable' === $item['type'];
					$selected_option        = ! empty( $item['selected_option'] ) ? $item['selected_option'] : null;
					$current_show_wholesale = $selected_option ? ! empty( $selected_option['show_wholesale'] ) : ! empty( $item['show_wholesale'] );
					$current_wholesale      = $selected_option ? $selected_option['wholesale_text'] : $item['wholesale_text'];
					$current_retail         = $selected_option ? $selected_option['retail_text'] : $item['retail_text'];
					$current_display        = $selected_option ? $selected_option['display_text'] : $item['display_text'];
					?>
					<div
						class="useup-complementary-product"
						data-product-id="<?php echo esc_attr( $item['product_id'] ); ?>"
						data-is-variable="<?php echo $is_variable ? '1' : '0'; ?>"
						data-default-show-wholesale="<?php echo ! empty( $item['show_wholesale'] ) ? '1' : '0'; ?>"
						data-default-wholesale-text="<?php echo esc_attr( $item['wholesale_text'] ); ?>"
						data-default-retail-text="<?php echo esc_attr( $item['retail_text'] ); ?>"
						data-default-display-text="<?php echo esc_attr( $item['display_text'] ); ?>"
					>
						<div class="useup-complementary-product__checkbox">
							<input
								type="checkbox"
								id="useup-complementary-product-<?php echo esc_attr( $item['product_id'] ); ?>"
								name="useup_complementary_products[<?php echo esc_attr( $item['product_id'] ); ?>][enabled]"
								value="1"
							/>
							<?php if ( $is_variable ) : ?>
								<input
									type="hidden"
									class="useup-complementary-product__variation-id"
									name="useup_complementary_products[<?php echo esc_attr( $item['product_id'] ); ?>][variation_id]"
									value="<?php echo $selected_option ? esc_attr( $selected_option['variation_id'] ) : ''; ?>"
								/>
							<?php endif; ?>
						</div>

						<div class="useup-complementary-product__thumb">
							<?php echo wp_kses_post( $item['image_html'] ); ?>
						</div>

						<div class="useup-complementary-product__body">
							<h4 class="useup-complementary-product__title"><?php echo esc_html( $item['name'] ); ?></h4>

							<?php if ( '' !== $item['description'] ) : ?>
								<p class="useup-complementary-product__description"><?php echo esc_html( $item['description'] ); ?></p>
							<?php endif; ?>

								<?php if ( $is_variable && ! empty( $item['variation_options'] ) ) : ?>
									<div class="useup-complementary-product__variations">
										<?php foreach ( $item['variation_options'] as $option ) : ?>
											<button
												type="button"
												class="useup-complementary-product__variation-option<?php echo ! empty( $option['selected'] ) ? ' is-selected' : ''; ?>"
												data-variation-id="<?php echo esc_attr( $option['variation_id'] ); ?>"
												data-show-wholesale="<?php echo ! empty( $option['show_wholesale'] ) ? '1' : '0'; ?>"
												data-wholesale-text="<?php echo esc_attr( $option['wholesale_text'] ); ?>"
												data-retail-text="<?php echo esc_attr( $option['retail_text'] ); ?>"
												data-display-text="<?php echo esc_attr( $option['display_text'] ); ?>"
												aria-pressed="<?php echo ! empty( $option['selected'] ) ? 'true' : 'false'; ?>"
											>
												<?php echo esc_html( $option['label'] ); ?>
											</button>
										<?php endforeach; ?>
								</div>
							<?php endif; ?>

								<p class="useup-complementary-product__message" hidden></p>
							</div>

							<div class="useup-complementary-product__price">
								<div class="useup-complementary-product__wholesale" <?php echo $current_show_wholesale ? '' : 'hidden'; ?>>
									+ <span class="useup-complementary-product__wholesale-amount"><?php echo esc_html( $current_wholesale ); ?></span>
									<span class="useup-price-mode">no atacado</span>
								</div>
								<div class="useup-complementary-product__retail<?php echo $current_show_wholesale ? '' : ' useup-complementary-product__retail--primary'; ?>">
									<span class="useup-complementary-product__retail-prefix"><?php echo $current_show_wholesale ? 'ou' : '+'; ?></span>
									<span class="useup-complementary-product__retail-amount"><?php echo esc_html( $current_show_wholesale ? $current_retail : $current_display ); ?></span>
									<span class="useup-complementary-product__retail-suffix"><?php echo $current_show_wholesale ? 'no varejo' : ''; ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php
	}

	public function validate_complementary_products( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		if ( ! $passed || $this->is_adding_complementary || ! USEUP_ME_Settings::is_complementary_products_enabled() ) {
			return $passed;
		}

		if ( empty( $_POST['useup_complementary_products'] ) ) {
			$this->validated_request_complements = array();

			return $passed;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return $passed;
		}

		$selected = $this->get_validated_selected_products( $product );

		if ( is_wp_error( $selected ) ) {
			wc_add_notice( $selected->get_error_message(), 'error' );
			$this->validated_request_complements = array();

			return false;
		}

		$this->validated_request_complements = $selected;

		return $passed;
	}

	public function add_selected_complementary_products( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( $this->is_adding_complementary || empty( $this->validated_request_complements ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$main_product = wc_get_product( $product_id );

		if ( ! $main_product instanceof WC_Product ) {
			$this->validated_request_complements = array();

			return;
		}

		$main_product_name = $main_product->get_name();
		$main_product_id   = $main_product->get_id();
		$selected_items    = $this->validated_request_complements;

		$this->validated_request_complements = array();
		$this->is_adding_complementary       = true;

		foreach ( $selected_items as $selected_item ) {
			$complementary_product_id = isset( $selected_item['product_id'] ) ? absint( $selected_item['product_id'] ) : 0;
			$complementary_variation  = isset( $selected_item['variation_id'] ) ? absint( $selected_item['variation_id'] ) : 0;
			$attributes               = isset( $selected_item['attributes'] ) && is_array( $selected_item['attributes'] ) ? $selected_item['attributes'] : array();

			if ( $complementary_product_id < 1 ) {
				continue;
			}

			WC()->cart->add_to_cart(
				$complementary_product_id,
				1,
				$complementary_variation,
				$attributes,
				array(
					'useup_complementary_for_product_id'   => $main_product_id,
					'useup_complementary_for_product_name' => $main_product_name,
				)
			);
		}

		$this->is_adding_complementary = false;
	}

	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['useup_complementary_for_product_id'] ) || empty( $values['useup_complementary_for_product_name'] ) ) {
			return;
		}

		$item->add_meta_data( 'Complemento do produto', (string) $values['useup_complementary_for_product_name'], true );
		$item->add_meta_data( '_useup_complementary_for_product_id', absint( $values['useup_complementary_for_product_id'] ), true );
	}

	private function should_render_for_product( $product ) {
		if ( ! USEUP_ME_Settings::is_complementary_products_enabled() || ! $this->is_product_context() || ! $product instanceof WC_Product ) {
			return false;
		}

		if ( empty( USEUP_ME_Settings::get( 'complementary_product_ids', array() ) ) ) {
			return false;
		}

		if ( ! $this->matches_display_target( $product ) ) {
			return false;
		}

		return ! empty( $this->get_prepared_products( $product ) );
	}

	private function is_product_context() {
		return function_exists( 'is_product' ) && is_product();
	}

	private function get_current_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		$product_id = get_the_ID();

		return $product_id ? wc_get_product( $product_id ) : null;
	}

	private function matches_display_target( WC_Product $product ) {
		$mode            = (string) USEUP_ME_Settings::get( 'complementary_display_mode', 'selected_categories' );
		$product_id      = $product->get_id();
		$category_ids    = array_map( 'absint', (array) USEUP_ME_Settings::get( 'complementary_category_ids', array() ) );
		$tag_ids         = array_map( 'absint', (array) USEUP_ME_Settings::get( 'complementary_tag_ids', array() ) );
		$target_products = array_map( 'absint', (array) USEUP_ME_Settings::get( 'complementary_target_product_ids', array() ) );

		if ( 'all_products' === $mode ) {
			return true;
		}

		if ( 'selected_products' === $mode ) {
			return in_array( $product_id, $target_products, true );
		}

		if ( 'selected_tags' === $mode ) {
			if ( empty( $tag_ids ) ) {
				return false;
			}

			$product_tag_ids = wc_get_product_term_ids( $product_id, 'product_tag' );

			return ! empty( array_intersect( $tag_ids, $product_tag_ids ) );
		}

		if ( empty( $category_ids ) ) {
			return false;
		}

		$product_category_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

		return ! empty( array_intersect( $category_ids, $product_category_ids ) );
	}

	private function get_prepared_products( WC_Product $main_product ) {
		$cache_key = $main_product->get_id();

		if ( isset( $this->prepared_products_cache[ $cache_key ] ) ) {
			return $this->prepared_products_cache[ $cache_key ];
		}

		$configured_ids = array_map( 'absint', (array) USEUP_ME_Settings::get( 'complementary_product_ids', array() ) );
		$prepared       = array();

		foreach ( $configured_ids as $configured_id ) {
			if ( $configured_id < 1 || $configured_id === $main_product->get_id() ) {
				continue;
			}

			$complementary_product = wc_get_product( $configured_id );

			if ( ! $complementary_product instanceof WC_Product ) {
				continue;
			}

			$item = $this->prepare_product_item( $complementary_product );

			if ( empty( $item ) ) {
				continue;
			}

			$prepared[] = $item;
		}

		$this->prepared_products_cache[ $cache_key ] = $prepared;

		return $prepared;
	}

	private function prepare_product_item( WC_Product $product ) {
		$description = wp_strip_all_tags( (string) $product->get_short_description() );
		$description = trim( preg_replace( '/\s+/u', ' ', $description ) );
		$description = '' !== $description ? wp_trim_words( $description, 16, '...' ) : '';
		$image_html  = $product->get_image( 'woocommerce_thumbnail' );
		$image_html  = $image_html ? $image_html : wc_placeholder_img( 'woocommerce_thumbnail' );

		if ( $product->is_type( 'variable' ) ) {
			$variation_options = $this->get_variation_options( $product );

			if ( empty( $variation_options ) ) {
				return array();
			}

			$selected_option = ( 1 === count( $variation_options ) ) ? $variation_options[0] : null;
			$price_data      = USEUP_ME_Pricing::get_product_price_data( $product );

			return array(
				'product_id'        => $product->get_id(),
				'type'              => 'variable',
				'name'              => $product->get_name(),
				'description'       => $description,
				'image_html'        => $image_html,
				'show_wholesale'    => ! empty( $price_data['show_wholesale'] ),
				'display_text'      => $this->format_price_text( $price_data['display_value'] ),
				'wholesale_text'    => $price_data['wholesale_value'] > 0 ? $this->format_price_text( $price_data['wholesale_value'] ) : '',
				'retail_text'       => $this->format_price_text( ! empty( $price_data['show_wholesale'] ) ? $price_data['retail_value'] : $price_data['display_value'] ),
				'variation_options' => $variation_options,
				'selected_option'   => $selected_option,
			);
		}

		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return array();
		}

		$price_data = USEUP_ME_Pricing::get_product_price_data( $product );

		if ( $price_data['display_value'] <= 0 ) {
			return array();
		}

		return array(
			'product_id'        => $product->get_id(),
			'type'              => 'simple',
			'name'              => $product->get_name(),
			'description'       => $description,
			'image_html'        => $image_html,
			'show_wholesale'    => ! empty( $price_data['show_wholesale'] ),
			'display_text'      => $this->format_price_text( $price_data['display_value'] ),
			'wholesale_text'    => $price_data['wholesale_value'] > 0 ? $this->format_price_text( $price_data['wholesale_value'] ) : '',
			'retail_text'       => $this->format_price_text( ! empty( $price_data['show_wholesale'] ) ? $price_data['retail_value'] : $price_data['display_value'] ),
			'variation_options' => array(),
			'selected_option'   => null,
		);
	}

	private function get_variation_options( WC_Product $product ) {
		$options = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			$price_data = USEUP_ME_Pricing::get_product_price_data( $variation );

			if ( $price_data['display_value'] <= 0 ) {
				continue;
			}

			$label = $this->get_variation_label( $variation );

			$options[] = array(
				'variation_id'   => $variation->get_id(),
				'label'          => '' !== $label ? $label : 'Opção',
				'show_wholesale' => ! empty( $price_data['show_wholesale'] ),
				'display_text'   => $this->format_price_text( $price_data['display_value'] ),
				'wholesale_text' => $price_data['wholesale_value'] > 0 ? $this->format_price_text( $price_data['wholesale_value'] ) : '',
				'retail_text'    => $this->format_price_text( ! empty( $price_data['show_wholesale'] ) ? $price_data['retail_value'] : $price_data['display_value'] ),
				'attributes'     => $variation->get_variation_attributes(),
				'selected'       => false,
			);
		}

		if ( 1 === count( $options ) ) {
			$options[0]['selected'] = true;
		}

		return $options;
	}

	private function get_variation_label( WC_Product_Variation $variation ) {
		$parts      = array();
		$attributes = $variation->get_variation_attributes();

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			$attribute_value = (string) $attribute_value;

			if ( '' === $attribute_value ) {
				continue;
			}

			$taxonomy = str_replace( 'attribute_', '', (string) $attribute_name );

			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $attribute_value, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$parts[] = $term->name;
					continue;
				}
			}

			$parts[] = $attribute_value;
		}

		return implode( ' / ', $parts );
	}

	private function get_validated_selected_products( WC_Product $main_product ) {
		$posted = isset( $_POST['useup_complementary_products'] ) ? wp_unslash( $_POST['useup_complementary_products'] ) : array();

		if ( ! is_array( $posted ) || empty( $posted ) ) {
			return array();
		}

		$prepared_products = $this->get_prepared_products( $main_product );
		$prepared_map      = array();
		$selected_items    = array();

		foreach ( $prepared_products as $prepared_product ) {
			$prepared_map[ $prepared_product['product_id'] ] = $prepared_product;
		}

		foreach ( $posted as $product_id => $selection ) {
			$product_id = absint( $product_id );

			if ( $product_id < 1 || empty( $selection['enabled'] ) || ! isset( $prepared_map[ $product_id ] ) ) {
				continue;
			}

			$prepared_product = $prepared_map[ $product_id ];

			if ( 'variable' === $prepared_product['type'] ) {
				$variation_id = isset( $selection['variation_id'] ) ? absint( $selection['variation_id'] ) : 0;

				if ( $variation_id < 1 ) {
					return new WP_Error( 'useup_me_complementary_variation_required', 'Escolha o tamanho da corrente para continuar.' );
				}

				$variation = wc_get_product( $variation_id );

				if ( ! $variation instanceof WC_Product_Variation || $variation->get_parent_id() !== $product_id || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
					return new WP_Error( 'useup_me_complementary_variation_invalid', 'A variação selecionada para o produto complementar não está disponível.' );
				}

				$selected_items[ $product_id ] = array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'attributes'   => $variation->get_variation_attributes(),
				);

				continue;
			}

			$complementary_product = wc_get_product( $product_id );

			if ( ! $complementary_product instanceof WC_Product || ! $complementary_product->is_purchasable() || ! $complementary_product->is_in_stock() ) {
				return new WP_Error( 'useup_me_complementary_unavailable', 'O produto complementar selecionado nao esta disponivel no momento.' );
			}

			$selected_items[ $product_id ] = array(
				'product_id'   => $product_id,
				'variation_id' => 0,
				'attributes'   => array(),
			);
		}

		return $selected_items;
	}

	private function format_price_text( $amount ) {
		$formatted = wc_price( (float) $amount );
		$formatted = wp_strip_all_tags( $formatted );
		$formatted = html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );
		$formatted = preg_replace( '/\s+/u', ' ', $formatted );

		return trim( (string) $formatted );
	}
}
