<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin List Table enhancements for the Menu Items CPT.
 * - Columns: Menu / Section / Price(s)
 * - Filters: Menu + Section (Section list limited by selected Menu)
 * - Bulk actions: Assign to Section…, Unassign from Section
 * - Prices: reads ONLY meta 'jprm_price'
 * - Multi-prices: "Multiple prices (N)" toggle reveals full list
 */
class Menu_Item_List {

	const CPT                = 'jprm_menu_item';
	const TAX_SECTION        = 'jprm_section';
	const TAX_MENU           = 'jprm_menu';

	const META_SECTION_ORDER = '_jprm_order_in_section';

	// Your canonical price meta (JSON/array: single/multi)
	const META_PRICE_PRIMARY = 'jprm_price';

	// Section -> menu owner term meta (on the section term)
	const META_MENU_OWNER    = '_jprm_menu_term_id';

	public static function init() : void {
		// Columns
		add_filter( 'manage_edit-' . self::CPT . '_columns', [ __CLASS__, 'columns' ], 20 );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', [ __CLASS__, 'sortable_columns' ] );

		// Filters in list screen
		add_action( 'restrict_manage_posts', [ __CLASS__, 'filters' ] );
		add_filter( 'parse_query', [ __CLASS__, 'apply_filters_to_query' ] );

		// Bulk actions
		add_filter( 'bulk_actions-edit-' . self::CPT, [ __CLASS__, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-' . self::CPT, [ __CLASS__, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'bulk_admin_notice' ] );

		// Inject selector for bulk-assign + tiny JS for toggling multi-prices
		add_action( 'admin_footer-edit.php', [ __CLASS__, 'inject_bulk_assign_selector' ] );
	}

	/* ---------------- Columns ---------------- */

	public static function columns( array $cols ) : array {
		// Remove WP Date column entirely
		if ( isset( $cols['date'] ) ) unset( $cols['date'] );

		$new = [];
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['jprm_menu']    = __( 'Menu', 'jprm' );
				$new['jprm_section'] = __( 'Section', 'jprm' );
				$new['jprm_prices']  = __( 'Price(s)', 'jprm' );
			}
		}
		$new += [
			'jprm_menu'    => __( 'Menu', 'jprm' ),
			'jprm_section' => __( 'Section', 'jprm' ),
			'jprm_prices'  => __( 'Price(s)', 'jprm' ),
		];
		return $new;
	}

	public static function sortable_columns( array $cols ) : array {
		return $cols; // no custom sorters
	}

	public static function column_content( string $col, int $post_id ) : void {
		if ( 'jprm_menu' === $col ) {
			$menu_name = self::derive_menu_name_for_item( $post_id );
			echo esc_html( $menu_name ?: '—' );
			return;
		}
		if ( 'jprm_section' === $col ) {
			$terms = wp_get_post_terms( $post_id, self::TAX_SECTION );
			if ( is_wp_error( $terms ) || empty( $terms ) ) { echo '—'; return; }
			$first = $terms[0]; $more = max( 0, count( $terms ) - 1 );
			echo esc_html( $first->name );
			if ( $more ) echo ' +' . intval( $more );
			return;
		}
		if ( 'jprm_prices' === $col ) {
			echo self::render_prices_cell( $post_id ); // already escaped inside
			return;
		}
	}

	/* ---------------- Filters (Menus + Sections) ---------------- */

