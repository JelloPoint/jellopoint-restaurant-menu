<?php
/** Verify the Plugins-screen badge follows the package identity, not entitlement. */
define( 'ABSPATH', __DIR__ );
define( 'JPRM_PLUGIN_FILE', dirname( __DIR__ ) . '/jellopoint-restaurant-menu.php' );
define( 'JPRM_PLUGIN_URL', 'https://example.test/wp-content/plugins/jellopoint-restaurant-menu-premium/' );
define( 'JPRM_VERSION', '2.0.50' );

$jprm_test_premium = true;
$jprm_test_hooks   = array();
$jprm_test_free_active = false;
$jprm_test_delete_data = false;
$jprm_test_styles      = array();
$jprm_test_scripts     = array();
$jprm_test_localized   = array();
$pagenow                = 'plugins.php';

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $jprm_test_hooks;
	$jprm_test_hooks[] = array( $hook, $callback );
}

function jprm_fs() {
	return new class() {
		public function is_premium() {
			global $jprm_test_premium;
			return $jprm_test_premium;
		}
	};
}

function plugin_basename( $file ) {
	return 'jellopoint-restaurant-menu-premium/' . basename( $file );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
	global $jprm_test_styles;
	$jprm_test_styles[ $handle ] = compact( 'src', 'deps', 'version' );
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $in_footer = false ) {
	global $jprm_test_scripts;
	$jprm_test_scripts[ $handle ] = compact( 'src', 'deps', 'version', 'in_footer' );
}

function wp_localize_script( $handle, $object_name, $data ) {
	global $jprm_test_localized;
	$jprm_test_localized[ $handle ] = compact( 'object_name', 'data' );
}

function current_user_can( $capability, ...$args ) {
	return 'delete_plugins' === $capability;
}

function get_plugins() {
	return array(
		'jellopoint-restaurant-menu/jellopoint-restaurant-menu.php' => array( 'Version' => '2.0.41' ),
	);
}

function is_plugin_active( $basename ) {
	global $jprm_test_free_active;
	return $jprm_test_free_active && 'jellopoint-restaurant-menu/jellopoint-restaurant-menu.php' === $basename;
}

function get_option( $name, $default = false ) {
	global $jprm_test_delete_data;
	return 'jprm_delete_data_on_uninstall' === $name && $jprm_test_delete_data ? '1' : $default;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_html_e( $text, $domain = 'default' ) {
	echo $text;
}

function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function esc_url( $url ) {
	return $url;
}

function badge_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-plugin.php';

\JelloPoint\RestaurantMenu\Plugin::init();
badge_check(
	in_array(
		array( 'admin_enqueue_scripts', array( \JelloPoint\RestaurantMenu\Plugin::class, 'enqueue_plugin_edition_badge' ) ),
		$jprm_test_hooks,
		true
	),
	'Plugins-screen badge hook is not registered.'
);

\JelloPoint\RestaurantMenu\Plugin::enqueue_plugin_edition_badge( 'plugins.php' );
badge_check( isset( $jprm_test_styles['jprm-plugin-edition-badge'] ), 'Premium badge stylesheet was not enqueued.' );
badge_check( isset( $jprm_test_scripts['jprm-plugin-edition-badge'] ), 'Premium badge script was not enqueued.' );
badge_check( isset( $jprm_test_localized['jprm-plugin-edition-badge'] ), 'Premium plugin basename was not localized.' );
badge_check( 'jprmPluginEditionBadge' === $jprm_test_localized['jprm-plugin-edition-badge']['object_name'], 'Premium badge configuration has the wrong JavaScript object.' );

$jprm_test_premium = false;
$jprm_test_styles = $jprm_test_scripts = $jprm_test_localized = array();
\JelloPoint\RestaurantMenu\Plugin::enqueue_plugin_edition_badge( 'plugins.php' );
badge_check( array() === $jprm_test_styles && array() === $jprm_test_scripts && array() === $jprm_test_localized, 'Free build enqueues the PRO badge.' );

$method = new ReflectionMethod( \JelloPoint\RestaurantMenu\Plugin::class, 'enqueue_plugin_edition_badge' );
$source = file( $method->getFileName() );
$body   = implode( '', array_slice( $source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
badge_check( false !== strpos( $body, 'is_premium()' ), 'Badge is not based on Premium build identity.' );
badge_check( false === strpos( $body, 'can_use_premium_code' ), 'Badge incorrectly depends on license entitlement.' );

$jprm_test_premium = true;
ob_start();
\JelloPoint\RestaurantMenu\Plugin::render_inactive_free_edition_notice();
$notice_output = ob_get_clean();
badge_check( false !== strpos( $notice_output, 'version 2.0.41' ), 'Inactive Free edition notice omits its version.' );
badge_check( false !== strpos( $notice_output, 'safely delete' ), 'Safe removal guidance is missing.' );

$jprm_test_delete_data = true;
ob_start();
\JelloPoint\RestaurantMenu\Plugin::render_inactive_free_edition_notice();
$delete_warning = ob_get_clean();
badge_check( false !== strpos( $delete_warning, 'Do not delete it' ), 'Delete-data warning is missing.' );

$jprm_test_free_active = true;
ob_start();
\JelloPoint\RestaurantMenu\Plugin::render_inactive_free_edition_notice();
$active_free_output = ob_get_clean();
badge_check( '' === $active_free_output, 'Notice appears while the Free companion is active.' );

echo "Free/Pro Plugins-screen badge and companion notice checks passed.\n";
