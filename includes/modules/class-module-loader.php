<?php
namespace JelloPoint\RestaurantMenu\Modules;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bootstrap the combined development distribution; license gating follows separately. */
final class Module_Loader {
	private static $runtime_loaded = false;
	private static $admin_loaded = false;

	public static function load_runtime() : void {
		if ( self::$runtime_loaded ) { return; }
		require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-settings.php';
		require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-builder.php';
		require_once JPRM_PLUGIN_PATH . 'includes/render/class-print-document-renderer.php';
		self::$runtime_loaded = true;
	}

	public static function boot_admin() : void {
		if ( ! is_admin() || self::$admin_loaded ) { return; }
		self::load_runtime();
		require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-import-export.php';
		\JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export::bootstrap();
		require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-print-document.php';
		\JelloPoint\RestaurantMenu\Admin\Print_Document_Admin::init();
		self::$admin_loaded = true;
	}
}
