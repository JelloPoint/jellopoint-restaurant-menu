<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Menu dispatcher (strict main heading switches + hierarchical template inheritance).
 *
 * Inheritance rules for per-section template overrides:
 *   Effective values (layout, placeholder, separator) are resolved as:
 *     section override  →  inherited from parent  →  global defaults
 *
 * This means:
 *   - Setting an override on a MAIN section applies to all its subsections,
 *     unless a subsection explicitly sets its own override.
 *   - Setting an override on a subsection applies to that subsection (and its own children),
 *     but not to siblings.
 */

/**
 * Safe HTML renderer for taxonomy descriptions (menu/section):
 * - allows safe tags (wp_kses_post)
 * - turns line breaks into paragraphs (<p>) and <br> (wpautop)
 */
if ( ! function_exists( 'jprm_render_rich_text' ) ) {
	function jprm_render_rich_text( string $text ) : string {
		$text = trim( $text );
		if ( $text === '' ) return '';
		return wpautop( wp_kses_post( $text ) );
	}
}

if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
	function jprm_render_menu_meta( $menu_term, bool $show_title, bool $show_desc, string $scope = 'global' ) : string {
		if ( ! $menu_term ) return '';
		$out   = '';
		$title = is_object( $menu_term ) ? (string) ( $menu_term->name ?? '' ) : (string) ( $menu_term['name'] ?? '' );
		$desc  = is_object( $menu_term ) ? (string) ( $menu_term->description ?? '' ) : (string) ( $menu_term['description'] ?? '' );

		if ( $show_title || ( $show_desc && $desc !== '' ) ) {
			$out .= '<li class="jp-menu__meta jp-menu__meta--' . esc_attr( $scope ) . '">';
			if ( $show_title && $title !== '' ) {
				$out .= '<h2 class="jp-menu__title">' . esc_html( $title ) . '</h2>';
			}
			if ( $show_desc && $desc !== '' ) {
				$out .= '<div class="jp-menu__desc">' . jprm_render_rich_text( $desc ) . '</div>';
			}
			$out .= '</li>';
		}

		return $out;
	}
}

/* ---------------- unpack ctx (STRICT for main headings) ---------------- */
$menu_term         = $ctx['menu_term'] ?? null;
$show_menu_title   = ! empty( $ctx['show_menu_title'] );
$show_menu_desc    = ! empty( $ctx['show_menu_desc'] );
$menu_pos          = $ctx['menu_pos'] ?? 'above_menu';

$sections_order    = is_array( $ctx['sections_order'] ?? null ) ? array_values( $ctx['sections_order'] ) : [];
$sections_data     = is_array( $ctx['sections_data'] ?? null ) ? $ctx['sections_data'] : [];

$show_main_sections      = (string) ( $ctx['show_main_sections']      ?? 'no' ); // 'yes'|'no'
$show_main_even_if_empty = (string) ( $ctx['show_main_even_if_empty'] ?? 'no' ); // 'yes'|'no'

$show_section_name = ! empty( $ctx['show_section_name'] ); // used for subsections only
$show_section_desc = ! empty( $ctx['show_section_desc'] );

$label_presentation = (string) ( $ctx['label_presentation'] ?? 'icon_text' );
$label_position     = (string) ( $ctx['label_position']     ?? 'right' );
$label_map          = is_array( $ctx['label_map']     ?? null ) ? $ctx['label_map']     : [];
$currency_opts      = is_array( $ctx['currency_opts'] ?? null ) ? $ctx['currency_opts'] : [];
$ib_map             = is_array( $ctx['ib_map']        ?? null ) ? $ctx['ib_map']        : [];

/* Global desktop layout (base for inheritance) */
$layout_desktop  = (string) ( $ctx['layout_desktop']  ?? 'inline' );
$layout_tablet   = (string) ( $ctx['layout_tablet']   ?? $layout_desktop );
$layout_mobile   = (string) ( $ctx['layout_mobile']   ?? $layout_tablet );
$layout_strategy = (string) ( $ctx['layout_strategy'] ?? 'force_global' );

$global_labels_layout = $layout_desktop; // base for desktop inheritance

$section_layouts = is_array( $ctx['section_layouts'] ?? null ) ? $ctx['section_layouts'] : [];

$global_matrix_placeholder = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
$global_inline_separator   = (string) ( $ctx['inline_separator']          ?? '' );
$global_placeholder_legacy = (string) ( $ctx['global_placeholder']        ?? '—' );

