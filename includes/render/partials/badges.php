<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Badges partial for JPRM
 *
 * Registry (global): option "jprm_dietary_badges_v1"
 *   Each row: [
 *     'name'     => string,
 *     'icon_id'  => mixed,
 *     'icon_url' => string,
 *     'active'   => bool-ish,
 *     'order'    => int
 *   ]
 *
 * Per item: post meta "jprm_badges" = JSON array of badge names
 *   Example: ["Vegan","Spicy","Gluten-free"]
 */

/** Build an ordered map of active badges keyed by name. */
function jprm_build_badge_map() : array {
	$opt = get_option( 'jprm_dietary_badges_v1', [] );
	if ( ! is_array( $opt ) ) return [];

	$rows = [];
	foreach ( $opt as $row ) {
		if ( ! is_array( $row ) ) continue;
		$name     = isset( $row['name'] ) ? (string) $row['name'] : '';
		$active   = ! empty( $row['active'] );
		$icon_id  = $row['icon_id'] ?? '';
		$icon_url = isset( $row['icon_url'] ) ? (string) $row['icon_url'] : '';
		$order    = isset( $row['order'] ) ? (int) $row['order'] : 0;

		if ( $name === '' || ! $active ) continue;
		$rows[ $name ] = [
			'name'     => $name,
			'icon_id'  => $icon_id,
			'icon_url' => $icon_url,
			'active'   => (bool) $active,
			'order'    => $order,
		];
	}

	uasort( $rows, static function( $a, $b ) {
		return ( $a['order'] <=> $b['order'] ) ?: strcasecmp( $a['name'], $b['name'] );
	});

	return $rows;
}

/** Read per-item selected badges, intersect with active registry, keep registry order. */
function jprm_get_item_badges( int $post_id, ?array $badge_map = null ) : array {
	if ( $badge_map === null ) $badge_map = jprm_build_badge_map();
	if ( empty( $badge_map ) ) return [];

	$raw = get_post_meta( $post_id, 'jprm_badges', true );
	if ( empty( $raw ) ) return [];

	$list = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $list ) ) return [];

	$want = [];
	foreach ( $list as $n ) {
		$n = (string) $n;
		if ( $n !== '' ) $want[ $n ] = true;
	}
	if ( empty( $want ) ) return [];

	$out = [];
	foreach ( $badge_map as $name => $row ) {
		if ( isset( $want[ $name ] ) ) $out[] = $row;
	}
	return $out;
}

/**
 * Render badges HTML.
 * $presentation: 'text' | 'icon' | 'icon_text'
 * $position:     'before' | 'after'
 */
function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $badge_map = null ) : string {
	$rows = jprm_get_item_badges( $post_id, $badge_map );
	if ( empty( $rows ) ) return '';

	$presentation = in_array( $presentation, [ 'text','icon','icon_text' ], true ) ? $presentation : 'icon_text';
	$position     = ( $position === 'after' ) ? 'after' : 'before';

	$out = '<span class="jp-badges jp-badges--' . esc_attr( $position ) . ' jp-badges--' . esc_attr( $presentation ) . '">';
	foreach ( $rows as $r ) {
		$name     = (string) ( $r['name'] ?? '' );
		$icon_url = (string) ( $r['icon_url'] ?? '' );

		$out .= '<span class="jp-badge">';
		if ( $icon_url !== '' && ( $presentation === 'icon' || $presentation === 'icon_text' ) ) {
			$out .= '<img class="jp-badge__icon" src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async" />';
		}
		if ( $name !== '' && ( $presentation === 'text' || $presentation === 'icon_text' ) ) {
			$out .= '<span class="jp-badge__text">' . esc_html( $name ) . '</span>';
		}
		$out .= '</span>';
	}
	$out .= '</span>';
	return $out;
}
