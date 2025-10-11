<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 * Restores the original menu tree and adds the new "Dietary Badges" submenu,
 * while preventing duplicate "Menu Items" entries.
 */
class Admin_Menu {

	const PARENT_SLUG = 'jellopoint';
	const SLUG_MENUS  = 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item';
	const SLUG_SECTS  = 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
	const SLUG_ITEMS  = 'edit.php?post_type=jprm_menu_item';

	/** Stable slug for the new page */
	const SLUG_BADGES = 'jprm-dietary-badges';

	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) return;
		self::$bootstrapped = true;

		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 99 );
		add_action( 'admin_menu', [ __CLASS__, 'sanitize_order_and_dedupe' ], 100 );
	}

	/**
	 * Ensure top-level "JelloPoint" parent exists.
	 */
	public static function ensure_parent(): void {
		global $menu;

		foreach ( (array) $menu as $m ) {
			if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
				return;
			}
		}

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
	 * Add submenus under the parent—keep existing ones, add Dietary Badges.
	 * IMPORTANT: We DO NOT add "Menu Items" here anymore to avoid duplicates.
	 */
	public static function register_submenus(): void {
		$parent      = self::PARENT_SLUG;
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		// Existing native taxonomy screens.
		self::maybe_add_submenu( $parent, __( 'Menus', 'jellopoint-restaurant-menu' ),    self::SLUG_MENUS, 'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Sections', 'jellopoint-restaurant-menu' ), self::SLUG_SECTS, 'edit_posts' );

		// DO NOT add Menu Items here (another component already adds it).
		// self::maybe_add_submenu( $parent, __( 'Menu Items', 'jellopoint-restaurant-menu' ), self::SLUG_ITEMS, 'edit_posts' );

		// Existing "Price Labels".
		self::maybe_add_submenu( $parent, __( 'Price Labels', 'jellopoint-restaurant-menu' ), $labels_slug, 'manage_options' );

		// NEW: Dietary Badges — mirror behavior of Price Labels.
		self::ensure_badges_classes_loaded();
		if ( class_exists( '\JPRM_Badges_Store', false ) && class_exists( '\JPRM_Admin_Dietary_Badges', false ) ) {
			$badges_store = new \JPRM_Badges_Store();
			$badges_page  = new \JPRM_Admin_Dietary_Badges( $badges_store );

			self::maybe_add_submenu(
				$parent,
				__( 'Dietary Badges', 'jellopoint-restaurant-menu' ),
				self::SLUG_BADGES,
				'manage_options',
				function() use ( $badges_page ) { $badges_page->render_page(); }
			);
		}
	}

	/**
	 * Remove the auto "JelloPoint" self-link.
	 */
	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * Keep order predictable and remove duplicates (especially "Menu Items").
	 */
	public static function sanitize_order_and_dedupe(): void {
		global $submenu;
		if ( empty( $submenu[ self::PARENT_SLUG ] ) ) return;

		$items = $submenu[ self::PARENT_SLUG ];
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		// First pass: dedupe by slug (keep first occurrence).
		$seen = [];
		$deduped = [];
		foreach ( $items as $row ) {
			$slug = isset( $row[2] ) ? (string) $row[2] : '';
			if ( $slug === '' ) {
				$deduped[] = $row;
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				// Skip duplicates.
				continue;
			}
			$seen[ $slug ] = true;
			$deduped[] = $row;
		}

		// Second pass: if somehow two different slugs both target Menu Items,
		// remove extras by checking the URL contains our post_type.
		$final = [];
		$menu_items_kept = false;
		foreach ( $deduped as $row ) {
			$slug = isset( $row[2] ) ? (string) $row[2] : '';
			$is_menu_items_target =
				( $slug === self::SLUG_ITEMS ) ||
				( is_string( $slug ) && strpos( $slug, 'edit.php' ) !== false && strpos( $slug, 'post_type=jprm_menu_item' ) !== false );

			if ( $is_menu_items_target ) {
				if ( $menu_items_kept ) {
					// Already have one; drop this duplicate.
					continue;
				}
				$menu_items_kept = true;
			}

			$final[] = $row;
		}

		// Order them: Menus, Sections, Menu Items, Price Labels, Dietary Badges, then rest.
		$order = [
			self::SLUG_MENUS  => 10,
			self::SLUG_SECTS  => 11,
			self::SLUG_ITEMS  => 12,
			$labels_slug      => 24,
			self::SLUG_BADGES => 25,
		];

		$weighted = [];
		foreach ( $final as $idx => $row ) {
			$slug = isset( $row[2] ) ? (string) $row[2] : '';
			$wt   = array_key_exists( $slug, $order ) ? $order[ $slug ] : (50 + $idx);
			$weighted[] = [ 'w' => $wt, 'i' => $idx, 'row' => $row ];
		}

		usort( $weighted, function( $a, $b ){
			if ( $a['w'] === $b['w'] ) return $a['i'] <=> $b['i'];
			return $a['w'] <=> $b['w'];
		});

		$submenu[ self::PARENT_SLUG ] = array_map( fn($x) => $x['row'], $weighted );
	}

	/**
	 * Add submenu if it doesn't already exist under the parent.
	 *
	 * @param string          $parent_slug
	 * @param string          $page_title
	 * @param string          $menu_slug  Native target (edit.php?...) or custom slug.
	 * @param string          $capability
	 * @param callable|string $callback   Optional render callback for custom pages.
	 */
	protected static function maybe_add_submenu( string $parent_slug, string $page_title, string $menu_slug, string $capability, $callback = '' ): void {
		if ( self::submenu_exists( $parent_slug, $menu_slug ) ) {
			return;
		}
		// Native screens: no callback.
		if ( ! is_callable( $callback ) ) {
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug );
			return;
		}
		// Custom renderer:
		add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, $callback );
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
		$base = dirname( __DIR__ ); // /includes
		$data = $base . '/data/class-badges-store.php';
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
