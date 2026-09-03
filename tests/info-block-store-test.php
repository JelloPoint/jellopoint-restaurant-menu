<?php
define( 'ABSPATH', __DIR__ . '/' );
$jprm_info_meta = [];
function get_term_meta( $id, $key, $single = false ) { global $jprm_info_meta; return $jprm_info_meta[$id][$key] ?? ''; }
function update_term_meta( $id, $key, $value ) { global $jprm_info_meta; $jprm_info_meta[$id][$key] = $value; return true; }
require_once dirname( __DIR__ ) . '/includes/data/class-info-block-store.php';
use JelloPoint\RestaurantMenu\Data\Info_Block_Store;
function jprm_ib_assert( $expected, $actual, $message ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$rows = [ [ 'id'=>4,'section_id'=>8,'position'=>'below','order'=>9 ], [ 'id'=>3,'section_id'=>8,'position'=>'above','order'=>1 ], [ 'id'=>3,'section_id'=>8,'position'=>'above','order'=>2 ], [ 'id'=>0,'section_id'=>8 ] ];
jprm_ib_assert( true, Info_Block_Store::save( 2, $rows ), 'Placements must save per Menu.' );
jprm_ib_assert( [ [ 'id'=>3,'section_id'=>8,'position'=>'above','order'=>0 ], [ 'id'=>4,'section_id'=>8,'position'=>'below','order'=>1 ] ], Info_Block_Store::get( 2 ), 'Placements must normalize order, position, and duplicates.' );
$widget = file_get_contents( dirname( __DIR__ ) . '/includes/widgets/class-restaurant-menu.php' );
$rest = file_get_contents( dirname( __DIR__ ) . '/includes/rest/class-jprm-menu-builder-controller.php' );
$controls = file_get_contents( dirname( __DIR__ ) . '/includes/widgets/traits/restaurant-menu-controls.php' );
$styles = file_get_contents( dirname( __DIR__ ) . '/includes/widgets/traits/restaurant-menu-style.php' );
jprm_ib_assert( true, false !== strpos( $widget, 'Info_Block_Store::data_for_widget' ), 'Elementor must resolve selected central Info Blocks.' );
jprm_ib_assert( true, false !== strpos( $rest, 'menu-builder/info-blocks/save' ), 'Menu Builder must manage Info Block placements.' );
jprm_ib_assert( true, false !== strpos( $controls, "'info_block_id'" ), 'Elementor must select centrally managed Info Blocks.' );
jprm_ib_assert( true, false === strpos( $controls, "'ib_title'" ), 'The unused legacy Info Block input must be removed.' );
jprm_ib_assert( true, false !== strpos( $controls, "'block_alignment'" ), 'Each selected Info Block must have independent alignment.' );
jprm_ib_assert( true, false !== strpos( $controls, "'individual_style_heading'" ), 'Per-block overrides must be clearly separated from placement controls.' );
jprm_ib_assert( true, false !== strpos( $controls, '{{CURRENT_ITEM}}.jprm-infoblock' ), 'Per-block selectors must outrank the global Info Block defaults.' );
jprm_ib_assert( true, false !== strpos( $styles, "'infob_alignment'" ), 'Info Blocks must also have a global responsive alignment control.' );
$store = file_get_contents( dirname( __DIR__ ) . '/includes/data/class-info-block-store.php' );
jprm_ib_assert( true, false === strpos( $store, "apply_filters( 'the_content'" ), 'Info Blocks must not re-enter the page content pipeline.' );
jprm_ib_assert( true, false !== strpos( $store, 'format_content' ), 'Info Blocks must use isolated safe content formatting.' );
fwrite( STDOUT, "Reusable Info Block checks passed.\n" );
