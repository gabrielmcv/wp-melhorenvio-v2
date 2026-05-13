<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Product_Page_Polish {

	/**
	 * @var array
	 */
	private $description_parts = array();

	public function init() {
		add_action( 'wp', array( $this, 'setup_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() || ! $this->is_product_context() ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_tagline' ), 6 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_price_block' ), 9 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_trust_line' ), 6 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_short_description' ), 35 );
	}

	public function enqueue_assets() {
		if ( ! $this->is_enabled() || ! $this->is_product_context() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-product-page-polish',
			USEUP_ME_URL . 'assets/css/product-page-polish.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-product-page-polish',
			USEUP_ME_URL . 'assets/js/product-page-polish.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-product-page-polish',
			'useupMeProductPagePolish',
			array(
				'expandLabel'   => 'Ler descrição',
				'collapseLabel' => 'Ocultar descrição',
				'priceFormat'   => get_woocommerce_price_format(),
				'currencySymbol'=> get_woocommerce_currency_symbol(),
				'decimalSep'    => wc_get_price_decimal_separator(),
				'thousandSep'   => wc_get_price_thousand_separator(),
				'decimals'      => wc_get_price_decimals(),
			)
		);
	}

	public function add_body_class( $classes ) {
		if ( $this->is_enabled() && $this->is_product_context() ) {
			$classes[] = 'useup-product-polish';
		}

		return $classes;
	}

	public function render_tagline() {
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$parts = $this->get_description_parts( $product );

		if ( empty( $parts['tagline'] ) ) {
			return;
		}

		printf(
			'<p class="useup-product-tagline">%s</p>',
			esc_html( $parts['tagline'] )
		);
	}

	public function render_price_block() {
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$price_data = $this->get_price_data( $product );
		$badges     = $this->get_badges();
		$popover_id = 'useup-wholesale-popover-' . $product->get_id();
		?>
		<div
			class="useup-price-block"
			data-base-current="<?php echo esc_attr( $price_data['current_value'] ); ?>"
			data-base-regular="<?php echo esc_attr( $price_data['regular_value'] ); ?>"
		>
			<div class="useup-price-block__main">
				<span class="useup-price-block__amount"><?php echo esc_html( $price_data['current_text'] ); ?></span>

				<span class="useup-wholesale-info">
					<button
						type="button"
						class="useup-wholesale-info-trigger"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $popover_id ); ?>"
					>
						no atacado
					</button>
					<span class="useup-wholesale-info__icon" aria-hidden="true">i</span>
					<span class="useup-wholesale-popover" id="<?php echo esc_attr( $popover_id ); ?>" role="tooltip" hidden>
						<span>Preço de atacado válido acima de 5 peças no pedido.</span>
						<span>As peças podem ser variadas.</span>
					</span>
				</span>
			</div>

			<div class="useup-price-block__row">
				<div class="useup-price-block__secondary" <?php echo empty( $price_data['regular_text'] ) ? 'hidden' : ''; ?>>
					<span class="useup-price-block__secondary-prefix">ou</span>
					<span class="useup-price-block__secondary-amount"><?php echo esc_html( $price_data['regular_text'] ); ?></span>
					<span class="useup-price-block__secondary-suffix">no varejo</span>
				</div>

				<?php if ( ! empty( $badges ) ) : ?>
					<div class="useup-price-block__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<span class="useup-price-badge"><?php echo esc_html( $badge['text'] ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_trust_line() {
		$items = apply_filters(
			'useup_me_product_trust_items',
			array(
				array(
					'label' => 'Compra segura',
					'icon'  => $this->get_trust_icon_svg( 'shield' ),
				),
				array(
					'label' => 'Troca fácil',
					'icon'  => $this->get_trust_icon_svg( 'refresh' ),
				),
				array(
					'label' => 'Pagamento protegido',
					'icon'  => $this->get_trust_icon_svg( 'lock' ),
				),
			)
		);

		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}
		?>
		<div class="useup-product-trust" aria-label="Benefícios da compra">
			<?php foreach ( $items as $item ) : ?>
				<span class="useup-product-trust__item">
					<span class="useup-product-trust__icon" aria-hidden="true">
						<?php echo wp_kses( $item['icon'], $this->get_allowed_svg_tags() ); ?>
					</span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function render_short_description() {
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$parts = $this->get_description_parts( $product );
		$body  = isset( $parts['body'] ) ? trim( $parts['body'] ) : '';

		if ( '' === $body ) {
			return;
		}
		?>
		<div class="useup-short-description useup-short-description--collapsed">
			<div class="useup-short-description__content">
				<?php echo wp_kses_post( apply_filters( 'woocommerce_short_description', $body ) ); ?>
			</div>

			<button type="button" class="useup-short-description__toggle" aria-expanded="false">
				Ler descrição
			</button>
		</div>
		<?php
	}

	private function is_enabled() {
		return USEUP_ME_Settings::is_product_page_polish_enabled();
	}

	private function is_product_context() {
		return function_exists( 'is_product' ) && is_product();
	}

	private function get_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		$product_id = get_the_ID();

		return $product_id ? wc_get_product( $product_id ) : null;
	}

	private function get_description_parts( WC_Product $product ) {
		$product_id = $product->get_id();

		if ( isset( $this->description_parts[ $product_id ] ) ) {
			return $this->description_parts[ $product_id ];
		}

		$raw     = trim( (string) $product->get_short_description() );
		$tagline = '';
		$body    = $raw;

		if ( preg_match_all( '/<p\b[^>]*>.*?<\/p>/is', $raw, $paragraphs ) && ! empty( $paragraphs[0] ) ) {
			$first_html = $paragraphs[0][0];
			$first_text = trim( wp_strip_all_tags( $first_html ) );

			if ( $this->is_tagline_candidate( $first_text ) ) {
				$tagline = $first_text;
				$body    = trim( preg_replace( '/^' . preg_quote( $first_html, '/' ) . '/is', '', $raw, 1 ) );
			}
		} else {
			$segments = preg_split( '/(?:\r?\n){2,}/', $raw );

			if ( ! empty( $segments[0] ) ) {
				$first_text = trim( wp_strip_all_tags( $segments[0] ) );

				if ( $this->is_tagline_candidate( $first_text ) ) {
					$tagline = $first_text;
					array_shift( $segments );
					$body = trim( implode( "\n\n", $segments ) );
				}
			}
		}

		$this->description_parts[ $product_id ] = array(
			'tagline' => $tagline,
			'body'    => $body,
		);

		return $this->description_parts[ $product_id ];
	}

	private function is_tagline_candidate( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return false;
		}

		if ( mb_strlen( $text ) > 80 ) {
			return false;
		}

		if ( false === strpos( $text, '·' ) && false === strpos( $text, '•' ) && false === strpos( $text, '|' ) ) {
			return false;
		}

		return (bool) apply_filters( 'useup_me_product_tagline_candidate', true, $text );
	}

	private function get_price_data( WC_Product $product ) {
		$current_value = 0.0;
		$regular_value = 0.0;

		if ( $product->is_type( 'variable' ) ) {
			$current_value = (float) $product->get_variation_price( 'min', true );
			$regular_value = (float) $product->get_variation_regular_price( 'min', true );
		} else {
			$current_value = (float) wc_get_price_to_display( $product );
			$regular_raw   = $product->get_regular_price();
			$regular_value = '' !== $regular_raw ? (float) wc_get_price_to_display(
				$product,
				array(
					'price' => (float) $regular_raw,
				)
			) : 0.0;
		}

		if ( $regular_value <= $current_value ) {
			$regular_value = 0.0;
		}

		return array(
			'current_value' => $current_value,
			'regular_value' => $regular_value,
			'current_text'  => $this->format_price_text( $current_value ),
			'regular_text'  => $regular_value > 0 ? $this->format_price_text( $regular_value ) : '',
		);
	}

	private function get_badges() {
		$badges = array();

		if ( USEUP_ME_Settings::get( 'enable_product_installment_badge', true ) ) {
			$text = trim( (string) USEUP_ME_Settings::get( 'product_installment_badge_text', 'Até 12x' ) );

			if ( '' !== $text ) {
				$badges[] = array(
					'key'  => 'installment',
					'text' => $text,
				);
			}
		}

		if ( USEUP_ME_Settings::get( 'enable_product_pix_badge', true ) ) {
			$text = trim( (string) USEUP_ME_Settings::get( 'product_pix_badge_text', '5% no PIX' ) );

			if ( '' !== $text ) {
				$badges[] = array(
					'key'  => 'pix',
					'text' => $text,
				);
			}
		}

		return apply_filters( 'useup_me_product_page_badges', $badges, $this->get_product() );
	}

	private function format_price_text( $amount ) {
		$formatted = wc_price( (float) $amount );
		$formatted = wp_strip_all_tags( $formatted );
		$formatted = html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' );
		$formatted = preg_replace( '/\s+/u', ' ', $formatted );

		return trim( (string) $formatted );
	}

	private function get_trust_icon_svg( $type ) {
		if ( 'refresh' === $type ) {
			return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M20 5v5h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 19v-5h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.5 9a7 7 0 0 1 11.5-2.5L20 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.5 15A7 7 0 0 1 6 17.5L4 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		if ( 'lock' === $type ) {
			return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 1 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
		}

		return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 3 5.5 6v5.7c0 4.2 2.8 7.9 6.5 9.3 3.7-1.4 6.5-5.1 6.5-9.3V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m9.5 12 1.7 1.7 3.3-3.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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
			'rect' => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
		);
	}
}
