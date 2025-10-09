<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 * Cleanup-only: no new features; ensures consistent parent + submenus without duplicates.
 */
class Admin_Menu {

	/**
	 * Parent slug shared across JelloPoint plugins.
	 * Must match the slug used by the root bootstrap.
	 */
	const PARENT_SLUG = 'jellopoint';

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Wire hooks (idempotent).
	 */
	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		// Ensure parent exists early; add submenus after types/tax are registered on init.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 90 );
		add_action( 'admin_menu', [ __CLASS__, 'enforce_order' ], 100 );
	}

	/**
	 * Create the top-level "JelloPoint" menu if it's not already present.
	 */
	public static function ensure_parent(): void {
		global $menu;

		$exists = false;
		foreach ( (array) $menu as $m ) {
			if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
				$exists = true;
				break;
			}
		}

		if ( $exists ) {
			return;
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
			58 // After Comments by default.
		);
	}

	/**
	 * Add the plugin submenus under the JelloPoint parent.
	 */
	public static function register_submenus(): void {
		$parent = self::PARENT_SLUG;

		// Menus taxonomy (jprm_menu).
		add_submenu_page(
			$parent,
			__( 'Menus', 'jellopoint-restaurant-menu' ),
			__( 'Menus', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item'
		);

		// Sections taxonomy (jprm_section).
		add_submenu_page(
			$parent,
			__( 'Sections', 'jellopoint-restaurant-menu' ),
			__( 'Sections', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item'
		);

		// CPT list (jprm_menu_item).
		add_submenu_page(
			$parent,
			__( 'Menu Items', 'jellopoint-restaurant-menu' ),
			__( 'Menu Items', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit.php?post_type=jprm_menu_item'
		);

		/**
		 * Price Labels.
		 * If your labels module creates its own page + slug, keep it.
		 * Otherwise, we expose a neutral placeholder.
		 */
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		// Only add a placeholder if nothing else already added that slug.
		if ( ! self::submenu_exists( $parent, $labels_slug ) ) {
			add_submenu_page(
				$parent,
				__( 'Price Labels', 'jellopoint-restaurant-menu' ),
				__( 'Price Labels', 'jellopoint-restaurant-menu' ),
				'manage_options',
				$labels_slug,
				'__return_null'
			);
		}
	}

	/**
	 * Remove the redundant top-level self-link (prevents duplicate first submenu).
	 */
	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * Enforce a predictable submenu order while preserving 3rd-party additions.
	 */
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

		$current = $submenu[ self::PARENT_SLUG ];
		$map     = [];

		foreach ( $current as $item ) {
			$key         = isset( $item[2] ) ? (string) $item[2] : '';
			$map[ $key ] = $item;
		}

		$reordered = [];
		foreach ( $desired as $slug ) {
			if ( isset( $map[ $slug ] ) ) {
				$reordered[] = $map[ $slug ];
				unset( $map[ $slug ] );
			}
		}

		// Append anything else at the end (added by other modules).
		foreach ( $map as $rest ) {
			$reordered[] = $rest;
		}

		$submenu[ self::PARENT_SLUG ] = $reordered;
	}

	/**
	 * Utility: check whether a submenu with a given slug exists.
	 *
	 * @param string $parent Parent menu slug.
	 * @param string $slug   Submenu slug/file.
	 * @return bool
	 */
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

// Bootstrap (safe to call multiple times).
Admin_Menu::init();
