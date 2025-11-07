<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sections_Admin {

	const TAX_SECTION     = 'jprm_section';
	const TAX_MENU        = 'jprm_menu';
	const META_MENU_OWNER = '_jprm_menu_term_id';
	const META_SECTION_ORDER = '_jprm_section_order';

	public static function init() : void {
		// Columns
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::TAX_SECTION . '_custom_column', [ __CLASS__, 'print_column' ], 10, 3 );

		// Filtering (works regardless of admin quirks)
		add_action( 'pre_get_terms', [ __CLASS__, 'apply_filter' ] );
		add_action( 'terms_clauses', [ __CLASS__, 'enforce_filter_sql' ], 10, 3 );

		// Add/Edit fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );

		// UI polish + force toolbar filter + AJAX swap
		add_action( 'admin_head-edit-tags.php',   [ __CLASS__, 'inject_admin_css_js' ] );
		add_action( 'admin_footer-edit-tags.php', [ __CLASS__, 'force_toolbar_filter' ] );

		// Section Filter Registration
		add_filter( 'jprm_get_sections_for_menu', [ __CLASS__, 'get_sections_for_menu' ], 10, 2 );
	}

	/* ================= Columns ================= */

	public static function columns( $cols ) {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) { $new['jprm_menu'] = __( 'Menu', 'jprm' ); $new['jprm_order'] = __( 'Order', 'jprm' ); }
		}
		if ( ! isset( $new['jprm_menu'] ) )  $new['jprm_menu']  = __( 'Menu', 'jprm' );
		if ( ! isset( $new['jprm_order'] ) ) $new['jprm_order'] = __( 'Order', 'jprm' );
		if ( isset( $new['slug'] ) ) unset( $new['slug'] ); // cleaner UI
		return $new;
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
			$ord = (int) get_term_meta( $term_id, self::META_SECTION_ORDER, true );
			echo $ord ? $ord : '—';
			return;
		}
	}

	/* ================= Filtering logic ================= */

	// Apply at query-vars level
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

	// Enforce at SQL level using WP's aliases: t = terms, tt = term_taxonomy
	public static function enforce_filter_sql( $clauses, $taxonomies, $args ) {
		if ( ! is_admin() ) return $clauses;
		if ( empty( $taxonomies ) || ! in_array( self::TAX_SECTION, (array) $taxonomies, true ) ) return $clauses;

		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id <= 0 ) return $clauses;

		global $wpdb;

		// LEFT JOIN termmeta using the 't' alias from core terms query
		if ( strpos( $clauses['join'], 'termmeta jprm_tmeta' ) === false ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS jprm_tmeta
				ON ( t.term_id = jprm_tmeta.term_id )";
		}

		$owner_where = $wpdb->prepare(
			" ( jprm_tmeta.meta_key = %s AND jprm_tmeta.meta_value = %s ) ",
			self::META_MENU_OWNER,
			(string) $menu_id
		);

		// Constrain taxonomy via the 'tt' alias used by core
		$tax_where = $wpdb->prepare( " tt.taxonomy = %s ", self::TAX_SECTION );

		$clauses['where']  .= " AND {$tax_where} AND {$owner_where} ";

		// Ensure DISTINCT due to extra join
		if ( strpos( $clauses['fields'], 'DISTINCT' ) === false ) {
			$clauses['fields'] = 'DISTINCT ' . $clauses['fields'];
		}

		// Join order meta and enforce ORDER BY (section order then name)
		if ( strpos( $clauses['join'], 'jprm_ord' ) === false ) {
			$clauses['join'] .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->termmeta} AS jprm_ord ON ( jprm_ord.term_id = t.term_id AND jprm_ord.meta_key = %s ) ",
				self::META_SECTION_ORDER
			);
		}
		$clauses['orderby'] = " ORDER BY CAST(jprm_ord.meta_value AS SIGNED) ASC, t.name ASC ";

		return $clauses;
	}

	/* ================= Add/Edit fields ================= */

	public static function add_field() {
		$menus   = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		$parents = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false ] );
		?>
		<div class="form-field term-name-wrap">
			<label for="tag-name"><?php esc_html_e( 'Name', 'jprm' ); ?></label>
			<input name="tag-name" id="tag-name" type="text" size="40" aria-required="true" />
		</div>
		<div class="form-field term-parent-wrap">
			<label for="parent"><?php esc_html_e( 'Parent Section', 'jprm' ); ?></label>
			<select name="parent" id="parent" class="postform">
				<option value="-1"><?php esc_html_e( '— None —', 'jprm' ); ?></option>
				<?php if ( ! is_wp_error( $parents ) ) foreach ( $parents as $p ) : ?>
					<option value="<?php echo (int) $p->term_id; ?>"><?php echo esc_html( $p->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Optional: leave empty for a top-level Section.', 'jprm' ); ?></p>
		</div>
		<div class="form-field term-owner-wrap">
			<label for="jprm_owner_menu"><?php esc_html_e( 'Owner Menu', 'jprm' ); ?></label>
			<select name="jprm_owner_menu" id="jprm_owner_menu">
				<option value="0"><?php esc_html_e( '— choose —', 'jprm' ); ?></option>
				<?php if ( ! is_wp_error( $menus ) ) foreach ( $menus as $m ) : ?>
					<option value="<?php echo (int) $m->term_id; ?>"><?php echo esc_html( $m->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'If you set a parent, and leave this empty, ownership will inherit from the parent.', 'jprm' ); ?></p>
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

	public static function save_on_create( $term_id, $tt_id ) : void {
		$owner_menu_id = isset( $_POST['jprm_owner_menu'] ) ? (int) $_POST['jprm_owner_menu'] : 0; // phpcs:ignore
		if ( $owner_menu_id > 0 ) {
			update_term_meta( $term_id, self::META_MENU_OWNER, $owner_menu_id );
			self::cascade_children( (int) $term_id, $owner_menu_id );
		} else {
			// If not explicitly set, try inheriting from parent
			$term = get_term( $term_id, self::TAX_SECTION );
			if ( $term && ! is_wp_error( $term ) && $term->parent ) {
				$parent_owner = (int) get_term_meta( (int) $term->parent, self::META_MENU_OWNER, true );
				if ( $parent_owner ) {
					update_term_meta( $term_id, self::META_MENU_OWNER, $parent_owner );
				}
			}
		}
	}

	public static function save_on_edit( $term_id, $tt_id ) : void {
		$owner_menu_id = isset( $_POST['jprm_owner_menu'] ) ? (int) $_POST['jprm_owner_menu'] : 0; // phpcs:ignore
		update_term_meta( $term_id, self::META_MENU_OWNER, $owner_menu_id );
		self::cascade_children( (int) $term_id, $owner_menu_id );
	}

	private static function cascade_children( int $parent_term_id, int $owner_menu_id ) : void {
		$children = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'hide_empty' => false,
			'parent'     => $parent_term_id,
		] );
		if ( is_wp_error( $children ) || empty( $children ) ) return;

		foreach ( $children as $child ) {
			if ( (int) get_term_meta( $child->term_id, self::META_MENU_OWNER, true ) !== $owner_menu_id ) {
				update_term_meta( $child->term_id, self::META_MENU_OWNER, $owner_menu_id );
			}
			self::cascade_children( (int) $child->term_id, $owner_menu_id );
		}
	}

	/* ================= UI polish + forced toolbar filter ================= */

	public static function inject_admin_css_js() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;
		?>
		<style>
			/* Hide Slug on add + edit */
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .form-field.term-slug-wrap,
			.taxonomy-<?php echo esc_attr( self::TAX_SECTION ); ?> .term-slug-wrap { display:none !important; }
			.column-jprm_order{ width:90px; text-align:right; }
		</style>
		<script>
		(function(){
			function moveOwnerToTop(){
				var addForm = document.getElementById('addtag');
				if (!addForm) return;
				var owner   = addForm.querySelector('.form-field.term-owner-wrap');
				var nameFld = addForm.querySelector('.form-field.term-name-wrap');
				if (owner && nameFld && owner !== nameFld.previousElementSibling) {
					addForm.insertBefore(owner, nameFld);
				}
			}
			document.addEventListener('DOMContentLoaded', moveOwnerToTop);
		})();
		</script>
		<?php
	}

	public static function force_toolbar_filter() : void {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		if ( $tax !== self::TAX_SECTION ) return;

		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		if ( is_wp_error( $menus ) ) return;

		$options = '<option value="0">'. esc_html__( '— Filter by Menu —', 'jprm' ) .'</option>';
		foreach ( $menus as $m ) {
			$sel = ( isset($_GET['jprm_filter_menu']) && (int)$_GET['jprm_filter_menu'] === (int)$m->term_id ) ? ' selected' : ''; // phpcs:ignore
			$options .= '<option value="'. (int)$m->term_id .'"'. $sel .'>'. esc_html($m->name) .'</option>';
		}
		?>
		<script>
		(function(){
			function ensureFilter(){
				var form   = document.getElementById('posts-filter');
				if (!form) return;

				// If already present, leave it.
				if (document.getElementById('jprm_filter_menu')) return;

				var topActions = form.querySelector('.tablenav.top .actions') || form.querySelector('.tablenav.top');
				if (!topActions) return;

				var wrap = document.createElement('div');
				wrap.className = 'alignleft actions jprm-sections-filter';
				wrap.innerHTML =
					'<label class="screen-reader-text" for="jprm_filter_menu"><?php echo esc_js( __( 'Filter by Menu', 'jprm' ) ); ?></label>' +
					'<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform"><?php echo $options; ?></select>' +
					'<input type="submit" name="filter_action" class="button" value="<?php echo esc_js( __( 'Filter', 'jprm' ) ); ?>">';

				topActions.prepend(wrap);
			}

			// AJAX swap: change -> fetch -> replace pieces (fallback to form submit)
			function ajaxify(){
				var sel = document.getElementById('jprm_filter_menu');
				if (!sel) return;

				sel.addEventListener('change', function(){
					// graceful: just submit the form if JS fails
					try {
						// subtle: let WP handle the refresh
						if (sel.form) sel.form.submit();
					} catch(e) {
						if (sel.form) sel.form.submit();
					}
				});
			}

			document.addEventListener('DOMContentLoaded', function(){
				ensureFilter();
				ajaxify();
			});
		})();
		</script>
		<?php
	}

	/* ================= Section list provider (filter hook) ================= */

	public static function get_sections_for_menu( $sections, $menu_id ) {
		// Keep your existing behavior; this is a hook point for other admin screens.
		return $sections;
	}
}
