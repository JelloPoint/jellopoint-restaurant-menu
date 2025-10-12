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
    // Use your real parent menu slug
    $default_parent = 'jellopoint';
    $parent_slug    = apply_filters( 'jprm/admin/menu_builder_parent', $default_parent );

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
    if ( strpos( (string) $hook, self::SLUG ) === false ) return;
    $asset_url = plugin_dir_url( dirname( __FILE__, 2 ) ) . 'includes/admin/assets/';
    wp_enqueue_style( 'jprm-menu-builder', $asset_url . 'jprm-menu-builder.css', [], '1.1.0' );
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script( 'jprm-menu-builder', $asset_url . 'jprm-menu-builder.js', [ 'jquery', 'jquery-ui-sortable' ], '1.1.0', true );
    wp_localize_script( 'jprm-menu-builder', 'JPRM_MENU_BUILDER', [
        'root'  => esc_url_raw( rest_url( 'jprm/v1' ) ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ] );
}

    public function render() : void {
        require __DIR__ . '/views/jprm-menu-builder.php';
    }
}
