<?php
/** Standalone checks for the generated WordPress.org submission candidate. */

require_once dirname( __DIR__ ) . '/tools/build-packages.php';

function jprm_wporg_check( bool $condition, string $message ) : void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$root        = dirname( __DIR__ );
$destination = sys_get_temp_dir() . '/jprm-wporg-' . bin2hex( random_bytes( 8 ) );
$results     = JPRM_Package_Builder::build( $root, $destination );
$free        = $results['free'];
$free_root   = $free['directory'];
$main        = file_get_contents( $free_root . '/jellopoint-restaurant-menu.php' );
$readme      = file_get_contents( $free_root . '/readme.txt' );

jprm_wporg_check( false !== $main && false !== $readme, 'Unable to read generated Free metadata.' );
jprm_wporg_check( false !== strpos( $main, 'Plugin Name:       JelloPoint – Restaurant Menu' ), 'Free plugin name is wrong.' );
jprm_wporg_check( false === strpos( $main, 'Restaurant Menu Pro' ), 'Free plugin header still identifies Pro.' );
jprm_wporg_check( false !== strpos( $main, 'Version:           2.0.41' ), 'Free plugin version is wrong.' );
jprm_wporg_check( false !== strpos( $main, "'is_premium' => false" ), 'Free Freemius identity is wrong.' );
jprm_wporg_check( false !== strpos( $main, "'is_org_compliant' => true" ), 'WordPress.org compliance flag is missing.' );
jprm_wporg_check( false === strpos( $main, "'wp_org_gatekeeper'" ), 'Premium gatekeeper leaked into Free.' );
jprm_wporg_check( false === stripos( $main, 'Update URI:' ), 'Third-party Update URI must not be in the submission package.' );
jprm_wporg_check( false !== strpos( $readme, '=== JelloPoint – Restaurant Menu ===' ), 'Free readme title is wrong.' );
jprm_wporg_check( false !== strpos( $readme, 'Contributors: jellopoint' ), 'WordPress.org contributor is missing.' );
jprm_wporg_check( false !== strpos( $readme, 'Stable tag: 2.0.41' ), 'Free stable tag is wrong.' );
jprm_wporg_check( false !== strpos( $readme, 'External service: Freemius' ), 'External service disclosure is missing.' );
jprm_wporg_check( false !== strpos( $readme, 'https://freemius.com/privacy/' ), 'Freemius privacy link is missing.' );
jprm_wporg_check( false !== strpos( $readme, 'https://github.com/JelloPoint/jellopoint-restaurant-menu' ), 'Public source link is missing.' );
jprm_wporg_check( is_file( $free['zip'] ), 'Free ZIP was not generated.' );

$zip     = new PharData( $free['zip'] );
$prefix  = 'jellopoint-restaurant-menu/';
$entries = [];
foreach ( new RecursiveIteratorIterator( $zip ) as $entry ) {
	$path = str_replace( '\\', '/', $entry->getPathName() );
	$path = substr( $path, strpos( $path, '.zip/' ) + 5 );
	$entries[] = $path;
}
jprm_wporg_check( [] !== $entries, 'Free ZIP is empty.' );
foreach ( $entries as $entry ) {
	jprm_wporg_check( 0 === strpos( $entry, $prefix ), 'ZIP contains a file outside the expected root folder.' );
}

foreach ( JPRM_Package_Builder::PRO_FILES as $pro_file ) {
	jprm_wporg_check( ! is_file( $free_root . '/' . $pro_file ), 'Pro file leaked into Free: ' . $pro_file );
}
jprm_wporg_check( ! is_file( $free_root . '/uninstall.php' ), 'Root uninstall.php must not be submitted.' );
jprm_wporg_check( ! is_dir( $free_root . '/.git' ) && ! is_dir( $free_root . '/tests' ), 'Development files leaked into Free.' );

echo "WordPress.org submission package checks passed.\n";
