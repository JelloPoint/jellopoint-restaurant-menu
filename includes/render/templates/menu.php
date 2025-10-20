<?php
/**
 * Unified Menu Template (1/2/3 columns) + Matrix layout per section
 * Expects $ctx (array). See $ctx keys in widget.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --- Safe helper for menu meta (if not already defined by widget) --- */
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

/** Force each .jp-menu__row to be an inline “chip” (beats theme/Elementor CSS) */
function jprm_force_inline_row_styles( string $html ) : string {
    $chip = 'style="display:inline-flex!important;flex:0 0 auto!important;width:auto!important;max-width:none!important;margin:0!important;padding:0!important;align-items:baseline!important;gap:.35rem!important;"';
    $html = preg_replace('~<div\s+class="([^"]*\bjp-menu__row\b[^"]*)"(?![^>]*\sstyle=)~i','<div class="$1" ' . $chip,$html);
    $html = preg_replace('~<span\s+class="([^"]*\bjp-menu__price\b[^"]*)"(?![^>]*\sstyle=)~i','<span class="$1" style="display:inline-flex!important;align-items:baseline!important;"',$html);
    $html = preg_replace('~<span\s+class="([^"]*\bjp-menu__label\b[^"]*)"(?![^>]*\sstyle=)~i','<span class="$1" style="display:inline-flex!important;align-items:baseline!important;gap:.35rem!important;"',$html);
    return $html;
}

/** Convert a CSS gap to a rough count of thin spaces (1..4) */
function jprm_gap_to_thinsp_count( string $gap ) : int {
    $gap = trim( strtolower( (string) $gap ) );
    if ( $gap === '' ) return 2;
    $val = floatval( $gap );
    if ( preg_match( '~px$~', $gap ) ) $val = $val / 16.0; // px -> rem-ish
    if ( $val <= 0.25 ) return 1;
    if ( $val <= 0.6 )  return 2;
    if ( $val <= 1.0 )  return 3;
    return 4;
}

/** Build a safe text separator (kept for later; not used in SEP test) */
function jprm_build_text_separator( string $sep, string $gap ) : string {
    $sep = (string) $sep;
    if ( $sep === '' ) return '';
    $n = jprm_gap_to_thinsp_count( $gap );
    $pad = str_repeat( '&#8201;', max(1, min(4, $n)) ); // &thinsp;
    return '<span class="jp-chip-sep" aria-hidden="true" style="display:inline-flex;align-items:center;vertical-align:baseline;margin:0 '.esc_attr($gap).';line-height:1;opacity:.75;color:currentColor;font-size:1rem;">'.$pad . esc_html($sep) . $pad.'</span>';
}

/** Insert separators (kept for later; not used in SEP test) */
function jprm_insert_separators( string $html, string $sep_text, string $gap ) : string {
    $sep_text = (string) $sep_text;
    if ( $sep_text === '' ) return $html;
    $sep_node = jprm_build_text_separator( $sep_text, $gap );
    $html = preg_replace('~</div>\s*(?=<div[^>]*\bjp-menu__row\b)~is','</div>'.$sep_node,$html);
    return $html;
}

/* === Read from context === */
$columns             = (string) ( $ctx['columns'] ?? '1' );
$menu_term           = $ctx['menu_term'] ?? null;
$show_menu_title     = ! empty( $ctx['show_menu_title'] );
$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

$sections_order      = $ctx['sections_order'] ?? [];
$sections_data       = $ctx['sections_data'] ?? [];

$show_section_name   = ! empty( $ctx['show_section_name'] );
$show_section_desc   = ! empty( $ctx['show_section_desc'] );

$show_badges         = ! empty( $ctx['show_badges'] );
$badges_presentation = $ctx['badges_presentation'] ?? 'icon_text';
$badges_position     = $ctx['badges_position'] ?? 'after_title';

