<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined('ABSPATH') ) { exit; }

class Plugin {

    public static function init() : void {
        add_action( 'init', [ __CLASS__, 'register_types' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );
        add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
    }

    public static function register_types() : void {
        if ( post_type_exists('jprm_menu_item') ) return;

        // Parent menu slug for nesting under JelloPoint root in admin
        $parent_menu_slug = 'jellopoint';

        register_post_type( 'jprm_menu_item', [
            'label'         => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'labels'        => [
                'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
                'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
                'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
                'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
            ],
            'public'        => true,
            'show_ui'       => true,
            'show_in_menu'  => $parent_menu_slug, // keep under JelloPoint
            'show_in_rest'  => true,
            'supports'      => [ 'title', 'page-attributes' ],
            'has_archive'   => false,
            'rewrite'       => [ 'slug' => 'menu-item' ],
            'menu_icon'     => 'dashicons-carrot',
        ] );
    }

    public static function register_taxonomies() : void {
        if ( ! taxonomy_exists('jprm_menu') ) {
            register_taxonomy( 'jprm_menu', [ 'jprm_menu_item' ], [
                'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'hierarchical' => true,
            ] );
        }
        if ( ! taxonomy_exists('jprm_section') ) {
            register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
                'label'        => __( 'Sections', 'jellopoint-restaurant-menu' ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'hierarchical' => true,
            ] );
        }
    }

    public static function register_assets() : void {
        if ( ! defined('JPRM_PLUGIN_URL') ) return;
        wp_register_style(
            'jprm-menu',
            JPRM_PLUGIN_URL . 'includes/render/css/menu.css',
            [],
            defined('JPRM_VERSION') ? JPRM_VERSION : null
        );
    }

    public static function enqueue_editor_styles() : void {
        // Ensure styling also loads inside Elementor editor preview
        wp_enqueue_style( 'jprm-menu' );
    }

    public static function register_elementor_category( $elements_manager ) : void {
        $elements_manager->add_category( 'jellopoint-widgets', [
            'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
            'icon'  => 'fa fa-plug',
        ] );
    }

    public static function register_elementor_widget( $widgets_manager ) : void {
        // Widget class must be already included by the main plugin file
        if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
            $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
        }
    }
}