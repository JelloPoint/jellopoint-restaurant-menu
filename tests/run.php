<?php
/**
 * Run the plugin's standalone regression checks.
 *
 * Each test file is executed in a separate PHP process because the tests use
 * lightweight WordPress and Elementor stubs that intentionally define some of
 * the same functions and classes.
 */

$test_files = glob( __DIR__ . DIRECTORY_SEPARATOR . '*-test.php' );

if ( false === $test_files ) {
	fwrite( STDERR, "Unable to discover regression tests.\n" );
	exit( 1 );
}

sort( $test_files, SORT_STRING );

if ( array() === $test_files ) {
	fwrite( STDERR, "No regression tests found.\n" );
	exit( 1 );
}

$failures = 0;

foreach ( $test_files as $test_file ) {
	echo 'Running ' . basename( $test_file ) . "\n";

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_file );
	passthru( $command, $exit_code );

	if ( 0 !== $exit_code ) {
		++$failures;
	}
}

if ( 0 !== $failures ) {
	fwrite( STDERR, sprintf( "%d regression test file(s) failed.\n", $failures ) );
	exit( 1 );
}

echo sprintf( "All %d regression test files passed.\n", count( $test_files ) );
