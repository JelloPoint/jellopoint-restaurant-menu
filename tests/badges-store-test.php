<?php
/** Standalone checks for stable Dietary Badge identifiers. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_badge_options = [];

function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', (string) $value ) ); }
function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
	return trim( $value, '-' );
}
function absint( $value ) { return abs( (int) $value ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function wp_get_attachment_image_src( $id, $size ) { return false; }
function get_option( $key, $default = null ) {
	global $jprm_badge_options;
	return $jprm_badge_options[ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ) {
	global $jprm_badge_options;
	$jprm_badge_options[ $key ] = $value;
	return true;
}

require_once dirname( __DIR__ ) . '/includes/data/class-badges-store.php';

function jprm_badge_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$store = new JPRM_Badges_Store();
$store->save_rows( [
	[ 'name' => 'Plant Based', 'slug' => 'vegan', 'active' => 1 ],
	[ 'name' => 'Nut Free', 'active' => 1 ],
	[ 'name' => 'Nut-Free Alternative', 'slug' => 'nut-free', 'active' => 1 ],
] );

$stored = get_option( JPRM_Badges_Store::OPTION_KEY, [] );
jprm_badge_assert_same( 'vegan', $stored[0]['slug'] ?? '', 'Renaming a badge must preserve its existing slug.' );
jprm_badge_assert_same( 'nut-free', $stored[1]['slug'] ?? '', 'New badges must receive a slug from their name.' );
jprm_badge_assert_same( 'nut-free-2', $stored[2]['slug'] ?? '', 'Duplicate badge slugs must be made unique.' );
jprm_badge_assert_same( [ 0, 1, 2 ], array_column( $stored, 'order' ), 'Saved badge order must be normalized.' );

fwrite( STDOUT, "Dietary Badges store checks passed.\n" );
