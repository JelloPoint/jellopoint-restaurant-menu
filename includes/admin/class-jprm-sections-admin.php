<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sections_Admin {

	const TAX_SECTION         = 'jprm_section';
	const TAX_MENU            = 'jprm_menu';
	const META_MENU_OWNER     = '_jprm_menu_term_id';
	const META_SECTION_ORDER  = '_jprm_section_order';

	public static function init() : void {
		// Columns
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_columns',          [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::TAX_SECTION . '_custom_column',         [ __CLASS__, 'print_column' ], 10, 3 );
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_sortable_columns', [ __CLASS__, 'sortable_columns' ] );

		// Toolbar filter (server-side, inside real list-table form)
		add_action( 'restrict_manage_terms', [ __CLASS__, 'toolbar_filter' ], 10, 1 );

		// Single source of truth for query (tree vs flat, filter, sort)
		add_action( 'pre_get_terms', [ __CLASS__, 'shape_terms_query' ] );

		// Add/Edit fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish + **self-healing** filter injector (guarantees dropdown is present & works)
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'admin_head_assets' ] );

		self::hook_terms_order_and_filter();
		add_filter( 'terms_clauses', [ __CLASS__, 'force_admin_order' ], 20, 3 );

	}

	/* ================= Columns ================= */

	public static function columns( $cols ) {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) {
				$new['jprm_menu']  = __( 'Menu',  'jprm' );
				$new['jprm_order'] = __( 'Order', 'jprm' );
			}
		}
		if ( ! isset( $new['jprm_menu'] ) )  $new['jprm_menu']  = __( 'Menu',  'jprm' );
		if ( ! isset( $new['jprm_order'] ) ) $new['jprm_order'] = __( 'Order', 'jprm' );
		if ( isset( $new['slug'] ) ) unset( $new['slug'] ); // cleaner UI
		return $new;
	}

	public static function sortable_columns( $cols ) {
		$cols['jprm_order'] = 'jprm_order'; // ?orderby=jprm_order
		return $cols;
	}

	public static function print_column( $out, $column_name, $term_id ) {
		if ( 'jprm_menu' === $column_name ) {
			$owner = (int) get_term_meta( $term_id, self::META_MENU_OWNER, true );
			if ( ! $owner ) { echo '—'; return; }
			$menu = get_term( $owner, self::TAX_MENU );
			echo ( $menu && ! is_wp_error( $menu ) ) ? esc_html( $menu->name ) : '—';
			return;
		}
		if ( 'jprm_order' === $column_name ) {
			$ord = get_term_meta( $term_id, self::META_SECTION_ORDER, true );
			echo ( $ord !== '' && $ord !== null ) ? (int) $ord : '—';
			return;
		}
	}

	/* ================= Toolbar filter (native + auto-submit) ================= */

	public static function toolbar_filter( $taxonomy ) : void {
		if ( $taxonomy !== self::TAX_SECTION ) return;

		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus    = get_terms( [
			'taxonomy'   => self::TAX_MENU,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		echo '<div class="alignleft actions jprm-filter-wrap">';
		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform" onchange="this.form.submit()">';
		echo '<option value="0">' . esc_html__( 'All Menus', 'jprm' ) . '</option>';
		if ( ! is_wp_error( $menus ) ) {
			foreach ( $menus as $m ) {
				printf(
					'<option value="%d"%s>%s</option>',
					(int) $m->term_id,
					selected( $selected, (int) $m->term_id, false ),
					esc_html( $m->name )
				);
			}
		}
		echo '</select>';
		echo '</div>';
	}

	/* ================= Query shaping (tree vs flat, filter, sort) ================= */

	public static function shape_terms_query( \WP_Term_Query $q ) : void {
		if ( ! is_admin() ) return;

		$taxonomies = (array) ( $q->query_vars['taxonomy'] ?? [] );
		if ( ! in_array( self::TAX_SECTION, $taxonomies, true ) ) return;

		// Read menu param robustly
		$menu_id = 0;
		if ( isset( $_GET['jprm_filter_menu'] ) ) { // phpcs:ignore
			$menu_id = (int) $_GET['jprm_filter_menu']; // phpcs:ignore
		} elseif ( isset( $_REQUEST['jprm_filter_menu'] ) ) { // fallback
			$menu_id = (int) $_REQUEST['jprm_filter_menu']; // phpcs:ignore
		}

		$orderby = isset( $_GET['orderby'] ) ? (string) $_GET['orderby'] : '';               // phpcs:ignore
		$order   = isset( $_GET['order'] )   ? strtoupper( (string) $_GET['order'] ) : 'ASC'; // phpcs:ignore

		// Default: TREE (no menu filter + not sorting by Order)
		if ( $menu_id <= 0 && $orderby !== 'jprm_order' ) {
			$q->query_vars['hierarchical'] = true;
			$q->query_vars['orderby']      = 'name';
			$q->query_vars['order']        = 'ASC';
			unset( $q->query_vars['meta_key'] );
			$q->query_vars['meta_query'] = [];
			return;
		}

		// Flat list when filtering or sorting by Order.
		$q->query_vars['hierarchical'] = false;
		$q->query_vars['hide_empty']   = false;

		// If a menu is selected, filter by owner meta and default to order ASC unless user clicked Order.
		if ( $menu_id > 0 ) {
			$mq   = (array) ( $q->query_vars['meta_query'] ?? [] );
			$mq[] = [ 'key' => self::META_MENU_OWNER, 'value' => (string) $menu_id ];
			$q->query_vars['meta_query'] = $mq;

			if ( $orderby !== 'jprm_order' ) {
				$q->query_vars['meta_key'] = self::META_SECTION_ORDER;
				$q->query_vars['orderby']  = 'meta_value_num';
				$q->query_vars['order']    = 'ASC';
			}
		}

		// If user clicked the "Order" header: sort by section order ASC/DESC.
		if ( $orderby === 'jprm_order' ) {
			$q->query_vars['meta_key'] = self::META_SECTION_ORDER;
			$q->query_vars['orderby']  = 'meta_value_num';
			$q->query_vars['order']    = ( $order === 'DESC' ) ? 'DESC' : 'ASC';
		}
	}

	public static function force_admin_order( $pieces, $taxonomies, $args ) : array {
	if ( ! is_admin() ) return $pieces;

	// Only our taxonomy on the admin list screen.
	if ( empty( $taxonomies ) || ! in_array( self::TAX_SECTION, (array) $taxonomies, true ) ) {
		return $pieces;
	}

	// We only want to force this on the taxonomy management screen.
	// get_current_screen() is safe in admin; guard in case it's null.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->taxonomy ) || $screen->taxonomy !== self::TAX_SECTION ) {
		return $pieces;
	}

	// Read the selected Menu (toolbar filter).
	$selected_menu = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore

	// We want: when a Menu is selected OR when the user clicked the "Order" header,
	// force ORDER BY _jprm_section_order ASC, then name ASC to break ties.
	$orderby_clicked = isset( $_GET['orderby'] ) && $_GET['orderby'] === 'jprm_order'; // phpcs:ignore

	if ( $selected_menu > 0 || $orderby_clicked ) {
		global $wpdb;

		// Join the order meta explicitly under a stable alias.
		if ( strpos( $pieces['join'] ?? '', 'tm_sort' ) === false ) {
			$meta_key = esc_sql( self::META_SECTION_ORDER );
			$pieces['join'] .= " LEFT JOIN {$wpdb->termmeta} AS tm_sort
				ON (tm_sort.term_id = t.term_id AND tm_sort.meta_key = '{$meta_key}')";
		}

		// Enforce deterministic order: numeric meta ASC, then name.
		$pieces['orderby'] = " CAST(tm_sort.meta_value AS UNSIGNED) ASC, t.name ASC ";
	}

	return $pieces;
}


	/* ================= Add/Edit fields ================= */

	public static function add_field() {
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		?>
		<div class="form-field term-owner-wrap">
			<label for="jprm_owner_menu"><?php esc_html_e( 'Owner Menu', 'jprm' ); ?></label>
			<select name="jprm_owner_menu" id="jprm_owner_menu">
				<option value="0"><?php esc_html_e( '— choose —', 'jprm' ); ?></option>
				<?php if ( ! is_wp_error( $menus ) ) foreach ( $menus as $m ) : ?>
					<option value="<?php echo (int) $m->term_id; ?>"><?php echo esc_html( $m->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'If you set a parent later, ownership will inherit from the parent.', 'jprm' ); ?></p>
		</div>
		<?php
	}

	public static function edit_field( $term, $taxonomy ) {
		$menus   = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		$current = (int) get_term_meta( $term->term_id, self::META_MENU_OWNER, true );
		$parent  = (int) $term->parent;
		$hint    = $parent
			? __( 'Owner usually inherits from parent; changing it cascades to children.', 'jprm' )
			: __( 'Choose the menu that owns this section.', 'jprm' );
		?>
		<tr class="form-field term-owner-wrap">
			<th scope="row"><label for="jprm_owner_menu"><?php esc_html_e( 'Owner Menu', 'jprm' ); ?></label></th>
			<td>
				<select name="jprm_owner_menu" id="jprm_owner_menu">
					<option value="0"><?php esc_html_e( '— choose —', 'jprm' ); ?></option>
					<?php if ( ! is_wp_error( $menus ) ) foreach ( $menus as $m ) : ?>
						<option value="<?php echo (int) $m->term_id; ?>" <?php selected( $current, (int) $m->term_id ); ?>>
							<?php echo esc_html( $m->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html( $hint ); ?></p>
			</td>
		</tr>
		<?php
	}

	/* ================= Save & cascade ================= */

	public static function save_on_create( $term_id, $tt_id ) {
		$owner = isset( $_POST['jprm_owner_menu'] ) ? (int) $_POST['jprm_owner_menu'] : 0; // phpcs:ignore
		$term  = get_term( $term_id, self::TAX_SECTION );
		if ( ! $term || is_wp_error( $term ) ) return;

		// Inherit from parent if any
		if ( $term->parent ) {
			$po = (int) get_term_meta( $term->parent, self::META_MENU_OWNER, true );
			if ( $po ) $owner = $po;
		}
		if ( $owner > 0 ) update_term_meta( $term_id, self::META_MENU_OWNER, $owner );
	}

	public static function save_on_edit( $term_id, $tt_id ) {
		$term = get_term( $term_id, self::TAX_SECTION );
		if ( ! $term || is_wp_error( $term ) ) return;

		$chosen = isset( $_POST['jprm_owner_menu'] ) ? (int) $_POST['jprm_owner_menu'] : 0; // phpcs:ignore
		$final  = $chosen;

		// If parent exists, force inheritance
		if ( $term->parent ) {
			$po = (int) get_term_meta( $term->parent, self::META_MENU_OWNER, true );
			if ( $po ) $final = $po;
		}

		$current = (int) get_term_meta( $term_id, self::META_MENU_OWNER, true );
		if ( $final !== $current ) {
			update_term_meta( $term_id, self::META_MENU_OWNER, $final );
			self::cascade_children( $term_id, $final );
		}
	}

	private static function cascade_children( int $parent_id, int $owner_menu_id ) : void {
		$children = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'parent'     => $parent_id,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $children ) || empty( $children ) ) return;

		foreach ( $children as $child ) {
			if ( (int) get_term_meta( $child->term_id, self::META_MENU_OWNER, true ) !== $owner_menu_id ) {
				update_term_meta( $child->term_id, self::META_MENU_OWNER, $owner_menu_id );
			}
			self::cascade_children( (int) $child->term_id, $owner_menu_id );
		}
	}
