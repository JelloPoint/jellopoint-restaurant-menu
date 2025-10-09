<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 * Cleanup-only, with duplicate guards.
 */
class Admin_Menu {

	const PARENT_SLUG = 'jellopoint';

	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 90 );
		add_action( 'admin_menu', [ __CLASS__, 'enforce_order' ], 100 );
	}

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

	public static function register_submenus(): void {
		$parent      = self::PARENT_SLUG;
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		// Menus taxonomy.
		self::maybe_add_submenu(
			$parent,
			__( 'Menus', 'jellopoint-restaurant-menu' ),
			'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item',
			'edit_posts'
		);

		// Sections taxonomy.
		self::maybe_add_submenu(
			$parent,
			__( 'Sections', 'jellopoint-restaurant-menu' ),
			'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item',
			'edit_posts'
		);

		// Menu Items CPT list — add only if not already present anywhere.
		self::maybe_add_submenu(
			$parent,
			__( 'Menu Items', 'jellopoint-restaurant-menu' ),
			'edit.php?post_type=jprm_menu_item',
			'edit_posts'
		);

		// Price Labels (placeholder only if nothing else adds it).
		self::maybe_add_submenu(
			$parent,
			__( 'Price Labels', 'jellopoint-restaurant-menu' ),
			$labels_slug,
			'manage_options',
			'__return_null'
		);
	}

	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	public static function enforce_order(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$desired = [
			'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item',
			'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item',
			'edit.php?post_type=jprm_menu_item',
			apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' ),
		];

		// Build a map by slug to naturally de-duplicate same targets.
		$unique = [];
		foreach ( $submenu[ self::PARENT_SLUG ] as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			$unique[ $slug ] = $item; // later wins, duplicates collapse
		}

		// Rebuild in desired order first...
		$ordered = [];
		foreach ( $desired as $slug ) {
			if ( isset( $unique[ $slug ] ) ) {
				$ordered[] = $unique[ $slug ];
				unset( $unique[ $slug ] );
			}
		}
		// ...then append any unknown items.
		foreach ( $unique as $rest ) {
			$ordered[] = $rest;
		}
		$submenu[ self::PARENT_SLUG ] = $ordered;
	}

	/**
	 * Add a submenu if a slug isn't already present under the parent.
	 *
	 * @param string      $parent
	 * @param string      $title
	 * @param string      $slug
	 * @param string      $capability
	 * @param callable|"" $callback
	 */
	private static function maybe_add_submenu( string $parent, string $title, string $slug, string $capability, $callback = '' ): void {
		if ( self::submenu_exists( $parent, $slug ) ) {
			return;
		}
		add_submenu_page( $parent, $title, $title, $capability, $slug, $callback );
	}

	private static function submenu_exists( string $parent, string $slug ): bool {
		global $submenu;

		if ( empty( $submenu[ $parent ] ) ) {
			return false;
		}
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && (string) $item[2] === $slug ) {
				return true;
			}
		}
		return false;
	}
}

Admin_Menu::init();
