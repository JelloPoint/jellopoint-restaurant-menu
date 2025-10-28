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
$catalog = get_option( 'jprm_dietary_badges_v1', null );
if ( ! is_array( $catalog ) || empty( $catalog ) ) {
    $catalog = get_option( 'jprm_dietary_badges', [] );
}
if ( ! is_array( $catalog ) || empty( $catalog ) ) {
    return ''; // no catalog at all
}

/* Build lookup by slug.
   IMPORTANT: derive slug from name when row['slug'] missing.
*/
$by_slug = [];
foreach ( $catalog as $row ) {
    if ( empty( $row ) || ! is_array( $row ) ) { continue; }

    $name = isset( $row['name'] ) ? (string) $row['name'] : '';
    // Prefer explicit slug when present, else derive from name
    $slug = '';
    if ( ! empty( $row['slug'] ) && is_string( $row['slug'] ) ) {
        $slug = sanitize_title( $row['slug'] );
    } elseif ( $name !== '' ) {
        $slug = sanitize_title( $name );
    }
    if ( $slug === '' ) { continue; }

    $by_slug[ $slug ] = [
        'name'     => ( $name !== '' ? $name : $slug ),
        'icon_url' => isset( $row['icon_url'] ) ? (string) $row['icon_url'] : '',
        'active'   => array_key_exists( 'active', $row ) ? (bool) $row['active'] : true,
        'order'    => isset( $row['order'] ) ? (int) $row['order'] : 0,
    ];
}

/* Normalize selected slugs and match */
$items = [];
foreach ( $slugs as $slug ) {
    $slug = sanitize_title( (string) $slug );  // ← normalize incoming selection
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
        // SVG as colorable mask (uses currentColor)
        if ( preg_match('~\.svg(\?.*)?$~i', $icon) || strpos($icon, 'data:image/svg+xml') === 0 ) {
            $url = esc_url($icon);
            $out .= '<span class="jp-badge__icon jp-badge__icon--mask" style="-webkit-mask-image:url(\''.$url.'\');mask-image:url(\''.$url.'\');" aria-hidden="true"></span>';
            // Keep accessible text for icon-only
            $out .= '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';
        } else {
            // Raster fallback
            $out .= '<img class="jp-badge__icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
            $out .= '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';
        }
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
        if ( preg_match('~\.svg(\?.*)?$~i', $icon) || strpos($icon, 'data:image/svg+xml') === 0 ) {
            $url = esc_url($icon);
            $out .= '<span class="jp-badge__icon jp-badge__icon--mask" style="-webkit-mask-image:url(\''.$url.'\');mask-image:url(\''.$url.'\');" aria-hidden="true"></span>';
        } else {
            $out .= '<img class="jp-badge__icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
        }
    }

    $out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span>';
    $out .= '</span>';
}


	$out .= '</span>';
	return $out;
}
endif;
