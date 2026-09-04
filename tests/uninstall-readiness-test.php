<?php
/** Standalone checks for opt-in uninstall behaviour. */

$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/jellopoint-restaurant-menu.php' );
$settings = file_get_contents( $root . '/includes/admin/class-admin-settings.php' );
$uninstall = file_get_contents( $root . '/uninstall.php' );

foreach ( [ 'main' => $main, 'settings' => $settings, 'uninstall' => $uninstall ] as $label => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "Could not read {$label} source.\n" );
		exit( 1 );
	}
}

$checks = [
	'settings bootstrap'      => [ $main, 'Settings::init()' ],
	'default disabled'        => [ $settings, "'default'           => false" ],
	'settings capability'     => [ $settings, "current_user_can( 'manage_options' )" ],
	'uninstall guard'         => [ $uninstall, "defined( 'WP_UNINSTALL_PLUGIN' )" ],
	'explicit opt-in'         => [ $uninstall, "'1' !== (string) get_option( 'jprm_delete_data_on_uninstall', '0' )" ],
	'menu item deletion'      => [ $uninstall, "'jprm_menu_item'" ],
	'Info Block deletion'     => [ $uninstall, "'jprm_info_block'" ],
	'menu taxonomy deletion'  => [ $uninstall, "'jprm_menu', 'jprm_section'" ],
	'multisite support'       => [ $uninstall, 'switch_to_blog' ],
	'import report cleanup'   => [ $uninstall, '_transient_jprm_ie_report_' ],
];

foreach ( $checks as $label => [ $source, $needle ] ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

$jprm_delete_enabled = false;
$jprm_uninstall_calls = [];

function get_option( $name, $default = false ) {
	global $jprm_delete_enabled;
	return 'jprm_delete_data_on_uninstall' === $name && $jprm_delete_enabled ? '1' : $default;
}
function get_posts( $args ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['post_query'] = $args;
	return [ 11, 12 ];
}
function get_post_stati( $args = [], $output = 'names' ) { return [ 'publish', 'draft', 'trash' ]; }
function wp_delete_post( $post_id, $force_delete = false ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['posts'][] = [ $post_id, $force_delete ];
}
function taxonomy_exists( $taxonomy ) { return false; }
function register_taxonomy( $taxonomy, $object_type, $args = [] ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['registered_taxonomies'][] = $taxonomy;
}
function get_terms( $args ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['term_queries'][] = $args['taxonomy'];
	return [ 21, 22 ];
}
function is_wp_error( $value ) { return false; }
function wp_delete_term( $term_id, $taxonomy ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['terms'][] = [ $term_id, $taxonomy ];
}
function delete_option( $option ) {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['options'][] = $option;
}
function flush_rewrite_rules() {
	global $jprm_uninstall_calls;
	$jprm_uninstall_calls['flushes'] = ( $jprm_uninstall_calls['flushes'] ?? 0 ) + 1;
}
function is_multisite() { return false; }

class JPRM_Uninstall_WPDB_Stub {
	public string $options = 'wp_options';
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function prepare( $query, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $query ), $args ); }
	public function query( $query ) {
		global $jprm_uninstall_calls;
		$jprm_uninstall_calls['query'] = $query;
	}
}

$wpdb = new JPRM_Uninstall_WPDB_Stub();
define( 'WP_UNINSTALL_PLUGIN', true );
require $root . '/uninstall.php';

if ( [] !== $jprm_uninstall_calls ) {
	fwrite( STDERR, "Data was removed without opt-in.\n" );
	exit( 1 );
}

$jprm_delete_enabled = true;
jprm_uninstall_site_data();

$runtime_checks = [
	'two posts permanently deleted' => 2 === count( $jprm_uninstall_calls['posts'] ?? [] ) && true === $jprm_uninstall_calls['posts'][0][1],
	'trashed posts included'         => in_array( 'trash', $jprm_uninstall_calls['post_query']['post_status'] ?? [], true ),
	'both taxonomies registered'    => [ 'jprm_menu', 'jprm_section' ] === ( $jprm_uninstall_calls['registered_taxonomies'] ?? [] ),
	'four terms deleted'            => 4 === count( $jprm_uninstall_calls['terms'] ?? [] ),
	'opt-in option deleted last'    => in_array( 'jprm_delete_data_on_uninstall', $jprm_uninstall_calls['options'] ?? [], true ),
	'import transients deleted'     => false !== strpos( $jprm_uninstall_calls['query'] ?? '', 'jprm\\_ie\\_report\\_' ),
	'rewrite rules flushed'         => 1 === ( $jprm_uninstall_calls['flushes'] ?? 0 ),
];

foreach ( $runtime_checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Failed runtime check: {$label}.\n" );
		exit( 1 );
	}
}

echo "Uninstall-readiness checks passed.\n";
