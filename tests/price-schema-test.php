<?php
/**
 * Standalone compatibility checks for the canonical price schema.
 *
 * Run with: php tests/price-schema-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_test_meta = [];

function get_post_meta( $post_id, $key, $single = false ) {
	global $jprm_test_meta;
	return $jprm_test_meta[ $post_id ][ $key ] ?? '';
}

function update_post_meta( $post_id, $key, $value ) {
	global $jprm_test_meta;
	$jprm_test_meta[ $post_id ][ $key ] = $value;
	return true;
}

function delete_post_meta( $post_id, $key ) {
	global $jprm_test_meta;
	unset( $jprm_test_meta[ $post_id ][ $key ] );
	return true;
}

function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

require_once dirname( __DIR__ ) . '/includes/storage/class-price-schema.php';
require_once dirname( __DIR__ ) . '/includes/data/class-price-schema.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-price-repository.php';

use JelloPoint\RestaurantMenu\Storage\Price_Repository;

function jprm_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

function jprm_fixture( string $name ): array {
	$raw = file_get_contents( __DIR__ . '/fixtures/' . $name );
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		throw new RuntimeException( 'Invalid fixture: ' . $name );
	}
	return $data;
}

$single = jprm_fixture( 'item_single.json' );
$multi  = jprm_fixture( 'intem_multi.json' );

update_post_meta( 101, Price_Repository::META_KEY, $single );
jprm_assert_same( $single, Price_Repository::get( 101 ), 'Legacy array single price must remain readable.' );

update_post_meta( 102, Price_Repository::META_KEY, wp_json_encode( $single ) );
jprm_assert_same( $single, Price_Repository::get( 102 ), 'JSON single price must remain readable.' );

update_post_meta( 103, Price_Repository::META_KEY, $multi );
jprm_assert_same( $multi, Price_Repository::get( 103 ), 'Legacy array multi-price data, including custom icon_id, must be preserved.' );

Price_Repository::set( 104, $multi );
$stored = get_post_meta( 104, Price_Repository::META_KEY, true );
jprm_assert_same( true, is_string( $stored ), 'Canonical writes must use JSON.' );
jprm_assert_same( $multi, Price_Repository::get( 104 ), 'Canonical JSON round-trip must preserve all supported fields.' );

$nested = [
	'mode'      => 'single',
	'price'     => wp_json_encode( [ 'price' => ' 12,50 ' ] ),
	'label_ref' => 'Lunch',
	'icon_id'   => 289,
	'hide_icon' => true,
];
$normalized_nested = [
	'mode'      => 'single',
	'price'     => '12,50',
	'label_ref' => 'Lunch',
	'hide_icon' => true,
	'icon_id'   => 289,
];
jprm_assert_same( true, Price_Repository::set( 105, $nested ), 'Supported nested price JSON must be accepted.' );
jprm_assert_same( $normalized_nested, Price_Repository::get( 105 ), 'Normalization must preserve icon and visibility settings.' );

update_post_meta( 106, Price_Repository::META_KEY, 'not-json' );
jprm_assert_same( [], Price_Repository::get( 106 ), 'Malformed legacy data must fail safely.' );
jprm_assert_same( false, Price_Repository::set( 107, [ 'mode' => 'multi', 'rows' => [] ] ), 'Empty price configurations must not be written.' );
jprm_assert_same( '', get_post_meta( 107, Price_Repository::META_KEY, true ), 'A rejected configuration must leave price meta untouched.' );

fwrite( STDOUT, "Price schema compatibility checks passed.\n" );