$label_presentation  = $ctx['label_presentation'] ?? 'icon_text';
$label_position      = $ctx['label_position'] ?? 'right';
$label_map           = $ctx['label_map'] ?? null;
$currency_opts       = $ctx['currency_opts'] ?? [];

$split_mode          = $ctx['split_mode'] ?? 'auto';
$split_after_1       = $ctx['split_after_1'] ?? '';
$split_after_2       = $ctx['split_after_2'] ?? '';

$ib_map              = $ctx['ib_map'] ?? [];
$section_layouts     = $ctx['section_layouts'] ?? [];
$global_labels_layout= $ctx['global_labels_layout'] ?? 'inline';
$global_placeholder  = $ctx['global_placeholder'] ?? '—';

/* Separator options (kept for later switch back) */
$sep_text = (string) ( $ctx['inline_below_sep_content'] ?? '' );
$sep_gap  = (string) ( $ctx['inline_below_sep_gap'] ?? '.6rem' );

/* === Matrix helpers === */
function jprm_section_collect_label_columns( array $items, ?array $label_map, array $currency_opts ) : array {
    $cols = [];
    foreach ( $items as $post ) {
        $pid   = (int) $post->ID;
        $rows  = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
        foreach ( $rows as $r ) {
            $lid = (int) ( $r['label_id'] ?? 0 );
            if ( $lid <= 0 && empty( $r['label_text'] ) ) continue;
            if ( $lid <= 0 ) $lid = crc32( 't:' . (string) $r['label_text'] );
            if ( ! isset( $cols[ $lid ] ) ) {
                $cols[ $lid ] = [
                    'text'      => (string) ( $r['label_text'] ?? '' ),
                    'icon_html' => (string) ( $r['icon_html']  ?? '' ),
                ];
                if ( $label_map && isset( $label_map[$lid] ) && is_array( $label_map[$lid] ) ) {
                    if ( isset( $label_map[$lid]['text'] ) && $label_map[$lid]['text'] !== '' )
                        $cols[$lid]['text'] = (string) $label_map[$lid]['text'];
                    if ( isset( $label_map[$lid]['icon_html'] ) && $label_map[$lid]['icon_html'] !== '' )
                        $cols[$lid]['icon_html'] = (string) $label_map[$lid]['icon_html'];
                }
            }
        }
    }
    return $cols;
}
function jprm_label_header_cell( array $l, string $presentation ) : string {
    $text = trim( (string) ( $l['text'] ?? '' ) );
    $ico  = (string) ( $l['icon_html'] ?? '' );
    if ( $presentation === 'icon' && $ico !== '' ) return '<span class="jp-lhdr-ico">'.$ico.'</span>';
    if ( $presentation === 'text' || $ico === '' ) return '<span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
    return '<span class="jp-lhdr-ico">'.$ico.'</span><span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
}
function jprm_item_value_for_label( int $post_id, int $lid, ?array $label_map, array $currency_opts ) : ?string {
    $rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $post_id, $label_map, $currency_opts ) : [];
    if ( empty( $rows ) ) return null;
    foreach ( $rows as $r ) {
        $rid = (int) ( $r['label_id'] ?? 0 );
        if ( $rid === $lid ) {
            $fmt = (string) ( $r['formatted'] ?? '' );
            return ( $fmt !== '' ) ? $fmt : null;
        }
    }
    return null;
}

