<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Menu structures own placements. Taxonomies are a projection for WordPress queries. */
final class Item_Assignments {
	private static $snapshot = [];

	public static function init() : void {
		add_action( 'jprm_before_structure_save', [ __CLASS__, 'capture' ] );
		add_action( 'jprm_structure_saved', [ __CLASS__, 'project' ], 10, 3 );
	}

	public static function structures() : array {
		$menus = get_terms( [ 'taxonomy' => 'jprm_menu', 'hide_empty' => false, 'fields' => 'ids', 'suppress_filter' => true ] );
		$out = [];
		if ( is_wp_error( $menus ) ) {
			// Fail before a structure save instead of treating an unreadable catalog as empty.
			throw new \RuntimeException( 'Unable to read Menu assignments.' );
		}
		foreach ( $menus as $menu_id ) { $out[ (int) $menu_id ] = Menu_Structure_Store::get( (int) $menu_id ); }
		return $out;
	}

	public static function capture() : void {
		// Read legacy menus before changing any taxonomy memberships.
		self::$snapshot = self::structures();
	}

	public static function project( int $menu_id, array $previous, array $next ) : void {
		$structures = self::$snapshot;
		$structures[ $menu_id ] = $next;
		$affected = [];
		foreach ( [ $previous, $next ] as $structure ) {
			foreach ( $structure['sections'] as $section ) {
				foreach ( $section['items'] as $item ) { $affected[ (int) $item['id'] ] = true; }
			}
		}
		foreach ( array_keys( $affected ) as $post_id ) { self::project_item( $post_id, $structures ); }
	}

	public static function project_item( int $post_id, array $structures ) : void {
		$menus = []; $sections = [];
		foreach ( $structures as $menu_id => $structure ) {
			foreach ( $structure['sections'] as $section ) {
				foreach ( $section['items'] as $item ) {
					if ( $post_id !== (int) $item['id'] ) { continue; }
					$menus[] = (int) $menu_id; $sections[] = (int) $section['id'];
				}
			}
		}
		wp_set_post_terms( $post_id, array_values( array_unique( $menus ) ), 'jprm_menu', false );
		wp_set_post_terms( $post_id, array_values( array_unique( $sections ) ), 'jprm_section', false );
	}

	/** Resolve flat import terms only when they identify complete, unambiguous placements. */
	public static function plan_names( int $post_id, array $menu_names, array $section_names ) {
		$sections = [];
		foreach ( $section_names as $name ) {
			$term = get_term_by( 'name', $name, 'jprm_section' );
			if ( ! $term || is_wp_error( $term ) ) { return false; }
			$sections[] = (int) $term->term_id;
		}
		$pairs = [];
		foreach ( self::structures() as $menu_id => $structure ) {
			if ( isset( Menu_Structure_Store::item_placements( $menu_id )[ $post_id ] ) ) { $pairs[ $menu_id ] = 0; }
		}
		$used = [];
		foreach ( $menu_names as $name ) {
			$menu = get_term_by( 'name', $name, 'jprm_menu' );
			if ( ! $menu || is_wp_error( $menu ) ) { return false; }
			$id = (int) $menu->term_id;
			$candidates = array_values( array_intersect( Menu_Structure_Store::section_ids( $id ), $sections ) );
			$current = Menu_Structure_Store::item_placements( $id )[ $post_id ]['section_id'] ?? 0;
			if ( 1 === count( $candidates ) ) { $pairs[ $id ] = $candidates[0]; }
			elseif ( $current && in_array( $current, $candidates, true ) ) { $pairs[ $id ] = $current; }
			else { return false; }
			$used[] = $pairs[ $id ];
		}
		// A flat CSV cannot encode an extra Section that has no Menu placement.
		if ( array_diff( $sections, $used ) ) { return false; }
		return $pairs;
	}

	/** Validate every pair before changing any Menu. Zero removes only that Menu's placement. */
	public static function replace( int $post_id, array $pairs ) : bool {
		foreach ( $pairs as $menu_id => $section_id ) {
			if ( $menu_id <= 0 || $section_id < 0 || ! term_exists( (int) $menu_id, 'jprm_menu' ) ) { return false; }
			if ( $section_id && ! in_array( $section_id, Menu_Structure_Store::section_ids( (int) $menu_id ), true ) ) { return false; }
		}
		foreach ( $pairs as $menu_id => $section_id ) {
			$current = Menu_Structure_Store::item_placements( (int) $menu_id )[ $post_id ] ?? null;
			if ( (int) ( $current['section_id'] ?? 0 ) === $section_id ) { continue; }
			$ok = $section_id ? Menu_Structure_Store::assign_items( (int) $menu_id, $section_id, [ $post_id ] ) : Menu_Structure_Store::unassign_item( (int) $menu_id, $post_id );
			if ( ! $ok ) { return false; }
		}
		self::project_item( $post_id, self::structures() );
		return true;
	}
}
