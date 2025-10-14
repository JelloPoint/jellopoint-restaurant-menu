<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sections_Admin {

	const TAX_SECTION     = 'jprm_section';
	const TAX_MENU        = 'jprm_menu';
	const META_MENU_OWNER = '_jprm_menu_term_id';

	public static function init() : void {
		// Columns for the Sections (taxonomy) list table
		add_filter( 'manage_edit-' . self::TAX_SECTION . '_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::TAX_SECTION . '_custom_column', [ __CLASS__, 'print_column' ], 10, 3 );

		// Filter UI on the terms screen (top “tablenav”)
		add_action( 'manage_terms_extra_tablenav', [ __CLASS__, 'filter_dropdown' ], 10, 1 );
		// Apply selected filter to the terms query
		add_filter( 'get_terms_args', [ __CLASS__, 'apply_filter' ], 10, 2 );

		// Add/Edit form fields (Owner Menu selector)
		add_action( self::TAX_SECTION . '_add_form_fields',  [ __CLASS__, 'add_field' ] );
		add_action( self::TAX_SECTION . '_edit_form_fields', [ __CLASS__, 'edit_field' ], 10, 2 );

		// Save + cascade owner
		add_action( 'created_' . self::TAX_SECTION, [ __CLASS__, 'save_on_create' ], 10, 2 );
		add_action( 'edited_'  . self::TAX_SECTION, [ __CLASS__, 'save_on_edit' ],   10, 2 );
	}

	/* ================= Columns ================= */

	public static function columns( $cols ) {
		// Insert our Menu column after the Name column
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) {
				$new['jprm_menu'] = __( 'Menu', 'jprm' );
			}
		}
		if ( ! isset( $new['jprm_menu'] ) ) {
			$new['jprm_menu'] = __( 'Menu', 'jprm' );
		}
		// Optional clean-up: remove “Slug” column if you don’t want it
		if ( isset( $new['slug'] ) ) unset( $new['slug'] );
		return $new;
	}

	/**
	 * IMPORTANT: this is an ACTION on term rows; we must echo content.
	 */
	public static function print_column( $out, $column_name, $term_id ) {
		if ( 'jprm_menu' !== $column_name ) {
			return;
		}
		$owner = (int) get_term_meta( $term_id, self::META_MENU_OWNER, true );
		if ( ! $owner ) {
			echo '—';
			return;
		}
		$menu = get_term( $owner, self::TAX_MENU );
		echo ( $menu && ! is_wp_error( $menu ) ) ? esc_html( $menu->name ) : '—';
	}

	/* ================= Filter UI ================= */

	/**
	 * Renders the “Filter by Menu” dropdown on the Sections terms screen.
	 * Hook: manage_terms_extra_tablenav ($which = 'top' or 'bottom')
	 */
	public static function filter_dropdown( $which ) : void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->taxonomy ) || $screen->taxonomy !== self::TAX_SECTION ) {
			return;
		}
		// Show only on the TOP toolbar
		if ( $which !== 'top' ) return;

		$sel   = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );

		echo '<div class="alignleft actions">';
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
		submit_button( __( 'Filter' ), 'secondary', 'filter_action', false ); // adds a Filter button
		echo '</div>';
	}

	/**
	 * Applies the selected Menu filter to get_terms() on the Sections list screen.
	 * Hook: get_terms_args
	 */
	public static function apply_filter( $args, $taxonomies ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->taxonomy ) || $screen->taxonomy !== self::TAX_SECTION ) {
			return $args;
		}
		if ( ! in_array( self::TAX_SECTION, (array) $taxonomies, true ) ) {
			return $args;
		}

		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id > 0 ) {
			$args['meta_query'][] = [
				'key'   => self::META_MENU_OWNER,
				'value' => (string) $menu_id,
			];
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
}
