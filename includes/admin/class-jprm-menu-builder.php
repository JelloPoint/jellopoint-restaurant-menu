<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin screen for Menu Builder: attaches submenu under the existing Jellopoint
 * top-level menu, enqueues cache-busted assets, and renders the builder view.
 *
 * Back-compat: main plugin may call Menu_Builder::hooks(), which aliases to init().
 */
class Menu_Builder {

    const SLUG        = 'jprm-menu-builder';
    const PARENT_SLUG = 'jellopoint'; // parent menu you already use

    /** Back-compat entrypoint expected by your main plugin */
    public static function hooks() : void { self::init(); }

    /** Register hooks */
    public static function init() : void {
        // Only register our submenu — DO NOT create a new top-level
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 20 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    /** Add the Menu Builder submenu under the existing parent */
    public static function register_page() : void {
        add_submenu_page(
            self::PARENT_SLUG,                          // parent: Jellopoint (already exists)
            __( 'Menu Builder', 'jprm' ),
            __( 'Menu Builder', 'jprm' ),
            'edit_posts',
            self::SLUG,
            [ __CLASS__, 'render' ],
            30
        );
    }

    /** Enqueue cache-busted assets + localized vars used by the UI */
    public static function enqueue( string $hook ) : void {
        if ( empty( $_GET['page'] ) || $_GET['page'] !== self::SLUG ) return; // phpcs:ignore

        $js_rel  = 'includes/admin/assets/jprm-menu-builder.js';
        $css_rel = 'includes/admin/assets/jprm-menu-builder.css';

        $js_path  = trailingslashit( JPRM_PLUGIN_PATH ) . $js_rel;
        $css_path = trailingslashit( JPRM_PLUGIN_PATH ) . $css_rel;

        $js_url  = trailingslashit( JPRM_PLUGIN_URL )  . $js_rel;
        $css_url = trailingslashit( JPRM_PLUGIN_URL )  . $css_rel;

        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_script(
            'jprm-menu-builder',
            $js_url,
            [ 'jquery', 'jquery-ui-sortable' ],
            @filemtime( $js_path ) ?: time(),
            true
        );

        wp_localize_script( 'jprm-menu-builder', 'JPRM_MENU_BUILDER', [
            'root'               => esc_url_raw( rest_url( 'jprm/v1' ) ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'debug'              => true, // set false to hide the diagnostics stripe
            'admin_new_item_url' => admin_url( 'post-new.php?post_type=jprm_menu_item' ),
        ] );

        wp_enqueue_style(
            'jprm-menu-builder',
            $css_url,
            [],
            @filemtime( $css_path ) ?: time()
        );
    }

    /** Render the builder view */
    public static function render() : void {
        $view = trailingslashit( JPRM_PLUGIN_PATH ) . 'includes/admin/views/jprm-menu-builder.php';
        if ( file_exists( $view ) ) { require $view; return; }

        echo '<div class="wrap"><h1>' . esc_html__( 'Menu Builder', 'jprm' ) . '</h1>';
        echo '<p>' . esc_html__( 'View file not found at includes/admin/views/jprm-menu-builder.php', 'jprm' ) . '</p></div>';
    }
}