	public static function filters( string $post_type ) : void {
		if ( $post_type !== self::CPT ) return;

		$sel_menu_id    = isset( $_GET['jprm_filter_menu'] ) ? intval( $_GET['jprm_filter_menu'] ) : 0; // phpcs:ignore
		$sel_section_id = isset( $_GET['jprm_filter_section'] ) ? intval( $_GET['jprm_filter_section'] ) : 0; // phpcs:ignore

		// MENUS
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		echo '<label for="jprm_filter_menu" class="screen-reader-text">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform">';
		echo '<option value="0">' . esc_html__( 'All Menus', 'jprm' ) . '</option>';
		if ( ! is_wp_error( $menus ) ) {
			foreach ( $menus as $m ) {
				printf(
					'<option value="%d"%s>%s</option>',
					intval( $m->term_id ),
					selected( $sel_menu_id, intval( $m->term_id ), false ),
					esc_html( $m->name )
				);
			}
		}
		echo '</select>';

		// SECTIONS limited by selected menu (if any)
		$sections = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false, 'fields' => 'all' ] );
		$opts = [];
		if ( ! is_wp_error( $sections ) ) {
			$by_id = [];
			foreach ( $sections as $t ) {
				$owner = intval( get_term_meta( $t->term_id, self::META_MENU_OWNER, true ) );
				if ( $sel_menu_id && $owner !== $sel_menu_id ) continue;
				$by_id[ $t->term_id ] = [ 'term' => $t, 'children' => [] ];
			}
			foreach ( $by_id as $id => &$node ) {
				$p = $node['term']->parent;
				if ( $p && isset( $by_id[ $p ] ) ) $by_id[ $p ]['children'][] = &$node;
			}
			unset( $node );
			$roots = array_filter( $by_id, static function( $n ) use ( $by_id ) {
				$p = $n['term']->parent;
				return ! $p || ! isset( $by_id[ $p ] );
			} );

			$flat = [];
			$walk = static function( $nodes, $depth ) use ( &$flat, &$walk ) {
				foreach ( $nodes as $n ) {
					$flat[] = [ 'id' => $n['term']->term_id, 'name' => $n['term']->name, 'depth' => $depth ];
					if ( ! empty( $n['children'] ) ) $walk( $n['children'], $depth + 1 );
				}
			};
			$walk( $roots, 0 );
			$opts = $flat;
		}

		echo '<label for="jprm_filter_section" class="screen-reader-text">' . esc_html__( 'Filter by Section', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_section" id="jprm_filter_section" class="postform">';
		echo '<option value="0">' . esc_html__( 'All Sections', 'jprm' ) . '</option>';
		foreach ( $opts as $o ) {
			$indent = str_repeat( '— ', max( 0, $o['depth'] ) );
			printf(
				'<option value="%d"%s>%s%s</option>',
				intval( $o['id'] ),
				selected( $sel_section_id, intval( $o['id'] ), false ),
				esc_html( $indent ),
				esc_html( $o['name'] )
			);
		}
		echo '</select>';
	}

	public static function apply_filters_to_query( \WP_Query $q ) : void {
		global $pagenow;
		if ( $pagenow !== 'edit.php' ) return;
		if ( empty( $q->query ) || ( $q->get( 'post_type' ) !== self::CPT ) ) return;

		$menu_id    = isset( $_GET['jprm_filter_menu'] ) ? intval( $_GET['jprm_filter_menu'] ) : 0; // phpcs:ignore
		$section_id = isset( $_GET['jprm_filter_section'] ) ? intval( $_GET['jprm_filter_section'] ) : 0; // phpcs:ignore

		$tax_query = (array) $q->get( 'tax_query', [] );

		if ( $section_id ) {
			$tax_query[] = [
				'taxonomy'         => self::TAX_SECTION,
				'field'            => 'term_id',
				'terms'            => [ $section_id ],
				'include_children' => true,
			];
		} elseif ( $menu_id ) {
			$all = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false, 'fields' => 'ids' ] );
			$ids = [];
			if ( ! is_wp_error( $all ) ) {
				foreach ( $all as $tid ) {
					if ( intval( get_term_meta( $tid, self::META_MENU_OWNER, true ) ) === $menu_id ) {
						$ids[] = intval( $tid );
					}
				}
			}
			$ids = array_values( array_unique( $ids ) );
			$tax_query[] = $ids ? [
				'taxonomy'         => self::TAX_SECTION,
				'field'            => 'term_id',
				'terms'            => $ids,
				'include_children' => true,
			] : [
				'taxonomy' => self::TAX_SECTION,
				'field'    => 'term_id',
				'terms'    => [ -1 ],
			];
		}

		if ( $tax_query ) $q->set( 'tax_query', $tax_query );
	}

	/* ---------------- Bulk actions ---------------- */

	public static function register_bulk_actions( array $actions ) : array {
		$actions['jprm_assign_section']   = __( 'Assign to Section…', 'jprm' );
		$actions['jprm_unassign_section'] = __( 'Unassign from Section', 'jprm' );
		return $actions;
	}

	public static function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ) : string {
		if ( $action !== 'jprm_assign_section' && $action !== 'jprm_unassign_section' ) {
			return $redirect_url;
		}

		$done = 0;

		if ( 'jprm_unassign_section' === $action ) {
			foreach ( $post_ids as $pid ) {
				if ( ! current_user_can( 'edit_post', $pid ) ) continue;
				wp_set_post_terms( $pid, [], self::TAX_SECTION, false );
				delete_post_meta( $pid, self::META_SECTION_ORDER );
				$done++;
			}
			return add_query_arg( [ 'jprm_bulk_unassigned' => $done ], $redirect_url );
		}

		$target_section = isset( $_REQUEST['jprm_target_section'] ) ? intval( $_REQUEST['jprm_target_section'] ) : 0; // phpcs:ignore
		if ( $target_section <= 0 ) {
			return add_query_arg( [ 'jprm_bulk_error' => 1 ], $redirect_url );
		}

		// Append to end of that section's order
		$existing = new \WP_Query( [
			'post_type'      => self::CPT,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'tax_query'      => [[
				'taxonomy' => self::TAX_SECTION,
				'field'    => 'term_id',
				'terms'    => [ $target_section ],
				'include_children' => false,
			]],
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		$next = 0;
		foreach ( (array) $existing->posts as $sid ) {
			$next = max( $next, intval( get_post_meta( $sid, self::META_SECTION_ORDER, true ) ) );
		}

		foreach ( $post_ids as $pid ) {
			if ( ! current_user_can( 'edit_post', $pid ) ) continue;
			wp_set_post_terms( $pid, [ $target_section ], self::TAX_SECTION, false );
			$next++;
			update_post_meta( $pid, self::META_SECTION_ORDER, $next );
			$done++;
		}

		return add_query_arg( [ 'jprm_bulk_assigned' => $done ], $redirect_url );
	}

	public static function bulk_admin_notice() : void {
		if ( isset( $_GET['jprm_bulk_unassigned'] ) ) { // phpcs:ignore
			$c = intval( $_GET['jprm_bulk_unassigned'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Unassigned %d item.', 'Unassigned %d items.', $c, 'jprm' ), $c ) ) . '</p></div>';
		}
		if ( isset( $_GET['jprm_bulk_assigned'] ) ) { // phpcs:ignore
			$c = intval( $_GET['jprm_bulk_assigned'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Assigned %d item.', 'Assigned %d items.', $c, 'jprm' ), $c ) ) . '</p></div>';
		}
		if ( isset( $_GET['jprm_bulk_error'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please choose a Section for bulk assign.', 'jprm' ) . '</p></div>';
		}
	}

	public static function inject_bulk_assign_selector() : void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'edit-' . self::CPT ) return;

		$menus    = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		$sections = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false ] );

		$by_menu = [];
		if ( ! is_wp_error( $sections ) ) {
			foreach ( $sections as $t ) {
				$owner = intval( get_term_meta( $t->term_id, self::META_MENU_OWNER, true ) );
				$by_menu[ $owner ][] = $t;
			}
		}

		?>
		<script>
		(function(){
			/* ------- Bulk-assign section selector ------- */
			function buildSelectHtml(){
				var html = '<select name="jprm_target_section" id="jprm_target_section" style="margin-left:6px;">';
				html += '<option value="0"><?php echo esc_js( __( '— choose Section —', 'jprm' ) ); ?></option>';
				<?php
				if ( ! is_wp_error( $menus ) ) {
					foreach ( $menus as $m ) {
						printf( "html += '<optgroup label=\"%s\">';\n", esc_js( $m->name ) );
						if ( ! empty( $by_menu[ $m->term_id ] ) ) {
							$tree = self::flatten_sections_with_depth( $by_menu[ $m->term_id ] );
							foreach ( $tree as $row ) {
								printf(
									"html += '<option value=\"%d\">%s%s</option>';\n",
									intval( $row['id'] ),
									esc_js( str_repeat( '— ', $row['depth'] ) ),
									esc_js( $row['name'] )
								);
							}
						}
						echo "html += '</optgroup>';\n";
					}
				}
				?>
				html += '</select>';
				return html;
			}
			function ensureSelector($which){
				var $bulk = jQuery($which);
				if (!$bulk.length) return;
				if ($bulk.find('#jprm_target_section').length) return;
				$bulk.append( buildSelectHtml() );
			}
			jQuery(document).on('change', 'select[name="action"], select[name="action2"]', function(){
				var val = jQuery(this).val();
				if (val === 'jprm_assign_section') {
					if (this.name === 'action') ensureSelector('#bulk-action-selector-top');
					if (this.name === 'action2') ensureSelector('#bulk-action-selector-bottom');
				} else {
					jQuery('#jprm_target_section').remove();
				}
			});

			/* ------- Toggle for "Multiple prices (N)" ------- */
			jQuery(document).on('click', '.jprm-multi-toggle', function(e){
				e.preventDefault();
				var target = jQuery(this).attr('data-target');
				if (!target) return;
				jQuery('#'+target).toggle();
			});
		})();
		</script>
		<?php
	}

	/* ---------------- Helpers ---------------- */

	private static function derive_menu_name_for_item( int $post_id ) : string {
		$terms = wp_get_post_terms( $post_id, self::TAX_SECTION, [ 'fields' => 'all' ] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) return '';
		$first = $terms[0];
		$owner_menu_id = intval( get_term_meta( $first->term_id, self::META_MENU_OWNER, true ) );
		if ( ! $owner_menu_id ) return '';
		$m = get_term( $owner_menu_id, self::TAX_MENU );
		return ( $m && ! is_wp_error( $m ) ) ? $m->name : '';
	}

	/**
	 * Resolve "pl-2" etc. to display text using your Labels Store.
	 * Uses JPRM_Labels_Store::resolve($key) which returns ['label_text'=>..., 'icon_id'=>...].
	 */
	private static function resolve_label_ref( string $label_ref ) : string {
		$label_ref = trim( $label_ref );
		if ( $label_ref === '' ) return '';

		try {
			if ( class_exists( 'JPRM_Labels_Store' ) && method_exists( 'JPRM_Labels_Store', 'resolve' ) ) {
				$res = \JPRM_Labels_Store::resolve( $label_ref );
				if ( is_array( $res ) ) {
					$txt = (string) ( $res['label_text'] ?? '' );
					if ( $txt !== '' ) return $txt;
				}
			}
		} catch ( \Throwable $e ) {
			/* silent in UI */
		}

		// If store unavailable, show the raw code so something is visible.
		return $label_ref;
	}

	/**
	 * Render Price(s) column using ONLY meta 'jprm_price'.
	 * Accepts:
	 *  - {"mode":"single","price":"3",...}
	 *  - {"mode":"multi","rows":[{"label_ref":"pl-1","value":"8,50"}, ...]}
	 *    (also supports rows[].label if present)
	 */
	private static function render_prices_cell( int $post_id ) : string {
		$raw = get_post_meta( $post_id, self::META_PRICE_PRIMARY, true );

		// If it's a JSON string, decode to array
		if ( is_string( $raw ) && $raw !== '' ) {
			$dec = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $dec ) ) {
				$raw = $dec;
			}
		}

		// Array path (preferred)
		if ( is_array( $raw ) ) {
			$mode = isset( $raw['mode'] ) ? strtolower( (string) $raw['mode'] ) : '';

			if ( $mode === 'single' ) {
				$p = isset( $raw['price'] ) ? (string) $raw['price'] : '';
				return $p !== '' ? esc_html( $p ) : '—';
			}

			if ( $mode === 'multi' ) {
				$list = is_array( $raw['rows'] ?? null ) ? $raw['rows'] : [];
				if ( ! $list ) return '—';

				$lines = [];
				foreach ( $list as $row ) {
					$label = '';
					if ( isset( $row['label'] ) && $row['label'] !== '' ) {
						$label = (string) $row['label'];
					} elseif ( isset( $row['label_ref'] ) && $row['label_ref'] !== '' ) {
						$label = self::resolve_label_ref( (string) $row['label_ref'] );
					}
					$price = isset( $row['value'] ) ? trim( (string) $row['value'] ) : '';

					if ( $label !== '' && $price !== '' ) {
						$lines[] = esc_html( $label . ' — ' . $price );
					} elseif ( $price !== '' ) {
						$lines[] = esc_html( $price );
					}
				}
				if ( ! $lines ) return '—';

				// Collapsed view: "Multiple prices (N)" → click to toggle full list
				$target_id = 'jprm-mprices-' . $post_id;
				$count     = count( $lines );
				$link      = sprintf(
					'<a href="#" class="jprm-multi-toggle" data-target="%s">%s</a>',
					esc_attr( $target_id ),
					esc_html( sprintf( __( 'Multiple prices (%d)', 'jprm' ), $count ) )
				);

				$list_html = '<div id="' . esc_attr( $target_id ) . '" style="display:none;margin-top:4px;">' .
				             implode( '<br>', $lines ) .
				             '</div>';

				return $link . $list_html;
			}

			// Unknown shape but array contains a 'price'
			if ( isset( $raw['price'] ) && $raw['price'] !== '' ) {
				return esc_html( (string) $raw['price'] );
			}

			return '—';
		}

		// Scalar path (number stored directly in meta)
		if ( is_scalar( $raw ) && $raw !== '' && $raw !== null ) {
			return esc_html( (string) $raw );
		}

		return '—';
	}

	/**
	 * Flatten a list of section terms into [ [id,name,depth], ... ] respecting hierarchy.
	 * Input array may be unordered; we rebuild a mini tree first.
	 */
	private static function flatten_sections_with_depth( array $terms ) : array {
		$by = [];
		foreach ( $terms as $t ) $by[ $t->term_id ] = [ 't' => $t, 'children' => [] ];

		// build children links
		foreach ( $by as $id => &$n ) {
			$p = $n['t']->parent;
			if ( $p && isset( $by[ $p ] ) ) {
				$by[ $p ]['children'][] = &$n;
			}
		}
		unset( $n );

		// roots = terms whose parent is 0 or not in this subset
		$roots = array_filter( $by, static function( $n ) use ( $by ) {
			$p = $n['t']->parent;
			return ! $p || ! isset( $by[ $p ] );
		} );

		$out = [];
		$walk = static function( $nodes, $d ) use ( &$out, &$walk ) {
			foreach ( $nodes as $n ) {
				$out[] = [ 'id' => $n['t']->term_id, 'name' => $n['t']->name, 'depth' => $d ];
				if ( ! empty( $n['children'] ) ) {
					$walk( $n['children'], $d + 1 );
				}
			}
		};
		$walk( $roots, 0 );
		return $out;
	}
}
