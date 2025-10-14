<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sections_Admin {

	const TAX_SECTION     = 'jprm_section';
	const TAX_MENU        = 'jprm_menu';
	const META_MENU_OWNER = '_jprm_menu_term_id';

	public static function init() : void {
		// Columns
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::TAX_SECTION . '_custom_column', [ __CLASS__, 'print_column' ], 10, 3 );

		// Filter UI + apply
		add_action( 'manage_terms_extra_tablenav', [ __CLASS__, 'filter_dropdown' ], 10, 2 );
		add_action( 'pre_get_terms', [ __CLASS__, 'apply_filter' ] );

		// Add/Edit fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish (hide slug, rename labels, move owner field)
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_admin_css_js' ] );
	}

	/* ================= Columns ================= */

	public static function columns( $cols ) {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) $new['jprm_menu'] = __( 'Menu', 'jprm' );
		}
		if ( ! isset( $new['jprm_menu'] ) ) $new['jprm_menu'] = __( 'Menu', 'jprm' );
		// Optional cleanup
		if ( isset( $new['slug'] ) ) unset( $new['slug'] );
		return $new;
	}

	/** Action: must echo content */
	public static function print_column( $out, $column_name, $term_id ) {
		if ( 'jprm_menu' !== $column_name ) return;
		$owner = (int) get_term_meta( $term_id, self::META_MENU_OWNER, true );
		if ( ! $owner ) { echo '—'; return; }
		$menu = get_term( $owner, self::TAX_MENU );
		echo ( $menu && ! is_wp_error( $menu ) ) ? esc_html( $menu->name ) : '—';
	}

	/* ================= Filter UI ================= */

	/**
	 * Render “Filter by Menu” on the Sections terms toolbar.
	 * Hook passes $which ('top'|'bottom') and $taxonomy.
	 */
	public static function filter_dropdown( $which, $taxonomy ) : void {
		if ( $taxonomy !== self::TAX_SECTION || $which !== 'top' ) return;

		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore

		echo '<div class="alignleft actions jprm-sections-filter">';
		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';

		// Use wp_dropdown_categories for robust population
		wp_dropdown_categories( [
			'show_option_all' => __( 'All Menus', 'jprm' ),
			'orderby'         => 'name',
			'hide_empty'      => 0,
			'taxonomy'        => self::TAX_MENU,
			'name'            => 'jprm_filter_menu',
			'id'              => 'jprm_filter_menu',
			'selected'        => $selected,
			'hierarchical'    => false,
			'class'           => 'postform',
			'value_field'     => 'term_id',
		] );

		submit_button( __( 'Filter' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Apply selected Menu filter to the Sections list query.
	 */
	public static function apply_filter( \WP_Term_Query $query ) : void {
		if ( ! is_admin() ) return;

		$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? [] );
		if ( ! in_array( self::TAX_SECTION, $taxonomies, true ) ) return;

		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id > 0 ) {
			$mq   = (array) ( $query->query_vars['meta_query'] ?? [] );
			$mq[] = [ 'key' => self::META_MENU_OWNER, 'value' => (string) $menu_id ];
			$query->query_vars['meta_query'] = $mq;
		}
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

	/* ================= UI polish (CSS/JS) ================= */

	public static function inject_admin_css_js() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;
		?>
		<style>
			/* Hide Slug on add + edit */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .form-field.term-slug-wrap,
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .term-slug-wrap { display:none !important; }
			/* Tighten list table a bit */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .wp-list-table .column-description { width:30%; }
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .wp-list-table .column-jprm_menu { width:20%; }
		</style>
		<script>
		(function(){
			function renameAddBox(){
				// Left add box header: ".wrap .form-wrap > h2" (older) or ".tag-add-form h2" (newer)
				var hdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (hdr && /Add\s+Category/i.test(hdr.textContent)) hdr.textContent = 'Add Section';

				// Parent Category -> Parent Section (add form)
				var parentAdd = document.querySelector('.form-field.term-parent-wrap > label');
				if (parentAdd) parentAdd.textContent = 'Parent Section';

				// Move Owner Menu above Description (add)
				var ownerAdd = document.querySelector('.form-field.term-owner-wrap');
				var descAdd  = document.querySelector('.form-field.term-description-wrap');
				if (ownerAdd && descAdd && ownerAdd.previousElementSibling !== descAdd) {
					descAdd.parentNode.insertBefore(ownerAdd, descAdd);
				}
			}
			function renameEditForm(){
				var form = document.querySelector('.edit-tag-form');
				if (!form) return;

				// Parent Category -> Parent Section (edit form)
				var lbl = form.querySelector('.form-field.term-parent-wrap th label');
				if (lbl) lbl.textContent = 'Parent Section';

				// Move Owner Menu above Description (edit)
				var owner = form.querySelector('.form-field.term-owner-wrap');
				var desc  = form.querySelector('.form-field.term-description-wrap');
				if (owner && desc && owner.previousElementSibling !== desc) {
					desc.parentNode.insertBefore(owner, desc);
				}
			}
			function renameSearch(){
				// "Search Categories" -> "Search Sections"
				var label = document.querySelector('label[for="tag-search-input"]');
				if (label && /Search\s+Categories/i.test(label.textContent)) {
					label.textContent = 'Search Sections:';
				}
				var inp = document.getElementById('tag-search-input');
				if (inp && (!inp.placeholder || /Categories/i.test(inp.placeholder))) {
					inp.placeholder = 'Search Sections';
				}
			}
			document.addEventListener('DOMContentLoaded', function(){
				renameAddBox();
				renameEditForm();
				renameSearch();
			});
		})();
		</script>
		<?php
	}
}
