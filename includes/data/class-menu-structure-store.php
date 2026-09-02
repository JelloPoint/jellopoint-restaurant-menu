<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Versioned per-Menu Section and item relationships with a legacy read fallback. */
final class Menu_Structure_Store {
	public const META_KEY = '_jprm_menu_structure_v2';
	private const VERSION = 1;
	private const TAX_MENU = 'jprm_menu';
	private const TAX_SECTION = 'jprm_section';
	private const CPT_ITEM = 'jprm_menu_item';

	public static function has_explicit( int $menu_id ) : bool {
		return $menu_id > 0 && is_array( get_term_meta( $menu_id, self::META_KEY, true ) );
	}

	/** Return the explicit structure, or derive a non-mutating snapshot from legacy data. */
	public static function get( int $menu_id ) : array {
		if ( $menu_id <= 0 ) { return self::empty_structure(); }
		$stored = get_term_meta( $menu_id, self::META_KEY, true );
		return is_array( $stored ) ? self::normalize( $stored ) : self::legacy_snapshot( $menu_id );
	}

	/** Save a complete normalized Menu structure. */
	public static function save( int $menu_id, array $structure ) : bool {
		if ( $menu_id <= 0 ) { return false; }
		$result = update_term_meta( $menu_id, self::META_KEY, self::normalize( $structure ) );
		return false !== $result;
	}

	public static function empty_structure() : array {
		return [ 'version' => self::VERSION, 'sections' => [] ];
	}

	/** Normalize IDs, dense ordering, parents, cycles, and one item placement per Menu. */
	public static function normalize( array $structure ) : array {
		$input = isset( $structure['sections'] ) && is_array( $structure['sections'] ) ? $structure['sections'] : [];
		$sections = [];
		$seen_sections = [];

		foreach ( $input as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 || isset( $seen_sections[ $id ] ) ) { continue; }
			$seen_sections[ $id ] = true;
			$sections[] = [
				'id' => $id,
				'parent_id' => max( 0, (int) ( $row['parent_id'] ?? 0 ) ),
				'order' => isset( $row['order'] ) ? (int) $row['order'] : (int) $index,
				'items' => isset( $row['items'] ) && is_array( $row['items'] ) ? $row['items'] : [],
			];
		}

		usort( $sections, static function( array $a, array $b ) : int {
			return $a['order'] <=> $b['order'];
		} );
		$ids = array_fill_keys( array_column( $sections, 'id' ), true );
		$parents = [];
		foreach ( $sections as $row ) {
			$parent = (int) $row['parent_id'];
			$parents[ (int) $row['id'] ] = $parent > 0 && isset( $ids[ $parent ] ) && $parent !== (int) $row['id'] ? $parent : 0;
		}

		foreach ( $parents as $id => $parent ) {
			$trail = [ $id => true ];
			while ( $parent > 0 ) {
				if ( isset( $trail[ $parent ] ) ) { $parents[ $id ] = 0; break; }
				$trail[ $parent ] = true;
				$parent = (int) ( $parents[ $parent ] ?? 0 );
			}
		}

		$seen_items = [];
		foreach ( $sections as $section_index => &$section ) {
			$section['order'] = $section_index;
			$section['parent_id'] = $parents[ (int) $section['id'] ] ?? 0;
			$items = [];
			foreach ( $section['items'] as $item_index => $item ) {
				$item = is_array( $item ) ? $item : [ 'id' => $item ];
				$item_id = (int) ( $item['id'] ?? 0 );
				if ( $item_id <= 0 || isset( $seen_items[ $item_id ] ) ) { continue; }
				$seen_items[ $item_id ] = true;
				$items[] = [ 'id' => $item_id, 'order' => isset( $item['order'] ) ? (int) $item['order'] : (int) $item_index ];
			}
			usort( $items, static function( array $a, array $b ) : int { return $a['order'] <=> $b['order']; } );
			foreach ( $items as $item_index => &$item ) { $item['order'] = $item_index; }
			unset( $item );
			$section['items'] = $items;
		}
		unset( $section );

