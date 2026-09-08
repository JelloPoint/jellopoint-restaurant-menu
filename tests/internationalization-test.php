<?php
/** Standalone source checks for the canonical translation domain. */

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/jellopoint-restaurant-menu.php' );
$bootstrap = str_replace( "\r\n", "\n", $bootstrap );
foreach ( [ 'Domain Path:       /languages', 'Text Domain:       jellopoint-restaurant-menu' ] as $needle ) {
	if ( false === strpos( $bootstrap, $needle ) ) {
		fwrite( STDERR, "Missing translation bootstrap: {$needle}\n" );
		exit( 1 );
	}
}
if ( false !== strpos( $bootstrap, 'load_plugin_textdomain(' ) ) {
	fwrite( STDERR, "WordPress.org language packs must use just-in-time translation loading.\n" );
	exit( 1 );
}

$php_files = array_merge(
	glob( $root . '/includes/*.php' ) ?: [],
	glob( $root . '/includes/*/*.php' ) ?: [],
	glob( $root . '/includes/*/*/*.php' ) ?: []
);
$wrong_domain = '~(?:__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\([^\r\n]*,\s*[\'\"]jprm[\'\"]\s*\)~';
foreach ( $php_files as $file ) {
	$source = file_get_contents( $file );
	if ( false !== $source && preg_match( $wrong_domain, $source ) ) {
		fwrite( STDERR, "Legacy translation domain remains in {$file}.\n" );
		exit( 1 );
	}
}

echo "Internationalization checks passed.\n";
