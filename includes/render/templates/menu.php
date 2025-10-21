<?php
/**
 * Menu dispatcher – routes to inline / inline-below / matrix templates.
 * Also normalizes $ctx so all templates (esp. Matrix) have what they need.
 *
 * Expects an incoming $ctx from the widget render(), but will fill sane
 * defaults when keys are missing (label_map, matrix placeholder, etc).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared helper for menu meta (kept here to share across includes) */
if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
	function jprm_render_menu_meta( $term, bool $show_title, bool $show_desc, string $scope ) : string {
		if ( ! $term || ( ! $show_title && ! $show_desc ) ) return '';
		$title = $show_title ? trim( (string) $term->name ) : '';
		$desc  = $show_desc  ? trim( (string) $term->description ) : '';
		if ( $title === '' && $desc === '' ) return '';
		$cls = 'jp-menu__meta ' . ( $scope === 'global' ? 'jp-menu__meta--global' : 'jp-menu__meta--col' );
		$out  = '<div class="' . esc_attr( $cls ) . '">';
		if ( $title !== '' ) $out .= '<h2 class="jp-menu__meta-title">' . esc_html( $title ) . '</h2>';
		if ( $desc  !== '' ) $out .= '<div class="jp-menu__meta-desc">' . esc_html( $desc ) . '</div>';
		$out .= '</div>';
		return $out;
	}
}

/**
 * Build icon HTML from common label meta shapes.
 */
if ( ! function_exists( 'jprm_build_icon_html' ) ) {
	function jprm_build_icon_html( array $meta ) : string {
		if ( ! empty( $meta['icon_html'] ) ) {
			return (string) $meta['icon_html'];
		}
		if ( ! empty( $meta['icon_id'] ) && function_exists( 'wp_get_attachment_image' ) ) {
			$html = wp_get_attachment_image( (int) $meta['icon_id'], 'thumbnail', false, [
				'class'    => 'jp-label__icon',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => '',
			] );
			if ( $html ) return $html;
		}
		if ( ! empty( $meta['icon_url'] ) ) {
			$url = esc_url( (string) $meta['icon_url'] );
			return '<img class="jp-label__icon" src="' . $url . '" alt="" loading="lazy" decoding="async" />';
		}
		return '';
	}
}

/**
 * Normalize $ctx – ensure keys exist for templates.
 * - label_map: array of label_id => ['title','icon_html',...]
 * - labels_matrix_placeholder: string (default '—')
 * - label_presentation default: 'icon_text'
 */
if ( ! function_exists( 'jprm_ctx_normalize' ) ) {
	function jprm_ctx_normalize( array $ctx ) : array {
		// Presentation defaults
		if ( empty( $ctx['label_presentation'] ) ) {
			$ctx['label_presentation'] = 'icon_text';
		}
		if ( ! isset( $ctx['labels_matrix_placeholder'] ) ) {
			// default to an em-dash; templates can still render '' if user clears control
			$ctx['labels_matrix_placeholder'] = '—';
		}

		// Label map: try existing; otherwise attempt to fetch from a project helper if present.
		$label_map = $ctx['label_map'] ?? null;
		if ( ! is_array( $label_map ) || empty( $label_map ) ) {
			if ( function_exists( 'jprm_get_active_labels_map' ) ) {
				// Preferred project helper: should return [ id => ['title'=>..., 'icon_id'=>..., ...], ... ]
				$label_map = (array) jprm_get_active_labels_map();
			} elseif ( function_exists( 'jprm_labels_store_get' ) ) {
				// Alternative store getter if your codebase provides it
				$label_map = (array) jprm_labels_store_get();
			} else {
				$label_map = [];
			}
		}

		// Ensure each label has icon_html synthesized
		$normalized = [];
		foreach ( $label_map as $id => $meta ) {
			if ( ! is_array( $meta ) ) $meta = [];
			$meta['title']     = isset( $meta['title'] ) ? (string) $meta['title'] : (string) ( $meta['text'] ?? '' );
			$meta['icon_html'] = jprm_build_icon_html( $meta );
			$normalized[ (string) ( is_numeric( $id ) ? (int) $id : $id ) ] = $meta;
		}
		$ctx['label_map'] = $normalized;

		return $ctx;
	}
}

/* ===== Normalize context and choose template ============================ */

$ctx = isset( $ctx ) && is_array( $ctx ) ? $ctx : [];
$ctx = jprm_ctx_normalize( $ctx );

$layout = isset( $ctx['global_labels_layout'] ) ? (string) $ctx['global_labels_layout'] : 'inline';
$layout = in_array( $layout, [ 'inline', 'inline_below', 'matrix' ], true ) ? $layout : 'inline';

$base_dir = __DIR__;

switch ( $layout ) {
	case 'matrix':
		$tpl = $base_dir . '/matrix.php';
		break;
	case 'inline_below':
		$tpl = $base_dir . '/inline-below.php';
		break;
	case 'inline':
	default:
		$tpl = $base_dir . '/inline.php';
		break;
}

if ( file_exists( $tpl ) ) {
	// Templates expect $ctx in scope
	include $tpl;
} else {
	echo '<ul class="jp-menu"></ul>';
}
