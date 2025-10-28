<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys Importer for JPRM (JSON/CSV from our exporter).
 * - Dry-run validation & report
 * - Commit writes: create/update posts, terms, meta
 * - Accurate change detection (multi-row compares whitelisted keys)
 * - Tracks missing Menu/Section terms (reports in dry-run; creates on commit if requested)
 * - Price summary per row
 */
final class JPRM_Importer {

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
			'items'     => [],
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
			$items = self::parse_csv_export( $raw, $report );
		} else {
			$t = ltrim( $raw );
			$items = ( $t !== '' && ($t[0] === '{' || $t[0] === '[') )
				? self::parse_json_export( $raw, $report )
				: self::parse_csv_export( $raw, $report );
		}

		if ( empty( $items ) ) {
			$report['errors'][] = 'No items found in file.';
			return $report;
		}

		foreach ( $items as $row ) {
			$r = self::process_item( $row, $dry_run, $create_missing_terms );

			if ( isset( $r['action'] ) ) {
				if ( $r['action'] === 'created' )      { $report['created']++; }
				elseif ( $r['action'] === 'updated' )  { $report['updated']++; }
				elseif ( $r['action'] === 'unchanged') { $report['unchanged']++; }
				elseif ( $r['action'] === 'skipped' )  { $report['skipped']++; }
			}
			if ( ! empty( $r['new_terms_created']['menus'] ) ) {
				$report['new_terms']['menus'] += count( $r['new_terms_created']['menus'] );
			}
			if ( ! empty( $r['new_terms_created']['sections'] ) ) {
				$report['new_terms']['sections'] += count( $r['new_terms_created']['sections'] );
			}
			if ( ! empty( $r['error'] ) ) { $report['errors'][] = $r['error']; }

			$report['items'][] = $r;
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

	private static function parse_csv_export( string $raw, array &$report ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $raw );
		if ( ! $lines ) { $report['errors'][] = 'Empty CSV.'; return []; }

		if ( isset( $lines[0] ) ) {
			$lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]); // BOM
		}

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
				if ( $idx !== null && isset( $vals[$idx] ) ) { $row[$h] = $vals[$idx]; }
			}
			$items_prices = json_decode( (string) ($row['prices_json'] ?? ''), true );
			if ( ! is_array( $items_prices ) ) { $items_prices = []; }

			$badges = array_filter( array_map( 'sanitize_title', explode( '|', (string) ( $row['badges'] ?? '' ) ) ) );

			$items_row = [
				'post_id'     => isset($row['post_id']) && $row['post_id'] !== '' ? (int) $row['post_id'] : 0,
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
		// Raw inputs (may be missing in the file)
		$title_in  = array_key_exists( 'post_title',  $it ) ? (string) $it['post_title']  : null;
		$status_in = array_key_exists( 'post_status', $it ) ? (string) $it['post_status'] : null;
		$desc_in   = array_key_exists( 'description', $it ) ? (string) $it['description'] : null;
		$tax       = is_array( $it['tax'] ?? null ) ? $it['tax'] : [ 'jprm_menu'=>[], 'jprm_section'=>[] ];
		$badges    = is_array( $it['badges'] ?? null ) ? $it['badges'] : [];
		$prices    = is_array( $it['prices'] ?? null ) ? $it['prices'] : [];

		// Treat missing/empty post_id as "new".
		$existing_id = 0;
		if ( isset( $it['post_id'] ) && $it['post_id'] !== '' ) {
			$existing_id = (int) $it['post_id'];
		}

		$post_id = 0;
		$action  = 'skipped';
		$error   = '';

		// Determine price mode
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

		// ---- Old state (normalize empties to '') ----
		$old = [
			'post_title'  => $is_existing ? (string) get_the_title( $post_id ) : '',
			'post_status' => $is_existing ? (string) get_post_status( $post_id ) : 'draft',
			'desc'        => $is_existing ? (string) get_post_meta( $post_id, 'jprm_desc', true ) : '',
			'menu_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_menu' ) : [],
			'sect_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_section' ) : [],
			'badges'      => $is_existing ? self::meta_badges( $post_id ) : [],
			'prices'      => $is_existing ? self::build_prices_payload( $post_id, (string) get_post_meta( $post_id, 'jprm_price_mode', true ) ) : [],
		];

		// For existing posts, inherit current DB values when the import omits a field.
		// For new posts, keep sane defaults (draft when status missing).
		$title_new  = $is_existing
			? ( ($title_in  === null || $title_in  === '') ? (string) $old['post_title']  : $title_in )
			: ( (string) ($title_in ?? '') );

		$status_new = $is_existing
			? ( ($status_in === null || $status_in === '') ? (string) $old['post_status'] : $status_in )
			: ( (string) ($status_in ?? 'draft') );

		$desc_new   = $is_existing
			? ( ($desc_in   === null || $desc_in   === '') ? (string) $old['desc']        : $desc_in )
			: ( (string) ($desc_in ?? '') );

		// Normalize taxonomy/badges
		$menus_new = array_values( array_filter( (array) ( $tax['jprm_menu']    ?? [] ) ) );
		$sects_new = array_values( array_filter( (array) ( $tax['jprm_section'] ?? [] ) ) );
		$badg_new  = array_values( array_map( 'sanitize_title', $badges ) );

		// Build NEW normalized state
		$new_rows = ( $price_mode === 'multi' ) ? (array) ( $prices['rows'] ?? [] ) : [];
		$new = [
			'post_title'  => $title_new,
			'post_status' => $status_new,
			'desc'        => $desc_new,
			'menu_terms'  => $menus_new,
			'sect_terms'  => $sects_new,
			'badges'      => $badg_new,
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
					'rows' => self::canonicalize_rows_whitelisted( $new_rows ),
				  ],
		];

		$price_summary = ( $new['prices']['mode'] === 'single' )
			? (string) ( $new['prices']['amount_raw'] ?? '' )
			: (string) ( count( (array) $new['prices']['rows'] ) . ' rows' );

		// Compute missing terms now (so dry-run reports “would create”)
		$missing_menus     = self::missing_term_names( 'jprm_menu', $new['menu_terms'] );
		$missing_sections  = self::missing_term_names( 'jprm_section', $new['sect_terms'] );
		$new_terms_created = [ 'menus' => $missing_menus, 'sections' => $missing_sections ];

		// Diff
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
						'title'             => $new['post_title'],
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
			self::assign_terms_if_changed( $post_id, 'jprm_menu', $new['menu_terms'] );
			self::assign_terms_if_changed( $post_id, 'jprm_section', $new['sect_terms'] );

			// Update post props only if user provided a different value.
			$need_update = false;
			$upd = [ 'ID' => $post_id ];

			// Title: update only if provided and different
			if ( $title_in !== null && $title_in !== '' && $changed['post_title'] ) {
				$upd['post_title'] = $new['post_title'];
				$need_update = true;
			}

			// Status: update only if provided and different
			if ( $status_in !== null && $status_in !== '' && $changed['post_status'] ) {
				$upd['post_status'] = $new['post_status'];
				$need_update = true;
			}

			if ( $need_update ) {
				wp_update_post( $upd );
			}

			// Meta: desc, visible, badges
			if ( $desc_in !== null && $changed['desc'] ) {
				update_post_meta( $post_id, 'jprm_desc', $new['desc'] );
			}
			if ( get_post_meta( $post_id, 'jprm_visible', true ) === '' ) {
				update_post_meta( $post_id, 'jprm_visible', 'yes' );
			}
			if ( $changed['badges'] ) {
				update_post_meta( $post_id, 'jprm_item_badges', $new['badges'] );
			}

			// Meta: prices
			if ( $changed['prices'] ) {
				update_post_meta( $post_id, 'jprm_price_mode', $price_mode );
				if ( $price_mode === 'single' ) {
					update_post_meta( $post_id, 'jprm_price_amount', $new['prices']['amount_raw'] );
					update_post_meta( $post_id, 'jprm_price_label_mode', $new['prices']['label_mode'] );
					update_post_meta( $post_id, 'jprm_price_label_ref',  $new['prices']['label_ref'] );

					update_post_meta( $post_id, 'jprm_price', [
						'mode'      => 'single',
						'price'     => $new['prices']['amount_raw'],
						'label_ref' => $new['prices']['label_ref'],
					] );

					delete_post_meta( $post_id, 'jprm_prices' );
				} else {
					$rows = (array) $new['prices']['rows'];
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
			// In dry-run we never have a new numeric ID; show old when updating, 0 when creating.
			'post_id_new'       => $dry ? ( $is_existing ? $existing_id : 0 ) : ( $post_id ?: 0 ),
			'title'             => $new['post_title'],
			'action'            => $action,
			'mode'              => $price_mode,
			'price_summary'     => $price_summary,
			'menus'             => $new['menu_terms'],
			'sections'          => $new['sect_terms'],
			'badges'            => $new['badges'],
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

	/** Assign terms if the set has changed. */
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
	 * Build prices payload for an existing post (same shape as exporter).
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
			'rows' => self::canonicalize_rows_whitelisted( $rows ),
		];
	}

	/**
	 * Canonicalize multi rows but only keep a whitelist of meaningful keys.
	 * Default whitelist: ['label_ref','amount'].
	 * - Treats 'price' as an alias of 'amount'
	 * - Normalizes numeric strings (EU/US) so "5,00" == "5.00" == 5
	 * Filter: 'jprm/import/row_compare_keys' to alter/extend.
	 */
	private static function canonicalize_rows_whitelisted( array $rows ): array {
		$keys = (array) apply_filters( 'jprm/import/row_compare_keys', [ 'label_ref', 'amount' ] );

		$norm = [];
		foreach ( $rows as $r ) {
			$a = is_array( $r ) ? $r : (array) $r;

			// Map 'price' -> 'amount' if present, so either field name works.
			if ( isset( $a['price'] ) && ! isset( $a['amount'] ) ) {
				$a['amount'] = $a['price'];
			}

			$clean = [];
			foreach ( $keys as $k ) {
				if ( $k === 'amount' ) {
					$clean['amount'] = self::normalize_amount_string( $a['amount'] ?? '' );
				} else {
					$clean[$k] = self::norm_scalar( $a[$k] ?? '' );
				}
			}

			ksort( $clean );
			$norm[] = $clean;
		}

		usort( $norm, function( $x, $y ) {
			$sx = json_encode( $x, JSON_UNESCAPED_UNICODE );
			$sy = json_encode( $y, JSON_UNESCAPED_UNICODE );
			return $sx <=> $sy;
		} );

		return array_values( $norm );
	}

	/** Normalize number-like strings to a canonical dot-decimal with 2 decimals (or '' if empty). */
	private static function normalize_amount_string( $v ): string {
		$v = self::norm_scalar( $v );
		if ( $v === '' ) return '';
		$f = self::to_float_eu_us( $v );
		if ( $f === null ) return $v; // leave as-is if not parseable; still stable
		return number_format( $f, 2, '.', '' );
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
		$c['any'] = (bool) array_sum( array_map( static fn($v) => $v ? 1 : 0, $c ) );
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
