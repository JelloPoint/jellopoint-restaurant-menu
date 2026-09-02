<?php
namespace JelloPoint\RestaurantMenu\Admin;

use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sections_Admin {

	const TAX_SECTION         = 'jprm_section';
	const TAX_MENU            = 'jprm_menu';
	const META_MENU_OWNER     = '_jprm_menu_term_id';
	const META_SECTION_ORDER  = '_jprm_section_order';
	const META_ITEM_SEPARATOR = '_jprm_item_separator';
	const META_DISABLE_ITEM_SEPARATOR = '_jprm_disable_item_separator';

	public static function init() : void {
		// Columns
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_columns',          [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::TAX_SECTION . '_custom_column',         [ __CLASS__, 'print_column' ], 10, 3 );
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_sortable_columns', [ __CLASS__, 'sortable_columns' ] );

		// Toolbar filter (server-side, inside real list-table form)
		add_action( 'restrict_manage_terms', [ __CLASS__, 'toolbar_filter' ], 10, 1 );

		// Single source of truth for query (tree vs flat, filter, sort)
		add_action( 'pre_get_terms', [ __CLASS__, 'shape_terms_query' ] );

		// Add/Edit fields (shared Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish + **self-healing** filter injector (guarantees dropdown is present & works)
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'admin_head_assets' ] );

		add_filter( 'terms_clauses', [ __CLASS__, 'force_admin_order' ], 999, 3 );

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
			$names = [];
			foreach ( self::menu_ids_for_section( (int) $term_id ) as $menu_id ) {
				$menu = get_term( $menu_id, self::TAX_MENU );
				if ( $menu && ! is_wp_error( $menu ) ) { $names[] = (string) $menu->name; }
			}
			echo $names ? esc_html( implode( ', ', $names ) ) : '—';
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
			$q->query_vars['include'] = Menu_Structure_Store::section_ids( $menu_id );
			$q->query_vars['orderby'] = 'include';
			unset( $q->query_vars['meta_key'] );
			$q->query_vars['meta_query'] = [];
		}

		// If user clicked the "Order" header: sort by section order ASC/DESC.
		if ( $orderby === 'jprm_order' ) {
			$q->query_vars['meta_key'] = self::META_SECTION_ORDER;
			$q->query_vars['orderby']  = 'meta_value_num';
			$q->query_vars['order']    = ( $order === 'DESC' ) ? 'DESC' : 'ASC';
		}
	}

