<?php
/** Standalone checks for optional Elementor dependency handling. */

$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
$section_script = file_get_contents( dirname( __DIR__ ) . '/assets/admin/elementor-sections-dep.js' );

if ( false === $plugin || false === $section_script ) {
	fwrite( STDERR, "Could not read Elementor integration files.\n" );
	exit( 1 );
}

$checks = [
	'admin_notices hook'       => "add_action( 'admin_notices', [ __CLASS__, 'render_elementor_dependency_notice' ] )",
	'activation capability'    => "current_user_can( 'activate_plugins' )",
	'Elementor class check'    => "class_exists( '\\Elementor\\Plugin' )",
	'Elementor loaded check'   => "did_action( 'elementor/loaded' )",
	'optional dependency text' => 'Restaurant menu data management remains available without it.',
	'per-Menu Section options' => 'Menu_Structure_Store::section_options( $menu_id )',
];

foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $plugin, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

$script_checks = [
	'empty Section map clearing' => 'prev !== null && sig === prev',
	'cached Menu map isolation'   => 'state.lastMenuId === currentMenuId',
];

foreach ( $script_checks as $label => $needle ) {
	if ( false === strpos( $section_script, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

echo "Elementor dependency checks passed.\n";
