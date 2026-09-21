<?php
/** Standalone rendering fixture; no WordPress database or plugin bootstrap. */
define( 'ABSPATH', __DIR__ );
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return esc_attr( $v ); }
function wp_kses_post( $v ) { return $v; }
function wpautop( $v ) { return '<p>' . $v . '</p>'; }
function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
function get_term_meta( ...$args ) { return ''; }
function get_term( $id, $taxonomy = '' ) { return (object) [ 'term_id' => $id, 'parent' => 0, 'name' => 'Parent' ]; }
function is_wp_error( $v ) { return false; }
function get_the_title( $id ) { return 'Dish ' . $id; }
function get_post_meta( $id, $key, $single = false ) { return str_repeat( 'Seasonal vegetables, fresh herbs and house dressing. ', $id % 3 + 1 ); }
function jprm_get_pricegroup_data( $id, ...$args ) { return [ [ 'label_text' => 'Glass', 'formatted' => '8.50', 'label_icon_html' => '', 'label_icon_url' => '' ], [ 'label_text' => 'Bottle', 'formatted' => '29.00', 'label_icon_html' => '', 'label_icon_url' => '' ] ]; }
function jprm_colorize_icon( ...$args ) { return ''; }
function jprm_render_badges_inline_html( ...$args ) { return '<span class="jp-menu__badges">Vegetarian</span>'; }
function jprm_infoblocks_render_group( $blocks, $position ) { return '<div class="jprm-infoblock">Section information ' . esc_html( $position ) . '</div>'; }
function jprm_columns_fixture( array $overrides = [] ) : string {
    $ctx = array_replace( [
        'layout_columns' => 2, 'layout_section_heading_full_width' => true,
        'layout_desktop' => 'inline', 'layout_tablet' => 'inline', 'layout_mobile' => 'inline',
        'show_main_sections' => 'yes', 'show_main_even_if_empty' => 'yes',
        'show_section_name' => true, 'show_section_desc' => true,
        'show_badges' => true, 'label_presentation' => 'text',
        'sections_order' => [ 1, 2, 3 ],
        'sections_data' => [
            1 => [ 'term' => (object) [ 'term_id' => 1, 'parent' => 0, 'name' => 'Starters', 'description' => 'Freshly prepared' ], 'items' => array_map( function( $id ) { return (object) [ 'ID' => $id ]; }, range( 1, 7 ) ) ],
            2 => [ 'term' => (object) [ 'term_id' => 2, 'parent' => 0, 'name' => 'Drinks', 'description' => '' ], 'items' => [] ],
            3 => [ 'term' => (object) [ 'term_id' => 3, 'parent' => 2, 'name' => 'Wines', 'description' => 'By the glass or bottle' ], 'items' => array_map( function( $id ) { return (object) [ 'ID' => $id ]; }, range( 8, 12 ) ) ],
        ],
        'ib_map' => [ 1 => [ 'above' => [ 1 ], 'below' => [ 1 ] ] ],
    ], $overrides );
    ob_start();
    $root = getenv( 'JPRM_RENDER_TEST_ROOT' ) ?: dirname( __DIR__, 2 );
    include $root . '/includes/render/templates/menu.php';
    return ob_get_clean();
}
if ( PHP_SAPI !== 'cli' ) {
    $layout = in_array( $_GET['layout'] ?? '', [ 'inline', 'inline_below', 'matrix' ], true ) ? $_GET['layout'] : 'inline';
    $columns = max( 1, min( 3, (int) ( $_GET['columns'] ?? 2 ) ) );
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Section columns test</title><link rel="stylesheet" href="../../assets/css/menu.css"><style>body{font:16px/1.5 Arial;margin:24px}.jp-menu__section{margin-bottom:24px}</style>';
    echo jprm_columns_fixture( [ 'layout_columns' => $columns, 'layout_desktop' => $layout, 'layout_tablet' => $layout, 'layout_mobile' => $layout, 'layout_section_heading_full_width' => ! isset( $_GET['legacy'] ) ] );
}