public static function force_admin_order( $pieces, $taxonomies, $args ) : array {
	// Only run on the jprm_section terms list screen in wp-admin.
	if ( ! is_admin() ) return $pieces;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ( ! $screen || empty($screen->taxonomy) || $screen->taxonomy !== self::TAX_SECTION ) {
		return $pieces; // do NOT touch other taxonomies (e.g., jprm_menu, wp_theme)
	}

	// Only if the queried taxonomy actually includes jprm_section.
	$taxes = is_array($taxonomies) ? $taxonomies : [];
	if ( ! in_array( self::TAX_SECTION, $taxes, true ) ) {
		return $pieces;
	}

	global $wpdb;

	$selected_menu   = isset($_GET['jprm_filter_menu']) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
	$orderby_clicked = ( isset($_GET['orderby']) && $_GET['orderby'] === 'jprm_order' );      // phpcs:ignore

	// COUNT(*) passes must never receive ORDER BY injections (Core doesn't add one there).
	$is_count = ( isset($args['fields']) && $args['fields'] === 'count' );
	if ( $selected_menu > 0 ) { return $pieces; }

	// Always have the ORDER meta available when we plan to order (non-count only).
	if ( ! $is_count && strpos($pieces['join'] ?? '', 'tm_sort') === false ) {
		$meta_key_order = esc_sql(self::META_SECTION_ORDER);
		$pieces['join'] .= " LEFT JOIN {$wpdb->termmeta} AS tm_sort
			ON (tm_sort.term_id = t.term_id AND tm_sort.meta_key = '{$meta_key_order}')";
	}

	// "All Menus" view: only re-order when the user clicked the Order column; never for count.
	if ( $orderby_clicked && ! $is_count ) {
		if ( empty($pieces['groupby']) ) {
			$pieces['groupby'] = ' t.term_id ';
		} elseif ( strpos($pieces['groupby'], 't.term_id') === false ) {
			$pieces['groupby'] .= ', t.term_id';
		}
		$pieces['orderby'] = ' ORDER BY CAST(tm_sort.meta_value AS UNSIGNED), t.name ';
	}

	return $pieces;
}

	/* ================= Add/Edit fields ================= */

	public static function add_field() {
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		?>
		<div class="form-field term-owner-wrap">
			<label for="jprm_owner_menus"><?php esc_html_e( 'Menus', 'jprm' ); ?></label>
			<select name="jprm_owner_menus[]" id="jprm_owner_menus" multiple size="6">
				<?php if ( ! is_wp_error( $menus ) ) foreach ( $menus as $m ) : ?>
					<option value="<?php echo (int) $m->term_id; ?>" data-daily="<?php echo '1' === (string) get_term_meta( (int) $m->term_id, '_jprm_is_daily_menu', true ) ? '1' : '0'; ?>"><?php echo esc_html( $m->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Select one or more Menus. Each Menu keeps its own Section content and order.', 'jprm' ); ?></p>
		</div>
		<div class="form-field term-item-separator-wrap jprm-daily-section-option">
			<label for="jprm_item_separator"><?php esc_html_e( 'Item Separator Override', 'jellopoint-restaurant-menu' ); ?></label>
			<input type="text" name="jprm_item_separator" id="jprm_item_separator" value="" placeholder="or" />
			<label><input type="checkbox" name="jprm_disable_item_separator" value="1" /> <?php esc_html_e( 'Do not show separators in this Section', 'jellopoint-restaurant-menu' ); ?></label>
			<p class="description"><?php esc_html_e( 'Leave empty to use the Daily Menu default.', 'jellopoint-restaurant-menu' ); ?></p>
		</div>
		<?php self::item_separator_dependency_script(); ?>
		<?php
	}

	public static function edit_field( $term, $taxonomy ) {
		$menus   = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		$current = self::menu_ids_for_section( (int) $term->term_id );
		$item_separator = (string) get_term_meta( $term->term_id, self::META_ITEM_SEPARATOR, true );
		$disable_item_separator = '1' === (string) get_term_meta( $term->term_id, self::META_DISABLE_ITEM_SEPARATOR, true );
		$hint = __( 'Select one or more Menus. Each Menu keeps its own Section content and order.', 'jprm' );
		?>
		<tr class="form-field term-owner-wrap">
			<th scope="row"><label for="jprm_owner_menus"><?php esc_html_e( 'Menus', 'jprm' ); ?></label></th>
			<td>
				<select name="jprm_owner_menus[]" id="jprm_owner_menus" multiple size="6">
					<?php if ( ! is_wp_error( $menus ) ) foreach ( $menus as $m ) : ?>
						<option value="<?php echo (int) $m->term_id; ?>" data-daily="<?php echo '1' === (string) get_term_meta( (int) $m->term_id, '_jprm_is_daily_menu', true ) ? '1' : '0'; ?>" <?php echo in_array( (int) $m->term_id, $current, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $m->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html( $hint ); ?></p>
			</td>
		</tr>
		<tr class="form-field term-item-separator-wrap jprm-daily-section-option">
			<th scope="row"><label for="jprm_item_separator"><?php esc_html_e( 'Item Separator Override', 'jellopoint-restaurant-menu' ); ?></label></th>
			<td><input type="text" name="jprm_item_separator" id="jprm_item_separator" value="<?php echo esc_attr( $item_separator ); ?>" placeholder="or" /><p><label><input type="checkbox" name="jprm_disable_item_separator" value="1" <?php checked( $disable_item_separator ); ?> /> <?php esc_html_e( 'Do not show separators in this Section', 'jellopoint-restaurant-menu' ); ?></label></p><p class="description"><?php esc_html_e( 'Leave empty to use the Daily Menu default.', 'jellopoint-restaurant-menu' ); ?></p></td>
		</tr>
		<?php self::item_separator_dependency_script(); ?>
		<?php
	}

	/* ================= Save & cascade ================= */

	public static function save_on_create( $term_id, $tt_id ) {
		self::save_menu_relations( (int) $term_id );
		self::save_item_separator( (int) $term_id );
	}

	public static function save_on_edit( $term_id, $tt_id ) {
		self::save_menu_relations( (int) $term_id );
		self::save_item_separator( (int) $term_id );
	}

	private static function save_menu_relations( int $term_id ) : void {
		$selected = isset( $_POST['jprm_owner_menus'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['jprm_owner_menus'] ) ) : []; // phpcs:ignore
		$selected = array_values( array_unique( array_filter( $selected ) ) );
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false, 'fields' => 'ids' ] );
		if ( is_wp_error( $menus ) ) { return; }

		foreach ( array_map( 'intval', (array) $menus ) as $menu_id ) {
			if ( in_array( $menu_id, $selected, true ) ) {
				Menu_Structure_Store::attach_section( $menu_id, $term_id );
			} elseif ( in_array( $menu_id, self::menu_ids_for_section( $term_id ), true ) ) {
				Menu_Structure_Store::detach_section( $menu_id, $term_id );
			}
		}

		if ( $selected ) {
			update_term_meta( $term_id, self::META_MENU_OWNER, $selected[0] );
			self::ensure_section_order( $term_id, $selected[0] );
		} else {
			delete_term_meta( $term_id, self::META_MENU_OWNER );
		}
	}

	private static function menu_ids_for_section( int $term_id ) : array {
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false, 'fields' => 'ids' ] );
		if ( is_wp_error( $menus ) ) { return []; }
		$menu_ids = [];
		foreach ( array_map( 'intval', (array) $menus ) as $menu_id ) {
			if ( in_array( $term_id, Menu_Structure_Store::section_ids( $menu_id ), true ) ) { $menu_ids[] = $menu_id; }
		}
		return $menu_ids;
	}

	private static function save_item_separator( int $term_id ) : void {
		if ( ! isset( $_POST['jprm_item_separator'] ) ) { return; }
		$value = sanitize_text_field( wp_unslash( $_POST['jprm_item_separator'] ) );
		$disabled = ! empty( $_POST['jprm_disable_item_separator'] );
		if ( '' === $value ) { delete_term_meta( $term_id, self::META_ITEM_SEPARATOR ); }
		else { update_term_meta( $term_id, self::META_ITEM_SEPARATOR, $value ); }
		update_term_meta( $term_id, self::META_DISABLE_ITEM_SEPARATOR, $disabled ? '1' : '0' );
	}

	private static function item_separator_dependency_script() : void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function(){
			var owner = document.getElementById('jprm_owner_menus');
			var fields = document.querySelectorAll('.jprm-daily-section-option');
			if (!owner || !fields.length) return;
			function refresh(){
				var visible = Array.prototype.some.call(owner.selectedOptions, function(option){
					return option.getAttribute('data-daily') === '1';
				});
				fields.forEach(function(field){ field.style.display = visible ? '' : 'none'; });
			}
			owner.addEventListener('change', refresh); refresh();
		});
		</script>
		<?php
	}

	/**
	 * Ensure a section has an order value for its owning menu.
	 * Without _jprm_section_order set, get_terms() queries that sort by meta_key
	 * will EXCLUDE the term (inner join), causing it to disappear from Menu Builder / Elementor.
	 */
	private static function ensure_section_order( int $term_id, int $owner_menu_id ) : void {
		if ( $term_id <= 0 || $owner_menu_id <= 0 ) return;

		$existing = get_term_meta( $term_id, self::META_SECTION_ORDER, true );
		if ( $existing !== '' && $existing !== null ) return;

		$next = self::next_section_order_for_menu( $owner_menu_id );
		update_term_meta( $term_id, self::META_SECTION_ORDER, $next );

		// Best-effort cache clear for this term.
		clean_term_cache( [ $term_id ], self::TAX_SECTION );
	}

	/**
	 * Get the next sequential order number for sections belonging to a Menu.
	 */
	private static function next_section_order_for_menu( int $owner_menu_id ) : int {
		global $wpdb;

		$meta_owner = self::META_MENU_OWNER;
		$meta_order = self::META_SECTION_ORDER;

		// Max existing order for this menu (terms without order meta are ignored here by design).
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(tm_sort.meta_value AS UNSIGNED))
				 FROM {$wpdb->termmeta} tm_sort
				 INNER JOIN {$wpdb->termmeta} tm_owner
				   ON tm_owner.term_id = tm_sort.term_id
				  AND tm_owner.meta_key = %s
				 WHERE tm_sort.meta_key = %s
				   AND tm_owner.meta_value = %s",
				$meta_owner,
				$meta_order,
				(string) $owner_menu_id
			)
		);

		$max_i = (int) $max;
		return ( $max_i > 0 ) ? ( $max_i + 1 ) : 1;
	}
	/**
	 * Backfill missing _jprm_section_order values for all sections owned by a menu.
	 * This fixes already-created sections that show "—" in the Order column and are
	 * invisible to queries that use meta_key ordering (Menu Builder / Elementor).
	 */
	private static function backfill_missing_orders_for_menu( int $owner_menu_id ) : void {
		if ( $owner_menu_id <= 0 ) return;

		$terms = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_query' => [
				[ 'key' => self::META_MENU_OWNER, 'value' => (string) $owner_menu_id ],
			],
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) return;

		$next = self::next_section_order_for_menu( $owner_menu_id );

		foreach ( $terms as $tid ) {
			$tid = (int) $tid;
			if ( $tid <= 0 ) continue;

			$existing = get_term_meta( $tid, self::META_SECTION_ORDER, true );
			if ( $existing !== '' && $existing !== null ) continue;

			update_term_meta( $tid, self::META_SECTION_ORDER, $next );
			$next++;
		}

		clean_term_cache( array_map( 'intval', (array) $terms ), self::TAX_SECTION );
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
		if ( $selected > 0 ) self::backfill_missing_orders_for_menu( (int) $selected );
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
