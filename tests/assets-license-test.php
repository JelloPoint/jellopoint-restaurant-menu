<?php
/** Standalone checks for distributable runtime assets and license metadata. */

$root = dirname( __DIR__ );
$license = file_get_contents( $root . '/LICENSE.md' );
$asset_notice = file_get_contents( $root . '/assets/README.md' );

if ( false === $license || false === strpos( $license, 'GPL-2.0-or-later' ) ) {
	fwrite( STDERR, "Missing GPL-2.0-or-later distribution license.\n" );
	exit( 1 );
}
if ( false === $asset_notice || false === strpos( $asset_notice, 'icons/defaults/' ) ) {
	fwrite( STDERR, "Missing bundled asset provenance.\n" );
	exit( 1 );
}

foreach ( [ '/includes/assets/menu.css', '/includes/admin/assets/jprm-items-list.js' ] as $unused ) {
	if ( file_exists( $root . $unused ) ) {
		fwrite( STDERR, "Unused duplicate asset remains: {$unused}\n" );
		exit( 1 );
	}
}

$asset_files = array_merge(
	glob( $root . '/assets/*/*.css' ) ?: [],
	glob( $root . '/assets/*/*.js' ) ?: [],
	glob( $root . '/assets/*/*/*.svg' ) ?: []
);
foreach ( $asset_files as $file ) {
	$source = file_get_contents( $file );
	if ( false === $source ) {
		fwrite( STDERR, "Unable to inspect asset: {$file}\n" );
		exit( 1 );
	}
	if ( preg_match( '~(?:src|href)=["\']https?://|@import\s+url\s*\(\s*["\']?https?://~i', $source ) ) {
		fwrite( STDERR, "Remote dependency found in bundled asset: {$file}\n" );
		exit( 1 );
	}
}

echo "Asset and license checks passed.\n";