		return [ 'version' => self::VERSION, 'sections' => $sections ];
	}

	public static function section_ids( int $menu_id ) : array {
		return array_map( 'intval', array_column( self::get( $menu_id )['sections'], 'id' ) );
	}

	public static function attach_section( int $menu_id, int $section_id, int $parent_id = 0 ) : bool {
		if ( $menu_id <= 0 || $section_id <= 0 ) { return false; }
		$structure = self::get( $menu_id );
		foreach ( $structure['sections'] as $section ) {
			if ( (int) $section['id'] === $section_id ) { return true; }
		}
		$structure['sections'][] = [ 'id' => $section_id, 'parent_id' => $parent_id, 'order' => count( $structure['sections'] ), 'items' => [] ];
		return self::save( $menu_id, $structure );
	}

	public static function save_section_tree( int $menu_id, array $tree ) : bool {
		if ( $menu_id <= 0 ) { return false; }
		$current = self::get( $menu_id );
		$items_by_section = [];
		foreach ( $current['sections'] as $section ) { $items_by_section[ (int) $section['id'] ] = $section['items']; }
		$sections = [];
		foreach ( $tree as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 ) { continue; }
			$sections[] = [ 'id' => $id, 'parent_id' => max( 0, (int) ( $row['parent_id'] ?? 0 ) ), 'order' => (int) $index, 'items' => $items_by_section[ $id ] ?? [] ];
		}
		return self::save( $menu_id, [ 'sections' => $sections ] );
	}

	public static function detach_section( int $menu_id, int $section_id ) : bool {
		if ( $menu_id <= 0 || $section_id <= 0 ) { return false; }
		$structure = self::get( $menu_id );
		$remove = [ $section_id => true ];
		do {
			$changed = false;
			foreach ( $structure['sections'] as $section ) {
				$id = (int) $section['id'];
				if ( isset( $remove[ (int) $section['parent_id'] ] ) && ! isset( $remove[ $id ] ) ) { $remove[ $id ] = true; $changed = true; }
			}
		} while ( $changed );
		$structure['sections'] = array_values( array_filter( $structure['sections'], static function( array $section ) use ( $remove ) : bool {
			return ! isset( $remove[ (int) $section['id'] ] );
		} ) );
		return self::save( $menu_id, $structure );
	}

	/** Return item_id => placement for one Menu. */
	public static function item_placements( int $menu_id ) : array {
		$placements = [];
		foreach ( self::get( $menu_id )['sections'] as $section ) {
			foreach ( $section['items'] as $item ) {
				$placements[ (int) $item['id'] ] = [ 'section_id' => (int) $section['id'], 'order' => (int) $item['order'] ];
			}
		}
		return $placements;
	}

	/** Replace all item placements for one Menu, preserving its Section tree. */
	public static function save_items( int $menu_id, array $rows ) : bool {
		if ( $menu_id <= 0 ) { return false; }
		$structure = self::get( $menu_id );
		$section_indexes = [];
		foreach ( $structure['sections'] as $index => &$section ) { $section['items'] = []; $section_indexes[ (int) $section['id'] ] = $index; }
		unset( $section );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$item_id = (int) ( $row['id'] ?? 0 );
			$section_id = (int) ( $row['section_id'] ?? 0 );
			if ( $item_id <= 0 || ! isset( $section_indexes[ $section_id ] ) ) { continue; }
			$structure['sections'][ $section_indexes[ $section_id ] ]['items'][] = [ 'id' => $item_id, 'order' => (int) ( $row['order'] ?? 0 ) ];
		}
		return self::save( $menu_id, $structure );
	}

	/** Append items to a Section in one Menu, removing only prior placements in that same Menu. */
	public static function assign_items( int $menu_id, int $section_id, array $item_ids ) : bool {
		$structure = self::get( $menu_id );
		$ids = array_fill_keys( array_filter( array_map( 'intval', $item_ids ) ), true );
		$target_index = null;
		foreach ( $structure['sections'] as $index => &$section ) {
			if ( (int) $section['id'] === $section_id ) { $target_index = $index; }
			$section['items'] = array_values( array_filter( $section['items'], static function( array $item ) use ( $ids ) : bool { return ! isset( $ids[ (int) $item['id'] ] ); } ) );
		}
		unset( $section );
		if ( null === $target_index ) { return false; }
		$next = count( $structure['sections'][ $target_index ]['items'] );
		foreach ( array_keys( $ids ) as $item_id ) { $structure['sections'][ $target_index ]['items'][] = [ 'id' => (int) $item_id, 'order' => $next++ ]; }
		return self::save( $menu_id, $structure );
	}

	public static function unassign_item( int $menu_id, int $item_id ) : bool {
		if ( $menu_id <= 0 || $item_id <= 0 ) { return false; }
		$structure = self::get( $menu_id );
		foreach ( $structure['sections'] as &$section ) {
			$section['items'] = array_values( array_filter( $section['items'], static function( array $item ) use ( $item_id ) : bool { return (int) $item['id'] !== $item_id; } ) );
		}
		unset( $section );
		return self::save( $menu_id, $structure );
	}

	/** Build a backwards-compatible snapshot without writing any new metadata. */
	private static function legacy_snapshot( int $menu_id ) : array {
		$terms = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) { return self::empty_structure(); }

		$sections = [];
		foreach ( $terms as $term ) {
			if ( (int) get_term_meta( (int) $term->term_id, '_jprm_menu_term_id', true ) !== $menu_id ) { continue; }
			$sections[ (int) $term->term_id ] = [
				'id' => (int) $term->term_id,
				'parent_id' => (int) $term->parent,
				'order' => (int) get_term_meta( (int) $term->term_id, '_jprm_section_order', true ),
				'items' => [],
			];
		}
		if ( [] === $sections ) { return self::empty_structure(); }

		$query = new \WP_Query( [
			'post_type' => self::CPT_ITEM,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'tax_query' => [
				'relation' => 'AND',
				[ 'taxonomy' => self::TAX_MENU, 'field' => 'term_id', 'terms' => [ $menu_id ] ],
				[ 'taxonomy' => self::TAX_SECTION, 'field' => 'term_id', 'terms' => array_keys( $sections ), 'include_children' => false ],
			],
		] );
		foreach ( (array) $query->posts as $post_id ) {
			$item_sections = wp_get_post_terms( (int) $post_id, self::TAX_SECTION, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $item_sections ) || ! is_array( $item_sections ) ) { continue; }
			foreach ( $item_sections as $section_id ) {
				$section_id = (int) $section_id;
				if ( ! isset( $sections[ $section_id ] ) ) { continue; }
				$sections[ $section_id ]['items'][] = [ 'id' => (int) $post_id, 'order' => (int) get_post_meta( (int) $post_id, '_jprm_order_in_section', true ) ];
				break;
			}
		}

		return self::normalize( [ 'sections' => array_values( $sections ) ] );
	}
}
