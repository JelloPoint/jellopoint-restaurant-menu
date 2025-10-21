<?php
/**
 * Menu dispatcher – routes to inline / inline-below / matrix templates
 * Expects $ctx (array) to be provided by the widget render().
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Safe helper used by templates (kept here to share across includes)
 */
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
 * Normalize and decide template
 */
$layout = isset( $ctx['global_labels_layout'] ) ? (string) $ctx['global_labels_layout'] : 'inline';
$layout = in_array( $layout, [ 'inline', 'inline_below', 'matrix' ], true ) ? $layout : 'inline';

$base_dir = __DIR__;

/**
 * Include the chosen template
 */
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
	// Hard fail safe – empty list to avoid breaking the page
	echo '<ul class="jp-menu"></ul>';
}