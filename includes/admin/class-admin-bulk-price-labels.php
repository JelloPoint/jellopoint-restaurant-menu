<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels tool.
 *
 * First step: register a Tools → Bulk Price Labels screen under the
 * core WordPress "Tools" menu. The actual bulk-assignment UI will be
 * added in a later iteration.
 */
final class JPRM_Admin_Bulk_Price_Labels {

    /** Submenu slug for this page. */
    private const PAGE_SLUG  = 'jprm-bulk-price-labels';

    /** Capability — keep it in line with the rest of the plugin tools. */
    private const CAPABILITY = 'edit_posts';

    /**
     * Bootstrap hooks — call once from the plugin loader (admin only).
     */
    public static function bootstrap(): void {
        if ( ! is_admin() ) {
            return;
        }

        // Register late, after core menus are ready.
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );

        // Assets only on our own screen.
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Register the Tools → Bulk Price Labels screen.
     *
     * We attach to the core Tools menu (tools.php) so the user gets a
     * dedicated "Tools" section where we can later also place the importer.
     */
    public static function register_menu(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        // Core Tools menu slug is "tools.php".
        add_submenu_page(
            'tools.php',
            __( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ),
            __( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Enqueue minimal styling for our screen only (optional, very light).
     */
    public static function enqueue_assets( string $hook_suffix ): void {
        // Our screen id is usually "tools_page_{PAGE_SLUG}".
        $expected = 'tools_page_' . self::PAGE_SLUG;
        if ( $hook_suffix !== $expected ) {
            return;
        }

        // Keep it extremely small and self-contained for now.
        $handle = 'jprm-bulk-price-labels-admin';
        $css    = '
            .jprm-bulk-price-labels-wrap .jprm-intro {
                max-width: 800px;
            }
            .jprm-bulk-price-labels-wrap .jprm-intro p {
                margin: 0 0 0.75em;
            }
            .jprm-bulk-price-labels-wrap .jprm-coming-soon {
                margin-top: 1.5em;
                padding: 1em 1.25em;
                border-radius: 4px;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
            }
        ';

        wp_register_style( $handle, false, [], '1.0.0' );
        wp_enqueue_style( $handle );
        wp_add_inline_style( $handle, $css );
    }

    /**
     * Render the admin page shell.
     *
     * For now this is just a descriptive skeleton; the actual bulk
     * selection + apply logic will be wired in a follow-up step.
     */
    public static function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
        }

        ?>
        <div class="wrap jprm-bulk-price-labels-wrap">
            <h1><?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?></h1>

            <div class="jprm-intro">
                <p>
                    <?php esc_html_e(
                        'This tool will let you assign and update price labels in bulk across many menu items at once.',
                        'jellopoint-restaurant-menu'
                    ); ?>
                </p>
                <p>
                    <?php esc_html_e(
                        'The idea is to list all price rows (including multiple prices per item), allow you to filter and select them, and then apply one or more labels in a single action.',
                        'jellopoint-restaurant-menu'
                    ); ?>
                </p>
            </div>

            <div class="jprm-coming-soon">
                <p><strong><?php esc_html_e( 'Bulk editor shell is ready.', 'jellopoint-restaurant-menu' ); ?></strong></p>
                <p>
                    <?php esc_html_e(
                        'The menu entry and page skeleton are in place. In the next step we will wire up the actual listing table, filters, and mass-update actions.',
                        'jellopoint-restaurant-menu'
                    ); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
