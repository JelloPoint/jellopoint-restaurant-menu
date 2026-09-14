<?php
namespace JelloPoint\RestaurantMenu\Modules;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Edition-specific bootstrap; build regions remove Pro dependencies from Free. */
final class Module_Loader {
	private static $runtime_loaded = false;
	private static $admin_loaded = false;

	public static function load_runtime() : void {
		if ( self::$runtime_loaded ) { return; }
// JPRM_PRO_BEGIN:print-runtime
		if ( jprm_fs()->is__premium_only() ) {
			require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-settings.php';
			require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-builder.php';
			require_once JPRM_PLUGIN_PATH . 'includes/render/class-print-document-renderer.php';
		}
// JPRM_PRO_END:print-runtime
		self::$runtime_loaded = true;
	}

	public static function boot_admin() : void {
		if ( ! is_admin() || self::$admin_loaded ) { return; }
// JPRM_PRO_BEGIN:pro-admin-loader
		if ( jprm_fs()->is__premium_only() ) {
			if ( ! Module_Access::allows( 'import_export' ) && ! Module_Access::allows( 'print_pdf' ) ) { return; }
			self::load_runtime();
			if ( Module_Access::allows( 'import_export' ) ) {
				require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-import-export.php';
				\JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export::bootstrap();
			}
			if ( Module_Access::allows( 'print_pdf' ) ) {
				require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-print-document.php';
				\JelloPoint\RestaurantMenu\Admin\Print_Document_Admin::init();
			}
		}
// JPRM_PRO_END:pro-admin-loader
		self::$admin_loaded = true;
	}
}
