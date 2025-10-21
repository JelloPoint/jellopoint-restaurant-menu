<?php
/**
 * Compatibility shim for admin save — Price_Schema
 *
 * Provides a small API expected by the admin writer to normalize/validate
 * the "jprm_price" structure that your renderer uses.
 *
 * Data shape supported (matches price-block.php):
 * - Single:
 *   { "mode":"single", "price":"7.50", "label_ref":"Small", "hide_icon":false, "icon_id":0 }
 * - Multi:
 *   { "mode":"multi", "rows":[ { "value":"5", "label_ref":"Small", "hide_icon":false, "icon_id":0 }, ... ] }
 */
namespace JelloPoint\RestaurantMenu\Storage;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( __NAMESPACE__ . '\\Price_Schema' ) ) {

	class Price_Schema {

		/**
		 * Build a normalized array from request-like input.
		 * Accepts arrays coming from $_POST or meta JSON decoded arrays.
		 */
		public static function from_request( $src ) : array {
			if ( ! is_array( $src ) ) {
				return self::default();
			}

			$mode = isset( $src['mode'] ) ? (string) $src['mode'] : '';
			if ( $mode !== 'single' && $mode !== 'multi' ) {
				// Heuristic: infer from presence of 'rows'
				$mode = ! empty( $src['rows'] ) && is_array( $src['rows'] ) ? 'multi' : 'single';
			}

			if ( $mode === 'single' ) {
				return self::normalize( [
					'mode'      => 'single',
					'price'     => (string) ( $src['price']     ?? '' ),
					'label_ref' => (string) ( $src['label_ref'] ?? '' ),
					'hide_icon' => (bool)   ( $src['hide_icon'] ?? false ),
					'icon_id'   => (int)    ( $src['icon_id']   ?? 0 ),
				] );
			}

			$rows = [];
			$in   = is_array( $src['rows'] ?? null ) ? $src['rows'] : [];
			foreach ( $in as $r ) {
				if ( ! is_array( $r ) ) { continue; }
				$rows[] = [
					'value'     => (string) ( $r['value']     ?? ( $r['price'] ?? '' ) ),
					'label_ref' => (string) ( $r['label_ref'] ?? ( $r['label'] ?? '' ) ),
					'hide_icon' => (bool)   ( $r['hide_icon'] ?? false ),
					'icon_id'   => (int)    ( $r['icon_id']   ?? 0 ),
				];
			}

			return self::normalize( [ 'mode' => 'multi', 'rows' => $rows ] );
		}

		/**
		 * Ensure the array has the exact keys/types the plugin expects.
		 */
		public static function normalize( array $data ) : array {
			$mode = (string) ( $data['mode'] ?? '' );
			if ( $mode === 'single' ) {
				return [
					'mode'      => 'single',
					'price'     => (string) ( $data['price']     ?? '' ),
					'label_ref' => (string) ( $data['label_ref'] ?? '' ),
					'hide_icon' => (bool)   ( $data['hide_icon'] ?? false ),
					'icon_id'   => (int)    ( $data['icon_id']   ?? 0 ),
				];
			}

			// multi (default)
			$rows = [];
			if ( is_array( $data['rows'] ?? null ) ) {
				foreach ( $data['rows'] as $r ) {
					if ( ! is_array( $r ) ) continue;
					$val = (string) ( $r['value'] ?? ( $r['price'] ?? '' ) );
					if ( $val === '' ) continue;

					$rows[] = [
						'value'     => $val,
						'label_ref' => (string) ( $r['label_ref'] ?? ( $r['label'] ?? '' ) ),
						'hide_icon' => (bool)   ( $r['hide_icon'] ?? false ),
						'icon_id'   => (int)    ( $r['icon_id']   ?? 0 ),
					];
				}
			}
			return [ 'mode' => 'multi', 'rows' => array_values( $rows ) ];
		}

		/**
		 * Validate the normalized array. Returns [ok=>bool, errors=>string[]].
		 * We keep it lenient to avoid blocking saves; tighten if needed later.
		 */
		public static function validate( array $normalized ) : array {
			$errors = [];

			if ( ! isset( $normalized['mode'] ) ) {
				$errors[] = 'Missing mode.';
			} elseif ( $normalized['mode'] !== 'single' && $normalized['mode'] !== 'multi' ) {
				$errors[] = 'Invalid mode.';
			}

			if ( empty( $errors ) && $normalized['mode'] === 'single' ) {
				if ( ! isset( $normalized['price'] ) ) {
					$errors[] = 'Missing single price.';
				}
			}

			if ( empty( $errors ) && $normalized['mode'] === 'multi' ) {
				if ( ! isset( $normalized['rows'] ) || ! is_array( $normalized['rows'] ) ) {
					$errors[] = 'Rows must be an array.';
				}
			}

			return [ 'ok' => empty( $errors ), 'errors' => $errors ];
		}

		/**
		 * Encode to JSON for storage in post meta.
		 */
		public static function to_json( array $normalized ) : string {
			return wp_json_encode( $normalized );
		}

		/**
		 * Default empty schema.
		 */
		public static function default() : array {
			return [
				'mode'      => 'single',
				'price'     => '',
				'label_ref' => '',
				'hide_icon' => false,
				'icon_id'   => 0,
			];
		}
	}
}
