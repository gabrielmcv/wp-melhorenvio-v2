<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Premium_Category_Pages {
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_main_content', array( $this, 'render_header' ), 15 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_action( 'wp', array( $this, 'maybe_disable_default_archive_description' ) );
	}

	public function enqueue_assets() {
		if ( ! $this->should_render() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-premium-category-pages',
			USEUP_ME_URL . 'assets/css/premium-category-pages.css',
			array(),
			USEUP_ME_VERSION
		);
	}

	public function render_header() {
		$context = $this->get_header_context();

		if ( empty( $context ) || 'taxonomy' !== $context['type'] ) {
			return;
		}

		$this->render_header_markup( $context['title'], $context['description'] );
	}

	private function render_header_markup( $title, $description ) {
		if ( '' === trim( (string) $title ) ) {
			return;
		}

		?>
		<header class="useup-premium-category-header">
			<h1 class="useup-premium-category-header__title"><?php echo esc_html( $title ); ?></h1>

			<?php if ( $this->should_render_ornament() ) : ?>
				<div class="useup-premium-category-header__ornament" aria-hidden="true">
					<span></span>
					<i></i>
					<span></span>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $description ) : ?>
				<div class="useup-premium-category-header__subtitle">
					<?php echo wp_kses_post( $description ); ?>
				</div>
			<?php endif; ?>
		</header>
		<?php
	}

	public function add_body_class( $classes ) {
		if ( $this->should_render() ) {
			$classes[] = 'useup-premium-category-enabled';
		}

		return $classes;
	}

	public function maybe_disable_default_archive_description() {
		if ( ! $this->should_render() ) {
			return;
		}

		if (
			( function_exists( 'is_product_category' ) && is_product_category() ) ||
			( function_exists( 'is_product_tag' ) && is_product_tag() ) ||
			( function_exists( 'is_tax' ) && is_tax( 'colecao' ) )
		) {
			remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
		}
	}

	private function should_render() {
		if ( ! USEUP_ME_Settings::is_premium_category_pages_enabled() ) {
			return false;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return true;
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return true;
		}

		return function_exists( 'is_tax' ) && is_tax( 'colecao' );
	}

	private function should_render_description() {
		return (bool) USEUP_ME_Settings::get( 'category_use_description_subtitle', true );
	}

	private function should_render_ornament() {
		return (bool) USEUP_ME_Settings::get( 'category_show_title_ornament', true );
	}

	private function get_current_term() {
		$term = get_queried_object();

		if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, array( 'product_cat', 'product_tag', 'colecao' ), true ) ) {
			return null;
		}

		return $term;
	}

	private function get_header_context() {
		$term = $this->get_current_term();

		if ( $term ) {
			return array(
				'type'        => 'taxonomy',
				'title'       => $term->name,
				'description' => $this->get_taxonomy_description( $term ),
				);
		}

		return null;
	}

	private function get_taxonomy_description( WP_Term $term ) {
		$description = '';

		if ( ! $this->should_render_description() ) {
			return $description;
		}

		$raw_description = term_description( $term->term_id, $term->taxonomy );

		if ( '' !== trim( wp_strip_all_tags( $raw_description ) ) ) {
			$description = wp_kses_post( $raw_description );
		}

		return $description;
	}
}
