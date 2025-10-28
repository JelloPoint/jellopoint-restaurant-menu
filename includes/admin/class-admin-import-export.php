<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Import/Export — strictly attached under the JelloPoint parent menu.
 * No fallbacks, no scanning, no Tools menu.
 */
final class JPRM_Admin_Import_Export {

    /** Submenu slug for this page. */
    private const PAGE_SLUG    = 'jprm-import-export';

    /** Nonce. */
    private const NONCE_ACTION = 'jprm_import_export';
    private const NONCE_FIELD  = '_jprm_ie_nonce';

    /** Capability — must match the parent menu to ensure visibility. */
    private const CAPABILITY   = 'edit_posts';

    /** Bootstrap hooks — call once from your plugin loader (admin only). */
    public static function bootstrap(): void {
        if ( ! is_admin() ) { return; }

        // Register late enough that the parent menu is definitely present.
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );

        // Handlers.
        add_action( 'admin_post_jprm_export', [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_post_jprm_import', [ __CLASS__, 'handle_import' ] );

        // Assets only on our screen.
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /** Add the submenu strictly under the known parent slug (Admin_Menu::PARENT_SLUG). */
    public static function register_menu(): void {
        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) {
            return; // parent class must be loaded first
        }
        $parent_slug_const = '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu::PARENT_SLUG';
        $parent_slug       = @constant( $parent_slug_const );
        if ( ! is_string( $parent_slug ) || $parent_slug === '' ) {
            return;
        }

        add_submenu_page(
            $parent_slug,
            __( 'Import/Export', 'jellopoint-restaurant-menu' ),
            __( 'Import/Export', 'jellopoint-restaurant-menu' ),
            self::CAPABILITY, // match parent capability
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ],
            20
        );
    }

    /** Render admin page. */
    public static function render_page(): void {
	if ( ! current_user_can( self::CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
	}

	$export_url  = admin_url( 'admin-post.php?action=jprm_export' );
	$import_url  = admin_url( 'admin-post.php?action=jprm_import' );
	$nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

	$messages = [];
	if ( isset( $_GET['jprm_ie_msg'] ) ) {
		$messages[] = sanitize_text_field( wp_unslash( $_GET['jprm_ie_msg'] ) );
	}

	// >>> ADD: load transient report if provided
	$import_report = null;
	if ( isset( $_GET['jprm_ie_report'] ) ) {
		$key = sanitize_text_field( wp_unslash( $_GET['jprm_ie_report'] ) );
		$import_report = get_transient( $key );
	}

	$view = plugin_dir_path( __FILE__ ) . 'views/import-export-page.php';
	if ( file_exists( $view ) ) {
		/** @var array|null $import_report */
		include $view;
	} else {
		echo '<div class="wrap"><h1>JPRM Import/Export</h1><p>View file missing.</p></div>';
	}
}


    /** Enqueue assets only on our exact screen. */
    public static function enqueue_assets( $hook ): void {
        if ( ! self::is_current_screen() ) { return; }

        $base = plugins_url( '/', dirname( __FILE__, 2 ) ); // points to /includes/
        $base = trailingslashit( dirname( $base ) );        // plugin root URL

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

    /** True only when viewing the submenu under the known parent. */
    private static function is_current_screen(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) { return false; }
        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) { return false; }

        $parent_slug_const = '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu::PARENT_SLUG';
        $parent_slug       = @constant( $parent_slug_const );
        if ( ! is_string( $parent_slug ) || $parent_slug === '' ) {
            return false;
        }

        $screen = get_current_screen();
        if ( ! $screen ) { return false; }

        // Admin base for submenu pages is "{$parent}_page_{$slug}"
        return ( $screen->base === $parent_slug . '_page_' . self::PAGE_SLUG );
    }

    /** Export handler (stub). */
public static function handle_export(): void {
	if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
	check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

	$format = isset( $_POST['format'] ) && $_POST['format'] === 'csv' ? 'csv' : 'json';

	// Include the exporter and stream the download.
	if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Exporter' ) ) {
		require_once dirname( __DIR__ ) . '/data/class-exporter.php';
	}
	\JelloPoint\RestaurantMenu\Data\JPRM_Exporter::stream( [
		'format' => $format,
	] );
	exit;
}

    /** Import handler (stub). */
   public static function handle_import(): void {
	if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
	check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

	$dry_run               = ! empty( $_POST['dry_run'] );
	$create_missing_terms  = ! empty( $_POST['create_missing_terms'] );

	if ( empty( $_FILES['jprm_import_file'] ) ) {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'No file uploaded.' ), $back ) );
		exit;
	}

	// Importer
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

	// Store a short-lived transient for the page to render.
	$key = 'jprm_ie_report_' . wp_generate_password( 8, false, false );
	set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );

	$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	wp_safe_redirect( add_query_arg( [ 'jprm_ie_report' => $key ], $back ) );
	exit;
}

}
