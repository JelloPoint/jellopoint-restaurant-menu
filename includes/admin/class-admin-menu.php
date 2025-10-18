<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 * Keeps parent menu stable, registers submenus, and cleans duplicates.
 */
class Admin_Menu {

	const PARENT_SLUG = 'jellopoint';

	// Direct links WordPress expects for CPT & tax submenus.
	const SLUG_MENUS  = 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item';
	const SLUG_SECTS  = 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
	const SLUG_ITEMS  = 'edit.php?post_type=jprm_menu_item';

	/** @var bool */
	private static $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_self_link' ], 90 );
		add_action( 'admin_menu', [ __CLASS__, 'sanitize_and_order' ], 100 );

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
	 * Register all submenus we own (and only add them once).
	 */
	public static function register_submenus(): void {
		$parent      = self::PARENT_SLUG;
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		self::maybe_add_submenu( $parent, __( 'Menus',       'jellopoint-restaurant-menu' ), self::SLUG_MENUS,  'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Sections',    'jellopoint-restaurant-menu' ), self::SLUG_SECTS,  'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Menu Items',  'jellopoint-restaurant-menu' ), self::SLUG_ITEMS,  'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Price Labels','jellopoint-restaurant-menu' ), $labels_slug,      'manage_options', '__return_null' );

		// Dietary Badges submenu (this is the one with your error).
		// IMPORTANT: we require the bootstrap file BEFORE checking class_exists.
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
			999 );
		}
	}

	/**
	 * Remove the redundant first self-link under the parent.
	 */
	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * De-duplicate submenu entries and ensure a sane order.
	 * Also removes mislinked "Menu Items" entries that don't point to our CPT list.
	 */
	public static function sanitize_and_order(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) || ! is_array( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items   = $submenu[ self::PARENT_SLUG ];
		$unique  = [];
		$ordered = [];

		foreach ( $items as $item ) {
			// $item: [ page_title, menu_title, slug, capability, ... ]
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			$slug = (string) $item[2];

			// Remove mislinked entries.
			if ( false !== stripos( $item[1] ?? '', 'Menu Items' ) && $slug !== self::SLUG_ITEMS ) {
				continue;
			}

			if ( ! isset( $unique[ $slug ] ) ) {
				$unique[ $slug ] = $item;
			}
		}

		// Desired fixed order for our core entries if present.
		$preferred = [
			self::SLUG_MENUS,
			self::SLUG_SECTS,
			self::SLUG_ITEMS,
			apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' ),
			'jprm-dietary-badges',
		];

		foreach ( $preferred as $slug ) {
			if ( isset( $unique[ $slug ] ) ) {
				$ordered[] = $unique[ $slug ];
				unset( $unique[ $slug ] );
			}
		}

		// Append any other entries (stable order).
		foreach ( $unique as $item ) {
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
	 * Utility: check if a submenu exists.
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

	/**
	 * Filter callback to expose the parent slug to other components.
	 */
	public static function menu_builder_parent( $slug ) {
		return self::PARENT_SLUG;
	}
}
