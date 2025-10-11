<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Minimal admin menu integration:
 * - Adds ONLY the new "Dietary Badges" submenu.
 * - Leaves existing Menus / Sections / Menu Items / Price Labels entirely to the original code.
 * - Removes a stray "Menu Items" that incorrectly points to Posts (edit.php without post_type).
 */
class Admin_Menu {

	/** Parent slug used by the existing JelloPoint menu group. */
	const PARENT_SLUG = 'jellopoint';

	/** Correct target for Menu Items (kept here for validation only). */
	const SLUG_ITEMS  = 'edit.php?post_type=jprm_menu_item';

	/** New page slug for Dietary Badges. */
	const SLUG_BADGES = 'jprm-dietary-badges';

	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) return;
		self::$bootstrapped = true;

		// Do NOT rebuild the menu tree. Just ensure parent exists (no-op if already there),
		// add our submenu, and then clean duplicates/mistakes.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_badges_submenu' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 90 );
		add_action( 'admin_menu', [ __CLASS__, 'cleanup_wrong_menu_items' ], 100 );
	}

	/**
	 * Ensure the top-level "JelloPoint" parent exists.
	 * If it already exists (added by your original code), we do nothing.
	 */
	public static function ensure_parent(): void {
		global $menu;

		foreach ( (array) $menu as $m ) {
			if ( isset( $m[2] ) && (string) $m[2] === (string) self::PARENT_SLUG ) {
				return;
			}
		}

		// Safe fallback icon; your original code can override via filter.
		$icon = defined( 'JPRM_MENU_ICON_URL' ) ? JPRM_MENU_ICON_URL : 'dashicons-carrot';
		$icon = apply_filters( 'jprm/root_menu_icon', $icon );

		add_menu_page(
			__( 'JelloPoint', 'jellopoint-restaurant-menu' ),
			__( 'JelloPoint', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			self::PARENT_SLUG,
			'__return_null',
			$icon,
			58
		);
	}

	/**
	 * Add ONLY the new Dietary Badges submenu (next to existing Price Labels).
	 */
	public static function register_badges_submenu(): void {
		// Load classes needed for the page render.
		self::ensure_badges_classes_loaded();

		if ( ! class_exists( '\JPRM_Badges_Store', false ) || ! class_exists( '\JPRM_Admin_Dietary_Badges', false ) ) {
			return;
		}

		// Avoid duplicate if something already added it.
		if ( self::submenu_exists( self::PARENT_SLUG, self::SLUG_BADGES ) ) {
			return;
		}

		$badges_store = new \JPRM_Badges_Store();
		$badges_page  = new \JPRM_Admin_Dietary_Badges( $badges_store );

		// Position 25 typically puts it after "Price Labels" (often 24) without enforcing strict ordering.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dietary Badges', 'jprm' ),
			__( 'Dietary Badges', 'jprm' ),
			'manage_options',
			self::SLUG_BADGES,
			function() use ( $badges_page ) { $badges_page->render_page(); },
			25
		);
	}

	/**
	 * Remove the parent "self" link for a cleaner list (no functional change).
	 */
	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * Remove the WRONG "Menu Items" that points to Posts (edit.php / no post_type),
	 * while keeping the correct "Menu Items" that targets post_type=jprm_menu_item.
	 */
	public static function cleanup_wrong_menu_items(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) || ! is_array( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items = $submenu[ self::PARENT_SLUG ];

		// Localized label we expect for the correct menu title.
		$label_menu_items_expected = mb_strtolower( wp_strip_all_tags( __( 'Menu Items', 'jellopoint-restaurant-menu' ) ) );

		$kept_one_correct = false;
		$clean = [];

		foreach ( $items as $row ) {
			// $row: [0]=page title, [1]=cap, [2]=slug/url, [3]=menu title...
			$title = isset( $row[0] ) ? wp_strip_all_tags( (string) $row[0] ) : '';
			$slug  = isset( $row[2] ) ? (string) $row[2] : '';

			$is_menu_items_title = ( mb_strtolower( $title ) === $label_menu_items_expected );

			if ( $is_menu_items_title ) {
				$targets_correct_cpt = ( $slug === self::SLUG_ITEMS ) || ( strpos( $slug, 'post_type=jprm_menu_item' ) !== false );

				if ( $targets_correct_cpt ) {
					// Keep only the first correct one.
					if ( $kept_one_correct ) {
						continue; // drop duplicates of the correct target
					}
					$kept_one_correct = true;
					$clean[] = $row;
					continue;
				} else {
					// This is the BAD one (e.g., slug "edit.php" => Posts). Drop it.
					continue;
				}
			}

			// Not a "Menu Items" row — keep as-is.
			$clean[] = $row;
		}

		$submenu[ self::PARENT_SLUG ] = $clean;
	}

	/**
	 * Check if a submenu with a given slug already exists under a parent.
	 */
	protected static function submenu_exists( string $parent_slug, string $menu_slug ): bool {
		global $submenu;
		if ( empty( $submenu[ $parent_slug ] ) ) return false;
		foreach ( $submenu[ $parent_slug ] as $item ) {
			if ( isset( $item[2] ) && (string) $item[2] === (string) $menu_slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lazy-load the Dietary Badges classes.
	 */
	protected static function ensure_badges_classes_loaded(): void {
		$base  = dirname( __DIR__ ); // /includes
		$data  = $base . '/data/class-badges-store.php';
		$admin = __DIR__ . '/class-admin-dietary-badges.php';

		if ( ! class_exists( '\JPRM_Badges_Store', false ) && file_exists( $data ) ) {
			require_once $data;
		}
		if ( ! class_exists( '\JPRM_Admin_Dietary_Badges', false ) && file_exists( $admin ) ) {
			require_once $admin;
		}
	}
}

Admin_Menu::init();
