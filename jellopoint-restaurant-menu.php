<?php
/**
 * Plugin Name:       JelloPoint – Restaurant Menu
 * Plugin URI:        https://jellopoint.com/
 * Description:       Create and display restaurant menus with sections, flexible prices, dietary labels, and an Elementor widget.
 * Version:           2.0.53
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            JelloPoint
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       jellopoint-restaurant-menu
 * Domain Path:       /languages
 *
 * @fs_premium_only /assets/admin/import-export.css, /assets/admin/import-export.js, /assets/admin/print-document.css, /assets/admin/print-document.js, /assets/css/print-document.css, /includes/admin/class-admin-import-export.php, /includes/admin/class-admin-print-document.php, /includes/admin/views/import-export-page.php, /includes/data/class-importer.php, /includes/data/class-exporter.php, /includes/data/class-demo-menu.php, /includes/data/class-print-document-settings.php, /includes/data/class-print-document-builder.php, /includes/render/class-print-document-renderer.php, /includes/render/print/document.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The repository is the Pro edition. tools/build-packages.php creates Free.
// Follow the SDK's parallel-activation wrapper: never bootstrap two editions.
if ( function_exists( 'jprm_fs' ) ) {
	jprm_fs()->set_basename( true, __FILE__ );
} else {
if ( ! function_exists( 'jprm_fs' ) ) {
	function jprm_fs() {
		global $jprm_fs;
		if ( ! isset( $jprm_fs ) ) {
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
			$jprm_fs = fs_dynamic_init( array(
				'id' => '39068',
				'slug' => 'jellopoint-restaurant-menu',
				'premium_slug' => 'jellopoint-restaurant-menu-premium',
				'type' => 'plugin',
				'public_key' => 'pk_ab73490b9e9945178da3b98c1e82c',
				'is_premium' => true,
				'premium_suffix' => '',
				'has_premium_version' => true,
				'has_addons' => false,
				'has_paid_plans' => true,
				'is_org_compliant' => true,
				'wp_org_gatekeeper' => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu' => array( 'slug' => 'jellopoint', 'support' => false ),
			) );
		}
		return $jprm_fs;
	}
	jprm_fs();
	// Show the full annual amount on the Upgrade page.
	jprm_fs()->add_filter( 'pricing/show_annual_in_monthly', '__return_false' );
	do_action( 'jprm_fs_loaded' );
}

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) ) {
	define( 'JPRM_VERSION', '2.0.53' );
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

// Freemius owns the WordPress uninstall hook; JelloPoint runs afterwards.
require_once JPRM_PLUGIN_PATH . 'includes/class-uninstaller.php';
\JelloPoint\RestaurantMenu\Uninstaller::register( jprm_fs() );

/* -------------------------------------------------
 * Includes (explicit, fixed paths)
 * ------------------------------------------------- */

/** Core data / storage / render (front + admin) */

// Canonical price schema and backward-compatible data adapter.
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-default-data.php';
JPRM_Default_Data::init();
require_once JPRM_PLUGIN_PATH . 'includes/data/class-menu-structure-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-item-assignments.php';
\JelloPoint\RestaurantMenu\Data\Item_Assignments::init();
require_once JPRM_PLUGIN_PATH . 'includes/data/class-info-block-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php';

// Storage
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

// Renderer
require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

/** Thin helper wrappers (provide stable global functions for the widget) */
require_once JPRM_PLUGIN_PATH . 'includes/helpers/prices.php';

// Central module boundary for the combined development distribution.
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-catalog.php';
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-access.php';
require_once JPRM_PLUGIN_PATH . 'includes/modules/class-module-loader.php';
\JelloPoint\RestaurantMenu\Modules\Module_Loader::load_runtime();

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
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-item-placement-sync.php';
	\JelloPoint\RestaurantMenu\Admin\Item_Placement_Sync::init();
	add_action( 'admin_notices', [ \JelloPoint\RestaurantMenu\Admin\Item_Placement_Sync::class, 'notice' ] );

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

	\JelloPoint\RestaurantMenu\Modules\Module_Loader::boot_admin();
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
} // Only one edition boots per request.
