<?php
/**
 * Core plugin bootstrap for JelloPoint Restaurant Menu.
 *
 * This file is intentionally conservative:
 * - Registers frontend styles so Elementor widget can depend on `jprm-menu`.
 * - Registers Elementor widget.
 * - Adds a REST route used by the Elementor editor to filter Sections by the selected Menu.
 * - Enqueues a tiny editor-only JS to dynamically update the Sections control.
 *
 * Nothing else in your plugin is modified by this file.
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure base constants exist (defensive; your main file likely defines these already).
 */
if ( ! defined( 'JPRM_PLUGIN_DIR' ) ) {
	define( 'JPRM_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) ); // /.../jellopoint-restaurant-menu/
}
if ( ! defined( 'JPRM_PLUGIN_URL' ) ) {
	define( 'JPRM_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
}
if ( ! defined( 'JPRM_PLUGIN_VERSION' ) ) {
	// Fall back to a timestamp in dev; your main file should define a real version.
	define( 'JPRM_PLUGIN_VERSION', '1.0.0-dev' );
}

/**
 * Main bootstrap class.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * Singleton init.
	 */
	public static function init() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register public styles/scripts early.
		add_action( 'init', [ $this, 'register_public_assets' ] );

		// Elementor: register our widget.
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widgets' ] );

		// REST route used by the Elementor editor (admin).
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Elementor editor-only assets (admin panel script that filters Sections by Menu).
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_elementor_editor_assets' ] );
	}

	/**
	 * Register frontend styles so the widget's get_style_depends() handle exists.
	 */
	public function register_public_assets() : void {
		// Primary style handle used by the widget (`jprm-menu`).
		$paths = [
			'includes/render/css/menu.css', // your tree shows this file
			'assets/css/frontend.css',      // optional extra stylesheet if you use it
		];

		// Register first existing file as the handle 'jprm-menu' (keeps BC with widget).
		foreach ( $paths as $rel ) {
			$abs = trailingslashit( JPRM_PLUGIN_DIR ) . $rel;
			$url = trailingslashit( JPRM_PLUGIN_URL ) . $rel;
			if ( file_exists( $abs ) ) {
				// Register the first stylesheet found as the main dependency handle.
				if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
					wp_register_style( 'jprm-menu', $url, [], JPRM_PLUGIN_VERSION );
				} else {
					// Optionally also register it under its own path-specific handle.
					wp_register_style( 'jprm-' . md5( $rel ), $url, [], JPRM_PLUGIN_VERSION );
				}
			}
		}
	}

	/**
	 * Register Elementor widgets.
	 */
	public function register_elementor_widgets( \Elementor\Widgets_Manager $widgets_manager ) : void {
		// Load widget class.
		$widget_file = trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/widgets/class-restaurant-menu.php';
		if ( file_exists( $widget_file ) ) {
			require_once $widget_file;
			// FQCN from your widget file:
			$cls = '\\JelloPoint\\RestaurantMenu\\Widgets\\Restaurant_Menu';
			if ( class_exists( $cls ) ) {
				$widgets_manager->register( new $cls() );
			}
		}
	}

	/**
	 * Register REST routes used by the Elementor editor to dynamically filter Sections by selected Menu.
	 */
	public function register_rest_routes() : void {
		$rest_file = trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/rest/class-jprm-sections-by-menu-controller.php';
		if ( file_exists( $rest_file ) ) {
			require_once $rest_file;
		}
		if ( class_exists( '\\JelloPoint\\RestaurantMenu\\Rest\\Sections_By_Menu_Controller' ) ) {
			\JelloPoint\RestaurantMenu\Rest\Sections_By_Menu_Controller::register();
		}
	}

	/**
	 * Enqueue Elementor editor-only JS that watches the Menu control and updates the Sections control.
	 */
	public function enqueue_elementor_editor_assets() : void {
		$handle = 'jprm-elementor-sections-dep';
		$src    = trailingslashit( JPRM_PLUGIN_URL ) . 'assets/admin/elementor-sections-dep.js';

		// Register (in case other code wants to localize first).
		wp_register_script(
			$handle,
			$src,
			[ 'jquery', 'elementor-editor' ],
			JPRM_PLUGIN_VERSION,
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

// Bootstrap immediately (safe to call multiple times).
Plugin::init();
