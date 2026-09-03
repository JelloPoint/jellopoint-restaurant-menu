<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per-Menu placement of reusable Info Block posts. */
final class Info_Block_Store {
	public const META_KEY = '_jprm_info_block_placements_v1';

	public static function get( int $menu_id ) : array {
		$rows = get_term_meta( $menu_id, self::META_KEY, true );
		return self::normalize( is_array( $rows ) ? $rows : [] );
	}

	public static function save( int $menu_id, array $rows ) : bool {
		return $menu_id > 0 && false !== update_term_meta( $menu_id, self::META_KEY, self::normalize( $rows ) );
	}

	public static function normalize( array $rows ) : array {
		$out = []; $seen = [];
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$id = (int) ( $row['id'] ?? 0 ); $section_id = (int) ( $row['section_id'] ?? 0 );
			$position = 'below' === (string) ( $row['position'] ?? '' ) ? 'below' : 'above';
			$key = $id . ':' . $section_id . ':' . $position;
			if ( $id <= 0 || $section_id <= 0 || isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = true;
			$out[] = [ 'id' => $id, 'section_id' => $section_id, 'position' => $position, 'order' => (int) ( $row['order'] ?? $index ) ];
		}
		usort( $out, static fn( array $a, array $b ) : int => $a['order'] <=> $b['order'] );
		foreach ( $out as $index => &$row ) { $row['order'] = $index; } unset( $row );
		return $out;
	}

	public static function data_for_menu( int $menu_id ) : array {
		$out = [];
		foreach ( self::get( $menu_id ) as $row ) {
			$post = get_post( $row['id'] );
			if ( ! $post || 'jprm_info_block' !== $post->post_type || 'publish' !== $post->post_status ) { continue; }
			$image_id = (int) get_post_thumbnail_id( $post->ID );
			$content = get_post_meta( $post->ID, 'jprm_info_block_content', true );
			if ( '' === (string) $content ) { $content = $post->post_content; }
			$out[] = array_merge( $row, [ 'title' => (string) $post->post_title, 'content_html' => (string) apply_filters( 'the_content', $content ), 'image' => [ 'id' => $image_id, 'url' => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '' ] ] );
		}
		return $out;
	}
}
