<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Menu_Builder {
    const SLUG = 'jprm-menu-builder';

    public function __construct() {}

    public function hooks() : void {
        add_action( 'admin_menu', [ $this, 'add_submenu' ], 60 ); // keep existing order; add near end
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function add_submenu() : void {
        // Parent slug: adjust if your top-level menu slug differs
        $parent_slug = 'edit.php?post_type=jprm_menu';
        add_submenu_page(
            $parent_slug,
            __( 'Menu Builder', 'jprm' ),
            __( 'Menu Builder (beta)', 'jprm' ),
            'edit_posts',
            self::SLUG,
            [ $this, 'render' ],
            99
        );
    }

    public function enqueue( $hook ) : void {
        if ( $hook !== 'restaurant-menu_page_' . self::SLUG && $hook !== 'jprm-menu_page_' . self::SLUG ) {
            // Depending on your parent slug WP builds different $hook; both guarded.
            return;
        }

        $asset_url = plugins_url( 'includes/admin/assets/', JPRM_PLUGIN_FILE ); // ensure JPRM_PLUGIN_FILE is defined in main plugin
        wp_enqueue_style( 'jprm-menu-builder', $asset_url . 'jprm-menu-builder.css', [], '1.0.0' );

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'jprm-menu-builder', $asset_url . 'jprm-menu-builder.js', [ 'jquery', 'jquery-ui-sortable' ], '1.0.0', true );

        wp_localize_script( 'jprm-menu-builder', 'JPRM_MENU_BUILDER', [
            'root'  => esc_url_raw( rest_url( 'jprm/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function render() : void {
        require __DIR__ . '/views/jprm-menu-builder.php';
    }
}
