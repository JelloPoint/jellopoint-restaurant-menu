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
	 * Wire up hooks. Safe to call multiple times (guarded).
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
	}
// REST route used by the Elementor editor to filter Sections by the chosen Menu.
add_action( 'rest_api_init', function () {
	$rest_file = trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/rest/class-jprm-sections-by-menu-controller.php';
	if ( file_exists( $rest_file ) ) {
		require_once $rest_file;
		if ( class_exists( '\JelloPoint\RestaurantMenu\Rest\Sections_By_Menu_Controller' ) ) {
			\JelloPoint\RestaurantMenu\Rest\Sections_By_Menu_Controller::register();
		}
	}
} );

// Elementor editor-only JS that updates the Sections control when Menu changes.
add_action( 'elementor/editor/after_enqueue_scripts', function () {
	$handle = 'jprm-elementor-sections-dep';
	wp_enqueue_script(
		$handle,
		trailingslashit( JPRM_PLUGIN_URL ) . 'assets/admin/elementor-sections-dep.js',
		[ 'jquery', 'elementor-editor' ],
		defined('JPRM_PLUGIN_VERSION') ? JPRM_PLUGIN_VERSION : time(),
		true
	);

	// Pass REST root + nonce (works across admin contexts).
	if ( function_exists( 'wp_create_nonce' ) ) {
		wp_localize_script( $handle, 'JPRMRest', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}
} );

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

		$file = defined( 'JPRM_PLUGIN_PATH' )
			? JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php'
			: plugin_dir_path( __DIR__ ) . 'widgets/class-restaurant-menu.php';

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
