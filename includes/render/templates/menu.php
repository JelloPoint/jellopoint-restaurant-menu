<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menu dispatcher with multi-column support.
 * - Columns: 1, 2, 3 via layout_columns
 * - Split mode: auto | manual (by top-level section IDs)
 * - Groups subsections under their taxonomy parent (ancestors synthesized if needed)
 * - Uses ONLY the two controls passed in $ctx:
 *     - show_main_sections: 'yes'|'no'
 *     - show_main_even_if_empty: 'yes'|'no'
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

/* -------- unpack ctx (STRICT) -------- */
$menu_term         = $ctx['menu_term'] ?? null;
$show_menu_title   = ! empty( $ctx['show_menu_title'] );
$show_menu_desc    = ! empty( $ctx['show_menu_desc'] );
$menu_pos          = $ctx['menu_pos'] ?? 'above_menu';

$sections_order    = is_array( $ctx['sections_order'] ?? null ) ? array_values($ctx['sections_order']) : [];
$sections_data     = is_array( $ctx['sections_data'] ?? null )  ? $ctx['sections_data']  : [];

/** STRICT: only read these two keys (strings 'yes'|'no'). Do not infer from other settings. */
$show_main_sections      = (string)($ctx['show_main_sections']      ?? 'no'); // 'yes'|'no'
$show_main_even_if_empty = (string)($ctx['show_main_even_if_empty'] ?? 'no'); // 'yes'|'no'

$show_section_name = ! empty( $ctx['show_section_name'] ); // used for subsections ONLY
$show_section_desc = ! empty( $ctx['show_section_desc'] );

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

// === BADGES ==============================================================
$show_badges         = ! empty( $ctx['show_badges'] );
$badges_position     = (string) ( $ctx['badges_position'] ?? 'after' );
$badges_presentation = (string) ( $ctx['badges_presentation'] ?? 'icon_text' );

/* -------- grid controls -------- */
$columns                    = max( 1, min( 3, (int) ( $ctx['layout_columns'] ?? 1 ) ) );
$split_mode                 = (string) ( $ctx['layout_split_mode'] ?? 'auto' );
$split_after_section_id_1   = (int) ( $ctx['layout_split_after_section']  ?? 0 );
$split_after_section_id_2   = (int) ( $ctx['layout_split_after_section2'] ?? 0 );

/* -------- helpers -------- */
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

/** Resolve section depth (0 = main) */
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
		if ( $level > 8 ) break;
	}
	return $level;
};

/** Safe getters */
$__term_id_of = function( $term ) : int {
	if ( ! $term ) return 0;
	if ( is_object( $term ) ) return (int) ( $term->term_id ?? 0 );
	if ( is_array( $term ) )  return (int) ( $term['term_id'] ?? 0 );
	return 0;
};
$__parent_id_of = function( $term ) : int {
	if ( ! $term ) return 0;
	if ( is_object( $term ) ) return (int) ( $term->parent ?? 0 );
	if ( is_array( $term ) )  return (int) ( $term['parent'] ?? 0 );
	return 0;
};

/* --- Build registry + children map (preserve order; synthesize ancestors) --- */
$registry       = []; // tid => ['term'=>WP_Term|'array','items'=>array]
$children_map   = []; // pid => [child tids]
$top_level_seen = [];
$top_level_order = [];