/* === Inline item card (SEP test enabled) === */
function jprm_render_item_inline(
    int $post_id, bool $show_badges, string $badges_pos, string $badges_pres,
    string $label_presentation, string $label_position,
    $label_map, array $currency_opts,
    string $sep_text, string $sep_gap
) : void {
    $title = get_the_title( $post_id );
    $desc  = get_post_meta( $post_id, 'jprm_desc', true );

    echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
    echo '  <div class="jp-menu__content">';
    echo '    <div class="jp-menu__titleline">';
    if ( $show_badges && $badges_pos === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $post_id, $badges_pres ); // phpcs:ignore
    if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
    if ( $show_badges && $badges_pos === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $post_id, $badges_pres ); // phpcs:ignore
    echo '    </div>';
    if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
    echo '  </div>';

    if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
        $_html = (string) jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
        $_html = jprm_force_inline_row_styles( $_html );

        /* ====== HARD TEST: literal SEP between chips ====== */
        $_html = preg_replace(
            '~</div>\s*<div\s+([^>]*\bjp-menu__row\b[^>]*)>~i',
            '</div> SEP <div $1>',
            $_html
        );
        $gap_css = '0';
        /* ================================================ */

        echo '<div class="jp-menu__pricegroup" style="display:flex!important;flex-wrap:wrap!important;gap:'.$gap_css.'!important;align-items:baseline!important;align-content:flex-start!important;justify-content:flex-start!important;text-align:left!important;">';
        echo $_html; // phpcs:ignore
        echo '</div>';
    } else {
        echo '<div class="jp-menu__pricegroup"></div>';
    }
    echo '</div></li>';
}

/* === Inline-Below item card (SEP test enabled) === */
function jprm_render_item_inline_below(
    int $post_id,
    bool $show_badges, string $badges_pos, string $badges_pres,
    string $label_presentation, string $label_position,
    $label_map, array $currency_opts,
    string $sep_text, string $sep_gap
) : void {
    $title = get_the_title( $post_id );
    $desc  = get_post_meta( $post_id, 'jprm_desc', true );

    echo '<li class="jp-menu__item"><div class="jp-menu__inner jp--inline-below">';
    echo '  <div class="jp-menu__content">';
    echo '    <div class="jp-menu__titleline">';
    if ( $show_badges && $badges_pos === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $post_id, $badges_pres ); // phpcs:ignore
    if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
    if ( $show_badges && $badges_pos === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $post_id, $badges_pres ); // phpcs:ignore
    echo '    </div>';
    if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
    echo '  </div>';

    if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
        $_html = (string) jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
        $_html = jprm_force_inline_row_styles( $_html );

        /* ====== HARD TEST: literal SEP between chips ====== */
        $_html = preg_replace(
            '~</div>\s*<div\s+([^>]*\bjp-menu__row\b[^>]*)>~i',
            '</div> SEP <div $1>',
            $_html
        );
        $gap_css = '0';
        /* ================================================ */

        echo '<div class="jp-menu__pricegroup" style="display:flex!important;flex-wrap:wrap!important;gap:'.$gap_css.'!important;align-items:baseline!important;align-content:flex-start!important;justify-content:flex-start!important;text-align:left!important;">';
        echo $_html; // phpcs:ignore
        echo '</div>';
    } else {
        echo '<div class="jp-menu__pricegroup"></div>';
    }
    echo '</div></li>';
}

