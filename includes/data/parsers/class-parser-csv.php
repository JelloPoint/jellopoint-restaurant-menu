<?php
namespace JelloPoint\RestaurantMenu\Data\Parsers;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parser_CSV {

	/**
	 * @param string $csv Raw CSV contents.
	 * @return array{items:array,meta:array}
	 */
	public static function parse( string $csv ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $csv );
		if ( ! $lines ) { return [ 'items' => [], 'meta' => [], 'error' => 'Empty CSV' ]; }

		// Delimiter auto-detect between ; and , (Excel-friendly)
		$first = $lines[0] ?? '';
		$delimiter = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

		$headers = str_getcsv( $first, $delimiter );
		$rows    = [];
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			if ( trim( $lines[$i] ) === '' ) { continue; }
			$vals = str_getcsv( $lines[$i], $delimiter );
			$rows[] = array_combine( $headers, $vals );
		}
		return [ 'items' => $rows, 'meta' => [ 'delimiter' => $delimiter ] ];
	}
}