// 1) seed registry
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( isset( $sections_data[ $tid ] ) ) {
		$registry[ $tid ] = $sections_data[ $tid ];
	} else {
		$term = get_term( $tid, 'jprm_section' );
		if ( $term && ! is_wp_error( $term ) ) {
			$registry[ $tid ] = [ 'term' => $term, 'items' => [] ];
		}
	}
}
// 2) ensure ancestors exist
foreach ( array_keys( $registry ) as $tid ) {
	$term = $registry[ $tid ]['term'] ?? null;
	$pid  = $__parent_id_of( $term );
	while ( $pid ) {
		if ( ! isset( $registry[ $pid ] ) ) {
			$parent_term = get_term( $pid, 'jprm_section' );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$registry[ $pid ] = [ 'term' => $parent_term, 'items' => [] ];
			} else { break; }
		}
		$pid = $__parent_id_of( $registry[ $pid ]['term'] ?? null );
	}
}
// 3) children map
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( empty( $registry[ $tid ] ) ) continue;
	$term = $registry[ $tid ]['term'] ?? null;
	$pid  = $__parent_id_of( $term );
	if ( $pid ) {
		if ( ! isset( $children_map[ $pid ] ) ) $children_map[ $pid ] = [];
		$children_map[ $pid ][] = $tid;
	}
}
// 4) top-level roots in encountered order
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( empty( $registry[ $tid ] ) ) continue;
	$node_term = $registry[ $tid ]['term'] ?? null;
	$root_tid  = $tid;
	$parent_id = $__parent_id_of( $node_term );
	while ( $parent_id && isset( $registry[ $parent_id ] ) ) {
		$root_tid = $parent_id;
		$parent_id = $__parent_id_of( $registry[ $parent_id ]['term'] ?? null );
	}
	if ( ! isset( $top_level_seen[ $root_tid ] ) ) {
		$top_level_seen[ $root_tid ] = true;
		$top_level_order[] = $root_tid;
	}
}

/* --- Column splitting (top-level only) --- */
$__index_of_term = function( array $order, int $term_id ) : int {
	if ( $term_id <= 0 ) return -1;
	foreach ( $order as $i => $tid ) { if ( (int)$tid === $term_id ) return (int)$i; }
	return -1;
};
$__split_sections = function( array $order, int $cols, string $mode, int $id1, int $id2 ) use ($__index_of_term) : array {
	$order = array_values( $order );
	$n = count( $order );
	if ( $cols <= 1 || $n === 0 ) return [ $order ];
	if ( $mode === 'manual' ) {
		$idx1 = $__index_of_term( $order, $id1 );
		$idx2 = $__index_of_term( $order, $id2 );
		if ( $cols === 2 ) {
			if ( $idx1 >= 0 ) { $cut = $idx1 + 1; return [ array_slice($order,0,$cut), array_slice($order,$cut) ]; }
			$cut = (int)ceil($n/2); return [ array_slice($order,0,$cut), array_slice($order,$cut) ];
		}
		if ( $idx1 >= 0 && $idx2 >= 0 && $idx2 > $idx1 ) {
			$cut1 = $idx1 + 1; $cut2 = $idx2 + 1;
			return [ array_slice($order,0,$cut1), array_slice($order,$cut1,$cut2-$cut1), array_slice($order,$cut2) ];
		}
		$cut1 = (int)ceil($n/3); $cut2 = (int)ceil(2*$n/3);
		return [ array_slice($order,0,$cut1), array_slice($order,$cut1,$cut2-$cut1), array_slice($order,$cut2) ];
	}
	// auto
	if ( $cols === 2 ) { $cut = (int)ceil($n/2); return [ array_slice($order,0,$cut), array_slice($order,$cut) ]; }
	$cut1 = (int)ceil($n/3); $cut2 = (int)ceil(2*$n/3);
	return [ array_slice($order,0,$cut1), array_slice($order,$cut1,$cut2-$cut1), array_slice($order,$cut2) ];
};

