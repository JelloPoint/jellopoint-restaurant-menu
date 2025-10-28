<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Admin_Import_Export {

	/** Default expected parent slug (will auto-detect if different). */
	private const DEFAULT_PARENT = 'jprm';

	/** Submenu slug for this page. */
	private const PAGE_SLUG = 'jprm-import-export';

	/** Nonce. */
	private const NONCE_ACTION = 'jprm_import_export';
	private const NONCE_FIELD  = '_jprm_ie_nonce';

	/** Resolved parent slug at runtime. */
	private static $resolved_parent = null;

	public static function bootstrap(): void {
		if ( ! is_admin() ) { return; }

		// Register very late so parent menus are already in place.
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );

		// Post handlers.
		add_action( 'admin_post_jprm_export', [ __CLASS__, 'handle_export' ] );
		add_action( 'admin_post_jprm_import', [ __CLASS__, 'handle_import' ] );

		// Assets only on our screen.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	private static function detect_parent_slug(): ?string {
		global $admin_page_hooks;

		// Exact match OK?
		if ( isset( $admin_page_hooks[ self::DEFAULT_PARENT ] ) ) {
			return self::DEFAULT_PARENT;
		}

		// Try to find any parent that starts with 'jprm'.
		if ( is_array( $admin_page_hooks ) ) {
			foreach ( array_keys( $admin_page_hooks ) as $slug ) {
				if ( strpos( $slug, 'jprm' ) === 0 ) {
					return $slug; // first match wins
				}
			}
		}

		return null; // not found
	}

	public static function register_menu(): void {
		self::$resolved_parent = self::detect_parent_slug();

		if ( self::$resolved_parent ) {
			add_submenu_page(
				self::$resolved_parent,
				__( 'Import/Export', 'jellopoint-restaurant-menu' ),
				__( 'Import/Export', 'jellopoint-restaurant-menu' ),
				'manage_options',
				self::PAGE_SLUG,
				[ __CLASS__, 'render_page' ],
				20
			);
			return;
		}

		// Fallback under Tools so you can still access the screen safely.
		add_submenu_page(
			'tools.php',
			__( 'JPRM Import/Export (temp)', 'jellopoint-restaurant-menu' ),
			__( 'JPRM Import/Export (temp)', 'jellopoint-restaurant-menu' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ],
			99
		);

		// Tiny notice explaining the fallback (non-intrusive).
		add_action( 'admin_notices', function () {
			if ( ! self::is_current_screen() ) { return; }
			echo '<div class="notice notice-warning is-dismissible"><p>'
				. esc_html__( 'JPRM parent menu not found. Showing Import/Export under Tools temporarily. Once your JPRM parent menu registers, this page will attach there automatically.', 'jellopoint-restaurant-menu' )
				. '</p></div>';
		} );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		$export_url  = admin_url( 'admin-post.php?action=jprm_export' );
		$import_url  = admin_url( 'admin-post.php?action=jprm_import' );
		$nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

		$messages = [];
		if ( isset( $_GET['jprm_ie_msg'] ) ) {
			$messages[] = sanitize_text_field( wp_unslash( $_GET['jprm_ie_msg'] ) );
		}

		$view = plugin_dir_path( __FILE__ ) . 'views/import-export-page.php';
		if ( file_exists( $view ) ) {
			// Make vars available in scope for the view.
			/** @var string $export_url */
			/** @var string $import_url */
			/** @var string $nonce_field */
			/** @var array  $messages */
			include $view;
		} else {
			echo '<div class="wrap"><h1>JPRM Import/Export</h1><p>View file missing.</p></div>';
		}
	}

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

	private static function is_current_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) { return false; }
		$screen = get_current_screen();
		if ( ! $screen ) { return false; }

		// When attached under parent: base becomes "{$parent}_page_{$slug}".
		$targets = [];
		if ( self::$resolved_parent ) {
			$targets[] = self::$resolved_parent . '_page_' . self::PAGE_SLUG;
		}
		// Fallback under Tools.
		$targets[] = 'tools_page_' . self::PAGE_SLUG;

		return in_array( $screen->base, $targets, true );
	}

	/** Export handler (stub). */
	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'Export not yet implemented (safe stub).' ), $back ) );
		exit;
	}

	/** Import handler (stub). */
	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		wp_safe_redirect( add_query_arg( 'jprm_ie_msg', rawurlencode( 'Import dry-run not yet implemented (safe stub).' ), $back ) );
		exit;
	}
}
