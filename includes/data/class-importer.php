<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Importer — STRICT CSV/JSON (no guessing).
 *
 * CSV template (exact headers; any order; delimiter ',' or ';' auto-detected):
 *   post_id, post_title, post_status, description, menus, sections, Price_Single, Price_Multiple
 *
 * Rules:
 * - Exactly one of Price_Single OR Price_Multiple must be filled for each row.
 * - Price_Multiple must be '*' separated: e.g. "2,50*5,00*7,50".
 * - Amount strings are stored EXACTLY as provided (no normalization/guessing).
 * - If create_missing_terms=1, missing Menus/Sections are created.
 * - New/Existing Sections get `_jprm_menu_term_id` set to the first Menu in the row (if any)
 *   and a sequential `_jprm_section_order` is assigned if missing.
 */
final class JPRM_Importer {

	/** Per-run counters: next section order per owner-menu term_id. */
	private static array $section_order_seq = []; // [ menu_term_id => next_int ]

	public static function run( array $file, array $opts = [] ): array {
		$dry_run              = ! empty( $opts['dry_run'] );
		$create_missing_terms = ! empty( $opts['create_missing_terms'] );
		$ignore_ids           = ! empty( $opts['ignore_ids'] ); // when true, always create new posts

		// reset per-run state
		self::$section_order_seq = [];

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
			$items = self::parse_csv_strict( $raw, $report );
		} else {
			// Fallback: sniff by first char
			$t = ltrim( $raw );
			$items = ( $t !== '' && ($t[0] === '{' || $t[0] === '[') )
				? self::parse_json_export( $raw, $report )
				: self::parse_csv_strict( $raw, $report );
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

	/**
	 * STRICT CSV parser: only fixed headers accepted, no synonyms.
	 */
	private static function parse_csv_strict( string $raw, array &$report ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $raw );
		if ( ! $lines ) { $report['errors'][] = 'Empty CSV.'; return []; }

		// Strip BOM if present
		if ( isset( $lines[0] ) ) {
			$lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
		}

		$first     = $lines[0] ?? '';
		$delimiter = ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ) ? ';' : ',';

		$headers = str_getcsv( $first, $delimiter );

		// Required header set (case-sensitive)
		$required = [
			'post_id','post_title','post_status','description','menus','sections','Price_Single','Price_Multiple'
		];
		$missing  = array_diff( $required, $headers );
		if ( $missing ) {
			$report['errors'][] = 'CSV missing required header(s): ' . implode(', ', $missing);
			return [];
		}

		$map  = array_flip( $headers );
		$rows = [];

