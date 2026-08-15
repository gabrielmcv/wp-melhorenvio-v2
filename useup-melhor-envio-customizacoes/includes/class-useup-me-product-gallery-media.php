<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Product_Gallery_Media {

	const META_KEY = '_useup_me_product_video_gallery_ids';

	public function init() {
		add_filter( 'upload_mimes', array( $this, 'allow_video_uploads' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_videos' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'woocommerce_product_thumbnails', array( $this, 'render_product_video_gallery_items' ), 30 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'filter_gallery_item_html' ), 10, 2 );
	}

	public function allow_video_uploads( $mimes ) {
		if ( current_user_can( 'upload_files' ) ) {
			$mimes['mp4'] = 'video/mp4';
		}

		return $mimes;
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style(
			'useup-me-product-gallery-media-admin',
			USEUP_ME_URL . 'assets/css/product-gallery-media-admin.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-product-gallery-media-admin',
			USEUP_ME_URL . 'assets/js/product-gallery-media-admin.js',
			array( 'jquery', 'media-editor', 'media-views', 'jquery-ui-sortable' ),
			USEUP_ME_VERSION,
			true
		);

		wp_localize_script(
			'useup-me-product-gallery-media-admin',
			'useupMeProductGalleryMediaAdmin',
			array(
				'frameTitle'       => 'Adicionar imagens e videos a galeria do produto',
				'frameButtonLabel' => 'Adicionar a galeria',
				'deleteLabel'      => 'Remover item da galeria',
				'videoBadge'       => 'MP4',
				'videoPlayLabel'   => 'Video do produto',
			)
		);
	}

	public function register_meta_box() {
		add_meta_box(
			'useup-me-product-video-gallery',
			'Videos da galeria USEUP!',
			array( $this, 'render_meta_box' ),
			'product',
			'side',
			'low'
		);
	}

	public function render_meta_box( $post ) {
		$video_ids = $this->get_product_video_ids( $post->ID );
		?>
		<div class="useup-me-product-gallery-media" data-useup-gallery-media-manager="1">
			<?php wp_nonce_field( 'useup_me_save_product_video_gallery', 'useup_me_product_video_gallery_nonce' ); ?>
			<input
				type="hidden"
				class="useup-me-product-gallery-media__ids"
				name="useup_me_product_video_gallery_ids"
				value="<?php echo esc_attr( implode( ',', $video_ids ) ); ?>"
			/>

			<ul class="useup-me-product-gallery-media__list product_images">
				<?php foreach ( $video_ids as $attachment_id ) : ?>
					<?php echo $this->get_admin_gallery_item_html( $attachment_id ); ?>
				<?php endforeach; ?>
			</ul>

			<p>
				<button type="button" class="button useup-me-open-product-gallery-media">Adicionar videos MP4</button>
			</p>
			<p class="description">Selecione arquivos MP4 para exibir junto das fotos na galeria do produto.</p>
		</div>
		<?php
	}

	public function save_product_videos( $post_id, $post ) {
		if ( ! isset( $_POST['useup_me_product_video_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['useup_me_product_video_gallery_nonce'] ) ), 'useup_me_save_product_video_gallery' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw_ids = isset( $_POST['useup_me_product_video_gallery_ids'] ) ? wp_unslash( $_POST['useup_me_product_video_gallery_ids'] ) : '';
		$ids     = $this->sanitize_attachment_ids( $raw_ids, 'video' );

		if ( empty( $ids ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, implode( ',', $ids ) );
	}

	public function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'useup-me-product-gallery-media',
			USEUP_ME_URL . 'assets/css/product-gallery-media.css',
			array(),
			USEUP_ME_VERSION
		);

		wp_enqueue_script(
			'useup-me-product-gallery-media',
			USEUP_ME_URL . 'assets/js/product-gallery-media.js',
			array( 'jquery' ),
			USEUP_ME_VERSION,
			true
		);
	}

	public function filter_gallery_item_html( $html, $attachment_id ) {
		if ( ! $this->is_video_attachment( $attachment_id ) ) {
			return $html;
		}

		return $this->get_video_gallery_item_html( $attachment_id );
	}

	public function render_product_video_gallery_items() {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		foreach ( $this->get_product_video_ids( $product->get_id() ) as $attachment_id ) {
			echo $this->get_video_gallery_item_html( $attachment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function is_video_attachment( $attachment_id ) {
		$mime_type = get_post_mime_type( $attachment_id );

		return is_string( $mime_type ) && 0 === strpos( $mime_type, 'video/' );
	}

	private function get_video_gallery_item_html( $attachment_id ) {
		$video_url = wp_get_attachment_url( $attachment_id );
		$mime_type = get_post_mime_type( $attachment_id );
		$title     = get_the_title( $attachment_id );
		$thumb_url = $this->get_attachment_preview_url( $attachment_id, 'woocommerce_gallery_thumbnail' );
		$poster_url = $this->get_attachment_preview_url( $attachment_id, 'full' );

		if ( ! $video_url ) {
			return '';
		}

		ob_start();
		?>
		<div
			data-thumb="<?php echo esc_url( $thumb_url ); ?>"
			data-thumb-alt="<?php echo esc_attr( $title ); ?>"
			class="woocommerce-product-gallery__image useup-product-gallery__image useup-product-gallery__image--video"
		>
			<div
				class="useup-product-gallery__video-shell"
				data-useup-gallery-video="1"
				data-video-src="<?php echo esc_url( $video_url ); ?>"
				aria-label="<?php echo esc_attr( $title ? $title : 'Video do produto' ); ?>"
			>
				<video
					class="useup-product-gallery__video"
					playsinline
					preload="metadata"
					muted
					autoplay
					loop
					controls
					controlslist="nodownload"
					poster="<?php echo esc_url( $poster_url ); ?>"
				>
					<source src="<?php echo esc_url( $video_url ); ?>" type="<?php echo esc_attr( $mime_type ? $mime_type : 'video/mp4' ); ?>" />
				</video>
				<span class="useup-product-gallery__video-play" aria-hidden="true"></span>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private function get_attachment_preview_url( $attachment_id, $size ) {
		$image = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! empty( $image[0] ) ) {
			return $image[0];
		}

		$icon_url = wp_mime_type_icon( $attachment_id );

		if ( $icon_url ) {
			return $icon_url;
		}

		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			return wc_placeholder_img_src( $size );
		}

		return '';
	}

	private function get_product_video_ids( $product_id ) {
		$stored = get_post_meta( $product_id, self::META_KEY, true );

		return $this->sanitize_attachment_ids( $stored, 'video' );
	}

	private function sanitize_attachment_ids( $raw_ids, $expected_type = '' ) {
		$ids = array_filter(
			array_map(
				'absint',
				is_array( $raw_ids ) ? $raw_ids : explode( ',', (string) $raw_ids )
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_values(
			array_filter(
				array_unique( $ids ),
				function ( $attachment_id ) use ( $expected_type ) {
					$attachment = get_post( $attachment_id );

					if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
						return false;
					}

					if ( '' === $expected_type ) {
						return true;
					}

					$mime_type = get_post_mime_type( $attachment_id );

					return is_string( $mime_type ) && 0 === strpos( $mime_type, $expected_type . '/' );
				}
			)
		);

		return $ids;
	}

	private function get_admin_gallery_item_html( $attachment_id ) {
		if ( ! $this->is_video_attachment( $attachment_id ) ) {
			return '';
		}

		$video_url = wp_get_attachment_url( $attachment_id );
		$thumb_url = $this->get_attachment_preview_url( $attachment_id, 'woocommerce_gallery_thumbnail' );

		if ( ! $video_url || ! $thumb_url ) {
			return '';
		}

		return sprintf(
			'<li class="image useup-me-gallery-item--video" data-attachment_id="%1$d"><span class="useup-me-gallery-item__preview"><video muted playsinline preload="metadata" src="%2$s"></video></span><span class="useup-me-gallery-item__badge">MP4</span><ul class="actions"><li><a href="#" class="delete" aria-label="%3$s">%3$s</a></li></ul></li>',
			absint( $attachment_id ),
			esc_url( $video_url ),
			esc_attr__( 'Remover item da galeria', 'useup-melhor-envio-customizacoes' )
		);
	}
}
