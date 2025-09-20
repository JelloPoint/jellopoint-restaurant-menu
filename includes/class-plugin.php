<?php
/**
 * JelloPoint Restaurant Menu – main plugin class (Admin + Relationships v4)
 * Admin structure:
 * JelloPoint Menu
 * ├─ Menus
 * ├─ Menu Items
 * ├─ Sections
 * └─ Price Labels
 *
 * Adds:
 * - Menus → metabox "Menu Composition" to pick Sections (multi)
 * - Menu Items → metabox "Menu Membership" to pick Menus (multi)
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Plugin {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        if ( isset( $GLOBALS['jprm_plugin_booted'] ) ) return;
        $GLOBALS['jprm_plugin_booted'] = true;

        add_action( 'plugins_loaded', [ $this, 'i18n' ] );
        add_action( 'init', [ $this, 'register_cpts' ], 9 );
        add_action( 'init', [ $this, 'register_taxonomies' ], 10 );
        add_action( 'init', [ $this, 'register_shortcodes' ] );

        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_menu', [ $this, 'cleanup_submenus' ], 999 );
        add_action( 'admin_head', [ $this, 'cleanup_submenus' ] );

        add_filter( 'parent_file',  [ $this, 'admin_parent_highlight' ] );
        add_filter( 'submenu_file', [ $this, 'admin_submenu_highlight' ], 10, 2 );

        // Meta boxes
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes_items' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes_menus' ] );
        add_action( 'save_post',      [ $this, 'save_item_meta' ], 10, 2 );
        add_action( 'save_post',      [ $this, 'save_menu_meta' ], 10, 2 );

        // Elementor
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
        if ( ! taxonomy_exists( 'jprm_label' ) ) {
            register_taxonomy( 'jprm_label', [ 'jprm_menu_item' ], [
                'label'  => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                'labels' => [ 'name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'singular_name' => __( 'Price Label', 'jellopoint-restaurant-menu' ), 'menu_name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ) ],
                'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => false,
            ] );
        } else {
            register_taxonomy_for_object_type( 'jprm_label', 'jprm_menu_item' );
        }

        if ( ! taxonomy_exists( 'jprm_section' ) ) {
            register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
                'label'  => __( 'Sections', 'jellopoint-restaurant-menu' ),
                'labels' => [ 'name' => __( 'Sections', 'jellopoint-restaurant-menu' ), 'singular_name' => __( 'Section', 'jellopoint-restaurant-menu' ), 'menu_name' => __( 'Sections', 'jellopoint-restaurant-menu' ) ],
                'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => true,
            ] );
        } else {
            register_taxonomy_for_object_type( 'jprm_section', 'jprm_menu_item' );
        }
    }

    /* ===== Admin Menu ===== */
    public function register_admin_menu() {
        add_menu_page( __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), 'edit_posts', 'jprm_admin', [ $this, 'render_admin_welcome' ], 'dashicons-food', 25 );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' );
    }

    public function cleanup_submenus() {
        remove_submenu_page( 'jprm_admin', 'jprm_admin' );
        remove_submenu_page( 'jprm_admin', 'post-new.php?post_type=jprm_menu' );
        remove_submenu_page( 'jprm_admin', 'post-new.php?post_type=jprm_menu_item' );
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
        </div>
        <?php
    }

    /* ===== Meta boxes: Menu Items ===== */

    public function add_meta_boxes_items() {
        add_meta_box( 'jprm_item_meta', __( 'Menu Item Settings', 'jellopoint-restaurant-menu' ), [ $this, 'render_item_metabox' ], 'jprm_menu_item', 'normal', 'high' );
        add_meta_box( 'jprm_item_menus', __( 'Menu Membership', 'jellopoint-restaurant-menu' ), [ $this, 'render_item_menus_metabox' ], 'jprm_menu_item', 'side', 'default' );
    }

    public function render_item_metabox( $post ) {
        wp_nonce_field( 'jprm_save_item_meta', 'jprm_item_meta_nonce' );
        $price        = get_post_meta( $post->ID, '_jprm_price', true );
        $price_label  = get_post_meta( $post->ID, '_jprm_price_label', true );
        $price_label_custom = get_post_meta( $post->ID, '_jprm_price_label_custom', true );
        $badge        = get_post_meta( $post->ID, '_jprm_badge', true );
        $badge_pos    = get_post_meta( $post->ID, '_jprm_badge_position', true );
        $separator    = get_post_meta( $post->ID, '_jprm_separator', true );
        $visible      = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc         = get_post_meta( $post->ID, '_jprm_desc', true );

        if ( empty( $badge_pos ) ) $badge_pos = 'corner-right';
        $badge_options = [ 'corner-left'=>__( 'Corner (left)', 'jellopoint-restaurant-menu' ), 'corner-right'=>__( 'Corner (right)', 'jellopoint-restaurant-menu' ), 'inline'=>__( 'Inline', 'jellopoint-restaurant-menu' ) ];
        ?>
        <style>.jprm-table{width:100%;border-collapse:collapse}.jprm-table th,.jprm-table td{padding:6px 8px;border-bottom:1px solid #e5e5e5;vertical-align:middle}.jprm-table th{text-align:left;width:160px}.jprm-muted{color:#666}</style>
        <table class="jprm-table"><tbody>
            <tr><th><label for="jprm_price"><?php esc_html_e( 'Price', 'jellopoint-restaurant-menu' ); ?></label></th><td><input type="text" id="jprm_price" name="jprm_price" value="<?php echo esc_attr( $price ); ?>" placeholder="€ 7,50" /></td></tr>
            <tr><th><label for="jprm_price_label"><?php esc_html_e( 'Price Label', 'jellopoint-restaurant-menu' ); ?></label></th><td><select id="jprm_price_label" name="jprm_price_label"><option value=""><?php esc_html_e( 'Select…', 'jellopoint-restaurant-menu' ); ?></option><option value="custom" <?php selected( (string)$price_label, 'custom' ); ?>><?php esc_html_e( 'Custom', 'jellopoint-restaurant-menu' ); ?></option></select> <input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="<?php echo esc_attr( $price_label_custom ); ?>" placeholder="<?php esc_attr_e( 'Custom label', 'jellopoint-restaurant-menu' ); ?>" /></td></tr>
            <tr><th><label for="jprm_badge"><?php esc_html_e( 'Badge Text', 'jellopoint-restaurant-menu' ); ?></label></th><td><input type="text" id="jprm_badge" name="jprm_badge" value="<?php echo esc_attr( $badge ); ?>" /> <select name="jprm_badge_position" id="jprm_badge_position"><?php foreach($badge_options as $k=>$l){ echo '<option value="'.esc_attr($k).'" '.selected($badge_pos,$k,false).'>'.esc_html($l).'</option>'; } ?></select></td></tr>
            <tr><th><label for="jprm_separator"><?php esc_html_e( 'Separator', 'jellopoint-restaurant-menu' ); ?></label></th><td><input type="text" id="jprm_separator" name="jprm_separator" value="<?php echo esc_attr( $separator ); ?>" placeholder="·" /></td></tr>
            <tr><th><label for="jprm_visible"><?php esc_html_e( 'Visible', 'jellopoint-restaurant-menu' ); ?></label></th><td><label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="1" <?php checked( (bool)$visible ); ?> /> <?php esc_html_e( 'Show this item on the site', 'jellopoint-restaurant-menu' ); ?></label></td></tr>
            <tr><th><label for="jprm_desc"><?php esc_html_e( 'Short Description', 'jellopoint-restaurant-menu' ); ?></label></th><td><textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%;"><?php echo esc_textarea( $desc ); ?></textarea></td></tr>
        </tbody></table>
        <?php
    }

    public function render_item_menus_metabox( $post ) {
        wp_nonce_field( 'jprm_save_item_menus', 'jprm_item_menus_nonce' );
        $current = (array) get_post_meta( $post->ID, '_jprm_item_menus', true );
        $menus = get_posts([ 'post_type'=>'jprm_menu', 'posts_per_page'=>200, 'orderby'=>'title', 'order'=>'ASC', 'post_status'=>'publish' ]);
        echo '<p>'. esc_html__( 'Select one or more Menus this item belongs to.', 'jellopoint-restaurant-menu' ) .'</p>';
        echo '<select name="jprm_item_menus[]" multiple size="6" style="width:100%;">';
        foreach ( $menus as $m ) {
            $sel = in_array( $m->ID, $current, true ) ? ' selected' : '';
            echo '<option value="'. (int)$m->ID .'"'. $sel .'>'. esc_html( $m->post_title ? $m->post_title : ('#'.$m->ID) ) .'</option>';
        }
        echo '</select>';
    }

    public function save_item_meta( $post_id, $post ) {
        if ( $post->post_type !== 'jprm_menu_item' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset($_POST['jprm_item_meta_nonce']) && wp_verify_nonce( $_POST['jprm_item_meta_nonce'], 'jprm_save_item_meta' ) ) {
            $get_text = function( $k ) { return isset( $_POST[$k] ) ? wp_kses_post( wp_unslash( $_POST[$k] ) ) : ''; };
            $get_bool = function( $k ) { return isset( $_POST[$k] ) ? 1 : 0; };
            update_post_meta( $post_id, '_jprm_price',               $get_text( 'jprm_price' ) );
            update_post_meta( $post_id, '_jprm_price_label',         sanitize_text_field( $get_text( 'jprm_price_label' ) ) );
            update_post_meta( $post_id, '_jprm_price_label_custom',  sanitize_text_field( $get_text( 'jprm_price_label_custom' ) ) );
            update_post_meta( $post_id, '_jprm_badge',               sanitize_text_field( $get_text( 'jprm_badge' ) ) );
            update_post_meta( $post_id, '_jprm_badge_position',      sanitize_text_field( $get_text( 'jprm_badge_position' ) ) );
            update_post_meta( $post_id, '_jprm_separator',           sanitize_text_field( $get_text( 'jprm_separator' ) ) );
            update_post_meta( $post_id, '_jprm_visible',             $get_bool( 'jprm_visible' ) );
            update_post_meta( $post_id, '_jprm_desc',                $get_text( 'jprm_desc' ) );
        }

        if ( isset($_POST['jprm_item_menus_nonce']) && wp_verify_nonce( $_POST['jprm_item_menus_nonce'], 'jprm_save_item_menus' ) ) {
            $vals = isset($_POST['jprm_item_menus']) ? (array) $_POST['jprm_item_menus'] : [];
            $vals = array_map( 'absint', $vals );
            $vals = array_values( array_unique( array_filter( $vals ) ) );
            update_post_meta( $post_id, '_jprm_item_menus', $vals );
        }
    }

    /* ===== Meta boxes: Menus ===== */

    public function add_meta_boxes_menus() {
        add_meta_box( 'jprm_menu_sections', __( 'Menu Composition', 'jellopoint-restaurant-menu' ), [ $this, 'render_menu_sections_metabox' ], 'jprm_menu', 'normal', 'default' );
    }

    public function render_menu_sections_metabox( $post ) {
        wp_nonce_field( 'jprm_save_menu_sections', 'jprm_menu_sections_nonce' );
        $selected = (array) get_post_meta( $post->ID, '_jprm_menu_sections', true );
        $terms = get_terms([ 'taxonomy'=>'jprm_section', 'hide_empty'=>false, 'parent'=>0 ]); // top-level sections; children still match in queries
        echo '<p>'. esc_html__( 'Pick the Sections that make up this Menu.', 'jellopoint-restaurant-menu' ) .'</p>';
        echo '<div style="max-height:240px;overflow:auto;border:1px solid #ddd;padding:8px;background:#fff">';
        foreach ( $terms as $t ) {
            $chk = in_array( $t->term_id, $selected, true ) ? ' checked' : '';
            echo '<label style="display:block;margin:.25em 0;"><input type="checkbox" name="jprm_menu_sections[]" value="'. (int)$t->term_id .'"'. $chk .' /> '. esc_html( $t->name ) .' (#'. (int)$t->term_id .')</label>';
        }
        echo '</div>';
        echo '<p class="description">'. esc_html__( 'Items appear in a Menu if they are in any of the selected Sections OR explicitly assigned to this Menu on the item screen.', 'jellopoint-restaurant-menu' ) .'</p>';
    }

    public function save_menu_meta( $post_id, $post ) {
        if ( $post->post_type !== 'jprm_menu' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset($_POST['jprm_menu_sections_nonce']) && wp_verify_nonce( $_POST['jprm_menu_sections_nonce'], 'jprm_save_menu_sections' ) ) {
            $vals = isset($_POST['jprm_menu_sections']) ? (array) $_POST['jprm_menu_sections'] : [];
            $vals = array_map( 'absint', $vals );
            $vals = array_values( array_unique( array_filter( $vals ) ) );
            update_post_meta( $post_id, '_jprm_menu_sections', $vals );
        }
    }

    /* ===== Elementor ===== */
    public function register_category( $elements_manager ) {
        $slug = 'jellopoint-widgets';
        $categories = method_exists( $elements_manager, 'get_categories' ) ? $elements_manager->get_categories() : [];
        if ( ! isset( $categories[ $slug ] ) ) $elements_manager->add_category( $slug, [ 'title' => __( 'JelloPoint Widgets', 'jellopoint-restaurant-menu' ), 'icon' => 'fa fa-plug' ] );
    }
    public function register_widgets_autoload( $widgets_manager ) { $classes = $this->autoload_widgets(); foreach ( $classes as $class ) $widgets_manager->register( new $class() ); }
    public function register_widgets_autoload_legacy() { if ( ! class_exists( '\\Elementor\\Plugin' ) ) return; $classes = $this->autoload_widgets(); foreach ( $classes as $class ) \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new $class() ); }
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

    /* ===== Shortcode (still minimal) ===== */
    public function register_shortcodes() { add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] ); }

    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [ 'menu'=>0, 'id'=>0, 'sections'=>'' ], $atts, 'jprm_menu' );
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
