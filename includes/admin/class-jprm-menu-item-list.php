<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin List Table enhancements for the Menu Items CPT.
 */
class Menu_Item_List {

	const CPT                = 'jprm_menu_item';
	const TAX_SECTION        = 'jprm_section';
	const TAX_MENU           = 'jprm_menu';

	const META_SECTION_ORDER = '_jprm_order_in_section';

	// Price meta keys (we'll probe these)
	const META_PRICE_SINGLE_PRI = '_jprm_price';
	const META_PRICE_SINGLE_ALT = [ '_price', 'price' ];
	const META_PRICE_MULTI      = '_jprm_prices'; // serialized array or JSON

	// Section -> menu owner term meta
	const META_MENU_OWNER       = '_jprm_menu_term_id';

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

		// Inject selector for bulk-assign
		add_action( 'admin_footer-edit.php', [ __CLASS__, 'inject_bulk_assign_selector' ] );
	}

	/* ---------------- Columns ---------------- */

	public static function columns( array $cols ) : array {
		// Remove the WP Date column entirely
		if ( isset( $cols['date'] ) ) unset( $cols['date'] );

		// Keep title first, then add ours
		$new = [];
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['jprm_menu']    = __( 'Menu', 'jprm' );
				$new['jprm_section'] = __( 'Section', 'jprm' );
				$new['jprm_prices']  = __( 'Price(s)', 'jprm' );
			}
		}

		// If title wasn't there for some reason, ensure ours exist
		$new += [
			'jprm_menu'    => __( 'Menu', 'jprm' ),
			'jprm_section' => __( 'Section', 'jprm' ),
			'jprm_prices'  => __( 'Price(s)', 'jprm' ),
		];

		return $new;
	}

	public static function sortable_columns( array $cols ) : array {
		// Leave these non-sortable (taxonomy/meta sorting is noisy/slow)
		return $cols;
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
			echo wp_kses_post( self::render_prices_cell( $post_id ) );
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
			$roots = array_filter( $by_id, static fn( $n ) => ! $n['term']->parent || ! isset( $by_id[ $n['term']->parent ] ) );

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

		if ( $action === 'jprm_unassign_section' ) {
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

	private static function render_prices_cell( int $post_id ) : string {
		// 1) Multi price array (preferred)
		$multi_raw = get_post_meta( $post_id, self::META_PRICE_MULTI, true );
		$multi = [];

		if ( is_array( $multi_raw ) ) {
			$multi = $multi_raw;
		} elseif ( is_string( $multi_raw ) && $multi_raw !== '' ) {
			// maybe JSON encoded array
			$dec = json_decode( $multi_raw, true );
			if ( is_array( $dec ) ) $multi = $dec;
		}

		if ( ! empty( $multi ) ) {
			$pieces = [];
			foreach ( $multi as $row ) {
				$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
				$price = isset( $row['price'] ) ? trim( (string) $row['price'] ) : '';
				if ( $label !== '' && $price !== '' ) { $pieces[] = sprintf( '%s %s', esc_html( $label ), esc_html( $price ) ); }
				elseif ( $price !== '' )            { $pieces[] = esc_html( $price ); }
			}
			if ( $pieces ) {
				$show = array_slice( $pieces, 0, 3 );
				$more = max( 0, count( $pieces ) - 3 );
				$out  = esc_html( implode( ' • ', $show ) );
				if ( $more ) $out .= ' <span class="description">+' . intval( $more ) . ' ' . esc_html__( 'more', 'jprm' ) . '</span>';
				return $out;
			}
		}

		// 2) Single price (primary key)
		$single = get_post_meta( $post_id, self::META_PRICE_SINGLE_PRI, true );
		if ( $single !== '' && $single !== null ) {
			return esc_html( (string) $single );
		}

		// 3) Single price (fallback keys)
		foreach ( self::META_PRICE_SINGLE_ALT as $alt ) {
			$v = get_post_meta( $post_id, $alt, true );
			if ( $v !== '' && $v !== null ) {
				return esc_html( (string) $v );
			}
		}

		// 4) Last resort: if a repository exists, try common static methods
		if ( class_exists( '\JelloPoint\RestaurantMenu\Storage\Price_Repository' ) ) {
			try {
				$repo = \JelloPoint\RestaurantMenu\Storage\Price_Repository::class;

				if ( method_exists( $repo, 'get_prices_for_post' ) ) {
					$prices = $repo::get_prices_for_post( $post_id );
				} elseif ( method_exists( $repo, 'for_post' ) ) {
					$prices = $repo::for_post( $post_id );
				} elseif ( method_exists( $repo, 'instance' ) ) {
					$inst = $repo::instance();
					$prices = method_exists( $inst, 'get_prices_for_post' ) ? $inst->get_prices_for_post( $post_id ) : [];
				} else {
					$prices = [];
				}

				if ( is_array( $prices ) && ! empty( $prices ) ) {
					$parts = [];
					foreach ( $prices as $p ) {
						$label = is_array( $p ) && isset( $p['label'] ) ? $p['label'] : '';
						$price = is_array( $p ) && isset( $p['price'] ) ? $p['price'] : ( is_scalar( $p ) ? $p : '' );
						if ( $label !== '' && $price !== '' ) { $parts[] = sprintf( '%s %s', esc_html( (string) $label ), esc_html( (string) $price ) ); }
						elseif ( $price !== '' )              { $parts[] = esc_html( (string) $price ); }
					}
					if ( $parts ) return esc_html( implode( ' • ', $parts ) );
				}
			} catch ( \Throwable $e ) {
				// fail silent in UI
			}
		}

		return '—';
	}

	private static function flatten_sections_with_depth( array $terms ) : array {
		$by = [];
		foreach ( $terms as $t ) $by[ $t->term_id ] = [ 't' => $t, 'children' => [] ];
		foreach ( $by as $id => &$n ) {
			$p = $n['t']->parent;
			if ( $p && isset( $by[ $p ] ) ) $by[ $p ]['children'][] = &$n;
		}
		unset( $n );
		$roots = array_filter( $by, static fn( $n ) => ! $n['t']->parent || ! isset( $by[ $n['t']->parent ] ) );

		$out = [];
		$walk = static function( $nodes, $d ) use ( &$out, &$walk ) {
			foreach ( $nodes as $n ) {
				$out[] = [ 'id' => $n['t']->term_id, 'name' => $n['t']->name, 'depth' => $d ];
				if ( ! empty( $n['children'] ) ) $walk( $n['children'], $d + 1 );
			}
		};
		$walk( $roots, 0 );
		return $out;
	}
}
