<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix (per-section)
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_map','currency_opts','matrix_placeholder'
 *   'show_badges','badges_position','badges_presentation'
 *   // Extras from menu.php:
 *   'section_level' => int (0 = main, 1+ = sub),
 *   'section_id'    => int (term_id)
 * ]
 */

$sctx  = ( isset( $_section_ctx ) && is_array( $_section_ctx ) ) ? $_section_ctx : [];
$items = is_array( $sctx['items'] ?? null ) ? $sctx['items'] : [];

$label_presentation = (string) ( $sctx['label_presentation'] ?? 'icon_text' );
$label_map          = is_array( $sctx['label_map'] ?? null ) ? $sctx['label_map'] : [];
$currency_opts      = is_array( $sctx['currency_opts'] ?? null ) ? $sctx['currency_opts'] : [];
$matrix_placeholder = (string) ( $sctx['matrix_placeholder'] ?? '' );

// BADGES
$badges_enabled      = ( (string) ( $sctx['show_badges'] ?? 'yes' ) === 'yes' );
$badges_position     = (string) ( $sctx['badges_position']     ?? 'after' );
$badges_presentation = (string) ( $sctx['badges_presentation'] ?? 'icon_text' );

// Level / ID (kept for possible styling hooks; markup now handled by menu.php)
$section_level = (int) ( $sctx['section_level'] ?? 0 );
$section_id    = (int) ( $sctx['section_id']    ?? 0 );

if ( empty( $items ) ) {
    return;
}

/* helpers (guarded) */
if ( ! function_exists( 'jprm_sanitize_single_icon' ) ) {
    function jprm_sanitize_single_icon( string $html ) : string {
        $html = trim( $html );
        if ( $html === '' ) return '';
        if ( preg_match( '~<img\b[^>]*>~is', $html, $m ) ) return $m[0];
        if ( preg_match( '~<svg\b[^>]*>.*?</svg>~is', $html, $m ) ) return $m[0];
        return '';
    }
}

/**
 * Header cell renderer. For unlabeled "position" columns, prints a NBSP.
 */
if ( ! function_exists( 'jprm_matrix_header_cell' ) ) {
    function jprm_matrix_header_cell( array $meta, string $presentation ) : string {
        $text = isset( $meta['text'] ) ? trim( (string) $meta['text'] ) : '';
        $ico  = '';

        // Icon pick + colorize
        if ( ! empty( $meta['icon_html'] ) ) {
            $ico = jprm_sanitize_single_icon( (string) $meta['icon_html'] );
        }
        $ico = jprm_colorize_icon(
            $ico,
            ! empty( $meta['icon_url'] ) ? (string) $meta['icon_url'] : null,
            'label'
        );

        // If truly no label (unlabeled position column), show non-breaking space
        if ( $text === '' && $ico === '' ) {
            return '<span class="jp-menu__label">&nbsp;</span>';
        }

        // Always wrap so style control hits both text & icon
        if ( $presentation === 'icon' ) {
            $content = ( $ico !== '' ? $ico : esc_html( $text ) );
            return '<span class="jp-menu__label">' . $content . '</span>';
        }
        if ( $presentation === 'text' ) {
            return '<span class="jp-menu__label"><span class="jp-badge__label">' . esc_html( $text ) . '</span></span>';
        }
        // icon_text
        if ( $ico !== '' && $text !== '' ) {
            return '<span class="jp-menu__label">' . $ico . '<span class="jp-badge__label">' . esc_html( $text ) . '</span></span>';
        }
        return '<span class="jp-menu__label">' . ( $ico !== '' ? $ico : esc_html( $text ) ) . '</span>';
    }
}

/**
 * Collect columns from:
 *  - Known labels in $label_map
 *  - Labels discovered in items (label_id or label_text)
 *  - PLUS a single "unlabeled" column when at least one unlabeled row exists
 * Returns ['cols'=>map, 'order'=>keys in order].
 */
if ( ! function_exists( 'jprm_matrix_collect_columns' ) ) {
    function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
        $cols = [];

        // Seed with configured labels
        foreach ( $label_map as $lid => $meta ) {
            $cols[ (string) $lid ] = [
                'text'      => (string) ( $meta['title'] ?? ( $meta['text'] ?? '' ) ),
                'icon_html' => (string) ( $meta['icon_html'] ?? '' ),
                'icon_url'  => (string) ( $meta['icon_url']  ?? '' ),
                '_seed'     => true,
            ];
        }

        $has_unlabeled = false;

        // Discover from content
        foreach ( $items as $post ) {
            $pid  = (int) $post->ID;
            $rows = function_exists( 'jprm_get_pricegroup_data' )
                ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
                : [];

            foreach ( $rows as $r ) {
                $lid = isset( $r['label_id'] )   ? (int) $r['label_id']   : 0;
                $txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';

                if ( $lid > 0 || $txt !== '' ) {
                    // Labeled row → build key from id OR text
                    $key = $lid > 0 ? (string) $lid : 't:' . md5( $txt );
                    if ( ! isset( $cols[ $key ] ) ) {
                        $cols[ $key ] = [
                            'text'      => $txt,
                            'icon_html' => (string) ( $r['icon_html'] ?? '' ),
                            'icon_url'  => (string) ( $r['icon_url']  ?? '' ),
                        ];
                    }
                } else {
                    // Unlabeled row → we only care that at least one exists
                    $has_unlabeled = true;
                }
            }
        }

        // Add a single unlabeled column when needed
        if ( $has_unlabeled ) {
            $key = 'p:0';
            if ( ! isset( $cols[ $key ] ) ) {
                $cols[ $key ] = [
                    'text'      => '',
                    'icon_html' => '',
                    'icon_url'  => '',
                    '_pos'      => 0,
                ];
            }
        }

        // Preserve insertion order (labels first as seeded, then discovered, then unlabeled)
        $order = array_keys( $cols );

        return [
            'cols'  => $cols,
            'order' => $order,
        ];
    }
}

