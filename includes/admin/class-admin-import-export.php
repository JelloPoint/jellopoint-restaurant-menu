<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Admin_Import_Export {

	/** @var string Slug of the top-level JPRM admin menu (must already exist). */
	private const PARENT_SLUG = 'jprm';

	/** @var string Submenu slug for this page. */
	private const PAGE_SLUG = 'jprm-import-export';

	/** @var string Nonce action base. */
	private const NONCE_ACTION = 'jprm_import_export';

	/** @var string Nonce field name. */
	private const NONCE_FIELD = '_jprm_ie_nonce';

	/** Bootstrap hooks — call once from your plugin loader (admin only). */
	public static function bootstrap(): void {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 60 ); // late to avoid clashes
		add_action( 'admin_post_jprm_export', [ __CLASS__, 'handle_export' ] );
		add_action( 'admin_post_jprm_import', [ __CLASS__, 'handle_import' ] );
		add_action( 'load-toplevel_page_' . self::PARENT_SLUG, [ __CLASS__, 'maybe_late_bind' ] ); // safety
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Only add submenu if parent exists to avoid corrupting the admin menu.
	 */
	public static function register_menu(): void {
		global $admin_page_hooks;
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			// Parent not registered — bail silently to avoid creating stray menus.
			return;
		}

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Import/Export', 'jellopoint-restaurant-menu' ),
			__( 'Import/Export', 'jellopoint-restaurant-menu' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ],
			20
		);
	}

	/**
	 * Extra safety: if parent loads later for any reason, we can re-run the check.
	 */
	public static function maybe_late_bind(): void {
		// No-op for now; placeholder if you need to defer submenu registration.
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		// View expects $export_url, $import_url, $nonce_field, $messages.
		$export_url  = admin_url( 'admin-post.php?action=jprm_export' );
		$import_url  = admin_url( 'admin-post.php?action=jprm_import' );
		$nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

		$messages = [];
		if ( isset( $_GET['jprm_ie_msg'] ) ) {
			$messages[] = sanitize_text_field( wp_unslash( $_GET['jprm_ie_msg'] ) );
		}

		$view = plugin_dir_path( __FILE__ ) . 'views/import-export-page.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wrap"><h1>JPRM Import/Export</h1><p>View file missing.</p></div>';
		}
	}

	public static function enqueue_assets( $hook ): void {
		// Only on our page.
		if ( $hook !== self::get_pagehook_suffix() && ! self::is_current_screen() ) { return; }

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

	private static function get_pagehook_suffix(): string {
		// We can’t know the exact $hook suffix reliably before registration; check via screen instead.
		return '';
	}

	private static function is_current_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $screen->base === self::PARENT_SLUG . '_page_' . self::PAGE_SLUG;
	}

	/** Handle Export (stub: validates nonce & redirects back). */
	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		// TODO: call \JelloPoint\RestaurantMenu\Data\JPRM_Exporter::stream( $args );
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'Export not yet implemented (safe stub).' ), $back ) );
		exit;
	}

	/** Handle Import (stub: validates nonce & redirects back). */
	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		// TODO: parse file + dry-run using Importer; for now just bounce back.
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'Import dry-run not yet implemented (safe stub).' ), $back ) );
		exit;
	}
}