<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Dynamic restaurant menu items with labels/icons and an Elementor widget.
 * Version: 2.0.4
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ----------------------------------------------------------------------------
 * Constants
 * ------------------------------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) )          define( 'JPRM_VERSION', '2.0.4' );
if ( ! defined( 'JPRM_PLUGIN_FILE' ) )      define( 'JPRM_PLUGIN_FILE', __FILE__ );
if ( ! defined( 'JPRM_PLUGIN_PATH' ) )      define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_URL' ) )       define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_BASENAME' ) )  define( 'JPRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/* ----------------------------------------------------------------------------
 * i18n
 * ------------------------------------------------------------------------- */
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain(
		'jellopoint-restaurant-menu',
		false,
		dirname( JPRM_PLUGIN_BASENAME ) . '/languages'
	);
} );

/* ----------------------------------------------------------------------------
 * Core includes (do NOT depend on Elementor)
 * ------------------------------------------------------------------------- */
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';

/* ----------------------------------------------------------------------------
 * Initialize core plugin class if present (safe-guarded)
 * ------------------------------------------------------------------------- */
add_action( 'init', function() {
	if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) ) {
		// Preferred static bootstrap method
		if ( method_exists( '\JelloPoint\RestaurantMenu\Plugin', 'init' ) ) {
			\JelloPoint\RestaurantMenu\Plugin::init();
		}
		// Alternate singleton pattern support
		elseif ( method_exists( '\JelloPoint\RestaurantMenu\Plugin', 'instance' ) ) {
			\JelloPoint\RestaurantMenu\Plugin::instance();
		}
	}
}, 5 );

/* ----------------------------------------------------------------------------
 * Elementor: register a custom category (optional, used by widget get_categories())
 * ------------------------------------------------------------------------- */
add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
	if ( method_exists( $elements_manager, 'add_category' ) ) {
		$elements_manager->add_category(
			'jellopoint-widgets',
			[
				'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}
}, 10 );

/* ----------------------------------------------------------------------------
 * Elementor integration — load & register widget ONLY after Elementor is ready
 * ------------------------------------------------------------------------- */
add_action( 'elementor/widgets/register', function( $widgets_manager ) {

	$widget_file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';
	if ( file_exists( $widget_file ) ) {
		require_once $widget_file;
	}

	if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
		$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
	}

}, 10 );

/* ----------------------------------------------------------------------------
 * Admin notice if Elementor is inactive (non-fatal)
 * ------------------------------------------------------------------------- */
add_action( 'admin_init', function() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', function() {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'JelloPoint – Restaurant Menu: Elementor is not active. The Elementor widget will be unavailable until Elementor is activated.', 'jellopoint-restaurant-menu' );
				echo '</p></div>';
			}
		} );
	}
} );

/* ----------------------------------------------------------------------------
 * Safety note:
 * Do NOT require includes/widgets/class-restaurant-menu.php anywhere else.
 * The only place it is included is inside the Elementor hook above.
 * ------------------------------------------------------------------------- */
