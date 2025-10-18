<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Info Blocks renderer (between sections / before/after full menu)
 * Supported fields per block:
 * - type: html | image | button
 * - content_html (when type=html)
 * - image_id, image_alt (when type=image)
 * - button_text, button_url (Elementor URL control) (when type=button)
 * - style_variant: subtle | accent | note
 * - position: before_menu | between_sections | after_menu
 */

if ( ! function_exists( 'jprm_infoblocks_partition_by_position' ) ) :
function jprm_infoblocks_partition_by_position( array $rows ) : array {
	$out = [
		'before_menu'      => [],
		'between_sections' => [],
		'after_menu'       => [],
	];

	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) ) { continue; }
		$pos = isset( $r['position'] ) ? (string) $r['position'] : 'between_sections';
		if ( ! isset( $out[ $pos ] ) ) { $pos = 'between_sections'; }
		$out[ $pos ][] = $r;
	}
	return $out;
}
endif;

if ( ! function_exists( 'jprm_infoblocks_render_group' ) ) :
/**
 * Legacy group renderer (kept for BC).
 */
function jprm_infoblocks_render_group( array $rows, string $position ) : string {
	if ( empty( $rows ) ) return '';

	$allow = [
		'a'      => [ 'href'=>[], 'title'=>[], 'target'=>[], 'rel'=>[], 'class'=>[] ],
		'strong' => [], 'em'=>[], 'br'=>[], 'span'=>['class'=>[]],
		'p'      => [ 'class'=>[] ],
		'ul'     => [ 'class'=>[] ], 'ol'=>[ 'class'=>[] ], 'li'=>[ 'class'=>[] ],
		'img'    => [ 'src'=>[], 'alt'=>[], 'class'=>[] ],
	];

	$out = '<div class="jp-infoblocks jp-infoblocks--' . esc_attr( $position ) . '">';

	foreach ( $rows as $r ) {
		$type    = isset( $r['type'] ) ? (string) $r['type'] : 'html';
		$variant = isset( $r['style_variant'] ) ? (string) $r['style_variant'] : 'subtle';
		$cls     = 'jp-infoblock jp-infoblock--' . sanitize_html_class( $type ) . ' jp-infoblock--' . sanitize_html_class( $variant );

		if ( $type === 'image' ) {
			$img_id  = isset( $r['image_id']['id'] ) ? (int) $r['image_id']['id'] : (int) ( $r['image_id'] ?? 0 );
			$img_alt = isset( $r['image_alt'] ) ? (string) $r['image_alt'] : '';
			$src     = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
			if ( $src ) {
				$out .= '<div class="' . esc_attr( $cls ) . '"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $img_alt ) . '" class="jp-infoblock__img"></div>';
			}
			continue;
		}

		if ( $type === 'button' ) {
			$txt = isset( $r['button_text'] ) ? (string) $r['button_text'] : '';
			$url = '';
			if ( isset( $r['button_url']['url'] ) ) {
				$url = (string) $r['button_url']['url'];
			} elseif ( isset( $r['button_url'] ) && is_string( $r['button_url'] ) ) {
				$url = (string) $r['button_url'];
			}
			$ext = ! empty( $r['button_url']['is_external'] );
			$rel = $ext ? ' rel="noopener"' : '';
			$tgt = $ext ? ' target="_blank"' : '';
			if ( $txt !== '' && $url !== '' ) {
				$out .= '<p class="' . esc_attr( $cls ) . '"><a class="jp-infoblock__btn" href="' . esc_url( $url ) . '"' . $tgt . $rel . '>' . wp_kses_post( $txt ) . '</a></p>';
			}
			continue;
		}

		// default: html
		$html = isset( $r['content_html'] ) ? (string) $r['content_html'] : '';
		if ( $html !== '' ) {
			$out .= '<div class="' . esc_attr( $cls ) . '">' . wp_kses( $html, $allow ) . '</div>';
		}
	}

	$out .= '</div>';
	return $out;
}
endif;

