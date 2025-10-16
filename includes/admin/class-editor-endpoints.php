<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor-only AJAX endpoints used by Elementor UI.
 *
 * Action: jprm_sections_by_menu
 * Params: menu (string|int), _ajax_nonce (via JPRMAjax.nonce)
 *
 * Behavior:
 *  - If a valid menu_id is provided, return sections whose term meta `_jprm_menu_id` equals that menu_id.
 *  - Also merges any ids from option 'jprm_sections_catalog' ([menu_id => [section_ids...] ]).
 *  - If the result is empty for any reason, it ALWAYS falls back to ALL sections (hierarchical labels).
 *  - If menu is empty/invalid, returns ALL sections (hierarchical labels).
 */
final class Editor_Endpoints {

	/** Canonical meta key on jprm_section that stores the owning menu's term_id (integer). */
	private const SECTION_MENU_META_KEY = '_jprm_menu_id';

	public static function init(): void {
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );
	}

	public static function ajax_sections_by_menu(): void {
		// Editor context; keep strict but never fatal.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 ); // return ALL so UI keeps working.
		}

		// Nonce check; on failure still return ALL so UI keeps working.
		if ( ! isset( $_REQUEST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ), 'jprm_sections' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		// No valid menu? Return ALL (hierarchical).
		if ( $menu_id <= 0 ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		// A) Sections explicitly linked via _jprm_menu_id.
		$ids = self::get_section_ids_from_meta( $menu_id );

		// B) Merge optional registry from option (if present).
		$opt = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $opt[ $menu_id ] ) );
		}

		$ids = array_values( array_unique( array_filter( $ids, static fn( $n ) => $n > 0 ) ) );

		// If some mapping exists, try to load only those.
		if ( ! empty( $ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $ids,
			] );
			if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_send_json_success( self::terms_to_hierarchical_options( $terms ), 200 );
			}
		}

		// C) Fallback: ALL sections (hierarchical).
		wp_send_json_success( self::all_sections_map(), 200 );
	}

	/* ===== Helpers ===== */

	private static function normalize_menu_to_id( $menu ): int {
		if ( is_numeric( $menu ) ) {
			$tid  = (int) $menu;
			$term = get_term( $tid, 'jprm_menu' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( $menu === '' ) return 0;

		$term = get_term_by( 'slug', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		$term = get_term_by( 'name', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		return 0;
	}

	/** Return ALL sections as id => label (with hierarchy indentation). */
	private static function all_sections_map(): array {
		$terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		return self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );
	}

	/** Get section IDs where _jprm_menu_id equals the given menu_id. */
	private static function get_section_ids_from_meta( int $menu_id ): array {
		$terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) return [];
		$out = [];
		foreach ( $terms as $t ) {
			$val = get_term_meta( $t->term_id, self::SECTION_MENU_META_KEY, true );
			if ( $val === '' || $val === null ) continue;
			if ( (int) $val === $menu_id ) $out[] = (int) $t->term_id;
		}
		return $out;
	}

	/** Flat id => label map with hierarchy indentation for jprm_section terms. */
	private static function terms_to_hierarchical_options( array $terms ): array {
		if ( empty( $terms ) ) return [];

		$by_parent = [];
		foreach ( $terms as $t ) { $by_parent[ (int) $t->parent ][] = $t; }

		$make_label = function( \WP_Term $term ): string {
			$depth  = count( get_ancestors( (int) $term->term_id, 'jprm_section', 'taxonomy' ) );
			$indent = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			return $indent . $term->name;
		};

		$roots = $by_parent[0] ?? [];
		usort( $roots, static fn($a, $b) => strcasecmp( $a->name, $b->name ) );

		$out = [];

		$walk = function( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) {
			if ( empty( $by_parent[ $parent_id ] ) ) return;
			$children = $by_parent[ $parent_id ];
			usort( $children, static fn($a, $b) => strcasecmp( $a->name, $b->name ) );
			foreach ( $children as $child ) {
				$out[ (string) $child->term_id ] = $make_label( $child );
				$walk( (int) $child->term_id );
			}
		};

		foreach ( $roots as $root ) {
			$out[ (string) $root->term_id ] = $make_label( $root );
			$walk( (int) $root->term_id );
		}

		return $out;
	}
}
