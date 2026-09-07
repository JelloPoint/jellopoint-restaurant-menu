<?php
define( 'ABSPATH', __DIR__ );
define( 'JPRM_PLUGIN_URL', 'https://example.test/wp-content/plugins/jellopoint-restaurant-menu/' );
$can_manage = false; $valid_nonce = false; $installed = 0;
function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['can_manage']; }
function esc_html__( $text, $domain ) { return $text; }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( 'capability denied' ); }
function check_admin_referer( $action, $query_arg = '_wpnonce' ) { if ( 'jprm_restore_defaults' !== $action || ! $GLOBALS['valid_nonce'] ) { throw new RuntimeException( 'nonce denied' ); } }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_safe_redirect( $url ) { if ( false === strpos( $url, 'page=jprm-settings&defaults-restored=1' ) ) { throw new LogicException( 'Wrong redirect.' ); } throw new RuntimeException( 'redirected' ); }
function get_option( $key, $default = false ) { return $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['installed']++; return true; }
function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
require_once dirname( __DIR__ ) . '/includes/data/class-default-data.php';
require_once dirname( __DIR__ ) . '/includes/admin/class-admin-settings.php';
foreach ( [[false, true, 'capability denied'], [true, false, 'nonce denied'], [true, true, 'redirected']] as [$can_manage, $valid_nonce, $expected] ) {
	try { \JelloPoint\RestaurantMenu\Admin\Settings::restore_defaults(); }
	catch ( RuntimeException $e ) { if ( $expected !== $e->getMessage() ) { throw $e; } }
	if ( $installed !== ( 'redirected' === $expected ? 2 : 0 ) ) { throw new LogicException( 'Unauthorized defaults mutation.' ); }
}
echo "Free defaults action capability/nonce checks passed.\n";
