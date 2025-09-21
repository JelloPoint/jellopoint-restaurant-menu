<?php
// Abort if called directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Admin asset loader for JelloPoint Restaurant Menu.
 * Scope-limited: only loads on jprm_menu_item edit screens and the Price Labels settings page.
 * NOTE: Kept separate from menu registration to avoid accidental regressions.
 */
add_action('admin_enqueue_scripts', function( $hook ){
    // Try to get the current screen safely
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = $screen && isset($screen->post_type) ? $screen->post_type : '';

    // Detect Price Labels admin page by slug
    $page_slug = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    $is_menu_item_editor = ( $post_type === 'jprm_menu_item' );
    $is_price_labels     = ( $page_slug === 'jprm-price-labels' );

    if ( ! $is_menu_item_editor && ! $is_price_labels ) {
        return; // do nothing elsewhere
    }

    // Core deps
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_media();

    // Register and enqueue our small admin JS (no caching issues thanks to filemtime)
    $handle = 'jprm-admin-js';
    $src    = plugins_url( 'assets/admin/jprm-admin.js', dirname(__FILE__) );
    $ver    = file_exists( __DIR__ . '/../assets/admin/jprm-admin.js' ) ? filemtime( __DIR__ . '/../assets/admin/jprm-admin.js' ) : '1.0.0';
    wp_enqueue_script( $handle, $src, [ 'jquery', 'jquery-ui-sortable', 'media-editor' ], $ver, true );

    // Small bit of context for the script to adapt behavior
    wp_localize_script( $handle, 'JPRM_ADMIN_CTX', [
        'is_menu_item_editor' => $is_menu_item_editor,
        'is_price_labels'     => $is_price_labels,
    ] );
}, 20);
