<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin screen for Menu Builder: attaches submenu under the existing Jellopoint
 * top-level menu, enqueues cache-busted assets, and renders the builder view.
 *
 * Also exposes a one-click "Resequence items (ASC/DESC)" handler that rewrites
 * menu_order for ALL items in ALL sections that belong to the chosen Menu.
 */
class Menu_Builder {

    const SLUG        = 'jprm-menu-builder';
    const PARENT_SLUG = 'jellopoint'; // parent menu you already use
    const ACTION_RESEQ = 'jprm_resequence_items';
    const NONCE_RESEQ  = 'jprm_resequence_items_nonce';

    /** Back-compat entrypoint expected by your main plugin */
    public static function hooks() : void { self::init(); }

    /** Register hooks */
    public static function init() : void {
        // Page + assets
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 20 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Admin-post handler for resequencing
        add_action( 'admin_post_' . self::ACTION_RESEQ, [ __CLASS__, 'handle_resequence' ] );
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
        // Admin notice (result of resequencing)
        if ( isset( $_GET['jprm_reseq_done'] ) ) { // phpcs:ignore
            $msg  = isset($_GET['msg']) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ?: __( 'Items resequenced.', 'jprm' ) ) . '</p></div>';
        }
        if ( isset( $_GET['jprm_reseq_err'] ) ) { // phpcs:ignore
            $msg  = isset($_GET['msg']) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ?: __( 'Resequence failed.', 'jprm' ) ) . '</p></div>';
        }

        $view = trailingslashit( JPRM_PLUGIN_PATH ) . 'includes/admin/views/jprm-menu-builder.php';
        if ( file_exists( $view ) ) { require $view; return; }

        echo '<div class="wrap"><h1>' . esc_html__( 'Menu Builder', 'jprm' ) . '</h1>';
        echo '<p>' . esc_html__( 'View file not found at includes/admin/views/jprm-menu-builder.php', 'jprm' ) . '</p></div>';
    }

    /**
     * Admin handler: resequence all items (write menu_order) across ALL sections
     * that belong to the provided Menu term ID.
     *
     * POST fields:
     * - _wpnonce = self::NONCE_RESEQ
     * - menu_id  = int (required)
     * - dir      = 'ASC'|'DESC'
     */
    public static function handle_resequence() : void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jprm' ) );
        }

        check_admin_referer( self::NONCE_RESEQ );

        $menu_id = isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : 0; // phpcs:ignore
        $dir     = isset($_POST['dir']) ? (string) $_POST['dir'] : 'ASC';  // phpcs:ignore
        $dir     = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $redirect = admin_url( 'admin.php?page=' . self::SLUG );
        if ( $menu_id <= 0 ) {
            wp_safe_redirect( add_query_arg( ['jprm_reseq_err'=>1,'msg'=>rawurlencode(__('Missing menu id','jprm')) ], $redirect ) );
            exit;
        }

        // 1) Find all sections that belong to this Menu
        $sections = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'   => '_jprm_menu_term_id',
                    'value' => (string) $menu_id,
                ],
            ],
            'fields' => 'ids',
        ]);
        if ( is_wp_error($sections) ) {
            wp_safe_redirect( add_query_arg( ['jprm_reseq_err'=>1,'msg'=>rawurlencode($sections->get_error_message()) ], $redirect ) );
            exit;
        }

        $updated = 0;

        // 2) For each section, fetch items in current order, then rewrite menu_order
        foreach ( (array) $sections as $sid ) {
            // Fetch by current menu_order ASC so we have a baseline sequence to flip if needed
            $q = new \WP_Query([
                'post_type'      => 'jprm_menu_item',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'tax_query'      => [
                    [
                        'taxonomy' => 'jprm_section',
                        'field'    => 'term_id',
                        'terms'    => (int) $sid,
                    ]
                ],
                'no_found_rows'  => true,
            ]);

            $ids = is_array($q->posts ?? null) ? $q->posts : [];
            if ( empty($ids) ) { continue; }

            if ( $dir === 'DESC' ) {
                $ids = array_reverse( $ids );
            }

            // Re-write menu_order as 10,20,30,... (gap helps future inserts)
            $n = 0;
            foreach ( $ids as $pid ) {
                $n += 10;
                // Only update if needed to keep writes minimal
                $current = get_post_field( 'menu_order', $pid );
                if ( (int) $current !== $n ) {
                    wp_update_post([
                        'ID'         => (int) $pid,
                        'menu_order' => $n,
                    ]);
                    $updated++;
                }
            }
        }

        $msg = sprintf(
            /* translators: %d = number of items updated */
            _n( 'Resequenced %d item.', 'Resequenced %d items.', $updated, 'jprm' ),
            $updated
        );

        wp_safe_redirect( add_query_arg( ['jprm_reseq_done'=>1,'msg'=>rawurlencode($msg)], $redirect ) );
        exit;
    }
}
