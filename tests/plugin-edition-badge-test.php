<?php
/** Verify the Plugins-screen badge follows the package identity, not entitlement. */
define( 'ABSPATH', __DIR__ );
define( 'JPRM_PLUGIN_FILE', dirname( __DIR__ ) . '/jellopoint-restaurant-menu.php' );

$jprm_test_premium = true;
$jprm_test_hooks   = array();

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

function badge_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-plugin.php';

\JelloPoint\RestaurantMenu\Plugin::init();
badge_check(
	in_array(
		array( 'admin_footer-plugins.php', array( \JelloPoint\RestaurantMenu\Plugin::class, 'render_plugin_edition_badge' ) ),
		$jprm_test_hooks,
		true
	),
	'Plugins-screen badge hook is not registered.'
);

ob_start();
\JelloPoint\RestaurantMenu\Plugin::render_plugin_edition_badge();
$premium_output = ob_get_clean();
badge_check( false !== strpos( $premium_output, "badge.textContent = 'PRO';" ), 'Premium build does not render the PRO badge.' );
badge_check( false !== strpos( $premium_output, 'jprm-plugin-edition-badge' ), 'Premium badge styling or selector is missing.' );

$jprm_test_premium = false;
ob_start();
\JelloPoint\RestaurantMenu\Plugin::render_plugin_edition_badge();
$free_output = ob_get_clean();
badge_check( '' === $free_output, 'Free build renders the PRO badge.' );

$method = new ReflectionMethod( \JelloPoint\RestaurantMenu\Plugin::class, 'render_plugin_edition_badge' );
$source = file( $method->getFileName() );
$body   = implode( '', array_slice( $source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
badge_check( false !== strpos( $body, 'is_premium()' ), 'Badge is not based on Premium build identity.' );
badge_check( false === strpos( $body, 'can_use_premium_code' ), 'Badge incorrectly depends on license entitlement.' );

echo "Free/Pro Plugins-screen badge identity checks passed.\n";
