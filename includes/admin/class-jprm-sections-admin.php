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

		// Filter UI (terms toolbar) + robust fallback
		add_action( 'manage_terms_extra_tablenav', [ __CLASS__, 'filter_dropdown' ], 10, 2 );
		add_action( 'pre_get_terms', [ __CLASS__, 'apply_filter' ] );

		// Add/Edit form fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish (hide slug, rename labels, move owner field first)
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
		// Optional cleanup: remove “Slug” column if present
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
	 * Primary: render “Filter by Menu” on Sections toolbar.
	 * Hook passes $which ('top'|'bottom') and $taxonomy.
	 */
	public static function filter_dropdown( $which, $taxonomy ) : void {
		if ( $taxonomy !== self::TAX_SECTION || $which !== 'top' ) return;

		$sel   = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );

		echo '<div class="alignleft actions jprm-sections-filter">';
		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform">';
		echo '<option value="0">' . esc_html__( 'All Menus', 'jprm' ) . '</option>';
		if ( ! is_wp_error( $menus ) ) {
			foreach ( $menus as $m ) {
				printf(
					'<option value="%d"%s>%s</option>',
					(int) $m->term_id,
					selected( $sel, (int) $m->term_id, false ),
					esc_html( $m->name )
				);
			}
		}
		echo '</select>';
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

	/**
	 * We can’t change core taxonomy labels here, so we:
	 *  - hide the Slug field (add + edit)
	 *  - rename Add/Search/Parent labels via JS
	 *  - move Owner Menu above Description
	 *  - (fallback) inject filter dropdown if some themes/pages miss the toolbar hook
	 */
	public static function inject_admin_css_js() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;
		?>
		<style>
			/* Hide Slug on add form */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .form-field.term-slug-wrap { display:none !important; }
			/* Hide Slug on edit form */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .term-slug-wrap { display:none !important; }
		</style>
		<script>
		(function(){
			// Rename strings & move the Owner field above Description
			function tweakAddForm(){
				var addBox = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .tag-add-form');
				if(!addBox) return;

				// Title on the left column "Add Category" -> "Add Section"
				var h2 = addBox.querySelector('h2, h3');
				if(h2 && /Add\s+Category/i.test(h2.textContent)) h2.textContent = 'Add Section';

				// "Parent Category" label -> "Parent Section"
				var parentLbl = addBox.querySelector('.form-field.term-parent-wrap > label');
				if(parentLbl) parentLbl.textContent = 'Parent Section';

				// Move Owner Menu above Description
				var owner = addBox.querySelector('.form-field.term-owner-wrap');
				var desc  = addBox.querySelector('.form-field.term-description-wrap');
				if(owner && desc && owner.nextSibling !== desc){
					desc.parentNode.insertBefore(owner, desc);
				}
			}

			function tweakEditForm(){
				var tbl = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .edit-tag-actions, .taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .edit-tag-form');
				var form = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .edit-tag-form');
				if(!form) return;

				// Parent Category -> Parent Section
				var parentRow = form.querySelector('.form-field.term-parent-wrap th label');
				if(parentRow) parentRow.textContent = 'Parent Section';

				// Move Owner Menu row above Description
				var ownerRow = form.querySelector('.form-field.term-owner-wrap');
				var descRow  = form.querySelector('.form-field.term-description-wrap');
				if(ownerRow && descRow && ownerRow.previousElementSibling !== descRow){
					descRow.parentNode.insertBefore(ownerRow, descRow);
				}
			}

			// Search label "Search Categories" -> "Search Sections"
			function tweakSearchLabel(){
				var wrap = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .wrap');
				if(!wrap) return;
				var search = wrap.querySelector('form[method="get"] p.search-form label');
				if(search && /Search\s+Categories/i.test(search.textContent)){
					search.textContent = 'Search Sections:';
				}
			}

			// Fallback filter dropdown injection (if the toolbar hook didn’t render)
			function ensureFilterDropdown(){
				var toolbarHasFilter = !!document.querySelector('#jprm_filter_menu');
				if (toolbarHasFilter) return;

				var h2 = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> .wrap h1');
				var form = document.querySelector('.taxonomy-<?php echo esc_js( self::TAX_SECTION ); ?> form#posts-filter');
				if(!h2 || !form) return;

				// Build minimal dropdown
				var div = document.createElement('div');
				div.className = 'alignleft actions jprm-sections-filter';
				div.innerHTML = '<label class="screen-reader-text" for="jprm_filter_menu">Filter by Menu</label>' +
					'<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform"><option value="0">All Menus</option></select>' +
					'<input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filter">';
				var toolbar = form.querySelector('.tablenav.top .actions');
				if (toolbar) {
					toolbar.prepend(div);
				} else {
					form.prepend(div);
				}

				// Fetch menus quickly via ajax (uses WP ajaxurl + built-in endpoint fallback)
				try {
					var url = (window.jQuery && window.JPRM_MENU_BUILDER && JPRM_MENU_BUILDER.root)
						? (JPRM_MENU_BUILDER.root + '/menu-builder/menus')
						: null;
					if (!url) return;
					jQuery.ajax({
						url: url,
						method: 'GET',
						beforeSend: function(x){ if (window.JPRM_MENU_BUILDER) x.setRequestHeader('X-WP-Nonce', JPRM_MENU_BUILDER.nonce); }
					}).done(function(res){
						var sel = document.getElementById('jprm_filter_menu');
						if(!sel || !res || !res.menus) return;
						res.menus.forEach(function(m){
							var opt = document.createElement('option');
							opt.value = m.id; opt.textContent = m.title;
							// keep current selection from URL
							var urlParams = new URLSearchParams(window.location.search);
							if (parseInt(urlParams.get('jprm_filter_menu')||'0',10) === m.id) opt.selected = true;
							sel.appendChild(opt);
						});
					});
				} catch(e){}
			}

			document.addEventListener('DOMContentLoaded', function(){
				tweakAddForm();
				tweakEditForm();
				tweakSearchLabel();
				ensureFilterDropdown();
			});
		})();
		</script>
		<?php
	}
}
