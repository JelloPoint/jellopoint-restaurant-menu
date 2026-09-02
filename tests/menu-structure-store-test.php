<?php
/** Standalone checks for the Phase 10D per-Menu structure store. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_structure_meta = [];
$jprm_term_meta = [
	11 => [ '_jprm_menu_term_id' => 8, '_jprm_section_order' => 2 ],
	12 => [ '_jprm_menu_term_id' => 8, '_jprm_section_order' => 1 ],
	13 => [ '_jprm_menu_term_id' => 9, '_jprm_section_order' => 1 ],
];

function get_term_meta( $term_id, $key, $single = false ) {
	global $jprm_structure_meta, $jprm_term_meta;
	if ( '_jprm_menu_structure_v2' === $key ) { return $jprm_structure_meta[ $term_id ] ?? ''; }
	return $jprm_term_meta[ $term_id ][ $key ] ?? '';
}
function update_term_meta( $term_id, $key, $value ) { global $jprm_structure_meta; $jprm_structure_meta[ $term_id ] = $value; return true; }
function get_terms( $args ) {
	return [
		(object) [ 'term_id' => 11, 'parent' => 0 ],
		(object) [ 'term_id' => 12, 'parent' => 11 ],
		(object) [ 'term_id' => 13, 'parent' => 0 ],
	];
}
function is_wp_error( $value ) { return false; }
function wp_get_post_terms( $post_id, $taxonomy, $args = [] ) { return 501 === $post_id ? [ 12 ] : [ 11 ]; }
function get_post_meta( $post_id, $key, $single = false ) { return 501 === $post_id ? 3 : 1; }

class WP_Query {
	public array $posts = [ 501, 502 ];
	public function __construct( array $args ) { if ( [] === $args ) { $this->posts = []; } }
}

require_once dirname( __DIR__ ) . '/includes/data/class-menu-structure-store.php';

use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;

function jprm_structure_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$legacy = Menu_Structure_Store::get( 8 );
jprm_structure_assert_same( [ 12, 11 ], array_column( $legacy['sections'], 'id' ), 'Legacy sections must be ordered and restricted to their owner Menu.' );
jprm_structure_assert_same( [ [ 'id' => 501, 'order' => 0 ] ], $legacy['sections'][0]['items'], 'Legacy item ordering must normalize densely.' );
jprm_structure_assert_same( false, Menu_Structure_Store::has_explicit( 8 ), 'Legacy reads must not mutate Menu metadata.' );

$dirty = [ 'sections' => [
	[ 'id' => 21, 'parent_id' => 22, 'order' => 8, 'items' => [ [ 'id' => 601, 'order' => 9 ], [ 'id' => 601, 'order' => 1 ] ] ],
	[ 'id' => 22, 'parent_id' => 21, 'order' => 2, 'items' => [ 602 ] ],
	[ 'id' => 21, 'parent_id' => 0, 'order' => 0 ],
] ];
$normalized = Menu_Structure_Store::normalize( $dirty );
jprm_structure_assert_same( [ 22, 21 ], array_column( $normalized['sections'], 'id' ), 'Duplicate Sections must be removed and ordering normalized.' );
jprm_structure_assert_same( 0, $normalized['sections'][0]['parent_id'], 'A cyclic parent relationship must be broken safely.' );
jprm_structure_assert_same( [ [ 'id' => 601, 'order' => 0 ] ], $normalized['sections'][1]['items'], 'An item may occur only once per Menu.' );

jprm_structure_assert_same( true, Menu_Structure_Store::save( 8, $dirty ), 'A valid Menu structure must be saved.' );
jprm_structure_assert_same( true, Menu_Structure_Store::has_explicit( 8 ), 'Saved structures must become the explicit source for that Menu.' );
jprm_structure_assert_same( [ 22, 21 ], Menu_Structure_Store::section_ids( 8 ), 'Section IDs must come from the explicit structure after migration.' );

jprm_structure_assert_same( true, Menu_Structure_Store::attach_section( 8, 23 ), 'An existing Section must attach to a Menu.' );
jprm_structure_assert_same( [ 22, 21, 23 ], Menu_Structure_Store::section_ids( 8 ), 'Attaching must preserve the existing Menu structure.' );
jprm_structure_assert_same( true, Menu_Structure_Store::save_section_tree( 8, [ [ 'id' => 23, 'parent_id' => 0 ], [ 'id' => 21, 'parent_id' => 23 ], [ 'id' => 22, 'parent_id' => 21 ] ] ), 'A per-Menu tree must save independently.' );
jprm_structure_assert_same( true, Menu_Structure_Store::detach_section( 8, 21 ), 'A Section subtree must detach from one Menu.' );
jprm_structure_assert_same( [ 23 ], Menu_Structure_Store::section_ids( 8 ), 'Detaching a subtree must retain unrelated Sections.' );

fwrite( STDOUT, "Menu structure store checks passed.\n" );
