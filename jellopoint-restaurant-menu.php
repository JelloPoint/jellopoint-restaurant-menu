<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Restaurant Menu items, labels and Elementor widget.
 * Version: 2.0.6
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) )      define( 'JPRM_VERSION', '2.0.6' );
if ( ! defined( 'JPRM_PLUGIN_FILE' ) )  define( 'JPRM_PLUGIN_FILE', __FILE__ );
if ( ! defined( 'JPRM_PLUGIN_PATH' ) )  define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_URL' ) )   define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------
 * Includes (explicit, fixed paths)
 * ------------------------------------------------- */

// Storage layer
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

// Data / Admin
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';

// Renderer (optional but present in your repo)
require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

// Debug (admin-only shortcode)
//require_once JPRM_PLUGIN_PATH . 'includes/debug/class-inspector.php';

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
add_action( 'init', 'jprm_register_assets', 5 );

// Ensure CSS is visible in Elementor editor preview as well
add_action( 'elementor/editor/after_enqueue_styles', function() {
    if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
        wp_register_style( 'jprm-menu', JPRM_PLUGIN_URL . 'includes/render/css/menu.css', [], JPRM_VERSION );
    }
    wp_enqueue_style( 'jprm-menu' );
}, 10 );

/* -------------------------------------------------
 * CPT fallback (post type only) – nest under JelloPoint root
 * ------------------------------------------------- */
function jprm_register_cpt_fallback() {
    if ( post_type_exists( 'jprm_item' ) ) {
        return;
    }
    // Make sure the parent menu slug matches your real top-level JelloPoint menu.
    // Commonly it's 'jellopoint' if you used add_menu_page('JelloPoint', ..., 'jellopoint', ...).
    $parent_menu_slug = 'jellopoint';

    register_post_type( 'jprm_item', [
        'label'               => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
        'labels'              => [
            'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
            'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
            'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
        ],
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => $parent_menu_slug, // ← nest under JelloPoint, not as its own root
        'show_in_rest'        => true,
        'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
        'has_archive'         => false,
        'rewrite'             => [ 'slug' => 'menu-item' ],
    ] );
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
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
    if ( method_exists( $elements_manager, 'add_category' ) ) {
        $elements_manager->add_category(
            'jellopoint-widgets',
            [
                'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }
}, 10 );

// Widget registration (require_once — never print file contents)
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
    $widget_file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';

    if ( ! file_exists( $widget_file ) ) {
        error_log('[JPRM] Widget file missing: ' . $widget_file);
        return;
    }

    require_once $widget_file;

    if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
        $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
    } else {
        error_log('[JPRM] Widget class not found after require_once.');
    }
}, 10 );

/* -------------------------------------------------
 * Optional: let your core plugin bootstrap itself (no private constructor calls)
 * ------------------------------------------------- */
if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) ) {
    if ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'instance' ] ) ) {
        \JelloPoint\RestaurantMenu\Plugin::instance();
    } elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'get_instance' ] ) ) {
        \JelloPoint\RestaurantMenu\Plugin::get_instance();
    }
}
