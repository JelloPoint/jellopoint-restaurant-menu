<?php
/**
 * Menu dispatcher – routes to inline / inline-below / matrix templates
 * and provides shared helpers used by all templates.
 *
 * Expects $ctx from the widget render().
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
/* DEBUG TEMPLATE OVERRIDES*/
if ( ! empty( $_GET['jprm_probe'] ) ) {
    echo "\n<!-- jprm: menu.php loaded -->\n";
    echo "<!-- jprm: section_layouts = " . esc_html( json_encode( $ctx['section_layouts'] ?? [] ) ) . " -->\n";
    echo "<!-- jprm: section_overrides = " . esc_html( json_encode( $ctx['section_overrides'] ?? [] ) ) . " -->\n";
}
/* END DEBUG*/
/* ---------------- Shared helpers (safe to redeclare) ------------------- */

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
 * Extract ONLY the first <img> or <svg> from an arbitrary HTML snippet.
 * Prevents dumping whole SVG sprite sheets (which show “all icons”).
 */
if ( ! function_exists( 'jprm_sanitize_single_icon' ) ) {
	function jprm_sanitize_single_icon( string $html ) : string {
		$html = trim( $html );
		if ( $html === '' ) return '';
		// First try <img ...>
		if ( preg_match( '~<img\b[^>]*>~is', $html, $m ) ) {
			return $m[0];
		}
		// Then first <svg ...>...</svg>
		if ( preg_match( '~<svg\b[^>]*>.*?</svg>~is', $html, $m ) ) {
			return $m[0];
		}
		return '';
	}
}

/** Build icon HTML from label meta (then sanitize to a single icon). */
if ( ! function_exists( 'jprm_build_icon_html' ) ) {
	function jprm_build_icon_html( array $meta ) : string {
		// Explicit HTML fields sometimes hold sprite blocks — sanitize.
		foreach ( ['icon_html','icon','svg'] as $k ) {
			if ( ! empty( $meta[$k] ) ) {
				$out = jprm_sanitize_single_icon( (string) $meta[$k] );
				if ( $out !== '' ) return $out;
			}
		}
		// Attachment ID
		if ( ! empty( $meta['icon_id'] ) && function_exists( 'wp_get_attachment_image' ) ) {
			$html = wp_get_attachment_image( (int) $meta['icon_id'], 'thumbnail', false, [
				'class'    => 'jp-label__icon',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => '',
			] );
			if ( $html ) return jprm_sanitize_single_icon( $html );
		}
		// Direct URL
		foreach ( ['icon_url','url','image_url'] as $k ) {
			if ( ! empty( $meta[$k] ) ) {
				$url = esc_url( (string) $meta[$k] );
				return '<img class="jp-label__icon" src="' . $url . '" alt="" loading="lazy" decoding="async" />';
			}
		}
		return '';
	}
}

/**
 * Normalize $ctx – ensure keys exist and label icons are synthesized.
 * IMPORTANT: we do NOT force a matrix placeholder; default is '' (no clutter).
 */
if ( ! function_exists( 'jprm_ctx_normalize' ) ) {
	function jprm_ctx_normalize( array $ctx ) : array {
		// Defaults
		if ( empty( $ctx['label_presentation'] ) ) $ctx['label_presentation'] = 'icon_text';
		if ( ! isset( $ctx['labels_matrix_placeholder'] ) ) $ctx['labels_matrix_placeholder'] = '';

		// Label map
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

		// Ensure icon_html is a single icon
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
 * Render a pricegroup (for Inline & Inline-Below) using structured rows so icons show,
 * and sanitize any incoming icon HTML to just ONE icon.
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

			$icon_html  = jprm_sanitize_single_icon( (string) ( $r['icon_html'] ?? '' ) );
			if ( $icon_html === '' && $label_id !== '' && isset( $label_map[ $label_id ] ) ) {
				$icon_html = jprm_sanitize_single_icon( (string) ( $label_map[ $label_id ]['icon_html'] ?? '' ) );
			}

			// Compose chip
			if ( $presentation === 'icon' ) {
				$label_chip = $icon_html !== '' ? $icon_html : esc_html( $label_text );
			} elseif ( $presentation === 'text' ) {
				$label_chip = esc_html( $label_text );
			} else { // icon_text
				$label_chip = ($icon_html !== '' && $label_text !== '')
					? '<span class="jp-menu__label">'.$icon_html.'<span>'.esc_html($label_text).'</span></span>'
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

/* ---------------- Normalize, pick template, include ------------------- */

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
