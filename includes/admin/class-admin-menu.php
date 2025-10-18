<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 */
class Admin_Menu {

	const PARENT_SLUG = 'jellopoint';

	// Canonical slugs for our lists.
	const SLUG_MENUS  = 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item';
	const SLUG_SECTS  = 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
	const SLUG_ITEMS  = 'edit.php?post_type=jprm_menu_item'; // canonical CPT list

	/** @var bool */
	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		/**
		 * IMPORTANT: Make the Dietary Badges admin-post handler available on every admin load,
		 * so admin-post.php?action=jprm_save_dietary_badges works even when the screen isn’t rendered.
		 */
		require_once __DIR__ . '/badges-post-bootstrap.php';
		add_action( 'admin_post_jprm_save_dietary_badges', [ '\JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap', 'handle_post' ] );

		// Build the parent and our submenus.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );

		// Cleanup & order after everything has had a chance to add entries.
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 90 );
		add_action( 'admin_menu', [ __CLASS__, 'dedupe_and_order' ], 200 );

		// Expose the parent slug to other components if needed.
		add_filter( 'jprm/admin/menu_builder_parent', [ __CLASS__, 'menu_builder_parent' ] );
	}

	/**
	 * Make sure the top-level "JelloPoint" menu exists exactly once.
	 */
	public static function ensure_parent(): void {
		global $menu;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $m ) {
				if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
					return;
				}
			}
		}

		$icon = defined( 'JPRM_MENU_ICON_URL' ) ? JPRM_MENU_ICON_URL : 'dashicons-food';
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
	 * Register the submenus we own.
	 */
	public static function register_submenus(): void {
		$parent      = self::PARENT_SLUG;
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		self::maybe_add_submenu( $parent, __( 'Menus',       'jellopoint-restaurant-menu' ), self::SLUG_MENUS,  'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Sections',    'jellopoint-restaurant-menu' ), self::SLUG_SECTS,  'edit_posts' );

		// "Menu Items": only add if there isn't already a submenu that matches our canonical CPT list.
		if ( ! self::has_submenu_slug( $parent, self::SLUG_ITEMS ) ) {
			self::maybe_add_submenu( $parent, __( 'Menu Items', 'jellopoint-restaurant-menu' ), self::SLUG_ITEMS, 'edit_posts' );
		}

		self::maybe_add_submenu( $parent, __( 'Price Labels','jellopoint-restaurant-menu' ), $labels_slug,      'manage_options', '__return_null' );

		// Dietary Badges submenu (requires our bootstrap file before class_exists).
		if ( ! self::submenu_exists( $parent, 'jprm-dietary-badges' ) ) {
			$render = function () {
				require_once __DIR__ . '/badges-post-bootstrap.php';
				if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap' ) ) {
					\JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap::render_screen();
				} else {
					\wp_die( \esc_html__( 'Dietary Badges screen could not be loaded. Missing classes.', 'jprm' ) );
				}
			};

			add_submenu_page(
				$parent,
				__( 'Dietary Badges', 'jprm' ),
				__( 'Dietary Badges', 'jprm' ),
				'manage_options',
				'jprm-dietary-badges',
				$render,
				999
			);
		}
	}

	/**
	 * Remove the redundant first self-link under the parent.
	 */
	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * De-duplicate submenu entries and ensure a predictable order.
	 * Rules:
	 *  - Drop any entry whose slug is 'edit.php' (default Posts list).
	 *  - Drop any entry whose slug starts with 'edit.php?post_type=' but is NOT exactly our CPT list slug.
	 *  - Keep only one entry per slug.
	 *  - Order: Menus, Sections, Menu Items, Price Labels, Dietary Badges, then others.
	 */
	public static function dedupe_and_order(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) || ! is_array( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items   = $submenu[ self::PARENT_SLUG ];
		$clean   = [];

		foreach ( $items as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( $slug === '' ) continue;

			// Remove default Posts list if it somehow got attached.
			if ( $slug === 'edit.php' ) continue;

			// Remove any other CPT list pages that are not our canonical CPT slug.
			if ( strpos( $slug, 'edit.php?post_type=' ) === 0 && $slug !== self::SLUG_ITEMS ) continue;

			$clean[] = $item;
		}

		// De-duplicate by slug (keep the first occurrence).
		$unique_by_slug = [];
		foreach ( $clean as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( $slug === '' ) continue;
			if ( ! isset( $unique_by_slug[ $slug ] ) ) {
				$unique_by_slug[ $slug ] = $item;
			}
		}

		// Preferred order for our known slugs.
		$preferred = [
			self::SLUG_MENUS,
			self::SLUG_SECTS,
			self::SLUG_ITEMS,
			apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' ),
			'jprm-dietary-badges',
		];

		$ordered = [];
		foreach ( $preferred as $slug ) {
			if ( isset( $unique_by_slug[ $slug ] ) ) {
				$ordered[] = $unique_by_slug[ $slug ];
				unset( $unique_by_slug[ $slug ] );
			}
		}

		// Append the rest in stable order.
		foreach ( $unique_by_slug as $item ) {
			$ordered[] = $item;
		}

		$submenu[ self::PARENT_SLUG ] = $ordered;
	}

	/**
	 * Utility: add submenu only if the slug isn't already present.
	 */
	private static function maybe_add_submenu( string $parent, string $title, string $slug, string $capability, $callback = '' ): void {
		if ( self::submenu_exists( $parent, $slug ) ) {
			return;
		}
		add_submenu_page( $parent, $title, $title, $capability, $slug, $callback );
	}

	/**
	 * Utility: check if a submenu exists with exact slug.
	 */
	private static function has_submenu_slug( string $parent, string $slug ): bool {
		global $submenu;
		if ( empty( $submenu[ $parent ] ) ) return false;
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && (string) $item[2] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Utility: check if a submenu exists (exact slug).
	 */
	private static function submenu_exists( string $parent, string $slug ): bool {
		return self::has_submenu_slug( $parent, $slug );
	}

	/**
	 * Filter callback to expose the parent slug to other components.
	 */
	public static function menu_builder_parent( $slug ) {
		return self::PARENT_SLUG;
	}
}
