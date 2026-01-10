<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Exporter
 * - Streams JSON or CSV
 * - Includes site_uid in meta
 * - Includes per-item jprm_uid (generated & saved if missing)
 * - CSV export format is aligned to the "easy import" template:
 *   post_id;post_title;post_status;description;menus;sections;Price_Single;Price_Multiple
 */
final class JPRM_Exporter {

	public static function stream( array $opts = [] ) : void {
		$format = isset( $opts['format'] ) && $opts['format'] === 'csv' ? 'csv' : 'json';

		$items = self::collect_items();

		$payload = [
			'meta'  => [
				'version'      => defined( 'JPRM_VERSION' ) ? JPRM_VERSION : '',
				'exported_utc'  => gmdate( 'c' ),
				'post_type'     => 'jprm_menu_item',
				'site_uid'      => self::get_site_uid(),
			],
			'items' => $items,
		];

		if ( $format === 'csv' ) {
			self::stream_csv( $payload );
		} else {
			self::stream_json( $payload );
		}
	}

	/** Collect items in normalized shape (matching importer). */
	private static function collect_items(): array {
		$q = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		$out = [];
		foreach ( (array) $q->posts as $post_id ) {
			$uid = self::ensure_item_uid( $post_id ); // generate if missing

			$mode = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
			$prices = [];
			if ( $mode === 'single' || $mode === '' ) {
				$amount_raw = (string) get_post_meta( $post_id, 'jprm_price_amount', true );

				// Some older installs may use a different key.
				if ( $amount_raw === '' ) {
					$legacy = get_post_meta( $post_id, 'jprm_price', true );
					if ( is_array( $legacy ) && isset( $legacy['price'] ) ) {
						$amount_raw = (string) $legacy['price'];
					}
				}

				$prices = [
					'mode'          => 'single',
					'amount_raw'    => $amount_raw,
					'amount_number' => self::to_float_eu_us( $amount_raw ),
					'label_mode'    => 'ref',
					'label_ref'     => (string) get_post_meta( $post_id, 'jprm_price_label_ref', true ),
				];
			} else {
				$rows = get_post_meta( $post_id, 'jprm_prices', true );
				if ( ! is_array( $rows ) ) { $rows = []; }

				$prices = [
					'mode' => 'multi',
					'rows' => self::canonicalize_rows( $rows ),
				];
			}

			$out[] = [
				'post_id'     => (int) $post_id,
				'uid'         => $uid,
				'post_title'  => (string) get_the_title( $post_id ),
				'post_status' => (string) get_post_status( $post_id ),
				'description' => (string) get_post_meta( $post_id, 'jprm_desc', true ),
				'tax'         => [
					'jprm_menu'    => self::terms_as_names( $post_id, 'jprm_menu' ),
					'jprm_section' => self::terms_as_names( $post_id, 'jprm_section' ),
				],
				'badges'      => self::badges( $post_id ),
				'prices'      => $prices,
			];
		}

		return $out;
	}

