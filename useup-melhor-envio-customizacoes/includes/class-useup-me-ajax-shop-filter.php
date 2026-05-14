<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Ajax_Shop_Filter {

	/**
	 * @var bool
	 */
	private $is_rendering_ajax_results = false;

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_useup_me_filter_shop_products', array( $this, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_nopriv_useup_me_filter_shop_products', array( $this, 'ajax_filter_products' ) );
		add_action( 'woocommerce_before_main_content', array( $this, 'render_filter_bar' ), 15 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'open_results_wrapper' ), 40 );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'close_results_wrapper' ), 999 );
		add_action( 'woocommerce_no_products_found', array( $this, 'open_results_wrapper' ), 0 );
		add_action( 'woocommerce_no_products_found', array( $this, 'close_results_wrapper' ), 999 );
		add_action( 'pre_get_posts', array( $this, 'apply_requested_filter_to_main_query' ) );
		add_filter( 'loop_shop_per_page', array( $this, 'filter_products_per_page' ), 999 );
	}

	public function enqueue_assets() {
		if ( ! $this->should_render() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-ajax-shop-filter',
			USEUP_ME_URL . 'assets/css/ajax-shop-filter.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-ajax-shop-filter',
			USEUP_ME_URL . 'assets/js/ajax-shop-filter.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-ajax-shop-filter',
			'useupMeAjaxShopFilter',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'useup_me_ajax_shop_filter' ),
				'paginationMode' => $this->get_pagination_mode(),
				'archiveUrl'     => $this->get_shop_archive_url(),
				'defaultOrderby' => $this->get_default_orderby(),
				'i18n'           => array(
					'loadMore' => 'Carregar mais',
					'error'    => 'Nao foi possivel atualizar os produtos agora. Tente novamente.',
				),
			)
		);
	}

	public function render_filter_bar() {
		$items      = $this->get_frontend_items();
		$active_key = $this->get_active_item_key( $items );

		if ( ! $this->should_render() || empty( $items ) ) {
			return;
		}

		?>
		<nav
			class="useup-shop-filter"
			data-useup-shop-filter="1"
			data-active-key="<?php echo esc_attr( $active_key ); ?>"
			data-pagination-mode="<?php echo esc_attr( $this->get_pagination_mode() ); ?>"
		>
			<div class="useup-shop-filter__inner">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$is_active = (string) $item['key'] === (string) $active_key;
					$href      = $this->build_item_href( $item );
					?>
					<a
						class="useup-shop-filter__item<?php echo $is_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $href ); ?>"
						data-filter-key="<?php echo esc_attr( $item['key'] ); ?>"
						data-filter-type="<?php echo esc_attr( $item['type'] ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<span class="useup-shop-filter__icon" aria-hidden="true">
							<?php echo wp_kses( $this->get_icon_markup( $item['icon'] ), $this->get_allowed_svg_tags() ); ?>
						</span>
						<span class="useup-shop-filter__label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}

	public function open_results_wrapper() {
		if ( ! $this->should_wrap_results() ) {
			return;
		}

		echo '<div class="useup-shop-filter-results" data-useup-shop-results="1">';
	}

	public function close_results_wrapper() {
		if ( ! $this->should_wrap_results() ) {
			return;
		}

		echo '</div>';
	}

	public function filter_products_per_page( $per_page ) {
		if ( ! $this->is_feature_enabled() || ! $this->is_shop_archive_context() ) {
			return $per_page;
		}

		return $this->get_products_per_page();
	}

	public function apply_requested_filter_to_main_query( $query ) {
		$requested_item = null;

		if (
			! $this->is_feature_enabled() ||
			is_admin() ||
			! $query instanceof WP_Query ||
			! $query->is_main_query() ||
			! $this->is_shop_archive_context()
		) {
			return;
		}

		$requested_key = isset( $_GET['useup_filter_item'] ) ? sanitize_text_field( wp_unslash( $_GET['useup_filter_item'] ) ) : '';

		if ( '' === $requested_key ) {
			return;
		}

		$requested_item = $this->get_item_by_key( $requested_key );

		if ( empty( $requested_item ) ) {
			return;
		}

		$this->apply_item_query_to_query(
			$query,
			$requested_item,
			array(
				'orderby' => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '',
				'paged'   => max( 1, absint( get_query_var( 'paged' ) ) ),
			)
		);
	}

	public function ajax_filter_products() {
		check_ajax_referer( 'useup_me_ajax_shop_filter', 'nonce' );

		if ( ! $this->is_feature_enabled() ) {
			wp_send_json_error();
		}

		$filter_key = isset( $_POST['filter_key'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_key'] ) ) : '';
		$orderby    = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : '';
		$paged      = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$item       = $this->get_item_by_key( $filter_key );

		if ( empty( $item ) ) {
			$item = $this->get_default_item();
		}

		$query = $this->build_products_query(
			$item,
			array(
				'orderby' => $orderby,
				'paged'   => $paged,
			)
		);

		wp_send_json_success(
			array(
				'html'             => $this->render_products_markup( $query ),
				'pagination'       => $this->render_pagination_markup(
					$query,
					$item,
					array(
						'orderby' => $orderby,
						'paged'   => $paged,
					)
				),
				'resultCountHtml'  => $this->render_result_count_markup( $query, $paged ),
				'found_posts'      => (int) $query->found_posts,
				'max_num_pages'    => (int) $query->max_num_pages,
				'current_page'     => (int) $paged,
				'pagination_mode'  => $this->get_pagination_mode(),
				'item_url'         => $this->build_item_href( $item ),
			)
		);
	}

	private function should_render() {
		return $this->is_feature_enabled() && $this->is_shop_archive_context() && ! empty( $this->get_frontend_items() );
	}

	private function should_wrap_results() {
		return $this->should_render() && ! $this->is_rendering_ajax_results;
	}

	private function is_feature_enabled() {
		return USEUP_ME_Settings::is_ajax_shop_filter_enabled();
	}

	private function is_shop_archive_context() {
		return function_exists( 'is_shop' ) && ( is_shop() );
	}

	private function get_products_per_page() {
		return max( 1, absint( USEUP_ME_Settings::get( 'ajax_shop_products_per_page', 12 ) ) );
	}

	private function get_pagination_mode() {
		$mode = (string) USEUP_ME_Settings::get( 'ajax_shop_pagination_mode', 'pagination' );

		return in_array( $mode, array( 'pagination', 'load_more' ), true ) ? $mode : 'pagination';
	}

	private function get_default_orderby() {
		return (string) apply_filters(
			'woocommerce_default_catalog_orderby',
			get_option( 'woocommerce_default_catalog_orderby', 'menu_order' )
		);
	}

	private function get_frontend_items() {
		$items = array();

		foreach ( $this->get_normalized_items() as $item ) {
			if ( empty( $item['enabled'] ) ) {
				continue;
			}

			$prepared_item = $this->prepare_item_for_frontend( $item );

			if ( empty( $prepared_item ) ) {
				continue;
			}

			$items[] = $prepared_item;
		}

		return $items;
	}

	private function get_normalized_items() {
		$items = USEUP_ME_Settings::get( 'ajax_shop_filter_items', array() );

		if ( ! is_array( $items ) ) {
			return array();
		}

		$normalized = array();
		$position   = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label         = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
			$type          = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'all';
			$category_slug = isset( $item['category_slug'] ) ? sanitize_title( (string) $item['category_slug'] ) : '';
			$tag_slug      = isset( $item['tag_slug'] ) ? sanitize_title( (string) $item['tag_slug'] ) : '';
			$custom_key    = isset( $item['custom_key'] ) ? sanitize_key( (string) $item['custom_key'] ) : '';
			$icon          = isset( $item['icon'] ) ? sanitize_key( (string) $item['icon'] ) : '';
			$order         = isset( $item['order'] ) ? absint( $item['order'] ) : ( $position + 1 ) * 10;
			$enabled       = ! empty( $item['enabled'] );

			if ( ! in_array( $type, array( 'all', 'category', 'tag', 'best_sellers', 'custom' ), true ) ) {
				$type = 'all';
			}

			$normalized[] = array(
				'key'           => (string) $position,
				'label'         => $label,
				'type'          => $type,
				'category_slug' => $category_slug,
				'tag_slug'      => $tag_slug,
				'custom_key'    => $custom_key,
				'icon'          => '' !== $icon ? $icon : $this->get_default_icon_for_type( $type ),
				'order'         => $order,
				'enabled'       => $enabled,
			);

			$position++;
		}

		usort(
			$normalized,
			static function ( $left, $right ) {
				$left_order  = isset( $left['order'] ) ? (int) $left['order'] : 0;
				$right_order = isset( $right['order'] ) ? (int) $right['order'] : 0;

				if ( $left_order === $right_order ) {
					return strcmp( (string) $left['key'], (string) $right['key'] );
				}

				return $left_order <=> $right_order;
			}
		);

		return array_values( $normalized );
	}

	private function prepare_item_for_frontend( $item ) {
		$type = $item['type'];

		if ( 'all' === $type || 'best_sellers' === $type || 'custom' === $type ) {
			if ( '' === $item['label'] ) {
				$item['label'] = $this->get_default_label_for_type( $type );
			}

			return $item;
		}

		if ( 'category' === $type ) {
			$term = get_term_by( 'slug', $item['category_slug'], 'product_cat' );
		} else {
			$term = get_term_by( 'slug', $item['tag_slug'], 'product_tag' );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		if ( '' === $item['label'] ) {
			$item['label'] = $term->name;
		}

		$item['term'] = $term;

		return $item;
	}

	private function get_item_by_key( $key ) {
		foreach ( $this->get_frontend_items() as $item ) {
			if ( (string) $item['key'] === (string) $key ) {
				return $item;
			}
		}

		return array();
	}

	private function get_default_item() {
		$items = $this->get_frontend_items();

		foreach ( $items as $item ) {
			if ( 'all' === $item['type'] ) {
				return $item;
			}
		}

		return ! empty( $items ) ? $items[0] : array(
			'key'   => 'all',
			'type'  => 'all',
			'label' => 'Todos',
			'icon'  => 'grid',
		);
	}

	private function get_active_item_key( $items ) {
		$requested_key = isset( $_GET['useup_filter_item'] ) ? sanitize_text_field( wp_unslash( $_GET['useup_filter_item'] ) ) : '';

		if ( '' !== $requested_key ) {
			foreach ( $items as $item ) {
				if ( (string) $item['key'] === (string) $requested_key ) {
					return (string) $item['key'];
				}
			}
		}

		if ( is_tax( 'product_cat' ) || is_tax( 'product_tag' ) ) {
			$current_term = get_queried_object();

			if ( $current_term instanceof WP_Term ) {
				foreach ( $items as $item ) {
					if (
						( 'category' === $item['type'] && 'product_cat' === $current_term->taxonomy && ! empty( $item['term'] ) && $item['term']->term_id === $current_term->term_id ) ||
						( 'tag' === $item['type'] && 'product_tag' === $current_term->taxonomy && ! empty( $item['term'] ) && $item['term']->term_id === $current_term->term_id )
					) {
						return (string) $item['key'];
					}
				}
			}
		}

		foreach ( $items as $item ) {
			if ( 'all' === $item['type'] ) {
				return (string) $item['key'];
			}
		}

		return ! empty( $items[0]['key'] ) ? (string) $items[0]['key'] : '';
	}

	private function get_shop_archive_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'shop' );
		}

		return get_post_type_archive_link( 'product' );
	}

	private function build_item_href( $item ) {
		switch ( $item['type'] ) {
			case 'category':
				if ( ! empty( $item['term'] ) && $item['term'] instanceof WP_Term ) {
					return get_term_link( $item['term'] );
				}
				break;

			case 'tag':
				if ( ! empty( $item['term'] ) && $item['term'] instanceof WP_Term ) {
					return get_term_link( $item['term'] );
				}
				break;

			case 'best_sellers':
			case 'custom':
				return add_query_arg( 'useup_filter_item', $item['key'], $this->get_shop_archive_url() );
		}

		return $this->get_shop_archive_url();
	}

	private function build_products_query( $item, $state ) {
		$query_args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $this->get_products_per_page(),
			'paged'               => max( 1, isset( $state['paged'] ) ? absint( $state['paged'] ) : 1 ),
			'ignore_sticky_posts' => true,
		);

		$query_args = $this->apply_item_query_to_query( $query_args, $item, $state );

		return new WP_Query( $query_args );
	}

	private function apply_item_query_to_query( $query, $item, $state ) {
		$orderby = isset( $state['orderby'] ) ? sanitize_text_field( (string) $state['orderby'] ) : '';
		$args    = $query instanceof WP_Query ? $query->query_vars : $query;

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( 'category' === $item['type'] && ! empty( $item['category_slug'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $item['category_slug'] ),
				),
			);
		}

		if ( 'tag' === $item['type'] && ! empty( $item['tag_slug'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_tag',
					'field'    => 'slug',
					'terms'    => array( $item['tag_slug'] ),
				),
			);
		}

		if (
			'best_sellers' === $item['type'] &&
			( '' === $orderby || $this->is_default_orderby( $orderby ) )
		) {
			$args['meta_key'] = 'total_sales';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';

			return $this->commit_query_args( $query, $args );
		}

		if ( 'custom' === $item['type'] ) {
			$custom_query_args = apply_filters(
				'useup_me_ajax_shop_custom_filter_query_args',
				array(),
				$item,
				$state
			);

			if ( is_array( $custom_query_args ) ) {
				foreach ( $custom_query_args as $query_key => $query_value ) {
					$args[ $query_key ] = $query_value;
				}
			}
		}

		$args = $this->apply_catalog_ordering_to_query( $args, $orderby );

		return $this->commit_query_args( $query, $args );
	}

	private function apply_catalog_ordering_to_query( $query, $orderby ) {
		$ordering_args = array();
		$orderby       = '' !== $orderby ? $orderby : $this->get_default_orderby();
		$args          = $query instanceof WP_Query ? $query->query_vars : $query;

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( function_exists( 'WC' ) && WC()->query ) {
			$ordering_args = WC()->query->get_catalog_ordering_args( $orderby );
		}

		if ( ! empty( $ordering_args['orderby'] ) ) {
			$args['orderby'] = $ordering_args['orderby'];
		}

		if ( ! empty( $ordering_args['order'] ) ) {
			$args['order'] = $ordering_args['order'];
		}

		if ( ! empty( $ordering_args['meta_key'] ) ) {
			$args['meta_key'] = $ordering_args['meta_key'];
		}

		return $this->commit_query_args( $query, $args );
	}

	private function commit_query_args( $query, $args ) {
		if ( $query instanceof WP_Query ) {
			foreach ( $args as $key => $value ) {
				$query->set( $key, $value );
			}

			return $query;
		}

		return is_array( $args ) ? $args : array();
	}

	private function render_products_markup( WP_Query $query ) {
		ob_start();

		wc_set_loop_prop( 'total', (int) $query->found_posts );
		wc_set_loop_prop( 'total_pages', (int) $query->max_num_pages );
		wc_set_loop_prop( 'per_page', (int) $query->get( 'posts_per_page' ) );
		wc_set_loop_prop( 'current_page', (int) max( 1, $query->get( 'paged' ) ) );

		if ( $query->have_posts() ) {
			woocommerce_product_loop_start();

			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}

			woocommerce_product_loop_end();
		} elseif ( function_exists( 'wc_no_products_found' ) ) {
			wc_no_products_found();
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	private function render_result_count_markup( WP_Query $query, $paged ) {
		ob_start();

		wc_set_loop_prop( 'total', (int) $query->found_posts );
		wc_set_loop_prop( 'total_pages', (int) $query->max_num_pages );
		wc_set_loop_prop( 'per_page', (int) $query->get( 'posts_per_page' ) );
		wc_set_loop_prop( 'current_page', (int) max( 1, $paged ) );

		if ( function_exists( 'woocommerce_result_count' ) ) {
			woocommerce_result_count();
		}

		return ob_get_clean();
	}

	private function render_pagination_markup( WP_Query $query, $item, $state ) {
		$current_page    = max( 1, isset( $state['paged'] ) ? absint( $state['paged'] ) : 1 );
		$max_num_pages   = max( 1, (int) $query->max_num_pages );
		$pagination_mode = $this->get_pagination_mode();

		if ( $max_num_pages < 2 ) {
			return '';
		}

		if ( 'load_more' === $pagination_mode ) {
			if ( $current_page >= $max_num_pages ) {
				return '';
			}

			return sprintf(
				'<div class="et-infload-controls et-shop-infload-controls scroll-mode useup-shop-filter__load-more-wrap"><a href="%1$s" class="useup-shop-filter__load-more et-loader" data-next-page="%2$s" data-current-page="%3$s" data-max-pages="%4$s" aria-label="%5$s"><span class="screen-reader-text">%5$s</span></a></div>',
				esc_url( $this->build_page_url( $item, $state, $current_page + 1 ) ),
				esc_attr( $current_page + 1 ),
				esc_attr( $current_page ),
				esc_attr( $max_num_pages ),
				esc_attr__( 'Carregar mais produtos', 'useup-melhor-envio-customizacoes' )
			);
		}

		$placeholder = 999999999;
		$base_url    = $this->build_page_url( $item, $state, $placeholder );
		$links       = paginate_links(
			array(
				'base'      => str_replace( $placeholder, '%#%', $base_url ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $max_num_pages,
				'type'      => 'list',
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
			)
		);

		if ( empty( $links ) ) {
			return '';
		}

		return '<nav class="woocommerce-pagination" aria-label="Paginação dos produtos">' . $links . '</nav>';
	}

	private function build_page_url( $item, $state, $paged ) {
		$url     = $this->build_item_href( $item );
		$orderby = isset( $state['orderby'] ) ? sanitize_text_field( (string) $state['orderby'] ) : '';
		$args    = array();

		if ( $paged > 1 ) {
			$args['paged'] = $paged;
		}

		if ( '' !== $orderby && ! $this->is_default_orderby( $orderby ) ) {
			$args['orderby'] = $orderby;
		}

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	private function is_default_orderby( $orderby ) {
		return '' === $orderby || $orderby === $this->get_default_orderby();
	}

	private function get_icon_markup( $icon ) {
		$icons = $this->get_icon_map();

		if ( isset( $icons[ $icon ] ) ) {
			return $icons[ $icon ];
		}

		return $icons['grid'];
	}

	private function get_icon_map() {
		return array(
			'grid'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="4" width="6" height="6" rx="1.2"></rect><rect x="14" y="4" width="6" height="6" rx="1.2"></rect><rect x="4" y="14" width="6" height="6" rx="1.2"></rect><rect x="14" y="14" width="6" height="6" rx="1.2"></rect></svg>',
			'flame'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3c2.2 2.6 3.4 4.6 3.4 6.4a3.9 3.9 0 0 1-1.9 3.3A5.4 5.4 0 0 0 9.7 8C7.6 10 6 12.2 6 15.1A6 6 0 0 0 12 21a6 6 0 0 0 6-5.9c0-3.4-2-5.8-6-12.1Z"></path></svg>',
			'sparkle'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m12 3 1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z"></path><path d="m19 14 .7 2.1L22 16.8l-2.3.7L19 20l-.7-2.5-2.3-.7 2.3-.7L19 14Z"></path><path d="m5 14 .6 1.7 1.7.6-1.7.6L5 18.6l-.6-1.7-1.7-.6 1.7-.6L5 14Z"></path></svg>',
			'necklace' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 4c0 4.8 1.7 8.1 5 10 3.3-1.9 5-5.2 5-10"></path><path d="M12 14v5"></path><circle cx="12" cy="20" r="2"></circle></svg>',
			'escapulario' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3.5 10 7.4"></path><path d="M16 3.5 14 7.4"></path><rect x="9.2" y="7" width="5.6" height="5.2" rx="0.8"></rect><path d="M8.8 15.2 6 12"></path><path d="M15.2 15.2 18 12"></path><rect x="8.8" y="15" width="6.4" height="5.6" rx="0.8"></rect></svg>',			
			'bracelet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="7"></circle><path d="M12 5.2h.01"></path><path d="M16.8 7.2h.01"></path><path d="M18.8 12h.01"></path><path d="M16.8 16.8h.01"></path><path d="M12 18.8h.01"></path><path d="M7.2 16.8h.01"></path><path d="M5.2 12h.01"></path><path d="M7.2 7.2h.01"></path></svg>',
			'pendant'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3c2 2 3 3.8 3 5.3 0 1.9-1.3 3.4-3 3.4s-3-1.5-3-3.4C9 6.8 10 5 12 3Z"></path><path d="M12 11.7v2.6"></path><path d="M8 14.3c0 4.1 1.8 6.7 4 6.7s4-2.6 4-6.7"></path></svg>',
			'link'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.5 13.5 13.5 10.5"></path><path d="M8.2 15.8 6.1 18a3.2 3.2 0 1 1-4.5-4.5l2.1-2.2a3.2 3.2 0 0 1 4.5 0"></path><path d="M15.8 8.2 18 6.1a3.2 3.2 0 1 1 4.5 4.5l-2.2 2.1a3.2 3.2 0 0 1-4.5 0"></path></svg>',
			'heart'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 20.6c-4.7-3.6-7.5-6-7.5-9.5a4.3 4.3 0 0 1 4.4-4.3c1.4 0 2.8.7 3.6 1.9.8-1.2 2.2-1.9 3.6-1.9a4.3 4.3 0 0 1 4.4 4.3c0 3.5-2.8 5.9-7.5 9.5Z"></path><circle cx="18.4" cy="6.1" r="1.5"></circle></svg>',
			'cross'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 4v16"></path><path d="M7 8h10"></path></svg>',
		);
	}

	private function get_default_icon_for_type( $type ) {
		$defaults = array(
			'all'          => 'grid',
			'best_sellers' => 'flame',
			'category'     => 'grid',
			'tag'          => 'sparkle',
			'custom'       => 'grid',
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : 'grid';
	}

	private function get_default_label_for_type( $type ) {
		$defaults = array(
			'all'          => 'Todos',
			'best_sellers' => 'Mais vendidos',
			'custom'       => 'Colecao',
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : 'Filtro';
	}

	private function get_allowed_svg_tags() {
		return array(
			'svg'    => array(
				'viewBox' => true,
				'fill'    => true,
				'stroke'  => true,
				'xmlns'   => true,
			),
			'path'   => array(
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
			'rect'   => array(
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
