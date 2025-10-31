<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys Importer for JPRM (JSON/CSV from our exporter/template).
 * - Dry-run validation & report
 * - Commit writes: create/update posts, terms, meta
 * - Accurate change detection (multi-row compares only whitelisted keys)
 * - Tracks newly created Menu/Section terms (and shows “would create” in dry-run)
 * - Keeps RAW price text (e.g., "9,00") when saving
 */
final class JPRM_Importer {

	public static function run( array $file, array $opts = [] ): array {
		$dry_run              = ! empty( $opts['dry_run'] );
		$create_missing_terms = ! empty( $opts['create_missing_terms'] );

		$report = [
			'dry_run'      => $dry_run,
			'created'      => 0,
			'updated'      => 0,
			'unchanged'    => 0,
			'skipped'      => 0,
			'errors'       => [],
			'new_terms'    => [ 'menus' => 0, 'sections' => 0, 'menus_list' => [], 'sections_list' => [] ],
			'would_create' => [ 'menus' => [], 'sections' => [] ],
			'items'        => [],
		];

		if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
			$report['errors'][] = 'Upload failed or file unreadable.';
			return $report;
		}

		$raw  = file_get_contents( $file['tmp_name'] );
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		$items = [];
		if ( $ext === 'json' ) {
			$items = self::parse_json_export( $raw, $report );
		} elseif ( $ext === 'csv' ) {
			$items = self::parse_csv_import_simple( $raw, $report );
		} else {
			$t = ltrim( $raw );
			$items = ( $t !== '' && ($t[0] === '{' || $t[0] === '[') )
				? self::parse_json_export( $raw, $report )
				: self::parse_csv_import_simple( $raw, $report );
		}

		if ( empty( $items ) ) {
			$report['errors'][] = 'No items found in file.';
			return $report;
		}

		$wc_menus    = [];
		$wc_sections = [];

		foreach ( $items as $row ) {
			$r = self::process_item( $row, $dry_run, $create_missing_terms );

			if ( isset( $r['action'] ) ) {
				if ( $r['action'] === 'created' )      { $report['created']++; }
				elseif ( $r['action'] === 'updated' )  { $report['updated']++; }
				elseif ( $r['action'] === 'unchanged') { $report['unchanged']++; }
				elseif ( $r['action'] === 'skipped' )  { $report['skipped']++; }
			}

			// Aggregate new terms created (commit mode) or "would create" (dry-run)
			if ( ! empty( $r['new_terms_created']['menus'] ) ) {
				$names = (array) $r['new_terms_created']['menus'];
				$report['new_terms']['menus']       += count( $names );
				$report['new_terms']['menus_list']   = array_values( array_unique( array_merge( $report['new_terms']['menus_list'], $names ) ) );
			}
			if ( ! empty( $r['new_terms_created']['sections'] ) ) {
				$names = (array) $r['new_terms_created']['sections'];
				$report['new_terms']['sections']       += count( $names );
				$report['new_terms']['sections_list']   = array_values( array_unique( array_merge( $report['new_terms']['sections_list'], $names ) ) );
			}

			// For a better dry-run overview
			if ( ! empty( $r['missing']['menus'] ) )    { $wc_menus    = array_merge( $wc_menus,    (array) $r['missing']['menus'] ); }
			if ( ! empty( $r['missing']['sections'] ) ) { $wc_sections = array_merge( $wc_sections, (array) $r['missing']['sections'] ); }

			if ( ! empty( $r['error'] ) ) { $report['errors'][] = $r['error']; }
			$report['items'][] = $r;
		}

		if ( $dry_run ) {
			$report['would_create']['menus']    = array_values( array_unique( $wc_menus ) );
			$report['would_create']['sections'] = array_values( array_unique( $wc_sections ) );
		}

