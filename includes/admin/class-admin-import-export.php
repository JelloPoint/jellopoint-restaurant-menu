<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Import/Export — strictly under the JelloPoint parent menu.
 */
final class JPRM_Admin_Import_Export {

    private const PAGE_SLUG    = 'jprm-import-export';
    private const NONCE_ACTION = 'jprm_import_export';
    private const NONCE_FIELD  = '_jprm_ie_nonce';
    private const CAPABILITY   = 'edit_posts';

    /** CSV template specifics */
    private const CSV_DELIM_MULTI = '*'; // delimiter for Price_Multiple column
    private const CSV_FILENAME    = 'jprm-import-template.csv';

    public static function bootstrap(): void {
        if ( ! is_admin() ) { return; }

        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );

        add_action( 'admin_post_jprm_export',          [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_post_jprm_import',          [ __CLASS__, 'handle_import' ] );
        add_action( 'admin_post_jprm_download_tpl',    [ __CLASS__, 'handle_download_template' ] );

        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function register_menu(): void {
        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) {
            return;
        }
        $parent_slug = \JelloPoint\RestaurantMenu\Admin\Admin_Menu::PARENT_SLUG;

        add_submenu_page(
            $parent_slug,
            __( 'Import/Export', 'jellopoint-restaurant-menu' ),
            __( 'Import/Export', 'jellopoint-restaurant-menu' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ],
            20
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
        }

        $export_url  = admin_url( 'admin-post.php?action=jprm_export' );
        $import_url  = admin_url( 'admin-post.php?action=jprm_import' );
        $tpl_url     = admin_url( 'admin-post.php?action=jprm_download_tpl' );
        $nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

        $messages = [];
        if ( isset( $_GET['jprm_ie_msg'] ) ) {
            $messages[] = sanitize_text_field( wp_unslash( $_GET['jprm_ie_msg'] ) );
        }

        $import_report = null;
        if ( isset( $_GET['jprm_ie_report'] ) ) {
            $key = sanitize_text_field( wp_unslash( $_GET['jprm_ie_report'] ) );
            $import_report = get_transient( $key );
        }

        $delimiter_hint = self::CSV_DELIM_MULTI;

        $view = plugin_dir_path( __FILE__ ) . 'views/import-export-page.php';
        if ( file_exists( $view ) ) {
            /** @var array|null $import_report */
            include $view;
        } else {
            echo '<div class="wrap"><h1>JPRM Import/Export</h1><p>View file missing.</p></div>';
        }
    }

    public static function enqueue_assets( $hook ): void {
        if ( ! self::is_current_screen() ) { return; }

        $base = plugins_url( '/', dirname( __FILE__, 2 ) );
        $base = trailingslashit( dirname( $base ) );

        wp_enqueue_style(
            'jprm-import-export',
            $base . 'assets/admin/import-export.css',
            [],
            defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'jprm-import-export',
            $base . 'assets/admin/import-export.js',
            [ 'jquery' ],
            defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '1.0.0',
            true
        );
    }

    private static function is_current_screen(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) { return false; }
        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) { return false; }
        $parent_slug = \JelloPoint\RestaurantMenu\Admin\Admin_Menu::PARENT_SLUG;
        $screen = get_current_screen();
        return ( $screen && $screen->base === $parent_slug . '_page_' . self::PAGE_SLUG );
    }

    public static function handle_export(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $format = isset( $_POST['format'] ) && $_POST['format'] === 'csv' ? 'csv' : 'json';

        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Exporter' ) ) {
            require_once dirname( __DIR__ ) . '/data/class-exporter.php';
        }
        \JelloPoint\RestaurantMenu\Data\JPRM_Exporter::stream( [
            'format' => $format,
        ] );
        exit;
    }

    public static function handle_import(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        // Action selector (buttons set this)
        $action_type = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : 'dry_run';
        $dry_run     = ( $action_type !== 'import' );

        $create_missing_terms = ! empty( $_POST['create_missing_terms'] );

        if ( empty( $_FILES['jprm_import_file'] ) ) {
            $back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
            wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'No file uploaded.' ), $back ) );
            exit;
        }

        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Importer' ) ) {
            require_once dirname( __DIR__ ) . '/data/class-importer.php';
        }
        $report = \JelloPoint\RestaurantMenu\Data\JPRM_Importer::run(
            $_FILES['jprm_import_file'],
            [
                'dry_run'              => $dry_run,
                'create_missing_terms' => $create_missing_terms,
            ]
        );

        $key = 'jprm_ie_report_' . wp_generate_password( 8, false, false );
        set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );

        $back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        wp_safe_redirect( add_query_arg( [ 'jprm_ie_report' => $key ], $back ) );
        exit;
    }

    /**
     * Stream a tailored CSV template for import.
     * Columns:
     *  - post_id, post_title, post_status, description, menus, sections,
     *  - Price_Single,
     *  - Price_Multiple  (values separated by self::CSV_DELIM_MULTI, max 4)
     *
     * Users should create one example item (single) and one with MAX multiple prices before use.
     */
    public static function handle_download_template(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $delim = self::CSV_DELIM_MULTI;

        // Build CSV in memory
        $fh = fopen( 'php://output', 'w' );
        if ( ! $fh ) { wp_die( 'Cannot open output stream' ); }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . self::CSV_FILENAME );

        // Header
        fputcsv( $fh, [
            'post_id',
            'post_title',
            'post_status',
            'description',
            'menus',      // pipe-separated names
            'sections',   // pipe-separated names
            'Price_Single',
            'Price_Multiple', // values separated by "*"
        ] );

        // Empty starter row
        fputcsv( $fh, [ '', '', 'publish', '', '', '', '', '' ] );

        // A short note row to remind about delimiter (kept harmless with blank post_title)
        fputcsv( $fh, [ '', '(Leave post_id empty for NEW items)', '', '', '', '', '', "Use '{$delim}' between values (max 4)" ] );

        fclose( $fh );
        exit;
    }
}