$show_badges         = ! empty( $ctx['show_badges'] );
$badges_position     = (string) ( $ctx['badges_position']     ?? 'after' );
$badges_presentation = (string) ( $ctx['badges_presentation'] ?? 'icon_text' );

/* === Inline leader (from widget ctx) === */
$inline_leader_enable = ( ! empty( $ctx['inline_leader_enable'] ) && $ctx['inline_leader_enable'] === 'yes' ) ? 'yes' : 'no';
$inline_leader_char   = (string) ( $ctx['inline_leader_char']   ?? '' );
$inline_leader_style  = (string) ( $ctx['inline_leader_style']  ?? 'dotted' ); // 'dotted'|'dashed'|'solid'

$columns                  = max( 1, min( 3, (int) ( $ctx['layout_columns'] ?? 1 ) ) );
$split_mode               = (string) ( $ctx['layout_split_mode']         ?? 'auto' );
$split_after_section_id_1 = (int) ( $ctx['layout_split_after_section']   ?? 0 );
$split_after_section_id_2 = (int) ( $ctx['layout_split_after_section2']  ?? 0 );

/* ---------------- helpers ---------------- */
$is_editor = false;
if ( class_exists( '\Elementor\Plugin' ) ) {
	try {
		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
	} catch ( \Throwable $e ) {}
}

$toggle_on = ! empty( $ctx['inline_below_sep_enable'] ) && $ctx['inline_below_sep_enable'] === 'on';
$computed_global_inline_separator = '';
if ( ! empty( $ctx['inline_below_separator'] ) ) {
	$computed_global_inline_separator = (string) $ctx['inline_below_separator'];
} elseif ( ! empty( $ctx['inline_separator'] ) ) {
	$computed_global_inline_separator = (string) $ctx['inline_separator'];
} elseif ( $is_editor && $toggle_on ) {
	$computed_global_inline_separator = '·';
}

$__resolve_section_level = function( $section_term ) : int {
	if ( ! $section_term ) return 0;
	$parent = is_object( $section_term ) ? (int) ( $section_term->parent ?? 0 ) : (int) ( $section_term['parent'] ?? 0 );
	$level  = 0;
	while ( $parent ) {
		$level++;
		$term = get_term( $parent, 'jprm_section' );
		if ( ! $term || is_wp_error( $term ) ) {
			break;
		}
		$parent = (int) $term->parent;
		if ( $level > 8 ) {
			break;
		}
	}
	return $level;
};

$__parent_id_of = function( $term ) : int {
	if ( ! $term ) return 0;
	return is_object( $term ) ? (int) ( $term->parent ?? 0 ) : (int) ( $term['parent'] ?? 0 );
};

/* ---------- build registry + children (preserve order; synthesize ancestors) ---------- */
$registry        = []; // tid => ['term'=>WP_Term|array,'items'=>array]
$children_map    = []; // pid => [child tids]
$top_level_seen  = [];
$top_level_order = [];

// 1) seed registry with known sections; stub unknowns
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( isset( $sections_data[ $tid ] ) ) {
		$registry[ $tid ] = $sections_data[ $tid ];
	} else {
		$t = get_term( $tid, 'jprm_section' );
		if ( $t && ! is_wp_error( $t ) ) {
			$registry[ $tid ] = [
				'term'  => $t,
				'items' => [],
			];
		}
	}
}

// 2) ensure all ancestors are present (stubs)
foreach ( array_keys( $registry ) as $tid ) {
	$term = $registry[ $tid ]['term'] ?? null;
	$pid  = $__parent_id_of( $term );
	while ( $pid ) {
		if ( ! isset( $registry[ $pid ] ) ) {
			$pt = get_term( $pid, 'jprm_section' );
			if ( $pt && ! is_wp_error( $pt ) ) {
				$registry[ $pid ] = [
					'term'  => $pt,
					'items' => [],
				];
			} else {
				break;
			}
		}
		$pid = $__parent_id_of( $registry[ $pid ]['term'] ?? null );
	}
}

// 3) children in order of sections_order
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( empty( $registry[ $tid ] ) ) continue;
	$term = $registry[ $tid ]['term'] ?? null;
	$pid  = $__parent_id_of( $term );
	if ( $pid ) {
		if ( ! isset( $children_map[ $pid ] ) ) {
			$children_map[ $pid ] = [];
		}
		$children_map[ $pid ][] = $tid;
	}
}

