<?php
/** Standalone checks for privacy-safe, local-only runtime behavior. */

$root = dirname( __DIR__ );
$runtime_files = array_merge(
	[ $root . '/jellopoint-restaurant-menu.php', $root . '/uninstall.php' ],
	glob( $root . '/includes/*.php' ) ?: [],
	glob( $root . '/includes/*/*.php' ) ?: [],
	glob( $root . '/includes/*/*/*.php' ) ?: []
);

$forbidden = [
	'wp_remote_get(',
	'wp_remote_post(',
	'wp_remote_request(',
	'curl_exec(',
	'curl_multi_exec(',
];

foreach ( $runtime_files as $file ) {
	$source = file_get_contents( $file );
	if ( false === $source ) {
		fwrite( STDERR, "Unable to inspect {$file}.\n" );
		exit( 1 );
	}
	foreach ( $forbidden as $needle ) {
		if ( false !== stripos( $source, $needle ) ) {
			fwrite( STDERR, "Unexpected external-request API in {$file}: {$needle}\n" );
			exit( 1 );
		}
	}
}

$exporter = file_get_contents( $root . '/includes/data/class-exporter.php' );
foreach ( [ "'site_uid'", "'uid'         =>", 'wp_generate_uuid4(' ] as $needle ) {
	if ( false !== strpos( $exporter, $needle ) ) {
		fwrite( STDERR, "Exporter still contains persistent identifier behavior: {$needle}\n" );
		exit( 1 );
	}
}

echo "Privacy and external-request checks passed.\n";
