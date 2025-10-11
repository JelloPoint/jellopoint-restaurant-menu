<?php
/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 *
 * - Registers the top-level "JelloPoint" admin menu.
 * - Adds submenus for Menus, Sections, Menu Items, Price Labels (existing),
 *   and the new Dietary Badges page.
 * - Avoids duplicate submenu insertion.
 *
 * @package JelloPoint\RestaurantMenu
 */

namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Menu {

	/** Parent slug for the plugin's admin group */
	const PARENT_SLUG = 'jellopoint';

	/** Convenience slugs used for core CPT/Tax screens */
	const SLUG_MENUS  = 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item';
	const SLUG_SECTS  = 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
	const SLUG_ITEMS  = 'edit.php?post_type=jprm_menu_item';

	/** Menu capability */
	const CAPABILITY  = 'edit_posts';

	/** Hook bootstrap */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
	}

	/**
	 * Top-level menu
	 */
	public static function register_menu(): void {
		$icon_data = 'data:image/svg+xml;base64,' . base64_encode(
			// Simple plate-fork-knife icon (kept tiny)
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h1v8a2 2 0 0 1-4 0V3h1v4h1V3h1v4h1zM14 3h1v7h-1zM12 3h1v7h-1zM16 3h1v7a3 3 0 1 0 6 0V3h-1v7a2 2 0 1 1-4 0V3h-1v7h-1z"/></svg>'
		);

		// Create (or ensure) the parent menu. Position 58 ~= below "Appearance"
		add_menu_page(
			__( 'JelloPoint', 'jellopoint-restaurant-menu' ),
			__( 'JelloPoint', 'jellopoint-restaurant-menu' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			'__return_null',
			$icon_data,
			58
		);
	}

	/**
	 * Submenus under the JelloPoint parent.
	 * Uses guarded adders to avoid duplicates.
	 */
	public static function register_submenus(): void {
		$parent       = self::PARENT_SLUG;
		$labels_slug  = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		// Core taxonomy/CPT screens.
		self::maybe_add_submenu( $parent, __( 'Menus', 'jellopoint-restaurant-menu' ),       self::SLUG_MENUS,  self::CAPABILITY, 10 );
		self::maybe_add_submenu( $parent, __( 'Sections', 'jellopoint-restaurant-menu' ),    self::SLUG_SECTS,  self::CAPABILITY, 11 );
		self::maybe_add_submenu( $parent, __( 'Menu Items', 'jellopoint-restaurant-menu' ),  self::SLUG_ITEMS,  self::CAPABILITY, 12 );

		// Existing "Price Labels" screen (already implemented elsewhere in your plugin).
		self::maybe_add_submenu( $parent, __( 'Price Labels', 'jellopoint-restaurant-menu' ), $labels_slug, 'manage_options', 24 );

		// --- NEW: Dietary Badges (mirrors Price Labels page) -----------------------
		// Lazy-require the store + admin page class only on admin_menu.
		self::ensure_badges_classes_loaded();

		// Instantiate store + page handler. The page renderer is a callable closure.
		$badges_store = new \JPRM_Badges_Store();
		$badges_page  = new \JPRM_Admin_Dietary_Badges( $badges_store );

		self::maybe_add_submenu(
			$parent,
			__( 'Dietary Badges', 'jprm' ),
			\JPRM_Admin_Dietary_Badges::PAGE_SLUG,
			'manage_options',
			25,
			function() use ( $badges_page ) { $badges_page->render_page(); }
		);
		// --------------------------------------------------------------------------
	}

	/**
	 * Add a submenu item if it doesn't already exist.
	 *
	 * @param string          $parent_slug
	 * @param string          $page_title
	 * @param string          $menu_slug
	 * @param string          $capability
	 * @param int             $position
	 * @param callable|string $callback Optional callback; for native screens pass the target slug as menu_slug and omit callback.
	 */
	protected static function maybe_add_submenu( string $parent_slug, string $page_title, string $menu_slug, string $capability, int $position = 10, $callback = '' ): void {
		if ( self::submenu_exists( $parent_slug, $menu_slug ) ) {
			return;
		}

		// If $menu_slug points to a native screen (e.g., edit.php?post_type=...), WordPress accepts it as the "menu slug"
		// and ignores the callback. Otherwise we must supply a render callback.
		if ( is_callable( $callback ) ) {
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, $callback, $position );
		} else {
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, '', $position );
		}
	}

	/**
	 * Detect if a submenu already exists under a parent.
	 */
	protected static function submenu_exists( string $parent_slug, string $menu_slug ): bool {
		global $submenu;
		if ( empty( $submenu[ $parent_slug ] ) ) {
			return false;
		}
		foreach ( $submenu[ $parent_slug ] as $item ) {
			// $item[2] is the slug.
			if ( isset( $item[2] ) && (string) $item[2] === (string) $menu_slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Require the Dietary Badges classes if not already loaded.
	 */
	protected static function ensure_badges_classes_loaded(): void {
		if ( ! class_exists( '\JPRM_Badges_Store', false ) ) {
			require_once dirname( __DIR__ ) . '/data/class-badges-store.php';
		}
		if ( ! class_exists( '\JPRM_Admin_Dietary_Badges', false ) ) {
			require_once __DIR__ . '/class-admin-dietary-badges.php';
		}
	}
}

Admin_Menu::init();
