<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Import/Export — strictly attached under the JelloPoint parent menu.
 */
final class JPRM_Admin_Import_Export {

    /** Submenu slug for this page. */
    private const PAGE_SLUG    = 'jprm-import-export';

    /** Nonce. */
    private const NONCE_ACTION = 'jprm_import_export';
    private const NONCE_FIELD  = '_jprm_ie_nonce';

    /** Capability — match the parent menu. */
    private const CAPABILITY   = 'manage_options';

    /** Maximum accepted import size (5 MiB). */
    private const MAX_IMPORT_BYTES = 5242880;

    /** Bootstrap hooks — call once from your plugin loader (admin only). */
    public static function bootstrap(): void {
        if ( ! is_admin() ) { return; }

        // Register late enough that the parent menu is definitely present.
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );

        // Handlers.
        add_action( 'admin_post_jprm_export', [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_post_jprm_import', [ __CLASS__, 'handle_import' ] );
        add_action( 'admin_post_jprm_import_demo', [ __CLASS__, 'handle_demo_import' ] );
        add_action( 'admin_post_jprm_remove_demo', [ __CLASS__, 'handle_demo_remove' ] );
        add_action( 'admin_post_jprm_install_defaults', [ __CLASS__, 'handle_install_defaults' ] );

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
        $demo_import_url = admin_url( 'admin-post.php?action=jprm_import_demo' );
        $demo_remove_url = admin_url( 'admin-post.php?action=jprm_remove_demo' );
        $install_defaults_url = admin_url( 'admin-post.php?action=jprm_install_defaults' );
        $nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Demo_Menu' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-demo-menu.php';
		}
		$demo_summary = \JelloPoint\RestaurantMenu\Data\JPRM_Demo_Menu::summary();

        $messages = [];
        if ( isset( $_GET['jprm_ie_msg'] ) ) {
            $messages[] = sanitize_text_field( wp_unslash( $_GET['jprm_ie_msg'] ) );
        }

        // Optional: load transient report if provided
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

        return ( $screen->base === $parent_slug . '_page_' . self::PAGE_SLUG );
    }

