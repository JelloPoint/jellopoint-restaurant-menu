<?php
/** Standalone source checks for the Print/PDF logo selector preview. */

$source = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-print-document.php' );
if ( false === $source ) {
	fwrite( STDERR, "Unable to inspect Print/PDF administration source.\n" );
	exit( 1 );
}

$checks = [
	'visible preview container' => 'class="jprm-logo-preview"',
	'image-only media library'  => "library:{type:'image'}",
	'medium image fallback'     => 'sizes.medium&&sizes.medium.url',
	'thumbnail fallback'        => 'sizes.thumbnail&&sizes.thumbnail.url',
	'safe image construction'   => "\$('<img>',{src:url,alt:''})",
	'empty preview state'       => "__( 'No logo selected', 'jellopoint-restaurant-menu' )",
];

foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

echo "Print logo preview checks passed.\n";