/* === Section renderer (Inline, Inline Below, or Matrix) === */
function jprm_render_section_block( $tid, array $blk, array $opts ) : void {
    $term                = $blk['term'];
    $items               = $blk['items'];
    $show_section_name   = ! empty( $opts['show_section_name'] );
    $show_section_desc   = ! empty( $opts['show_section_desc'] );
    $show_badges         = ! empty( $opts['show_badges'] );
    $badges_presentation = (string) ( $opts['badges_presentation'] ?? 'icon_text' );
    $badges_position     = (string) ( $opts['badges_position'] ?? 'after_title' );
    $label_presentation  = (string) ( $opts['label_presentation'] ?? 'icon_text' );
    $label_position      = (string) ( $opts['label_position'] ?? 'right' );
    $label_map           = $opts['label_map'] ?? null;
    $currency_opts       = $opts['currency_opts'] ?? [];
    $ib_map              = $opts['ib_map'] ?? [];
    $section_layouts     = $opts['section_layouts'] ?? [];
    $global_layout       = (string) ( $opts['global_labels_layout'] ?? 'inline' );
    $global_placeholder  = (string) ( $opts['global_placeholder'] ?? '—' );

    /* Separator opts (text non-empty => show separators) */
    $sep_text = (string) ( $opts['inline_below_sep_content'] ?? '' );
    $sep_gap  = (string) ( $opts['inline_below_sep_gap'] ?? '.6rem' );

    $layout      = $global_layout;
    $placeholder = $global_placeholder;
    if ( isset( $section_layouts[$tid] ) ) {
        $o = $section_layouts[$tid];
        if ( ! empty( $o['layout'] ) ) $layout = (string) $o['layout'];
        if ( isset( $o['placeholder'] ) && $o['placeholder'] !== '' ) $placeholder = (string) $o['placeholder'];
    }

    // ABOVE Info Blocks
    if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
        echo '<li class="jp-menu__infoblock-li">';
        echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
        echo '</li>';
    }

    // Section header
    if ( $term && $show_section_name ) {
        echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
        if ( $show_section_desc && ! empty( $term->description ) ) {
            echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
        }
        echo '</li>';
    }

    // Decide layout per section
    $use_matrix       = ( $layout === 'matrix' );
    $use_inline_below = ( $layout === 'inline_below' );

    if ( $use_matrix ) {
        $cols = jprm_section_collect_label_columns( $items, $label_map, $currency_opts );
        if ( empty( $cols ) ) $use_matrix = false; // fallback if no structured data
    }

    if ( ! $use_matrix ) {
        foreach ( $items as $post ) {
            if ( $use_inline_below ) {
                jprm_render_item_inline_below(
                    (int) $post->ID,
                    $show_badges, $badges_position, $badges_presentation,
                    $label_presentation, $label_position,
                    $label_map, $currency_opts,
                    $sep_text, $sep_gap
                );
            } else {
                jprm_render_item_inline(
                    (int) $post->ID,
                    $show_badges, $badges_position, $badges_presentation,
                    $label_presentation, $label_position,
                    $label_map, $currency_opts,
                    $sep_text, $sep_gap
                );
            }
        }
    } else {
        // Matrix table rendering
        $col_ids = array_keys( $cols );

        echo '<li class="jp-menu__matrix">';
        $__jp_cols = (int) count( $col_ids );
        echo '<div class="jp-matrix" style="--jp-matrix-cols:' . $__jp_cols . ';">';

        echo '<div class="jp-matrix__row jp-matrix__row--header">';
        echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--title"></div>';
        foreach ( $col_ids as $lid ) {
            $l = $cols[$lid];
            echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--label">';
            echo jprm_label_header_cell( $l, $label_presentation ); // phpcs:ignore
            echo '</div>';
        }
        echo '</div>';

        foreach ( $items as $post ) {
            $pid   = (int) $post->ID;
            $title = get_the_title( $pid );
            $desc  = get_post_meta( $pid, 'jprm_desc', true );

            echo '<div class="jp-matrix__row">';

            echo '<div class="jp-matrix__cell jp-matrix__cell--title">';
            echo '<div class="jp-matrix__titleline">';
            if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $pid, $badges_presentation ); // phpcs:ignore
            if ( $title !== '' ) echo '<span class="jp-matrix__title">' . esc_html( $title ) . '</span>';
            if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) echo jprm_render_badges_inline_html( $pid, $badges_presentation ); // phpcs:ignore
            echo '</div>';
            if ( is_string( $desc ) && $desc !== '' ) echo '<div class="jp-matrix__desc">' . esc_html( $desc ) . '</div>';
            echo '</div>';

            foreach ( $col_ids as $lid ) {
                $val = jprm_item_value_for_label( $pid, (int) $lid, $label_map, $currency_opts );
                echo '<div class="jp-matrix__cell jp-matrix__cell--value">';
                echo ( $val !== null && $val !== '' ) ? $val : esc_html( $global_placeholder );
                echo '</div>';
            }

            echo '</div>'; // row
        }

        echo '</div>';
        echo '</li>';
    }

    // BELOW Info Blocks
    if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
        echo '<li class="jp-menu__infoblock-li">';
        echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
        echo '</li>';
    }
}

