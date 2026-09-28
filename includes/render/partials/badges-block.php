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

// inside rendering for each badge
if ( $presentation === 'icon' ) {
    $out .= '<span class="' . esc_attr( $cls . ' jp-badge--icon' ) . '">';
    if ( $icon !== '' ) {
        // unified: mask or cleaned svg/img based on URL/html
        $out .= jprm_colorize_icon('', $icon, 'badge');
        $out .= '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';
    } else {
        $out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span>';
    }
    $out .= '</span>';

} elseif ( $presentation === 'text' ) {
    $out .= '<span class="' . esc_attr( $cls . ' jp-badge--text' ) . '"><span class="jp-badge__label">' . esc_html( $name ) . '</span></span>';

} else { // icon_text
    $out .= '<span class="' . esc_attr( $cls . ' jp-badge--icontext' ) . '">';
    if ( $icon !== '' ) {
        $out .= jprm_colorize_icon('', $icon, 'badge');
    }
    $out .= '<span class="jp-badge__label">' . esc_html( $name ) . '</span></span>';
}

	}

	$out .= '</span>';
	return $out;
}
endif;

if ( ! function_exists( 'jprm_render_badges_legend_html' ) ) :
function jprm_render_badges_legend_html( array $sections_data, string $title = '' ) : string {
	$catalog = get_option( 'jprm_dietary_badges_v1', null );
	if ( ! is_array( $catalog ) || empty( $catalog ) ) { $catalog = get_option( 'jprm_dietary_badges', [] ); }
	if ( ! is_array( $catalog ) ) { return ''; }
	$used = [];
	foreach ( $sections_data as $bucket ) {
		foreach ( (array) ( $bucket['items'] ?? [] ) as $post ) {
			$slugs = get_post_meta( (int) $post->ID, 'jprm_item_badges', true );
			foreach ( is_array( $slugs ) ? $slugs : [] as $slug ) { $used[ sanitize_title( (string) $slug ) ] = true; }
		}
	}
	$items = [];
	foreach ( $catalog as $row ) {
		if ( ! is_array( $row ) || empty( $row['active'] ) ) { continue; }
		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		$slug = sanitize_title( (string) ( $row['slug'] ?? $name ) );
		if ( $name === '' || $slug === '' || ! isset( $used[ $slug ] ) ) { continue; }
		$items[] = [ 'name' => $name, 'icon' => (string) ( $row['icon_url'] ?? '' ), 'order' => (int) ( $row['order'] ?? 0 ) ];
	}
	if ( empty( $items ) ) { return ''; }
	usort( $items, static fn( $a, $b ) => $a['order'] <=> $b['order'] );
	$out = '<div class="jp-menu__badges-legend" aria-label="' . esc_attr__( 'Dietary badges legend', 'jellopoint-restaurant-menu' ) . '">';
	if ( trim( $title ) !== '' ) { $out .= '<h3 class="jp-menu__badges-legend-title">' . esc_html( $title ) . '</h3>'; }
	$out .= '<div class="jp-menu__badges-legend-items">';
	foreach ( $items as $item ) {
		$out .= '<span class="jp-menu__badges-legend-item">';
		if ( $item['icon'] !== '' ) { $out .= jprm_colorize_icon( '', $item['icon'], 'badge' ); }
		$out .= '<span class="jp-menu__badges-legend-label">' . esc_html( $item['name'] ) . '</span></span>';
	}
	return $out . '</div></div>';
}
endif;
