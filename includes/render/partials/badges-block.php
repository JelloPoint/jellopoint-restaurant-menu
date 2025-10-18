<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'jprm_render_badges_inline_html' ) ) :
/**
 * Render Dietary Badges for a Menu Item inline (before/after title).
 *
 * @param int    $post_id
 * @param string $presentation 'text' | 'icon' | 'icon_text'
 * @return string HTML
 */
function jprm_render_badges_inline_html( int $post_id, string $presentation = 'icon_text' ) : string {
	$slugs = get_post_meta( $post_id, 'jprm_item_badges', true );
	if ( ! is_array( $slugs ) || empty( $slugs ) ) { return ''; }

	$catalog = get_option( 'jprm_dietary_badges', [] );
	if ( ! is_array( $catalog ) || empty( $catalog ) ) { return ''; }

	// Map catalog by slug
	$map = [];
	foreach ( $catalog as $row ) {
		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		if ( $name === '' ) { continue; }
		$slug = ! empty( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $name );
		$map[ $slug ] = [
			'name'     => $name,
			'icon_url' => isset( $row['icon_url'] ) ? (string) $row['icon_url'] : '',
			'active'   => array_key_exists( 'active', $row ) ? (bool) $row['active'] : true,
		];
	}

	$out = '';
	$out .= '<div class="jp-menu__badges" aria-label="Dietary badges">';

	foreach ( $slugs as $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! isset( $map[ $slug ] ) ) { continue; }
		$row = $map[ $slug ];
		$name = esc_html( $row['name'] );
		$icon = $row['icon_url'] ? '<img class="jp-badge__icon" src="' . esc_url( $row['icon_url'] ) . '" alt="" />' : '';
		$cls  = 'jp-badge';
		if ( ! $row['active'] ) { $cls .= ' is-inactive'; }

		if ( $presentation === 'text' ) {
			$out .= '<span class="' . esc_attr( $cls . ' jp-badge--text' ) . '"><span class="jp-badge__label">' . $name . '</span></span>';
		} elseif ( $presentation === 'icon' ) {
			if ( $icon === '' ) { continue; }
			$out .= '<span class="' . esc_attr( $cls . ' jp-badge--icon' ) . '">' . $icon . '</span>';
		} else { // icon_text
			if ( $icon !== '' ) {
				$out .= '<span class="' . esc_attr( $cls . ' jp-badge--icontext' ) . '">' . $icon . '<span class="jp-badge__label">' . $name . '</span></span>';
			} else {
				$out .= '<span class="' . esc_attr( $cls . ' jp-badge--text' ) . '"><span class="jp-badge__label">' . $name . '</span></span>';
			}
		}
	}

	$out .= '</div>';

	return $out;
}
endif;
