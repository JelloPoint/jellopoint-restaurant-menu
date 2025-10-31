<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-keys Importer for JPRM (JSON/CSV from our exporter).
 * - Dry-run validation & report
 * - Commit writes: create/update posts, terms, meta
 * - Accurate change detection (multi-row compares only whitelisted keys)
 * - Tracks newly created Menu/Section terms (and shows “would create” in dry-run)
 * - Ensures new Sections get their owner Menu via term meta _jprm_menu_term_id
 * - Always writes canonical jprm_price (and jprm_prices) meta for list-table rendering
 */
final class JPRM_Importer {

	public static function run( array $file, array $opts = [] ): array {
		$dry_run              = ! empty( $opts['dry_run'] );
		$create_missing_terms = ! empty( $opts['create_missing_terms'] );
		$ignore_ids           = ! empty( $opts['ignore_ids'] ); // NEW: always create new items if set

		$report = [
			'dry_run'   => $dry_run,
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
			'errors'    => [],
			'new_terms' => [
				'menus'          => 0,
				'sections'       => 0,
				'menus_list'     => [],
				'sections_list'  => [],
			],
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
			$r = self::process_item( $row, $dry_run, $create_missing_terms, $ignore_ids );

			if ( isset( $r['action'] ) ) {
				if ( $r['action'] === 'created' )      { $report['created']++; }
				elseif ( $r['action'] === 'updated' )  { $report['updated']++; }
				elseif ( $r['action'] === 'unchanged') { $report['unchanged']++; }
				elseif ( $r['action'] === 'skipped' )  { $report['skipped']++; }
			}
			if ( ! empty( $r['new_terms_created']['menus'] ) ) {
				$names = array_values( $r['new_terms_created']['menus'] );
				$report['new_terms']['menus'] += count( $names );
				$report['new_terms']['menus_list'] = array_values( array_unique( array_merge( $report['new_terms']['menus_list'], $names ) ) );
			}
			if ( ! empty( $r['new_terms_created']['sections'] ) ) {
				$names = array_values( $r['new_terms_created']['sections'] );
				$report['new_terms']['sections'] += count( $names );
				$report['new_terms']['sections_list'] = array_values( array_unique( array_merge( $report['new_terms']['sections_list'], $names ) ) );
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

			$badges = array_filter( array_map( 'sanitize_title', explode( '|', (string) ( $row['badges'] ?? '' ) ) ) );

			// Prices from the “template” shape
			$price_mode = (string) ( $row['price_mode'] ?? '' );
			$price_single = [
				'amount_raw'    => (string) ( $row['price_single_amount'] ?? '' ),
				'amount_number' => self::to_float_eu_us( (string) ( $row['price_single_amount'] ?? '' ) ),
				'label_mode'    => 'ref',
				'label_ref'     => (string) ( $row['price_single_label_ref'] ?? '' ),
			];
			$m_rows = [];
			for ( $k = 1; $k <= 4; $k++ ) {
				$amt = (string) ( $row["price_m{$k}_amount"] ?? '' );
				$ref = (string) ( $row["price_m{$k}_label_ref"] ?? '' );
				if ( $amt === '' && $ref === '' ) continue;
				$m_rows[] = [
					'enabled'     => true,
					'label_mode'  => 'ref',
					'label_ref'   => $ref,
					'label_custom'=> '',
					'icon_id'     => 0,
					'amount'      => $amt,
					'hide_icon'   => false,
				];
			}

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
				'prices'      => ( $price_mode === 'multi'
					? [ 'mode' => 'multi', 'rows' => $m_rows ]
					: [ 'mode' => 'single' ] + $price_single
				),
			];
			$rows[] = $items_row;
		}

		return $rows;
	}

	/* --------------------------- Processing -------------------------- */

