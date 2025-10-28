<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys exporter for JPRM (no featured_image in output).
 * - Prices:
 *   - mode: from 'jprm_price_mode' ('single'|'multi')
 *   - single: amount + label_mode + label_ref (raw + parsed numeric)
 *   - multi:  rows taken directly from 'jprm_prices' (unserialized) or
 *             from 'jprm_price' if it contains a 'rows' structure
 * - Badges: from 'jprm_item_badges' (unserialized array of slugs)
 * - Terms: names for jprm_menu and jprm_section
 */
final class JPRM_Exporter {

	/**
	 * Stream an export download for all jprm_menu_item posts.
	 *
	 * @param array $args { @type string $format 'json'|'csv' }
	 */
	public static function stream( array $args ): void {
		$format = ( isset( $args['format'] ) && $args['format'] === 'csv' ) ? 'csv' : 'json';
		$items  = self::collect_items();

		if ( $format === 'json' ) {
			self::stream_json( $items );
			return;
		}
		self::stream_csv( $items );
	}

	/**
	 * Query and map all items to a canonical array (without featured_image).
	 *
	 * @return array
	 */
	private static function collect_items(): array {
		$q = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );

		$out = [];
		foreach ( $q->posts as $post_id ) {
			$title   = get_the_title( $post_id );
			$status  = get_post_status( $post_id );
			$desc    = (string) get_post_meta( $post_id, 'jprm_desc', true );

			// Terms
			$menus   = self::terms_as_names( $post_id, 'jprm_menu' );
			$sects   = self::terms_as_names( $post_id, 'jprm_section' );

			// Badges (serialized array of slugs)
			$badges_raw = get_post_meta( $post_id, 'jprm_item_badges', true );
			$badges     = self::dec_any_to_array( $badges_raw );
			$badges     = array_values( array_filter( array_map( 'sanitize_title', (array) $badges ) ) );

			// Prices — exact keys
			$mode   = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
			$prices = self::build_prices_payload( $post_id, $mode );

			$out[] = [
				'post_id'        => (int) $post_id,
				'post_title'     => (string) $title,
				'post_status'    => (string) $status,
				'description'    => $desc,
				'tax'            => [
					'jprm_menu'    => $menus,
					'jprm_section' => $sects,
				],
				'badges'         => $badges,
				'prices'         => $prices,
			];
		}

