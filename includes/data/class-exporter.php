<?php
namespace JelloPoint\RestaurantMenu\Data;

use JelloPoint\RestaurantMenu\Storage\Price_Repository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Exporter
 * - Streams JSON or CSV
 * - CSV export format aligned to the "easy import" template:
 *   post_id;post_title;post_status;description;menus;sections;Price_Single;Price_Multiple
 *
 * Important for Excel:
 * - Add UTF-8 BOM
 * - Use CRLF line endings for rows
 * - Normalize embedded description newlines to CRLF so Excel keeps them inside the cell
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
			$prices = self::prices_for_export( $post_id );

			$out[] = [
				'post_id'     => (int) $post_id,
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

		// Excel friendliness: UTF-8 BOM
		fwrite( $fp, "\xEF\xBB\xBF" );

		// Delimiter: semicolon (EU Excel)
		$del = ';';

		// Excel friendliness: CRLF line endings for records
		$eol = "\r\n";
		$use_eol_param = false;
		try {
			$rf = new \ReflectionFunction( 'fputcsv' );
			$use_eol_param = ( $rf->getNumberOfParameters() >= 6 );
		} catch ( \Throwable $t ) {
			$use_eol_param = false;
		}

		$headers = [
			'post_id','post_title','post_status','description','menus','sections','Price_Single','Price_Multiple'
		];

		if ( $use_eol_param ) {
			fputcsv( $fp, $headers, $del, '"', "\\", $eol );
		} else {
			// Fallback (older PHP): write to temp string and force CRLF
			$tmp = fopen( 'php://temp', 'r+' );
			fputcsv( $tmp, $headers, $del );
			rewind( $tmp );
			$line = stream_get_contents( $tmp );
			fclose( $tmp );
			$line = str_replace( "\n", $eol, $line );
			fwrite( $fp, $line );
		}

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
				$price_single = (string) ( $prices['amount_raw'] ?? '' );
				if ( $price_single === '' ) {
					$price_single = (string) ( $prices['price'] ?? '' );
				}
			}

			// IMPORTANT: normalize embedded newlines to CRLF so Excel keeps the full text in one cell
			$desc = (string) ( $it['description'] ?? '' );
			$desc = str_replace( [ "\r\n", "\r" ], "\n", $desc ); // normalize to LF first
			$desc = str_replace( "\n", $eol, $desc );             // then to CRLF

			$row = [
				(int) ( $it['post_id'] ?? 0 ),
				(string) ( $it['post_title'] ?? '' ),
				(string) ( $it['post_status'] ?? 'draft' ),
				$desc,
				$menus,
				$sections,
				$price_single,
				$price_multiple,
			];

			if ( $use_eol_param ) {
				fputcsv( $fp, $row, $del, '"', "\\", $eol );
			} else {
				$tmp = fopen( 'php://temp', 'r+' );
				fputcsv( $tmp, $row, $del );
				rewind( $tmp );
				$line = stream_get_contents( $tmp );
				fclose( $tmp );
				$line = str_replace( "\n", $eol, $line );
				fwrite( $fp, $line );
			}
		}

		fclose( $fp );
		exit;
	}

	/* ---------------- helpers ---------------- */

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

	/** Build a lossless importer-compatible price payload from canonical meta. */
	private static function prices_for_export( int $post_id ): array {
		$cfg = Price_Repository::get( $post_id );
		if ( ! is_array( $cfg ) || empty( $cfg['mode'] ) ) {
			return [];
		}

		if ( 'single' === $cfg['mode'] ) {
			$amount = (string) ( $cfg['price'] ?? '' );
			$label_mode = ( (string) get_post_meta( $post_id, 'jprm_price_label_mode', true ) === 'custom' ) ? 'custom' : 'ref';
			return [
				'mode'          => 'single',
				'amount_raw'    => $amount,
				'amount_number' => self::to_float_eu_us( $amount ),
				'label_mode'    => $label_mode,
				'label_ref'     => 'ref' === $label_mode ? (string) ( $cfg['label_ref'] ?? '' ) : '',
				'label_custom'  => 'custom' === $label_mode ? (string) ( $cfg['label_ref'] ?? '' ) : '',
				'icon_id'       => (int) ( $cfg['icon_id'] ?? 0 ),
				'hide_icon'     => ! empty( $cfg['hide_icon'] ),
			];
		}

		$editor_rows = get_post_meta( $post_id, 'jprm_prices', true );
		if ( is_string( $editor_rows ) ) {
			$decoded = json_decode( $editor_rows, true );
			$editor_rows = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $editor_rows ) ) $editor_rows = [];

		$rows = [];
		foreach ( array_values( (array) ( $cfg['rows'] ?? [] ) ) as $index => $row ) {
			if ( ! is_array( $row ) || (string) ( $row['value'] ?? '' ) === '' ) continue;
			$editor = is_array( $editor_rows[ $index ] ?? null ) ? $editor_rows[ $index ] : [];
			$label_mode = ( (string) ( $editor['label_mode'] ?? 'ref' ) === 'custom' ) ? 'custom' : 'ref';
			$rows[] = [
				'enabled'      => true,
				'label_mode'   => $label_mode,
				'label_ref'    => 'ref' === $label_mode ? (string) ( $row['label_ref'] ?? '' ) : '',
				'label_custom' => 'custom' === $label_mode ? (string) ( $row['label_ref'] ?? '' ) : '',
				'icon_id'      => (int) ( $row['icon_id'] ?? 0 ),
				'amount'       => (string) $row['value'],
				'hide_icon'    => ! empty( $row['hide_icon'] ),
			];
		}

		return [ 'mode' => 'multi', 'rows' => $rows ];
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

		$s = preg_replace( '/[^\d\.,\-]/u', '', $s );
		if ( $s === '' ) return null;

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
