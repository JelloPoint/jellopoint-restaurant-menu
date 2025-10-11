<?php
/**
 * Admin menu glue for adding the new "Dietary Badges" page
 * without altering any existing menus or submenus.
 *
 * This file:
 * - Detects the parent slug currently used by "Price Labels".
 * - Registers the "Dietary Badges" submenu right next to it.
 *
 * It DOES NOT create top-level menus, and DOES NOT add the existing
 * Menus/Sections/Menu Items submenus — those remain untouched.
 *
 * @package JelloPoint\RestaurantMenu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight registrar for the Dietary Badges submenu only.
 */
class JPRM_Admin_Menu {

	/** The menu slug of our new page (kept stable). */
	const BADGES_SLUG = 'jprm-dietary-badges';

	/** Hook bootstrap */
	public static function init() {
		// Late priority so all other menus (including Price Labels) already exist.
		add_action( 'admin_menu', array( __CLASS__, 'register_badges_submenu' ), 99 );
	}

	/**
	 * Register the "Dietary Badges" submenu under the SAME parent as "Price Labels".
	 */
	public static function register_badges_submenu() {
		$parent = self::detect_parent_slug_for_price_labels();

		// If we couldn't detect the parent, do nothing (avoid creating duplicates),
		// but show a small admin notice to admins with a quick remedy.
		if ( ! $parent ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_cannot_find_parent' ) );
			return;
		}

		// Avoid duplicates if some other inclusion already added it.
		if ( self::submenu_exists( $parent, self::BADGES_SLUG ) ) {
			return;
		}

		// Ensure classes are available.
		self::ensure_badges_classes_loaded();

		$store = new \JPRM_Badges_Store();
		$page  = new \JPRM_Admin_Dietary_Badges( $store );

		// Add just after Price Labels; if unknown, WordPress will sort anyway.
		add_submenu_page(
			$parent,
			__( 'Dietary Badges', 'jprm' ),
			__( 'Dietary Badges', 'jprm' ),
			'manage_options',
			self::BADGES_SLUG,
			function() use ( $page ) {
				$page->render_page();
			},
			25
		);
	}

	/**
	 * Try to find the parent slug used by the existing "Price Labels" submenu.
	 * Strategy:
	 *   1) Look for common slugs.
	 *   2) Scan $submenu titles for a "Price Labels" entry.
	 *   3) Allow explicit override via filter 'jprm/dietary_badges_parent_slug'.
	 *
	 * @return string|null
	 */
	protected static function detect_parent_slug_for_price_labels() {
		$explicit = apply_filters( 'jprm/dietary_badges_parent_slug', null );
		if ( is_string( $explicit ) && $explicit !== '' ) {
			return $explicit;
		}

		global $submenu;

		if ( empty( $submenu ) || ! is_array( $submenu ) ) {
			return null;
		}

		// Candidate slugs that are often used for the Price Labels page.
		$candidates = array(
			'jprm',              // common internal slug
			'jellopoint',        // branded parent slug
			'jellopoint-menu',   // sometimes used
		);

		// If any candidate has a "Price Labels" entry under it, return that parent.
		foreach ( $candidates as $parent ) {
			if ( ! empty( $submenu[ $parent ] ) ) {
				foreach ( $submenu[ $parent ] as $item ) {
					// $item = [page_title, capability, menu_slug, menu_title, ...]
					$title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
					if ( is_string( $title ) && self::is_price_labels_title( $title ) ) {
						return $parent;
					}
				}
			}
		}

		// Fallback: brute-force scan all parents for a "Price Labels" submenu.
		foreach ( $submenu as $parent_slug => $items ) {
			foreach ( (array) $items as $item ) {
				$title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
				if ( is_string( $title ) && self::is_price_labels_title( $title ) ) {
					return $parent_slug;
				}
			}
		}

		return null;
	}

	/**
	 * Heuristic check for "Price Labels" title (supports translations that still include "Label").
	 */
	protected static function is_price_labels_title( $title ) {
		$title_lc = mb_strtolower( $title );
		// Check for typical English title or parts of localized strings.
		return ( $title_lc === 'price labels' )
			|| ( false !== strpos( $title_lc, 'price' ) && false !== strpos( $title_lc, 'label' ) )
			|| ( false !== strpos( $title_lc, 'labels' ) && false !== strpos( $title_lc, 'price' ) );
	}

	/**
	 * Prevent duplicate registration.
	 */
	protected static function submenu_exists( $parent_slug, $menu_slug ) {
		global $submenu;
		if ( empty( $submenu[ $parent_slug ] ) ) {
			return false;
		}
		foreach ( $submenu[ $parent_slug ] as $item ) {
			if ( isset( $item[2] ) && (string) $item[2] === (string) $menu_slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure our classes are loaded.
	 */
	protected static function ensure_badges_classes_loaded() {
		// Paths relative to plugin root: includes/data/, includes/admin/
		$base_dir = dirname( __DIR__, 1 ); // .../includes/admin -> /includes
		$data     = $base_dir . '/data/class-badges-store.php';
		$admin    = __DIR__ . '/class-admin-dietary-badges.php';

		if ( ! class_exists( '\JPRM_Badges_Store', false ) && file_exists( $data ) ) {
			require_once $data;
		}
		if ( ! class_exists( '\JPRM_Admin_Dietary_Badges', false ) && file_exists( $admin ) ) {
			require_once $admin;
		}
	}

	/**
	 * Admin notice if we couldn't detect the parent (won't block site).
	 */
	public static function notice_cannot_find_parent() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'JPRM: Could not locate the parent menu for “Price Labels”, so the “Dietary Badges” submenu was not added. You can set it explicitly via the filter jprm/dietary_badges_parent_slug.', 'jprm' );
		echo '</p></div>';
	}
}

JPRM_Admin_Menu::init();
