<?php
/** Standalone checks for Phase 10A Daily Menu field registration and validation. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_daily_hooks = [];
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { global $jprm_daily_hooks; $jprm_daily_hooks[ $hook ][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { global $jprm_daily_hooks; $jprm_daily_hooks[ $hook ][] = $callback; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }

require_once dirname( __DIR__ ) . '/includes/admin/class-jprm-menus-admin.php';

use JelloPoint\RestaurantMenu\Admin\Menus_Admin;

function jprm_daily_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

Menus_Admin::init();
jprm_daily_assert_same( 1, count( $jprm_daily_hooks['jprm_menu_add_form_fields'] ?? [] ), 'New Menu fields must be registered once.' );
jprm_daily_assert_same( 1, count( $jprm_daily_hooks['jprm_menu_edit_form_fields'] ?? [] ), 'Edit Menu fields must be registered once.' );
jprm_daily_assert_same( 1, count( $jprm_daily_hooks['created_jprm_menu'] ?? [] ), 'New Menu metadata save hook must be registered once.' );
jprm_daily_assert_same( 1, count( $jprm_daily_hooks['edited_jprm_menu'] ?? [] ), 'Edited Menu metadata save hook must be registered once.' );

jprm_daily_assert_same( '2026-09-01', Menus_Admin::sanitize_date( '2026-09-01' ), 'A valid ISO date must be retained.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_date( '2026-02-30' ), 'An impossible calendar date must be rejected.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_date( '01-09-2026' ), 'A non-ISO date must be rejected.' );
jprm_daily_assert_same( '2026-09-07', Menus_Admin::sanitize_end_date( '2026-09-01', '2026-09-07', 'range' ), 'A valid inclusive range end date must be retained.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_end_date( '2026-09-07', '2026-09-01', 'range' ), 'An end date before the start date must be rejected.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_end_date( '2026-09-01', '2026-09-07', 'single' ), 'Single-date menus must not retain a range end date.' );
jprm_daily_assert_same( '39.50', Menus_Admin::sanitize_price( '39,50' ), 'A decimal comma must normalize to a dot.' );
jprm_daily_assert_same( '0', Menus_Admin::sanitize_price( '0' ), 'Zero must remain a valid optional price.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_price( '€ 39.50' ), 'Currency symbols must be rejected.' );
jprm_daily_assert_same( '', Menus_Admin::sanitize_price( '-1' ), 'Negative fixed prices must be rejected.' );

fwrite( STDOUT, "Daily Menu field checks passed.\n" );
