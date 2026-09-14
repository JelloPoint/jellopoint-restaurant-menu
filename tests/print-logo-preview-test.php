<?php
/** Standalone source checks for the Print/PDF logo selector preview. */

$source = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-print-document.php' );
$script = file_get_contents( dirname( __DIR__ ) . '/assets/admin/print-document.js' );
if ( false === $source || false === $script ) {
	fwrite( STDERR, "Unable to inspect Print/PDF administration source.\n" );
	exit( 1 );
}

$checks = [
	'visible preview container' => 'class="jprm-logo-preview"',
	'empty preview state'       => "__( 'No logo selected', 'jellopoint-restaurant-menu' )",
	'enqueued preview script'   => "assets/admin/print-document.js",
];

foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

$script_checks = [
	'image-only media library' => "library:{type:'image'}",
	'medium image fallback'    => 'sizes.medium&&sizes.medium.url',
	'thumbnail fallback'       => 'sizes.thumbnail&&sizes.thumbnail.url',
	'safe image construction'  => "\$('<img>',{src:url,alt:''})",
];

foreach ( $script_checks as $label => $needle ) {
	if ( false === strpos( $script, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

echo "Print logo preview checks passed.\n";
