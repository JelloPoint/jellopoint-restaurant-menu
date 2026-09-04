<?php
/** Standalone checks for REST permission hardening. */

define( 'ABSPATH', __DIR__ );

class WP_REST_Controller {}
class WP_Error {
	private string $code;
	private array $data;
	public function __construct( $code, $message = '', $data = [] ) {
		$this->code = (string) $code;
		$this->data = (array) $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}

$jprm_rest_caps = [];
$jprm_rest_term = (object) [ 'term_id' => 10, 'taxonomy' => 'jprm_menu' ];

function current_user_can( $capability, ...$args ) {
	global $jprm_rest_caps;
	return ! empty( $jprm_rest_caps[ $capability ] );
}
function get_term( $term_id, $taxonomy = '' ) {
	global $jprm_rest_term;
	return $jprm_rest_term;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }

require_once dirname( __DIR__ ) . '/includes/rest/class-jprm-menu-builder-controller.php';

$controller = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();

if ( $controller->read_permissions_check() ) {
	fwrite( STDERR, "REST reads were allowed without edit_posts.\n" );
	exit( 1 );
}

$jprm_rest_caps['edit_posts'] = true;
if ( ! $controller->read_permissions_check() ) {
	fwrite( STDERR, "REST reads were denied with edit_posts.\n" );
	exit( 1 );
}

$denied = $controller->write_permissions_check( [ 'menu_id' => 10 ] );
if ( ! is_wp_error( $denied ) || 403 !== $denied->get_error_data()['status'] ) {
	fwrite( STDERR, "REST writes were allowed without manage_categories.\n" );
	exit( 1 );
}

$jprm_rest_caps['manage_categories'] = true;
$missing = $controller->write_permissions_check( [ 'menu_id' => 10 ] );
if ( ! is_wp_error( $missing ) || 403 !== $missing->get_error_data()['status'] ) {
	fwrite( STDERR, "REST writes were allowed without edit_term.\n" );
	exit( 1 );
}

$jprm_rest_caps['edit_term'] = true;
if ( true !== $controller->write_permissions_check( [ 'menu_id' => 10 ] ) ) {
	fwrite( STDERR, "REST writes were denied for an editable Menu.\n" );
	exit( 1 );
}

$source = file_get_contents( dirname( __DIR__ ) . '/includes/rest/class-jprm-menu-builder-controller.php' );
$checks = [
	'object read capability' => "current_user_can( 'read_post'",
	'object edit capability' => "current_user_can( 'edit_post'",
	'nested object schemas'  => "'additionalProperties' => false",
	'positive ID schemas'    => "'minimum' => 1",
	'sanitized IDs'          => "'sanitize_callback' => 'absint'",
];

foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Missing {$label}.\n" );
		exit( 1 );
	}
}

echo "REST permission checks passed.\n";
