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
columns_check( substr_count( $variants, 'class="jp-menu__section-items"' ) === 2, 'Device variants must share section column wrappers.' );
columns_check( substr_count( $variants, 'jprm-layout-variant' ) === 6, 'Device layout variants missing.' );
$daily = jprm_columns_fixture( [ 'daily_menu' => [ 'enabled' => true, 'item_separator' => 'or' ] ] );
if ( ! getenv( 'JPRM_RENDER_TEST_ROOT' ) || strpos( getenv( 'JPRM_RENDER_TEST_ROOT' ), '-premium' ) !== false ) {
    columns_check( strpos( $daily, '>8.50<' ) === false, 'Daily menus must keep item prices hidden.' );
    columns_check( substr_count( $daily, 'class="jp-menu__item-separator"' ) === 10, 'Daily separators missing.' );
}
$root = dirname( __DIR__ );
$controls = file_get_contents( $root . '/includes/widgets/traits/restaurant-menu-controls.php' );
columns_check( strpos( $controls, "'section_source'" ) < strpos( $controls, "'jprm_section_layout'" ) && strpos( $controls, "'jprm_section_layout'" ) < strpos( $controls, "'jprm_section_sections_menus'" ), 'Layout control order incorrect.' );
columns_check( substr_count( $controls, "'jprm_section_layout'" ) === 1, 'Layout registered twice.' );
columns_check( preg_match( "/add_control\( 'layout_section_heading_full_width', \\[.*?'default' => '',.*?'condition' => \\[ 'layout_columns' => \\[ '2', '3' \\] \\]/s", $controls ) === 1, 'New setting must default off and require multiple columns.' );
columns_check( substr_count( $controls, "'layout_section_heading_full_width!' => 'yes'" ) === 3, 'All manual section splitting controls must hide in new mode.' );
echo "Section column rendering, hierarchy, item order, layouts, Info Blocks and legacy mode passed.\n";