/* === Column orchestrators (1/2/3) — pass separator options === */

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
    echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* 1 column */
if ( $columns === '1' ) {
    echo '<ul class="jp-menu">';
    if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
        echo '<li class="jp-menu__meta-li">';
        echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
        echo '</li>';
    }
    foreach ( $sections_order as $tid ) {
        $blk = $sections_data[ $tid ];
        jprm_render_section_block( $tid, $blk, [
            'show_section_name'   => $show_section_name,
            'show_section_desc'   => $show_section_desc,
            'show_badges'         => $show_badges,
            'badges_presentation' => $badges_presentation,
            'badges_position'     => $badges_position,
            'label_presentation'  => $label_presentation,
            'label_position'      => $label_position,
            'label_map'           => $label_map,
            'currency_opts'       => $currency_opts,
            'ib_map'              => $ib_map,
            'section_layouts'     => $section_layouts,
            'global_labels_layout'=> $global_labels_layout,
            'global_placeholder'  => $global_placeholder,
            'inline_below_sep_content' => $sep_text,
            'inline_below_sep_gap'     => $sep_gap,
        ] );
    }
    echo '</ul>';
    return;
}

/* 2 columns */
if ( $columns === '2' ) {
    $split_index = null;
    if ( $split_mode === 'manual' && $split_after_1 !== '' ) {
        $target = (int) $split_after_1;
        foreach ( $sections_order as $idx => $tid ) {
            if ( $tid === $target ) { $split_index = $idx; break; }
        }
    }
    if ( $split_index === null ) {
        $total = 0;
        foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }
        $half = (int) ceil( $total / 2 );
        $acc  = 0;
        foreach ( $sections_order as $idx => $tid ) {
            $acc += count( $sections_data[ $tid ]['items'] );
            if ( $acc >= $half ) { $split_index = $idx; break; }
        }
        if ( $split_index === null ) $split_index = count( $sections_order ) - 1;
    }

    $left_sections  = array_slice( $sections_order, 0, $split_index + 1 );
    $right_sections = array_slice( $sections_order, $split_index + 1 );

    echo '<div class="jp-menu-grid jp-cols-2 jp-menu--cols-2 jp-two-cols">';

    echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--left">';
    if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
        echo '<li class="jp-menu__meta-li">';
        echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
        echo '</li>';
    }
    foreach ( $left_sections as $tid ) {
        $blk = $sections_data[ $tid ];
        jprm_render_section_block( $tid, $blk, [
            'show_section_name'   => $show_section_name,
            'show_section_desc'   => $show_section_desc,
            'show_badges'         => $show_badges,
            'badges_presentation' => $badges_presentation,
            'badges_position'     => $badges_position,
            'label_presentation'  => $label_presentation,
            'label_position'      => $label_position,
            'label_map'           => $label_map,
            'currency_opts'       => $currency_opts,
            'ib_map'              => $ib_map,
            'section_layouts'     => $section_layouts,
            'global_labels_layout'=> $global_labels_layout,
            'global_placeholder'  => $global_placeholder,
            'inline_below_sep_content' => $sep_text,
            'inline_below_sep_gap'     => $sep_gap,
        ] );
    }
    echo '</ul></div>';

    echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--right">';
    foreach ( $right_sections as $tid ) {
        $blk = $sections_data[ $tid ];
        jprm_render_section_block( $tid, $blk, [
            'show_section_name'   => $show_section_name,
            'show_section_desc'   => $show_section_desc,
            'show_badges'         => $show_badges,
            'badges_presentation' => $badges_presentation,
            'badges_position'     => $badges_position,
            'label_presentation'  => $label_presentation,
            'label_position'      => $label_position,
            'label_map'           => $label_map,
            'currency_opts'       => $currency_opts,
            'ib_map'              => $ib_map,
            'section_layouts'     => $section_layouts,
            'global_labels_layout'=> $global_labels_layout,
            'global_placeholder'  => $global_placeholder,
            'inline_below_sep_content' => $sep_text,
            'inline_below_sep_gap'     => $sep_gap,
        ] );
    }
    echo '</ul></div>';

    echo '</div>';
    return;
}

