<?php
/**
 * Plugin Name:       JelloPoint – Restaurant Menu
 * Plugin URI:        https://github.com/JelloPoint/jellopoint-restaurant-menu
 * Description:       Create and display restaurant menus with sections, flexible prices, dietary labels, and an Elementor widget.
 * Version:           2.0.23
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            JelloPoint
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) ) {
	define( 'JPRM_VERSION', '2.0.23' );
}
if ( ! defined( 'JPRM_PLUGIN_FILE' ) ) {
	define( 'JPRM_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'JPRM_PLUGIN_PATH' ) ) {
	define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'JPRM_PLUGIN_URL' ) ) {
	define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/* -------------------------------------------------
 * Includes (explicit, fixed paths)
 * ------------------------------------------------- */

/** Core data / storage / render (front + admin) */

// Canonical price schema and backward-compatible data adapter.
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-default-data.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-menu-structure-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-info-block-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-settings.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-print-document-builder.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/render/class-print-document-renderer.php';

// Storage
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

// Renderer
require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

/** Thin helper wrappers (provide stable global functions for the widget) */
require_once JPRM_PLUGIN_PATH . 'includes/helpers/prices.php';

/** Plugin core */
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';

/** REST endpoints (load regardless of admin to keep endpoints available) */
require_once JPRM_PLUGIN_PATH . 'includes/rest/class-jprm-menu-builder-controller.php';

/* -------------------------------------------------
 * Admin-only includes
 * ------------------------------------------------- */
if ( is_admin() ) {
	// Meta boxes, admin UI
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
	require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menu.php';               // admin menu bootstrap
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-settings.php';
	require_once JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php';

	// Menu Builder (admin UI shell)
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menu-builder.php';
	\JelloPoint\RestaurantMenu\Admin\Menu_Builder::init();

	// Admin: Items list table enhancements
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menu-item-list.php';
	\JelloPoint\RestaurantMenu\Admin\Menu_Item_List::init();

	// Sections admin polish (Menu column, filter, owner select + cascade)
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-sections-admin.php';
	\JelloPoint\RestaurantMenu\Admin\Sections_Admin::init();

	// Menus admin
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menus-admin.php';
	\JelloPoint\RestaurantMenu\Admin\Menus_Admin::init();

	// Sections UX helpers
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-sections-ux.php';
	\JelloPoint\RestaurantMenu\Admin\Sections_UX::init();

    // includes/admin/class-admin-bulk-price-labels.php
    require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-bulk-price-labels.php';
    \JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Bulk_Price_Labels::bootstrap();

    // includes/admin/class-admin-import-export.php
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-import-export.php';
    \JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export::bootstrap();

	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-print-document.php';
	\JelloPoint\RestaurantMenu\Admin\Print_Document_Admin::init();
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-info-blocks.php';
	\JelloPoint\RestaurantMenu\Admin\Info_Blocks_Admin::init();

	\JelloPoint\RestaurantMenu\Admin\Settings::init();
	\JelloPoint\RestaurantMenu\Admin\Admin_Menu::init();
}

\JelloPoint\RestaurantMenu\Plugin::init();

add_action(
	'rest_api_init',
	static function (): void {
		$controller = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
		$controller->register_routes();
	}
);

/** Register canonical rewrite rules before flushing them on activation. */
function jprm_activate(): void {
	\JelloPoint\RestaurantMenu\Plugin::register_types();
	\JelloPoint\RestaurantMenu\Plugin::register_taxonomies();
	JPRM_Default_Data::install_missing();
	flush_rewrite_rules();
}

/** Flush rewrite rules after deactivation. */
function jprm_deactivate(): void {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'jprm_activate' );
register_deactivation_hook( __FILE__, 'jprm_deactivate' );
