<?php
/** Standalone checks for safe, repeatable standard-data installation. */

define( 'ABSPATH', __DIR__ . '/' );
define( 'JPRM_PLUGIN_URL', 'https://example.test/wp-content/plugins/jprm/' );

function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }
function trailingslashit( $value ) { return rtrim( (string) $value, '/\\' ) . '/'; }

require_once dirname( __DIR__ ) . '/includes/data/class-default-data.php';

function jprm_defaults_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$badges = JPRM_Default_Data::badge_defaults();
$labels = JPRM_Default_Data::label_defaults();
jprm_defaults_assert( 8 === count( $badges ), 'Eight standard dietary badges are defined.' );
jprm_defaults_assert( 8 === count( $labels ), 'Eight standard price labels are defined.' );
jprm_defaults_assert( '.svg' === substr( $badges[0]['icon_url'], -4 ), 'Bundled badge icons use SVG assets.' );

$existing = [
	[ 'slug' => 'vegan', 'name' => 'My Vegan', 'icon_id' => 99, 'icon_url' => '', 'active' => false, 'order' => 0 ],
	[ 'slug' => 'spicy', 'name' => 'Hot', 'icon_id' => 0, 'icon_url' => '', 'active' => true, 'order' => 1 ],
];
$merged = JPRM_Default_Data::merge_rows( $existing, $badges );
jprm_defaults_assert( 6 === $merged['added'], 'Only missing standard badges are added.' );
jprm_defaults_assert( 1 === $merged['icons_added'], 'An icon is added only to a matching row without an icon.' );
jprm_defaults_assert( 99 === $merged['rows'][0]['icon_id'], 'A selected Media Library icon is preserved.' );
jprm_defaults_assert( 'My Vegan' === $merged['rows'][0]['name'] && false === $merged['rows'][0]['active'], 'Existing names and settings are preserved.' );

$again = JPRM_Default_Data::merge_rows( $merged['rows'], $badges );
jprm_defaults_assert( 0 === $again['added'] && 0 === $again['icons_added'], 'Running the installer again changes nothing.' );

fwrite( STDOUT, "Default data checks passed.\n" );
