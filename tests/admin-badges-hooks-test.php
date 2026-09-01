<?php
/** Standalone checks for Dietary Badges admin hook registration. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_admin_actions = [];
$jprm_admin_filters = [];
$jprm_media_enqueues = 0;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $jprm_admin_actions;
	$jprm_admin_actions[ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $jprm_admin_filters;
	$jprm_admin_filters[ $hook ][] = $callback;
}
function wp_enqueue_media() {
	global $jprm_media_enqueues;
	$jprm_media_enqueues++;
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }

require_once dirname( __DIR__ ) . '/includes/admin/class-admin-menu.php';

use JelloPoint\RestaurantMenu\Admin\Admin_Menu;
use JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap;

function jprm_admin_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

Admin_Menu::init();
jprm_admin_assert_same( 1, count( $jprm_admin_actions['admin_post_jprm_save_dietary_badges'] ?? [] ), 'The active badge form must have exactly one save handler.' );
jprm_admin_assert_same( 0, count( $jprm_admin_actions['admin_post_jprm_badges_save'] ?? [] ), 'The unused legacy badge action must not be registered.' );

require_once dirname( __DIR__ ) . '/includes/admin/class-admin-dietary-badges.php';
$dummy_store = new class {};
new JPRM_Admin_Dietary_Badges( $dummy_store );
jprm_admin_assert_same( 1, count( $jprm_admin_actions['admin_post_jprm_save_dietary_badges'] ?? [] ), 'Constructing the screen UI must not register another save handler.' );

$_GET['page'] = 'jprm-dietary-badges';
Badges_Post_Bootstrap::enqueue_admin_assets( '' );
jprm_admin_assert_same( 1, $jprm_media_enqueues, 'Badge media assets must load on the actual hyphenated page slug.' );

fwrite( STDOUT, "Dietary Badges admin hook checks passed.\n" );
