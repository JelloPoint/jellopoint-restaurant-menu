<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Clean v3 schema — Elementor widget & admin for restaurant menu items.
 * Version:     3.0.0
 * Author:      JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined('ABSPATH') ) { exit; }

define( 'JPRM_VERSION', '3.0.0' );
define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'JPRM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Boot
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';

// Data (labels admin)
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';

// Admin editor
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php'; // optional central writer

// Elementor Widget (you already have the clean widget file in includes/widgets/)
require_once JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';

// Init
\JelloPoint\RestaurantMenu\Plugin::init();