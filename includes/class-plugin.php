<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap (CPT/Tax, Elementor, assets).
 * Keep this file minimal: register types/tax, assets, Elementor glue, and load modular classes.
 */
class Plugin {

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Public entry.
	 */
	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		// Types & Taxonomies.
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Elementor.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Assets.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_editor_assets' ] );

		// Load modular classes (editor endpoints, optional debug shortcode).
		add_action( 'init', [ __CLASS__, 'load_modules' ], 11 );
	}

	/* =========================
	 * Modules
	 * ========================= */

	public static function load_modules(): void {
		$base_dir = defined( 'JPRM_PLUGIN_DIR' ) ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../';

		// Editor-only AJAX endpoints (Elementor panel helpers).
		$editor_endpoints = trailingslashit( $base_dir ) . 'includes/admin/class-editor-endpoints.php';
		if ( file_exists( $editor_endpoints ) ) {
			require_once $editor_endpoints;
			if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\Editor_Endpoints' ) ) {
				\JelloPoint\RestaurantMenu\Admin\Editor_Endpoints::init();
			}
		}

		// Admin-only inspector shortcode ([jprm_inspect]) for quick diagnostics.
		$inspector_sc = trailingslashit( $base_dir ) . 'includes/debug/class-inspector-shortcode.php';
		if ( file_exists( $inspector_sc ) ) {
			require_once $inspector_sc;
			if ( class_exists( '\JelloPoint\RestaurantMenu\Debug\Inspector_Shortcode' ) ) {
				\JelloPoint\RestaurantMenu\Debug\Inspector_Shortcode::init();
			}
		}

		// NEW: Sections Meta UI (owning menu dropdown)
		$sections_meta_ui = trailingslashit( $base_dir ) . 'includes/admin/class-jprm-sections-meta-ui.php';
		if ( file_exists( $sections_meta_ui ) ) {
			require_once $sections_meta_ui;
		if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI' ) ) {
		\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI::init();
	}
}
	}

	/* =========================
	 * CPT + Taxonomies
	 * ========================= */

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
				// List screen attachment to a parent menu is handled elsewhere.
				'show_in_menu' => false,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'page-attributes' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'menu-item' ],
				'menu_icon'    => 'dashicons-carrot',
			]
		);
	}

	public static function register_taxonomies(): void {
		// Menus (e.g., Lunch, Dinner).
		if ( ! taxonomy_exists( 'jprm_menu' ) ) {
			register_taxonomy(
				'jprm_menu',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
					'labels'       => [
						'name'          => __( 'Menus', 'jellopoint-restaurant-menu' ),
						'singular_name' => __( 'Menu', 'jellopoint-restaurant-menu' ),
						'add_new_item'  => __( 'Add Menu', 'jellopoint-restaurant-menu' ),
						'edit_item'     => __( 'Edit Menu', 'jellopoint-restaurant-menu' ),
					],
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}

		// Sections within a Menu (e.g., Starters, Mains).
		if ( ! taxonomy_exists( 'jprm_section' ) ) {
			register_taxonomy(
				'jprm_section',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Sections', 'jellopoint-restaurant-menu' ),
					'labels'       => [
						'name'          => __( 'Sections', 'jellopoint-restaurant-menu' ),
						'singular_name' => __( 'Section', 'jellopoint-restaurant-menu' ),
						'add_new_item'  => __( 'Add Section', 'jellopoint-restaurant-menu' ),
						'edit_item'     => __( 'Edit Section', 'jellopoint-restaurant-menu' ),
					],
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}
	}

	/* =========================
	 * Assets
	 * ========================= */

	public static function register_assets(): void {
		// Register (not enqueue) the frontend stylesheet used by the widget/shortcodes.
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			$base = defined( 'JPRM_PLUGIN_URL' ) ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
			$rel  = 'includes/render/css/menu.css';

			$abs  = trailingslashit( defined('JPRM_PLUGIN_DIR') ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../' ) . $rel;
			$url  = trailingslashit( $base ) . $rel;
			$ver  = defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null;

			if ( file_exists( $abs ) ) {
				wp_register_style( 'jprm-menu', $url, [], $ver );
			} else {
				wp_register_style( 'jprm-menu', trailingslashit( $base ) . 'assets/css/frontend.css', [], $ver );
			}
		}
	}

	public static function enqueue_editor_styles(): void {
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

		$file = defined( 'JPRM_PLUGIN_DIR' )
			? trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/widgets/class-restaurant-menu.php'
			: plugin_dir_path( __DIR__ ) . 'widgets/class-restaurant-menu.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}

		if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) && is_object( $widgets_manager ) ) {
			$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
		}
	}

	/* =========================
	 * Elementor editor assets
	 * ========================= */

	public static function enqueue_elementor_editor_assets(): void {
		$base_url = defined('JPRM_PLUGIN_URL') ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
		$handle   = 'jprm-elementor-sections-dep';
		$src      = trailingslashit( $base_url ) . 'assets/admin/elementor-sections-dep.js';

		wp_register_script(
			$handle,
			$src,
			[ 'jquery', 'elementor-editor' ],
			defined('JPRM_PLUGIN_VERSION') ? JPRM_PLUGIN_VERSION : null,
			true
		);

		// Provide ajaxurl + nonce to the script.
		wp_localize_script(
			$handle,
			'JPRMAjax',
			[
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'jprm_sections' ),
			]
		);

		wp_enqueue_script( $handle );
	}
}

/** Bootstrap */
\JelloPoint\RestaurantMenu\Plugin::init();
