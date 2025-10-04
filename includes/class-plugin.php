<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined('ABSPATH') ) { exit; }

class Plugin {

    /** Our desired parent slug */
    const PARENT_SLUG = 'jellopoint';

    public static function init() : void {
        add_action( 'admin_menu', [ __CLASS__, 'ensure_parent_menu' ], 5 ); // create parent early if missing

        add_action( 'init', [ __CLASS__, 'register_types' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

        // Elementor integration (safe if Elementor not active)
        add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

        // Styles
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
    }

    /**
     * Ensure a stable parent menu exists. If something else in your stack already
     * creates a "JelloPoint" root menu with slug 'jellopoint', we won't duplicate it.
     * If not found, we create a lightweight parent so CPT + subpages have a home.
     */
    public static function ensure_parent_menu() : void {
        global $menu;
        $has_parent = false;

        if ( is_array( $menu ) ) {
            foreach ( $menu as $m ) {
                if ( isset($m[2]) && $m[2] === self::PARENT_SLUG ) {
                    $has_parent = true;
                    break;
                }
            }
        }

        if ( ! $has_parent ) {
            add_menu_page(
                __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
                __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
                'manage_options',
                self::PARENT_SLUG,
                '__return_null', // no page, it just acts as a hub
                'dashicons-admin-generic',
                58 // position near the top
            );
        }
    }

    public static function register_types() : void {
        if ( post_type_exists('jprm_menu_item') ) return;

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
            // Put CPT under the JelloPoint parent we ensure above
            'show_in_menu'  => self::PARENT_SLUG,
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
        wp_enqueue_style( 'jprm-menu' );
    }

    public static function register_elementor_category( $elements_manager ) : void {
        if ( ! is_object( $elements_manager ) ) return;
        $elements_manager->add_category( 'jellopoint-widgets', [
            'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
            'icon'  => 'fa fa-plug',
        ] );
    }

    public static function register_elementor_widget( $widgets_manager ) : void {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

        if ( defined('JPRM_PLUGIN_PATH') ) {
            $file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }

        if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) && is_object( $widgets_manager ) ) {
            $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
        }
    }
}
