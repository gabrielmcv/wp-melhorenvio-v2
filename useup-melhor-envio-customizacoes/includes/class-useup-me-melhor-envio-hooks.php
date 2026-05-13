<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Melhor_Envio_Hooks {

	const STATUS_OPTION = 'useup_me_melhor_envio_hooks_status';
	const REPAIR_NONCE  = 'useup_me_repair_melhor_envio_hooks';

	/**
	 * @var array|null
	 */
	private static $runtime_status = null;

	public function init() {
		$this->ensure_hooks();

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'render_notice' ) );
			add_action( 'admin_post_useup_me_repair_melhor_envio_hooks', array( $this, 'handle_manual_repair' ) );
		}
	}

	public function render_notice() {
		if ( ! current_user_can( USEUP_ME_Admin::CAPABILITY ) ) {
			return;
		}

		$status = self::get_status();

		if ( empty( $status['show_notice'] ) || empty( $status['message'] ) ) {
			return;
		}

		$notice_class = ! empty( $status['ok'] ) ? 'notice notice-success' : 'notice notice-warning';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $notice_class ),
			esc_html( $status['message'] )
		);
	}

	public function handle_manual_repair() {
		if ( ! current_user_can( USEUP_ME_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Voce nao tem permissao para executar esta acao.', 'useup-melhor-envio-customizacoes' ) );
		}

		check_admin_referer( self::REPAIR_NONCE, 'useup_me_repair_nonce' );

		self::$runtime_status = null;
		delete_option( self::STATUS_OPTION );
		$this->ensure_hooks( true );

		$redirect_url = add_query_arg(
			array(
				'page'                => USEUP_ME_Admin::PAGE_SLUG,
				'useup_me_hooks_sync' => 1,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function get_status() {
		if ( null !== self::$runtime_status ) {
			return self::$runtime_status;
		}

		$status = get_option( self::STATUS_OPTION, array() );

		if ( ! is_array( $status ) ) {
			$status = array();
		}

		self::$runtime_status = wp_parse_args(
			$status,
			self::get_default_status()
		);

		return self::$runtime_status;
	}

	public static function render_admin_panel() {
		$status       = self::get_status();
		$manual_steps = self::get_manual_steps( $status );
		?>
		<div class="useup-me-card">
			<h2>Integracao com hooks do Melhor Envio</h2>
			<p>O plugin integrador verifica se os hooks obrigatorios continuam presentes no Melhor Envio e tenta reinclui-los automaticamente quando necessario.</p>

			<table class="widefat striped" style="max-width: 100%; margin-top: 12px;">
				<tbody>
					<tr>
						<th style="width: 240px;">Status</th>
						<td><?php echo esc_html( $status['label'] ); ?></td>
					</tr>
					<tr>
						<th>Arquivo monitorado</th>
						<td><?php echo ! empty( $status['target_file'] ) ? esc_html( $status['target_file'] ) : esc_html__( 'Nao localizado', 'useup-melhor-envio-customizacoes' ); ?></td>
					</tr>
					<tr>
						<th>Hooks encontrados</th>
						<td><?php echo ! empty( $status['hooks_present'] ) ? 'Sim' : 'Nao'; ?></td>
					</tr>
					<tr>
						<th>Escrita automatica</th>
						<td><?php echo ! empty( $status['write_available'] ) ? 'Disponivel' : 'Indisponivel'; ?></td>
					</tr>
					<tr>
						<th>Ultima verificacao</th>
						<td><?php echo ! empty( $status['checked_at'] ) ? esc_html( wp_date( 'd/m/Y H:i:s', (int) $status['checked_at'], wp_timezone() ) ) : 'Ainda nao registrada'; ?></td>
					</tr>
					<tr>
						<th>Mensagem</th>
						<td><?php echo esc_html( $status['message'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 14px;">
				<input type="hidden" name="action" value="useup_me_repair_melhor_envio_hooks" />
				<?php wp_nonce_field( self::REPAIR_NONCE, 'useup_me_repair_nonce' ); ?>
				<?php submit_button( 'Verificar e reincluir hooks agora', 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( ! empty( $manual_steps ) ) : ?>
				<h3 style="margin-top: 18px;">Passo a passo para reinclusao manual</h3>
				<ol style="margin-left: 18px;">
					<?php foreach ( $manual_steps as $step ) : ?>
						<li><?php echo esc_html( $step ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
		<?php
	}

	private function ensure_hooks( $force = false ) {
		$target_file = $this->locate_target_file();
		$status      = self::get_default_status();

		$status['checked_at']  = time();
		$status['target_file'] = $target_file;

		if ( empty( $target_file ) || ! file_exists( $target_file ) ) {
			$status['code']       = 'missing_file';
			$status['label']      = 'Arquivo do Melhor Envio nao localizado';
			$status['message']    = 'Nao foi possivel localizar o arquivo CalculateShippingMethodService.php do Melhor Envio para validar os hooks.';
			$status['show_notice'] = true;
			$this->store_status( $status );
			return;
		}

		$status['write_available'] = is_writable( $target_file );

		$file_contents = file_get_contents( $target_file );

		if ( false === $file_contents ) {
			$status['code']        = 'read_failed';
			$status['label']       = 'Falha ao ler o arquivo do Melhor Envio';
			$status['message']     = 'O plugin integrador encontrou o arquivo do Melhor Envio, mas nao conseguiu ler seu conteudo.';
			$status['show_notice'] = true;
			$this->store_status( $status );
			return;
		}

		$status['hooks_present'] = $this->has_required_hooks( $file_contents );

		if ( $status['hooks_present'] ) {
			$status['ok']          = true;
			$status['code']        = 'ready';
			$status['label']       = 'Hooks confirmados';
			$status['message']     = 'Os hooks obrigatorios da USEUP! estao presentes no Melhor Envio.';
			$status['show_notice'] = false;
			$this->store_status( $status );
			return;
		}

		$patched = false;

		if ( $status['write_available'] ) {
			$patched = $this->patch_target_file( $target_file, $file_contents );
		}

		if ( $patched ) {
			$updated_contents         = file_get_contents( $target_file );
			$status['hooks_present']  = is_string( $updated_contents ) && $this->has_required_hooks( $updated_contents );
			$status['ok']             = $status['hooks_present'];
			$status['code']           = $status['hooks_present'] ? 'patched' : 'patch_failed';
			$status['label']          = $status['hooks_present'] ? 'Hooks reincluidos automaticamente' : 'Falha ao validar a reinclusao automatica';
			$status['message']        = $status['hooks_present']
				? 'Os hooks obrigatorios nao estavam presentes e foram reincluidos automaticamente no Melhor Envio.'
				: 'O plugin tentou reincluir os hooks automaticamente, mas nao conseguiu confirmar o resultado final.';
			$status['show_notice']    = true;
			$this->store_status( $status );
			return;
		}

		$status['code']        = $status['write_available'] ? 'patch_failed' : 'not_writable';
		$status['label']       = $status['write_available'] ? 'Reinclusao automatica nao aplicada' : 'Arquivo sem permissao de escrita';
		$status['message']     = $status['write_available']
			? 'Os hooks da USEUP! estao ausentes no Melhor Envio e o plugin integrador nao conseguiu reaplica-los automaticamente com seguranca.'
			: 'Os hooks da USEUP! estao ausentes no Melhor Envio e o arquivo monitorado nao permite escrita automatica.';
		$status['show_notice'] = true;
		$this->store_status( $status );
	}

	private function patch_target_file( $target_file, $file_contents ) {
		$patched_contents = $this->get_patched_file_contents( $file_contents );

		if ( ! is_string( $patched_contents ) || $patched_contents === $file_contents ) {
			return false;
		}

		$this->maybe_create_backup( $target_file, $file_contents );

		return false !== file_put_contents( $target_file, $patched_contents, LOCK_EX );
	}

	private function get_patched_file_contents( $file_contents ) {
		if ( $this->has_required_hooks( $file_contents ) ) {
			return $file_contents;
		}

		$old_block = <<<'PHP'
				$rate = array(
					'id'        => $id,
					'label'     => $title . TimeHelper::label(
						$result->delivery_range,
						$timeExtra
					),
					'cost'      => MoneyHelper::cost(
						$result->price,
						$taxExtra,
						$percent
					),
					'calc_tax'  => 'per_item',
					'meta_data' => array(
						'delivery_time' => TimeHelper::label(
							$result->delivery_range,
							$timeExtra
						),
						'price'         => MoneyHelper::price(
							$result->price,
							$taxExtra,
							$percent
						),
						'company'       => $company,
					),
				);
PHP;

		$new_block = <<<'PHP'
				$timeExtra = max(
					0,
					(int) apply_filters(
						'useup_melhor_envio_time_extra',
						$timeExtra,
						$package,
						$products,
						$result,
						$this
					)
				);

				$deliveryLabel = TimeHelper::label(
					$result->delivery_range,
					$timeExtra
				);

				$deliveryLabel = apply_filters(
					'useup_melhor_envio_delivery_deadline_label',
					$deliveryLabel,
					$result->delivery_range,
					$timeExtra,
					$package,
					$products,
					$result,
					$this
				);

				if ( ! is_string( $deliveryLabel ) || '' === $deliveryLabel ) {
					$deliveryLabel = TimeHelper::label(
						$result->delivery_range,
						$timeExtra
					);
				}

				$rate = array(
					'id'        => $id,
					'label'     => $title . $deliveryLabel,
					'cost'      => MoneyHelper::cost(
						$result->price,
						$taxExtra,
						$percent
					),
					'calc_tax'  => 'per_item',
					'meta_data' => array(
						'delivery_time' => $deliveryLabel,
						'price'         => MoneyHelper::price(
							$result->price,
							$taxExtra,
							$percent
						),
						'company'       => $company,
					),
				);
PHP;

		$patched_contents = str_replace( $old_block, $new_block, $file_contents, $count );

		if ( $count > 0 ) {
			return $patched_contents;
		}

		$pattern = '/\t\t\t\t\\$rate = array\\(\R\t\t\t\t\t\\\'id\\\'\s*=>\s*\\$id,\R\t\t\t\t\t\\\'label\\\'\s*=>\s*\\$title \. TimeHelper::label\\(\R\t\t\t\t\t\t\\$result->delivery_range,\R\t\t\t\t\t\t\\$timeExtra\R\t\t\t\t\t\\),\R\t\t\t\t\t\\\'cost\\\'\s*=>\s*MoneyHelper::cost\\(\R\t\t\t\t\t\t\\$result->price,\R\t\t\t\t\t\t\\$taxExtra,\R\t\t\t\t\t\t\\$percent\R\t\t\t\t\t\\),\R\t\t\t\t\t\\\'calc_tax\\\'\s*=>\s*\\\'per_item\\\',\R\t\t\t\t\t\\\'meta_data\\\'\s*=>\s*array\\(\R\t\t\t\t\t\t\\\'delivery_time\\\'\s*=>\s*TimeHelper::label\\(\R\t\t\t\t\t\t\t\\$result->delivery_range,\R\t\t\t\t\t\t\t\\$timeExtra\R\t\t\t\t\t\t\\),\R\t\t\t\t\t\t\\\'price\\\'\s*=>\s*MoneyHelper::price\\(\R\t\t\t\t\t\t\t\\$result->price,\R\t\t\t\t\t\t\t\\$taxExtra,\R\t\t\t\t\t\t\t\\$percent\R\t\t\t\t\t\t\\),\R\t\t\t\t\t\t\\\'company\\\'\s*=>\s*\\$company,\R\t\t\t\t\t\\),\R\t\t\t\t\\);/m';

		$patched_contents = preg_replace( $pattern, $new_block, $file_contents, 1, $regex_count );

		if ( $regex_count > 0 && is_string( $patched_contents ) ) {
			return $patched_contents;
		}

		return $file_contents;
	}

	private function has_required_hooks( $file_contents ) {
		return false !== strpos( $file_contents, 'useup_melhor_envio_time_extra' )
			&& false !== strpos( $file_contents, 'useup_melhor_envio_delivery_deadline_label' );
	}

	private function locate_target_file() {
		$possible_files = array();

		if ( defined( 'MELHORENVIO_PATH' ) ) {
			$possible_files[] = trailingslashit( MELHORENVIO_PATH ) . 'Services/CalculateShippingMethodService.php';
		}

		$possible_files[] = trailingslashit( WP_PLUGIN_DIR ) . 'melhor-envio-cotacao/Services/CalculateShippingMethodService.php';
		$possible_files[] = trailingslashit( WP_PLUGIN_DIR ) . 'melhor-envio-cotacao-atualizado/melhor-envio-cotacao/Services/CalculateShippingMethodService.php';

		foreach ( $possible_files as $possible_file ) {
			if ( file_exists( $possible_file ) ) {
				return $possible_file;
			}
		}

		$matches = glob( trailingslashit( WP_PLUGIN_DIR ) . '*/Services/CalculateShippingMethodService.php' );

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				if ( false !== strpos( wp_normalize_path( $match ), 'melhor-envio' ) ) {
					return $match;
				}
			}
		}

		return '';
	}

	private function maybe_create_backup( $target_file, $file_contents ) {
		$backup_file = dirname( $target_file ) . '/CalculateShippingMethodService.useup-backup.php';

		if ( file_exists( $backup_file ) ) {
			return;
		}

		file_put_contents( $backup_file, $file_contents, LOCK_EX );
	}

	private function store_status( $status ) {
		self::$runtime_status = wp_parse_args( $status, self::get_default_status() );
		update_option( self::STATUS_OPTION, self::$runtime_status, false );
	}

	private static function get_default_status() {
		return array(
			'ok'             => false,
			'code'           => 'unknown',
			'label'          => 'Status ainda nao verificado',
			'message'        => 'O plugin integrador ainda nao verificou os hooks do Melhor Envio nesta execucao.',
			'target_file'    => '',
			'hooks_present'  => false,
			'write_available'=> false,
			'checked_at'     => 0,
			'show_notice'    => false,
		);
	}

	private static function get_manual_steps( $status ) {
		$target_file = ! empty( $status['target_file'] )
			? $status['target_file']
			: 'wp-content/plugins/melhor-envio-cotacao/Services/CalculateShippingMethodService.php';

		return array(
			'Confirme que o plugin Melhor Envio continua ativo e que o arquivo monitorado existe: ' . $target_file,
			'Abra o arquivo CalculateShippingMethodService.php e procure o trecho onde a cotacao monta o array $rate.',
			'Garanta que antes da montagem do $rate exista o filtro useup_melhor_envio_time_extra aplicado sobre $timeExtra.',
			'Garanta que a label final de prazo passe pelo filtro useup_melhor_envio_delivery_deadline_label e seja reutilizada em label e meta_data delivery_time.',
			'Salve o arquivo em UTF-8 sem BOM e volte em WooCommerce > USEUP! Entrega para executar a verificacao novamente.',
		);
	}
}
