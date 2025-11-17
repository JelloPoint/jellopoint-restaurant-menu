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

        // === Resequence handler (admin-post.php) =========================
        // Handles POSTs from the "Resequence all items" form in the builder view
        add_action( 'admin_post_jprm_resequence_items', [ __CLASS__, 'handle_resequence_items' ] );

        // Optional: show a success notice after resequencing
        add_action( 'admin_notices', [ __CLASS__, 'maybe_notice' ] );
    }

    /** Add the Menu Builder submenu under the existing parent */
    public static function register_page() : void {
        add_submenu_page(
            self::PARENT_SLUG,                          // parent: Jellopoint (already exists)
            __( 'Menu Builder', 'jprm' ),
            __( 'Menu Builder (beta)', 'jprm' ),
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

    /* ================================================================
     * Resequence handler + notice + worker
     * ================================================================ */

    /** Handle POST from the "Resequence all items" form */
    public static function handle_resequence_items() : void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'jprm' ) );
        }

        // Nonce
        if ( empty( $_POST['_jprm_resort_nonce'] ) || ! wp_verify_nonce( $_POST['_jprm_resort_nonce'], 'jprm_resequence_items' ) ) { // phpcs:ignore
            wp_die( esc_html__( 'Security check failed', 'jprm' ) );
        }

        $menu_id   = isset( $_POST['menu_id'] ) ? (int) $_POST['menu_id'] : 0; // phpcs:ignore
        $direction = isset( $_POST['direction'] ) ? strtoupper( (string) $_POST['direction'] ) : 'ASC'; // phpcs:ignore
        $direction = ( $direction === 'DESC' ) ? 'DESC' : 'ASC';

        // Redirect URL back to builder
        $redir = add_query_arg(
            [ 'page' => self::SLUG, 'jprm-resort' => '0' ],
            admin_url( 'admin.php' )
        );

        if ( $menu_id <= 0 ) {
            wp_safe_redirect( $redir );
            exit;
        }

        self::resequence_all_items_for_menu( $menu_id, $direction );

        $redir = add_query_arg(
            [ 'page' => self::SLUG, 'jprm-resort' => '1', 'dir' => $direction, 'mid' => $menu_id ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redir );
        exit;
    }

    /** Optional success notice on the builder page */
    public static function maybe_notice() : void {
        if ( empty( $_GET['page'] ) || $_GET['page'] !== self::SLUG ) return; // phpcs:ignore
        if ( empty( $_GET['jprm-resort'] ) ) return; // phpcs:ignore

        $ok  = ( $_GET['jprm-resort'] === '1' ); // phpcs:ignore
        $dir = ! empty( $_GET['dir'] ) ? sanitize_text_field( $_GET['dir'] ) : 'ASC'; // phpcs:ignore

        if ( $ok ) {
            echo '<div class="notice notice-success is-dismissible"><p>'
               . esc_html__( 'All items resequenced successfully', 'jprm' )
               . ' (' . esc_html( $dir ) . ')</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>'
               . esc_html__( 'Resequencing failed or no Menu selected.', 'jprm' )
               . '</p></div>';
        }
    }

    /**
     * Worker: resequence all items in all sections that belong to a Menu.
     * Rewrites menu_order within each section, ordered by Title ASC/DESC.
     */
    private static function resequence_all_items_for_menu( int $menu_id, string $direction = 'ASC' ) : void {
        // 1) Get the sections that are owned by this Menu (builder sets this meta)
        $sections = get_terms( [
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'meta_query' => [
                [ 'key' => '_jprm_menu_term_id', 'value' => (string) $menu_id ],
            ],
            'fields'     => 'ids',
        ] );
        if ( is_wp_error( $sections ) || empty( $sections ) ) return;

        $direction = ( $direction === 'DESC' ) ? 'DESC' : 'ASC';

        foreach ( $sections as $section_id ) {
            // 2) Pull all items in this section ordered by Title so the result is predictable
            $q = new \WP_Query( [
                'post_type'        => 'jprm_menu_item',
                'post_status'      => 'any',
                'posts_per_page'   => -1,
                'no_found_rows'    => true,
                'suppress_filters' => false,
                'orderby'          => 'title',
                'order'            => $direction,
                'tax_query'        => [[
                    'taxonomy' => 'jprm_section',
                    'field'    => 'term_id',
                    'terms'    => (int) $section_id,
                ]],
            ] );

            $posts = is_array( $q->posts ?? null ) ? $q->posts : [];
            if ( empty( $posts ) ) continue;

            // 3) Re-sequence menu_order → 10,20,30…
            $order_val = 10;
            foreach ( $posts as $p ) {
                wp_update_post( [
                    'ID'         => (int) $p->ID,
                    'menu_order' => $order_val,
                ] );
                $order_val += 10;
            }
            wp_reset_postdata();
        }
    }
}
