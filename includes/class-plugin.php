<?php
/**
 * JelloPoint Restaurant Menu – main plugin class (Admin menu layout v3)
 * Final desired structure:
 * JelloPoint Menu
 * ├─ Menus
 * ├─ Menu Items
 * ├─ Sections
 * └─ Price Labels
 * Also removes any stray/duplicate "Restaurant Menu - Price Labels" or generic "Labels".
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Plugin {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if ( isset( $GLOBALS['jprm_plugin_booted'] ) ) return;
        $GLOBALS['jprm_plugin_booted'] = true;

        add_action( 'plugins_loaded', [ $this, 'i18n' ] );
        add_action( 'init', [ $this, 'register_cpts' ], 9 );
        add_action( 'init', [ $this, 'register_taxonomies' ], 10 );
        add_action( 'init', [ $this, 'register_shortcodes' ] );

        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        // Clean up after everyone else
        add_action( 'admin_menu', [ $this, 'cleanup_submenus' ], 999 );
        add_action( 'admin_head', [ $this, 'cleanup_submenus' ] );

        add_filter( 'parent_file',  [ $this, 'admin_parent_highlight' ] );
        add_filter( 'submenu_file', [ $this, 'admin_submenu_highlight' ], 10, 2 );

        add_action( 'elementor/init', function () {
            add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
            add_action( 'elementor/widgets/register',               [ $this, 'register_widgets_autoload' ] );
            add_action( 'elementor/widgets/widgets_registered',     [ $this, 'register_widgets_autoload_legacy' ] );
        }, 1 );
    }

    public function i18n() { load_plugin_textdomain( 'jellopoint-restaurant-menu' ); }

    /* ===== CPTs ===== */
    public function register_cpts() {
        register_post_type( 'jprm_menu', [
            'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'labels'       => [ 'name' => __( 'Menus', 'jellopoint-restaurant-menu' ), 'singular_name' => __( 'Menu', 'jellopoint-restaurant-menu' ) ],
            'public'       => false, 'show_ui' => true, 'show_in_menu' => 'jprm_admin', 'supports' => [ 'title' ],
            'map_meta_cap' => true, 'rewrite' => false,
        ] );

        register_post_type( 'jprm_menu_item', [
            'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'labels'       => [ 'name' => __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ) ],
            'public'       => false, 'show_ui' => true, 'show_in_menu' => 'jprm_admin',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'map_meta_cap' => true, 'rewrite' => false,
        ] );
    }

    /* ===== Taxonomies ===== */
    public function register_taxonomies() {
        // Price Labels (flat)
        if ( ! taxonomy_exists( 'jprm_label' ) ) {
            register_taxonomy( 'jprm_label', [ 'jprm_menu_item' ], [
                'label'  => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                'labels' => [
                    'name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                    'singular_name' => __( 'Price Label', 'jellopoint-restaurant-menu' ),
                    'menu_name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                ],
                'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => false,
            ] );
        } else {
            register_taxonomy_for_object_type( 'jprm_label', 'jprm_menu_item' );
        }

        // Sections (hierarchical)
        if ( ! taxonomy_exists( 'jprm_section' ) ) {
            register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
                'label'  => __( 'Sections', 'jellopoint-restaurant-menu' ),
                'labels' => [
                    'name' => __( 'Sections', 'jellopoint-restaurant-menu' ),
                    'singular_name' => __( 'Section', 'jellopoint-restaurant-menu' ),
                    'menu_name' => __( 'Sections', 'jellopoint-restaurant-menu' ),
                ],
                'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => true,
            ] );
        } else {
            register_taxonomy_for_object_type( 'jprm_section', 'jprm_menu_item' );
        }
    }

    /* ===== Admin Menu ===== */
    public function register_admin_menu() {
        add_menu_page(
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            'edit_posts', 'jprm_admin', [ $this, 'render_admin_welcome' ], 'dashicons-food', 25
        );

        $this->maybe_add_submenu( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' );
    }

    public function cleanup_submenus() {
        global $submenu;
        // Ensure array exists
        if ( ! isset( $submenu['jprm_admin'] ) || ! is_array( $submenu['jprm_admin'] ) ) return;

        $canon = [
            'menus'    => 'edit.php?post_type=jprm_menu',
            'items'    => 'edit.php?post_type=jprm_menu_item',
            'sections' => 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item',
            'labels'   => 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item',
        ];

        // Remove parent duplicate and "Add New" auto entries
        remove_submenu_page( 'jprm_admin', 'jprm_admin' );
        remove_submenu_page( 'jprm_admin', 'post-new.php?post_type=jprm_menu' );
        remove_submenu_page( 'jprm_admin', 'post-new.php?post_type=jprm_menu_item' );

        // Hard-remove any taxonomy submenus that don't match our canonical slugs,
        // especially ones that say "Restaurant Menu - Price Labels" or generic "Labels".
        foreach ( $submenu['jprm_admin'] as $i => $entry ) {
            $title = isset( $entry[0] ) ? wp_strip_all_tags( $entry[0] ) : '';
            $slug  = isset( $entry[2] ) ? $entry[2] : '';

            // Keep only our canonical four slugs.
            $isCanonical = in_array( $slug, $canon, true );
            if ( ! $isCanonical ) {
                // If this looks like a taxonomy screen for labels/sections under our parent, remove it.
                if ( is_string( $slug ) && false !== strpos( $slug, 'edit-tags.php' ) ) {
                    unset( $submenu['jprm_admin'][ $i ] );
                    continue;
                }
                // If the title contains "Price Labels" but slug is not canonical, remove.
                if ( is_string( $title ) && false !== stripos( $title, 'Price Labels' ) ) {
                    unset( $submenu['jprm_admin'][ $i ] );
                    continue;
                }
                // If it contains the old prefix "Restaurant Menu -", remove.
                if ( is_string( $title ) && false !== stripos( $title, 'Restaurant Menu' ) ) {
                    unset( $submenu['jprm_admin'][ $i ] );
                    continue;
                }
            }
        }

        // Re-index
        $submenu['jprm_admin'] = array_values( $submenu['jprm_admin'] );

        // Guarantee our four canonical entries exist once.
        $this->ensure_unique_submenu( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), $canon['menus'] );
        $this->ensure_unique_submenu( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), $canon['items'] );
        $this->ensure_unique_submenu( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), $canon['sections'] );
        $this->ensure_unique_submenu( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), $canon['labels'] );
    }

    private function ensure_unique_submenu( $parent, $title, $slug ) {
        global $submenu;
        foreach ( $submenu[ $parent ] as $e ) {
            if ( isset( $e[2] ) && $e[2] === $slug ) return;
        }
        $this->maybe_add_submenu( $parent, $title, $title, 'edit_posts', $slug );
    }

    private function maybe_add_submenu( $parent, $page_title, $menu_title, $cap, $menu_slug, $callback = null, $position = null ) {
        global $submenu;
        if ( isset( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ) {
            foreach ( $submenu[ $parent ] as $item ) {
                if ( isset( $item[2] ) && $item[2] === $menu_slug ) return;
            }
        }
        add_submenu_page( $parent, $page_title, $menu_title, $cap, $menu_slug, $callback, $position );
    }

    public function admin_parent_highlight( $parent ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return $parent;
        if ( in_array( ( $screen->post_type ?? '' ), [ 'jprm_menu', 'jprm_menu_item' ], true ) ) return 'jprm_admin';
        if ( 'edit-tags' === ( $screen->base ?? '' ) && in_array( ( $screen->taxonomy ?? '' ), [ 'jprm_label', 'jprm_section' ], true ) ) return 'jprm_admin';
        return $parent;
    }

    public function admin_submenu_highlight( $submenu_file, $parent_file ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( 'jprm_admin' !== $parent_file || ! $screen ) return $submenu_file;
        if ( 'jprm_menu' === ( $screen->post_type ?? '' ) ) return 'edit.php?post_type=jprm_menu';
        if ( 'jprm_menu_item' === ( $screen->post_type ?? '' ) ) return 'edit.php?post_type=jprm_menu_item';
        if ( 'edit-tags' === ( $screen->base ?? '' ) && 'jprm_section' === ( $screen->taxonomy ?? '' ) ) return 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
        if ( 'edit-tags' === ( $screen->base ?? '' ) && 'jprm_label' === ( $screen->taxonomy ?? '' ) ) return 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item';
        return $submenu_file;
    }

    public function render_admin_welcome() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ); ?></h1>
            <p><?php esc_html_e( 'Manage Menus, Menu Items, Sections and Price Labels.', 'jellopoint-restaurant-menu' ); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=jprm_menu' ) ); ?>"><?php esc_html_e( 'Manage Menus', 'jellopoint-restaurant-menu' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=jprm_menu_item' ) ); ?>"><?php esc_html_e( 'Manage Menu Items', 'jellopoint-restaurant-menu' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' ) ); ?>"><?php esc_html_e( 'Manage Sections', 'jellopoint-restaurant-menu' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' ) ); ?>"><?php esc_html_e( 'Manage Price Labels', 'jellopoint-restaurant-menu' ); ?></a>
            </p>
        </div>
        <?php
    }

    /* ===== Elementor ===== */
    public function register_category( $elements_manager ) {
        $slug = 'jellopoint-widgets';
        $categories = method_exists( $elements_manager, 'get_categories' ) ? $elements_manager->get_categories() : [];
        if ( ! isset( $categories[ $slug ] ) ) {
            $elements_manager->add_category( $slug, [ 'title' => __( 'JelloPoint Widgets', 'jellopoint-restaurant-menu' ), 'icon' => 'fa fa-plug' ] );
        }
    }
    public function register_widgets_autoload( $widgets_manager ) {
        $classes = $this->autoload_widgets();
        foreach ( $classes as $class ) { $widgets_manager->register( new $class() ); }
    }
    public function register_widgets_autoload_legacy() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) ) return;
        $classes = $this->autoload_widgets();
        foreach ( $classes as $class ) { \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new $class() ); }
    }
    private function autoload_widgets() {
        if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) return [];
        $widgets_dir = plugin_dir_path( __FILE__ ) . 'widgets/';
        if ( ! is_dir( $widgets_dir ) ) return [];
        $before = get_declared_classes();
        foreach ( glob( $widgets_dir . '*.php' ) as $file ) if ( is_readable( $file ) ) require_once $file;
        $after = get_declared_classes();
        $new = array_diff( $after, $before );
        $found = [];
        foreach ( $new as $fqcn ) if ( is_subclass_of( $fqcn, '\\Elementor\\Widget_Base' ) ) $found[] = $fqcn;
        return $found;
    }

    /* ===== Shortcode (kept minimal) ===== */
    public function register_shortcodes() { add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] ); }
    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [ 'menu' => 0, 'id' => 0, 'sections' => '' ], $atts, 'jprm_menu' );
        $menu_id = absint( $atts['menu'] ?: $atts['id'] );
        if ( ! $menu_id ) return '';
        $title = get_the_title( $menu_id );
        if ( ! $title ) return '';
        ob_start(); ?>
        <div class="jprm-menu" data-menu-id="<?php echo (int) $menu_id; ?>">
            <div class="jprm-menu__title"><?php echo esc_html( $title ); ?></div>
        </div>
        <?php return ob_get_clean();
    }
}

/* Bootstrap */
if ( ! function_exists( __NAMESPACE__ . '\\jprm_bootstrap' ) ) { function jprm_bootstrap() { return Plugin::instance(); } }
jprm_bootstrap();