    /** Export handler. */
    public static function handle_export(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'jellopoint-restaurant-menu' ) ); }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $format = isset( $_POST['format'] ) && 'csv' === sanitize_key( wp_unslash( $_POST['format'] ) ) ? 'csv' : 'json';

        // Include the exporter and stream the download.
        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Exporter' ) ) {
            require_once dirname( __DIR__ ) . '/data/class-exporter.php';
        }
        \JelloPoint\RestaurantMenu\Data\JPRM_Exporter::stream( [
            'format' => $format,
        ] );
        exit;
    }

    /** Import handler. */
    public static function handle_import(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'jellopoint-restaurant-menu' ) ); }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        // Explicit action type (buttons set this)
        $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'dry_run';
        $dry_run     = ( $action_type !== 'import' );

        $create_missing_terms = ! empty( $_POST['create_missing_terms'] );
        $ignore_ids           = ! empty( $_POST['ignore_ids'] );
		if ( empty( $_FILES['jprm_import_file'] ) || ! is_array( $_FILES['jprm_import_file'] ) ) {
			self::redirect_import_error( __( 'No file uploaded.', 'jellopoint-restaurant-menu' ) );
		}

		$file = $_FILES['jprm_import_file'];
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$name  = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$tmp_name = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) || ! in_array( $ext, [ 'csv', 'json' ], true ) ) {
			self::redirect_import_error( __( 'Upload a valid CSV or JSON file.', 'jellopoint-restaurant-menu' ) );
		}

		clearstatcache( true, $tmp_name );
		$actual_size = filesize( $tmp_name );
		if ( false === $actual_size || $actual_size <= 0 || $actual_size > self::MAX_IMPORT_BYTES ) {
			self::redirect_import_error( __( 'Upload a non-empty CSV or JSON file no larger than 5 MB.', 'jellopoint-restaurant-menu' ) );
		}

		$content_error = self::validate_import_file_content( $tmp_name, $ext );
		if ( '' !== $content_error ) {
			self::redirect_import_error( $content_error );
		}

		$file['name'] = $name;
		$file['size'] = $actual_size;

        if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Importer' ) ) {
            require_once dirname( __DIR__ ) . '/data/class-importer.php';
        }

        $report = \JelloPoint\RestaurantMenu\Data\JPRM_Importer::run(
            $file,
            [
                'dry_run'              => $dry_run,
                'create_missing_terms' => $create_missing_terms,
                'ignore_ids'           => $ignore_ids,
                // Future: 'enforce_uid' => true, to require UID match for updates.
            ]
        );

        // Store a short-lived transient for the page to render.
        $key = 'jprm_ie_report_' . wp_generate_password( 8, false, false );
        set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );

        $back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        wp_safe_redirect( add_query_arg( [ 'jprm_ie_report' => $key ], $back ) );
        exit;
    }

	/** Preview or import the bundled demo menu. */
	public static function handle_demo_import(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'jellopoint-restaurant-menu' ) ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : 'dry_run';
		$dry_run = ( 'import' !== $action_type );

		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Demo_Menu' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-demo-menu.php';
		}

		$report = \JelloPoint\RestaurantMenu\Data\JPRM_Demo_Menu::run( $dry_run );
		$key = 'jprm_ie_report_' . wp_generate_password( 8, false, false );
		set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( [ 'jprm_ie_report' => $key ], $back ) );
		exit;
	}

	/** Validate that the uploaded bytes match the selected strict import format. */
	public static function validate_import_file_content( string $path, string $extension ) : string {
		$raw = file_get_contents( $path, false, null, 0, self::MAX_IMPORT_BYTES + 1 );
		if ( false === $raw || '' === $raw || strlen( $raw ) > self::MAX_IMPORT_BYTES || false !== strpos( $raw, "\0" ) ) {
			return __( 'The uploaded file is empty, unreadable, too large, or contains binary data.', 'jellopoint-restaurant-menu' );
		}

		$raw = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( 'json' === $extension ) {
			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || ( ! isset( $decoded['items'] ) && ! isset( $decoded[0] ) ) ) {
				return __( 'The JSON file is invalid or does not contain an items array.', 'jellopoint-restaurant-menu' );
			}
			return '';
		}

		if ( 'csv' !== $extension ) {
			return __( 'Only CSV and JSON imports are supported.', 'jellopoint-restaurant-menu' );
		}

		$line_end = strpos( $raw, "\n" );
		$header_line = false === $line_end ? $raw : substr( $raw, 0, $line_end );
		$delimiter = substr_count( $header_line, ';' ) >= substr_count( $header_line, ',' ) ? ';' : ',';
		$headers = str_getcsv( rtrim( $header_line, "\r\n" ), $delimiter );
		$required = [ 'post_id', 'post_title', 'post_status', 'description', 'menus', 'sections', 'Price_Single', 'Price_Multiple' ];
		if ( array_diff( $required, $headers ) ) {
			return __( 'The CSV file does not contain the required JelloPoint import headers.', 'jellopoint-restaurant-menu' );
		}

		return '';
	}

	/** Return safely to the plugin page after a rejected upload. */
	private static function redirect_import_error( string $message ) : void {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( $message ), $back ) );
		exit;
	}

	/** Remove content created by the bundled demo importer. */
	public static function handle_demo_remove(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'jellopoint-restaurant-menu' ) ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Demo_Menu' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-demo-menu.php';
		}

		$result = \JelloPoint\RestaurantMenu\Data\JPRM_Demo_Menu::remove();
		$message = sprintf(
			__( 'Removed demo content: %d items, %d sections, %d menu.', 'jellopoint-restaurant-menu' ),
			(int) ( $result['items'] ?? 0 ),
			(int) ( $result['sections'] ?? 0 ),
			(int) ( $result['menus'] ?? 0 )
		);

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( $message ), $back ) );
		exit;
	}

	/** Add missing standard badges, labels, and bundled icons. */
	public static function handle_install_defaults(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'jellopoint-restaurant-menu' ) ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( ! class_exists( '\JPRM_Default_Data' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-default-data.php';
		}
		$result = \JPRM_Default_Data::install_missing();
		$message = sprintf(
			__( 'Defaults installed: %d badges, %d labels, %d icons added.', 'jellopoint-restaurant-menu' ),
			(int) $result['badges_added'],
			(int) $result['labels_added'],
			(int) $result['badge_icons_added'] + (int) $result['label_icons_added']
		);

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( $message ), $back ) );
		exit;
	}
}