/**
 * Extra safety: on the jprm_section admin screen, force ordering by
 * _jprm_section_order and respect ?jprm_filter_menu= when present.
 * Runs only on the taxonomy list screen; does not affect REST.
 */
public static function hook_terms_order_and_filter() : void {
	add_filter( 'get_terms_args', function( $args, $taxonomies ) {
		if ( ! is_admin() ) return $args;

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen || $screen->taxonomy !== self::TAX_SECTION ) return $args;
		if ( is_array( $taxonomies ) && ! in_array( self::TAX_SECTION, $taxonomies, true ) ) return $args;
		if ( isset( $args['fields'] ) && $args['fields'] === 'count' ) return $args;

		// Default ordering key
		$args['meta_key'] = self::META_SECTION_ORDER;
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'ASC';

		// Respect toolbar filter
		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id > 0 ) {
			$mq   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
			$mq[] = [ 'key' => self::META_MENU_OWNER, 'value' => (string) $menu_id ];
			$args['meta_query'] = $mq;
		}

		return $args;
	}, 10, 2 );
}

	/* ================= UI polish + self-healing injector ================= */

	public static function admin_head_assets() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;

		// CSS
		echo '<style>
			.taxonomy-' . esc_attr( self::TAX_SECTION ) . ' .form-field.term-slug-wrap,
			.taxonomy-' . esc_attr( self::TAX_SECTION ) . ' .term-slug-wrap { display:none!important; }
			.fixed .column-jprm_order{ width:90px; text-align:right; }
			.jprm-filter-wrap, .jprm-sections-filter { margin-right:8px; }
		</style>';

		// Build <option>s server-side for the self-healing injector
		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus    = get_terms( [
			'taxonomy'   => self::TAX_MENU,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		$options  = '<option value="0">' . esc_html__( 'All Menus', 'jprm' ) . '</option>';
		if ( ! is_wp_error( $menus ) ) {
			foreach ( $menus as $m ) {
				$sel = ( $selected === (int) $m->term_id ) ? ' selected' : '';
				$options .= '<option value="' . (int) $m->term_id . '"' . $sel . '>' . esc_html( $m->name ) . '</option>';
			}
		}
		?>
		<script>
		(function(){
		  // If the toolbar select failed to render (some admin skins), inject it and wire navigation.
		  function ensureToolbarFilter(){
		    var form = document.getElementById('posts-filter');
		    if (!form) return;

		    var top = form.querySelector('.tablenav.top .actions') || form.querySelector('.tablenav.top');
		    if (!top) return;

		    if (document.getElementById('jprm_filter_menu')) return; // already present (from restrict_manage_terms)

		    var wrap = document.createElement('div');
		    wrap.className = 'alignleft actions jprm-sections-filter';
		    wrap.innerHTML =
		      '<label class="screen-reader-text" for="jprm_filter_menu"><?php echo esc_js(__('Filter by Menu','jprm')); ?></label>' +
		      '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform"><?php echo $options; ?></select>';
		    top.prepend(wrap);

		    var sel = wrap.querySelector('#jprm_filter_menu');
	   	    if (sel) {
		      sel.addEventListener('change', function(){
		        var url = new URL(window.location.href);
		        url.searchParams.set('jprm_filter_menu', this.value || '0');
		        url.searchParams.delete('paged');
		        if ((this.value||'0') === '0') {
		          url.searchParams.delete('orderby');
		          url.searchParams.delete('order');
		        }
		        window.location.assign(url.toString());
		      });
		    }
		  }
		  document.addEventListener('DOMContentLoaded', ensureToolbarFilter);
		})();
		</script>
		<?php
	}
}
