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
jprm_ib_assert( true, false !== strpos( $widget, 'Info_Block_Store::data_for_menu' ), 'Elementor must include central Info Blocks.' );
jprm_ib_assert( true, false !== strpos( $rest, 'menu-builder/info-blocks/save' ), 'Menu Builder must manage Info Block placements.' );
fwrite( STDOUT, "Reusable Info Block checks passed.\n" );
