<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menu dispatcher
 * - Picks layout per section (override → global)
 * - Computes effective Matrix placeholder & Inline-Below separator (override → global)
 * - Includes the chosen layout template with a per-section context
 *
 * Expected ctx keys:
 *   - menu_term, show_menu_title, show_menu_desc, menu_pos
 *   - sections_order, sections_data
 *   - show_section_name, show_section_desc
 *   - label_presentation, label_position, label_map, currency_opts
 *   - ib_map
 *   - global_labels_layout, section_layouts, section_overrides
 *   - labels_matrix_placeholder, inline_below_separator, global_placeholder
 */

$menu_term        = $ctx['menu_term'] ?? null;
$show_menu_title  = ! empty( $ctx['show_menu_title'] );
$show_menu_desc   = ! empty( $ctx['show_menu_desc'] );
$menu_pos         = $ctx['menu_pos'] ?? 'above_menu';

$sections_order   = is_array( $ctx['sections_order'] ?? null ) ? $ctx['sections_order'] : [];
$sections_data    = is_array( $ctx['sections_data'] ?? null )  ? $ctx['sections_data']  : [];

$show_section_name= ! empty( $ctx['show_section_name'] );
$show_section_desc= ! empty( $ctx['show_section_desc'] );

$label_presentation = (string) ( $ctx['label_presentation'] ?? 'icon_text' );
$label_position     = (string) ( $ctx['label_position'] ?? 'right' );
$label_map          = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts      = is_array( $ctx['currency_opts'] ?? null ) ? $ctx['currency_opts'] : [];

$ib_map             = is_array( $ctx['ib_map'] ?? null ) ? $ctx['ib_map'] : [];

$global_labels_layout     = (string) ( $ctx['global_labels_layout'] ?? 'inline' );
$section_layouts          = is_array( $ctx['section_layouts'] ?? null ) ? $ctx['section_layouts'] : [];
$section_overrides        = is_array( $ctx['section_overrides'] ?? null ) ? $ctx['section_overrides'] : [];

$global_matrix_placeholder = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
$global_inline_separator   = (string) ( $ctx['inline_below_separator'] ?? '' );
$global_placeholder_legacy = (string) ( $ctx['global_placeholder'] ?? '—' );

// Top meta (title/desc above)
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// List wrapper
echo '<ul class="jp-menu">';

foreach ( $sections_order as $tid ) {
	if ( ! isset( $sections_data[ $tid ] ) ) continue;

	$blk   = $sections_data[ $tid ];
	$term  = $blk['term']  ?? null;
	$items = $blk['items'] ?? [];

	// Section header (name/desc)
	if ( $term && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $term->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
		}
		echo '</li>';
	}

	// ABOVE info blocks
	if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Determine effective layout: override → global
	$layout = $global_labels_layout;
	if ( isset( $section_layouts[ $tid ]['layout'] ) && $section_layouts[ $tid ]['layout'] !== '' ) {
		$layout = (string) $section_layouts[ $tid ]['layout'];
	}
	if ( isset( $section_overrides[ $tid ]['layout'] ) && $section_overrides[ $tid ]['layout'] !== '' ) {
		$layout = (string) $section_overrides[ $tid ]['layout'];
	}

	// Effective values per layout (override → global)
	$effective_matrix_placeholder = $global_matrix_placeholder !== '' ? $global_matrix_placeholder : $global_placeholder_legacy;
	if ( isset( $section_layouts[ $tid ]['placeholder'] ) && $section_layouts[ $tid ]['placeholder'] !== '' ) {
		$effective_matrix_placeholder = (string) $section_layouts[ $tid ]['placeholder'];
	}
	if ( isset( $section_overrides[ $tid ]['matrix']['placeholder'] ) && $section_overrides[ $tid ]['matrix']['placeholder'] !== '' ) {
		$effective_matrix_placeholder = (string) $section_overrides[ $tid ]['matrix']['placeholder'];
	}

	$effective_inline_separator = (string) $global_inline_separator;
	if ( isset( $section_layouts[ $tid ]['separator'] ) && $section_layouts[ $tid ]['separator'] !== '' ) {
		$effective_inline_separator = (string) $section_layouts[ $tid ]['separator'];
	}
	if ( isset( $section_overrides[ $tid ]['inline_below']['separator'] ) && $section_overrides[ $tid ]['inline_below']['separator'] !== '' ) {
		$effective_inline_separator = (string) $section_overrides[ $tid ]['inline_below']['separator'];
	}

	// Per-section ctx to pass into the chosen layout
	$sctx = [
		'term'                => $term,
		'items'               => $items,

		'label_presentation'  => $label_presentation,
		'label_position'      => $label_position,
		'label_map'           => $label_map,
		'currency_opts'       => $currency_opts,

		// effective values for this section
		'matrix_placeholder'  => $effective_matrix_placeholder,
		'inline_separator'    => $effective_inline_separator,
	];

	// Include selected layout
	$base = __DIR__;
	switch ( $layout ) {
		case 'matrix':
			$file = $base . '/matrix.php';
			break;
		case 'inline_below':
			$file = $base . '/inline-below.php';
			break;
		case 'inline':
		default:
			$file = $base . '/inline.php';
			break;
	}

	if ( file_exists( $file ) ) {
		// expose $sctx and $ctx (full) to the included layout
		$_section_ctx = $sctx;
		include $file;
		unset( $_section_ctx );
	} else {
		echo '<li class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</li>';
	}

	// BELOW info blocks
	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

echo '</ul>';

// Bottom meta (title/desc below)
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
