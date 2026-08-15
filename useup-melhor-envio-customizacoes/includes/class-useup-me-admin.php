<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Admin {

	const PAGE_SLUG = 'useup-me-entrega';
	const CAPABILITY = 'manage_woocommerce';

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_post_useup_me_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_useup_me_trigger_tiktok_full_sync', array( $this, 'trigger_tiktok_full_sync' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			'USEUP! Entrega',
			'USEUP! Entrega',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'wc-enhanced-select' );

		wp_enqueue_style(
			'useup-me-admin',
			USEUP_ME_URL . 'assets/admin.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-admin',
			USEUP_ME_URL . 'assets/admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			USEUP_ME_VERSION,
			true
		);
	}

	public function save_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para salvar estas configurações.', 'useup-melhor-envio-customizacoes' ) );
		}

		check_admin_referer( 'useup_me_save_settings', 'useup_me_nonce' );

		$posted_settings = isset( $_POST['useup_me_settings'] ) ? wp_unslash( $_POST['useup_me_settings'] ) : array();
		$sanitized       = USEUP_ME_Settings::sanitize( $posted_settings );

		update_option( USEUP_ME_Settings::OPTION_KEY, $sanitized, false );

		$redirect_url = add_query_arg(
			array(
				'page'    => self::PAGE_SLUG,
				'updated' => 1,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function trigger_tiktok_full_sync() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para executar esta ação.', 'useup-melhor-envio-customizacoes' ) );
		}

		check_admin_referer( 'useup_me_trigger_tiktok_full_sync', 'useup_me_tiktok_nonce' );

		$tiktok_resync = new USEUP_ME_TikTok_Resync_Preparer();
		$action_id     = $tiktok_resync->trigger_full_sync();
		$redirect_url  = add_query_arg(
			array(
				'page'                      => self::PAGE_SLUG,
				'useup_me_tiktok_full_sync' => $action_id > 0 ? 'queued' : 'unavailable',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'useup-melhor-envio-customizacoes' ) );
		}

		$settings         = USEUP_ME_Settings::get_all();
		$categories       = $this->get_terms_for_taxonomy( 'product_cat' );
		$tags             = $this->get_terms_for_taxonomy( 'product_tag' );
		$shipping_classes = $this->get_shipping_classes();
		$shipping_methods = $this->get_shipping_methods();
		$tiktok_resync    = new USEUP_ME_TikTok_Resync_Preparer();
		$tiktok_last_sync = $tiktok_resync->get_last_triggered_at();
		$shop_filter_items = ! empty( $settings['ajax_shop_filter_items'] ) && is_array( $settings['ajax_shop_filter_items'] )
			? array_values( $settings['ajax_shop_filter_items'] )
			: array( $this->get_empty_ajax_shop_filter_item() );
		$quantity_price_adjustments = ! empty( $settings['quantity_price_adjustments'] ) && is_array( $settings['quantity_price_adjustments'] )
			? array_values( $settings['quantity_price_adjustments'] )
			: array( $this->get_empty_quantity_price_adjustment() );
		?>
		<div class="wrap useup-me-admin">
			<h1>USEUP! Entrega</h1>
			<p>Configure regras para acrescentar dias ao prazo do Melhor Envio sem alterar preço do frete.</p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Configurações salvas com sucesso.</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['useup_me_hooks_sync'] ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>Verificação dos hooks do Melhor Envio executada novamente.</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['useup_me_tiktok_full_sync'] ) && 'queued' === $_GET['useup_me_tiktok_full_sync'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Sincronização completa da TikTok enfileirada com sucesso.</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['useup_me_tiktok_full_sync'] ) && 'unavailable' === $_GET['useup_me_tiktok_full_sync'] ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p>Não foi possível enfileirar a sincronização da TikTok. Verifique se a integração está conectada e com catálogo selecionado.</p>
				</div>
			<?php endif; ?>

			<div class="useup-me-card">
				<h2>TikTok</h2>
				<p>Enfileire manualmente uma sincronização completa do catálogo da TikTok para republicar os produtos com o preço de varejo calculado pelo plugin.</p>

				<?php if ( $tiktok_resync->is_available() ) : ?>
					<?php if ( '' !== $tiktok_last_sync ) : ?>
						<p class="description">
							Último disparo manual:
							<strong><?php echo esc_html( wp_date( 'd/m/Y H:i:s', strtotime( $tiktok_last_sync ) ) ); ?></strong>
							<?php if ( $tiktok_resync->get_last_action_id() > 0 ) : ?>
								(ação #<?php echo esc_html( $tiktok_resync->get_last_action_id() ); ?>)
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 12px;">
						<input type="hidden" name="action" value="useup_me_trigger_tiktok_full_sync" />
						<?php wp_nonce_field( 'useup_me_trigger_tiktok_full_sync', 'useup_me_tiktok_nonce' ); ?>
						<?php submit_button( 'Enfileirar sincronização completa da TikTok', 'secondary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p class="description">Conecte a integração da TikTok, selecione um catálogo e mantenha o token ativo para habilitar este gatilho manual.</p>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="useup_me_save_settings" />
				<?php wp_nonce_field( 'useup_me_save_settings', 'useup_me_nonce' ); ?>

				<div class="useup-me-card">
					<h2>Cálculo de frete na página do produto</h2>
					<p>Exibe um bloco de cálculo de entrega e prazo diretamente na página individual do produto, abaixo do botão Comprar.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[show_product_shipping_calculator]"
							value="1"
							<?php checked( ! empty( $settings['show_product_shipping_calculator'] ), true ); ?>
						/>
						Ativar cálculo de frete na página do produto
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-free-shipping-threshold">Valor de referência para frete grátis</label>
							<input
								type="number"
								min="0"
								step="0.01"
								id="useup-me-free-shipping-threshold"
								name="useup_me_settings[product_shipping_free_shipping_threshold]"
								value="<?php echo esc_attr( $settings['product_shipping_free_shipping_threshold'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Use `0` para ocultar a linha automaticamente quando a mensagem depender de valor.</p>
						</div>

						<div>
							<label for="useup-me-free-shipping-message">Texto da mensagem de frete grátis</label>
							<input
								type="text"
								id="useup-me-free-shipping-message"
								name="useup_me_settings[product_shipping_free_shipping_message]"
								value="<?php echo esc_attr( $settings['product_shipping_free_shipping_message'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Use `{amount}` para inserir o valor formatado automaticamente. Deixe vazio para não exibir a mensagem.</p>
						</div>
					</div>


				</div>

				<div class="useup-me-card">
					<h2>Controle de preco por quantidade</h2>
					<p>Aplica acrescimos ao preco dos produtos enquanto o carrinho nao atingir a quantidade minima configurada para atacado.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_quantity_price_control]"
							value="1"
							<?php checked( ! empty( $settings['enable_quantity_price_control'] ), true ); ?>
						/>
						Ativar controle de preco por quantidade
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-wholesale-min-quantity">Quantidade minima para preco de atacado</label>
							<input
								type="number"
								min="1"
								step="1"
								id="useup-me-wholesale-min-quantity"
								name="useup_me_settings[wholesale_min_quantity]"
								value="<?php echo esc_attr( $settings['wholesale_min_quantity'] ); ?>"
								class="small-text"
							/>
						</div>

						<div>
							<label for="useup-me-wholesale-quantity-count-mode">Tipo de contagem</label>
							<select
								id="useup-me-wholesale-quantity-count-mode"
								name="useup_me_settings[wholesale_quantity_count_mode]"
							>
								<option value="total_cart_items" <?php selected( $settings['wholesale_quantity_count_mode'], 'total_cart_items' ); ?>>Todas as pecas do carrinho</option>
								<option value="eligible_products_only" <?php selected( $settings['wholesale_quantity_count_mode'], 'eligible_products_only' ); ?>>Apenas produtos elegiveis</option>
							</select>
						</div>
					</div>

					<div class="useup-me-toolbar useup-me-toolbar--tight">
						<h3>Acrescimos de preco</h3>
						<button type="button" class="button button-secondary" id="useup-me-add-quantity-price-adjustment">Adicionar acrescimo</button>
					</div>

					<div id="useup-me-quantity-price-adjustments" data-next-index="<?php echo esc_attr( count( $quantity_price_adjustments ) ); ?>">
						<?php foreach ( $quantity_price_adjustments as $index => $adjustment ) : ?>
							<?php $this->render_quantity_price_adjustment( $index, $adjustment ); ?>
						<?php endforeach; ?>
					</div>
					<p class="description">Os acrescimos sao aplicados em sequencia, na ordem configurada. O preco de varejo exibido usa a mesma regra.</p>
				</div>

				<div class="useup-me-card">
					<h2>Desconto de frete por quantidade</h2>
					<p>Aplica desconto nos metodos de frete retornados pelo WooCommerce conforme a quantidade total de produtos no carrinho.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_shipping_quantity_discount]"
							value="1"
							<?php checked( ! empty( $settings['enable_shipping_quantity_discount'] ), true ); ?>
						/>
						Ativar desconto de frete por quantidade
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-shipping-discount-per-item">Valor de desconto por produto</label>
							<input
								type="number"
								min="0"
								step="0.01"
								id="useup-me-shipping-discount-per-item"
								name="useup_me_settings[shipping_discount_per_item]"
								value="<?php echo esc_attr( $settings['shipping_discount_per_item'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Valor descontado do frete para cada produto no carrinho, respeitando o limite maximo configurado.</p>
						</div>

						<div>
							<label for="useup-me-shipping-discount-max-items">Quantidade maxima de produtos com desconto</label>
							<input
								type="number"
								min="1"
								step="1"
								id="useup-me-shipping-discount-max-items"
								name="useup_me_settings[shipping_discount_max_items]"
								value="<?php echo esc_attr( $settings['shipping_discount_max_items'] ); ?>"
								class="small-text"
							/>
							<p class="description">Carrinhos acima do limite configurado nao recebem desconto adicional.</p>
						</div>
					</div>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-shipping-discount-label">Texto do desconto no checkout/carrinho</label>
							<input
								type="text"
								id="useup-me-shipping-discount-label"
								name="useup_me_settings[shipping_discount_label]"
								value="<?php echo esc_attr( $settings['shipping_discount_label'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Texto exibido ao cliente quando o desconto for aplicado ao metodo de frete.</p>
						</div>

						<div>
							<label class="useup-me-checkbox" for="useup-me-shipping-discount-apply-free">
								<input
									type="checkbox"
									id="useup-me-shipping-discount-apply-free"
									name="useup_me_settings[shipping_discount_apply_to_free_shipping]"
									value="1"
									<?php checked( ! empty( $settings['shipping_discount_apply_to_free_shipping'] ), true ); ?>
								/>
								Aplicar em frete gratis?
							</label>
							<p class="description">Quando desativado, metodos de frete gratis nao recebem desconto adicional.</p>
						</div>
					</div>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-shipping-discount-methods">Metodos de frete elegiveis</label>
							<select
								id="useup-me-shipping-discount-methods"
								name="useup_me_settings[shipping_discount_allowed_methods][]"
								multiple="multiple"
								size="6"
							>
								<?php foreach ( $shipping_methods as $method_id => $method_label ) : ?>
									<option
										value="<?php echo esc_attr( $method_id ); ?>"
										<?php selected( in_array( (string) $method_id, $settings['shipping_discount_allowed_methods'], true ), true ); ?>
									>
										<?php echo esc_html( $method_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">Se nenhum metodo for selecionado, o desconto sera aplicado a todos os fretes pagos.</p>
						</div>
					</div>
				</div>

				<div class="useup-me-card">
					<h2>Produtos complementares</h2>
					<p>Exibe produtos complementares na pagina individual do produto, permitindo adicionar itens extras ao carrinho junto com o produto principal.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_complementary_products]"
							value="1"
							<?php checked( ! empty( $settings['enable_complementary_products'] ), true ); ?>
						/>
						Ativar produtos complementares na pagina do produto
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-complementary-title">Titulo do bloco</label>
							<input
								type="text"
								id="useup-me-complementary-title"
								name="useup_me_settings[complementary_products_title]"
								value="<?php echo esc_attr( $settings['complementary_products_title'] ); ?>"
								class="regular-text"
							/>
						</div>

						<div>
							<label for="useup-me-complementary-subtitle">Texto de apoio do bloco</label>
							<textarea
								id="useup-me-complementary-subtitle"
								name="useup_me_settings[complementary_products_subtitle]"
								rows="3"
								class="large-text"
							><?php echo esc_textarea( $settings['complementary_products_subtitle'] ); ?></textarea>
						</div>
					</div>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-complementary-products">Produtos complementares</label>
							<?php
							$this->render_product_search_field(
								'useup-me-complementary-products',
								'useup_me_settings[complementary_product_ids][]',
								$settings['complementary_product_ids'],
								'Busque por nome ou SKU'
							);
							?>
							<p class="description">Selecione um ou mais produtos reais do WooCommerce para exibir como complementares.</p>
						</div>
					</div>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-complementary-display-mode">Exibir em quais produtos?</label>
							<select
								id="useup-me-complementary-display-mode"
								name="useup_me_settings[complementary_display_mode]"
								class="useup-me-complementary-display-mode"
							>
								<option value="all_products" <?php selected( $settings['complementary_display_mode'], 'all_products' ); ?>>Todos os produtos</option>
								<option value="selected_categories" <?php selected( $settings['complementary_display_mode'], 'selected_categories' ); ?>>Categorias selecionadas</option>
								<option value="selected_tags" <?php selected( $settings['complementary_display_mode'], 'selected_tags' ); ?>>Tags selecionadas</option>
								<option value="selected_products" <?php selected( $settings['complementary_display_mode'], 'selected_products' ); ?>>Produtos principais selecionados</option>
							</select>
						</div>
					</div>

					<div class="useup-me-grid useup-me-complementary-display-targets" style="margin-top: 16px;">
						<div
							class="useup-me-complementary-display-target useup-me-complementary-display-target--categories"
							data-display-mode="selected_categories"
						>
							<label for="useup-me-complementary-categories">Categorias alvo</label>
							<select
								id="useup-me-complementary-categories"
								name="useup_me_settings[complementary_category_ids][]"
								multiple="multiple"
								size="6"
							>
								<?php foreach ( $categories as $term ) : ?>
									<option
										value="<?php echo esc_attr( $term->term_id ); ?>"
										<?php selected( in_array( (int) $term->term_id, $settings['complementary_category_ids'], true ), true ); ?>
									>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">O bloco sera exibido apenas em produtos destas categorias.</p>
						</div>

						<div
							class="useup-me-complementary-display-target useup-me-complementary-display-target--tags"
							data-display-mode="selected_tags"
						>
							<label for="useup-me-complementary-tags">Tags alvo</label>
							<select
								id="useup-me-complementary-tags"
								name="useup_me_settings[complementary_tag_ids][]"
								multiple="multiple"
								size="6"
							>
								<?php foreach ( $tags as $term ) : ?>
									<option
										value="<?php echo esc_attr( $term->term_id ); ?>"
										<?php selected( in_array( (int) $term->term_id, $settings['complementary_tag_ids'], true ), true ); ?>
									>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">O bloco sera exibido apenas em produtos com estas tags.</p>
						</div>

						<div
							class="useup-me-complementary-display-target useup-me-complementary-display-target--products"
							data-display-mode="selected_products"
						>
							<label for="useup-me-complementary-target-products">Produtos principais selecionados</label>
							<?php
							$this->render_product_search_field(
								'useup-me-complementary-target-products',
								'useup_me_settings[complementary_target_product_ids][]',
								$settings['complementary_target_product_ids'],
								'Busque os produtos principais'
							);
							?>
							<p class="description">Use esta opcao quando quiser exibir os complementares apenas em produtos especificos.</p>
						</div>
					</div>
				</div>

				<div class="useup-me-card">
					<h2>Filtro AJAX do Shop</h2>
					<p>Ativa uma navegacao premium e curada no Shop, com filtro via AJAX e fallback por links reais.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_ajax_shop_filter]"
							value="1"
							<?php checked( ! empty( $settings['enable_ajax_shop_filter'] ), true ); ?>
						/>
						Ativar filtro AJAX do Shop
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-ajax-shop-products-per-page">Quantidade de produtos por pagina</label>
							<input
								type="number"
								min="1"
								step="1"
								id="useup-me-ajax-shop-products-per-page"
								name="useup_me_settings[ajax_shop_products_per_page]"
								value="<?php echo esc_attr( $settings['ajax_shop_products_per_page'] ); ?>"
								class="small-text"
							/>
						</div>

						<div>
							<label for="useup-me-ajax-shop-pagination-mode">Comportamento de paginacao</label>
							<select
								id="useup-me-ajax-shop-pagination-mode"
								name="useup_me_settings[ajax_shop_pagination_mode]"
							>
								<option value="pagination" <?php selected( $settings['ajax_shop_pagination_mode'], 'pagination' ); ?>>Paginacao AJAX</option>
								<option value="load_more" <?php selected( $settings['ajax_shop_pagination_mode'], 'load_more' ); ?>>Carregar mais</option>
							</select>
						</div>
					</div>

					<div class="useup-me-toolbar useup-me-toolbar--tight">
						<h3>Itens do filtro</h3>
						<button type="button" class="button button-secondary" id="useup-me-add-shop-filter-item">Adicionar item</button>
					</div>

					<div id="useup-me-shop-filter-items" data-next-index="<?php echo esc_attr( count( $shop_filter_items ) ); ?>">
						<?php foreach ( $shop_filter_items as $index => $item ) : ?>
							<?php $this->render_ajax_shop_filter_item( $index, $item, $categories, $tags ); ?>
						<?php endforeach; ?>
					</div>
					<p class="description">Use apenas os itens curados que devem aparecer no topo do Shop. Categorias e tags usam o slug real do WooCommerce.</p>
				</div>

				<div class="useup-me-card">
					<h2>Paginas de categoria e tag premium</h2>
					<p>Ativa um cabecalho editorial mais elegante nas paginas de categorias e tags de produto, sem mexer no grid e nos cards.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_premium_category_pages]"
							value="1"
							<?php checked( ! empty( $settings['enable_premium_category_pages'] ), true ); ?>
						/>
						Ativar paginas de categoria e tag premium
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label class="useup-me-checkbox" for="useup-me-category-use-description-subtitle">
								<input
									type="checkbox"
									id="useup-me-category-use-description-subtitle"
									name="useup_me_settings[category_use_description_subtitle]"
									value="1"
									<?php checked( ! empty( $settings['category_use_description_subtitle'] ), true ); ?>
								/>
								Usar descricao da categoria como subtitulo
							</label>
							<p class="description">Quando a categoria ou tag tiver descricao, ela aparece abaixo do titulo do cabecalho premium.</p>
						</div>

						<div>
							<label class="useup-me-checkbox" for="useup-me-category-show-title-ornament">
								<input
									type="checkbox"
									id="useup-me-category-show-title-ornament"
									name="useup_me_settings[category_show_title_ornament]"
									value="1"
									<?php checked( ! empty( $settings['category_show_title_ornament'] ), true ); ?>
								/>
								Exibir elemento decorativo abaixo do titulo
							</label>
							<p class="description">Renderiza uma linha delicada com ponto central entre o titulo e o subtitulo.</p>
						</div>
					</div>
				</div>

				<div class="useup-me-card">
					<h2>Visual premium no checkout</h2>
					<p>Redesenha visualmente a área de entrega e total do checkout para manter a experiência premium da USEUP!.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_checkout_polish]"
							value="1"
							<?php checked( ! empty( $settings['enable_checkout_polish'] ), true ); ?>
						/>
						Aplicar visual premium no checkout
					</label>
					<label class="useup-me-checkbox" style="margin-top: 14px;">
						<input
							type="checkbox"
							name="useup_me_settings[enable_checkout_form_design]"
							value="1"
							<?php checked( ! empty( $settings['enable_checkout_form_design'] ), true ); ?>
						/>
						Aplicar design premium no formulário do checkout
					</label>
					<p class="description">Organiza visualmente os campos do formulário de checkout em grupos e aplica um design mais limpo e premium.</p>
				</div>

				<div class="useup-me-card">
					<h2>Página do produto</h2>
					<p>Ajusta a hierarquia visual da página de produto, aproxima o CTA do preço e adiciona badges discretas com descrição curta expansível.</p>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							name="useup_me_settings[enable_product_page_polish]"
							value="1"
							<?php checked( ! empty( $settings['enable_product_page_polish'] ), true ); ?>
						/>
						Aplicar visual premium na página de produto
					</label>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label class="useup-me-checkbox" for="useup-me-enable-installment-badge">
								<input
									type="checkbox"
									id="useup-me-enable-installment-badge"
									name="useup_me_settings[enable_product_installment_badge]"
									value="1"
									<?php checked( ! empty( $settings['enable_product_installment_badge'] ), true ); ?>
								/>
								Ativar badge de parcelamento
							</label>
							<input
								type="text"
								name="useup_me_settings[product_installment_badge_text]"
								value="<?php echo esc_attr( $settings['product_installment_badge_text'] ); ?>"
								class="regular-text"
								placeholder="Até 12x"
							/>
						</div>

						<div>
							<label class="useup-me-checkbox" for="useup-me-enable-pix-badge">
								<input
									type="checkbox"
									id="useup-me-enable-pix-badge"
									name="useup_me_settings[enable_product_pix_badge]"
									value="1"
									<?php checked( ! empty( $settings['enable_product_pix_badge'] ), true ); ?>
								/>
								Ativar badge de PIX
							</label>
							<input
								type="text"
								name="useup_me_settings[product_pix_badge_text]"
								value="<?php echo esc_attr( $settings['product_pix_badge_text'] ); ?>"
								class="regular-text"
								placeholder="5% no PIX"
							/>
						</div>
					</div>

					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-retail-markup-percent">Percentual de acréscimo para preço de varejo</label>
							<input
								type="number"
								min="0"
								step="0.01"
								id="useup-me-retail-markup-percent"
								name="useup_me_settings[retail_markup_percent]"
								value="<?php echo esc_attr( $settings['retail_markup_percent'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Percentual aplicado sobre o preço de atacado para calcular o preço de varejo exibido no produto.</p>
						</div>

						<div>
							<label for="useup-me-retail-markup-fixed">Acréscimo fixo para preço de varejo</label>
							<input
								type="number"
								min="0"
								step="0.01"
								id="useup-me-retail-markup-fixed"
								name="useup_me_settings[retail_markup_fixed]"
								value="<?php echo esc_attr( $settings['retail_markup_fixed'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Valor fixo somado ao preço de varejo após o acréscimo percentual.</p>
						</div>
					</div>
					<div class="useup-me-grid" style="margin-top: 16px;">
						<div>
							<label for="useup-me-product-wholesale-tooltip-line-1">Texto 1 do popover de atacado</label>
							<input
								type="text"
								id="useup-me-product-wholesale-tooltip-line-1"
								name="useup_me_settings[product_wholesale_tooltip_line_1]"
								value="<?php echo esc_attr( $settings['product_wholesale_tooltip_line_1'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Primeira linha exibida no texto de apoio do indicador &ldquo;no atacado&rdquo;.</p>
						</div>

						<div>
							<label for="useup-me-product-wholesale-tooltip-line-2">Texto 2 do popover de atacado</label>
							<input
								type="text"
								id="useup-me-product-wholesale-tooltip-line-2"
								name="useup_me_settings[product_wholesale_tooltip_line_2]"
								value="<?php echo esc_attr( $settings['product_wholesale_tooltip_line_2'] ); ?>"
								class="regular-text"
							/>
							<p class="description">Segunda linha exibida logo abaixo da primeira no popover.</p>
						</div>
					</div>
				</div>

				<div class="useup-me-card">
					<h2>Combinação global das regras</h2>
					<p>Defina como o plugin deve consolidar os dias extras quando mais de uma regra corresponder ao mesmo pacote.</p>
					<select name="useup_me_settings[combine_mode]">
						<option value="max" <?php selected( $settings['combine_mode'], 'max' ); ?>>Usar apenas o maior acréscimo</option>
						<option value="sum" <?php selected( $settings['combine_mode'], 'sum' ); ?>>Somar acréscimos das regras correspondentes</option>
					</select>
				</div>

				<div class="useup-me-toolbar">
					<h2>Regras de dias extras</h2>
					<button type="button" class="button button-secondary" id="useup-me-add-rule">Adicionar regra</button>
				</div>

				<div id="useup-me-rules" data-next-index="<?php echo esc_attr( max( 1, count( $settings['rules'] ) ) ); ?>">
					<?php
					if ( empty( $settings['rules'] ) ) {
						$this->render_rule_card(
							0,
							USEUP_ME_Rules::get_empty_rule(),
							$categories,
							$tags,
							$shipping_classes
						);
					} else {
						foreach ( $settings['rules'] as $index => $rule ) {
							$this->render_rule_card( $index, $rule, $categories, $tags, $shipping_classes );
						}
					}
					?>
				</div>

				<?php submit_button( 'Salvar configurações' ); ?>
			</form>

			<?php USEUP_ME_Melhor_Envio_Hooks::render_admin_panel(); ?>

			<template id="useup-me-rule-template">
				<?php
				$this->render_rule_card(
					'__index__',
					USEUP_ME_Rules::get_empty_rule(),
					$categories,
					$tags,
					$shipping_classes
				);
				?>
			</template>

			<template id="useup-me-shop-filter-item-template">
				<?php $this->render_ajax_shop_filter_item( '__index__', $this->get_empty_ajax_shop_filter_item(), $categories, $tags ); ?>
			</template>

			<template id="useup-me-quantity-price-adjustment-template">
				<?php $this->render_quantity_price_adjustment( '__index__', $this->get_empty_quantity_price_adjustment() ); ?>
			</template>
		</div>
		<?php
	}

	private function render_rule_card( $index, $rule, $categories, $tags, $shipping_classes ) {
		$rule                 = USEUP_ME_Rules::normalize_rule( $rule );
		$parameter_options    = USEUP_ME_Rules::get_parameter_options();
		$comparison_operators = USEUP_ME_Rules::get_comparison_operator_options();
		?>
		<div class="useup-me-card useup-me-rule">
			<div class="useup-me-rule-header">
				<h3>Regra</h3>
				<button type="button" class="button-link-delete useup-me-remove-rule">Remover</button>
			</div>

			<div class="useup-me-grid">
				<div>
					<label for="useup-me-rule-name-<?php echo esc_attr( $index ); ?>">Nome da regra</label>
					<input
						type="text"
						id="useup-me-rule-name-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][name]"
						value="<?php echo esc_attr( $rule['name'] ); ?>"
						class="regular-text"
					/>
				</div>

				<div>
					<label for="useup-me-rule-days-<?php echo esc_attr( $index ); ?>">Dias extras</label>
					<input
						type="number"
						min="0"
						step="1"
						id="useup-me-rule-days-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][days]"
						value="<?php echo esc_attr( $rule['days'] ); ?>"
						class="small-text"
					/>
				</div>

				<div>
					<label for="useup-me-rule-status-<?php echo esc_attr( $index ); ?>">Status</label>
					<label class="useup-me-checkbox">
						<input
							type="checkbox"
							id="useup-me-rule-status-<?php echo esc_attr( $index ); ?>"
							name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][enabled]"
							value="1"
							<?php checked( $rule['enabled'], true ); ?>
						/>
						Ativa
					</label>
				</div>

				<div>
					<label for="useup-me-rule-operator-<?php echo esc_attr( $index ); ?>">Operador entre condicoes</label>
					<select
						id="useup-me-rule-operator-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][operator]"
					>
						<option value="and" <?php selected( $rule['operator'], 'and' ); ?>>AND</option>
						<option value="or" <?php selected( $rule['operator'], 'or' ); ?>>OR</option>
					</select>
				</div>

				<div>
					<label for="useup-me-rule-match-mode-<?php echo esc_attr( $index ); ?>">Aplicacao ao pacote</label>
					<select
						id="useup-me-rule-match-mode-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][match_mode]"
					>
						<option value="any" <?php selected( $rule['match_mode'], 'any' ); ?>>Se qualquer item corresponder</option>
						<option value="all" <?php selected( $rule['match_mode'], 'all' ); ?>>Se todos os itens corresponderem</option>
					</select>
				</div>

				<div>
					<label for="useup-me-rule-parameter-<?php echo esc_attr( $index ); ?>">Parâmetro adicional</label>
					<select
						id="useup-me-rule-parameter-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][parameter]"
						class="useup-me-rule-parameter"
					>
						<?php foreach ( $parameter_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule['parameter'], $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="useup-me-rule-parameter-fields" <?php echo empty( $rule['parameter'] ) ? 'hidden' : ''; ?>>
					<label for="useup-me-rule-comparison-operator-<?php echo esc_attr( $index ); ?>">Operador numérico</label>
					<select
						id="useup-me-rule-comparison-operator-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][comparison_operator]"
						class="useup-me-rule-comparison-operator"
					>
						<?php foreach ( $comparison_operators as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule['comparison_operator'], $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="useup-me-rule-parameter-fields" <?php echo empty( $rule['parameter'] ) ? 'hidden' : ''; ?>>
					<label
						for="useup-me-rule-value-<?php echo esc_attr( $index ); ?>"
						class="useup-me-rule-value-label"
					><?php echo ( 'between' === $rule['comparison_operator'] ) ? 'Valor mínimo' : 'Valor'; ?></label>
					<input
						type="number"
						min="0"
						step="1"
						id="useup-me-rule-value-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][value]"
						value="<?php echo esc_attr( $rule['value'] ); ?>"
						class="small-text useup-me-rule-value"
						placeholder="Ex.: 5"
					/>
				</div>

				<div
					class="useup-me-rule-value-to-wrap"
					<?php echo ( empty( $rule['parameter'] ) || 'between' !== $rule['comparison_operator'] ) ? 'hidden' : ''; ?>
				>
					<label for="useup-me-rule-value-to-<?php echo esc_attr( $index ); ?>">Valor máximo</label>
					<input
						type="number"
						min="0"
						step="1"
						id="useup-me-rule-value-to-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][value_to]"
						value="<?php echo esc_attr( $rule['value_to'] ); ?>"
						class="small-text useup-me-rule-value-to"
						placeholder="Ex.: 9"
					/>
				</div>
			</div>

			<div class="useup-me-grid useup-me-conditions">
				<div>
					<label for="useup-me-rule-categories-<?php echo esc_attr( $index ); ?>">Categorias</label>
					<select
						id="useup-me-rule-categories-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][category_ids][]"
						multiple="multiple"
						size="6"
					>
						<?php foreach ( $categories as $term ) : ?>
							<option
								value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( in_array( (int) $term->term_id, $rule['category_ids'], true ), true ); ?>
							>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="useup-me-rule-tags-<?php echo esc_attr( $index ); ?>">Tags</label>
					<select
						id="useup-me-rule-tags-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][tag_ids][]"
						multiple="multiple"
						size="6"
					>
						<?php foreach ( $tags as $term ) : ?>
							<option
								value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( in_array( (int) $term->term_id, $rule['tag_ids'], true ), true ); ?>
							>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="useup-me-rule-shipping-class-<?php echo esc_attr( $index ); ?>">Classes de entrega</label>
					<select
						id="useup-me-rule-shipping-class-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][shipping_class_ids][]"
						multiple="multiple"
						size="6"
					>
						<?php foreach ( $shipping_classes as $shipping_class ) : ?>
							<option
								value="<?php echo esc_attr( $shipping_class->term_id ); ?>"
								<?php selected( in_array( (int) $shipping_class->term_id, $rule['shipping_class_ids'], true ), true ); ?>
							>
								<?php echo esc_html( $shipping_class->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="useup-me-rule-products-<?php echo esc_attr( $index ); ?>">Produtos por ID</label>
					<textarea
						id="useup-me-rule-products-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][product_ids]"
						rows="3"
						placeholder="123, 456"
					><?php echo esc_textarea( implode( ', ', $rule['product_ids'] ) ); ?></textarea>
				</div>

				<div>
					<label for="useup-me-rule-skus-<?php echo esc_attr( $index ); ?>">SKUs</label>
					<textarea
						id="useup-me-rule-skus-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[rules][<?php echo esc_attr( $index ); ?>][skus]"
						rows="3"
						placeholder="SKU-001, SKU-002"
					><?php echo esc_textarea( implode( ', ', $rule['skus'] ) ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_ajax_shop_filter_item( $index, $item, $categories, $tags ) {
		$item         = wp_parse_args( is_array( $item ) ? $item : array(), $this->get_empty_ajax_shop_filter_item() );
		$type_options = $this->get_ajax_shop_filter_type_options();
		$icon_options = $this->get_ajax_shop_filter_icon_options();
		?>
		<div class="useup-me-card useup-me-shop-filter-item">
			<div class="useup-me-rule-header">
				<h3>Item do filtro</h3>
				<button type="button" class="button-link-delete useup-me-remove-shop-filter-item">Remover</button>
			</div>

			<div class="useup-me-grid">
				<div>
					<label for="useup-me-shop-filter-label-<?php echo esc_attr( $index ); ?>">Label</label>
					<input
						type="text"
						id="useup-me-shop-filter-label-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][label]"
						value="<?php echo esc_attr( $item['label'] ); ?>"
						class="regular-text"
					/>
				</div>

				<div>
					<label for="useup-me-shop-filter-type-<?php echo esc_attr( $index ); ?>">Tipo</label>
					<select
						id="useup-me-shop-filter-type-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][type]"
						class="useup-me-shop-filter-item-type"
					>
						<?php foreach ( $type_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $item['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="useup-me-shop-filter-icon-<?php echo esc_attr( $index ); ?>">Icone</label>
					<select
						id="useup-me-shop-filter-icon-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][icon]"
					>
						<?php foreach ( $icon_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $item['icon'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="useup-me-shop-filter-order-<?php echo esc_attr( $index ); ?>">Ordem</label>
					<input
						type="number"
						min="0"
						step="1"
						id="useup-me-shop-filter-order-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][order]"
						value="<?php echo esc_attr( $item['order'] ); ?>"
						class="small-text"
					/>
				</div>
			</div>

			<div class="useup-me-grid useup-me-shop-filter-item-targets">
				<div
					class="useup-me-shop-filter-item-target"
					data-shop-filter-target="category"
					<?php echo 'category' === $item['type'] ? '' : 'hidden'; ?>
				>
					<label for="useup-me-shop-filter-category-<?php echo esc_attr( $index ); ?>">Categoria vinculada</label>
					<select
						id="useup-me-shop-filter-category-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][category_slug]"
					>
						<option value="">Selecione</option>
						<?php foreach ( $categories as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $item['category_slug'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div
					class="useup-me-shop-filter-item-target"
					data-shop-filter-target="tag"
					<?php echo 'tag' === $item['type'] ? '' : 'hidden'; ?>
				>
					<label for="useup-me-shop-filter-tag-<?php echo esc_attr( $index ); ?>">Tag vinculada</label>
					<select
						id="useup-me-shop-filter-tag-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][tag_slug]"
					>
						<option value="">Selecione</option>
						<?php foreach ( $tags as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $item['tag_slug'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div
					class="useup-me-shop-filter-item-target"
					data-shop-filter-target="custom"
					<?php echo 'custom' === $item['type'] ? '' : 'hidden'; ?>
				>
					<label for="useup-me-shop-filter-custom-<?php echo esc_attr( $index ); ?>">Chave custom</label>
					<input
						type="text"
						id="useup-me-shop-filter-custom-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][custom_key]"
						value="<?php echo esc_attr( $item['custom_key'] ); ?>"
						class="regular-text"
						placeholder="colecao_autoral"
					/>
					<p class="description">Use junto ao filtro <code>useup_me_ajax_shop_custom_filter_query_args</code>.</p>
				</div>

				<div>
					<label class="useup-me-checkbox" for="useup-me-shop-filter-enabled-<?php echo esc_attr( $index ); ?>">
						<input
							type="checkbox"
							id="useup-me-shop-filter-enabled-<?php echo esc_attr( $index ); ?>"
							name="useup_me_settings[ajax_shop_filter_items][<?php echo esc_attr( $index ); ?>][enabled]"
							value="1"
							<?php checked( ! empty( $item['enabled'] ), true ); ?>
						/>
						Item ativo
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_empty_ajax_shop_filter_item() {
		return array(
			'label'         => '',
			'type'          => 'category',
			'category_slug' => '',
			'tag_slug'      => '',
			'custom_key'    => '',
			'icon'          => 'grid',
			'order'         => 10,
			'enabled'       => true,
		);
	}

	private function get_ajax_shop_filter_type_options() {
		return array(
			'all'          => 'Todos',
			'category'     => 'Categoria',
			'tag'          => 'Tag',
			'best_sellers' => 'Mais vendidos',
			'custom'       => 'Custom',
		);
	}

	private function get_ajax_shop_filter_icon_options() {
		return array(
			'grid'      => 'Grid',
			'flame'     => 'Flame',
			'sparkle'   => 'Sparkle',
			'necklace'  => 'Necklace',
			'escapulario'  => 'Escapulário',
			'foto'  => 'Foto',
			'bracelet'  => 'Bracelet',
			'pendant'   => 'Pendant',
			'link'      => 'Link',
			'heart'     => 'Heart',
			'cross'     => 'Cross',
		);
	}

	private function render_quantity_price_adjustment( $index, $adjustment ) {
		$adjustment = wp_parse_args( is_array( $adjustment ) ? $adjustment : array(), $this->get_empty_quantity_price_adjustment() );
		?>
		<div class="useup-me-card useup-me-quantity-price-adjustment">
			<div class="useup-me-rule-header">
				<h3>Acrescimo</h3>
				<button type="button" class="button-link-delete useup-me-remove-quantity-price-adjustment">Remover</button>
			</div>

			<div class="useup-me-grid">
				<div>
					<label for="useup-me-quantity-price-adjustment-type-<?php echo esc_attr( $index ); ?>">Tipo</label>
					<select
						id="useup-me-quantity-price-adjustment-type-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[quantity_price_adjustments][<?php echo esc_attr( $index ); ?>][type]"
					>
						<option value="percent" <?php selected( $adjustment['type'], 'percent' ); ?>>Porcentagem</option>
						<option value="fixed" <?php selected( $adjustment['type'], 'fixed' ); ?>>Valor fixo</option>
					</select>
				</div>

				<div>
					<label for="useup-me-quantity-price-adjustment-value-<?php echo esc_attr( $index ); ?>">Valor</label>
					<input
						type="number"
						min="0"
						step="0.01"
						id="useup-me-quantity-price-adjustment-value-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[quantity_price_adjustments][<?php echo esc_attr( $index ); ?>][value]"
						value="<?php echo esc_attr( $adjustment['value'] ); ?>"
						class="regular-text"
					/>
				</div>

				<div>
					<label for="useup-me-quantity-price-adjustment-order-<?php echo esc_attr( $index ); ?>">Ordem</label>
					<input
						type="number"
						min="0"
						step="1"
						id="useup-me-quantity-price-adjustment-order-<?php echo esc_attr( $index ); ?>"
						name="useup_me_settings[quantity_price_adjustments][<?php echo esc_attr( $index ); ?>][order]"
						value="<?php echo esc_attr( $adjustment['order'] ); ?>"
						class="small-text"
					/>
				</div>

				<div>
					<label class="useup-me-checkbox" for="useup-me-quantity-price-adjustment-enabled-<?php echo esc_attr( $index ); ?>">
						<input
							type="checkbox"
							id="useup-me-quantity-price-adjustment-enabled-<?php echo esc_attr( $index ); ?>"
							name="useup_me_settings[quantity_price_adjustments][<?php echo esc_attr( $index ); ?>][enabled]"
							value="1"
							<?php checked( ! empty( $adjustment['enabled'] ), true ); ?>
						/>
						Ativo
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_empty_quantity_price_adjustment() {
		return array(
			'type'    => 'percent',
			'value'   => '',
			'order'   => 10,
			'enabled' => true,
		);
	}

	private function get_terms_for_taxonomy( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return $terms;
	}

	private function get_shipping_classes() {
		$shipping_classes = WC()->shipping()->get_shipping_classes();

		if ( empty( $shipping_classes ) ) {
			return array();
		}

		return $shipping_classes;
	}

	private function get_shipping_methods() {
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
			return array();
		}

		$methods = WC()->shipping()->load_shipping_methods();

		if ( empty( $methods ) || ! is_array( $methods ) ) {
			return array();
		}

		$options = array();

		foreach ( $methods as $method ) {
			if ( ! is_object( $method ) ) {
				continue;
			}

			$method_id = '';
			$title     = '';

			if ( method_exists( $method, 'get_method_title' ) ) {
				$title = (string) $method->get_method_title();
			} elseif ( isset( $method->method_title ) ) {
				$title = (string) $method->method_title;
			}

			if ( method_exists( $method, 'get_method_id' ) ) {
				$method_id = (string) $method->get_method_id();
			} elseif ( isset( $method->id ) ) {
				$method_id = (string) $method->id;
			}

			$method_id = sanitize_text_field( $method_id );
			$title     = '' !== $title ? $title : $method_id;

			if ( '' !== $method_id ) {
				$options[ $method_id ] = $title;
			}
		}

		asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

		return $options;
	}

	private function render_product_search_field( $field_id, $field_name, $selected_ids, $placeholder ) {
		$selected_ids = is_array( $selected_ids ) ? array_map( 'absint', $selected_ids ) : array();
		$options      = $this->get_product_search_options( $selected_ids );
		?>
		<select
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="wc-product-search"
			multiple="multiple"
			data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
			data-action="woocommerce_json_search_products"
			data-allow_clear="true"
			data-sortable="true"
			style="width: 100%;"
		>
			<?php foreach ( $options as $product_id => $product_label ) : ?>
				<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected">
					<?php echo esc_html( $product_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private function get_product_search_options( $product_ids ) {
		$options = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$label = $product->get_formatted_name();
			$sku   = $product->get_sku();

			if ( '' !== $sku ) {
				$label .= ' (' . $sku . ')';
			}

			$options[ $product_id ] = $label;
		}

		return $options;
	}
}
