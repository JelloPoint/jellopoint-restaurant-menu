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
 * CPT fallback (ONLY post type, since your taxonomies already exist)
 * ------------------------------------------------- */
function jprm_register_cpt_fallback() {
    if ( post_type_exists( 'jprm_item' ) ) {
        return;
    }
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
add_action( 'init', 'jprm_register_cpt_fallback', 3 ); // early: ensure available before queries

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

/* ===========================================================
 * JPRM – FORENSICS LOGGER (TEMPORARY)
 * =========================================================== */
add_action('wp', function () {

    // 0) List all public post types
    $pts = get_post_types([], 'objects');
    $pt_names = array_keys($pts);
    error_log('[JPRM FORENSICS] Registered post types: ' . implode(', ', $pt_names));

    // 1) Latest 15 posts of ANY type – ID, type, title
    $q_latest = new WP_Query([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => 15,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);
    error_log('[JPRM FORENSICS] Latest any-type posts found=' . intval($q_latest->found_posts));
    if ( $q_latest->have_posts() ) {
        while ( $q_latest->have_posts() ) { $q_latest->the_post();
            $pid  = get_the_ID();
            $type = get_post_type( $pid );
            error_log('[JPRM FORENSICS] Post ID=' . $pid . ' type=' . $type . ' title="' . get_the_title() . '"');
        }
        wp_reset_postdata();
    }

    // 2) For those latest posts, dump meta keys that look like price/label
    if ( $q_latest->posts ) {
        foreach ( $q_latest->posts as $p ) {
            $pid  = $p->ID;
            $type = get_post_type( $pid );
            $all  = get_post_meta( $pid );
            $hits = [];
            foreach ( $all as $k => $v ) {
                if (
                    stripos($k,'jprm') !== false ||
                    stripos($k,'price') !== false ||
                    stripos($k,'label') !== false ||
                    stripos($k,'amount') !== false
                ) {
                    $val = isset($v[0]) ? (is_scalar($v[0]) ? (string)$v[0] : json_encode($v[0])) : '';
                    $len = strlen($val);
                    $hits[] = $k . ($len ? ' (len='. $len .')' : '');
                }
            }
            if ( $hits ) {
                error_log('[JPRM FORENSICS] Meta hits for ID=' . $pid . ' (' . $type . '): ' . implode(', ', $hits));
            }
        }
    }

    // 3) Super-broad meta probe (legacy keys included)
    $meta_keys = [
        'jprm_price','jprm_price_rows','single_price',
        '_jprm_price','_jprm_price_amounts','_jprm_price_labels',
        'jprm_prices','prices','jprm_price_v2'
    ];
    $meta_or = array_map(function($k){ return [ 'key' => $k, 'compare' => 'EXISTS' ]; }, $meta_keys);

    $q_meta = new WP_Query([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => 10,
        'no_found_rows'  => true,
        'meta_query'     => array_merge([ 'relation' => 'OR' ], $meta_or),
    ]);
    error_log('[JPRM FORENSICS] Broad ANY+legacy-meta probe found=' . intval($q_meta->found_posts));
    if ( $q_meta->have_posts() ) {
        while ( $q_meta->have_posts() ) { $q_meta->the_post();
            error_log('[JPRM FORENSICS] ANY+legacy-meta sample => ID=' . get_the_ID() . ' type=' . get_post_type() . ' title="' . get_the_title() . '"');
        }
        wp_reset_postdata();
    }

    // 4) Do we have any posts attached to our taxonomies at all?
    $q_tax = new WP_Query([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => 10,
        'no_found_rows'  => true,
        'tax_query'      => [
            'relation' => 'OR',
            [ 'taxonomy' => 'jprm_menu',    'field' => 'slug', 'terms' => get_terms([ 'taxonomy'=>'jprm_menu', 'fields'=>'slugs','hide_empty'=>false ]) ],
            [ 'taxonomy' => 'jprm_section', 'field' => 'slug', 'terms' => get_terms([ 'taxonomy'=>'jprm_section','fields'=>'slugs','hide_empty'=>false ]) ],
        ],
    ]);
    error_log('[JPRM FORENSICS] Any posts attached to jprm_menu/section? ' . intval($q_tax->found_posts));
    if ( $q_tax->have_posts() ) {
        while ( $q_tax->have_posts() ) { $q_tax->the_post();
            error_log('[JPRM FORENSICS] Tax-attached sample => ID=' . get_the_ID() . ' type=' . get_post_type() . ' title="' . get_the_title() . '"');
        }
        wp_reset_postdata();
    }
}, 20);
