<?php
/** Exercise editor saves against the real per-Menu structure store. */
define( 'ABSPATH', __DIR__ );
$meta = [];
$terms = [ 'jprm_menu' => [ 8 ], 'jprm_section' => [ 11 ] ];
$valid_nonce = true;
$can_edit = true;
$warnings = [];
function get_post_type( $id ) { return 'jprm_menu_item'; }
function wp_get_post_terms( $id, $taxonomy, $args ) { global $terms; return $terms[ $taxonomy ]; }
function is_wp_error( $value ) { return false; }
function get_term_meta( $id, $key, $single ) { global $meta; return $meta[ $id ]; }
function update_term_meta( $id, $key, $value ) { global $meta; $meta[ $id ] = $value; return true; }
function wp_is_post_revision( $id ) { return false; }
function wp_is_post_autosave( $id ) { return false; }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce( $value, $action ) { global $valid_nonce; return $valid_nonce; }
function current_user_can( ...$args ) { global $can_edit; return $can_edit; }
function get_taxonomy( $name ) { return (object) [ 'cap' => (object) [ 'assign_terms' => 'edit_posts' ] ]; }
function add_filter( ...$args ) { global $warnings; $warnings[] = $args; }
require_once dirname( __DIR__ ) . '/includes/data/class-menu-structure-store.php';
require_once dirname( __DIR__ ) . '/includes/admin/class-item-placement-sync.php';
use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store as Store;
use JelloPoint\RestaurantMenu\Admin\Item_Placement_Sync as Sync;
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
Store::save( 8, [ 'sections' => [ [ 'id' => 11, 'items' => [ 100, 101 ] ], [ 'id' => 12 ] ] ] );
Store::save( 9, [ 'sections' => [ [ 'id' => 11, 'items' => [ 100 ] ] ] ] );
$_POST = [ 'jprm_meta_nonce' => 'valid', 'tax_input' => [ 'jprm_menu' => [ 8 ], 'jprm_section' => [ 11 ] ] ];
$post = (object) [ 'post_type' => 'jprm_menu_item' ];
Sync::save( 102, $post );
check( 11 === Store::item_placements( 8 )[102]['section_id'], 'Editor assignment did not reach Builder structure.' );
$before = $meta;
Sync::save( 100, $post );
check( $before === $meta, 'Unchanged save reordered items or changed another Menu.' );
Sync::capture( 100 );
$terms['jprm_section'] = [ 12 ];
Sync::save( 100, $post );
check( 12 === Store::item_placements( 8 )[100]['section_id'], 'Section move was not synchronized.' );
check( 11 === Store::item_placements( 9 )[100]['section_id'], 'Unrelated shared Menu was changed.' );
Sync::capture( 100 );
$terms['jprm_menu'] = [ 9 ];
$terms['jprm_section'] = [ 11 ];
Sync::save( 100, $post );
check( ! isset( Store::item_placements( 8 )[100] ), 'Removed Menu retained the item.' );
check( isset( Store::item_placements( 9 )[100] ), 'New Menu lost the item.' );
$terms = [ 'jprm_menu' => [ 8 ], 'jprm_section' => [ 11, 12 ] ];
Sync::save( 103, $post );
check( ! isset( Store::item_placements( 8 )[103] ) && count( $warnings ) > 0, 'Ambiguous assignment was guessed or warning missing.' );
$before = $meta;
$valid_nonce = false;
$terms['jprm_section'] = [ 12 ];
Sync::save( 102, $post );
check( $before === $meta, 'Invalid nonce changed placements.' );
$valid_nonce = true;
$can_edit = false;
Sync::save( 102, $post );
check( $before === $meta, 'Missing capability changed placements.' );
$can_edit = true;
unset( $_POST['tax_input'] );
Sync::save( 102, $post );
check( $before === $meta, 'Absent editor fields changed placements.' );
echo "Item placement synchronization checks passed.\n";
