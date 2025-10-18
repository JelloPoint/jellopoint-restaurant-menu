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
 * Render a group of info blocks at a given position.
 *
 * @param array  $rows Blocks
 * @param string $position before_menu|between_sections|after_menu
 * @return string HTML
 */
function jprm_infoblocks_render_group( array $rows, string $position ) : string {
	if ( empty( $rows ) ) { return ''; }

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
			if ( $txt !== '' && $url !== '' ) {
				$target = ( ! empty( $r['button_url']['is_external'] ) ) ? ' target="_blank" rel="noopener"' : '';
				$out   .= '<div class="' . esc_attr( $cls ) . '"><a class="jp-button" href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $txt ) . '</a></div>';
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
