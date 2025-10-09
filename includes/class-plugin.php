<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap (admin menu, CPT/Tax, Elementor hookups).
 * Cleanup-only: no functional changes intended; idempotent init to avoid double hooks.
 */
class Plugin {

	/** Stable top-level admin menu slug/title (parent). */
	const PARENT_SLUG  = 'jprm_root';
	const PARENT_TITLE = 'JelloPoint Menu';

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Wire up hooks. Safe to call multiple times (guarded).
	 */
	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		// Root + types.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent_menu' ], 5 );
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Submenus & order.
		add_action( 'admin_menu', [ __CLASS__, 'register_submenus' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'remove_parent_duplicate' ], 99 );
		add_action( 'admin_menu', [ __CLASS__, 'enforce_submenu_order' ], 100 );

		// Elementor integration.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Styles (frontend + Elementor editor preview).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
	}

	/* =========================
	 * Root + CPT + Taxonomies
	 * ========================= */

	public static function ensure_parent_menu(): void {
		global $menu;

		$has = false;
		foreach ( (array) $menu as $m ) {
			if ( isset( $m[2] ) && $m[2] === self::PARENT_SLUG ) {
				$has = true;
				break;
			}
		}

		$icon = defined( 'JPRM_MENU_ICON_URL' ) ? JPRM_MENU_ICON_URL : 'dashicons-carrot';
		$icon = apply_filters( 'jprm/root_menu_icon', $icon );

		if ( ! $has ) {
			add_menu_page(
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
				__( 'JelloPoint', 'jellopoint-restaurant-menu' ),
				'edit_posts',
				self::PARENT_SLUG,
				'__return_null',
				$icon,
				58 // After Comments, before Appearance by default.
			);
		}
	}

	public static function register_types(): void {
		if ( post_type_exists( 'jprm_menu_item' ) ) {
			return;
		}

		register_post_type(
			'jprm_menu_item',
			[
				'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
				'labels'       => [
					'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
					'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
					'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
					'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
				],
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false, // Attach under our parent manually.
				'show_in_rest' => true,
				'supports'     => [ 'title', 'page-attributes' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'menu-item' ],
				'menu_icon'    => 'dashicons-carrot',
			]
		);
	}

	public static function register_taxonomies(): void {
		// High-level "Menu" grouping (e.g., Lunch, Dinner).
		if ( ! taxonomy_exists( 'jprm_menu' ) ) {
			register_taxonomy(
				'jprm_menu',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}

		// Sections inside a Menu (e.g., Starters, Mains).
		if ( ! taxonomy_exists( 'jprm_section' ) ) {
			register_taxonomy(
				'jprm_section',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Sections', 'jellopoint-restaurant-menu' ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}
	}

	/* =========================
	 * Admin Submenus
	 * ========================= */

	public static function register_submenus(): void {
		// Menus (taxonomy).
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Menus', 'jellopoint-restaurant-menu' ),
			__( 'Menus', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item'
		);

		// Sections (taxonomy).
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Sections', 'jellopoint-restaurant-menu' ),
			__( 'Sections', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item'
		);

		// Menu Items (CPT list).
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Menu Items', 'jellopoint-restaurant-menu' ),
			__( 'Menu Items', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			'edit.php?post_type=jprm_menu_item'
		);

		// Price Labels placeholder (if the Labels Store doesn't add its own).
		if ( ! class_exists( 'JPRM_Labels_Store' ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Price Labels', 'jellopoint-restaurant-menu' ),
				__( 'Price Labels', 'jellopoint-restaurant-menu' ),
				'manage_options',
				'jprm-price-labels',
				'__return_null'
			);
		}
	}

	public static function remove_parent_duplicate(): void {
		// Hide the top-level self-link to avoid duplicate first submenu.
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	public static function enforce_submenu_order(): void {
		global $submenu;

		if ( empty( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$desired = [
			'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item',
			'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item',
			'edit.php?post_type=jprm_menu_item',
			'jprm-price-labels',
		];

		$current = $submenu[ self::PARENT_SLUG ];

		$map = [];
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
		// Append anything else (added by other modules) to the end.
		foreach ( $map as $rest ) {
			$reordered[] = $rest;
		}

		$submenu[ self::PARENT_SLUG ] = $reordered;
	}

	/* =========================
	 * Assets
	 * ========================= */

	public static function register_assets(): void {
		// Register (not enqueue) the frontend stylesheet used by the widget/shortcodes.
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			$base = defined( 'JPRM_PLUGIN_URL' ) ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
			wp_register_style(
				'jprm-menu',
				$base . 'includes/render/css/menu.css',
				[],
				defined( 'JPRM_VERSION' ) ? JPRM_VERSION : null
			);
		}
	}

	public static function enqueue_editor_styles(): void {
		// Ensure style is available in Elementor editor preview.
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'jprm-menu' );
	}

	/* =========================
	 * Elementor
	 * ========================= */

	public static function register_elementor_category( $elements_manager ): void {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category(
			'jellopoint-widgets',
			[
				'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}

	public static function register_elementor_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$file = null;
		if ( defined( 'JPRM_PLUGIN_PATH' ) ) {
			$file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';
		} else {
			$file = plugin_dir_path( __DIR__ ) . 'widgets/class-restaurant-menu.php';
		}

		if ( $file && file_exists( $file ) ) {
			require_once $file;
		}

		if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) && is_object( $widgets_manager ) ) {
			$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
		}
	}
}

/**
 * Ensure init() runs even if the bootstrap forgets to call it.
 * Safe due to the $bootstrapped guard above.
 */
\JelloPoint\RestaurantMenu\Plugin::init();