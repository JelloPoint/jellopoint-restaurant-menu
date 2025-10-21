<?php
/**
 * Menu dispatcher: routes to inline / inline-below / matrix templates
 * + provides shared helpers + a small diagnostics panel (opt-in via ?jprm_diag=1).
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
		$keys_text = ['icon_html','icon','svg']; // common custom keys
		foreach ( $keys_text as $k ) {
			if ( ! empty( $meta[$k] ) ) return (string) $meta[$k];
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
		$url_keys = ['icon_url','url','image_url'];
		foreach ( $url_keys as $k ) {
			if ( ! empty( $meta[$k] ) ) {
				$url = esc_url( (string) $meta[$k] );
				return '<img class="jp-label__icon" src="' . $url . '" alt="" loading="lazy" decoding="async" />';
			}
		}
		return '';
	}
}

/** Normalize $ctx; DO NOT force any placeholders. */
if ( ! function_exists( 'jprm_ctx_normalize' ) ) {
	function jprm_ctx_normalize( array $ctx ) : array {
		if ( empty( $ctx['label_presentation'] ) ) $ctx['label_presentation'] = 'icon_text';
		if ( ! isset( $ctx['labels_matrix_placeholder'] ) ) $ctx['labels_matrix_placeholder'] = '';

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
 * Render pricegroup inline using structured data (ensures icons show).
 */
if ( ! function_exists( 'jprm_render_pricegroup_inline_ctx' ) ) {
	function jprm_render_pricegroup_inline_ctx( int $post_id, string $presentation, string $label_position, array $label_map, array $currency_opts ) : string {
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
				$out .= '<div class="jp-menu__row" data-has-icon="' . ( $icon_html !== '' ? '1':'0' ) . '"><span class="jp-chip">'.$label_chip.'</span><span class="jp-price">'.$fmt.'</span></div>';
			} else {
				$out .= '<div class="jp-menu__row" data-has-icon="' . ( $icon_html !== '' ? '1':'0' ) . '"><span class="jp-price">'.$fmt.'</span><span class="jp-chip">'.$label_chip.'</span></div>';
			}
		}
		$out .= '</div>';
		return $out;
	}
}

/* ---------------- Diagnostics (opt-in) ---------------- */
/**
 * Add ?jprm_diag=1 to the page URL to see the panel.
 * Shows: label_presentation, labels_matrix_placeholder, label_map (icon yes/no),
 * and first section's first item's price rows (icon yes/no).
 */
if ( ! function_exists( 'jprm_diag_panel' ) ) {
	function jprm_diag_panel( array $ctx ) : void {
		if ( empty( $_GET['jprm_diag'] ) ) return;

		$lp   = (string) ( $ctx['label_presentation'] ?? '' );
		$ph   = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
		$lmap = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];

		$first_rows = [];
		$first_sid  = null;
		$first_pid  = null;

		$sections_order = $ctx['sections_order'] ?? [];
		$sections_data  = $ctx['sections_data'] ?? [];
		foreach ( $sections_order as $sid ) {
			if ( ! empty( $sections_data[$sid]['items'] ) ) {
				$first_sid = $sid;
				$post = $sections_data[$sid]['items'][0] ?? null;
				if ( $post ) {
					$first_pid = (int) $post->ID;
					if ( function_exists( 'jprm_get_pricegroup_data' ) ) {
						$first_rows = (array) jprm_get_pricegroup_data( $first_pid, $lmap, (array) ($ctx['currency_opts'] ?? []) );
					}
				}
				break;
			}
		}

		echo '<div style="position:relative;z-index:99999;margin:1rem 0;padding:12px;border:2px dashed #e67e22;background:#fff6ec;font:12px/1.4 system-ui">';
		echo '<strong>JPRM Diagnostics</strong><br>';
		echo 'label_presentation: <code>' . esc_html( $lp ) . '</code><br>';
		echo 'labels_matrix_placeholder: <code>' . esc_html( $ph ) . '</code><br>';
		echo 'labels in label_map: <code>' . count( $lmap ) . '</code><br>';

		// Label map rows (id, title, has icon)
		if ( $lmap ) {
			echo '<details style="margin-top:6px"><summary>label_map details</summary><ul style="margin:6px 0 0 16px">';
			foreach ( $lmap as $id => $m ) {
				$title = isset($m['title']) ? (string)$m['title'] : '';
				$has   = ! empty( $m['icon_html'] ) ? 'yes' : 'no';
				echo '<li><code>' . esc_html( (string)$id ) . '</code> – ' . esc_html( $title ) . ' (icon: ' . $has . ')</li>';
			}
			echo '</ul></details>';
		} else {
			echo '<div style="color:#c0392b;margin-top:6px">label_map is EMPTY → icons cannot show. Check your labels store.</div>';
		}

		// First item rows
		if ( $first_pid ) {
			echo '<div style="margin-top:6px">first section: <code>' . esc_html( (string)$first_sid ) . '</code>, first item ID: <code>' . esc_html( (string)$first_pid ) . '</code></div>';
			if ( $first_rows ) {
				echo '<details style="margin-top:6px" open><summary>first item price rows</summary><ul style="margin:6px 0 0 16px">';
				foreach ( $first_rows as $r ) {
					$lid = isset($r['label_id']) ? (string)(int)$r['label_id'] : '';
					$txt = (string) ($r['label_text'] ?? '');
					$fmt = (string) ($r['formatted'] ?? '');
					$has = ! empty( $r['icon_html'] ) ? 'yes' : ( ( $lid !== '' && ! empty($lmap[$lid]['icon_html']) ) ? 'yes' : 'no' );
					echo '<li>label_id:<code>' . esc_html($lid) . '</code> text:<code>' . esc_html($txt) . '</code> price:<code>' . esc_html($fmt) . '</code> icon:' . $has . '</li>';
				}
				echo '</ul></details>';
			} else {
				echo '<div style="color:#c0392b;margin-top:6px">No price rows from jprm_get_pricegroup_data(). If prices exist, we need that function file.</div>';
			}
		} else {
			echo '<div style="margin-top:6px">Could not sample a first item (no items found in provided $ctx).</div>';
		}

		echo '</div>';
	}
}

/* ---------------- Normalize, pick template, include ---------------- */

$ctx = isset( $ctx ) && is_array( $ctx ) ? $ctx : [];
$ctx = jprm_ctx_normalize( $ctx );
jprm_diag_panel( $ctx ); // visible only with ?jprm_diag=1

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