// 4) compute roots in encountered order
foreach ( $sections_order as $tid ) {
	$tid = (int) $tid;
	if ( empty( $registry[ $tid ] ) ) continue;
	$node = $registry[ $tid ]['term'] ?? null;
	$root = $tid;
	$pid  = $__parent_id_of( $node );
	while ( $pid && isset( $registry[ $pid ] ) ) {
		$root = $pid;
		$pid  = $__parent_id_of( $registry[ $pid ]['term'] ?? null );
	}
	if ( ! isset( $top_level_seen[ $root ] ) ) {
		$top_level_seen[ $root ] = true;
		$top_level_order[]       = $root;
	}
}

/* ---------------- column split (roots only) ---------------- */
$__index_of_term = function( array $order, int $term_id ) : int {
	foreach ( $order as $i => $tid ) {
		if ( (int) $tid === $term_id ) return $i;
	}
	return -1;
};

$__split_sections = function( array $order, int $cols, string $mode, int $id1, int $id2 ) use ( $__index_of_term ) : array {
	$order = array_values( $order );
	$n     = count( $order );
	if ( $cols <= 1 || $n === 0 ) {
		return [ $order ];
	}

	if ( $mode === 'manual' ) {
		$idx1 = $__index_of_term( $order, $id1 );
		$idx2 = $__index_of_term( $order, $id2 );

		if ( $cols === 2 ) {
			if ( $idx1 >= 0 ) {
				$cut = $idx1 + 1;
				return [
					array_slice( $order, 0, $cut ),
					array_slice( $order, $cut ),
				];
			}
			$cut = (int) ceil( $n / 2 );
			return [
				array_slice( $order, 0, $cut ),
				array_slice( $order, $cut ),
			];
		}

		if ( $idx1 >= 0 && $idx2 >= 0 && $idx2 > $idx1 ) {
			$c1 = $idx1 + 1;
			$c2 = $idx2 + 1;
			return [
				array_slice( $order, 0, $c1 ),
				array_slice( $order, $c1, $c2 - $c1 ),
				array_slice( $order, $c2 ),
			];
		}

		$c1 = (int) ceil( $n / 3 );
		$c2 = (int) ceil( 2 * $n / 3 );
		return [
			array_slice( $order, 0, $c1 ),
			array_slice( $order, $c1, $c2 - $c1 ),
			array_slice( $order, $c2 ),
		];
	}

	if ( $cols === 2 ) {
		$cut = (int) ceil( $n / 2 );
		return [
			array_slice( $order, 0, $cut ),
			array_slice( $order, $cut ),
		];
	}

	$c1 = (int) ceil( $n / 3 );
	$c2 = (int) ceil( 2 * $n / 3 );
	return [
		array_slice( $order, 0, $c1 ),
		array_slice( $order, $c1, $c2 - $c1 ),
		array_slice( $order, $c2 ),
	];
};

/* ---------------- renderer (recursive with inheritance) ---------------- */
$__render_section = null;