	private static function process_item( array $it, bool $dry, bool $create_terms, bool $ignore_ids ): array {
		$title   = (string) ( $it['post_title'] ?? '' );
		$status  = (string) ( $it['post_status'] ?? 'draft' );
		$desc    = (string) ( $it['description'] ?? '' );
		$tax     = is_array( $it['tax'] ?? null ) ? $it['tax'] : [ 'jprm_menu'=>[], 'jprm_section'=>[] ];
		$badges  = is_array( $it['badges'] ?? null ) ? $it['badges'] : [];
		$prices  = is_array( $it['prices'] ?? null ) ? $it['prices'] : [];

		// Treat missing/empty post_id as "new", and also "new" if ignore_ids
		$existing_id = 0;
		if ( ! $ignore_ids && isset( $it['post_id'] ) && $it['post_id'] !== '' ) {
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

		// ---- New state (normalized like exporter) ----
		$new_rows = ( $price_mode === 'multi' ) ? (array) ( $prices['rows'] ?? [] ) : [];
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

		/* -------------------- Writes -------------------- */
		if ( ! $dry ) {
			// Create post if needed
			if ( ! $is_existing ) {
				$post_id = wp_insert_post( [
					'post_type'   => 'jprm_menu_item',
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				], true );
				if ( is_wp_error( $post_id ) ) {
					return self::result_row( $existing_id, 0, $title, 'skipped', $price_mode, $price_summary, $new, $new_terms_created, 'Insert failed: ' . $post_id->get_error_message() );
				}
			}

			// Create missing terms (and capture their IDs) if asked
			$created_m = $missing_menus;
			$created_s = $missing_sections;
			$menu_ids  = [];
			$sect_ids  = [];

			if ( $create_terms ) {
				$menu_ids = self::ensure_terms_return_ids( 'jprm_menu', $new['menu_terms'] );
				$sect_ids = self::ensure_terms_return_ids( 'jprm_section', $new['sect_terms'] );

				// Link each NEW section to the first available menu via _jprm_menu_term_id
				$owner_menu_id = $menu_ids ? (int) reset( $menu_ids ) : 0;
				if ( $owner_menu_id ) {
					foreach ( $sect_ids as $sid ) {
						// Only set if missing to avoid overriding existing structure
						if ( ! get_term_meta( $sid, '_jprm_menu_term_id', true ) ) {
							update_term_meta( $sid, '_jprm_menu_term_id', $owner_menu_id );
						}
					}
				}
			}

			// Assign tax terms (names → IDs mapping done inside)
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

			// Meta: prices — ALWAYS write canonical jprm_price for list-table visibility
			update_post_meta( $post_id, 'jprm_price_mode', $price_mode );
			if ( $price_mode === 'single' ) {
				update_post_meta( $post_id, 'jprm_price_amount', $new['prices']['amount_raw'] );
				update_post_meta( $post_id, 'jprm_price_label_mode', $new['prices']['label_mode'] );
				update_post_meta( $post_id, 'jprm_price_label_ref',  $new['prices']['label_ref'] );

				update_post_meta( $post_id, 'jprm_price', [
					'mode'      => 'single',
					'price'     => (string) $new['prices']['amount_raw'],
					'label_ref' => (string) $new['prices']['label_ref'],
				] );

				delete_post_meta( $post_id, 'jprm_prices' );
			} else {
				$rows = (array) $new['prices']['rows'];
				// Normalize to the UI’s expected shape: label_ref + value
				$normalized = [];
				foreach ( $rows as $r ) {
					$normalized[] = [
						'label_ref' => isset( $r['label_ref'] ) ? (string) $r['label_ref'] : '',
						'value'     => isset( $r['amount'] ) ? (string) $r['amount'] : (string) ( $r['value'] ?? '' ),
						'hide_icon' => (bool) ( $r['hide_icon'] ?? false ),
					];
				}
				update_post_meta( $post_id, 'jprm_prices', $normalized );
				update_post_meta( $post_id, 'jprm_price', [ 'mode' => 'multi', 'rows' => $normalized ] );

				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_mode' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
			}

			// Reflect actually created names (commit mode)
			if ( $create_terms ) {
				$new_terms_created['menus']    = $created_m;
				$new_terms_created['sections'] = $created_s;
			}
		}

		// Final action
		if ( $is_existing && ! $changed['any'] ) {
			$action = 'unchanged';
		}

		return self::result_row( $existing_id, $dry ? ( $is_existing ? $existing_id : 0 ) : ( $post_id ?: 0 ),
			$title, $action, $price_mode, $price_summary, $new, $new_terms_created, $error );
	}

	private static function result_row( $old_id, $new_id, $title, $action, $mode, $price_summary, $new, $new_terms_created, $error ) : array {
		return [
			'post_id_old'       => $old_id,
			'post_id_new'       => $new_id,
			'title'             => $title,
			'action'            => $action,
			'mode'              => $mode,
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

	/** Create terms if missing and return their IDs (existing ones included). */
	private static function ensure_terms_return_ids( string $tax, array $names ): array {
		$ids = [];
		foreach ( $names as $name ) {
			if ( $name === '' ) continue;
			$t = get_term_by( 'name', $name, $tax );
			if ( ! $t || is_wp_error( $t ) ) {
				$ins = wp_insert_term( $name, $tax );
				if ( ! is_wp_error( $ins ) && ! empty( $ins['term_id'] ) ) {
					$ids[] = (int) $ins['term_id'];
				}
			} else {
				$ids[] = (int) $t->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
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
	 * Canonicalize multi rows keeping only whitelisted keys (defaults: label_ref, amount).
	 * - Maps 'price' -> 'amount'
	 * - Normalizes numbers so "5,00" == "5.00"
	 */
	private static function canonicalize_rows_whitelisted( array $rows ): array {
		$keys = (array) apply_filters( 'jprm/import/row_compare_keys', [ 'label_ref', 'amount' ] );

		$norm = [];
		foreach ( $rows as $r ) {
			$a = is_array( $r ) ? $r : (array) $r;

			if ( isset( $a['price'] ) && ! isset( $a['amount'] ) ) {
				$a['amount'] = $a['price'];
			}

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

		usort( $norm, function( $x, $y ) {
			$sx = json_encode( $x, JSON_UNESCAPED_UNICODE );
			$sy = json_encode( $y, JSON_UNESCAPED_UNICODE );
			return $sx <=> $sy;
		} );

		return array_values( $norm );
	}

	private static function normalize_amount_string( $v ): string {
		$v = self::norm_scalar( $v );
		if ( $v === '' ) return '';
		$f = self::to_float_eu_us( $v );
		if ( $f === null ) return $v;
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
