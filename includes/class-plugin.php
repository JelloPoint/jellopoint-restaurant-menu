<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap (CPT/Tax, Elementor, assets).
 * Cleanup-only: no functional changes. This class does NOT manage admin menus.
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

		// Types.
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Elementor.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Styles (frontend registration + Elementor editor enqueue).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );

		// --- PATCH: dependent Sections in Elementor panel (admin-only) ---
		// Editor-only script: dynamic Sections based on selected Menu.
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_editor_assets' ] );
		// REST route for editor to fetch Sections by Menu.
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
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
		// High-level "Menu" grouping (e.g., Lunch, Dinner).
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

			// Prefer includes/render/css/menu.css (per your tree)
			$primary_rel = 'includes/render/css/menu.css';
			$primary_abs = trailingslashit( defined('JPRM_PLUGIN_DIR') ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../' ) . $primary_rel;
			$primary_url = trailingslashit( $base ) . $primary_rel;

			$ver = defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null;

			if ( file_exists( $primary_abs ) ) {
				wp_register_style( 'jprm-menu', $primary_url, [], $ver );
			} else {
				// Fallback (legacy path)
				wp_register_style( 'jprm-menu', trailingslashit( $base ) . 'assets/css/frontend.css', [], $ver );
			}
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
	 * PATCH METHODS (editor UX)
	 * ========================= */

	/**
	 * Register REST routes used by the Elementor editor to dynamically filter Sections by selected Menu.
	 */
	public static function register_rest_routes(): void {
		$base_dir = defined('JPRM_PLUGIN_DIR') ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../';
		$rest_file = trailingslashit( $base_dir ) . 'includes/rest/class-jprm-sections-by-menu-controller.php';

		if ( file_exists( $rest_file ) ) {
			require_once $rest_file;
		}
		if ( class_exists( '\JelloPoint\RestaurantMenu\Rest\Sections_By_Menu_Controller' ) ) {
			\JelloPoint\RestaurantMenu\Rest\Sections_By_Menu_Controller::register();
		}
	}

	/**
	 * Enqueue Elementor editor-only JS that watches the Menu control and updates the Sections control.
	 */
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

		// Localize REST root + nonce (works in most admin contexts).
		if ( function_exists( 'wp_create_nonce' ) ) {
			wp_localize_script(
				$handle,
				'JPRMRest',
				[
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				]
			);
		}

		wp_enqueue_script( $handle );
	}
}

/**
 * Ensure init() runs even if the bootstrap forgets to call it.
 * Safe due to the $bootstrapped guard above.
 */
\JelloPoint\RestaurantMenu\Plugin::init();
