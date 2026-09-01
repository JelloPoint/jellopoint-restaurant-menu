<?php
/** Standalone checks for Import/Export admin hook registration. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_import_actions = [];

function is_admin() { return true; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $jprm_import_actions;
	$jprm_import_actions[ $hook ][] = $callback;
}

require_once dirname( __DIR__ ) . '/includes/admin/class-admin-import-export.php';

use JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export;

function jprm_import_hook_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

JPRM_Admin_Import_Export::bootstrap();
jprm_import_hook_assert_same( 1, count( $jprm_import_actions['admin_post_jprm_import_demo'] ?? [] ), 'The demo import must have exactly one authenticated admin handler.' );
jprm_import_hook_assert_same( 1, count( $jprm_import_actions['admin_post_jprm_import'] ?? [] ), 'The normal import handler must remain registered.' );
jprm_import_hook_assert_same( 1, count( $jprm_import_actions['admin_post_jprm_export'] ?? [] ), 'The export handler must remain registered.' );

fwrite( STDOUT, "Import/Export admin hook checks passed.\n" );
