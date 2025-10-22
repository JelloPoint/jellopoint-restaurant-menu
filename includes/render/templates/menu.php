<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dispatcher: renders the menu, section by section.
 * For each section, it resolves the effective layout and layout-specific values:
 *   - Matrix: placeholder
 *   - Inline Below: separator
 * Then it includes the corresponding layout template, passing a compact per-section context.
 *
 * Expects in $ctx (already built by the widget):
 *   - menu_term, show_menu_title, show_menu_desc, menu_pos
 *   - sections_order (array of term IDs), sections_data[tid] = ['term'=>WP_Term,'items'=>WP_Post[]]
 *   - show_section_name, show_section_desc
 *   - label_presentation, label_position, label_map, currency_opts
 *   - ib_map[tid]['above'|'below'] (optional)
 *   - global_labels_layout ('inline'|'inline_below'|'matrix')
 *   - section_layouts[tid] = ['layout'=>..., 'placeholder'=>..., 'separator'=>...]
 *   - labels_matrix_placeholder (global Matrix placeholder)
 *   - inline_below_separator (global Inline Below separator)
 *   - global_placeholder (legacy, used if labels_matrix_placeholder is empty)
 */

/* --- Local fallback to avoid fatal if helper isn't loaded elsewhere --- */
if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
	function jprm_render_menu_meta( $menu_term, bool $show_title, bool $show_desc, string $scope = 'global' ) : string {
		if ( ! $menu_term ) return '';
		$out = '';
		$title = is_object( $menu_term ) && isset( $menu_term->name ) ? (string) $menu_term->name : '';
		$desc  = is_object( $menu_term ) && isset( $menu_term->description ) ? (string) $menu_term->description : '';
		if ( $show_title || ( $show_desc && $desc !== '' ) ) {
			$out .= '<li class="jp-menu__meta jp-menu__meta--' . esc_attr( $scope ) . '">';
			if ( $show_title && $title !== '' ) $out .= '<h2 class="jp-menu__title">' . esc_html( $title ) . '</h2>';
			if ( $show_desc && $desc  !== '' ) $out .= '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			$out .= '</li>';
		}
		return $out;
	}
}

/* -------- unpack ctx -------- */
$menu_term         = $ctx['menu_term'] ?? null;
$show_menu_title   = ! empty( $ctx['show_menu_title'] );
$show_menu_desc    = ! empty( $ctx['show_menu_desc'] );
$menu_pos          = $ctx['menu_pos'] ?? 'above_menu';

$sections_order    = is_array( $ctx['sections_order'] ?? null ) ? $ctx['sections_order'] : [];
$sections_data     = is_array( $ctx['sections_data'] ?? null )  ? $ctx['sections_data']  : [];

$show_section_name = ! empty( $ctx['show_section_name'] );
$show_section_desc = ! empty( $ctx['show_section_desc'] );

$label_presentation = (string) ( $ctx['label_presentation'] ?? 'icon_text' );
$label_position     = (string) ( $ctx['label_position'] ?? 'right' );
$label_map          = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts      = is_array( $ctx['currency_opts'] ?? null ) ? $ctx['currency_opts'] : [];

$ib_map             = is_array( $ctx['ib_map'] ?? null ) ? $ctx['ib_map'] : [];

$global_labels_layout     = (string) ( $ctx['global_labels_layout'] ?? 'inline' );
$section_layouts          = is_array( $ctx['section_layouts'] ?? null ) ? $ctx['section_layouts'] : [];

$global_matrix_placeholder = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
$global_inline_separator   = (string) ( $ctx['inline_below_separator'] ?? '' );
$global_placeholder_legacy = (string) ( $ctx['global_placeholder'] ?? '—' );

/* -------- top meta (above) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/* -------- list wrapper -------- */
echo '<ul class="jp-menu">';

/* -------- sections -------- */
foreach ( $sections_order as $tid ) {
	if ( empty( $sections_data[ $tid ] ) ) continue;

	$blk   = $sections_data[ $tid ];
	$term  = $blk['term']  ?? null;
	$items = $blk['items'] ?? [];

	/* section header (name/desc) */
	if ( $term && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $term->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
		}
		echo '</li>';
	}

	/* ABOVE info blocks */
	if ( ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/* effective layout for this section: per-section override → global */
	$layout = $global_labels_layout;
	if ( ! empty( $section_layouts[ $tid ]['layout'] ) ) {
		$layout = (string) $section_layouts[ $tid ]['layout'];
	}

	/* effective values (override → global) */
	$effective_matrix_placeholder = $global_matrix_placeholder !== '' ? $global_matrix_placeholder : $global_placeholder_legacy;
	if ( isset( $section_layouts[ $tid ]['placeholder'] ) && $section_layouts[ $tid ]['placeholder'] !== '' ) {
		$effective_matrix_placeholder = (string) $section_layouts[ $tid ]['placeholder'];
	}
	$effective_inline_separator = (string) $global_inline_separator;
	if ( isset( $section_layouts[ $tid ]['separator'] ) && $section_layouts[ $tid ]['separator'] !== '' ) {
		$effective_inline_separator = (string) $section_layouts[ $tid ]['separator'];
	}

	/* per-section ctx for chosen layout */
	$sctx = [
		'term'               => $term,
		'items'              => $items,
		'label_presentation' => $label_presentation,
		'label_position'     => $label_position,
		'label_map'          => $label_map,
		'currency_opts'      => $currency_opts,
		'matrix_placeholder' => $effective_matrix_placeholder,
		'inline_separator'   => $effective_inline_separator,
	];

	/* include selected layout */
	$base = __DIR__;
	switch ( $layout ) {
		case 'matrix':       $file = $base . '/matrix.php'; break;
		case 'inline_below': $file = $base . '/inline-below.php'; break;
		case 'inline':
		default:             $file = $base . '/inline.php'; break;
	}

	if ( file_exists( $file ) ) {
		$_section_ctx = $sctx;
		include $file;
		unset( $_section_ctx );
	} else {
		echo '<li class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</li>';
	}

	/* BELOW info blocks */
	if ( ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

echo '</ul>';

/* -------- bottom meta (below) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
