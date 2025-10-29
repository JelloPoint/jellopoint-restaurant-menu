<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM Import/Export — strictly under the JelloPoint parent admin menu.
 */
final class JPRM_Admin_Import_Export {

	private const PAGE_SLUG    = 'jprm-import-export';
	private const NONCE_ACTION = 'jprm_import_export';
	private const NONCE_FIELD  = '_jprm_ie_nonce';
	private const CAPABILITY   = 'edit_posts';

	public static function bootstrap(): void {
		if ( ! is_admin() ) { return; }

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_post_jprm_export', [ __CLASS__, 'handle_export' ] );
		add_action( 'admin_post_jprm_import', [ __CLASS__, 'handle_import' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function register_menu(): void {
		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) { return; }
		$parent_slug = @constant( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu::PARENT_SLUG' );
		if ( ! is_string( $parent_slug ) || $parent_slug === '' ) { return; }

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

		$view = __DIR__ . '/views/import-export-page.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wrap"><h1>JPRM Import/Export</h1><p>View file missing.</p></div>';
		}
	}

	public static function enqueue_assets( $hook ): void {
		if ( ! self::is_current_screen() ) { return; }

		$base_url = plugins_url( '/', dirname( __FILE__, 1 ) ); // /includes/admin/
		$plugin_root_url = trailingslashit( dirname( $base_url, 2 ) );

		wp_enqueue_style(
			'jprm-import-export',
			$plugin_root_url . 'assets/admin/import-export.css',
			[],
			defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '1.0.0'
		);

		wp_enqueue_script(
			'jprm-import-export',
			$plugin_root_url . 'assets/admin/import-export.js',
			[ 'jquery' ],
			defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '1.0.0',
			true
		);
	}

	private static function is_current_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) { return false; }
		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu' ) ) { return false; }
		$parent_slug = @constant( '\\JelloPoint\\RestaurantMenu\\Admin\\Admin_Menu::PARENT_SLUG' );
		if ( ! is_string( $parent_slug ) || $parent_slug === '' ) { return false; }

		$screen = get_current_screen();
		return ( $screen && $screen->base === $parent_slug . '_page_' . self::PAGE_SLUG );
	}

	/* =========================== Handlers =========================== */

	public static function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$format = isset( $_POST['format'] ) && $_POST['format'] === 'csv' ? 'csv' : 'json';

		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Exporter' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-exporter.php';
		}
		\JelloPoint\RestaurantMenu\Data\JPRM_Exporter::stream( [ 'format' => $format ] );
		exit;
	}

	public static function handle_import(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		if ( empty( $_FILES['jprm_import_file'] ) ) {
			$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
			wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'No file uploaded.' ), $back ) );
			exit;
		}

		// Explicit action signal from view.
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'dry_run';
		$dry_run = ($action_type !== 'import');

		$create_missing_terms = ! empty( $_POST['create_missing_terms'] );
		$attach_images        = ! empty( $_POST['attach_images'] ); // reserved

		if ( ! class_exists( '\\JelloPoint\\RestaurantMenu\\Data\\JPRM_Importer' ) ) {
			require_once dirname( __DIR__ ) . '/data/class-importer.php';
		}

		$report = \JelloPoint\RestaurantMenu\Data\JPRM_Importer::run(
			$_FILES['jprm_import_file'],
			[
				'dry_run'              => $dry_run,
				'create_missing_terms' => $create_missing_terms,
				'attach_images'        => $attach_images,
			]
		);

		// Also keep lists of newly created terms (if provided by importer)
		// so the view can display them explicitly.
		// (Importer should set new_terms => [ 'menus' => n, 'sections' => n, 'menus_list' => [...], 'sections_list' => [...] ])
		if ( ! isset( $report['new_terms']['menus_list'] ) ) {
			$report['new_terms']['menus_list'] = $report['new_terms']['menus_list'] ?? [];
		}
		if ( ! isset( $report['new_terms']['sections_list'] ) ) {
			$report['new_terms']['sections_list'] = $report['new_terms']['sections_list'] ?? [];
		}

		$key = 'jprm_ie_report_' . wp_generate_password( 8, false, false );
		set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( [ 'jprm_ie_report' => $key ], $back ) );
		exit;
	}
}
