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

		// Filter UI + query constraints
		add_action( 'manage_terms_extra_tablenav', [ __CLASS__, 'filter_dropdown' ], 10, 1 );
		add_action( 'pre_get_terms', [ __CLASS__, 'apply_filter' ] );

		// Add/Edit fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish + AJAX filter script
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
		// Optional: remove “Slug” for a cleaner UI
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
	 * Render “Filter by Menu” on Sections toolbar.
	 * Hook provides only $which ('top'|'bottom'); taxonomy is in the query.
	 */
	public static function filter_dropdown( $which ) : void {
		if ( $which !== 'top' ) return;

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $taxonomy !== self::TAX_SECTION ) return;

		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore

		echo '<div class="alignleft actions jprm-sections-filter">';
		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';

		// Robust server-side population
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
	 * Uses a clauses filter to guarantee the JOIN + WHERE are applied.
	 */
	public static function apply_filter( \WP_Term_Query $query ) : void {
		if ( ! is_admin() ) return;

		$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? [] );
		if ( ! in_array( self::TAX_SECTION, $taxonomies, true ) ) return;

		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id <= 0 ) return;

		// Attach a one-off terms_clauses filter for this request
		add_filter( 'terms_clauses', function( $clauses, $taxonomies, $args ) use ( $menu_id ) {
			global $wpdb;

			// Only for our taxonomy
			if ( empty( $taxonomies ) || ! in_array( self::TAX_SECTION, (array) $taxonomies, true ) ) {
				return $clauses;
			}

			// JOIN termmeta (aliased)
			if ( strpos( $clauses['join'], 'termmeta jprm_tmeta' ) === false ) {
				$clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS jprm_tmeta
					ON ( {$wpdb->terms}.term_id = jprm_tmeta.term_id )";
			}

			// WHERE owner = selected menu
			$owner_where = $wpdb->prepare(
				" ( jprm_tmeta.meta_key = %s AND jprm_tmeta.meta_value = %s ) ",
				self::META_MENU_OWNER,
				(string) $menu_id
			);

			// Bind to the taxonomy (in case multiple tax queried)
			$tax_where = $wpdb->prepare( " {$wpdb->term_taxonomy}.taxonomy = %s ", self::TAX_SECTION );

			$clauses['where'] .= " AND {$tax_where} AND {$owner_where} ";

			// Ensure distinct (avoid duplicates due to join)
			if ( strpos( $clauses['fields'], 'DISTINCT' ) === false ) {
				$clauses['fields'] = 'DISTINCT ' . $clauses['fields'];
			}

			return $clauses;
		}, 10, 3 );
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

	/* ================= UI polish + AJAX filter ================= */

	public static function inject_admin_css_js() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;

		?>
		<style>
			/* Hide Slug on add + edit */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .form-field.term-slug-wrap,
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .term-slug-wrap { display:none !important; }
		</style>
		<script>
		(function(){
			function moveOwnerToTop(){
				var addForm = document.getElementById('addtag');
				if (!addForm) return;
				var owner   = addForm.querySelector('.form-field.term-owner-wrap');
				var nameFld = addForm.querySelector('.form-field.term-name-wrap');
				if (owner && nameFld && owner !== nameFld.previousElementSibling) {
					nameFld.parentNode.insertBefore(owner, nameFld);
				}
			}
			function renameLabels(){
				var hdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (hdr && /Add\s+Category/i.test(hdr.textContent)) hdr.textContent = 'Add Section';

				var parentAdd = document.querySelector('#addtag .form-field.term-parent-wrap > label');
				if (parentAdd) parentAdd.textContent = 'Parent Section';

				var parentEdit = document.querySelector('.edit-tag-form .form-field.term-parent-wrap th label');
				if (parentEdit) parentEdit.textContent = 'Parent Section';

				var srch = document.querySelector('label[for="tag-search-input"]');
				if (srch) srch.textContent = 'Search Sections:';
				var inp = document.getElementById('tag-search-input');
				if (inp) inp.placeholder = 'Search Sections';
			}

			// AJAX filter: swap table & pagination in-place
			function ajaxFilter(){
				var sel = document.getElementById('jprm_filter_menu');
				if(!sel) return;
				sel.addEventListener('change', function(){
					var form   = document.getElementById('posts-filter');
					if(!form){ this.form && this.form.submit(); return; }

					// Build new URL with selected filter
					var url = new URL(window.location.href);
					url.searchParams.set('jprm_filter_menu', this.value || '0');
					// Reset to first page when filtering
					url.searchParams.delete('paged');

					// Fetch and replace table + paginations
					fetch(url.toString(), { credentials: 'same-origin' })
						.then(function(r){ return r.text(); })
						.then(function(html){
							var doc = new DOMParser().parseFromString(html, 'text/html');
							var newBody = doc.querySelector('#the-list');
							var newTopPag = doc.querySelector('.tablenav.top .tablenav-pages');
							var newBotPag = doc.querySelector('.tablenav.bottom .tablenav-pages');
							var curBody = document.getElementById('the-list');
							if (newBody && curBody) curBody.innerHTML = newBody.innerHTML;
							var curTopPag = document.querySelector('.tablenav.top .tablenav-pages');
							if (curTopPag && newTopPag) curTopPag.innerHTML = newTopPag.innerHTML;
							var curBotPag = document.querySelector('.tablenav.bottom .tablenav-pages');
							if (curBotPag && newBotPag) curBotPag.innerHTML = newBotPag.innerHTML;

							// Update URL (so refresh keeps filter)
							if (history && history.replaceState) {
								history.replaceState({}, '', url.toString());
							}
						})
						.catch(function(){
							// Fallback: normal submit if anything fails
							if (sel.form) sel.form.submit();
						});
				});
			}

			document.addEventListener('DOMContentLoaded', function(){
				moveOwnerToTop();
				renameLabels();
				ajaxFilter();
			});
		})();
		</script>
		<?php
	}
}
