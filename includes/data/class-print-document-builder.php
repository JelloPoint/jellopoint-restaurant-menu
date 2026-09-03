<?php
namespace JelloPoint\RestaurantMenu\Data;

use JelloPoint\RestaurantMenu\Storage\Price_Repository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Creates one template-neutral document from the existing Menu data. */
final class Print_Document_Builder {
	public static function build( int $menu_id, array $settings = [] ) : array {
		$menu = get_term( $menu_id, 'jprm_menu' );
		if ( $menu_id <= 0 || ! $menu || is_wp_error( $menu ) ) { return []; }

		$settings['menu_id'] = $menu_id;
		$settings = Print_Document_Settings::sanitize( $settings );
		$structure = Menu_Structure_Store::get( $menu_id );
		$badge_map = self::badge_map();
		$sections = [];

		foreach ( $structure['sections'] as $section_row ) {
			$term = get_term( (int) $section_row['id'], 'jprm_section' );
			if ( ! $term || is_wp_error( $term ) ) { continue; }
			$items = [];
			foreach ( $section_row['items'] as $item_row ) {
				$post = get_post( (int) $item_row['id'] );
				if ( ! $post || 'jprm_menu_item' !== $post->post_type || 'publish' !== $post->post_status ) { continue; }
				$items[] = self::item( $post, $badge_map );
			}
			$sections[] = [
				'id' => (int) $term->term_id,
				'name' => (string) $term->name,
				'parent_id' => (int) $section_row['parent_id'],
				'order' => (int) $section_row['order'],
				'item_separator' => (string) get_term_meta( (int) $term->term_id, '_jprm_item_separator', true ),
				'disable_item_separator' => '1' === (string) get_term_meta( (int) $term->term_id, '_jprm_disable_item_separator', true ),
				'items' => $items,
			];
		}

		return [
			'schema_version' => 1,
			'settings' => $settings,
			'menu' => [
				'id' => (int) $menu->term_id,
				'name' => (string) $menu->name,
				'description' => (string) $menu->description,
				'daily' => self::daily_data( $menu_id ),
			],
			'sections' => $sections,
		];
	}

	private static function item( \WP_Post $post, array $badge_map ) : array {
		$slugs = get_post_meta( $post->ID, 'jprm_item_badges', true );
		$badges = [];
		foreach ( is_array( $slugs ) ? $slugs : [] as $slug ) {
			$key = sanitize_title( (string) $slug );
			if ( isset( $badge_map[ $key ] ) ) { $badges[] = $badge_map[ $key ]; }
		}
		return [
			'id' => (int) $post->ID,
			'title' => (string) $post->post_title,
			'description' => (string) get_post_meta( $post->ID, 'jprm_desc', true ),
			'price' => self::price_data( $post->ID ),
			'badges' => $badges,
		];
	}

	private static function price_data( int $post_id ) : array {
		$config = Price_Repository::get( $post_id );
		if ( ! is_array( $config ) ) { return []; }
		if ( 'multi' === (string) ( $config['mode'] ?? '' ) ) {
			$config['rows'] = isset( $config['rows'] ) && is_array( $config['rows'] ) ? $config['rows'] : [];
			foreach ( $config['rows'] as &$row ) {
				$row['label'] = \JPRM_Labels_Store::resolve( (string) ( $row['label_ref'] ?? '' ) );
			}
			unset( $row );
		} else {
			$config['label'] = \JPRM_Labels_Store::resolve( (string) ( $config['label_ref'] ?? '' ) );
		}
		return $config;
	}

	private static function badge_map() : array {
		$map = [];
		$store = new \JPRM_Badges_Store();
		foreach ( $store->get_rows() as $row ) {
			if ( empty( $row['active'] ) ) { continue; }
			$map[ (string) $row['slug'] ] = [
				'name' => (string) $row['name'], 'slug' => (string) $row['slug'],
				'icon_id' => (int) $row['icon_id'], 'icon_url' => (string) $row['icon_url'],
			];
		}
		return $map;
	}

	private static function daily_data( int $menu_id ) : array {
		return [
			'enabled' => '1' === (string) get_term_meta( $menu_id, '_jprm_is_daily_menu', true ),
			'date_type' => (string) get_term_meta( $menu_id, '_jprm_daily_menu_date_type', true ),
			'start_date' => (string) get_term_meta( $menu_id, '_jprm_daily_menu_date', true ),
			'end_date' => (string) get_term_meta( $menu_id, '_jprm_daily_menu_end_date', true ),
			'fixed_price' => (string) get_term_meta( $menu_id, '_jprm_daily_menu_fixed_price', true ),
			'item_separator' => (string) get_term_meta( $menu_id, '_jprm_daily_menu_item_separator', true ),
		];
	}
}
