<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin screen for Menu Builder: always attach submenu, cache-busted assets,
 * diagnostics toggle, and a safe "Add Item" link to the post-new editor.
 *
 * Compatible with older bootstraps that call Menu_Builder::hooks().
 */
class Menu_Builder {

    const SLUG = 'jprm-menu-builder';
    const PARENT_SLUG = 'jprm'; // we will create this top-level parent if it doesn't exist

    /** Back-compat entrypoint expected by your main plugin */
    public static function hooks() : void { self::init(); }

    /** Actual hook registrations */
    public static function init() : void {
        // Make sure our parent menu exists before adding submenu
        add_action( 'admin_menu', [ __CLASS__, 'ensure_parent_menu' ], 9 );
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 10 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    /**
     * Ensure the top-level parent exists so the submenu never disappears.
     * If another class already added it, this will do nothing.
     */
    public static function ensure_parent_menu() : void {
        global $menu;
        $parent_exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $m ) {
                if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
                    $parent_exists = true;
                    break;
                }
            }
        }

        if ( $parent_exists ) return;

        // Create a light-weight parent page with a link to the builder
        add_menu_page(
            __( 'Restaurant Menus', 'jprm' ),
            __( 'Restaurant Menus', 'jprm' ),
            'edit_posts',
            self::PARENT_SLUG,
            [ __CLASS__, 'render_parent' ],
            'dashicons-clipboard',
            56
        );
    }

    /** Simple landing for the parent; points to the builder */
    public static function render_parent() : void {
        $builder_url = admin_url( 'admin.php?page=' . self::SLUG );
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Restaurant Menus', 'jprm' ) . '</h1>';
        echo '<p><a class="button button-primary" href="' . esc_url( $builder_url ) . '">' .
                esc_html__( 'Open Menu Builder', 'jprm' ) . '</a></p>';
        echo '</div>';
    }

    /** Register the Menu Builder submenu under our guaranteed parent */
    public static function register_page() : void {
        add_submenu_page(
            self::PARENT_SLUG,                               // parent (now guaranteed)
            __( 'Menu Builder', 'jprm' ),
            __( 'Menu Builder (beta)', 'jprm' ),
            'edit_posts',
            self::SLUG,
            [ __CLASS__, 'render' ],
            30
        );
    }

    /** Enqueue cache-busted assets and localize REST + admin URLs */
    public static function enqueue( string $hook ) : void {
        if ( empty( $_GET['page'] ) || $_GET['page'] !== self::SLUG ) return;

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

        // Localize REST info + diagnostics + admin URLs for "Add Item"
        wp_localize_script( 'jprm-menu-builder', 'JPRM_MENU_BUILDER', [
            'root'                => esc_url_raw( rest_url( 'jprm/v1' ) ),
            'nonce'               => wp_create_nonce( 'wp_rest' ),
            'debug'               => true, // set false to hide diagnostics
            'admin_new_item_url'  => admin_url( 'post-new.php?post_type=jprm_menu_item' ),
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
        if ( file_exists( $view ) ) {
            require $view;
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Menu Builder', 'jprm' ) . '</h1>';
        echo '<p>' . esc_html__( 'View file not found at includes/admin/views/jprm-menu-builder.php', 'jprm' ) . '</p></div>';
    }
}
