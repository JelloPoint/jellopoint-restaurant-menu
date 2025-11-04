<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menu dispatcher with multi-column support.
 * - Columns: 1, 2, 3 via layout_columns
 * - Split mode: auto | manual (by top-level section IDs)
 * - Per-section layout override still applied for each section
 * - Passes effective values to selected layout (Matrix placeholder / Inline-Below separator)
 *
 * Enhanced (robust):
 * - Grouping: main (level 0) section renders its children beneath it
 * - Orphan fallback: subsections whose parent isn't in sections_order are promoted to top-level
 * - Rendered-tracking: any section not rendered by grouping is appended at the end (safety net)
 * - Main heading visibility considers "has items" OR "has children" OR "Show even if empty"
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

/** Elementor "Sections and Menus" controls */
$show_main_sections       = ! empty( $ctx['show_main_sections'] ) && $ctx['show_main_sections'] === 'yes';
$show_main_even_if_empty  = ! empty( $ctx['show_main_even_if_empty'] ) && $ctx['show_main_even_if_empty'] === 'yes';

$label_presentation = (string) ( $ctx['label_presentation'] ?? 'icon_text' );
$label_position     = (string) ( $ctx['label_position'] ?? 'right' );
$label_map          = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts      = is_array( $ctx['currency_opts'] ?? null ) ? $ctx['currency_opts'] : [];
$ib_map             = is_array( $ctx['ib_map'] ?? null ) ? $ctx['ib_map'] : [];

$global_labels_layout     = (string) ( $ctx['global_labels_layout'] ?? 'inline' );
$section_layouts          = is_array( $ctx['section_layouts'] ?? null ) ? $ctx['section_layouts'] : [];

$global_matrix_placeholder = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
$global_inline_separator   = (string) ( $ctx['inline_separator'] ?? '' );
$global_placeholder_legacy = (string) ( $ctx['global_placeholder'] ?? '—' );

// === BADGES (read from ctx) ============================================
$show_badges         = ! empty( $ctx['show_badges'] );
$badges_position     = (string) ( $ctx['badges_position'] ?? 'after' );
$badges_presentation = (string) ( $ctx['badges_presentation'] ?? 'icon_text' );

/* -------- grid controls -------- */
$columns                    = max( 1, min( 3, (int) ( $ctx['layout_columns'] ?? 1 ) ) );
$split_mode                 = (string) ( $ctx['layout_split_mode'] ?? 'auto' );            // 'auto' | 'manual'
$split_after_section_id_1   = (int) ( $ctx['layout_split_after_section']  ?? 0 );
$split_after_section_id_2   = (int) ( $ctx['layout_split_after_section2'] ?? 0 );

/* -------- helpers -------- */

// === Inline-Below separator from widget settings (supports both keys) ===
$is_editor = false;
if ( class_exists('\Elementor\Plugin') ) {
	try { $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode(); } catch (\Throwable $e) {}
}

$toggle_on = ! empty( $ctx['inline_below_sep_enable'] ) && $ctx['inline_below_sep_enable'] === 'on';

$global_inline_separator = '';
if ( ! empty( $ctx['inline_below_separator'] ) ) {
	$global_inline_separator = (string) $ctx['inline_below_separator'];
} elseif ( ! empty( $ctx['inline_separator'] ) ) {
	$global_inline_separator = (string) $ctx['inline_separator'];
} elseif ( $is_editor && $toggle_on ) {
	$global_inline_separator = '·';
}

/**
 * Compute the section depth (0 = main). Supports deep hierarchies safely.
 *
 * @param WP_Term|array|null $section_term
 * @return int
 */
$__resolve_section_level = function( $section_term ) : int {
	if ( ! $section_term ) return 0;
	$parent = 0;
	if ( is_object( $section_term ) ) {
		$parent = (int) ( $section_term->parent ?? 0 );
	} elseif ( is_array( $section_term ) ) {
		$parent = (int) ( $section_term['parent'] ?? 0 );
	}
	$level = 0;
	while ( $parent ) {
		$level++;
		$term = get_term( $parent, 'jprm_section' );
		if ( ! $term || is_wp_error( $term ) ) break;
		$parent = (int) $term->parent;
		if ( $level > 6 ) break; // safety
	}
	return $level;
};

/** Get term_id from a $term (object/array). */
$__term_id_of = function( $term ) : int {
	if ( ! $term ) return 0;
	if ( is_object( $term ) ) return (int) ( $term->term_id ?? 0 );
	if ( is_array( $term ) )  return (int) ( $term['term_id'] ?? 0 );
	return 0;
};

