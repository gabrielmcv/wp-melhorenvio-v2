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

		wp_enqueue_style(
			'useup-me-admin',
			USEUP_ME_URL . 'assets/admin.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-admin',
			USEUP_ME_URL . 'assets/admin.js',
			array(),
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

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'useup-melhor-envio-customizacoes' ) );
		}

		$settings         = USEUP_ME_Settings::get_all();
		$categories       = $this->get_terms_for_taxonomy( 'product_cat' );
		$tags             = $this->get_terms_for_taxonomy( 'product_tag' );
		$shipping_classes = $this->get_shipping_classes();
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
		</div>
		<?php
	}

	private function render_rule_card( $index, $rule, $categories, $tags, $shipping_classes ) {
		$rule = USEUP_ME_Rules::normalize_rule( $rule );
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
}
