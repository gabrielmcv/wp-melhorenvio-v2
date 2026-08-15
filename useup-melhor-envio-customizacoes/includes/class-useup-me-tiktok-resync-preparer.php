<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_TikTok_Resync_Preparer {

	const LAST_TRIGGERED_OPTION = 'useup_me_tiktok_last_manual_full_sync_at';
	const LAST_ACTION_OPTION    = 'useup_me_tiktok_last_manual_full_sync_action_id';

	public function is_available() {
		if (
			! class_exists( 'TiktokForBusiness' ) ||
			! class_exists( 'Tt4b_Catalog_Class' ) ||
			! function_exists( 'as_enqueue_async_action' )
		) {
			return false;
		}

		$catalog_id   = (string) get_option( 'tt4b_catalog_id', '' );
		$bc_id        = (string) get_option( 'tt4b_bc_id', '' );
		$access_token = (string) get_option( 'tt4b_access_token', '' );

		return '' !== $catalog_id && '' !== $bc_id && '' !== $access_token;
	}

	public function trigger_full_sync() {
		if ( ! $this->is_available() ) {
			return 0;
		}

		$catalog_id = (string) get_option( 'tt4b_catalog_id', '' );
		$bc_id      = (string) get_option( 'tt4b_bc_id', '' );
		$store_name = (string) get_bloginfo( 'name' );
		$group      = 'tt4b_manual_catalog_sync_' . sanitize_key( $catalog_id );
		$payload    = array(
			'catalog_id'   => $catalog_id,
			'bc_id'        => $bc_id,
			'store_name'   => $store_name,
			'access_token' => '',
		);

		update_option( 'tt4b_last_product_sync_time', 1, false );
		update_option( 'tt4b_last_full_sync_time', 1, false );

		$action_id = as_enqueue_async_action( 'tt4b_catalog_sync', $payload, $group, true );

		if ( empty( $action_id ) ) {
			return 0;
		}

		update_option( self::LAST_TRIGGERED_OPTION, gmdate( 'c' ), false );
		update_option( self::LAST_ACTION_OPTION, absint( $action_id ), false );

		return absint( $action_id );
	}

	public function get_last_triggered_at() {
		return (string) get_option( self::LAST_TRIGGERED_OPTION, '' );
	}

	public function get_last_action_id() {
		return absint( get_option( self::LAST_ACTION_OPTION, 0 ) );
	}
}
