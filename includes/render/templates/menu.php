<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menu dispatcher with multi-column support (ID-based manual split).
 * - Columns: 1, 2, 3 via layout_columns
 * - Split mode: auto | manual (by section IDs)
 * - Per-section layout override still applied for each section
 * - Passes effective values to selected layout (Matrix placeholder / Inline-Below separator)
 */

if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
	function jprm_render_menu_meta( $menu_term, bool $show_title, bool $show_desc, string $scope = 'global' ) : string {
		if ( ! $menu_term ) return '';
		$out = '';
		$title = is_object( $menu_term ) && isset( $menu_term->name ) ? (string) $menu_term->name : '';
		$desc  = is_object( $menu_term ) && isset( $menu_term->description ) ? (string) $menu_term->description : '';
		if ( $show_title || ( $show_desc && $desc !== '' ) ) {
			$out .= '<li class="jp-menu__meta jp-menu__meta--' . esc_attr( $scope ) . '">';
			if ( $show_title && $title !== '' ) $out .= '<h2 class="jp-menu__title">' . esc_html( $title ) . '</h2>';
			if ( $show_desc  && $desc  !== '' ) $out .= '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
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

/* -------- read your existing control names -------- */
$columns                    = max( 1, min( 3, (int) ( $ctx['layout_columns'] ?? 1 ) ) );
$split_mode                 = (string) ( $ctx['layout_split_mode'] ?? 'auto' );            // 'auto' | 'manual'
$split_after_section_id_1   = (int) ( $ctx['layout_split_after_section']  ?? 0 );          // term_id (or 0)
$split_after_section_id_2   = (int) ( $ctx['layout_split_after_section2'] ?? 0 );          // term_id (or 0)

/* -------- helpers -------- */

/** Render a single section by term-id */
$__render_section = function( int $tid ) use (
	$sections_data, $show_section_name, $show_section_desc, $ib_map,
	$global_labels_layout, $section_layouts,
	$global_matrix_placeholder, $global_inline_separator, $global_placeholder_legacy,
	$label_presentation, $label_position, $label_map, $currency_opts
) : void {

	if ( empty( $sections_data[ $tid ] ) ) return;

	$blk   = $sections_data[ $tid ];
	$term  = $blk['term']  ?? null;
	$items = $blk['items'] ?? [];

	// Section header
	if ( $term && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $term->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
		}
		echo '</li>';
	}

	// ABOVE info blocks
	if ( ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Effective layout (per-section override → global)
	$layout = $global_labels_layout;
	if ( ! empty( $section_layouts[ $tid ]['layout'] ) ) {
		$layout = (string) $section_layouts[ $tid ]['layout'];
	}

	// Effective layout-specific values (override → global)
	$effective_matrix_placeholder = $global_matrix_placeholder !== '' ? $global_matrix_placeholder : $global_placeholder_legacy;
	if ( isset( $section_layouts[ $tid ]['placeholder'] ) && $section_layouts[ $tid ]['placeholder'] !== '' ) {
		$effective_matrix_placeholder = (string) $section_layouts[ $tid ]['placeholder'];
	}
	$effective_inline_separator = (string) $global_inline_separator;
	if ( isset( $section_layouts[ $tid ]['separator'] ) && $section_layouts[ $tid ]['separator'] !== '' ) {
		$effective_inline_separator = (string) $section_layouts[ $tid ]['separator'];
	}

	// Per-section ctx to pass to layout
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

	// Choose and include layout
	$base = __DIR__;
	switch ( $layout ) {
		case 'matrix':       $file = $base . '/matrix.php';       break;
		case 'inline_below': $file = $base . '/inline-below.php'; break;
		case 'inline':
		default:             $file = $base . '/inline.php';       break;
	}
	if ( file_exists( $file ) ) {
		$_section_ctx = $sctx;
		include $file;
		unset( $_section_ctx );
	} else {
		echo '<li class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</li>';
	}

	// BELOW info blocks
	if ( ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
};

/** Find index of a term id within order, or -1 if not present */
$__index_of_term = function( array $order, int $term_id ) : int {
	if ( $term_id <= 0 ) return -1;
	foreach ( $order as $i => $tid ) {
		if ( (int)$tid === $term_id ) return (int)$i;
	}
	return -1;
};

/** Split sections into 1/2/3 columns.
 * Auto: even by count.
 * Manual: split after the provided term IDs (if not found → fallback auto).
 */
$__split_sections = function( array $order, int $cols, string $mode, int $id1, int $id2 ) use ($__index_of_term) : array {
	$order = array_values( $order );
	$n = count( $order );
	if ( $cols <= 1 || $n === 0 ) return [ $order ];

	if ( $mode === 'manual' ) {
		$idx1 = $__index_of_term( $order, $id1 ); // index of split point 1
		$idx2 = $__index_of_term( $order, $id2 ); // index of split point 2 (for 3 cols)

		if ( $cols === 2 ) {
			if ( $idx1 >= 0 ) {
				$cut = $idx1 + 1; // after the chosen section
				return [
					array_slice( $order, 0, $cut ),
					array_slice( $order, $cut ),
				];
			}
			// fallback to auto if not found
			$cut = (int) ceil( $n / 2 );
			return [ array_slice( $order, 0, $cut ), array_slice( $order, $cut ) ];
		} else {
			// 3 columns
			if ( $idx1 >= 0 && $idx2 >= 0 && $idx2 > $idx1 ) {
				$cut1 = $idx1 + 1;
				$cut2 = $idx2 + 1;
				return [
					array_slice( $order, 0, $cut1 ),
					array_slice( $order, $cut1, $cut2 - $cut1 ),
					array_slice( $order, $cut2 ),
				];
			}
			// partial or invalid manual → fallback auto
			$cut1 = (int) ceil( $n / 3 );
			$cut2 = (int) ceil( 2 * $n / 3 );
			return [
				array_slice( $order, 0, $cut1 ),
				array_slice( $order, $cut1, $cut2 - $cut1 ),
				array_slice( $order, $cut2 ),
			];
		}
	}

	// Auto: split evenly by count
	if ( $cols === 2 ) {
		$cut = (int) ceil( $n / 2 );
		return [ array_slice( $order, 0, $cut ), array_slice( $order, $cut ) ];
	} else {
		$cut1 = (int) ceil( $n / 3 );
		$cut2 = (int) ceil( 2 * $n / 3 );
		return [
			array_slice( $order, 0, $cut1 ),
			array_slice( $order, $cut1, $cut2 - $cut1 ),
			array_slice( $order, $cut2 ),
		];
	}
};

/* -------- top meta (above) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* -------- render columns -------- */
$columns_sets = $__split_sections(
	$sections_order,
	$columns,
	$split_mode,
	$split_after_section_id_1,
	$split_after_section_id_2
);

echo '<div class="jp-menu-grid jp-menu-grid--cols-' . (int)$columns . '" style="--jp-cols:' . (int)$columns . ';">';
foreach ( $columns_sets as $col_idx => $col_section_ids ) {
	echo '<ul class="jp-menu jp-menu--col" data-col="' . (int)$col_idx . '">';
	foreach ( $col_section_ids as $tid ) {
		$__render_section( (int)$tid );
	}
	echo '</ul>';
}
echo '</div>';

/* -------- bottom meta (below) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}
