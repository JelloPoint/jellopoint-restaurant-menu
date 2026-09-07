<?php
/** Exercise the actual combined module loader in frontend and admin contexts. */
define( 'ABSPATH', __DIR__ );
define( 'JPRM_PLUGIN_PATH', dirname( __DIR__ ) . '/' );
$admin_context = false;
$hooks = [];
function is_admin() { global $admin_context; return $admin_context; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { global $hooks; $hooks[$name][] = $callback; }
function check_module( bool $ok, string $message ) : void { if ( ! $ok ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-catalog.php';
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-access.php';
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-loader.php';
function jprm_fs() { return new class { public function is_premium() { return true; } public function can_use_premium_code() { return true; } }; }
use JelloPoint\RestaurantMenu\Modules\Module_Catalog as Catalog;
use JelloPoint\RestaurantMenu\Modules\Module_Loader as Loader;
check_module( 'free' === Catalog::tier( 'multiple_prices' ), 'Multiple Prices must remain Free.' );
foreach ( [ 'daily_weekly_menus', 'print_pdf', 'import_export' ] as $id ) {
	check_module( Catalog::is_pro( $id ), 'Incorrect module ownership: ' . $id );
}
check_module( null === Catalog::tier( 'unknown' ), 'Unknown modules must not be classified as Free.' );
foreach ( Catalog::all() as $id => $module ) {
	foreach ( $module['dependencies'] as $dependency ) {
		check_module( null !== Catalog::tier( $dependency ), 'Missing dependency.' );
		check_module( 'free' !== $module['tier'] || ! Catalog::is_pro( $dependency ), 'Free module depends on Pro.' );
	}
}
Loader::boot_admin();
check_module( [] === $hooks, 'Frontend registered admin hooks.' );
Loader::load_runtime();
check_module( class_exists( 'JelloPoint\\RestaurantMenu\\Data\\Print_Document_Builder' ), 'Runtime print builder missing.' );
check_module( class_exists( 'JelloPoint\\RestaurantMenu\\Render\\Print_Document_Renderer' ), 'Runtime print renderer missing.' );
$admin_context = true;
Loader::boot_admin();
foreach ( [ 'admin_post_jprm_import', 'admin_post_jprm_export', 'admin_post_jprm_import_demo', 'admin_post_jprm_install_defaults', 'admin_post_jprm_save_print_document', 'admin_post_jprm_preview_print_document' ] as $hook ) {
	check_module( 1 === count( $hooks[$hook] ?? [] ), 'Missing or duplicated handler: ' . $hook );
	check_module( is_callable( $hooks[$hook][0] ), 'Handler is not callable: ' . $hook );
}
$before = $hooks;
Loader::load_runtime(); Loader::boot_admin();
check_module( $before === $hooks, 'Repeated boot duplicated hooks.' );
echo "Module architecture checks passed.\n";
