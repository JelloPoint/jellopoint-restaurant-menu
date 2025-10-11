<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Central admin menu registrar for the JelloPoint plugin.
 * - Ensures a single top-level "JelloPoint Menu"
 * - Provides a stable parent slug for all submenus
 * - Avoids duplicate roots; runs late to reuse existing parent if already registered
 */
class Admin_Menu {

	/** Cached detected parent slug */
	protected static $parent_slug = null;

	/** Submenu registrations queued by other components */
	protected static $queued_submenus = [];

	public static function init() : void {
		// Run late so any previously-registered parent can be reused.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent_and_attach_submenus' ], 99 );
	}

	/**
	 * Queue a submenu to be attached under the detected/created parent.
	 * Accepts:
	 * - page_title
	 * - menu_title
	 * - capability
	 * - menu_slug
	 * - callback (callable)
	 * - position (int)
	 */
	public static function register_submenu( array $args ) : void {
		self::$queued_submenus[] = $args;
	}

	/**
	 * Detect (or create) the parent slug we use for all submenus.
	 */
	public static function get_parent_slug() : string {
		if ( is_string( self::$parent_slug ) ) return self::$parent_slug;

		// 1) Try candidates from filter or common names
		global $admin_page_hooks, $menu;
		$candidates = apply_filters( 'jprm/admin_parent_slug_candidates', [] );
		if ( ! is_array( $candidates ) ) $candidates = [];
		$common = [ 'jellopoint-menu', 'jellopoint_root', 'jellopoint', 'jprm_root' ];
		$candidates = array_values( array_unique( array_merge( $candidates, $common ) ) );

		foreach ( $candidates as $slug ) {
			if ( isset( $admin_page_hooks[ $slug ] ) ) {
				self::$parent_slug = $slug;
				return self::$parent_slug;
			}
		}

		// 2) Scan admin menu titles to find our known parent by label (fallback)
		if ( is_array( $menu ) ) {
			foreach ( $menu as $m ) {
				// $m = [0 => title, 2 => slug]
				if ( isset( $m[0], $m[2] ) ) {
					$t = wp_strip_all_tags( (string) $m[0] );
					$slug = (string) $m[2];
					$low = mb_strtolower( $t );
					if ( $low === 'jellopoint menu' || $low === 'jellopoint' ) {
						self::$parent_slug = $slug;
						return self::$parent_slug;
					}
				}
			}
		}

		// Not found yet; return empty string to signal creation path.
		return '';
	}

	/**
	 * Ensure parent menu exists, then attach all queued submenus.
	 */
	public static function ensure_parent_and_attach_submenus() : void {
		$parent = self::get_parent_slug();

		// If still not found, create the parent now (once).
		if ( $parent === '' ) {
			$parent = 'jellopoint-menu';
			add_menu_page(
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), // page title
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), // menu title
				'manage_options',
				$parent,     // slug we standardize on
				'__return_null',
				'dashicons-store',
				56
			);
			self::$parent_slug = $parent; // cache it
		}

		// Add any queued submenus now.
		if ( ! empty( self::$queued_submenus ) ) {
			foreach ( self::$queued_submenus as $args ) {
				$page_title = $args['page_title'] ?? '';
				$menu_title = $args['menu_title'] ?? '';
				$capability = $args['capability'] ?? 'manage_options';
				$menu_slug  = $args['menu_slug'] ?? '';
				$callback   = $args['callback']   ?? '__return_null';
				$position   = isset($args['position']) ? (int)$args['position'] : null;

				if ( $page_title && $menu_title && $menu_slug ) {
					add_submenu_page(
						self::$parent_slug,
						$page_title,
						$menu_title,
						$capability,
						$menu_slug,
						$callback,
						$position
					);
				}
			}
			// Clear the queue once attached
			self::$queued_submenus = [];
		}
	}
}

// === JPRM: Dietary Badges submenu (append-only) ===
// This does NOT alter any existing menus; it just queues one more submenu at the bottom.
\JelloPoint\RestaurantMenu\Admin\Admin_Menu::register_submenu([
	'page_title' => __( 'Dietary Badges', 'jprm' ),
	'menu_title' => __( 'Dietary Badges', 'jprm' ),
	'capability' => 'manage_options',
	'menu_slug'  => 'jprm-dietary-badges',
	'callback'   => function() {
		$admin_file = __DIR__ . '/class-admin-dietary-badges.php';
		$data_file  = dirname( __DIR__ ) . '/data/class-badges-store.php';
		if ( file_exists( $data_file ) ) require_once $data_file;
		if ( file_exists( $admin_file ) ) require_once $admin_file;

		if ( class_exists( '\JPRM_Badges_Store', false ) && class_exists( '\JPRM_Admin_Dietary_Badges', false ) ) {
			$store = new \JPRM_Badges_Store();
			$page  = new \JPRM_Admin_Dietary_Badges( $store );
			$page->render_page();
		} else {
			wp_die( esc_html__( 'Dietary Badges screen could not be loaded. Missing classes.', 'jprm' ) );
		}
	},
	// Large position ensures it appears at the bottom of your existing group.
	'position'   => 999,
]);
