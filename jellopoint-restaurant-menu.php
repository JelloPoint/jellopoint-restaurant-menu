<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Restaurant Menu items, labels and Elementor widget.
 * Version: 2.0.5
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) )      define( 'JPRM_VERSION', '2.0.5' );
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
/* =========================
 * JPRM TEMP DEBUG LOGGER
 * Remove after diagnosing.
 * ========================= */
if ( ! function_exists('jprm_dbg_str') ) {
    function jprm_dbg_str( $v ) {
        if ( is_bool($v) ) return $v ? 'true' : 'false';
        if ( is_null($v) ) return 'null';
        if ( is_array($v) || is_object($v) ) return wp_json_encode( $v );
        return (string) $v;
    }
}

add_action( 'init', function() {
    $cpt   = post_type_exists('jprm_item') ? 'YES' : 'NO';
    $tax_m = taxonomy_exists('jprm_menu') ? 'YES' : 'NO';
    $tax_s = taxonomy_exists('jprm_section') ? 'YES' : 'NO';
    error_log("[JPRM DEBUG] init: CPT jprm_item={$cpt}; tax jprm_menu={$tax_m}; tax jprm_section={$tax_s}");
}, 12 );

add_action( 'wp', function() {
    // 1) Broad "looks-like menu item" probe across ANY post type by meta keys
    $q_any = new WP_Query([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'no_found_rows'  => true,
        'posts_per_page' => 5,
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => 'jprm_price',      'compare' => 'EXISTS' ],
            [ 'key' => 'jprm_price_rows', 'compare' => 'EXISTS' ],
            [ 'key' => 'single_price',    'compare' => 'EXISTS' ],
        ],
    ]);
    error_log('[JPRM DEBUG] wp: ANY+meta probe found=' . intval( $q_any->found_posts ));

    if ( $q_any->have_posts() ) {
        $q_any->the_post();
        error_log('[JPRM DEBUG] wp: ANY sample => ID=' . get_the_ID() . ' post_type=' . get_post_type() . ' title="' . get_the_title() . '"');
        wp_reset_postdata();
    }

    // 2) Strict CPT probe
    $q_cpt = new WP_Query([
        'post_type'      => 'jprm_item',
        'post_status'    => 'any',
        'no_found_rows'  => true,
        'posts_per_page' => 5,
    ]);
    error_log('[JPRM DEBUG] wp: jprm_item probe found=' . intval( $q_cpt->found_posts ));

    if ( $q_cpt->have_posts() ) {
        $q_cpt->the_post();
        error_log('[JPRM DEBUG] wp: jprm_item sample => ID=' . get_the_ID() . ' title="' . get_the_title() . '"');
        wp_reset_postdata();
    }
}, 12 );

// 3) Log what the widget receives (without altering it)
add_filter( 'jprm/widget/get_items', function( $items, $settings, $widget ) {
    $ds   = isset($settings['data_source']) ? $settings['data_source'] : '(unset)';
    $menus    = isset($settings['query_menus'])    ? $settings['query_menus']    : [];
    $sections = isset($settings['query_sections']) ? $settings['query_sections'] : [];
    error_log('[JPRM DEBUG] widget hook: data_source=' . $ds . ' menus=' . jprm_dbg_str($menus) . ' sections=' . jprm_dbg_str($sections));

    // DO NOT alter behavior; let normal code run
    return null;
}, 10, 3 );

// 4) Final safety: log when the widget finds 0 items just before output
add_action( 'wp_footer', function() {
    // Only log in editor or if WP_DEBUG to avoid noisy logs
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log('[JPRM DEBUG] footer ping (page rendered).');
    }
}, 99 );
