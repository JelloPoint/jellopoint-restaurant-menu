<?php
namespace JelloPoint\RestaurantMenu\Admin;

use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;
use JelloPoint\RestaurantMenu\Data\Item_Assignments;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit Menu -> Section pairs, using the same structures as Menu Builder. */
final class Item_Placement_Sync {
	public static function init() : void {
		add_action( 'add_meta_boxes_jprm_menu_item', [ __CLASS__, 'metabox' ], 100 );
		add_action( 'wp_after_insert_post', [ __CLASS__, 'save' ], 20, 2 );
	}

	public static function metabox() : void {
		foreach ( [ 'jprm_menudiv', 'jprm_sectiondiv', 'tagsdiv-jprm_menu', 'tagsdiv-jprm_section' ] as $id ) {
			foreach ( [ 'side', 'normal', 'advanced' ] as $context ) { remove_meta_box( $id, 'jprm_menu_item', $context ); }
		}
		add_meta_box( 'jprm_item_placements', __( 'Menu assignments', 'jellopoint-restaurant-menu' ), [ __CLASS__, 'render' ], 'jprm_menu_item', 'normal', 'default' );
	}

	public static function render( $post ) : void {
		wp_nonce_field( 'jprm_placements', 'jprm_placements_nonce' );
		echo '<p>' . esc_html__( 'Choose one Section per Menu. An item can appear in multiple Menus. Choose Not assigned to remove it from a Menu. Sections are managed in Menu Builder.', 'jellopoint-restaurant-menu' ) . '</p>';
		$menus = get_terms( [ 'taxonomy' => 'jprm_menu', 'hide_empty' => false ] );
		if ( is_wp_error( $menus ) ) { return; }
		foreach ( $menus as $menu ) {
			$id = (int) $menu->term_id;
			$current = Menu_Structure_Store::item_placements( $id )[ (int) $post->ID ]['section_id'] ?? 0;
			echo '<p><label for="jprm-placement-' . esc_attr( (string) $id ) . '">' . esc_html( $menu->name ) . '</label> ';
			echo '<select id="jprm-placement-' . esc_attr( (string) $id ) . '" name="jprm_placements[' . esc_attr( (string) $id ) . ']">';
			echo '<option value="0">' . esc_html__( 'Not assigned', 'jellopoint-restaurant-menu' ) . '</option>';
			foreach ( Menu_Structure_Store::get( $id )['sections'] as $row ) {
				$section = get_term( $row['id'], 'jprm_section' );
				if ( ! $section || is_wp_error( $section ) ) { continue; }
				echo '<option value="' . (int) $section->term_id . '" ' . selected( $current, (int) $section->term_id, false ) . '>' . esc_html( $section->name ) . '</option>';
			}
			echo '</select></p>';
		}
	}

	public static function save( int $post_id, $post ) : void {
		if ( 'jprm_menu_item' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		$nonce = isset( $_POST['jprm_placements_nonce'] ) && is_string( $_POST['jprm_placements_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jprm_placements_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'jprm_placements' ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( ! isset( $_POST['jprm_placements'] ) || ! is_array( $_POST['jprm_placements'] ) ) { return; }
		foreach ( [ 'jprm_menu', 'jprm_section' ] as $name ) {
			$taxonomy = get_taxonomy( $name );
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) { return; }
		}
		$pairs = [];
		// Each key/value pair is scalar-validated and normalized in the loop below.
		$posted_placements = wp_unslash( $_POST['jprm_placements'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( $posted_placements as $menu_id => $section_id ) {
			if ( ! ctype_digit( (string) $menu_id ) || ! is_scalar( $section_id ) || ! ctype_digit( (string) $section_id ) ) {
				add_filter( 'redirect_post_location', [ __CLASS__, 'warning_redirect' ] ); return;
			}
			$pairs[ (int) $menu_id ] = (int) $section_id;
		}
		if ( ! Item_Assignments::replace( $post_id, $pairs ) ) { add_filter( 'redirect_post_location', [ __CLASS__, 'warning_redirect' ] ); }
	}

	public static function warning_redirect( string $location ) : string {
		return add_query_arg( 'jprm_placement_review', '1', $location );
	}

	public static function notice() : void {
		if ( empty( $_GET['jprm_placement_review'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect status.
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Menu assignments could not be saved. Reload the item and select a Section belonging to each Menu, then save again.', 'jellopoint-restaurant-menu' ) . '</p></div>';
	}
}