		return $out;
	}

	/**
	 * Build the prices payload strictly from the known keys.
	 */
	private static function build_prices_payload( int $post_id, string $mode ): array {
		$mode = $mode ?: 'single';

		if ( $mode === 'single' ) {
			$amount_raw  = (string) get_post_meta( $post_id, 'jprm_price_amount', true );          // e.g. "5,50"
			$label_mode  = (string) get_post_meta( $post_id, 'jprm_price_label_mode', true );      // e.g. "ref"
			$label_ref   = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );       // e.g. "pl-1"

			return [
				'mode'          => 'single',
				'amount_raw'    => $amount_raw,
				'amount_number' => self::to_float_eu_us( $amount_raw ),
				'label_mode'    => $label_mode,
				'label_ref'     => $label_ref,
			];
		}

		// MULTI
		$rows = [];

		// Primary source: jprm_prices (serialized PHP array of rows)
		$raw_prices = get_post_meta( $post_id, 'jprm_prices', true );
		$rows       = self::dec_any_to_array( $raw_prices );

		// If empty, try jprm_price (it may be a JSON/serialized struct with 'rows')
		if ( empty( $rows ) ) {
			$raw_price = get_post_meta( $post_id, 'jprm_price', true );
			$dec       = self::dec_any( $raw_price );
			if ( is_array( $dec ) && isset( $dec['rows'] ) && is_array( $dec['rows'] ) ) {
				$rows = $dec['rows'];
			}
		}

		return [
			'mode' => 'multi',
			'rows' => $rows, // raw row objects/arrays as stored in meta
		];
	}

	private static function terms_as_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		return ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? [] : array_values( $terms );
	}

	/**
	 * Decode “anything” into a PHP value:
	 * - Arrays pass through
	 * - JSON strings -> array
	 * - Serialized strings -> maybe_unserialize()
	 * - Other scalars -> returned as-is
	 */
	private static function dec_any( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) {
			return (array) $v;
		}
		if ( is_string( $v ) && $v !== '' ) {
			$s = trim( $v );

			// serialized?
			if ( function_exists( 'is_serialized' ) && is_serialized( $s ) ) {
				$un = @maybe_unserialize( $s );
				if ( is_array( $un ) ) return $un;
			}

			// JSON?
			if ( ( $s[0] === '{' || $s[0] === '[' ) && substr( $s, -1 ) && ( substr( $s,-1 ) === '}' || substr( $s,-1 ) === ']' ) ) {
				$dec = json_decode( $s, true );
				if ( is_array( $dec ) ) return $dec;
			}
		}
		return $v;
	}

	/** Ensure we always return an array (never null/false) */
	private static function dec_any_to_array( $v ): array {
		$dec = self::dec_any( $v );
		return is_array( $dec ) ? $dec : [];
	}

	/** Parse EU/US decimal formats to float (e.g., "5,50" → 5.5 ; "1,234.56" → 1234.56). */
	private static function to_float_eu_us( $v ): ?float {
		if ( is_null( $v ) || $v === '' ) return null;
		if ( is_numeric( $v ) ) return (float) $v;
		if ( is_string( $v ) ) {
			$s = preg_replace( '/[^\d\.,-]/u', '', $v );
			if ( $s === '' ) return null;
			if ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) !== false ) {
				// last separator is decimal
				$last_comma = strrpos( $s, ',' );
				$last_dot   = strrpos( $s, '.' );
				if ( $last_comma > $last_dot ) {
					$s = str_replace( '.', '', $s );
					$s = str_replace( ',', '.', $s );
				} else {
					$s = str_replace( ',', '', $s );
				}
			} elseif ( strpos( $s, ',' ) !== false ) {
				$s = str_replace( ',', '.', $s );
			}
			return is_numeric( $s ) ? (float) $s : null;
		}
		return null;
	}

	private static function stream_json( array $items ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export.json"' );

		$payload = [
			'meta'  => [
				'exported_at'   => gmdate( 'c' ),
				'plugin'        => 'jellopoint-restaurant-menu',
				'plugin_version'=> defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '',
				'format'        => 'json',
				'price_keys'    => [
					'mode'        => 'jprm_price_mode',
					'single'      => [ 'amount' => 'jprm_price_amount', 'label_mode' => 'jprm_price_label_mode', 'label_ref' => 'jprm_price_label_ref' ],
					'multi'       => [ 'rows'   => 'jprm_prices (or jprm_price.rows)' ],
				],
			],
			'items' => $items,
		];

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function stream_csv( array $items ): void {
		// Excel-friendly: UTF-8 BOM + semicolon delimiter
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export.csv"' );

		$out = fopen( 'php://output', 'w' );
		// BOM
		fwrite( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );

		// Note: removed featured_image_id and featured_image_url
		$headers = [ 'post_id', 'post_title', 'post_status', 'description', 'menus', 'sections', 'badges', 'prices_json' ];
		fputcsv( $out, $headers, ';', '"' );

		foreach ( $items as $it ) {
			$menus   = implode( '|', (array) ( $it['tax']['jprm_menu'] ?? [] ) );
			$sects   = implode( '|', (array) ( $it['tax']['jprm_section'] ?? [] ) );
			$badges  = implode( '|', (array) ( $it['badges'] ?? [] ) );
			$pricesJ = wp_json_encode( $it['prices'], JSON_UNESCAPED_SLASHES );

			$row = [
				$it['post_id'],
				self::esc_csv( $it['post_title'] ),
				$it['post_status'],
				self::esc_csv( $it['description'] ),
				$menus,
				$sects,
				$badges,
				$pricesJ,
			];
			fputcsv( $out, $row, ';', '"' );
		}
		fclose( $out );
		exit;
	}

	private static function esc_csv( $val ): string {
		$val = is_scalar( $val ) ? (string) $val : '';
		// Keep it simple; fputcsv will quote. Strip CR/LF to keep rows tidy.
		return str_replace( [ "\r\n", "\n", "\r" ], ' ', $val );
	}
}
