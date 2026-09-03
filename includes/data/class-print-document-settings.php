<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Validated defaults for the dedicated print/PDF document. */
final class Print_Document_Settings {
	public const OPTION_KEY = 'jprm_print_document_settings';

	public static function defaults() : array {
		return [
			'menu_id' => 0,
			'preset' => 'classic',
			'paper_size' => 'a4',
			'orientation' => 'portrait',
			'margins' => [ 'top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15 ],
			'columns' => 1,
			'column_breaks' => [],
			'logo_id' => 0,
			'logo_position' => 'center',
			'heading_font' => 'serif',
			'body_font' => 'sans',
			'text_color' => '#242424',
			'accent_color' => '#173f47',
			'background_color' => '#ffffff',
			'header_alignment' => 'center',
			'section_alignment' => 'left',
			'title_size' => 30,
			'section_size' => 17,
			'item_size' => 11,
			'description_size' => 9,
			'section_spacing' => 9,
			'item_spacing' => 4,
			'show_descriptions' => true,
			'show_price_labels' => true,
			'show_price_icons' => true,
			'show_badges' => true,
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
			'preset' => in_array( (string) ( $input['preset'] ?? '' ), [ 'classic', 'modern', 'elegant' ], true ) ? (string) $input['preset'] : 'classic',
			'paper_size' => 'a4',
			'orientation' => 'landscape' === (string) ( $input['orientation'] ?? '' ) ? 'landscape' : 'portrait',
			'margins' => [],
			'columns' => max( 1, min( 3, (int) ( $input['columns'] ?? $defaults['columns'] ) ) ),
			'column_breaks' => array_values( array_unique( array_filter( array_map( 'intval', (array) ( $input['column_breaks'] ?? [] ) ), static fn( int $id ) : bool => $id > 0 ) ) ),
			'logo_id' => max( 0, (int) ( $input['logo_id'] ?? 0 ) ),
			'logo_position' => self::choice( $input['logo_position'] ?? '', [ 'left', 'center', 'right' ], 'center' ),
			'heading_font' => self::choice( $input['heading_font'] ?? '', [ 'serif', 'sans', 'modern', 'classic' ], 'serif' ),
			'body_font' => self::choice( $input['body_font'] ?? '', [ 'serif', 'sans', 'modern', 'classic' ], 'sans' ),
			'text_color' => self::color( $input['text_color'] ?? '', (string) $defaults['text_color'] ),
			'accent_color' => self::color( $input['accent_color'] ?? '', (string) $defaults['accent_color'] ),
			'background_color' => self::color( $input['background_color'] ?? '', (string) $defaults['background_color'] ),
			'header_alignment' => self::choice( $input['header_alignment'] ?? '', [ 'left', 'center', 'right' ], 'center' ),
			'section_alignment' => self::choice( $input['section_alignment'] ?? '', [ 'left', 'center', 'right' ], 'left' ),
			'title_size' => self::number( $input['title_size'] ?? null, 18, 60, (float) $defaults['title_size'] ),
			'section_size' => self::number( $input['section_size'] ?? null, 10, 36, (float) $defaults['section_size'] ),
			'item_size' => self::number( $input['item_size'] ?? null, 7, 24, (float) $defaults['item_size'] ),
			'description_size' => self::number( $input['description_size'] ?? null, 6, 18, (float) $defaults['description_size'] ),
			'section_spacing' => self::number( $input['section_spacing'] ?? null, 0, 30, (float) $defaults['section_spacing'] ),
			'item_spacing' => self::number( $input['item_spacing'] ?? null, 0, 20, (float) $defaults['item_spacing'] ),
			'show_descriptions' => array_key_exists( 'show_descriptions', $input ) ? ! empty( $input['show_descriptions'] ) : true,
			'show_price_labels' => array_key_exists( 'show_price_labels', $input ) ? ! empty( $input['show_price_labels'] ) : true,
			'show_price_icons' => array_key_exists( 'show_price_icons', $input ) ? ! empty( $input['show_price_icons'] ) : true,
			'show_badges' => array_key_exists( 'show_badges', $input ) ? ! empty( $input['show_badges'] ) : true,
		];
		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$value = isset( $margins[ $side ] ) && is_numeric( $margins[ $side ] ) ? (float) $margins[ $side ] : (float) $defaults['margins'][ $side ];
			$out['margins'][ $side ] = max( 0, min( 50, $value ) );
		}
		return $out;
	}

	private static function choice( $value, array $allowed, string $fallback ) : string {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function color( $value, string $fallback ) : string {
		$value = sanitize_hex_color( is_scalar( $value ) ? (string) $value : '' );
		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	private static function number( $value, float $min, float $max, float $fallback ) : float {
		$value = is_numeric( $value ) ? (float) $value : $fallback;
		return max( $min, min( $max, $value ) );
	}
}