/**
 * NEW: does a between-sections row belong after a given section?
 */
if ( ! function_exists( 'jprm_infoblocks_matches_section' ) ) :
function jprm_infoblocks_matches_section( array $row, $section_id_or_slug ) : bool {
	$target = isset( $row['after_section'] ) ? trim( (string) $row['after_section'] ) : '';
	if ( $target === '' ) { return true; } // no target => after every section
	if ( is_numeric( $target ) && (string) (int) $section_id_or_slug === (string) (int) $target ) {
		return true;
	}
	if ( ! is_numeric( $target ) && is_string( $section_id_or_slug ) ) {
		return strtolower( (string) $section_id_or_slug ) === strtolower( $target );
	}
	return false;
}
endif;

/**
 * NEW: render  a list of rows into a single container (prevents double wrapping)
 */
if ( ! function_exists( 'jprm_infoblocks_render_rows' ) ) :
function jprm_infoblocks_render_rows( array $rows, string $position ) : string {
	if ( empty( $rows ) ) return '';
	$allow = [
		'a'      => [ 'href'=>[], 'title'=>[], 'target'=>[], 'rel'=>[], 'class'=>[] ],
		'strong' => [], 'em'=>[], 'br'=>[], 'span'=>['class'=>[]],
		'p'      => [ 'class'=>[] ],
		'ul'     => [ 'class'=>[] ], 'ol'=>[ 'class'=>[] ], 'li'=>[ 'class'=>[] ],
		'img'    => [ 'src'=>[], 'alt'=>[], 'class'=>[] ],
	];
	$out = '<div class="jp-infoblocks jp-infoblocks--' . esc_attr( $position ) . '">';
	foreach ( $rows as $r ) {
		$type    = isset( $r['type'] ) ? (string) $r['type'] : 'html';
		$variant = isset( $r['style_variant'] ) ? (string) $r['style_variant'] : 'subtle';
		$cls     = 'jp-infoblock jp-infoblock--' . sanitize_html_class( $type ) . ' jp-infoblock--' . sanitize_html_class( $variant );

		if ( $type === 'image' ) {
			$img_id  = isset( $r['image_id']['id'] ) ? (int) $r['image_id']['id'] : (int) ( $r['image_id'] ?? 0 );
			$img_alt = isset( $r['image_alt'] ) ? (string) $r['image_alt'] : '';
			$src     = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
			if ( $src ) {
				$out .= '<div class="' . esc_attr( $cls ) . '"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $img_alt ) . '" class="jp-infoblock__img" loading="lazy" decoding="async"></div>';
			}
			continue;
		}

		if ( $type === 'button' ) {
			$txt = isset( $r['button_text'] ) ? (string) $r['button_text'] : '';
			$url = '';
			if ( isset( $r['button_url']['url'] ) ) { $url = (string) $r['button_url']['url']; }
			elseif ( isset( $r['button_url'] ) && is_string( $r['button_url'] ) ) { $url = (string) $r['button_url']; }
			$ext = ! empty( $r['button_url']['is_external'] );
			$rel = $ext ? ' rel="noopener"' : '';
			$tgt = $ext ? ' target="_blank"' : '';
			if ( $txt !== '' && $url !== '' ) {
				$out .= '<p class="' . esc_attr( $cls ) . '"><a class="jp-infoblock__btn" href="' . esc_url( $url ) . '"' . $tgt . $rel . '>' . wp_kses_post( $txt ) . '</a></p>';
			}
			continue;
		}

		$html = isset( $r['content_html'] ) ? (string) $r['content_html'] : '';
		if ( $html !== '' ) {
			$out .= '<div class="' . esc_attr( $cls ) . '">' . wp_kses( $html, $allow ) . '</div>';
		}
	}
	$out .= '</div>';
	return $out;
}
endif;
