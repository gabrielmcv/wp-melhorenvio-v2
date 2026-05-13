<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class USEUP_ME_Delivery_Label {

	public function init() {
		add_filter(
			'useup_melhor_envio_delivery_deadline_label',
			array( $this, 'filter_delivery_deadline_label' ),
			10,
			7
		);
	}

	public function filter_delivery_deadline_label( $default_label, $delivery_range, $time_extra, $package, $products, $quotation, $service ) {
		return self::build_deadline_label( $delivery_range, $time_extra, $default_label );
	}

	public static function build_deadline_label( $delivery_range, $time_extra, $fallback = '' ) {
		$window = self::build_window_from_business_days( $delivery_range, $time_extra );

		if ( empty( $window['max_date'] ) ) {
			return $fallback;
		}

		$today         = self::get_today();
		$delivery_date = $window['max_date'];

		if ( $delivery_date->format( 'Y-m-d' ) === $today->modify( '+1 day' )->format( 'Y-m-d' ) ) {
			return ' (Chega até amanhã)';
		}

		$iso_week = $today->format( 'o-W' );
		$weekday  = self::get_weekday_label( (int) $delivery_date->format( 'N' ), true );

		if ( $delivery_date->format( 'o-W' ) === $iso_week && ! empty( $weekday ) ) {
			return sprintf( ' (Chega até %s)', $weekday );
		}

		return sprintf(
			' (Chega até %s)',
			wp_date( 'd/m', $delivery_date->getTimestamp(), wp_timezone() )
		);
	}

	public static function build_window_from_business_days( $delivery_range, $time_extra ) {
		if ( empty( $delivery_range ) || ! isset( $delivery_range->max ) ) {
			return array();
		}

		$time_extra = max( 0, (int) $time_extra );
		$min_days   = isset( $delivery_range->min ) ? max( 0, (int) $delivery_range->min + $time_extra ) : 0;
		$max_days   = max( 0, (int) $delivery_range->max + $time_extra );
		$today      = self::get_today();

		return array(
			'min_business_days' => $min_days,
			'max_business_days' => $max_days,
			'min_date'          => self::add_business_days( $today, $min_days ),
			'max_date'          => self::add_business_days( $today, $max_days ),
		);
	}

	public static function build_product_shipping_label_from_window( $window, $context = array() ) {
		$text = self::build_product_shipping_label_text( $window );

		return apply_filters( 'useup_me_product_shipping_date_label', $text, $window, $context );
	}

	public static function build_product_shipping_label_text( $window ) {
		if ( empty( $window['min_date'] ) && empty( $window['max_date'] ) ) {
			return '';
		}

		$min_date = ! empty( $window['min_date'] ) ? $window['min_date'] : $window['max_date'];
		$max_date = ! empty( $window['max_date'] ) ? $window['max_date'] : $window['min_date'];

		if ( ! $min_date instanceof DateTimeImmutable || ! $max_date instanceof DateTimeImmutable ) {
			return '';
		}

		if ( $min_date->format( 'Y-m-d' ) === $max_date->format( 'Y-m-d' ) ) {
			return sprintf( 'Chega até %s.', self::format_day_and_date( $max_date ) );
		}

		return sprintf(
			'Receba entre %s e %s.',
			self::format_day_and_date( $min_date ),
			self::format_day_and_date( $max_date )
		);
	}

	public static function format_day_and_date( DateTimeImmutable $date ) {
		$weekday = self::get_weekday_label( (int) $date->format( 'N' ), false );

		if ( empty( $weekday ) ) {
			return wp_date( 'd/m', $date->getTimestamp(), wp_timezone() );
		}

		return sprintf(
			'%s, %s',
			$weekday,
			wp_date( 'd/m', $date->getTimestamp(), wp_timezone() )
		);
	}

	private static function get_today() {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 0, 0, 0 );
	}

	private static function add_business_days( DateTimeImmutable $start_date, $business_days ) {
		$current_date = $start_date;
		$remaining    = max( 0, (int) $business_days );

		while ( $remaining > 0 ) {
			$current_date = $current_date->modify( '+1 day' );

			if ( self::is_business_day( $current_date ) ) {
				$remaining--;
			}
		}

		return $current_date;
	}

	private static function is_business_day( DateTimeImmutable $date ) {
		if ( (int) $date->format( 'N' ) > 5 ) {
			return false;
		}

		$holidays = self::get_holidays_for_year( (int) $date->format( 'Y' ) );

		return ! in_array( $date->format( 'Y-m-d' ), $holidays, true );
	}

	private static function get_holidays_for_year( $year ) {
		$easter = ( new DateTimeImmutable( '@' . easter_date( $year ) ) )->setTimezone( wp_timezone() );

		$holidays = array(
			sprintf( '%d-01-01', $year ),
			sprintf( '%d-04-21', $year ),
			sprintf( '%d-05-01', $year ),
			sprintf( '%d-09-07', $year ),
			sprintf( '%d-10-12', $year ),
			sprintf( '%d-11-02', $year ),
			sprintf( '%d-11-15', $year ),
			sprintf( '%d-12-25', $year ),
			$easter->modify( '-48 days' )->format( 'Y-m-d' ),
			$easter->modify( '-47 days' )->format( 'Y-m-d' ),
			$easter->modify( '-2 days' )->format( 'Y-m-d' ),
			$easter->format( 'Y-m-d' ),
			$easter->modify( '+60 days' )->format( 'Y-m-d' ),
		);

		$holidays = apply_filters( 'useup_me_business_holidays', $holidays, $year );

		if ( ! is_array( $holidays ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strval', $holidays ) ) );
	}

	private static function get_weekday_label( $weekday_number, $capitalize = false ) {
		$labels = $capitalize
			? array(
				1 => 'Segunda-feira',
				2 => 'Terça-feira',
				3 => 'Quarta-feira',
				4 => 'Quinta-feira',
				5 => 'Sexta-feira',
			)
			: array(
				1 => 'segunda',
				2 => 'terça',
				3 => 'quarta',
				4 => 'quinta',
				5 => 'sexta',
			);

		return isset( $labels[ $weekday_number ] ) ? $labels[ $weekday_number ] : '';
	}
}