		for ( $i = 1; $i < count( $lines ); $i++ ) {
			if ( trim( $lines[$i] ) === '' ) continue;
			$vals = str_getcsv( $lines[$i], $delimiter );

			// Build associative row by headers exactly
			$row = array_fill_keys( $headers, '' );
			foreach ( $headers as $h ) {
				$idx = $map[$h] ?? null;
				if ( $idx !== null && isset( $vals[$idx] ) ) { $row[$h] = $vals[$idx]; }
			}

			$post_id     = isset($row['post_id']) && $row['post_id'] !== '' ? (int) $row['post_id'] : 0;
			$post_title  = (string) ( $row['post_title'] ?? '' );
			$post_status = (string) ( $row['post_status'] ?? 'draft' );
			$description = (string) ( $row['description'] ?? '' );

			$menus    = self::split_pipe( (string) ( $row['menus'] ?? '' ) );
			$sections = self::split_pipe( (string) ( $row['sections'] ?? '' ) );

			$price_single   = (string) ( $row['Price_Single'] ?? '' );
			$price_multiple = (string) ( $row['Price_Multiple'] ?? '' );

			// STRICT validation: exactly one of the two must be filled
			if ( $price_single !== '' && $price_multiple !== '' ) {
				$rows[] = [
					'_row_error' => 'Both Price_Single and Price_Multiple provided — only one allowed.',
					'post_id'     => $post_id,
					'post_title'  => $post_title,
					'post_status' => $post_status,
				];
				continue;
			}
			if ( $price_single === '' && $price_multiple === '' ) {
				$rows[] = [
					'_row_error' => 'No price provided — fill Price_Single or Price_Multiple.',
					'post_id'     => $post_id,
					'post_title'  => $post_title,
					'post_status' => $post_status,
				];
				continue;
			}

			// Build strict prices payload (no normalization, keep strings exactly as in CSV)
			if ( $price_single !== '' ) {
				$prices = [
					'mode'          => 'single',
					'amount_raw'    => $price_single,
					'amount_number' => null, // intentionally not guessed
					'label_mode'    => 'ref',
					'label_ref'     => '',
				];
			} else {
				$parts = array_map( 'trim', explode( '*', $price_multiple ) );
				$parts = array_values( array_filter( $parts, static fn($s) => $s !== '' ) );

				if ( empty( $parts ) ) {
					$rows[] = [
						'_row_error' => 'Price_Multiple contained no amounts (use * to separate, e.g. "2,50*5,00").',
						'post_id'     => $post_id,
						'post_title'  => $post_title,
						'post_status' => $post_status,
					];
					continue;
				}

				$m_rows = [];
				foreach ( $parts as $p ) {
					$m_rows[] = [
						'enabled'      => true,
						'label_mode'   => 'ref',
						'label_ref'    => '',
						'label_custom' => '',
						'icon_id'      => 0,
						'amount'       => $p, // keep as-is
						'hide_icon'    => false,
					];
				}

				$prices = [ 'mode' => 'multi', 'rows' => $m_rows ];
			}

			$rows[] = [
				'post_id'     => $post_id,
				'post_title'  => $post_title,
				'post_status' => $post_status,
				'description' => $description,
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

	private static function split_pipe( string $v ): array {
		$v = trim( $v );
		if ( $v === '' ) return [];
		$parts = array_map( 'trim', explode( '|', $v ) );
		return array_values( array_filter( $parts, static fn($s) => $s !== '' ) );
	}

	/* --------------------------- Processing -------------------------- */

	private static function process_item( array $it, bool $dry, bool $create_terms, bool $ignore_ids ): array {
		// Row-level validation bubble-up
		if ( isset( $it['_row_error'] ) && $it['_row_error'] !== '' ) {
			return self::result_row(
				$it['post_id'] ?? 0, 0,
				(string) ( $it['post_title'] ?? '' ),
				'skipped', '', '', [
					'menu_terms' => [], 'sect_terms' => [], 'badges' => [], 'prices' => []
				],
				['menus'=>[],'sections'=>[]],
				(string) $it['_row_error']
			);
		}

		$title   = (string) ( $it['post_title'] ?? '' );
		$status  = (string) ( $it['post_status'] ?? 'draft' );
		$desc    = (string) ( $it['description'] ?? '' );
		$tax     = is_array( $it['tax'] ?? null ) ? $it['tax'] : [ 'jprm_menu'=>[], 'jprm_section'=>[] ];
		$badges  = is_array( $it['badges'] ?? null ) ? $it['badges'] : [];
		$prices  = is_array( $it['prices'] ?? null ) ? $it['prices'] : [];

		$existing_id = 0;
		if ( ! $ignore_ids && isset( $it['post_id'] ) && $it['post_id'] !== '' ) {
			$existing_id = (int) $it['post_id'];
		}

		$post_id = 0;
		$action  = 'skipped';
		$error   = '';

		$price_mode = (string) ( $prices['mode'] ?? '' );
		if ( $price_mode !== 'single' && $price_mode !== 'multi' ) {
			// STRICT: compute from payload shape only (no guessing)
			$price_mode = isset( $prices['rows'] ) ? 'multi' : 'single';
		}

		$is_existing = ( $existing_id > 0 && get_post_type( $existing_id ) === 'jprm_menu_item' );
		if ( $is_existing ) {
			$post_id = $existing_id;
			$action  = 'updated'; // may become 'unchanged'
		} else {
			$action  = 'created';
		}

		$old = [
			'post_title'  => $is_existing ? (string) get_the_title( $post_id ) : '',
			'post_status' => $is_existing ? (string) get_post_status( $post_id ) : 'draft',
			'desc'        => $is_existing ? (string) get_post_meta( $post_id, 'jprm_desc', true ) : '',
			'menu_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_menu' ) : [],
			'sect_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_section' ) : [],
			'badges'      => $is_existing ? self::meta_badges( $post_id ) : [],
			'prices'      => $is_existing ? self::build_prices_payload( $post_id, (string) get_post_meta( $post_id, 'jprm_price_mode', true ) ) : [],
		];

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
					'amount_number' => null,
					'label_mode'    => 'ref',
					'label_ref'     => '',
				  ]
				: [
					'mode' => 'multi',
					'rows' => self::canonicalize_rows_strict( $new_rows ),
				  ],
		];

		$price_summary = ( $new['prices']['mode'] === 'single' )
			? (string) ( $new['prices']['amount_raw'] ?? '' )
			: (string) ( count( (array) $new['prices']['rows'] ) . ' rows' );

		$missing_menus     = self::missing_term_names( 'jprm_menu', $new['menu_terms'] );
		$missing_sections  = self::missing_term_names( 'jprm_section', $new['sect_terms'] );
		$new_terms_created = [ 'menus' => $missing_menus, 'sections' => $missing_sections ];

		$changed = self::diff_any( $old, $new );

		if ( ! $dry ) {
			// Create missing post
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

			// Create missing terms (strict names only)
			if ( $create_terms ) {
				$menu_ids = self::ensure_terms_return_ids( 'jprm_menu', $new['menu_terms'] );
				$sect_ids = self::ensure_terms_return_ids( 'jprm_section', $new['sect_terms'] );

				// Link each NEW section to the first available menu
				$owner_menu_id = $menu_ids ? (int) reset( $menu_ids ) : 0;
				if ( $owner_menu_id ) {
					foreach ( $sect_ids as $sid ) {
						if ( ! get_term_meta( $sid, '_jprm_menu_term_id', true ) ) {
							update_term_meta( $sid, '_jprm_menu_term_id', $owner_menu_id );
						}
					}
				}
			}

			// Assign terms by name (keeps strict behavior)
			self::assign_terms_if_changed( $post_id, 'jprm_menu',    $new['menu_terms'] );
			self::assign_terms_if_changed( $post_id, 'jprm_section', $new['sect_terms'] );

			// Post basics
			if ( $changed['post_title'] || $changed['post_status'] ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				] );
			}

			// Meta: desc/visible/badges
			if ( $changed['desc'] ) {
				update_post_meta( $post_id, 'jprm_desc', $new['desc'] );
			}
			if ( get_post_meta( $post_id, 'jprm_visible', true ) === '' ) {
				update_post_meta( $post_id, 'jprm_visible', 'yes' );
			}
			if ( $changed['badges'] ) {
				update_post_meta( $post_id, 'jprm_item_badges', $new['badges'] );
			}

			// Meta: prices — write both shapes deterministically
			update_post_meta( $post_id, 'jprm_price_mode', $price_mode );

			if ( $price_mode === 'single' ) {
				$amount_raw = (string) $new['prices']['amount_raw'];

				update_post_meta( $post_id, 'jprm_price_amount', $amount_raw );
				update_post_meta( $post_id, 'jprm_price_label_mode', 'ref' );
				update_post_meta( $post_id, 'jprm_price_label_ref',  '' );

				update_post_meta( $post_id, 'jprm_price', [
					'mode'      => 'single',
					'price'     => $amount_raw,
					'label_ref' => '',
				] );

				delete_post_meta( $post_id, 'jprm_prices' );

			} else {
				$in_rows = is_array( $prices['rows'] ?? null ) ? (array) $prices['rows'] : [];
				$rows_for_editor = [];
				$rows_for_price  = [];

				foreach ( $in_rows as $r ) {
					$amount    = (string) ( $r['amount'] ?? '' );
					$label_ref = (string) ( $r['label_ref'] ?? '' );
					$hide_icon = (bool)   ( $r['hide_icon'] ?? false );
					$icon_id   = isset( $r['icon_id'] ) ? (int) $r['icon_id'] : 0;

					$rows_for_editor[] = [
						'enabled'      => true,
						'label_mode'   => 'ref',
						'label_ref'    => $label_ref,
						'label_custom' => '',
						'icon_id'      => $icon_id,
						'amount'       => $amount,
						'hide_icon'    => $hide_icon,
					];

					$rows_for_price[] = [
						'label_ref' => $label_ref,
						'value'     => $amount,
						'hide_icon' => $hide_icon,
					];
				}

				update_post_meta( $post_id, 'jprm_prices', $rows_for_editor );
				update_post_meta( $post_id, 'jprm_price', [ 'mode' => 'multi', 'rows' => $rows_for_price ] );

				// Clean single-only metas
				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_mode' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
			}

			/**
			 * NEW: ensure Owner Menu + Section Order on all attached sections.
			 * - Owner = first Menu term in the row (by exact name match)
			 * - Order = sequential per Owner Menu, only assigned if missing
			 */
			$owner_menu_id = self::first_menu_id_from_names( $new['menu_terms'] );
			if ( $owner_menu_id ) {
				$attached_sections = wp_get_object_terms( $post_id, 'jprm_section', [ 'fields' => 'ids' ] );
				if ( is_array( $attached_sections ) && ! is_wp_error( $attached_sections ) ) {
					foreach ( $attached_sections as $sid ) {
						self::ensure_section_owner_and_order( (int) $sid, $owner_menu_id );
					}
				}
			}
		}

		if ( $is_existing && ! $changed['any'] ) {
			$action = 'unchanged';
		}

		return self::result_row(
			$existing_id,
			$dry ? ( $is_existing ? $existing_id : 0 ) : ( $post_id ?: 0 ),
			$title, $price_mode, $price_summary, $new, $new_terms_created, $error
		);
	}

	private static function result_row( $old_id, $new_id, $title, $mode, $price_summary, $new, $new_terms_created, $error ) : array {
		return [
			'post_id_old'       => $old_id,
			'post_id_new'       => $new_id,
			'title'             => $title,
			'action'            => ( $new_id && $old_id && $new_id === $old_id ) ? 'updated' : ( $new_id ? 'created' : 'skipped' ),
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
				'amount_number' => null,
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

		// Canonicalize strictly: keep provided strings; only keep needed keys.
		return [
			'mode' => 'multi',
			'rows' => self::canonicalize_rows_strict( $rows ),
		];
	}

	private static function canonicalize_rows_strict( array $rows ): array {
		$norm = [];
		foreach ( $rows as $r ) {
			$a = is_array( $r ) ? $r : (array) $r;
			$norm[] = [
				'label_ref' => isset($a['label_ref']) ? (string)$a['label_ref'] : '',
				'amount'    => isset($a['amount'])    ? (string)$a['amount']    : (string)($a['value'] ?? ''),
			];
		}
		// No sorting, keep given order.
		return array_values( $norm );
	}

	private static function diff_any( array $old, array $new ): array {
		$c = [
			'post_title'  => ( self::eqs($old['post_title'])  !== self::eqs($new['post_title']) ),
			'post_status' => ( self::eqs($old['post_status']) !== self::eqs($new['post_status']) ),
			'desc'        => ( self::eqs($old['desc'])        !== self::eqs($new['desc']) ),
			'menu_terms'  => ( self::sorted($old['menu_terms']) !== self::sorted($new['menu_terms']) ),
			'sect_terms'  => ( self::sorted($old['sect_terms']) !== self::sorted($new['sect_terms']) ),
			'badges'      => ( self::sorted($old['badges'])     !== self::sorted($new['badges']) ),
			'prices'      => ( self::normalize_prices_for_compare($old['prices']) !== self::normalize_prices_for_compare($new['prices']) ),
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

	// For comparison only; keep strings as-is.
	private static function normalize_prices_for_compare( $p ): array {
		if ( ! is_array( $p ) ) return [];
		if ( ( $p['mode'] ?? '' ) === 'single' ) {
			return [
				'mode'       => 'single',
				'amount_raw' => self::eqs( $p['amount_raw'] ?? '' ),
				'label_mode' => 'ref',
				'label_ref'  => '',
			];
		}
		$rows = isset( $p['rows'] ) && is_array( $p['rows'] ) ? $p['rows'] : [];
		$out  = [];
		foreach ( $rows as $r ) {
			$out[] = [
				'label_ref' => self::eqs( (string) ( $r['label_ref'] ?? '' ) ),
				'amount'    => self::eqs( (string) ( $r['amount'] ?? ( $r['value'] ?? '' ) ) ),
			];
		}
		return [ 'mode' => 'multi', 'rows' => $out ];
	}

	/* ---------------- Owner + Order helpers ---------------- */

	/**
	 * Resolve first menu ID by exact term name list; returns 0 if none found.
	 */
	private static function first_menu_id_from_names( array $names ): int {
		foreach ( $names as $name ) {
			if ( $name === '' ) { continue; }
			$t = get_term_by( 'name', $name, 'jprm_menu' );
			if ( $t && ! is_wp_error( $t ) ) {
				return (int) $t->term_id;
			}
		}
		return 0;
	}

	/**
	 * Ensure a section has its owner menu set (if missing) and a sequential order (if missing).
	 */
	private static function ensure_section_owner_and_order( int $section_term_id, int $menu_term_id ) : void {
		if ( $section_term_id <= 0 || $menu_term_id <= 0 ) { return; }

		$owner = get_term_meta( $section_term_id, '_jprm_menu_term_id', true );
		if ( ! $owner ) {
			update_term_meta( $section_term_id, '_jprm_menu_term_id', $menu_term_id );
		}

		$order_raw = get_term_meta( $section_term_id, '_jprm_section_order', true );
		$order     = is_numeric( $order_raw ) ? (int) $order_raw : 0;

		if ( $order <= 0 ) {
			$next = self::next_order_for_menu( $menu_term_id );
			update_term_meta( $section_term_id, '_jprm_section_order', $next );
		}
	}

	/**
	 * Get the next sequential order number for a menu_id, seeding from current max once per run.
	 */
	private static function next_order_for_menu( int $menu_term_id ) : int {
		if ( $menu_term_id <= 0 ) { return 1; }

		if ( ! isset( self::$section_order_seq[ $menu_term_id ] ) ) {
			$max = 0;

			// Try to fetch the highest existing order for this menu owner quickly
			$args = [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => [
					[
						'key'   => '_jprm_menu_term_id',
						'value' => (string) $menu_term_id,
					],
				],
				'meta_key'   => '_jprm_section_order',
				'orderby'    => 'meta_value_num',
				'order'      => 'DESC',
			];
			$terms = get_terms( $args );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$max = (int) get_term_meta( (int) $terms[0]->term_id, '_jprm_section_order', true );
			}

			self::$section_order_seq[ $menu_term_id ] = max( 0, $max ) + 1;
		}

		$next = self::$section_order_seq[ $menu_term_id ];
		self::$section_order_seq[ $menu_term_id ] = $next + 1;
		return $next;
	}
}
