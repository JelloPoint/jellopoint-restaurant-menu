<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
            $r = self::process_item( $row, $dry_run, $create_missing_terms, $wc_menus, $wc_sections );

            if ( isset( $r['action'] ) ) {
                if ( $r['action'] === 'created' )      { $report['created']++; }
                elseif ( $r['action'] === 'updated' )  { $report['updated']++; }
                elseif ( $r['action'] === 'unchanged') { $report['unchanged']++; }
                elseif ( $r['action'] === 'skipped' )  { $report['skipped']++; }
            }
            if ( ! empty( $r['new_terms_created']['menus'] ) ) {
                $names = (array) $r['new_terms_created']['menus'];
                $report['new_terms']['menus']      += count( $names );
                $report['new_terms']['menus_list']  = array_values( array_unique( array_merge( $report['new_terms']['menus_list'], $names ) ) );
            }
            if ( ! empty( $r['new_terms_created']['sections'] ) ) {
                $names = (array) $r['new_terms_created']['sections'];
                $report['new_terms']['sections']      += count( $names );
                $report['new_terms']['sections_list']  = array_values( array_unique( array_merge( $report['new_terms']['sections_list'], $names ) ) );
            }
            if ( ! empty( $r['error'] ) ) { $report['errors'][] = $r['error']; }

            $report['items'][] = $r;
        }

        if ( $dry_run ) {
            $report['would_create']['menus']    = array_values( array_unique( $wc_menus ) );
            $report['would_create']['sections'] = array_values( array_unique( $wc_sections ) );
        }

        return $report;
    }

    /* -------------------- Parsers -------------------- */

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
     * Supports an optional first line: "sep=;" or "sep=," (Excel hint)
     * Headers: post_id, post_title, post_status, description, menus, sections, Price_Single, Price_Multiple
     * - menus/sections: pipe-separated names
     * - Price_Multiple: values separated by "*"
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
        if ( $delimiter === null ) {
            $delimiter = ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ) ? ';' : ',';
        }

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
                $canon_rows = [];
                foreach ( $parts as $p ) {
                    $canon_rows[] = [
                        'label_ref' => '',
                        'amount'    => (string) $p,
                    ];
                }
                $prices = [ 'mode' => 'multi', 'rows' => $canon_rows ];
            } elseif ( $single !== '' ) {
                $prices = [
                    'mode'          => 'single',
                    'amount_raw'    => (string) $single,
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

	private static function process_item( array $it, bool $dry, bool $create_terms, array &$wc_menus, array &$wc_sections ): array {
		$title   = (string) ( $it['post_title'] ?? '' );
		$status  = (string) ( $it['post_status'] ?? 'draft' );
		$desc    = (string) ( $it['description'] ?? '' );
		$tax     = is_array( $it['tax'] ?? null ) ? $it['tax'] : [ 'jprm_menu'=>[], 'jprm_section'=>[] ];
		$badges  = is_array( $it['badges'] ?? null ) ? $it['badges'] : [];
		$prices  = is_array( $it['prices'] ?? null ) ? $it['prices'] : [];

		$existing_id = ( isset( $it['post_id'] ) && $it['post_id'] !== '' ) ? (int) $it['post_id'] : 0;

		$action  = 'skipped';
		$error   = '';
		$post_id = 0;

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

		// Old state for diff
		$old = [
			'post_title'  => $is_existing ? (string) get_the_title( $post_id ) : '',
			'post_status' => $is_existing ? (string) get_post_status( $post_id ) : 'draft',
			'desc'        => $is_existing ? (string) get_post_meta( $post_id, 'jprm_desc', true ) : '',
			'menu_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_menu' ) : [],
			'sect_terms'  => $is_existing ? self::terms_as_names( $post_id, 'jprm_section' ) : [],
			'badges'      => $is_existing ? self::meta_badges( $post_id ) : [],
			'prices'      => $is_existing ? self::build_prices_payload( $post_id, (string) get_post_meta( $post_id, 'jprm_price_mode', true ) ) : [],
		];

		// Normalize new state (matching exporter)
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
					'rows' => self::canonicalize_rows_whitelisted( $new_rows ), // internal canonical rows (amount)
				  ],
		];

		// Collect “would create” for dry-run
		$missing_menus    = self::missing_term_names( 'jprm_menu',    $new['menu_terms'] );
		$missing_sections = self::missing_term_names( 'jprm_section', $new['sect_terms'] );
		$wc_menus         = array_merge( $wc_menus,    $missing_menus );
		$wc_sections      = array_merge( $wc_sections, $missing_sections );

		// Diff
		$changed = self::diff_any( $old, $new );

		// Writes
		if ( ! $dry ) {
			if ( ! $is_existing ) {
				$ins = wp_insert_post( [
					'post_type'   => 'jprm_menu_item',
					'post_title'  => $new['post_title'],
					'post_status' => $new['post_status'],
				], true );
				if ( is_wp_error( $ins ) ) {
					return self::row_result( $existing_id, 0, $title, 'skipped', $price_mode, $new, [], 'Insert failed: ' . $ins->get_error_message() );
				}
				$post_id = (int) $ins;
			}

			// Create missing terms if asked
			$created_m = [];
			$created_s = [];
			if ( $create_terms ) {
				$created_m = self::ensure_terms_exist( 'jprm_menu',    $missing_menus );
				$created_s = self::ensure_terms_exist( 'jprm_section', $missing_sections );
			}

			// Assign Menu + Section terms
			self::assign_terms_if_changed( $post_id, 'jprm_menu',    $new['menu_terms'] );
			self::assign_terms_if_changed( $post_id, 'jprm_section', $new['sect_terms'] );

			// **NEW**: ensure each Section has an owner menu (_jprm_menu_term_id) if we can infer it.
			self::ensure_section_owners( $new['menu_terms'], $new['sect_terms'] );

			// Post props if changed
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

			// Meta: prices (WRITE jprm_price in the exact shape that the list expects)
			update_post_meta( $post_id, 'jprm_price_mode', $price_mode );
			if ( $price_mode === 'single' ) {
				$amount = (string) ( $new['prices']['amount_raw'] ?? '' );

				// legacy helper metas (keep if your code uses them elsewhere)
				update_post_meta( $post_id, 'jprm_price_amount',      $amount );
				update_post_meta( $post_id, 'jprm_price_label_mode',  (string) ( $new['prices']['label_mode'] ?? '' ) );
				update_post_meta( $post_id, 'jprm_price_label_ref',   (string) ( $new['prices']['label_ref']  ?? '' ) );

				// PRIMARY meta for list table:
				update_post_meta( $post_id, 'jprm_price', [
					'mode'  => 'single',
					'price' => $amount, // <— the list reads "price"
				] );

				// optional internal array
				delete_post_meta( $post_id, 'jprm_prices' );
			} else {
				$rows_canon = (array) ( $new['prices']['rows'] ?? [] ); // rows have amount + label_ref

				// Keep your internal array if you need it
				update_post_meta( $post_id, 'jprm_prices', $rows_canon );

				// Build rows for PRIMARY jprm_price (value, not amount)
				$rows_for_list = [];
				foreach ( $rows_canon as $r ) {
					$rows_for_list[] = [
						'label_ref' => isset( $r['label_ref'] ) ? (string) $r['label_ref'] : '',
						'value'     => isset( $r['amount'] )    ? (string) $r['amount']    : ( isset($r['value']) ? (string)$r['value'] : '' ),
						'hide_icon' => isset( $r['hide_icon'] ) ? (bool)   $r['hide_icon'] : false,
					];
				}

				update_post_meta( $post_id, 'jprm_price', [
					'mode' => 'multi',
					'rows' => $rows_for_list, // <— list reads rows[].value
				] );

				// cleanup legacy singles
				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_mode' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
			}

			// Reflect actually created terms in commit mode
			$new_terms_created = [
				'menus'    => $created_m,
				'sections' => $created_s,
			];
		} else {
			// Dry-run: no writes, just report "would create"
			$new_terms_created = [ 'menus' => [], 'sections' => [] ];
		}

		// Price summary for the table
		$price_summary = ( $price_mode === 'single' )
			? (string) ( $new['prices']['amount_raw'] ?? '' )
			: (string) count( (array) ( $new['prices']['rows'] ?? [] ) ) . ' rows';

		// Final action
		if ( $is_existing && ! $changed['any'] ) {
			$action = 'unchanged';
		}

		return self::row_result(
			$existing_id,
			$dry ? ( $is_existing ? $existing_id : 0 ) : ( $post_id ?: 0 ),
			$title,
			$action,
			$price_mode,
			$new,
			$new_terms_created,
			$error,
			$price_summary
		);
	}

	private static function row_result( int $old_id, int $new_id, string $title, string $action, string $mode, array $new,
		array $new_terms_created, string $error = '', string $price_summary = '' ): array {
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

	private static function ensure_terms_exist( string $tax, array $names ): array {
		$created = [];
		foreach ( $names as $name ) {
			if ( $name === '' ) continue;
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) { continue; }
			$ins = wp_insert_term( $name, $tax );
			if ( ! is_wp_error( $ins ) ) {
				$created[] = $name;
			}
		}
		return $created;
	}

	private static function assign_terms_if_changed( int $post_id, string $tax, array $target_names ): void {
		$current = self::terms_as_names( $post_id, $tax );
		$cn = $current; sort( $cn );
		$tn = $target_names; sort( $tn );
		if ( $cn === $tn ) { return; }

		$ids = [];
		foreach ( $target_names as $name ) {
			if ( $name === '' ) continue;
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $post_id, $ids, $tax, false );
	}

	/**
	 * If exactly one Menu name is given, ensure each Section has that owner in term meta.
	 */
	private static function ensure_section_owners( array $menu_names, array $section_names ): void {
		$menu_names = array_values( array_filter( array_map( 'strval', $menu_names ) ) );
		if ( count( $menu_names ) !== 1 ) return;

		$menu = get_term_by( 'name', $menu_names[0], 'jprm_menu' );
		if ( ! $menu || is_wp_error( $menu ) ) return;

		foreach ( $section_names as $sname ) {
			if ( $sname === '' ) continue;
			$sec = get_term_by( 'name', $sname, 'jprm_section' );
			if ( $sec && ! is_wp_error( $sec ) ) {
				$owner = get_term_meta( (int) $sec->term_id, '_jprm_menu_term_id', true );
				if ( (int) $owner !== (int) $menu->term_id ) {
					update_term_meta( (int) $sec->term_id, '_jprm_menu_term_id', (int) $menu->term_id );
				}
			}
		}
	}

	/**
	 * Build prices payload (exporter shape).
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
				// Note: jprm_price uses rows[].value for list; convert back to canonical amount for exporter shape
				foreach ( (array) $raw_price['rows'] as $r ) {
					$rows[] = [
						'label_ref' => isset($r['label_ref']) ? (string)$r['label_ref'] : '',
						'amount'    => isset($r['value'])     ? (string)$r['value']     : '',
						'hide_icon' => isset($r['hide_icon']) ? (bool)$r['hide_icon']   : false,
					];
				}
			}
		}

		return [
			'mode' => 'multi',
			'rows' => self::canonicalize_rows_whitelisted( $rows ),
		];
	}

	/**
	 * Canonicalize multi rows but keep a whitelist of meaningful keys.
	 * Default whitelist: ['label_ref','amount'].
	 * - Treats 'price' -> 'amount'
	 * - Normalizes numeric strings (EU/US) so "5,00" == "5.00"
	 * Filter: 'jprm/import/row_compare_keys'
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