$__render_section = function( int $tid, ?array $inherit = null ) use (
	&$__render_section,
	$registry, $children_map,
	$show_section_name, $show_section_desc,
	$global_labels_layout, $section_layouts,
	$global_matrix_placeholder, $computed_global_inline_separator, $global_placeholder_legacy,
	$label_presentation, $label_position, $label_map, $currency_opts,
	$show_badges, $badges_position, $badges_presentation,
	$show_main_sections, $show_main_even_if_empty,
	$inline_leader_enable, $inline_leader_char, $inline_leader_style,
	$__resolve_section_level, $ib_map,
	$layout_desktop, $layout_tablet, $layout_mobile, $layout_strategy
) : void {

	if ( empty( $registry[ $tid ] ) ) {
		return;
	}

	$term  = $registry[ $tid ]['term']  ?? null;
	$items = $registry[ $tid ]['items'] ?? [];
	$level = $__resolve_section_level( $term );

	$has_items    = ! empty( $items );
	$has_children = ! empty( $children_map[ $tid ] );

	// ------- Resolve effective layout/values with inheritance (DESKTOP) -------
	$sec             = $section_layouts[ $tid ] ?? [];
	$own_layout      = isset( $sec['layout'] ) && $sec['layout'] !== '' ? (string) $sec['layout'] : null;
	$own_placeholder = array_key_exists( 'placeholder', $sec ) && $sec['placeholder'] !== '' ? (string) $sec['placeholder'] : null;
	$own_separator   = array_key_exists( 'separator', $sec )   && $sec['separator']   !== '' ? (string) $sec['separator']   : null;

	// Desktop effective layout (this is where per-section overrides apply)
	$eff_layout      = $own_layout      ?? ( $inherit['layout']      ?? $layout_desktop );
	$eff_placeholder = $own_placeholder ?? ( $inherit['placeholder'] ?? ( $global_matrix_placeholder !== '' ? $global_matrix_placeholder : $global_placeholder_legacy ) );
	$eff_separator   = $own_separator   ?? ( $inherit['separator']   ?? $computed_global_inline_separator );

	// Bundle to pass to children (DESKTOP inheritance)
	$child_inherit = [
		'layout'      => $eff_layout,
		'placeholder' => $eff_placeholder,
		'separator'   => $eff_separator,
	];

	// ------- ABOVE info blocks ------- //
	if ( ! empty( $ib_map[ $tid ]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">' . jprm_infoblocks_render_group( $ib_map[ $tid ]['above'], 'above' ) . '</li>';
	}

	// ------- Section header HTML (now lives INSIDE the box) ------- //
	$header_html = '';

	if ( $term ) {
		$title = is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' );
		$desc  = is_object( $term ) ? (string) ( $term->description ?? '' ) : (string) ( $term['description'] ?? '' );

		if ( $level === 0 ) {
			// Main section visibility rules (strict)
			$allow_main = ( $show_main_sections === 'yes' )
				&& ( $show_main_even_if_empty === 'yes' || $has_items || $has_children );

			if ( $allow_main ) {
				$header_html .= '<div class="jp-menu__section-header jp-menu__section--level-0">';
				if ( $title !== '' ) {
					$header_html .= '<h3 class="jp-section__title">' . esc_html( $title ) . '</h3>';
				}
				if ( $show_section_desc && $desc !== '' ) {
					$header_html .= '<div class="jp-section__desc">' . jprm_render_rich_text( $desc ) . '</div>';
				}
				$header_html .= '</div>';
			}
		} else {
			// Sub sections use H4 + toggle by show_section_name
			if ( $show_section_name ) {
				$header_html .= '<div class="jp-menu__section-header jp-menu__section--level-' . (int) $level . '">';
				if ( $title !== '' ) {
					$header_html .= '<h4 class="jp-section__title">' . esc_html( $title ) . '</h4>';
				}
				if ( $show_section_desc && $desc !== '' ) {
					$header_html .= '<div class="jp-section__desc">' . jprm_render_rich_text( $desc ) . '</div>';
				}
				$header_html .= '</div>';
			}
		}
	}

	// Decide whether we render a section "box":
	// - if there is a header, or
	// - if there are items
	$render_box = ( $header_html !== '' ) || $has_items;

	if ( $render_box ) {
		$wrapper_classes = [
			'jp-menu__section',
			'jp-menu__section-box',
			'jp-menu__section--level-' . (int) $level,
		];
		$wrapper_class_attr = esc_attr( implode( ' ', $wrapper_classes ) );
		$data_id_attr       = ' data-section-id="' . (int) $tid . '"';

		echo '<li class="' . $wrapper_class_attr . '"' . $data_id_attr . '>';

		// Header (if any)
		echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// ------- Include layout if there are items ------- //
		$base = __DIR__;

		if ( ! empty( $items ) ) {

			// --- Decide layouts per device for THIS section ---
			if ( $layout_strategy === 'force_global' ) {
				// Desktop: per-section; Tablet/Mobile: forced global layouts
				$section_desktop_layout = $eff_layout;
				$section_tablet_layout  = $layout_tablet;
				$section_mobile_layout  = $layout_mobile;
			} else {
				// respect_overrides: same layout on all devices, based on desktop effective layout
				$section_desktop_layout = $eff_layout;
				$section_tablet_layout  = $eff_layout;
				$section_mobile_layout  = $eff_layout;
			}

			$ld = $section_desktop_layout;
			$lt = $section_tablet_layout;
			$lm = $section_mobile_layout;

			// If all 3 are identical, render ONCE (no grey overlays).
			if ( $ld === $lt && $ld === $lm ) {
				$layout_to_use = $ld;

				switch ( $layout_to_use ) {
					case 'matrix':
						$file = $base . '/matrix.php';
						break;
					case 'inline_below':
						$file = $base . '/inline-below.php';
						break;
					case 'inline':
					default:
						$file = $base . '/inline.php';
				}

				if ( file_exists( $file ) ) {
					$_section_ctx = [
						'term'                 => $term,
						'items'                => $items,
						'label_presentation'   => $label_presentation,
						'label_position'       => $label_position,
						'label_map'            => $label_map,
						'currency_opts'        => $currency_opts,
						// badges
						'show_badges'          => $show_badges ? 'yes' : 'no',
						'badges_position'      => $badges_position,
						'badges_presentation'  => $badges_presentation,
						// effective per-layout extras
						'matrix_placeholder'   => $eff_placeholder,
						'inline_separator'     => $eff_separator,
						// inline leader (only used by inline.php)
						'inline_leader_enable' => $inline_leader_enable,
						'inline_leader_char'   => $inline_leader_char,
						'inline_leader_style'  => $inline_leader_style,
						// extras
						'section_level'        => $level,
						'section_id'           => $tid,
					];
					include $file;
					unset( $_section_ctx );
				} else {
					echo '<div class="jp-menu__error">Missing layout template: ' . esc_html( basename( $file ) ) . '</div>';
				}

			} else {
				// === Device-specific variants ===
				$variants = [];

				// Desktop only variant
				$variants[] = [
					'layout' => $ld,
					'class'  => 'elementor-hidden-tablet elementor-hidden-mobile',
				];

				// Tablet + mobile combined if equal and different from desktop
				if ( $lt === $lm && $lt !== $ld ) {
					$variants[] = [
						'layout' => $lt,
						'class'  => 'elementor-hidden-desktop', // visible tablet + mobile
					];
				} else {
					// Tablet-only (if different from desktop)
					if ( $lt !== $ld ) {
						$variants[] = [
							'layout' => $lt,
							'class'  => 'elementor-hidden-desktop elementor-hidden-mobile',
						];
					}
					// Mobile-only (if different from desktop)
					if ( $lm !== $ld ) {
						$variants[] = [
							'layout' => $lm,
							'class'  => 'elementor-hidden-desktop elementor-hidden-tablet',
						];
					}
				}

				// Render each variant block.
				foreach ( $variants as $variant ) {
					$layout = $variant['layout'];
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
					}
					if ( ! file_exists( $file ) ) {
						continue;
					}

					$extra_class  = trim( (string) $variant['class'] );
					$layout_class = 'jprm-layout-variant jprm-layout-' . $layout;
					if ( $extra_class !== '' ) {
						$layout_class .= ' ' . $extra_class;
					}

					echo '<div class="' . esc_attr( $layout_class ) . '">';

					$_section_ctx = [
						'term'                 => $term,
						'items'                => $items,
						'label_presentation'   => $label_presentation,
						'label_position'       => $label_position,
						'label_map'            => $label_map,
						'currency_opts'        => $currency_opts,
						// badges
						'show_badges'          => $show_badges ? 'yes' : 'no',
						'badges_position'      => $badges_position,
						'badges_presentation'  => $badges_presentation,
						// effective per-layout extras
						'matrix_placeholder'   => $eff_placeholder,
						'inline_separator'     => $eff_separator,
						// inline leader (only used by inline.php)
						'inline_leader_enable' => $inline_leader_enable,
						'inline_leader_char'   => $inline_leader_char,
						'inline_leader_style'  => $inline_leader_style,
						// extras
						'section_level'        => $level,
						'section_id'           => $tid,
					];
					include $file;
					unset( $_section_ctx );

					echo '</div>';
				}
			}
		}

		echo '</li>';
	}

	// ------- Render children (inherit desktop effective layout) ------- //
	if ( ! empty( $children_map[ $tid ] ) ) {
		foreach ( $children_map[ $tid ] as $child_tid ) {
			$__render_section( (int) $child_tid, $child_inherit );
		}
	}

	// ------- BELOW info blocks ------- //
	if ( ! empty( $ib_map[ $tid ]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">' . jprm_infoblocks_render_group( $ib_map[ $tid ]['below'], 'below' ) . '</li>';
	}
};

/* -------------- top meta (above) -------------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* -------------- render columns (roots only) -------------- */
$columns_sets = ( function() use ( $top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2, $__split_sections ) {
	return $__split_sections( $top_level_order, $columns, $split_mode, $split_after_section_id_1, $split_after_section_id_2 );
} )();

echo '<div class="jp-menu-grid jp-menu-grid--cols-' . (int) $columns . '" style="--jp-cols:' . (int) $columns . ';">';
foreach ( $columns_sets as $col_idx => $roots ) {
	echo '<ul class="jp-menu jp-menu--col" data-col="' . (int) $col_idx . '">';
	foreach ( $roots as $root_tid ) {
		$__render_section( (int) $root_tid, null ); // root: no inheritance yet
	}
	echo '</ul>';
}
echo '</div>';

/* -------------- bottom meta (below) -------------- */
if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'below_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}
