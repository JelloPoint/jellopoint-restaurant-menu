<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Restaurant Menu items, labels and Elementor widget.
 * Version: 2.0.4
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) )      define( 'JPRM_VERSION', '2.0.4' );
if ( ! defined( 'JPRM_PLUGIN_FILE' ) )  define( 'JPRM_PLUGIN_FILE', __FILE__ );
if ( ! defined( 'JPRM_PLUGIN_PATH' ) )  define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_URL' ) )   define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------
 * Includes (safe order)
 * ------------------------------------------------- */
$jprm_includes = [
    // Storage (if present)
    'includes/storage/class-price-schema.php',
    'includes/storage/class-price-repository.php',

    // Data / Admin
    'includes/data/class-labels-store.php',
    'includes/class-plugin.php',
    'includes/admin/class-admin-menuitem-meta.php',
    'includes/admin/save/class-menuitem-v3-writer.php',

    // Renderer (optional)
    'includes/render/class-price-renderer.php',
];

foreach ( $jprm_includes as $rel ) {
    $abs = JPRM_PLUGIN_PATH . $rel;
    if ( file_exists( $abs ) ) {
        require_once $abs;
    }
}

/* -------------------------------------------------
 * Assets
 * ------------------------------------------------- */
function jprm_register_assets() {
    $css = JPRM_PLUGIN_URL . 'includes/render/css/menu.css';
    wp_register_style( 'jprm-menu', $css, [], JPRM_VERSION );
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
 * CPT & Taxonomy fallback registration
 * (Only registers if missing, so we don't override your existing definitions)
 * ------------------------------------------------- */
function jprm_register_types_fallback() {
    // Post type
    if ( ! post_type_exists( 'jprm_item' ) ) {
        register_post_type( 'jprm_item', [
            'label'               => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'labels'              => [
                'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
                'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
            ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'has_archive'         => false,
            'rewrite'             => [ 'slug' => 'menu-item' ],
            'menu_position'       => 25,
        ] );
    }

    // Taxonomy: Menu
    if ( ! taxonomy_exists( 'jprm_menu' ) ) {
        register_taxonomy( 'jprm_menu', 'jprm_item', [
            'label'             => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'public'            => true,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'menu' ],
        ] );
    }

    // Taxonomy: Section
    if ( ! taxonomy_exists( 'jprm_section' ) ) {
        register_taxonomy( 'jprm_section', 'jprm_item', [
            'label'             => __( 'Sections', 'jellopoint-restaurant-menu' ),
            'public'            => true,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'menu-section' ],
        ] );
    }
}
add_action( 'init', 'jprm_register_types_fallback', 4 ); // early, before queries

// Flush rewrites when activating (in case fallbacks are used)
function jprm_activate() {
    jprm_register_types_fallback();
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
 * Optional: if your Plugin singleton exists, let it bootstrap itself.
 * (Do NOT call private constructors.)
 * ------------------------------------------------- */
if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) ) {
    if ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'instance' ] ) ) {
        \JelloPoint\RestaurantMenu\Plugin::instance();
    } elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'get_instance' ] ) ) {
        \JelloPoint\RestaurantMenu\Plugin::get_instance();
    }
}