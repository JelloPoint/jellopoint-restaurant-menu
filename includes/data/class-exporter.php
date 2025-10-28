<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Exporter {

	/**
	 * Stream an export download. (Stub)
	 *
	 * @param array $args {
	 *   @type string $format 'json'|'csv'
	 * }
	 */
	public static function stream( array $args ): void {
		$format = isset( $args['format'] ) && $args['format'] === 'csv' ? 'csv' : 'json';

		if ( $format === 'json' ) {
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="jprm-export.json"' );
			echo wp_json_encode( [ 'meta' => [ 'note' => 'stub export' ], 'items' => [] ], JSON_PRETTY_PRINT );
			exit;
		}

		// CSV
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'post_title', 'description', 'menus', 'sections', 'badges', 'prices_json' ], ';', '"' );
		// fputcsv( $out, [ 'Example', 'Desc', 'Drinks', 'Beers', 'gluten-free', '[{"label_text":"25cl","numeric":3.5}]' ], ';', '"' );
		fclose( $out );
		exit;
	}
}