/* 3 columns */
$total = 0;
foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }

$col1 = $col2 = $col3 = [];
if ( $split_mode === 'manual' && $split_after_1 !== '' && $split_after_2 !== '' ) {
    $idx1 = $idx2 = null;
    $t1   = (int) $split_after_1;
    $t2   = (int) $split_after_2;
    foreach ( $sections_order as $i => $tid ) {
        if ( $idx1 === null && $tid === $t1 ) $idx1 = $i;
        if ( $idx2 === null && $tid === $t2 ) $idx2 = $i;
        if ( $idx1 !== null && $idx2 !== null ) break;
    }
    if ( $idx1 !== null && $idx2 !== null && $idx2 > $idx1 ) {
        $col1 = array_slice( $sections_order, 0, $idx1 + 1 );
        $col2 = array_slice( $sections_order, $idx1 + 1, $idx2 - $idx1 );
        $col3 = array_slice( $sections_order, $idx2 + 1 );
    }
}
if ( empty( $col1 ) && empty( $col2 ) && empty( $col3 ) ) {
    $t1 = (int) ceil( $total / 3 );
    $t2 = (int) ceil( (2 * $total) / 3 );
    $i1 = null; $i2 = null; $acc = 0;
    foreach ( $sections_order as $idx => $tid ) {
        $acc += count( $sections_data[ $tid ]['items'] );
        if ( $i1 === null && $acc >= $t1 ) $i1 = $idx;
        if ( $i2 === null && $acc >= $t2 ) { $i2 = $idx; break; }
    }
    if ( $i1 === null ) $i1 = 0;
    if ( $i2 === null ) $i2 = max( $i1, count( $sections_order ) - 1 );

    $col1 = array_slice( $sections_order, 0, $i1 + 1 );
    $col2 = array_slice( $sections_order, $i1 + 1, $i2 - $i1 );
    $col3 = array_slice( $sections_order, $i2 + 1 );
}

echo '<div class="jp-menu-grid jp-cols-3 jp-menu--cols-3 jp-three-cols">';

$cols = [ $col1, $col2, $col3 ];
$pos  = [ 'left', 'middle', 'right' ];

foreach ( $cols as $i => $section_ids_chunk ) {
    echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--' . esc_attr( $pos[$i] ) . '">';
    if ( $i === 0 && $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
        echo '<li class="jp-menu__meta-li">';
        echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
        echo '</li>';
    }
    foreach ( $section_ids_chunk as $tid ) {
        $blk = $sections_data[ $tid ];
        jprm_render_section_block( $tid, $blk, [
            'show_section_name'   => $show_section_name,
            'show_section_desc'   => $show_section_desc,
            'show_badges'         => $show_badges,
            'badges_presentation' => $badges_presentation,
            'badges_position'     => $badges_position,
            'label_presentation'  => $label_presentation,
            'label_position'      => $label_position,
            'label_map'           => $label_map,
            'currency_opts'       => $currency_opts,
            'ib_map'              => $ib_map,
            'section_layouts'     => $section_layouts,
            'global_labels_layout'=> $global_labels_layout,
            'global_placeholder'  => $global_placeholder,
            'inline_below_sep_content' => $sep_text,
            'inline_below_sep_gap'     => $sep_gap,
        ] );
    }
    echo '</ul></div>';
}

echo '</div>';