	private static function stream_json( array $payload ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export-' . gmdate('Ymd-His') . '.json"' );
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * CSV export aligned with the "easy import" template:
	 * post_id;post_title;post_status;description;menus;sections;Price_Single;Price_Multiple
	 */
	private static function stream_csv( array $payload ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export-' . gmdate('Ymd-His') . '.csv"' );

		$fp = fopen( 'php://output', 'w' );

		// Delimiter: semicolon for EU Excel friendliness
		$del = ';';

		$headers = [
			'post_id','post_title','post_status','description','menus','sections','Price_Single','Price_Multiple'
		];
		fputcsv( $fp, $headers, $del );

		foreach ( (array) $payload['items'] as $it ) {
			$menus    = implode( '|', (array) ( $it['tax']['jprm_menu'] ?? [] ) );
			$sections = implode( '|', (array) ( $it['tax']['jprm_section'] ?? [] ) );

			$price_single   = '';
			$price_multiple = '';

			$prices = is_array( $it['prices'] ?? null ) ? (array) $it['prices'] : [];
			$mode   = (string) ( $prices['mode'] ?? '' );

			if ( $mode === 'multi' ) {
				$rows = is_array( $prices['rows'] ?? null ) ? (array) $prices['rows'] : [];
				$amts = [];
				foreach ( $rows as $r ) {
					if ( ! is_array( $r ) ) { continue; }
					$amt = isset( $r['amount'] ) ? trim( (string) $r['amount'] ) : '';
					if ( $amt === '' ) { continue; }
					$amts[] = $amt;
				}
				$price_multiple = implode( '*', $amts );
			} else {
				// Default to single
				$price_single = (string) ( $prices['amount_raw'] ?? '' );
				if ( $price_single === '' ) {
					// Backwards-compat (if older data uses another key)
					$price_single = (string) ( $prices['price'] ?? '' );
				}
			}

			$row = [
				(int) ( $it['post_id'] ?? 0 ),
				(string) ( $it['post_title'] ?? '' ),
				(string) ( $it['post_status'] ?? 'draft' ),
				(string) ( $it['description'] ?? '' ),
				$menus,
				$sections,
				$price_single,
				$price_multiple,
			];

			// fputcsv will handle quoting (including embedded newlines in description)
			fputcsv( $fp, $row, $del );
		}

		fclose( $fp );
		exit;
	}

	/* ---------------- helpers ---------------- */

	private static function ensure_item_uid( int $post_id ): string {
		$uid = (string) get_post_meta( $post_id, 'jprm_uid', true );
		if ( $uid !== '' ) return $uid;
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$uid = wp_generate_uuid4();
		} else {
			$uid = uniqid( 'jprm_', true );
		}
		update_post_meta( $post_id, 'jprm_uid', $uid );
		return $uid;
	}

	private static function get_site_uid(): string {
		$uid = (string) get_option( 'jprm_site_uid', '' );
		if ( $uid !== '' ) return $uid;

		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$uid = wp_generate_uuid4();
		} else {
			$uid = uniqid( 'jprm_site_', true );
		}

		update_option( 'jprm_site_uid', $uid );
		return $uid;
	}

	private static function terms_as_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		return ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? [] : array_values( $terms );
	}

	private static function badges( int $post_id ): array {
		$raw = get_post_meta( $post_id, 'jprm_item_badges', true );
		if ( is_array( $raw ) ) return array_values( $raw );
		if ( is_string( $raw ) && $raw !== '' && function_exists( 'is_serialized' ) && is_serialized( $raw ) ) {
			$un = @maybe_unserialize( $raw );
			return is_array( $un ) ? array_values( $un ) : [];
		}
		return [];
	}

	private static function canonicalize_rows( array $rows ): array {
		$out = [];
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) continue;

			$enabled = isset( $r['enabled'] ) ? (bool) $r['enabled'] : true;
			if ( ! $enabled ) continue;

			$amount = isset( $r['amount'] ) ? (string) $r['amount'] : '';
			$amount = trim( $amount );
			if ( $amount === '' ) continue;

			$out[] = [
				'enabled'      => true,
				'label_mode'   => isset( $r['label_mode'] ) ? (string) $r['label_mode'] : 'ref',
				'label_ref'    => isset( $r['label_ref'] ) ? (string) $r['label_ref'] : '',
				'label_custom' => isset( $r['label_custom'] ) ? (string) $r['label_custom'] : '',
				'icon_id'      => isset( $r['icon_id'] ) ? (int) $r['icon_id'] : 0,
				'amount'       => $amount,
				'hide_icon'    => ! empty( $r['hide_icon'] ),
			];
		}
		return array_values( $out );
	}

	private static function to_float_eu_us( $s ): ?float {
		if ( $s === null ) return null;
		if ( is_float( $s ) || is_int( $s ) ) return (float) $s;
		$s = trim( (string) $s );
		if ( $s === '' ) return null;

		// Remove currency symbols/spaces, keep digits/dot/comma/minus
		$s = preg_replace( '/[^\d\.,\-]/u', '', $s );

		if ( $s === '' ) return null;

		// If both comma and dot exist, decide decimal separator by last occurrence
		if ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) !== false ) {
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
}