		return $report;
	}

	/* ---------------------------- Parsers ---------------------------- */

	private static function parse_json_export( string $raw, array &$report ): array {
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) { $report['errors'][] = 'Invalid JSON.'; return []; }
		if ( isset( $dec['items'] ) && is_array( $dec['items'] ) ) { return $dec['items']; }
		if ( isset( $dec[0] ) && is_array( $dec[0] ) ) { return $dec; }
		$report['errors'][] = 'JSON did not contain an items array.';
		return [];
	}

	/**
	 * CSV “simple” import:
	 * Optional first line: "sep=;" or "sep=," (Excel hint).
	 * Headers: post_id, post_title, post_status, description, menus, sections, Price_Single, Price_Multiple
	 * - menus/sections: pipe-separated names
	 * - Price_Multiple: values separated by "*", max 4
	 */
	private static function parse_csv_import_simple( string $raw, array &$report ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $raw );
		if ( ! $lines ) { $report['errors'][] = 'Empty CSV.'; return []; }

		// Strip BOM from first line if present
		if ( isset( $lines[0] ) ) {
			$lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
		}

		// Excel delimiter hint "sep=;"
		$delimiter = null;
		if ( isset( $lines[0] ) && preg_match( '/^\s*sep\s*=\s*([;,])\s*$/i', $lines[0], $m ) ) {
			$delimiter = $m[1];
			array_shift( $lines ); // remove the sep= line
		}

		// Fallback auto-detect if no sep= found
		$first = $lines[0] ?? '';
		$delimiter = $delimiter ?? ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',' );

		$headers = str_getcsv( $first, $delimiter );
		$map     = array_flip( $headers );
		$rows    = [];

		for ( $i = 1; $i < count( $lines ); $i++ ) {
			if ( trim( $lines[$i] ) === '' ) continue;
			$vals = str_getcsv( $lines[$i], $delimiter );

			$row = array_fill_keys( $headers, '' );
			foreach ( $headers as $h ) {
				$idx = $map[$h] ?? null;
				if ( $idx !== null && isset( $vals[$idx] ) ) { $row[$h] = $vals[$idx]; }
			}

			$menus    = array_filter( array_map( 'trim', explode( '|', (string) ( $row['menus']    ?? '' ) ) ) );
			$sections = array_filter( array_map( 'trim', explode( '|', (string) ( $row['sections'] ?? '' ) ) ) );

			$single   = trim( (string) ( $row['Price_Single']   ?? '' ) );
			$multi_in = trim( (string) ( $row['Price_Multiple'] ?? '' ) );

			// Build prices structure (labels intentionally ignored for simplicity)
			$prices = [];
			if ( $multi_in !== '' ) {
				$parts = array_filter( array_map( 'trim', explode( '*', $multi_in ) ) );
				$parts = array_slice( $parts, 0, 4 ); // max 4
				$save_rows = [];
				foreach ( $parts as $p ) {
					// Store exactly what the user typed under 'value' (not normalized)
					$save_rows[] = [
						'label_ref' => '',
						'value'     => (string) $p,
					];
				}
				$prices = [ 'mode' => 'multi', 'rows' => $save_rows ];
			} elseif ( $single !== '' ) {
				$prices = [
					'mode'          => 'single',
					'amount_raw'    => (string) $single, // keep raw string (e.g., "9,00")
					'amount_number' => null,
					'label_mode'    => '',
					'label_ref'     => '',
				];
			} else {
				$prices = [ 'mode' => 'single', 'amount_raw' => '' ];
			}

			$rows[] = [
				'post_id'     => isset($row['post_id']) && $row['post_id'] !== '' ? (int) $row['post_id'] : 0,
				'post_title'  => (string) ( $row['post_title']  ?? '' ),
				'post_status' => (string) ( $row['post_status'] ?? 'draft' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'tax'         => [
					'jprm_menu'    => $menus,
					'jprm_section' => $sections,
				],
				'badges'      => [],
				'prices'      => $prices,
			];
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

		// Treat missing/empty post_id as "new".
		$existing_id = 0;
		if ( isset( $it['post_id'] ) && $it['post_id'] !== '' ) {
			$existing_id = (int) $it['post_id'];
		}

		$post_id = 0;
		$action  = 'skipped';
		$error   = '';

		$price_mode = (string) ( $prices['mode'] ?? '' );
		if ( $price_mode !== 'single' && $price_mode !== 'multi' ) {
			$price_mode = isset( $prices['rows'] ) ? 'multi' : 'single';
		}

		$is_existing = ( $existing_id > 0 && get_post_type( $existing_id ) === 'jprm_menu_item' );
		if ( $is_existing ) {
			$post_id = $existing_id;
			$action  = 'updated'; // may become 'unchanged'
		} else {
			$action  = 'created';
		}

		// ---- Old state ----
		$old = [
			'post_title'  => $is_existing ? (string) get_the_title( $post_id ) : '',
			'post_status' => $is_existing ? (string) get_post_status( $post_id ) : 'draft',
			'desc'        => $is_existing ? (string) get_post_meta( $post_id, 'jprm_desc', true ) : '',
			'menu_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_menu' ) : [],
			'sect_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_section' ) : [],
			'badges'      => $is_existing ? self::meta_badges( $post_id ) : [],
			'prices'      => $is_existing ? self::build_prices_payload( $post_id, (string) get_post_meta( $post_id, 'jprm_price_mode', true ) ) : [],
		];

		// ---- New state (RAW, keep user-entered values) ----
		$new_menu_terms = array_values( array_filter( (array) ( $tax['jprm_menu'] ?? [] ) ) );
		$new_sect_terms = array_values( array_filter( (array) ( $tax['jprm_section'] ?? [] ) ) );

		$new_rows_raw = [];
		if ( $price_mode === 'multi' ) {
			$in_rows = is_array( $prices['rows'] ?? null ) ? $prices['rows'] : [];
			foreach ( $in_rows as $r ) {
				$a = is_array( $r ) ? $r : (array) $r;
				$val = '';
				if ( isset( $a['value'] ) )        $val = (string) $a['value'];
				elseif ( isset( $a['amount'] ) )   $val = (string) $a['amount'];
				elseif ( isset( $a['price'] ) )    $val = (string) $a['price'];
				$new_rows_raw[] = [
					'label_ref' => (string) ( $a['label_ref'] ?? '' ),
					'value'     => $val, // RAW
					'hide_icon' => (bool) ( $a['hide_icon'] ?? false ),
				];
			}
		}

		$new = [
			'post_title'  => $title,
			'post_status' => $status ?: 'draft',
			'desc'        => $desc,
			'menu_terms'  => $new_menu_terms,
			'sect_terms'  => $new_sect_terms,
			'badges'      => array_values( array_map( 'sanitize_title', $badges ) ),
			'prices'      => ( $price_mode === 'single' )
				? [
					'mode'          => 'single',
					'amount_raw'    => (string) ( $prices['amount_raw'] ?? '' ), // RAW, keep commas and zeros
					'amount_number' => isset( $prices['amount_raw'] ) ? self::to_float_eu_us( $prices['amount_raw'] ) : null,
					'label_mode'    => (string) ( $prices['label_mode'] ?? '' ),
					'label_ref'     => (string) ( $prices['label_ref'] ?? '' ),
				  ]
				: [
					'mode' => 'multi',
					'rows' => $new_rows_raw, // RAW 'value'
				  ],
		];

		$price_summary = ( $new['prices']['mode'] === 'single' )
			? (string) ( $new['prices']['amount_raw'] ?? '' )
			: (string) ( count( (array) $new['prices']['rows'] ) . ' rows' );

		// Missing terms now (so dry-run reports “would create”)
		$missing_menus     = self::missing_term_names( 'jprm_menu', $new['menu_terms'] );
		$missing_sections  = self::missing_term_names( 'jprm_section', $new['sect_terms'] );
		$new_terms_created = [ 'menus' => $missing_menus, 'sections' => $missing_sections ];

		// Diff (uses canonical & whitelist comparison)
		$changed = self::diff_any( $old, $new );

		// Writes only if changed & not dry-run
		if ( ! $dry ) {
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
						'menus'             => $new['menu_terms'],
						'sections'          => $new['sect_terms'],
						'badges'            => $new['badges'],
						'new_terms_created' => $new_terms_created,
						'error'             => 'Insert failed: ' . $post_id->get_error_message(),
					];
				}
			}

			// Create missing terms if asked; capture actually created names
			$created_m = [];
			$created_s = [];
			if ( $create_missing_terms ) {
				$created_m = self::ensure_terms_exist( 'jprm_menu', $missing_menus );
				$created_s = self::ensure_terms_exist( 'jprm_section', $missing_sections );
			}

			// Assign terms only if set has changed
			self::assign_terms_if_changed( $post_id, 'jprm_menu',    $new['menu_terms'] );
			self::assign_terms_if_changed( $post_id, 'jprm_section', $new['sect_terms'] );

			// Update post props if changed
			if ( $changed['post_title'] || $changed['post_status'] ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				] );
			}

			// Meta: desc, visible, badges
			if ( $changed['desc'] ) {
				update_post_meta( $post_id, 'jprm_desc', $new['desc'] );
			}
			if ( get_post_meta( $post_id, 'jprm_visible', true ) === '' ) {
				update_post_meta( $post_id, 'jprm_visible', 'yes' );
			}
			if ( $changed['badges'] ) {
				update_post_meta( $post_id, 'jprm_item_badges', $new['badges'] );
			}

			// Meta: prices (SAVE RAW)
			if ( $changed['prices'] ) {
				update_post_meta( $post_id, 'jprm_price_mode', $price_mode );
				if ( $price_mode === 'single' ) {
					update_post_meta( $post_id, 'jprm_price_amount', $new['prices']['amount_raw'] );
					update_post_meta( $post_id, 'jprm_price_label_mode', $new['prices']['label_mode'] );
					update_post_meta( $post_id, 'jprm_price_label_ref',  $new['prices']['label_ref'] );

					update_post_meta( $post_id, 'jprm_price', [
						'mode'      => 'single',
						'price'     => $new['prices']['amount_raw'], // RAW
						'label_ref' => $new['prices']['label_ref'],
					] );

					delete_post_meta( $post_id, 'jprm_prices' );
				} else {
					$rows = (array) $new['prices']['rows']; // RAW with 'value'
					update_post_meta( $post_id, 'jprm_prices', $rows );
					update_post_meta( $post_id, 'jprm_price', [ 'mode' => 'multi', 'rows' => $rows ] );

					delete_post_meta( $post_id, 'jprm_price_amount' );
					delete_post_meta( $post_id, 'jprm_price_label_mode' );
					delete_post_meta( $post_id, 'jprm_price_label_ref' );
				}
			}

			// If we actually created terms, reflect that (commit mode)
			if ( $create_missing_terms ) {
				$new_terms_created['menus']    = $created_m;
				$new_terms_created['sections'] = $created_s;
			}
		}

		// Final action
		if ( $is_existing && ! $changed['any'] ) {
			$action = 'unchanged';
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
			'missing'           => [ 'menus' => $missing_menus, 'sections' => $missing_sections ],
			'new_terms_created' => $new_terms_created,
			'error'             => $error,
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

	/** Which names from $names do NOT exist in $taxonomy yet? */
	private static function missing_term_names( string $tax, array $names ): array {
		$missing = [];
		foreach ( $names as $name ) {
			if ( $name === '' ) continue;
			$term = get_term_by( 'name', $name, $tax );
			if ( ! $term || is_wp_error( $term ) ) {
				$missing[] = $name;
			}
		}
		return $missing;
	}

	/** Create terms by name (returns list of successfully created names). */
	private static function ensure_terms_exist( string $tax, array $names ): array {
		$created = [];
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) { continue; }
			$ins = wp_insert_term( $name, $tax );
			if ( ! is_wp_error( $ins ) ) {
				$created[] = $name;
			}
		}
		return $created;
	}

	/** Assign terms if the set has changed (names -> IDs). */
	private static function assign_terms_if_changed( int $post_id, string $tax, array $target_names ): void {
		$current = self::terms_as_names( $post_id, $tax );
		$cn = $current; sort( $cn );
		$tn = $target_names; sort( $tn );
		if ( $cn === $tn ) { return; }

		$ids = [];
		foreach ( $target_names as $name ) {
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $post_id, $ids, $tax, false );
	}

	/**
	 * Build prices payload for an existing post (same shape the exporter/template expects).
	 * Uses ONLY 'jprm_price' (and jprm_prices/jprm_price_* for backwards-compat).
	 * Keeps stored values RAW (e.g., commas, trailing zeros).
	 */
	private static function build_prices_payload( int $post_id, string $mode ): array {
		$mode = $mode ?: 'single';

		if ( $mode === 'single' ) {
			$amount_raw  = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
			$label_mode  = (string) get_post_meta( $post_id, 'jprm_price_label_mode', true );
			$label_ref   = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );

			// Fallback to 'jprm_price' if needed
			if ( $amount_raw === '' ) {
				$p = get_post_meta( $post_id, 'jprm_price', true );
				if ( is_array( $p ) && isset( $p['price'] ) ) $amount_raw = (string) $p['price'];
			}

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
		$raw_prices_rows = get_post_meta( $post_id, 'jprm_prices', true ); // legacy array of rows
		if ( is_array( $raw_prices_rows ) ) {
			$rows = $raw_prices_rows;
		} else {
			$raw_price = get_post_meta( $post_id, 'jprm_price', true );
			if ( is_array( $raw_price ) && isset( $raw_price['rows'] ) && is_array( $raw_price['rows'] ) ) {
				$rows = $raw_price['rows'];
			}
		}

		// Ensure rows have 'value' for UI/list table; accept aliases for old data
		$rows_out = [];
		foreach ( (array) $rows as $r ) {
			$a = is_array( $r ) ? $r : (array) $r;
			$val = '';
			if ( isset( $a['value'] ) )        $val = (string) $a['value'];
			elseif ( isset( $a['amount'] ) )   $val = (string) $a['amount'];
			elseif ( isset( $a['price'] ) )    $val = (string) $a['price'];
			$rows_out[] = [
				'label_ref' => (string) ( $a['label_ref'] ?? '' ),
				'value'     => $val,
				'hide_icon' => (bool) ( $a['hide_icon'] ?? false ),
			];
		}

		return [
			'mode' => 'multi',
			'rows' => $rows_out,
		];
	}

	/**
	 * Canonicalize multi rows but only keep a whitelist of meaningful keys.
	 * Default whitelist: ['label_ref','amount'].
	 * - Treats 'value'/'price' as aliases of 'amount' for comparison only
	 * - Normalizes numeric strings (EU/US) so "5,00" == "5.00" == 5
	 */
	private static function canonicalize_rows_whitelisted( array $rows ): array {
		$keys = (array) apply_filters( 'jprm/import/row_compare_keys', [ 'label_ref', 'amount' ] );

		$norm = [];
		foreach ( $rows as $r ) {
			$a = is_array( $r ) ? $r : (array) $r;

			// Map aliases to 'amount' for comparison only
			if ( isset( $a['value'] ) && ! isset( $a['amount'] ) )  { $a['amount'] = $a['value']; }
			if ( isset( $a['price'] ) && ! isset( $a['amount'] ) )  { $a['amount'] = $a['price']; }

			$clean = [];
			foreach ( $keys as $k ) {
				if ( $k === 'amount' ) {
					$clean['amount'] = self::normalize_amount_string( $a['amount'] ?? '' );
					continue;
				}
				$clean[$k] = self::norm_scalar( $a[$k] ?? '' );
			}

			ksort( $clean );
			$norm[] = $clean;
		}

		// Stable sort rows by JSON signature
		usort( $norm, function( $x, $y ) {
			$sx = json_encode( $x, JSON_UNESCAPED_UNICODE );
			$sy = json_encode( $y, JSON_UNESCAPED_UNICODE );
			return $sx <=> $sy;
		} );

		return array_values( $norm );
	}

	/** Normalize number-like strings to a canonical dot-decimal for comparison, or '' if empty. */
	private static function normalize_amount_string( $v ): string {
		$v = self::norm_scalar( $v );
		if ( $v === '' ) return '';
		$f = self::to_float_eu_us( $v );
		if ( $f === null ) return $v; // leave as-is if not parseable; still stable
		$s = number_format( $f, 2, '.', '' );
		if ( substr($s, -3) === '.00' ) {
			return substr($s, 0, -3);
		}
		return $s;
	}

	private static function norm_scalar( $v ): string {
		if ( is_null( $v ) ) return '';
		if ( is_bool( $v ) ) return $v ? '1' : '0';
		if ( is_numeric( $v ) ) return (string) $v;
		return trim( (string) $v );
	}

	/**
	 * Compare two normalized item states.
	 */
	private static function diff_any( array $old, array $new ): array {
		$c = [
			'post_title'  => ( self::eqs($old['post_title'])  !== self::eqs($new['post_title']) ),
			'post_status' => ( self::eqs($old['post_status']) !== self::eqs($new['post_status']) ),
			'desc'        => ( self::eqs($old['desc'])        !== self::eqs($new['desc']) ),
			'menu_terms'  => ( self::sorted($old['menu_terms']) !== self::sorted($new['menu_terms']) ),
			'sect_terms'  => ( self::sorted($old['sect_terms']) !== self::sorted($new['sect_terms']) ),
			'badges'      => ( self::sorted($old['badges'])     !== self::sorted($new['badges']) ),
			'prices'      => ( self::normalize_prices($old['prices']) !== self::normalize_prices($new['prices']) ),
		];
		$c['any'] = (bool) array_sum( array_map( fn($v) => $v ? 1 : 0, $c ) );
		return $c;
	}

	private static function eqs( $s ): string {
		return trim( is_string( $s ) ? $s : (string) $s );
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
				'amount_raw' => self::eqs( $p['amount_raw'] ?? '' ),
				'label_mode' => self::eqs( $p['label_mode'] ?? '' ),
				'label_ref'  => self::eqs( $p['label_ref'] ?? '' ),
			];
		}
		$rows = isset( $p['rows'] ) && is_array( $p['rows'] ) ? $p['rows'] : [];
		// Only canonicalize for comparison; do not affect saved values
		return [ 'mode' => 'multi', 'rows' => self::canonicalize_rows_whitelisted( $rows ) ];
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
