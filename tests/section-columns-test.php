<?php
require __DIR__ . '/fixtures/section-columns.php';
set_error_handler( function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function columns_check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
foreach ( [ 'inline', 'inline_below', 'matrix' ] as $layout ) {
    foreach ( [ 1, 2, 3 ] as $count ) {
        $html = jprm_columns_fixture( [ 'layout_columns' => $count, 'layout_desktop' => $layout, 'layout_tablet' => $layout, 'layout_mobile' => $layout ] );
        columns_check( substr_count( $html, 'class="jp-menu jp-menu--col"' ) === 1, 'New mode must not distribute sections.' );
        columns_check( substr_count( $html, 'class="jp-menu__section-items"' ) === 2, 'Each populated section needs its own item columns.' );
        columns_check( substr_count( $html, '>Starters</h3>' ) === 1 && substr_count( $html, '>Wines</h4>' ) === 1, 'Section hierarchy lost.' );
        for ( $id = 1; $id <= 12; ++$id ) { columns_check( substr_count( $html, '>Dish ' . $id . '</span>' ) === 1, 'Item missing or duplicated.' ); }
        columns_check( substr_count( $html, 'Section information' ) === 2, 'Info Blocks lost.' );
    }
}
$old = jprm_columns_fixture( [ 'layout_section_heading_full_width' => false, 'layout_columns' => 2, 'layout_split_mode' => 'manual', 'layout_split_after_section' => 1 ] );
columns_check( strpos( $old, 'jp-menu__section-items' ) === false, 'Legacy markup changed.' );
columns_check( substr_count( $old, 'class="jp-menu jp-menu--col"' ) === 2, 'Legacy columns lost.' );
$hidden = jprm_columns_fixture( [ 'show_main_sections' => 'no', 'show_section_name' => false ] );
columns_check( strpos( $hidden, '<h3' ) === false && strpos( $hidden, '<h4' ) === false, 'New layout must respect heading visibility.' );
$variants = jprm_columns_fixture( [ 'layout_desktop' => 'matrix', 'layout_tablet' => 'inline_below', 'layout_mobile' => 'inline' ] );
columns_check( substr_count( $variants, ' jp-menu__section-items ' ) === 6, 'Each device variant must own its column formatting context.' );
columns_check( strpos( $variants, 'class="jp-menu__section-items"' ) === false, 'Do not balance around responsive wrappers.' );
columns_check( substr_count( $variants, 'jprm-layout-variant' ) === 6, 'Device layout variants missing.' );
$legacy_variants = jprm_columns_fixture( [ 'layout_section_heading_full_width' => false, 'layout_desktop' => 'matrix', 'layout_tablet' => 'inline_below', 'layout_mobile' => 'inline' ] );
columns_check( strpos( $legacy_variants, 'jp-menu__section-items' ) === false, 'Legacy device variants must not gain item columns.' );
$daily = jprm_columns_fixture( [ 'daily_menu' => [ 'enabled' => true, 'item_separator' => 'or' ] ] );
if ( ! getenv( 'JPRM_RENDER_TEST_ROOT' ) || strpos( getenv( 'JPRM_RENDER_TEST_ROOT' ), '-premium' ) !== false ) {
    columns_check( strpos( $daily, '>8.50<' ) === false, 'Daily menus must keep item prices hidden.' );
    columns_check( substr_count( $daily, 'class="jp-menu__item-separator"' ) === 10, 'Daily separators missing.' );
}
$root = dirname( __DIR__ );
$widget_source = file_get_contents( $root . '/includes/widgets/class-restaurant-menu.php' );
columns_check( strpos( $widget_source, "'layout_section_heading_full_width' => \$this->get_settings( 'layout_section_heading_full_width' ) === 'yes'" ) !== false, 'Saved switch must not depend on Elementor active-control filtering.' );
// The supplied Elementor export stores the switch, but omits default columns.
$export_settings = [ 'layout_section_heading_full_width' => 'yes' ];
$default_columns_html = jprm_columns_fixture( [
    'layout_columns' => $export_settings['layout_columns'] ?? '2',
    'layout_section_heading_full_width' => ( $export_settings['layout_section_heading_full_width'] ?? '' ) === 'yes',
] );
columns_check( strpos( $default_columns_html, 'jp-menu-grid--cols-2 jp-menu-grid--section-columns' ) !== false, 'Default two columns must retain the explicitly saved switch.' );
$controls = file_get_contents( $root . '/includes/widgets/traits/restaurant-menu-controls.php' );
columns_check( strpos( $controls, "'section_source'" ) < strpos( $controls, "'jprm_section_layout'" ) && strpos( $controls, "'jprm_section_layout'" ) < strpos( $controls, "'jprm_section_sections_menus'" ), 'Layout control order incorrect.' );
columns_check( substr_count( $controls, "'jprm_section_layout'" ) === 1, 'Layout registered twice.' );
columns_check( preg_match( "/add_control\\( 'layout_section_heading_full_width', \\[.*?'default' => '',.*?'condition' => \\[ 'layout_columns' => \\[([^\\]]+)\\] \\]/s", $controls, $condition_match ) === 1, 'New setting must default off and require multiple columns.' );
// Read the actual registered condition values, preserving numeric/string types.
preg_match_all( "/'([^']*)'|(\\d+)/", $condition_match[1], $condition_values, PREG_SET_ORDER );
$accepted_columns = array_map( function( $match ) { return isset( $match[2] ) ? (int) $match[2] : $match[1]; }, $condition_values );
foreach ( [ '2', 2, '3', 3 ] as $saved_columns ) {
    // Match Elementor's strict frontend condition comparison.
    columns_check( in_array( $saved_columns, $accepted_columns, true ), 'Frontend must retain the switch for ' . var_export( $saved_columns, true ) );
    $html = jprm_columns_fixture( [ 'layout_columns' => $saved_columns ] );
    columns_check( strpos( $html, 'jp-menu-grid--section-columns' ) !== false, 'Enabled layout missing.' );
}
foreach ( [ '1', 1, '', null, false, 0, '4', 4 ] as $saved_columns ) {
    columns_check( ! in_array( $saved_columns, $accepted_columns, true ), 'Switch must remain hidden for unsupported column values.' );
}
columns_check( substr_count( $controls, "'layout_section_heading_full_width!' => 'yes'" ) === 3, 'All manual section splitting controls must hide in new mode.' );
echo "Section column rendering, hierarchy, item order, layouts, Info Blocks and legacy mode passed.\n";

// Both switches are independent in every layout and device variant. Exercise
// the same templates against source and locally generated Free/Pro editions.
foreach ( [ 'inline', 'inline_below', 'matrix', 'mixed' ] as $layout ) {
    foreach ( [ false, true ] as $title ) {
        foreach ( [ false, true ] as $description ) {
            foreach ( [ false, true ] as $badges ) {
                $options = [ 'show_item_title' => $title, 'show_item_description' => $description, 'show_badges' => $badges, 'inline_leader_enable' => 'yes' ];
                $options += $layout === 'mixed'
                    ? [ 'layout_desktop' => 'matrix', 'layout_tablet' => 'inline_below', 'layout_mobile' => 'inline' ]
                    : [ 'layout_desktop' => $layout, 'layout_tablet' => $layout, 'layout_mobile' => $layout ];
                $html = jprm_columns_fixture( $options );
                $copies = $layout === 'mixed' ? 3 : 1;
                columns_check( substr_count( $html, 'class="jp-menu__title"' ) === ( $title ? 12 * $copies : 0 ), 'Title visibility incorrect: ' . $layout );
                columns_check( substr_count( $html, 'class="jp-menu__desc"' ) === ( $description ? 12 * $copies : 0 ), 'Description visibility incorrect: ' . $layout );
                columns_check( substr_count( $html, '>Vegetarian</span>' ) === ( $badges ? 12 * $copies : 0 ), 'Hiding content lost badges: ' . $layout );
                columns_check( substr_count( $html, '>8.50<' ) === 12 * $copies, 'Hiding content lost prices: ' . $layout );
                columns_check( strpos( $html, '>Glass<' ) !== false, 'Hiding content lost price labels: ' . $layout );
                columns_check( substr_count( $html, 'Section information' ) === 2, 'Hiding content lost Info Blocks.' );
                if ( ! $title ) { columns_check( strpos( $html, 'class="jp-leader"' ) === false, 'Titleless content must not leave a leader.' ); }
                if ( ! $description ) { columns_check( strpos( $html, 'class="jp-left-desc"' ) === false, 'Hidden descriptions must not leave empty wrappers.' ); }
                if ( ! $title && ! $badges ) { columns_check( strpos( $html, 'class="jp-menu__titlewrap"' ) === false, 'Hidden titles must not leave empty wrappers.' ); }
            }
        }
    }
}
$defaults = jprm_columns_fixture();
columns_check( $defaults === jprm_columns_fixture( [ 'show_item_title' => true, 'show_item_description' => true ] ), 'Missing settings must preserve existing menus.' );
columns_check( strpos( $controls, "'jprm_section_sections_menus'" ) < strpos( $controls, "'jprm_section_item_content'" ) && strpos( $controls, "'jprm_section_item_content'" ) < strpos( $controls, "'jprm_section_prices_labels'" ), 'Item panel must follow Sections and Menus.' );
foreach ( [ 'show_item_title', 'show_item_description' ] as $key ) {
    columns_check( preg_match( "/add_control\\( '" . $key . "', \\[.*?'default'\\s*=> 'yes'/s", $controls ) === 1, 'Visibility switch must default on.' );
}
echo "All item title/description combinations, device variants, badges, prices and backwards-compatible defaults passed.\n";
