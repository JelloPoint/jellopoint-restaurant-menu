<?php
namespace JelloPoint\RestaurantMenu\Admin;

use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Synchronize explicit item-editor taxonomy changes with the Menu Builder. */
final class Item_Placement_Sync {
	private static $previous_menus = [];

	public static function init() : void {
		add_action( 'pre_post_update', [ __CLASS__, 'capture' ] );
		add_action( 'wp_after_insert_post', [ __CLASS__, 'save' ], 20, 2 );
	}

	public static function capture( int $post_id ) : void {
		if ( 'jprm_menu_item' !== get_post_type( $post_id ) ) { return; }
		$ids = wp_get_post_terms( $post_id, 'jprm_menu', [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $ids ) ) { self::$previous_menus[ $post_id ] = array_map( 'intval', $ids ); }
	}

	public static function save( int $post_id, $post ) : void {
		if ( 'jprm_menu_item' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		$nonce = isset( $_POST['jprm_meta_nonce'] ) && is_string( $_POST['jprm_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jprm_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'jprm_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		// Missing controls (quick edits, imports, translations) must not remove placements.
		if ( ! isset( $_POST['tax_input']['jprm_menu'], $_POST['tax_input']['jprm_section'] ) ) { return; }
		foreach ( [ 'jprm_menu', 'jprm_section' ] as $name ) {
			$taxonomy = get_taxonomy( $name );
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) { return; }
		}
		$menus = wp_get_post_terms( $post_id, 'jprm_menu', [ 'fields' => 'ids' ] );
		$sections = wp_get_post_terms( $post_id, 'jprm_section', [ 'fields' => 'ids' ] );
		if ( is_wp_error( $menus ) || is_wp_error( $sections ) ) { return; }
		$menus = array_map( 'intval', $menus );
		$sections = array_map( 'intval', $sections );
		foreach ( array_diff( self::$previous_menus[ $post_id ] ?? [], $menus ) as $menu_id ) {
			Menu_Structure_Store::unassign_item( $menu_id, $post_id );
		}
		foreach ( $menus as $menu_id ) {
			$candidates = array_values( array_intersect( Menu_Structure_Store::section_ids( $menu_id ), $sections ) );
			$placement = Menu_Structure_Store::item_placements( $menu_id )[ $post_id ] ?? null;
			// Keep an existing valid placement and its exact order, including shared Sections.
			if ( $placement && in_array( $placement['section_id'], $candidates, true ) ) { continue; }
			if ( 1 === count( $candidates ) ) {
				Menu_Structure_Store::assign_items( $menu_id, $candidates[0], [ $post_id ] );
			} elseif ( [] === $sections ) {
				Menu_Structure_Store::unassign_item( $menu_id, $post_id );
			} else {
				// Multiple selections do not encode a Menu -> Section pairing; never guess.
				add_filter( 'redirect_post_location', [ __CLASS__, 'warning_redirect' ] );
			}
		}
		unset( self::$previous_menus[ $post_id ] );
	}

	public static function warning_redirect( string $location ) : string {
		return add_query_arg( 'jprm_placement_review', '1', $location );
	}

	public static function notice() : void {
		if ( empty( $_GET['jprm_placement_review'] ) ) { return; }
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Some Menu placements could not be determined from the selected Sections. Open Menu Builder to choose the Section for each Menu. Existing placements have been preserved.', 'jellopoint-restaurant-menu' ) . '</p></div>';
	}
}
