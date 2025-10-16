<?php
/**
 * Sections Meta UI (Owning Menu)
 *
 * Adds a dropdown on jprm_section add/edit screens to store the owning Menu
 * in term meta `_jprm_menu_id` (integer). Used by the Elementor editor to
 * filter Sections by the selected Menu.
 *
 * This file is intentionally minimal and does NOT replace your existing
 * class-jprm-sections-admin.php. It only adds the meta UI.
 */

namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JPRM_Sections_Meta_UI {

	/** Canonical meta key stored on jprm_section terms */
	private const META_MENU_OWNER = '_jprm_menu_id';

	public static function init() : void {
		// Add field on "Add new Section" screen.
		add_action( 'jprm_section_add_form_fields', [ __CLASS__, 'add_menu_meta_field' ] );

		// Add field on "Edit Section" screen.
		add_action( 'jprm_section_edit_form_fields', [ __CLASS__, 'edit_menu_meta_field' ], 10, 2 );

		// Persist meta on create/edit.
		add_action( 'created_jprm_section', [ __CLASS__, 'save_menu_meta' ] );
		add_action( 'edited_jprm_section',  [ __CLASS__, 'save_menu_meta' ] );
	}

	/**
	 * Render extra field on the "Add new Section" form.
	 */
	public static function add_menu_meta_field() : void {
		$menus = get_terms(
			[
				'taxonomy'   => 'jprm_menu',
				'hide_empty' => false,
			]
		);
		?>
		<div class="form-field term-group">
			<label for="jprm_menu_owner"><?php echo esc_html__( 'Owning Menu', 'jellopoint-restaurant-menu' ); ?></label>
			<select id="jprm_menu_owner" name="<?php echo esc_attr( self::META_MENU_OWNER ); ?>" class="postform">
				<option value=""><?php echo esc_html_x( '— None —', 'dropdown option', 'jellopoint-restaurant-menu' ); ?></option>
				<?php
				if ( is_array( $menus ) ) :
					foreach ( $menus as $m ) :
						?>
						<option value="<?php echo (int) $m->term_id; ?>"><?php echo esc_html( $m->name ); ?></option>
						<?php
					endforeach;
				endif;
				?>
			</select>
			<p class="description">
				<?php echo esc_html__( 'Link this Section to a Menu so the Elementor widget can filter sections by the selected menu.', 'jellopoint-restaurant-menu' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render extra field on the "Edit Section" form.
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 */
	public static function edit_menu_meta_field( $term, $taxonomy ) : void {
		unset( $taxonomy ); // not used, keep signature for action.
		$current = (int) get_term_meta( $term->term_id, self::META_MENU_OWNER, true );

		$menus = get_terms(
			[
				'taxonomy'   => 'jprm_menu',
				'hide_empty' => false,
			]
		);
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row">
				<label for="jprm_menu_owner"><?php echo esc_html__( 'Owning Menu', 'jellopoint-restaurant-menu' ); ?></label>
			</th>
			<td>
				<select id="jprm_menu_owner" name="<?php echo esc_attr( self::META_MENU_OWNER ); ?>" class="postform">
					<option value=""><?php echo esc_html_x( '— None —', 'dropdown option', 'jellopoint-restaurant-menu' ); ?></option>
					<?php
					if ( is_array( $menus ) ) :
						foreach ( $menus as $m ) :
							?>
							<option value="<?php echo (int) $m->term_id; ?>" <?php selected( $current, (int) $m->term_id ); ?>>
								<?php echo esc_html( $m->name ); ?>
							</option>
							<?php
						endforeach;
					endif;
					?>
				</select>
				<p class="description">
					<?php echo esc_html__( 'Select the Menu this Section belongs to. Used for editor-side filtering; does not affect frontend rendering.', 'jellopoint-restaurant-menu' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the `_jprm_menu_id` term meta when creating or editing a Section.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_menu_meta( $term_id ) : void {
		// Capability check aligned with taxonomy editing.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- taxonomy forms do not include a custom nonce by default; rely on capability checks.
		$value = isset( $_POST[ self::META_MENU_OWNER ] ) ? wp_unslash( $_POST[ self::META_MENU_OWNER ] ) : '';

		if ( '' === $value ) {
			delete_term_meta( $term_id, self::META_MENU_OWNER );
			return;
		}

		update_term_meta( $term_id, self::META_MENU_OWNER, (int) $value );
	}
}
