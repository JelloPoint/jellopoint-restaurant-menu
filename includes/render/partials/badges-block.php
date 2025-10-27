<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render Dietary Badges for a Menu Item inline (before/after title).
 *
 * Uses item meta 'jprm_item_badges' => array of slugs.
 * Catalog is stored in 'jprm_dietary_badges_v1' (fallback to 'jprm_dietary_badges').
 *
 * @param int    $post_id
 * @param string $presentation 'icon' | 'text' | 'icon_text'
 * @return string HTML (empty string if no badges)
 */
if ( ! function_exists( 'jprm_render_badges_inline_html' ) ) :
function jprm_render_badges_inline_html( int $post_id, string $presentation = 'icon_text' ) : string {

	$slugs = get_post_meta( $post_id, 'jprm_item_badges', true );
	if ( ! is_array( $slugs ) || empty( $slugs ) ) {
		return '';
	}

	// Load catalog (v1 first, then legacy)
	// NEW (works even if v1 exists but is empty)
$catalog = get_option( 'jprm_dietary_badges_v1', null );
if ( ! is_array( $catalog ) || empty( $catalog ) ) {
    $catalog = get_option( 'jprm_dietary_badges', [] );
}
if ( ! is_array( $catalog ) || empty( $catalog ) ) {
    return ''; // no catalog at all
}

	// Build fast lookup by slug; catalog entries expected like:
	// [ [ 'name' => 'Vegan', 'slug' => 'vegan', 'icon_url' => '...', 'active' => 1, 'order' => 0 ], ... ]
	$by_slug = [];
	foreach ( $catalog as $row ) {
		if ( empty( $row ) || ! is_array( $row ) ) { continue; }
		$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
		if ( $slug === '' ) { continue; }
		$by_slug[ $slug ] = [
			'name'     => isset( $row['name'] ) ? (string) $row['name'] : $slug,
			'icon_url' => isset( $row['icon_url'] ) ? (string) $row['icon_url'] : '',
			'active'   => isset( $row['active'] ) ? (bool) $row['active'] : true,
			'order'    => isset( $row['order'] ) ? (int) $row['order'] : 0,
		];
	}

	// Keep original order of $slugs but drop unknown/inactive ones.
	$items = [];
	foreach ( $slugs as $slug ) {
		$slug = (string) $slug;
		if ( $slug === '' || ! isset( $by_slug[ $slug ] ) ) { continue; }
		$row = $by_slug[ $slug ];
		if ( ! $row['active'] ) { continue; }
		$items[] = [
			'slug'  => $slug,
			'name'  => $row['name'],
			'icon'  => $row['icon_url'],
			'order' => $row['order'],
		];
	}

	if ( empty( $items ) ) { return ''; }

	// Container
	$out  = '<span class="jp-menu__badges" aria-label="' . esc_attr__( 'Dietary badges', 'jellopoint-restaurant-menu' ) . '">';

	foreach ( $items as $it ) {
		$slug = $it['slug'];
		$name = $it['name'];
		$icon = $it['icon'];

		$base = 'jp-badge';
		$cls  = $base . ' ' . $base . '--' . sanitize_html_class( $slug );

		if ( $presentation === 'icon' ) {
			$out .= '<span class="' . esc_attr( $cls . ' jp-badge--icon' ) . '">';
			if ( $icon !== '' ) {
				$out .= '<img class="jp-badge__icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
				$out .= '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';
			} else {
				// No icon available → graceful text fallback
				$out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span>';
			}
			$out .= '</span>';

		} elseif ( $presentation === 'text' ) {
			$out .= '<span class="' . esc_attr( $cls . ' jp-badge--text' ) . '">';
			$out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span>';
			$out .= '</span>';

		} else { // 'icon_text'
			$out .= '<span class="' . esc_attr( $cls . ' jp-badge--icontext' ) . '">';
			if ( $icon !== '' ) {
				$out .= '<img class="jp-badge__icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
			}
			$out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span>';
			$out .= '</span>';
		}
	}

	$out .= '</span>';
	return $out;
}
endif;
