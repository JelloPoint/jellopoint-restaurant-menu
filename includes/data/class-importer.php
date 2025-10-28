<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys Importer for JPRM (supports JSON/CSV from our exporter).
 * - Dry-run validation & report
 * - Commit writes: create/update posts, terms, meta
 * - No heuristics beyond CSV delimiter detection and JSON decode.
 */
final class JPRM_Importer {

	/**
	 * Run an import (dry-run or commit).
	 *
	 * @param array $file  The $_FILES['jprm_import_file'] array.
	 * @param array $opts {
	 *   @type bool $dry_run               Default true (no DB writes).
	 *   @type bool $create_missing_terms  Create jprm_menu / jprm_section by name if missing.
	 * }
	 * @return array Report
	 */
	public static function run( array $file, array $opts = [] ): array {
		$dry_run              = ! empty( $opts['dry_run'] );
		$create_missing_terms = ! empty( $opts['create_missing_terms'] );

		$report = [
			'dry_run'  => $dry_run,
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => [],
			'items'    => [], // per-item result rows
		];

		// Basic file checks
		if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
			$report['errors'][] = 'Upload failed or file unreadable.';
			return $report;
		}

		$raw    = file_get_contents( $file['tmp_name'] );
		$name   = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext    = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		// Parse items
		$items = [];
		if ( $ext === 'json' ) {
			$items = self::parse_json_export( $raw, $report );
		} elseif ( $ext === 'csv' ) {
			$items = self::parse_csv_export( $raw, $report );
		} else {
			// Try detect JSON by content
			$t = ltrim( $raw );
			if ( $t !== '' && ($t[0] === '{' || $t[0] === '[') ) {
				$items = self::parse_json_export( $raw, $report );
			} else {
				$items = self::parse_csv_export( $raw, $report );
			}
		}

		if ( empty( $items ) ) {
			$report['errors'][] = 'No items found in file.';
			return $report;
		}

		// Process each item
		foreach ( $items as $row ) {
			$r = self::process_item( $row, $dry_run, $create_missing_terms );
			$report['items'][] = $r;
			if ( isset( $r['action'] ) ) {
				if ( $r['action'] === 'created' ) { $report['created']++; }
				elseif ( $r['action'] === 'updated' ) { $report['updated']++; }
				elseif ( $r['action'] === 'skipped' ) { $report['skipped']++; }
			}
			if ( ! empty( $r['error'] ) ) {
				$report['errors'][] = $r['error'];
			}
		}

