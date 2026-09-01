<?php
namespace JelloPoint\RestaurantMenu\Data;

use JelloPoint\RestaurantMenu\Storage\Price_Repository;

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
	 *
	 * NOTE: This parser supports quoted fields that contain newlines (common for multi-line descriptions).
	 */
	private static function parse_csv_strict( string $raw, array &$report ): array {
		// We cannot safely split by "\n" because CSV fields may contain newlines inside quotes.
		// Use fgetcsv on an in-memory stream instead.
		$fp = fopen( 'php://temp', 'r+' );
		if ( ! $fp ) { $report['errors'][] = 'Could not open temp stream for CSV.'; return []; }

		// Strip UTF-8 BOM if present
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

		fwrite( $fp, $raw );
		rewind( $fp );

		// Read header physical line (safe assumption: headers do not contain embedded newlines)
		$first = fgets( $fp );
		if ( $first === false ) { $report['errors'][] = 'Empty CSV.'; fclose($fp); return []; }

		$delimiter = ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ) ? ';' : ',';

		// Parse headers using the chosen delimiter
		$headers = str_getcsv( rtrim( $first, "\r\n" ), $delimiter );

		// Required header set (case-sensitive)
		$required = [ 'post_id','post_title','post_status','description','menus','sections','Price_Single','Price_Multiple' ];
		$missing  = array_diff( $required, $headers );
		if ( $missing ) {
			$report['errors'][] = 'CSV missing required header(s): ' . implode(', ', $missing);
			fclose($fp);
			return [];
		}

		$map  = array_flip( $headers );
		$rows = [];

		// Continue reading remaining rows with fgetcsv (supports quoted newlines correctly)
		while ( ( $vals = fgetcsv( $fp, 0, $delimiter ) ) !== false ) {
			// Skip empty lines
			if ( $vals === [ null ] ) { continue; }
			if ( count( $vals ) === 1 && trim( (string) $vals[0] ) === '' ) { continue; }

			// Build associative row by headers exactly
			$row = array_fill_keys( $headers, '' );
			foreach ( $headers as $h ) {
				$idx = $map[$h] ?? null;
				if ( $idx !== null && isset( $vals[$idx] ) ) {
					$row[$h] = is_string( $vals[$idx] ) ? $vals[$idx] : (string) $vals[$idx];
				}
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

		fclose( $fp );
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
			$new_payload = [
				'menu_terms' => [],
				'sect_terms' => [],
				'badges'     => [],
				'prices'     => [],
			];
			return self::result_row(
				$it['post_id'] ?? 0,
				0,
				(string) ( $it['post_title'] ?? '' ),
				'skipped',
				'',
				$new_payload,
				[ 'menus' => [], 'sections' => [] ],
				(string) $it['_row_error']
			);
		}

		$title   = sanitize_text_field( (string) ( $it['post_title'] ?? '' ) );
		$status  = sanitize_key( (string) ( $it['post_status'] ?? 'draft' ) );
		$status  = in_array( $status, [ 'draft', 'pending', 'publish', 'private' ], true ) ? $status : 'draft';
		$desc    = wp_kses_post( (string) ( $it['description'] ?? '' ) );
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
		if ( $is_existing && ! current_user_can( 'edit_post', $existing_id ) ) {
			return self::result_row(
				$existing_id,
				0,
				$title,
				'skipped',
				'',
				[],
				[ 'menus' => [], 'sections' => [] ],
				'Insufficient permissions to update this item.'
			);
		}
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
		$single_label_mode = ( (string) ( $prices['label_mode'] ?? 'ref' ) === 'custom' ) ? 'custom' : 'ref';
		$single_label_ref  = (string) ( $prices['label_ref'] ?? '' );
		$single_custom     = (string) ( $prices['label_custom'] ?? '' );
		$single_icon_id    = max( 0, (int) ( $prices['icon_id'] ?? 0 ) );
		$single_hide_icon  = ! empty( $prices['hide_icon'] );
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
					'label_mode'    => $single_label_mode,
					'label_ref'     => $single_label_ref,
					'label_custom'  => $single_custom,
					'icon_id'       => $single_icon_id,
					'hide_icon'     => $single_hide_icon,
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
					// IMPORTANT: keep argument order consistent with result_row() signature
					// result_row( old_id, new_id, title, mode, price_summary, new_payload, new_terms_created, error )
					return self::result_row(
						$existing_id,
						0,
						$title,
						$price_mode,
						$price_summary,
						$new,
						$new_terms_created,
						'Insert failed: ' . $post_id->get_error_message()
					);
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
				update_post_meta( $post_id, 'jprm_price_label_mode', $single_label_mode );
				update_post_meta( $post_id, 'jprm_price_label_ref', $single_label_ref );
				update_post_meta( $post_id, 'jprm_price_label_custom', $single_custom );
				update_post_meta( $post_id, 'jprm_price_label_icon_id', $single_icon_id );

				$canonical_label = ( 'custom' === $single_label_mode && '' !== $single_custom )
					? $single_custom
					: $single_label_ref;

				Price_Repository::set( $post_id, [
					'mode'      => 'single',
					'price'     => $amount_raw,
					'label_ref' => $canonical_label,
					'hide_icon' => $single_hide_icon,
					'icon_id'   => $single_icon_id,
				] );

				delete_post_meta( $post_id, 'jprm_prices' );

			} else {
				$in_rows = is_array( $prices['rows'] ?? null ) ? (array) $prices['rows'] : [];
				$rows_for_editor = [];
				$rows_for_price  = [];

				foreach ( $in_rows as $r ) {
					$amount    = (string) ( $r['amount'] ?? '' );
					$label_mode = ( (string) ( $r['label_mode'] ?? 'ref' ) === 'custom' ) ? 'custom' : 'ref';
					$label_ref = (string) ( $r['label_ref'] ?? '' );
					$label_custom = (string) ( $r['label_custom'] ?? '' );
					$canonical_label = ( 'custom' === $label_mode && '' !== $label_custom ) ? $label_custom : $label_ref;
					$hide_icon = (bool)   ( $r['hide_icon'] ?? false );
					$icon_id   = isset( $r['icon_id'] ) ? (int) $r['icon_id'] : 0;

					$rows_for_editor[] = [
						'enabled'      => true,
						'label_mode'   => $label_mode,
						'label_ref'    => $label_ref,
						'label_custom' => $label_custom,
						'icon_id'      => $icon_id,
						'amount'       => $amount,
						'hide_icon'    => $hide_icon,
					];

					$rows_for_price[] = [
						'label_ref' => $canonical_label,
						'value'     => $amount,
						'hide_icon' => $hide_icon,
						'icon_id'   => $icon_id,
					];
				}

				update_post_meta( $post_id, 'jprm_prices', $rows_for_editor );
				Price_Repository::set( $post_id, [ 'mode' => 'multi', 'rows' => $rows_for_price ] );

				// Clean single-only metas
				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_mode' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
				delete_post_meta( $post_id, 'jprm_price_label_custom' );
				delete_post_meta( $post_id, 'jprm_price_label_icon_id' );
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
			$title, $price_mode, $price_summary, $new, $new_terms_created, $error, $action
		);
	}

	private static function result_row( $old_id, $new_id, $title, $mode, $price_summary, $new, $new_terms_created, $error, $action = '' ) : array {
		// Defensive: never fatally error when an upstream caller passes an unexpected type.
		if ( ! is_array( $new ) ) {
			$new = [
				'menu_terms' => [],
				'sect_terms' => [],
				'badges'     => [],
				'prices'     => [],
			];
		}
		$new = array_merge( [
			'menu_terms' => [],
			'sect_terms' => [],
			'badges'     => [],
			'prices'     => [],
		], $new );

		$derived_action = ( $new_id && $old_id && $new_id === $old_id ) ? 'updated' : ( $new_id ? 'created' : 'skipped' );
		$reported_action = in_array( $action, [ 'created', 'updated', 'unchanged', 'skipped' ], true ) ? $action : $derived_action;

		return [
			'post_id_old'       => $old_id,
			'post_id_new'       => $new_id,
			'title'             => $title,
			'action'            => $reported_action,
			'mode'              => $mode,
			'price_summary'     => $price_summary,
			'menus'             => is_array( $new['menu_terms'] ) ? $new['menu_terms'] : [],
			'sections'          => is_array( $new['sect_terms'] ) ? $new['sect_terms'] : [],
			'badges'            => is_array( $new['badges'] ) ? $new['badges'] : [],
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
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) { $ids[] = (int) $term->term_id; continue; }
			$r = wp_insert_term( $name, $tax );
			if ( is_array( $r ) && isset( $r['term_id'] ) ) {
				$ids[] = (int) $r['term_id'];
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function assign_terms_if_changed( int $post_id, string $tax, array $names ): void {
		$curr = wp_get_object_terms( $post_id, $tax, [ 'fields' => 'names' ] );
		$curr = ( is_wp_error( $curr ) || ! is_array( $curr ) ) ? [] : array_values( $curr );
		sort( $curr );
		$want = array_values( array_filter( $names ) );
		sort( $want );
		if ( $curr === $want ) return;

		$ids = [];
		foreach ( $want as $name ) {
			$t = get_term_by( 'name', $name, $tax );
			if ( $t && ! is_wp_error( $t ) ) $ids[] = (int) $t->term_id;
		}
		wp_set_object_terms( $post_id, $ids, $tax, false );
	}

	private static function build_prices_payload( int $post_id, string $mode ): array {
		$cfg = Price_Repository::get( $post_id );
		$mode = is_array( $cfg ) && ( $cfg['mode'] ?? '' ) === 'multi' ? 'multi' : ( ( $mode === 'multi' ) ? 'multi' : 'single' );

		if ( $mode === 'single' ) {
			$label_mode = ( (string) get_post_meta( $post_id, 'jprm_price_label_mode', true ) === 'custom' ) ? 'custom' : 'ref';
			return [
				'mode'          => 'single',
				'amount_raw'    => is_array( $cfg ) ? (string) ( $cfg['price'] ?? '' ) : (string) get_post_meta( $post_id, 'jprm_price_amount', true ),
				'amount_number' => null,
				'label_mode'    => $label_mode,
				'label_ref'     => (string) get_post_meta( $post_id, 'jprm_price_label_ref', true ),
				'label_custom'  => (string) get_post_meta( $post_id, 'jprm_price_label_custom', true ),
				'icon_id'       => is_array( $cfg ) ? (int) ( $cfg['icon_id'] ?? 0 ) : 0,
				'hide_icon'     => is_array( $cfg ) && ! empty( $cfg['hide_icon'] ),
			];
		}

		$rows = get_post_meta( $post_id, 'jprm_prices', true );
		if ( is_string( $rows ) ) {
			$decoded = json_decode( $rows, true );
			$rows = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $rows ) ) $rows = [];
		return [ 'mode' => 'multi', 'rows' => self::canonicalize_rows_strict( $rows ) ];
	}

	private static function canonicalize_rows_strict( array $rows ): array {
		$out = [];
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) continue;
			$amount = isset( $r['amount'] ) ? (string) $r['amount'] : '';
			if ( $amount === '' ) continue;

			$out[] = [
				'enabled'      => true,
				'label_mode'   => ( (string) ( $r['label_mode'] ?? 'ref' ) === 'custom' ) ? 'custom' : 'ref',
				'label_ref'    => isset( $r['label_ref'] ) ? (string) $r['label_ref'] : '',
				'label_custom' => isset( $r['label_custom'] ) ? (string) $r['label_custom'] : '',
				'icon_id'      => isset( $r['icon_id'] ) ? (int) $r['icon_id'] : 0,
				'amount'       => $amount,
				'hide_icon'    => ! empty( $r['hide_icon'] ),
			];
		}
		return array_values( $out );
	}

	private static function diff_any( array $old, array $new ): array {
		$diff = [
			'post_title'   => ( $old['post_title'] ?? '' ) !== ( $new['post_title'] ?? '' ),
			'post_status'  => ( $old['post_status'] ?? '' ) !== ( $new['post_status'] ?? '' ),
			'desc'         => ( $old['desc'] ?? '' ) !== ( $new['desc'] ?? '' ),
			'menu_terms'   => (array) ( $old['menu_terms'] ?? [] ) !== (array) ( $new['menu_terms'] ?? [] ),
			'sect_terms'   => (array) ( $old['sect_terms'] ?? [] ) !== (array) ( $new['sect_terms'] ?? [] ),
			'badges'       => (array) ( $old['badges'] ?? [] ) !== (array) ( $new['badges'] ?? [] ),
			'prices'       => (array) ( $old['prices'] ?? [] ) !== (array) ( $new['prices'] ?? [] ),
		];
		$diff['any'] = in_array( true, $diff, true );
		return $diff;
	}

	private static function first_menu_id_from_names( array $menu_names ): int {
		foreach ( $menu_names as $name ) {
			$name = (string) $name;
			if ( $name === '' ) continue;
			$t = get_term_by( 'name', $name, 'jprm_menu' );
			if ( $t && ! is_wp_error( $t ) ) return (int) $t->term_id;
		}
		return 0;
	}

	private static function ensure_section_owner_and_order( int $section_term_id, int $owner_menu_term_id ): void {
		if ( $section_term_id <= 0 || $owner_menu_term_id <= 0 ) return;

		// Owner
		$curr_owner = (int) get_term_meta( $section_term_id, '_jprm_menu_term_id', true );
		if ( ! $curr_owner ) {
			update_term_meta( $section_term_id, '_jprm_menu_term_id', $owner_menu_term_id );
		}

		// Order
		$curr_order = get_term_meta( $section_term_id, '_jprm_section_order', true );
		if ( $curr_order === '' || $curr_order === null ) {
			if ( ! isset( self::$section_order_seq[ $owner_menu_term_id ] ) ) {
				self::$section_order_seq[ $owner_menu_term_id ] = self::max_section_order_for_menu( $owner_menu_term_id ) + 1;
			}
			$next = (int) self::$section_order_seq[ $owner_menu_term_id ];
			update_term_meta( $section_term_id, '_jprm_section_order', $next );
			self::$section_order_seq[ $owner_menu_term_id ] = $next + 1;
		}
	}

	private static function max_section_order_for_menu( int $menu_term_id ): int {
		$max = 0;
		$terms = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
			'fields'     => 'ids',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) return 0;

		foreach ( $terms as $sid ) {
			$owner = (int) get_term_meta( (int) $sid, '_jprm_menu_term_id', true );
			if ( $owner !== $menu_term_id ) continue;
			$o = get_term_meta( (int) $sid, '_jprm_section_order', true );
			if ( $o !== '' && is_numeric( $o ) ) {
				$max = max( $max, (int) $o );
			}
		}

		return $max;
	}
}
