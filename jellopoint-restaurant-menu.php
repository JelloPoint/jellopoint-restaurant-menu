<?php
/**
 * Plugin Name: JelloPoint Restaurant Menu
 * Description: Restaurant menu items with dynamic prices, labels, and Elementor widget.
 * Version: 2.0.1
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined('ABSPATH') ) { exit; }

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if ( ! defined('JPRM_VERSION') ) {
    define('JPRM_VERSION', '2.0.1');
}
if ( ! defined('JPRM_PLUGIN_FILE') ) {
    define('JPRM_PLUGIN_FILE', __FILE__);
}
if ( ! defined('JPRM_PLUGIN_PATH') ) {
    define('JPRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if ( ! defined('JPRM_PLUGIN_URL') ) {
    define('JPRM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// -----------------------------------------------------------------------------
// i18n
// -----------------------------------------------------------------------------
add_action('plugins_loaded', function(){
    load_plugin_textdomain('jellopoint-restaurant-menu', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// -----------------------------------------------------------------------------
// Core includes (order matters: storage -> data -> admin -> render -> widget)
// -----------------------------------------------------------------------------
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';

require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';

require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

// -----------------------------------------------------------------------------
// Styles
// -----------------------------------------------------------------------------
add_action('init', function(){
    // Register frontend/editor style once; Elementor widget declares dependency.
    wp_register_style(
        'jprm-menu',
        JPRM_PLUGIN_URL . 'includes/render/css/menu.css',
        [],
        JPRM_VERSION
    );
});

// -----------------------------------------------------------------------------
// Elementor integration
// -----------------------------------------------------------------------------
/**
 * Check Elementor and register widget + category
 */
add_action('plugins_loaded', function(){

    // If Elementor not loaded yet, add an admin notice
    if ( ! did_action('elementor/loaded') ) {
        add_action('admin_notices', function(){
            if ( current_user_can('activate_plugins') ) {
                echo '<div class="notice notice-warning"><p>';
                echo esc_html__('JelloPoint Restaurant Menu: Elementor is not active. The widget will be unavailable until Elementor is activated.', 'jellopoint-restaurant-menu');
                echo '</p></div>';
            }
        });
        return;
    }

    // Register category
    add_action('elementor/elements/categories_registered', function( $elements_manager ){
        $elements_manager->add_category(
            'jellopoint-widgets',
            [
                'title' => __('JelloPoint', 'jellopoint-restaurant-menu'),
                'icon'  => 'fa fa-plug',
            ]
        );
    });

    // Register widget
    add_action('elementor/widgets/register', function( $widgets_manager ){
        require_once JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';
        $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
    });

}, 20);
