<?php
/** Standalone checks for optional Elementor dependency handling. */

$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );

if ( false === $plugin ) {
	fwrite( STDERR, "Could not read plugin bootstrap.\n" );
	exit( 1 );
}

$checks = [
	'admin_notices hook'       => "add_action( 'admin_notices', [ __CLASS__, 'render_elementor_dependency_notice' ] )",
	'activation capability'    => "current_user_can( 'activate_plugins' )",
	'Elementor class check'    => "class_exists( '\\Elementor\\Plugin' )",
	'Elementor loaded check'   => "did_action( 'elementor/loaded' )",
	'optional dependency text' => 'Restaurant menu data management remains available without it.',
];

foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $plugin, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

echo "Elementor dependency checks passed.\n";
