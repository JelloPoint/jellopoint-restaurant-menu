<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Exporter
 * - Streams JSON or CSV
 * - Includes site_uid in meta
 * - Includes per-item jprm_uid (generated & saved if missing)
 * - Excludes featured image fields as requested previously
 */
final class JPRM_Exporter {

	public static function stream( array $opts = [] ) : void {
		$format = isset( $opts['format'] ) && $opts['format'] === 'csv' ? 'csv' : 'json';

		$items = self::collect_items();
		$payload = [
			'meta' => [
				'generated_at' => gmdate( 'c' ),
				'post_type'    => 'jprm_menu_item',
				'site_uid'     => self::get_site_uid(),
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
		] );

		$out = [];
		foreach ( (array) $q->posts as $post_id ) {
			$uid = self::ensure_item_uid( $post_id ); // generate if missing

			$mode = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
			$prices = [];
			if ( $mode === 'single' || $mode === '' ) {
				$amount  = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
				$lmode   = (string) get_post_meta( $post_id, 'jprm_price_label_mode', true );
				$lref    = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );
				$prices = [
					'mode'          => 'single',
					'amount_raw'    => $amount,
					'amount_number' => self::to_float_eu_us( $amount ),
					'label_mode'    => $lmode,
					'label_ref'     => $lref,
				];
			} else {
				$rows = [];
				$mp = get_post_meta( $post_id, 'jprm_prices', true );
				if ( is_array( $mp ) ) $rows = $mp;
				else {
					$p = get_post_meta( $post_id, 'jprm_price', true );
					if ( is_array( $p ) && isset($p['rows']) && is_array($p['rows']) ) $rows = $p['rows'];
				}
				$prices = [
					'mode' => 'multi',
					'rows' => self::canonicalize_rows( $rows ),
				];
			}

			$out[] = [
				'post_id'     => (int) $post_id,
				'uid'         => $uid,
				'post_title'  => get_the_title( $post_id ),
				'post_status' => get_post_status( $post_id ) ?: 'draft',
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
	}

	private static function stream_csv( array $payload ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export-' . gmdate('Ymd-His') . '.csv"' );

		$fp = fopen( 'php://output', 'w' );

		// Delimiter: semicolon for EU Excel friendliness
		$del = ';';

		$headers = [
			'post_id','jprm_uid','post_title','post_status','description','menus','sections','badges','prices_json'
		];
		fputcsv( $fp, $headers, $del );

		foreach ( (array) $payload['items'] as $it ) {
			$menus    = implode( '|', (array) ($it['tax']['jprm_menu'] ?? []) );
			$sections = implode( '|', (array) ($it['tax']['jprm_section'] ?? []) );
			$badges   = implode( '|', (array) ($it['badges'] ?? []) );
			$pricesj  = wp_json_encode( $it['prices'], JSON_UNESCAPED_UNICODE );

			$row = [
				(int) ($it['post_id'] ?? 0),
				(string) ($it['uid'] ?? ''),
				(string) ($it['post_title'] ?? ''),
				(string) ($it['post_status'] ?? 'draft'),
				(string) ($it['description'] ?? ''),
				$menus,
				$sections,
				$badges,
				$pricesj,
			];

			// Escape any delimiter collisions by fputcsv automatically
			fputcsv( $fp, $row, $del );
		}

		fclose( $fp );
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
		$uid = get_option( 'jprm_site_uid', '' );
		if ( ! is_string( $uid ) || $uid === '' ) {
			$uid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'site_', true );
			update_option( 'jprm_site_uid', $uid, true );
		}
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
			$a = is_array( $r ) ? $r : (array) $r;
			// Keep the keys you use most; exporter can be tolerant
			$out[] = [
				'enabled'     => isset($a['enabled']) ? (bool)$a['enabled'] : true,
				'label_mode'  => (string)($a['label_mode'] ?? 'ref'),
				'label_ref'   => (string)($a['label_ref'] ?? ''),
				'label_custom'=> (string)($a['label_custom'] ?? ''),
				'icon_id'     => (int)($a['icon_id'] ?? 0),
				'amount'      => (string)($a['amount'] ?? ( $a['value'] ?? ( $a['price'] ?? '' ) ) ),
				'hide_icon'   => isset($a['hide_icon']) ? (bool)$a['hide_icon'] : false,
			];
		}
		return $out;
	}

	private static function to_float_eu_us( $v ): ?float {
		if ( is_null( $v ) || $v === '' ) return null;
		if ( is_numeric( $v ) ) return (float) $v;
		if ( is_string( $v ) ) {
			$s = preg_replace( '/[^\d\.,-]/u', '', $v );
			if ( $s === '' ) return null;
			if ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) !== false ) {
				$last_comma = strrpos( $s, ',' );
				$last_dot   = strrpos( $s, '.' );
				if ( $last_comma > $last_dot ) { $s = str_replace( '.', '', $s ); $s = str_replace( ',', '.', $s ); }
				else { $s = str_replace( ',', '', $s ); }
			} elseif ( strpos( $s, ',' ) !== false ) {
				$s = str_replace( ',', '.', $s );
			}
			return is_numeric( $s ) ? (float) $s : null;
		}
		return null;
	}
}
