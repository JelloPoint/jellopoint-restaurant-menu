<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor-only AJAX endpoints used by Elementor UI.
 *
 * - jprm_sections_by_menu: returns sections for a selected Menu, with hierarchy in labels.
 *   Source of truth (Option 1):
 *     A) Section term-meta **_jprm_menu_id** (single integer) linking a section to a menu.
 *     B) Option 'jprm_sections_catalog' (array: menu_id => [section_ids]) as an optional, additive registry.
 *     C) Fallback: ALL sections (hierarchical), so the selector stays usable even if no mapping exists yet.
 */
final class Editor_Endpoints {

	/** The single, canonical term-meta key on jprm_section that stores the owning menu's term_id. */
	private const SECTION_MENU_META_KEY = '_jprm_menu_id';

	public static function init(): void {
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );
	}

	/**
	 * AJAX: return available Sections for a Menu (hierarchical labels).
	 *
	 * Action: jprm_sections_by_menu
	 * Params: menu (string|int), _ajax_nonce (via JPRMAjax.nonce)
	 */
	public static function ajax_sections_by_menu(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		check_ajax_referer( 'jprm_sections' );

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			wp_send_json_success( [] );
		}

		// A) Sections explicitly linked via canonical section term-meta key.
		$ids = self::get_section_ids_from_meta( $menu_id );

		// B) Also include optional registry in option (if present).
		$opt = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $opt[ $menu_id ] ) );
		}

		$ids = array_values( array_unique( array_filter( $ids, static fn( $n ) => $n > 0 ) ) );

		$terms = [];
		if ( ! empty( $ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $ids,
			] );
		}

		// C) Fallback: show ALL sections (hierarchical) if no mapping yet.
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
		}

		$map = self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );

		wp_send_json_success( $map );
	}

	/* =========================
	 * Helpers
	 * ========================= */

	/** Normalize menu input (id/slug/name) to term_id in taxonomy jprm_menu. */
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

	/**
	 * Get section IDs linked to a menu via the canonical meta key on jprm_section.
	 * Key: _jprm_menu_id (integer menu term_id)
	 *
	 * @return int[]
	 */
	private static function get_section_ids_from_meta( int $menu_id ): array {
		$terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$out = [];
		foreach ( $terms as $t ) {
			$val = get_term_meta( $t->term_id, self::SECTION_MENU_META_KEY, true );
			if ( $val === '' || $val === null ) {
				continue;
			}
			$linked_menu = (int) $val;
			if ( $linked_menu === $menu_id ) {
				$out[] = (int) $t->term_id;
			}
		}
		return $out;
	}

	/**
	 * Build a flat id => label map with hierarchy indentation for jprm_section terms.
	 * Uses em-dash indentation ("— ") per depth level.
	 *
	 * @param \WP_Term[] $terms
	 * @return array<string,string>
	 */
	private static function terms_to_hierarchical_options( array $terms ): array {
		if ( empty( $terms ) ) {
			return [];
		}

		// Index by parent
		$by_parent = [];
		foreach ( $terms as $t ) {
			$by_parent[ (int) $t->parent ][] = $t;
		}

		$make_label = function( \WP_Term $term ): string {
			$depth  = count( get_ancestors( (int) $term->term_id, 'jprm_section', 'taxonomy' ) );
			$indent = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			return $indent . $term->name;
		};

		$roots = isset( $by_parent[0] ) ? $by_parent[0] : [];
		usort( $roots, static function( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		} );

		$out = [];

		$walk = function( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) {
			if ( empty( $by_parent[ $parent_id ] ) ) return;
			$children = $by_parent[ $parent_id ];
			usort( $children, static function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			} );
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
