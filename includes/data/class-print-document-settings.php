<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Validated defaults for the dedicated print/PDF document. */
final class Print_Document_Settings {
	public const OPTION_KEY = 'jprm_print_document_settings';

	public static function defaults() : array {
		return [
			'menu_id' => 0,
			'paper_size' => 'a4',
			'orientation' => 'portrait',
			'margins' => [ 'top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15 ],
		];
	}

	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, [] );
		return self::sanitize( is_array( $stored ) ? $stored : [] );
	}

	public static function save( array $input ) : bool {
		return false !== update_option( self::OPTION_KEY, self::sanitize( $input ), false );
	}

	public static function sanitize( array $input ) : array {
		$defaults = self::defaults();
		$margins = isset( $input['margins'] ) && is_array( $input['margins'] ) ? $input['margins'] : [];
		$out = [
			'menu_id' => max( 0, (int) ( $input['menu_id'] ?? 0 ) ),
			'paper_size' => 'a4',
			'orientation' => 'landscape' === (string) ( $input['orientation'] ?? '' ) ? 'landscape' : 'portrait',
			'margins' => [],
		];
		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$value = isset( $margins[ $side ] ) && is_numeric( $margins[ $side ] ) ? (float) $margins[ $side ] : (float) $defaults['margins'][ $side ];
			$out['margins'][ $side ] = max( 0, min( 50, $value ) );
		}
		return $out;
	}
}
