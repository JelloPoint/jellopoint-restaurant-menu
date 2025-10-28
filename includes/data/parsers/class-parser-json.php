<?php
namespace JelloPoint\RestaurantMenu\Data\Parsers;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parser_JSON {

	/**
	 * @param string $json
	 * @return array{items:array,meta:array} Canonical array (stub)
	 */
	public static function parse( string $json ): array {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return [ 'items' => [], 'meta' => [], 'error' => 'Invalid JSON' ];
		}
		return [
			'items' => isset( $decoded['items'] ) && is_array( $decoded['items'] ) ? $decoded['items'] : [],
			'meta'  => isset( $decoded['meta'] ) ? (array) $decoded['meta'] : [],
		];
	}
}
