<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor-only AJAX endpoints used by Elementor UI.
 *
 * jprm_sections_by_menu:
 * - Source of truth: section term-meta `_jprm_menu_id` (integer menu term_id).
 * - Optional additive: option 'jprm_sections_catalog' => [ menu_id => [section_ids...] ].
 * - If no mapping, we ALWAYS fall back to ALL sections (hierarchical).
 */
final class Editor_Endpoints {

	/** Canonical key on jprm_section that stores the owning menu's term_id. */
	private const SECTION_MENU_META_KEY = '_jprm_menu_id';

	public static function init(): void {
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );
	}

	public static function ajax_sections_by_menu(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		check_ajax_referer( 'jprm_sections' );

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			// With no menu we still show ALL sections so the control remains usable.
			$all_terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
			wp_send_json_success( self::terms_to_hierarchical_options( is_array( $all_terms ) ? $all_terms : [] ) );
		}

		// A) Sections explicitly linked via _jprm_menu_id.
		$ids = self::get_section_ids_from_meta( $menu_id );

		// B) Add optional registry from option.
		$opt = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $opt[ $menu_id ] ) );
		}
		$ids = array_values( array_unique( array_filter( $ids, static fn( $n ) => $n > 0 ) ) );

		// If we have mapped ids, try to load just those. If that yields nothing, fall back to ALL.
		if ( ! empty( $ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $ids,
			] );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				wp_send_json_success( self::terms_to_hierarchical_options( $terms ) );
			}
		}

		// C) No mapping (yet) → ALL sections (hierarchical).
		$all_terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		wp_send_json_success( self::terms_to_hierarchical_options( is_array( $all_terms ) ? $all_terms : [] ) );
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

	/** Get section IDs linked to a menu via _jprm_menu_id (integer). */
	private static function get_section_ids_from_meta( int $menu_id ): array {
		$terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}
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