		return $report;
	}

	/* ---------------------------- Parsers ---------------------------- */

	private static function parse_json_export( string $raw, array &$report ): array {
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) { $report['errors'][] = 'Invalid JSON.'; return []; }

		// Our exporter wraps in { meta, items } — but also accept a plain array of items.
		if ( isset( $dec['items'] ) && is_array( $dec['items'] ) ) {
			return $dec['items'];
		}
		// Plain array? accept as-is.
		if ( isset( $dec[0] ) && is_array( $dec[0] ) ) {
			return $dec;
		}
		$report['errors'][] = 'JSON did not contain an items array.';
		return [];
	}

	private static function parse_csv_export( string $raw, array &$report ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $raw );
		if ( ! $lines ) { $report['errors'][] = 'Empty CSV.'; return []; }

		// BOM strip
		if ( isset( $lines[0] ) ) {
			$lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
		}

		// Delimiter: prefer semicolon (our exporter), otherwise comma.
		$first     = $lines[0] ?? '';
		$delimiter = ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ) ? ';' : ',';

		$headers = str_getcsv( $first, $delimiter );
		$map     = array_flip( $headers );
		$rows    = [];

		for ( $i = 1; $i < count( $lines ); $i++ ) {
			if ( trim( $lines[$i] ) === '' ) continue;
			$vals = str_getcsv( $lines[$i], $delimiter );
			$row  = array_fill_keys( $headers, '' );
			foreach ( $headers as $h ) {
				$idx = $map[$h] ?? null;
				if ( $idx !== null && isset( $vals[$idx] ) ) {
					$row[$h] = $vals[$idx];
				}
			}
			// Map to our canonical item structure used in JSON export.
			$items_prices = json_decode( (string) ($row['prices_json'] ?? ''), true );
			if ( ! is_array( $items_prices ) ) { $items_prices = []; }

			$badges = array_filter( array_map( 'sanitize_title', explode( '|', (string) ( $row['badges'] ?? '' ) ) ) );

			$items_row = [
				'post_id'     => isset($row['post_id']) ? (int) $row['post_id'] : 0,
				'post_title'  => (string) ( $row['post_title'] ?? '' ),
				'post_status' => (string) ( $row['post_status'] ?? 'draft' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'tax'         => [
					'jprm_menu'    => array_filter( array_map( 'trim', explode( '|', (string) ( $row['menus'] ?? '' ) ) ) ),
					'jprm_section' => array_filter( array_map( 'trim', explode( '|', (string) ( $row['sections'] ?? '' ) ) ) ),
				],
				'badges'      => array_values( $badges ),
				'prices'      => $items_prices,
			];
			$rows[] = $items_row;
		}

		return $rows;
	}

	/* --------------------------- Processing -------------------------- */

	private static function process_item( array $it, bool $dry, bool $create_terms ): array {
		$title   = (string) ( $it['post_title'] ?? '' );
		$status  = (string) ( $it['post_status'] ?? 'draft' );
		$desc    = (string) ( $it['description'] ?? '' );
		$tax     = is_array( $it['tax'] ?? null ) ? $it['tax'] : [ 'jprm_menu'=>[], 'jprm_section'=>[] ];
		$badges  = is_array( $it['badges'] ?? null ) ? $it['badges'] : [];
		$prices  = is_array( $it['prices'] ?? null ) ? $it['prices'] : [];

		$existing_id = (int) ( $it['post_id'] ?? 0 );
		$post_id     = 0;
		$action      = 'skipped';
		$error       = '';

		// Determine price mode & shape exactly like exporter
		$price_mode = (string) ( $prices['mode'] ?? '' );
		if ( $price_mode === '' ) {
			// Accept single payload shorthand (amount/label in top-level)
			if ( isset( $prices['amount_raw'] ) || isset( $prices['rows'] ) ) {
				$price_mode = isset( $prices['rows'] ) ? 'multi' : 'single';
			}
		}
		if ( $price_mode !== 'single' && $price_mode !== 'multi' ) {
			// If rows present -> multi, else default single
			$price_mode = isset( $prices['rows'] ) ? 'multi' : 'single';
		}

		// Dry-run or commit: Create / Update logic
		if ( $existing_id > 0 && get_post_type( $existing_id ) === 'jprm_menu_item' ) {
			$post_id = $existing_id;
			$action  = 'updated';
			if ( ! $dry ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_title'  => $title,
					'post_status' => $status ?: 'draft',
				] );
			}
		} else {
			$action = 'created';
			if ( ! $dry ) {
				$post_id = wp_insert_post( [
					'post_type'   => 'jprm_menu_item',
					'post_title'  => $title,
					'post_status' => $status ?: 'draft',
				], true );
				if ( is_wp_error( $post_id ) ) {
					return [
						'post_id_old' => $existing_id,
						'post_id_new' => 0,
						'title'       => $title,
						'action'      => 'skipped',
						'error'       => 'Insert failed: ' . $post_id->get_error_message(),
					];
				}
			} else {
				// Simulate new ID in dry-run by leaving 0 but marking created
				$post_id = 0;
			}
		}

		// Terms
		$menus = array_values( array_filter( (array) ( $tax['jprm_menu'] ?? [] ) ) );
		$sects = array_values( array_filter( (array) ( $tax['jprm_section'] ?? [] ) ) );

		if ( ! $dry ) {
			self::apply_terms( $post_id, 'jprm_menu', $menus, $create_terms );
			self::apply_terms( $post_id, 'jprm_section', $sects, $create_terms );
		}

		// Meta: description, visible
		if ( ! $dry ) {
			update_post_meta( $post_id, 'jprm_desc', $desc );
			// visible: derive simple yes/no from presence of post_status ? keep as-is default 'yes'
			$visible = get_post_meta( $post_id, 'jprm_visible', true );
			if ( $visible === '' ) { $visible = 'yes'; }
			update_post_meta( $post_id, 'jprm_visible', $visible );
		}

		// Meta: badges (array of slugs)
		if ( ! $dry ) {
			update_post_meta( $post_id, 'jprm_item_badges', array_values( array_map( 'sanitize_title', $badges ) ) );
		}

		// Meta: prices (EXACT KEYS)
		if ( ! $dry ) {
			update_post_meta( $post_id, 'jprm_price_mode', $price_mode );

			if ( $price_mode === 'single' ) {
				$amount_raw = (string) ( $prices['amount_raw'] ?? '' );
				$label_mode = (string) ( $prices['label_mode'] ?? '' );
				$label_ref  = (string) ( $prices['label_ref'] ?? '' );

				update_post_meta( $post_id, 'jprm_price_amount', $amount_raw );
				update_post_meta( $post_id, 'jprm_price_label_mode', $label_mode );
				update_post_meta( $post_id, 'jprm_price_label_ref',  $label_ref );

				// Keep jprm_price in sync with your existing structure
				update_post_meta( $post_id, 'jprm_price', [
					'mode'      => 'single',
					'price'     => $amount_raw,
					'label_ref' => $label_ref,
				] );
				// Clear rows store
				delete_post_meta( $post_id, 'jprm_prices' );

			} else { // multi
				$rows = (array) ( $prices['rows'] ?? [] );

				update_post_meta( $post_id, 'jprm_prices', $rows );
				update_post_meta( $post_id, 'jprm_price', [
					'mode' => 'multi',
					'rows' => $rows,
				] );

				// Clear single-specific meta
				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_mode' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
			}
		}

		return [
			'post_id_old' => $existing_id,
			'post_id_new' => $dry ? $existing_id : ( $post_id ?: 0 ),
			'title'       => $title,
			'action'      => $action,
			'mode'        => $price_mode,
			'menus'       => $menus,
			'sections'    => $sects,
			'badges'      => $badges,
			'error'       => $error,
		];
	}

	private static function apply_terms( int $post_id, string $tax, array $names, bool $create ): void {
		if ( empty( $names ) ) {
			wp_set_object_terms( $post_id, [], $tax, false );
			return;
		}
		$term_ids = [];
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $tax );
			if ( ! $term && $create ) {
				$insert = wp_insert_term( $name, $tax );
				if ( ! is_wp_error( $insert ) ) {
					$term = get_term( (int) $insert['term_id'], $tax );
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $post_id, $term_ids, $tax, false );
	}
}
