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

		// Toolbar filter (server-side output only; no JS injection)
		add_action( 'restrict_manage_terms', [ __CLASS__, 'toolbar_filter' ], 10, 1 );

		// Shape list-table query (single source of truth; no raw SQL)
		add_filter( 'terms_list_table_query_args', [ __CLASS__, 'list_table_args' ], 10, 2 );

		// Add/Edit fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// Small UI polish
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'admin_css' ] );
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

	/* ================= Toolbar filter ================= */

	public static function toolbar_filter( $taxonomy ) : void {
		if ( $taxonomy !== self::TAX_SECTION ) return;

		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus    = get_terms( [
			'taxonomy'   => self::TAX_MENU,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform">';
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
		// We rely on the default "Filter" submit button in the toolbar.
	}

	/* ================= Query shaping (single source of truth) ================= */

	public static function list_table_args( $args, $taxonomies ) {
		if ( empty( $taxonomies ) || ! in_array( self::TAX_SECTION, (array) $taxonomies, true ) ) return $args;
		if ( ! is_admin() ) return $args;

		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$orderby = isset( $_GET['orderby'] ) ? (string) $_GET['orderby'] : '';              // phpcs:ignore
		$order   = isset( $_GET['order'] )   ? strtoupper( (string) $_GET['order'] ) : 'ASC'; // phpcs:ignore

		// CASE A — default: All Menus + no "Order" click → keep the tree (do not touch args)
		if ( $menu_id <= 0 && $orderby !== 'jprm_order' ) {
			return $args;
		}

		// From here, we switch to a FLAT, reliable list (tree + meta filtering/sorting is fragile)
		$args['hierarchical'] = false;
		$args['hide_empty']   = false;

		// If a Menu is selected, filter by owner meta
		if ( $menu_id > 0 ) {
			$mq = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
			$mq[] = [
				'key'   => self::META_MENU_OWNER,
				'value' => (string) $menu_id,
			];
			$args['meta_query'] = $mq;

			// Default ordering under a selected menu unless user clicked "Order"
			if ( $orderby !== 'jprm_order' ) {
				$args['meta_key'] = self::META_SECTION_ORDER;
				$args['orderby']  = 'meta_value_num'; // single key avoids duplicate ASC
				$args['order']    = 'ASC';
			}
		}

		// If user clicked the "Order" header: flat + sort by order meta (ASC/DESC)
		if ( $orderby === 'jprm_order' ) {
			$args['meta_key'] = self::META_SECTION_ORDER;
			$args['orderby']  = 'meta_value_num';
			$args['order']    = ( $order === 'DESC' ) ? 'DESC' : 'ASC';
		}

		return $args;
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

	/* ================= UI polish ================= */

	public static function admin_css() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;
		echo '<style>
			.taxonomy-' . esc_attr( self::TAX_SECTION ) . ' .form-field.term-slug-wrap,
			.taxonomy-' . esc_attr( self::TAX_SECTION ) . ' .term-slug-wrap { display:none!important; }
			.fixed .column-jprm_order{ width:90px; text-align:right; }
		</style>';
	}
}
