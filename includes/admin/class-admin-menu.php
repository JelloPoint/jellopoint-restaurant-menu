<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu manager for JelloPoint Restaurant Menu.
 * Cleanup-only, with duplicate/mislink guards.
 */
class Admin_Menu {

	const PARENT_SLUG = 'jellopoint';
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

		// 🔹 NEW: expose the correct parent slug to other components (e.g. Menu Builder)
		add_filter( 'jprm/admin/menu_builder_parent', [ __CLASS__, 'menu_builder_parent' ] );
	}

	public static function ensure_parent(): void {
		global $menu;

		foreach ( (array) $menu as $m ) {
			if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
				return;
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

	public static function register_submenus(): void {
		$parent      = self::PARENT_SLUG;
		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );

		self::maybe_add_submenu( $parent, __( 'Menus', 'jellopoint-restaurant-menu' ),   self::SLUG_MENUS, 'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Sections', 'jellopoint-restaurant-menu' ), self::SLUG_SECTS, 'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Menu Items', 'jellopoint-restaurant-menu' ), self::SLUG_ITEMS, 'edit_posts' );
		self::maybe_add_submenu( $parent, __( 'Price Labels', 'jellopoint-restaurant-menu' ), $labels_slug, 'manage_options', '__return_null' );
	}

	public static function remove_parent_self_link(): void {
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * De-duplicate and fix ordering. Also **remove mislinked “Menu Items” entries**
	 * that do not point to `edit.php?post_type=jprm_menu_item`.
	 */
	public static function sanitize_and_order(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$labels_slug = apply_filters( 'jprm/price_labels_slug', 'jprm-price-labels' );
		$desired     = [ self::SLUG_MENUS, self::SLUG_SECTS, self::SLUG_ITEMS, $labels_slug ];

		// 1) Build unique map by slug (later wins).
		$unique = [];
		foreach ( $submenu[ self::PARENT_SLUG ] as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';

			// If an entry is titled "Menu Items" but points to the wrong slug (e.g., edit.php),
			// drop it — we only allow our CPT list slug.
			$title = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
			if ( $title === __( 'Menu Items', 'jellopoint-restaurant-menu' ) && $slug !== self::SLUG_ITEMS ) {
				continue; // remove mislinked duplicate
			}

			$unique[ $slug ] = $item;
		}

		// 2) Rebuild in desired order when available; append extras at the end (de-duped).
		$ordered = [];
		foreach ( $desired as $slug ) {
			if ( isset( $unique[ $slug ] ) ) {
				$ordered[] = $unique[ $slug ];
				unset( $unique[ $slug ] );
			}
		}
		// Append leftovers (stable order).
		foreach ( $unique as $item ) {
			$ordered[] = $item;
		}

		$submenu[ self::PARENT_SLUG ] = $ordered;
	}

	/**
	 * Utility: add a submenu if the slug isn't already present.
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

	// 🔹 NEW: Provide the parent slug for external components (Menu Builder)
	public static function menu_builder_parent( $slug = '' ) : string {
		return self::PARENT_SLUG;
	}
}

Admin_Menu::init();

// === JPRM: Append-only "Dietary Badges" submenu (keep it last; no other changes below) ===
add_action( 'admin_menu', function () {
	$parent_slug = Admin_Menu::PARENT_SLUG;

	$render = function() {
		if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap' ) ) {
			\JelloPoint\RestaurantMenu\Admin\Badges_Post_Bootstrap::render_screen();
		} else {
			\wp_die( \esc_html__( 'Dietary Badges screen could not be loaded. Missing classes.', 'jprm' ) );
		}
	};

	\add_submenu_page(
		$parent_slug,
		\__( 'Dietary Badges', 'jprm' ),
		\__( 'Dietary Badges', 'jprm' ),
		'manage_options',
		'jprm-dietary-badges',
		$render,
		999 // bottom of the group
	);
}, 999);
