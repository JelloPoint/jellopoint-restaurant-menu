<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin screen for Menu Builder: adds submenu, enqueues assets with cache-busting,
 * and outputs the builder view.
 *
 * Class name and methods are aligned with the plugin bootstrap:
 * - static hooks()  -> calls init()  (to satisfy older bootstraps)
 * - static init()   -> attaches WP hooks
 */
class Menu_Builder {

    const SLUG = 'jprm-menu-builder';

    /** Back-compat entrypoint expected by your main plugin */
    public static function hooks() : void {
        self::init();
    }

    /** Actual hook registrations */
    public static function init() : void {
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function register_page() : void {
        /**
         * Adjust $parent_slug if your plugin uses a different parent menu.
         * Common options:
         * - 'jprm' (custom top-level menu slug you created)
         * - 'edit.php?post_type=jprm_menu_item' (anchor under the CPT)
         */
        $parent_slug = 'jprm';

        add_submenu_page(
            $parent_slug,
            __( 'Menu Builder', 'jprm' ),
            __( 'Menu Builder (beta)', 'jprm' ),
            'edit_posts',
            self::SLUG,
            [ __CLASS__, 'render' ],
            30
        );
    }

    public static function enqueue( string $hook ) : void {
        // Only load assets on our screen
        if ( empty( $_GET['page'] ) || $_GET['page'] !== self::SLUG ) return;

        // Paths
        $js_rel  = 'includes/admin/assets/jprm-menu-builder.js';
        $css_rel = 'includes/admin/assets/jprm-menu-builder.css';

        $js_path  = trailingslashit( JPRM_PLUGIN_PATH ) . $js_rel;
        $css_path = trailingslashit( JPRM_PLUGIN_PATH ) . $css_rel;

        $js_url  = trailingslashit( JPRM_PLUGIN_URL )  . $js_rel;
        $css_url = trailingslashit( JPRM_PLUGIN_URL )  . $css_rel;

        // Core dependency
        wp_enqueue_script( 'jquery-ui-sortable' );

        // Cache-busted enqueue so updates are always loaded
        wp_enqueue_script(
            'jprm-menu-builder',
            $js_url,
            [ 'jquery', 'jquery-ui-sortable' ],
            @filemtime( $js_path ) ?: time(),
            true
        );

        // Localize REST info + diagnostics toggle
        wp_localize_script( 'jprm-menu-builder', 'JPRM_MENU_BUILDER', [
            'root'  => esc_url_raw( rest_url( 'jprm/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'debug' => true, // set to false to hide diagnostics bar
        ] );

        wp_enqueue_style(
            'jprm-menu-builder',
            $css_url,
            [],
            @filemtime( $css_path ) ?: time()
        );
    }

    public static function render() : void {
        $view = trailingslashit( JPRM_PLUGIN_PATH ) . 'includes/admin/views/jprm-menu-builder.php';
        if ( file_exists( $view ) ) {
            require $view;
            return;
        }

        // Minimal fallback if view missing
        echo '<div class="wrap"><h1>' . esc_html__( 'Menu Builder', 'jprm' ) . '</h1>';
        echo '<p>' . esc_html__( 'View file not found at includes/admin/views/jprm-menu-builder.php', 'jprm' ) . '</p></div>';
    }
}