/* --- Renderer (recursive) — STRICT on main heading rules --- */
$__render_section = null;
$__render_section = function( int $tid ) use (
	&$__render_section,
	$registry, $children_map,
	$show_section_name, $show_section_desc,
	$global_labels_layout, $section_layouts,
	$global_matrix_placeholder, $global_inline_separator, $global_placeholder_legacy,
	$label_presentation, $label_position, $label_map, $currency_opts,
	$show_badges, $badges_position, $badges_presentation,
	$show_main_sections, $show_main_even_if_empty, $__resolve_section_level, $ib_map
) : void {

	if ( empty( $registry[ $tid ] ) ) return;

	$term   = $registry[ $tid ]['term']  ?? null;
	$items  = $registry[ $tid ]['items'] ?? [];
	$level  = $__resolve_section_level( $term );

	$has_items    = ! empty( $items );
	$has_children = ! empty( $children_map[ $tid ] );

	// ABOVE info blocks
	if ( ! empty( $ib_map[ $tid ]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[ $tid ]['above'], 'above' ) .'</li>'; // phpcs:ignore
	}

	// Section header
	if ( $term ) {
		$classes = 'jp-menu__section-header jp-menu__section--level-' . (int) $level;
		$data_id = ' data-section-id="' . (int) $tid . '"';

		if ( $level === 0 ) {
			// STRICT: only the two switches decide
			if ( $show_main_sections === 'yes' && ( $show_main_even_if_empty === 'yes' || $has_items || $has_children ) ) {
				echo '<li class="' . esc_attr( $classes ) . '"' . $data_id . '>';
				echo '<h3 class="jp-section__title">' . esc_html( is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' ) ) . '</h3>';
				if ( $show_section_desc ) {
					$desc = is_object( $term ) ? (string) ( $term->description ?? '' ) : (string) ( $term['description'] ?? '' );
					if ( $desc !== '' ) echo '<div class="jp-section__desc">' . esc_html( $desc ) . '</div>';
				}
				echo '</li>';
			}
		} else {
			// subsections obey the general show_section_name flag
			if ( $show_section_name ) {
				echo '<li class="' . esc_attr( $classes ) . '"' . $data_id . '>';
				echo '<h4 class="jp-section__title">' . esc_html( is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' ) ) . '</h4>';
				if ( $show_section_desc ) {
					$desc = is_object( $term ) ? (string) ( $term->description ?? '' ) : (string) ( $term['description'] ?? '' );
					if ( $desc !== '' ) echo '<div class="jp-section__desc">' . esc_html( $desc ) . '</div>';
				}
				echo '</li>';
			}
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

	// Template ctx (per section)
	$sctx = [
		'term'               => $term,
		'items'              => $items,
		'label_presentation' => $label_presentation,
		'label_position'     => $label_position,
		'label_map'          => $label_map,
		'currency_opts'      => $currency_opts,
		// BADGES
		'show_badges'         => $show_badges ? 'yes' : 'no',
		'badges_position'     => $badges_position,
		'badges_presentation' => $badges_presentation,
		'matrix_placeholder'  => $effective_matrix_placeholder,
		'inline_separator'    => $effective_inline_separator,
		// Useful to layouts
		'section_level'       => $level,
		'section_id'          => $tid,
	];

	// Include layout if there are items
	$base = __DIR__;
	switch ( $layout ) {
		case 'matrix':       $file = $base . '/matrix.php';       break;
		case 'inline_below': $file = $base . '/inline-below.php'; break;
		case 'inline':
		default:             $file = $base . '/inline.php';       break;
	}
	if ( file_exists( $file ) && ! empty( $items ) ) {
		$_section_ctx = $sctx;
		include $file;
		unset( $_section_ctx );
	} elseif ( ! file_exists( $file ) ) {
		echo '<li class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</li>';
	}

	// Children directly after parent
	if ( ! empty( $children_map[ $tid ] ) ) {
		foreach ( $children_map[ $tid ] as $child_tid ) {
			$__render_section( (int)$child_tid );
		}
	}

	// BELOW info blocks
	if ( ! empty( $ib_map[ $tid ]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[ $tid ]['below'], 'below' ) .'</li>'; // phpcs:ignore
	}
};

/* -------- top meta (above) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* -------- columns: split by ROOTS (top-level) only -------- */
$columns_sets = (function() use ($top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2, $__split_sections) {
	return $__split_sections( $top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2 );
})();

echo '<div class="jp-menu-grid jp-menu-grid--cols-' . (int)$columns . '" style="--jp-cols:' . (int)$columns . ';">';
foreach ( $columns_sets as $col_idx => $roots ) {
	echo '<ul class="jp-menu jp-menu--col" data-col="' . (int)$col_idx . '">';
	foreach ( $roots as $root_tid ) {
		$__render_section( (int)$root_tid );
	}
	echo '</ul>';
}
echo '</div>';

/* -------- bottom meta (below) -------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}