/* -------- build children map + top-level order (with orphan fallback) -------- */
$in_order = [];
foreach ( $sections_order as $tid ) {
	$in_order[(int)$tid] = true;
}

$children_map   = [];            // parent_id => [child_ids ordered]
$top_level_order = [];           // [top-level ids ordered]
$all_ids_linear  = [];           // original order (ints), used for leftovers pass

foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	$all_ids_linear[] = $tid;
	if ( empty( $sections_data[ $tid ] ) ) {
		// Keep going; safety net later will skip unknowns.
		continue;
	}

	$term   = $sections_data[ $tid ]['term'] ?? null;
	$parent = 0;
	if ( is_object( $term ) ) {
		$parent = (int) ( $term->parent ?? 0 );
	} elseif ( is_array( $term ) ) {
		$parent = (int) ( $term['parent'] ?? 0 );
	}

	if ( $parent === 0 ) {
		$top_level_order[] = $tid;
	} else {
		// If parent not present in "order", promote this child to top-level (orphan fallback).
		if ( empty( $in_order[ $parent ] ) ) {
			$top_level_order[] = $tid;
		} else {
			if ( ! isset( $children_map[ $parent ] ) ) $children_map[ $parent ] = [];
			$children_map[ $parent ][] = $tid;
		}
	}
}

/** Find index of a term id within order, or -1 if not present */
$__index_of_term = function( array $order, int $term_id ) : int {
	if ( $term_id <= 0 ) return -1;
	foreach ( $order as $i => $tid ) {
		if ( (int)$tid === $term_id ) return (int)$i;
	}
	return -1;
};

/** Split top-level sections into 1/2/3 columns. */
$__split_sections = function( array $order, int $cols, string $mode, int $id1, int $id2 ) use ($__index_of_term) : array {
	$order = array_values( $order );
	$n = count( $order );
	if ( $cols <= 1 || $n === 0 ) return [ $order ];

	if ( $mode === 'manual' ) {
		$idx1 = $__index_of_term( $order, $id1 );
		$idx2 = $__index_of_term( $order, $id2 );

		if ( $cols === 2 ) {
			if ( $idx1 >= 0 ) {
				$cut = $idx1 + 1;
				return [ array_slice( $order, 0, $cut ), array_slice( $order, $cut ) ];
			}
			$cut = (int) ceil( $n / 2 );
			return [ array_slice( $order, 0, $cut ), array_slice( $order, $cut ) ];
		}

		if ( $idx1 >= 0 && $idx2 >= 0 && $idx2 > $idx1 ) {
			$cut1 = $idx1 + 1;
			$cut2 = $idx2 + 1;
			return [
				array_slice( $order, 0, $cut1 ),
				array_slice( $order, $cut1, $cut2 - $cut1 ),
				array_slice( $order, $cut2 ),
			];
		}
		$cut1 = (int) ceil( $n / 3 );
		$cut2 = (int) ceil( 2 * $n / 3 );
		return [
			array_slice( $order, 0, $cut1 ),
			array_slice( $order, $cut1, $cut2 - $cut1 ),
			array_slice( $order, $cut2 ),
		];
	}

	// Auto
	if ( $cols === 2 ) {
		$cut = (int) ceil( $n / 2 );
		return [ array_slice( $order, 0, $cut ), array_slice( $order, $cut ) ];
	}
	$cut1 = (int) ceil( $n / 3 );
	$cut2 = (int) ceil( 2 * $n / 3 );
	return [
		array_slice( $order, 0, $cut1 ),
		array_slice( $order, $cut1, $cut2 - $cut1 ),
		array_slice( $order, $cut2 ),
	];
};

