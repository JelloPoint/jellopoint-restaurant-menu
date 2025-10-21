<?php
/**
 * Menu dispatcher – routes to inline / inline-below / matrix templates
 * and provides shared helpers used by all templates.
 *
 * Expects $ctx from the widget render().
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------- Shared helpers ---------------- */

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

/** Build icon HTML from label meta. */
if ( ! function_exists( 'jprm_build_icon_html' ) ) {
	function jprm_build_icon_html( array $meta ) : string {
		if ( ! empty( $meta['icon_html'] ) ) return (string) $meta['icon_html'];

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

/** Normalize $ctx; DO NOT force any placeholders. */
if ( ! function_exists( 'jprm_ctx_normalize' ) ) {
	function jprm_ctx_normalize( array $ctx ) : array {
		// Presentation default
		if ( empty( $ctx['label_presentation'] ) ) $ctx['label_presentation'] = 'icon_text';

		// Accept several possible keys for the Matrix placeholder (be accommodating)
		$ph = '';
		foreach ( ['labels_matrix_placeholder','matrix_placeholder','labels_placeholder'] as $k ) {
			if ( array_key_exists( $k, $ctx ) ) {
				$ph = (string) $ctx[$k];
				break;
			}
		}
		$ctx['labels_matrix_placeholder'] = $ph;

		// Label map (and synthesize icon_html)
		$label_map = $ctx['label_map'] ?? null;
		if ( ! is_array( $label_map ) || empty( $label_map ) ) {
			if ( function_exists( 'jprm_get_active_labels_map' ) ) {
				$label_map = (array) jprm_get_active_labels_map();
			} elseif ( function_exists( 'jprm_labels_store_get' ) ) {
				$label_map = (array) jprm_labels_store_get();
			} else {
				$label_map = [];
			}
		}
		$norm = [];
		foreach ( $label_map as $id => $meta ) {
			$m = is_array( $meta ) ? $meta : [];
			$m['title']     = isset( $m['title'] ) ? (string) $m['title'] : (string) ( $m['text'] ?? '' );
			$m['icon_html'] = jprm_build_icon_html( $m );
			$norm[ (string) ( is_numeric( $id ) ? (int) $id : $id ) ] = $m;
		}
		$ctx['label_map'] = $norm;

		return $ctx;
	}
}

/**
 * Render a pricegroup (for Inline & Inline-Below) using structured data.
 * (Left here unchanged; Matrix will not use this — it extracts price-only.)
 */
if ( ! function_exists( 'jprm_render_pricegroup_inline_ctx' ) ) {
	function jprm_render_pricegroup_inline_ctx(
		int $post_id,
		string $presentation,
		string $label_position,
		array $label_map,
		array $currency_opts
	) : string {
		$rows = function_exists( 'jprm_get_pricegroup_data' )
			? (array) jprm_get_pricegroup_data( $post_id, $label_map, $currency_opts )
			: [];

		if ( empty( $rows ) ) return '';

		$out = '<div class="jp-pricegroup jp--presentation-' . esc_attr( $presentation ) . '">';
		foreach ( $rows as $r ) {
			$label_text = (string) ( $r['label_text'] ?? '' );
			$label_id   = isset( $r['label_id'] ) ? (string) (int) $r['label_id'] : '';
			$fmt        = (string) ( $r['formatted'] ?? '' );

			$icon_html  = (string) ( $r['icon_html'] ?? '' );
			if ( $icon_html === '' && $label_id !== '' && isset( $label_map[ $label_id ] ) ) {
				$icon_html = (string) ( $label_map[ $label_id ]['icon_html'] ?? '' );
			}

			if ( $presentation === 'icon' ) {
				$label_chip = $icon_html !== '' ? $icon_html : esc_html( $label_text );
			} elseif ( $presentation === 'text' ) {
				$label_chip = esc_html( $label_text );
			} else {
				$label_chip = ($icon_html !== '' && $label_text !== '')
					? '<span class="jp-menu__label">' . $icon_html . '<span>' . esc_html( $label_text ) . '</span></span>'
					: ($icon_html !== '' ? $icon_html : esc_html( $label_text ));
			}

			if ( $label_position === 'left' ) {
				$out .= '<div class="jp-menu__row"><span class="jp-chip">'.$label_chip.'</span><span class="jp-price">'.$fmt.'</span></div>';
			} else {
				$out .= '<div class="jp-menu__row"><span class="jp-price">'.$fmt.'</span><span class="jp-chip">'.$label_chip.'</span></div>';
			}
		}
		$out .= '</div>';
		return $out;
	}
}

/* ---------------- Normalize, pick template, include ---------------- */

$ctx = isset( $ctx ) && is_array( $ctx ) ? $ctx : [];
$ctx = jprm_ctx_normalize( $ctx );

$layout = isset( $ctx['global_labels_layout'] ) ? (string) $ctx['global_labels_layout'] : 'inline';
$layout = in_array( $layout, [ 'inline', 'inline_below', 'matrix' ], true ) ? $layout : 'inline';

$base_dir = __DIR__;
switch ( $layout ) {
	case 'matrix':       $tpl = $base_dir . '/matrix.php';       break;
	case 'inline_below': $tpl = $base_dir . '/inline-below.php'; break;
	case 'inline':
	default:             $tpl = $base_dir . '/inline.php';        break;
}

if ( file_exists( $tpl ) ) {
	include $tpl; // $ctx in scope
} else {
	echo '<ul class="jp-menu"></ul>';
}
