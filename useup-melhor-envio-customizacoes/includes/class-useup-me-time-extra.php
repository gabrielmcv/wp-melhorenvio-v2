<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Time_Extra {

	public function init() {
		add_filter(
			'useup_melhor_envio_time_extra',
			array( $this, 'filter_time_extra' ),
			10,
			5
		);
	}

	public function filter_time_extra( $time_extra, $package, $products, $quotation, $service ) {
		$time_extra = max( 0, (int) $time_extra );
		$extra_days = USEUP_ME_Rules::get_extra_days_for_package( $package );

		return max( 0, $time_extra + $extra_days );
	}
}