/** Render a single section (and recursively its children) */
$__render_section = function( int $tid, array &$rendered ) use (
	$sections_data, $show_section_name, $show_section_desc, $ib_map,
	$global_labels_layout, $section_layouts,
	$global_matrix_placeholder, $global_inline_separator, $global_placeholder_legacy,
	$label_presentation, $label_position, $label_map, $currency_opts,
	$show_badges, $badges_position, $badges_presentation,
	$show_main_sections, $show_main_even_if_empty, $__resolve_section_level, $__term_id_of, $children_map
) : void {

	if ( empty( $sections_data[ $tid ] ) ) return;
	if ( ! empty( $rendered[ $tid ] ) ) return;
	$rendered[ $tid ] = true;

	$blk    = $sections_data[ $tid ];
	$term   = $blk['term']  ?? null;
	$items  = $blk['items'] ?? [];
	$level  = $__resolve_section_level( $term );

	$has_items    = ! empty( $items );
	$has_children = ! empty( $children_map[ $tid ] );

	// ABOVE info blocks
	if ( ! empty( $ib_map[ $tid ]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[ $tid ]['above'], 'above' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Section header
	if ( $term && $show_section_name ) {
		$classes = 'jp-menu__section-header jp-menu__section--level-' . (int) $level;
		$data_id = ' data-section-id="' . (int) $tid . '"';

		if ( $level === 0 ) {
			// MAIN section → obey widget switches; show if has items or children or explicit "even if empty"
			if ( $show_main_sections && ( $show_main_even_if_empty || $has_items || $has_children ) ) {
				echo '<li class="' . esc_attr( $classes ) . '"' . $data_id . '>';
				echo '<h3 class="jp-section__title">' . esc_html( is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' ) ) . '</h3>';
				if ( $show_section_desc ) {
					$desc = is_object( $term ) ? (string) ( $term->description ?? '' ) : (string) ( $term['description'] ?? '' );
					if ( $desc !== '' ) echo '<div class="jp-section__desc">' . esc_html( $desc ) . '</div>';
				}
				echo '</li>';
			}
		} else {
			// SUB section → always show
			echo '<li class="' . esc_attr( $classes ) . '"' . $data_id . '>';
			echo '<h4 class="jp-section__title">' . esc_html( is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' ) ) . '</h4>';
			if ( $show_section_desc ) {
				$desc = is_object( $term ) ? (string) ( $term->description ?? '' ) : (string) ( $term['description'] ?? '' );
				if ( $desc !== '' ) echo '<div class="jp-section__desc">' . esc_html( $desc ) . '</div>';
			}
			echo '</li>';
		}
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
		// === BADGES
		'show_badges'         => $show_badges ? 'yes' : 'no',
		'badges_position'     => $badges_position,
		'badges_presentation' => $badges_presentation,
		'matrix_placeholder'  => $effective_matrix_placeholder,
		'inline_separator'    => $effective_inline_separator,
		// Useful to layouts
		'section_level'       => $level,
		'section_id'          => $tid,
	];

	// Choose and include layout
	$base = __DIR__;
	switch ( $layout ) {
		case 'matrix':       $file = $base . '/matrix.php';       break;
		case 'inline_below': $file = $base . '/inline-below.php'; break;
		case 'inline':
		default:             $file = $base . '/inline.php';       break;
	}

	// Render items (if any)
	if ( file_exists( $file ) && ! empty( $items ) ) {
		$_section_ctx = $sctx;
		echo "<!-- badges ctx @section tid={$tid} enabled=" . ( ($sctx['show_badges']==='yes') ? '1' : '0' )
		   . " pos={$sctx['badges_position']} pres={$sctx['badges_presentation']} level={$level} -->";
		include $file;
		unset( $_section_ctx );
	} elseif ( ! file_exists( $file ) ) {
		echo '<li class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</li>';
	}

	// Render children directly below this section, in original order
	if ( ! empty( $children_map[ $tid ] ) ) {
		foreach ( $children_map[ $tid ] as $child_tid ) {
			$child_tid = (int) $child_tid;
			$__render_section( $child_tid, $rendered );
		}
	}

	// BELOW info blocks
	if ( ! empty( $ib_map[ $tid ]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[ $tid ]['below'], 'below' ) .'</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
};

/* -------- top meta (above) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* -------- render columns (top-level split only) -------- */
$columns_sets = (function() use ($top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2, $__split_sections) {
	return $__split_sections( $top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2 );
})();

$rendered = []; // tid => true

echo '<div class="jp-menu-grid jp-menu-grid--cols-' . (int)$columns . '" style="--jp-cols:' . (int)$columns . ';">';
foreach ( $columns_sets as $col_idx => $col_top_level_ids ) {
	echo '<ul class="jp-menu jp-menu--col" data-col="' . (int)$col_idx . '">';
	foreach ( $col_top_level_ids as $tid ) {
		$tid = (int) $tid;
		$__render_section( $tid, $rendered ); // renders children and marks all as rendered
	}
	echo '</ul>';
}
echo '</div>';

/* -------- leftover safety net: render any section not rendered yet (in original order) -------- */
$leftovers = [];
foreach ( $all_ids_linear as $tid ) {
	$tid = (int)$tid;
	if ( empty( $sections_data[ $tid ] ) ) continue;
	if ( empty( $rendered[ $tid ] ) ) $leftovers[] = $tid;
}
if ( ! empty( $leftovers ) ) {
	echo '<ul class="jp-menu jp-menu--col jp-menu--leftovers" data-col="leftovers">';
	foreach ( $leftovers as $tid ) {
		$__render_section( (int)$tid, $rendered );
	}
	echo '</ul>';
}

/* -------- bottom meta (below) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}
