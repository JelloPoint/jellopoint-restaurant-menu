<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Restaurant Menu items, labels and Elementor widget.
 * Version: 2.0.6
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) ) {
	define( 'JPRM_VERSION', '2.0.6' );
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

// Data layer (fix: correct path was includes/data/, not storage/)
require_once JPRM_PLUGIN_PATH . 'includes/data/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';

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
	require_once JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php';

	// Menu Builder (admin UI shell)
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menu-builder.php';

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

	// Debug (admin-only tools)
	require_once JPRM_PLUGIN_PATH . 'includes/debug/class-inspector.php';

	// Optional debug addon if present
	if ( file_exists( JPRM_PLUGIN_PATH . 'includes/debug/inspector-badges.php' ) ) {
		require_once JPRM_PLUGIN_PATH . 'includes/debug/inspector-badges.php';
	}

	// includes/admin/class-admin-import-export.php
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-import-export.php';
	\JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export::bootstrap();

}

// Register routes for ALL contexts (front + admin).
add_action( 'rest_api_init', function () {
    // Sanity: if class isn’t loaded, bail early (prevents fatal).
    if ( ! class_exists( '\JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller' ) ) {
        return;
    }
    $ctl = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
    $ctl->register_routes();
}, 10 );


/* -------------------------------------------------
 * Assets
 * ------------------------------------------------- */
function jprm_register_assets() {
	wp_register_style(
		'jprm-menu',
		JPRM_PLUGIN_URL . 'includes/render/css/menu.css',
		[],
		JPRM_VERSION
	);
}
// Frontend styles for the menu widget
add_action( 'wp_enqueue_scripts', function () {
	// Register the handle the widget declares in get_style_depends()
	wp_register_style(
		'jprm-menu',
		plugins_url( 'includes/render/css/menu.css', __FILE__ ),
		[],
		'1.0'
	);
}, 5);
add_action( 'init', 'jprm_register_assets', 5 );

// Ensure CSS is visible in Elementor editor preview as well.
add_action(
	'elementor/editor/after_enqueue_styles',
	function () {
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			wp_register_style( 'jprm-menu', JPRM_PLUGIN_URL . 'includes/render/css/menu.css', [], JPRM_VERSION );
		}
		wp_enqueue_style( 'jprm-menu' );
	},
	10
);

/* -------------------------------------------------
 * CPT fallback (post type only) – nest under JelloPoint root
 * ------------------------------------------------- */
function jprm_register_cpt_fallback() {
	if ( post_type_exists( 'jprm_item' ) ) {
		return;
	}

	// This only runs as a fallback and nests under the JelloPoint parent menu.
	$parent_menu_slug = 'jellopoint';

	register_post_type(
		'jprm_item',
		[
			'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
			'labels'       => [
				'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
				'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
				'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
				'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
			],
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => $parent_menu_slug,
			'show_in_rest' => true,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => 'menu-item' ],
		]
	);
}
add_action( 'init', 'jprm_register_cpt_fallback', 3 );

// Flush rewrites when activating (helps first-time fallback)
function jprm_activate() {
	jprm_register_cpt_fallback();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'jprm_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* -------------------------------------------------
 * Elementor integration
 * ------------------------------------------------- */

// Category for our widgets
add_action(
	'elementor/elements/categories_registered',
	function ( $elements_manager ) {
		if ( method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category(
				'jellopoint-widgets',
				[
					'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
					'icon'  => 'fa fa-plug',
				]
			);
		}
	},
	10
);

// Widget registration (require_once — never print file contents)
add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		$widget_file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';

		if ( ! file_exists( $widget_file ) ) {
			error_log( '[JPRM] Widget file missing: ' . $widget_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}

		require_once $widget_file;

		if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
			$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
		} else {
			error_log( '[JPRM] Widget class not found after require_once.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	},
	10
);
	// 2) NEW: register Menu Builder hooks on admin only
add_action( 'plugins_loaded', function () {
    if ( ! is_admin() ) { return; }

    // Admin screen
    $builder = new \JelloPoint\RestaurantMenu\Admin\Menu_Builder();
    $builder->hooks(); // adds submenu via admin_menu (priority 60)

    // REST endpoints
    add_action( 'rest_api_init', function() {
        $ctl = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
        $ctl->register_routes();
    } );
}, 30 );

/* -------------------------------------------------
 * Optional: let your core plugin bootstrap itself (no private constructor calls)
 * ------------------------------------------------- */
if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) ) {
	if ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'instance' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::instance();
	} elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'get_instance' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::get_instance();
	} elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'init' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::init();
	}
}

/** 🔹 NEW: Initialize the Admin Menu (creates parent if missing, adds submenus) */
if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\Admin_Menu' ) ) {
	\JelloPoint\RestaurantMenu\Admin\Admin_Menu::init();
}