/**
 * Find the formatted value for a given column key.
 *  - labeled columns: match by label_id or label_text
 *  - unlabeled column (p:0): take the FIRST unlabeled row's formatted price
 */
if ( ! function_exists( 'jprm_matrix_find_cell' ) ) {
    function jprm_matrix_find_cell( array $rows, string $col_key ) : ?string {
        // Unlabeled column?
        if ( strpos( $col_key, 'p:' ) === 0 ) {
            foreach ( $rows as $r ) {
                $lid = isset( $r['label_id'] )   ? (int) $r['label_id']   : 0;
                $txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';
                if ( $lid <= 0 && $txt === '' ) {
                    $fmt = (string) ( $r['formatted'] ?? '' );
                    return $fmt !== '' ? $fmt : null;
                }
            }
            return null;
        }

        // Labeled: match by id or text-hash
        foreach ( $rows as $r ) {
            $lid = isset( $r['label_id'] )   ? (int) $r['label_id']   : 0;
            $txt = isset( $r['label_text'] ) ? (string) $r['label_text'] : '';
            $key = $lid > 0 ? (string) $lid : ( $txt !== '' ? 't:' . md5( $txt ) : '' );
            if ( $key !== '' && $key === $col_key ) {
                $fmt = (string) ( $r['formatted'] ?? '' );
                return $fmt !== '' ? $fmt : null;
            }
        }
        return null;
    }
}

/**
 * Filter to active columns: keep any column that has at least one value
 * across all items. For unlabeled column, we also test that at least one
 * unlabeled row has a value.
 */
if ( ! function_exists( 'jprm_matrix_filter_active_columns' ) ) {
    function jprm_matrix_filter_active_columns( array $items, array $col_keys, array $label_map, array $currency_opts ) : array {
        $active = [];
        foreach ( $col_keys as $k ) {
            foreach ( $items as $post ) {
                $pid  = (int) $post->ID;
                $rows = function_exists( 'jprm_get_pricegroup_data' )
                    ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
                    : [];
                if ( jprm_matrix_find_cell( $rows, $k ) !== null ) {
                    $active[] = $k;
                    break;
                }
            }
        }
        return $active;
    }
}

/* grid build */
$collect   = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
$cols      = $collect['cols'];
$col_keys  = jprm_matrix_filter_active_columns( $items, $collect['order'], $label_map, $currency_opts );
$col_count = max( 1, count( $col_keys ) );

/**
 * New structure:
 * menu.php already created:
 *   <li class="jp-menu__section jp-menu__section-box ...">
 *     [header div here]
 *     [we now add this body wrapper:]
 *       <div class="jp-matrix" style="--jp-matrix-cols:N">...</div>
 *   </li>
 */
echo '<div class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

/* header row: first cell blank (item title column) */
echo '<div class="jp-matrix__row jp-matrix__row--header">';
    echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item"></div>';
    foreach ( $col_keys as $k ) {
        $meta = [
            'text'      => isset( $cols[ $k ]['text'] ) ? (string) $cols[ $k ]['text'] : '',
            'icon_html' => (string) ( $cols[ $k ]['icon_html'] ?? '' ),
            'icon_url'  => (string) ( $cols[ $k ]['icon_url']  ?? '' ),
        ];
        echo '<div class="jp-matrix__cell jp-matrix__cell--head" data-label-key="' . esc_attr( $k ) . '">'
            . jprm_matrix_header_cell( $meta, $label_presentation )
            . '</div>';
    }
echo '</div>';

/* rows */
foreach ( $items as $post ) {
    $pid   = (int) $post->ID;
    $title = get_the_title( $pid );
    $desc  = get_post_meta( $pid, 'jprm_desc', true );
    $rows  = function_exists( 'jprm_get_pricegroup_data' )
        ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
        : [];

    echo '<div class="jp-matrix__row" data-post-id="' . esc_attr( (string) $pid ) . '">';

        // First cell: item title + badges + desc
        echo '<div class="jp-matrix__cell jp-matrix__cell--item">';

            // BADGES: pre-render per item
            $badges_html = '';
            if ( $badges_enabled && function_exists( 'jprm_render_badges_inline_html' ) ) {
                $badges_html = jprm_render_badges_inline_html( $pid, $badges_presentation );
            }

            if ( $title !== '' ) {
                echo '<div class="jp-menu__titlewrap">';
                    if ( $badges_position === 'before' && $badges_html !== '' ) {
                        echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    echo '<span class="jp-menu__title">' . esc_html( $title ) . '</span>';
                    if ( $badges_position !== 'before' && $badges_html !== '' ) {
                        echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                echo '</div>';
            }

            if ( is_string( $desc ) && $desc !== '' ) {
                echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            }

        echo '</div>';

        // Value cells
        foreach ( $col_keys as $k ) {
            $val = $rows ? jprm_matrix_find_cell( $rows, $k ) : null;
            if ( $val === null || $val === '' ) {
                $val = $matrix_placeholder !== ''
                    ? '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>'
                    : '';
            }
            echo '<div class="jp-matrix__cell jp-matrix__cell--value" data-label-key="' . esc_attr( $k ) . '">'
                . $val
                . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

    echo '</div>';
}

echo '</div>'; // .jp-matrix
