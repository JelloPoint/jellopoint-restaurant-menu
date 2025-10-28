<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys Importer for JPRM (supports JSON/CSV from our exporter).
 * - Dry-run validation & report
 * - Commit writes: create/update posts, terms, meta
 * - Change detection: marks rows as "unchanged" when no actual diffs
 * - Tracks newly created Menu/Section terms
 * - Price summary per row
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
			'dry_run'   => $dry_run,
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
			'errors'    => [],
			'new_terms' => [ 'menus' => 0, 'sections' => 0 ],
			'items'     => [], // per-item result rows
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

			// Tally totals
			if ( isset( $r['action'] ) ) {
				if ( $r['action'] === 'created' ) { $report['created']++; }
				elseif ( $r['action'] === 'updated' ) { $report['updated']++; }
				elseif ( $r['action'] === 'unchanged' ) { $report['unchanged']++; }
				elseif ( $r['action'] === 'skipped' ) { $report['skipped']++; }
			}
			if ( ! empty( $r['new_terms_created']['menus'] ) ) {
				$report['new_terms']['menus'] += count( $r['new_terms_created']['menus'] );
			}
			if ( ! empty( $r['new_terms_created']['sections'] ) ) {
				$report['new_terms']['sections'] += count( $r['new_terms_created']['sections'] );
			}
			if ( ! empty( $r['error'] ) ) {
				$report['errors'][] = $r['error'];
			}

			$report['items'][] = $r;
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

		// Determine price mode exactly like exporter
		$price_mode = (string) ( $prices['mode'] ?? '' );
		if ( $price_mode !== 'single' && $price_mode !== 'multi' ) {
			$price_mode = isset( $prices['rows'] ) ? 'multi' : 'single';
		}

		// Resolve target post id (without writing yet).
		$is_existing = ( $existing_id > 0 && get_post_type( $existing_id ) === 'jprm_menu_item' );
		if ( $is_existing ) {
			$post_id = $existing_id;
			$action  = 'updated'; // default; may be switched to 'unchanged' after diff
		} else {
			$action  = 'created';
		}

		// ----------- Build "old" state (for change detection) -----------
		$old = [
			'post_title'  => $is_existing ? (string) get_the_title( $post_id ) : null,
			'post_status' => $is_existing ? (string) get_post_status( $post_id ) : null,
			'desc'        => $is_existing ? (string) get_post_meta( $post_id, 'jprm_desc', true ) : null,
			'menu_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_menu' ) : [],
			'sect_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_section' ) : [],
			'badges'      => $is_existing ? self::meta_badges( $post_id ) : [],
			'prices'      => $is_existing ? self::build_prices_payload( $post_id, (string) get_post_meta( $post_id, 'jprm_price_mode', true ) ) : [],
		];

		// ----------- Build "new" state (normalized like exporter) -----------
		$new = [
			'post_title'  => $title,
			'post_status' => $status ?: 'draft',
			'desc'        => $desc,
			'menu_terms'  => array_values( array_filter( (array) ( $tax['jprm_menu'] ?? [] ) ) ),
			'sect_terms'  => array_values( array_filter( (array) ( $tax['jprm_section'] ?? [] ) ) ),
			'badges'      => array_values( array_map( 'sanitize_title', $badges ) ),
			'prices'      => ( $price_mode === 'single' )
				? [
					'mode'          => 'single',
					'amount_raw'    => (string) ( $prices['amount_raw'] ?? '' ),
					'amount_number' => isset( $prices['amount_raw'] ) ? self::to_float_eu_us( $prices['amount_raw'] ) : null,
					'label_mode'    => (string) ( $prices['label_mode'] ?? '' ),
					'label_ref'     => (string) ( $prices['label_ref'] ?? '' ),
				  ]
				: [
					'mode' => 'multi',
					'rows' => (array) ( $prices['rows'] ?? [] ),
				  ],
		];

		// For display: price summary text
		$price_summary = ( $new['prices']['mode'] === 'single' )
			? (string) ( $new['prices']['amount_raw'] ?? '' )
			: (string) ( count( (array) $new['prices']['rows'] ) . ' rows' );

		// ----------- Compare "old" vs "new" -----------
		$changed = self::diff_any( $old, $new );

		// ----------- Perform writes only if changed & not dry-run -----------
		$new_terms_created = [ 'menus' => [], 'sections' => [] ];

		if ( ! $dry ) {
			// Create post if needed
			if ( ! $is_existing ) {
				$post_id = wp_insert_post( [
					'post_type'   => 'jprm_menu_item',
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				], true );

				if ( is_wp_error( $post_id ) ) {
					return [
						'post_id_old'       => $existing_id,
						'post_id_new'       => 0,
						'title'             => $title,
						'action'            => 'skipped',
						'mode'              => $price_mode,
						'price_summary'     => $price_summary,
						'new_terms_created' => $new_terms_created,
						'error'             => 'Insert failed: ' . $post_id->get_error_message(),
						'notes'             => '',
					];
				}
			}

			// Update post title/status only if changed
			if ( $changed['post_title'] || $changed['post_status'] ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				] );
			}

			// Terms
			$menus_result = self::apply_terms( $post_id, 'jprm_menu', $new['menu_terms'], $create_terms );
			$sects_result = self::apply_terms( $post_id, 'jprm_section', $new['sect_terms'], $create_terms );
			$new_terms_created['menus']    = $menus_result['created'];
			$new_terms_created['sections'] = $sects_result['created'];

			// Meta: description
			if ( $changed['desc'] ) {
				update_post_meta( $post_id, 'jprm_desc', $new['desc'] );
			}

			// Meta: visible (touch only if not set at all)
			$visible = get_post_meta( $post_id, 'jprm_visible', true );
			if ( $visible === '' ) {
				update_post_meta( $post_id, 'jprm_visible', 'yes' );
			}

			// Meta: badges
			if ( $changed['badges'] ) {
				update_post_meta( $post_id, 'jprm_item_badges', $new['badges'] );
			}

			// Meta: prices (EXACT KEYS)
			if ( $changed['prices'] ) {
				update_post_meta( $post_id, 'jprm_price_mode', $price_mode );

				if ( $price_mode === 'single' ) {
					update_post_meta( $post_id, 'jprm_price_amount', $new['prices']['amount_raw'] );
					update_post_meta( $post_id, 'jprm_price_label_mode', $new['prices']['label_mode'] );
					update_post_meta( $post_id, 'jprm_price_label_ref',  $new['prices']['label_ref'] );

					// Keep jprm_price in sync
					update_post_meta( $post_id, 'jprm_price', [
						'mode'      => 'single',
						'price'     => $new['prices']['amount_raw'],
						'label_ref' => $new['prices']['label_ref'],
					] );

					// Clear rows store
					delete_post_meta( $post_id, 'jprm_prices' );
				} else {
					$rows = (array) $new['prices']['rows'];

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
		}

		// Decide final action label (updated vs unchanged)
		if ( $is_existing && ! $changed['any'] ) {
			$action = 'unchanged';
		}

		// Notes
		$notes = [];
		if ( ! empty( $new_terms_created['menus'] ) ) {
			$notes[] = 'New Menus: ' . implode( ', ', $new_terms_created['menus'] );
		}
		if ( ! empty( $new_terms_created['sections'] ) ) {
			$notes[] = 'New Sections: ' . implode( ', ', $new_terms_created['sections'] );
		}

		return [
			'post_id_old'       => $existing_id,
			'post_id_new'       => $dry ? ( $is_existing ? $existing_id : 0 ) : ( $post_id ?: 0 ),
			'title'             => $title,
			'action'            => $action,
			'mode'              => $price_mode,
			'price_summary'     => $price_summary,
			'menus'             => $new['menu_terms'],
			'sections'          => $new['sect_terms'],
			'badges'            => $new['badges'],
			'new_terms_created' => $new_terms_created,
			'error'             => $error,
			'notes'             => implode( ' — ', $notes ),
		];
	}

	/* --------------------------- Helpers -------------------------- */

	private static function terms_as_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		return ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? [] : array_values( $terms );
	}

	private static function meta_badges( int $post_id ): array {
		$raw = get_post_meta( $post_id, 'jprm_item_badges', true );
		if ( is_array( $raw ) ) return array_values( $raw );
		if ( is_string( $raw ) && $raw !== '' && function_exists( 'is_serialized' ) && is_serialized( $raw ) ) {
			$un = @maybe_unserialize( $raw );
			return is_array( $un ) ? array_values( $un ) : [];
		}
		return [];
	}

	/**
	 * Apply terms; optionally create missing ones.
	 * Returns list of newly created term names and whether assignments changed.
	 *
	 * @return array{created: string[], changed: bool}
	 */
	private static function apply_terms( int $post_id, string $tax, array $names, bool $create ): array {
		$current = self::terms_as_names( $post_id, $tax );

		$targets   = [];
		$created   = [];
		foreach ( $names as $name ) {
			$name = (string) $name;
			if ( $name === '' ) continue;
			$term = get_term_by( 'name', $name, $tax );
			if ( ! $term && $create ) {
				$insert = wp_insert_term( $name, $tax );
				if ( ! is_wp_error( $insert ) ) {
					$term = get_term( (int) $insert['term_id'], $tax );
					$created[] = $name;
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$targets[] = (int) $term->term_id;
			}
		}

		// Normalize order (names array order is what we set; WordPress stores term order but we compare by names)
		sort( $current );
		$names_sorted = $names;
		sort( $names_sorted );

		$changed = ( $current !== $names_sorted );

		if ( $changed ) {
			wp_set_object_terms( $post_id, $targets, $tax, false );
		}

		return [ 'created' => $created, 'changed' => $changed ];
	}

	/**
	 * Build the prices payload strictly from the known keys (same shape as exporter).
	 */
	private static function build_prices_payload( int $post_id, string $mode ): array {
		$mode = $mode ?: 'single';

		if ( $mode === 'single' ) {
			$amount_raw  = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
			$label_mode  = (string) get_post_meta( $post_id, 'jprm_price_label_mode', true );
			$label_ref   = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );

			return [
				'mode'          => 'single',
				'amount_raw'    => $amount_raw,
				'amount_number' => self::to_float_eu_us( $amount_raw ),
				'label_mode'    => $label_mode,
				'label_ref'     => $label_ref,
			];
		}

		$rows = [];
		$raw_prices = get_post_meta( $post_id, 'jprm_prices', true );
		if ( is_array( $raw_prices ) ) {
			$rows = $raw_prices;
		} else {
			$raw_price = get_post_meta( $post_id, 'jprm_price', true );
			if ( is_array( $raw_price ) && isset( $raw_price['rows'] ) && is_array( $raw_price['rows'] ) ) {
				$rows = $raw_price['rows'];
			}
		}

		return [
			'mode' => 'multi',
			'rows' => $rows,
		];
	}

	/**
	 * Compare two normalized item states; return which top-level parts changed.
	 *
	 * @return array{any:bool,post_title:bool,post_status:bool,desc:bool,menu_terms:bool,sect_terms:bool,badges:bool,prices:bool}
	 */
	private static function diff_any( array $old, array $new ): array {
		$c = [
			'post_title'  => ( $old['post_title']  !== $new['post_title'] ),
			'post_status' => ( $old['post_status'] !== $new['post_status'] ),
			'desc'        => ( $old['desc']        !== $new['desc'] ),
			'menu_terms'  => ( self::sorted($old['menu_terms']) !== self::sorted($new['menu_terms']) ),
			'sect_terms'  => ( self::sorted($old['sect_terms']) !== self::sorted($new['sect_terms']) ),
			'badges'      => ( self::sorted($old['badges'])     !== self::sorted($new['badges']) ),
			'prices'      => ( self::normalize_prices($old['prices']) !== self::normalize_prices($new['prices']) ),
		];
		$c['any'] = (bool) array_sum( array_map( fn($v) => $v ? 1 : 0, $c ) );
		return $c;
	}

	private static function sorted( $a ): array {
		$a = is_array( $a ) ? $a : [];
		$a = array_values( $a );
		sort( $a );
		return $a;
	}

	private static function normalize_prices( $p ): array {
		if ( ! is_array( $p ) ) return [];
		if ( ( $p['mode'] ?? '' ) === 'single' ) {
			return [
				'mode'       => 'single',
				'amount_raw' => (string) ( $p['amount_raw'] ?? '' ),
				'label_mode' => (string) ( $p['label_mode'] ?? '' ),
				'label_ref'  => (string) ( $p['label_ref'] ?? '' ),
			];
		}
		$rows = isset( $p['rows'] ) && is_array( $p['rows'] ) ? $p['rows'] : [];
		return [ 'mode' => 'multi', 'rows' => array_values( $rows ) ];
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
}
