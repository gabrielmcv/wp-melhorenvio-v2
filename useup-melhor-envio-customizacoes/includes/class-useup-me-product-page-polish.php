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
		add_filter( 'woocommerce_post_class', array( $this, 'filter_product_post_class' ), 10, 2 );
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_loop_price' ), 10 );

		if ( $this->is_product_context() ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );

			add_action( 'woocommerce_single_product_summary', array( $this, 'render_price_block' ), 10 );
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_short_description' ), 20 );
		}
	}

	public function enqueue_assets() {
		if ( ! $this->is_enabled() || is_admin() ) {
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
				'expandLabel'         => 'Ler descrição',
				'collapseLabel'       => 'Ocultar descrição',
				'priceFormat'         => get_woocommerce_price_format(),
				'currencySymbol'      => get_woocommerce_currency_symbol(),
				'decimalSep'          => wc_get_price_decimal_separator(),
				'thousandSep'         => wc_get_price_thousand_separator(),
				'decimals'            => wc_get_price_decimals(),
				'retailMarkupPercent' => USEUP_ME_Pricing::get_retail_markup_percent(),
				'retailMarkupFixed'   => USEUP_ME_Pricing::get_retail_markup_fixed(),
			)
		);
	}

	public function add_body_class( $classes ) {
		if ( $this->is_enabled() && $this->is_product_context() ) {
			$classes[] = 'useup-product-polish';
		}

		return $classes;
	}

	public function filter_product_post_class( $classes, $product ) {
		if ( ! $this->is_enabled() || ! $product instanceof WC_Product ) {
			return $classes;
		}

		if ( ! function_exists( 'wc_get_loop_prop' ) || (int) wc_get_loop_prop( 'total', 0 ) < 1 ) {
			return $classes;
		}

		$classes[] = 'useup-loop-card';

		if ( $product->is_type( 'variable' ) ) {
			$classes[] = 'useup-loop-card--variable';
		}

		return array_values( array_unique( $classes ) );
	}

	public function render_loop_price() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$price_data = $this->get_price_data( $product );

		if ( $price_data['wholesale_value'] <= 0 ) {
			return;
		}

		$is_variable = $product->is_type( 'variable' );
		?>
		<div class="useup-loop-price">
			<div class="useup-loop-price__main">
				<?php if ( $is_variable ) : ?>
					<span class="useup-loop-price__prefix">A partir de</span>
				<?php endif; ?>
				<span class="useup-loop-price__amount"><?php echo esc_html( $price_data['wholesale_text'] ); ?></span>
				<span class="useup-loop-price__mode">no atacado</span>
			</div>
			<?php if ( ! empty( $price_data['retail_text'] ) ) : ?>
				<div class="useup-loop-price__retail">
					ou <?php echo esc_html( $price_data['retail_text'] ); ?> no varejo
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_price_block() {
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$price_data    = $this->get_price_data( $product );
		$badges        = $this->get_badges();
		$popover_id    = 'useup-wholesale-popover-' . $product->get_id();
		$tooltip_lines = USEUP_ME_Pricing::get_wholesale_tooltip_lines();
		?>
		<div
			class="useup-price-block"
			data-base-wholesale="<?php echo esc_attr( $price_data['wholesale_value'] ); ?>"
			data-base-retail="<?php echo esc_attr( $price_data['retail_value'] ); ?>"
		>
			<div class="useup-price-block__main">
				<span class="useup-price-block__amount"><?php echo esc_html( $price_data['wholesale_text'] ); ?></span>

				<span class="useup-wholesale-info">
					<button
						type="button"
						class="useup-price-block__info-trigger useup-wholesale-info-trigger"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $popover_id ); ?>"
					>
						<span class="useup-price-block__mode">no atacado</span>
						<span class="useup-wholesale-info-icon" aria-hidden="true">
							<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
								<circle cx="10" cy="10" r="7.25" fill="none" stroke="currentColor" stroke-width="1.25"></circle>
								<path d="M10 8.1v4.2" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path>
								<circle cx="10" cy="5.6" r="0.85" fill="currentColor"></circle>
							</svg>
						</span>
					</button>
					<span class="useup-wholesale-popover" id="<?php echo esc_attr( $popover_id ); ?>" role="tooltip" hidden>
						<?php foreach ( $tooltip_lines as $line ) : ?>
							<span><?php echo esc_html( $line ); ?></span>
						<?php endforeach; ?>
					</span>
				</span>
			</div>

			<?php if ( ! empty( $price_data['retail_text'] ) ) : ?>
				<p class="useup-price-block__retail useup-price-block__secondary">
					<span class="useup-price-block__secondary-prefix">ou</span>
					<span class="useup-price-block__secondary-amount"><?php echo esc_html( $price_data['retail_text'] ); ?></span>
					<span class="useup-price-block__secondary-suffix">no varejo</span>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $badges ) ) : ?>
				<div class="useup-price-block__badges">
					<?php foreach ( $badges as $badge ) : ?>
						<span class="useup-price-badge"><?php echo esc_html( $badge['text'] ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_short_description() {
		$product = $this->get_product();

		if ( ! $product ) {
			return;
		}

		$parts   = $this->get_description_parts( $product );
		$tagline = isset( $parts['tagline'] ) ? trim( $parts['tagline'] ) : '';
		$body    = isset( $parts['body'] ) ? trim( $parts['body'] ) : '';

		if ( '' === $tagline && '' === $body ) {
			return;
		}
		?>
		<div class="useup-short-description useup-short-description--collapsed">
			<?php if ( '' !== $tagline ) : ?>
				<p class="useup-short-description__tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $body ) : ?>
				<div class="useup-short-description__content">
					<?php echo wp_kses_post( apply_filters( 'woocommerce_short_description', $body ) ); ?>
				</div>

				<button type="button" class="useup-short-description__toggle" aria-expanded="false">
					Ler descrição
				</button>
			<?php endif; ?>
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

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 80 ) {
			return false;
		}

		if ( false === strpos( $text, '·' ) && false === strpos( $text, '•' ) && false === strpos( $text, '|' ) ) {
			return false;
		}

		return (bool) apply_filters( 'useup_me_product_tagline_candidate', true, $text );
	}

	private function get_price_data( WC_Product $product ) {
		$price_data = USEUP_ME_Pricing::get_product_price_data( $product );

		return array(
			'wholesale_value' => $price_data['wholesale_value'],
			'retail_value'    => $price_data['retail_value'],
			'wholesale_text'  => $this->format_price_text( $price_data['wholesale_value'] ),
			'retail_text'     => $price_data['retail_value'] > 0 ? $this->format_price_text( $price_data['retail_value'] ) : '',
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
}
