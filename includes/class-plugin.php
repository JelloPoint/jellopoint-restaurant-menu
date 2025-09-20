<?php
/**
 * JelloPoint Restaurant Menu — Core (Admin menu + taxonomies + Price Label term meta)
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Textdomain
        add_action( 'plugins_loaded', [ $this, 'i18n' ] );

        // Content model
        add_action( 'init', [ $this, 'register_post_type_and_tax' ], 9 );

        // Admin menu
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_head', [ $this, 'hide_parent_duplicate_submenu' ] );
        add_filter( 'parent_file',  [ $this, 'admin_parent_highlight' ] );
        add_filter( 'submenu_file', [ $this, 'admin_submenu_highlight' ], 10, 2 );

        // Price Label term meta + list column
        add_action( 'jprm_label_add_form_fields',  [ $this, 'label_add_fields' ] );
        add_action( 'jprm_label_edit_form_fields', [ $this, 'label_edit_fields' ], 10, 2 );
        add_action( 'created_jprm_label',          [ $this, 'save_label_meta' ] );
        add_action( 'edited_jprm_label',           [ $this, 'save_label_meta' ] );
        add_filter( 'manage_edit-jprm_label_columns', [ $this, 'label_columns' ] );
        add_filter( 'manage_jprm_label_custom_column', [ $this, 'label_column_content' ], 10, 3 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_for_label' ] );
    }

    public function i18n() {
        load_plugin_textdomain( 'jellopoint-restaurant-menu' );
    }

    /** Register CPT + Taxonomies */
    public function register_post_type_and_tax() {
        // CPT: Menu Items
        register_post_type( 'jprm_menu_item', [
            'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'labels'       => [
                'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
                'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
                'add_new_item'  => __( 'Add New Menu Item', 'jellopoint-restaurant-menu' ),
                'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
                'new_item'      => __( 'New Menu Item', 'jellopoint-restaurant-menu' ),
                'menu_name'     => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            // keep duplicates out; we add curated submenu instead
            'show_in_menu' => false,
            'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'map_meta_cap' => true,
        ] );

        // Taxonomy: Menus (non-hierarchical)
        register_taxonomy( 'jprm_menu', [ 'jprm_menu_item' ], [
            'labels'            => [
                'name'      => __( 'Menus', 'jellopoint-restaurant-menu' ),
                'menu_name' => __( 'Menus', 'jellopoint-restaurant-menu' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
        ] );

        // Taxonomy: Sections (hierarchical)
        register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
            'labels'            => [
                'name'      => __( 'Sections', 'jellopoint-restaurant-menu' ),
                'menu_name' => __( 'Sections', 'jellopoint-restaurant-menu' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
        ] );

        // Taxonomy: Price Labels (non-hierarchical) — force menu label
        register_taxonomy( 'jprm_label', [ 'jprm_menu_item' ], [
            'labels'            => [
                'name'      => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                'menu_name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
        ] );
    }

    /** Admin menu: curated, no duplicates */
    public function register_admin_menu() {
        add_menu_page(
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            'edit_posts',
            'jprm_admin',
            [ $this, 'render_admin_page' ],
            'dashicons-food',
            25
        );
        add_submenu_page( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' );
    }

    public function render_admin_page() {
        echo '<div class="wrap"><h1>'. esc_html__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ) .'</h1><p>'. esc_html__( 'Manage Menus, Menu Items, Sections and Price Labels.', 'jellopoint-restaurant-menu' ) .'</p></div>';
    }

    public function hide_parent_duplicate_submenu() {
        remove_submenu_page( 'jprm_admin', 'jprm_admin' );
    }

    public function admin_parent_highlight( $parent ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return $parent;
        if ( 'jprm_menu_item' === ( $screen->post_type ?? '' ) ) return 'jprm_admin';
        if ( 'edit-tags' === ( $screen->base ?? '' ) && in_array( ( $screen->taxonomy ?? '' ), [ 'jprm_menu', 'jprm_label', 'jprm_section' ], true ) ) return 'jprm_admin';
        return $parent;
    }

    public function admin_submenu_highlight( $submenu_file, $parent_file ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( 'jprm_admin' !== $parent_file || ! $screen ) return $submenu_file;
        if ( 'jprm_menu_item' === ( $screen->post_type ?? '' ) ) return 'edit.php?post_type=jprm_menu_item';
        if ( 'edit-tags' === ( $screen->base ?? '' ) ) {
            if ( 'jprm_menu' === ( $screen->taxonomy ?? '' ) )   return 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item';
            if ( 'jprm_section' === ( $screen->taxonomy ?? '' ) ) return 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item';
            if ( 'jprm_label' === ( $screen->taxonomy ?? '' ) )   return 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item';
        }
        return $submenu_file;
    }

    /* === Price Labels: term meta (icon image + CSS class) === */
    public function enqueue_media_for_label( $hook ) {
        if ( empty( $_GET['taxonomy'] ) || $_GET['taxonomy'] !== 'jprm_label' ) return;
        wp_enqueue_media();
        $js = <<<JS
(function($){
  function bind(){
    $(document).on('click','.jprm-upload-icon',function(e){
      e.preventDefault();
      var $w = $(this).closest('.form-field, .jprm-term-meta, tr');
      var frame = wp.media({ title: 'Select Icon', multiple:false, library:{ type:'image' } });
      frame.on('select', function(){
        var a = frame.state().get('selection').first().toJSON();
        var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
        $w.find('.jprm-icon-id').val(a.id);
        $w.find('.jprm-icon-preview').html('<img src="'+url+'" style="height:40px;width:auto;border-radius:3px" />');
        $w.find('.jprm-remove-icon').show();
      });
      frame.open();
    });
    $(document).on('click','.jprm-remove-icon',function(e){
      e.preventDefault();
      var $w = $(this).closest('.form-field, .jprm-term-meta, tr');
      $w.find('.jprm-icon-id').val('');
      $w.find('.jprm-icon-preview').empty();
      $(this).hide();
    });
  }
  $(document).ready(bind);
})(jQuery);
JS;
        wp_add_inline_script( 'jquery-core', $js );
        $css = '.column-jprm_label_icon{width:70px}.jprm-term-meta .jprm-icon-preview img{height:40px;width:auto;border-radius:3px}';
        wp_add_inline_style( 'common', $css );
    }

    public function label_add_fields() {
        ?>
        <div class="form-field jprm-term-meta">
            <label for="jprm_label_icon_id"><?php esc_html_e( 'Icon', 'jellopoint-restaurant-menu' ); ?></label>
            <div class="jprm-icon-preview"></div>
            <input type="hidden" class="jprm-icon-id" name="jprm_label_icon_id" id="jprm_label_icon_id" value="" />
            <p>
                <button class="button jprm-upload-icon"><?php esc_html_e( 'Upload Icon', 'jellopoint-restaurant-menu' ); ?></button>
                <button class="button-secondary jprm-remove-icon" style="display:none;"><?php esc_html_e( 'Remove', 'jellopoint-restaurant-menu' ); ?></button>
            </p>
            <p class="description"><?php esc_html_e( 'Upload a small image to represent this label (e.g., vegan, spicy).', 'jellopoint-restaurant-menu' ); ?></p>
        </div>
        <div class="form-field">
            <label for="jprm_label_icon_class"><?php esc_html_e( 'Icon CSS class (optional)', 'jellopoint-restaurant-menu' ); ?></label>
            <input type="text" name="jprm_label_icon_class" id="jprm_label_icon_class" value="" />
            <p class="description"><?php esc_html_e( 'Alternative to an image: a CSS class like “fas fa-pepper-hot”.', 'jellopoint-restaurant-menu' ); ?></p>
        </div>
        <?php
    }

    public function label_edit_fields( $term, $taxonomy ) {
        $icon_id    = (int) get_term_meta( $term->term_id, '_jprm_icon_id', true );
        $icon_class = (string) get_term_meta( $term->term_id, '_jprm_icon_class', true );
        $thumb      = $icon_id ? wp_get_attachment_image( $icon_id, 'thumbnail', false, [ 'style' => 'height:40px;width:auto;border-radius:3px' ] ) : '';
        ?>
        <tr class="form-field jprm-term-meta">
            <th scope="row"><label for="jprm_label_icon_id"><?php esc_html_e( 'Icon', 'jellopoint-restaurant-menu' ); ?></label></th>
            <td>
                <div class="jprm-icon-preview"><?php echo $thumb ?: ''; ?></div>
                <input type="hidden" class="jprm-icon-id" name="jprm_label_icon_id" id="jprm_label_icon_id" value="<?php echo esc_attr( $icon_id ); ?>" />
                <p>
                    <button class="button jprm-upload-icon"><?php esc_html_e( 'Upload Icon', 'jellopoint-restaurant-menu' ); ?></button>
                    <button class="button-secondary jprm-remove-icon" <?php echo $icon_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'jellopoint-restaurant-menu' ); ?></button>
                </p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="jprm_label_icon_class"><?php esc_html_e( 'Icon CSS class (optional)', 'jellopoint-restaurant-menu' ); ?></label></th>
            <td>
                <input type="text" name="jprm_label_icon_class" id="jprm_label_icon_class" value="<?php echo esc_attr( $icon_class ); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e( 'Alternative to an image: a CSS class like “fas fa-pepper-hot”.', 'jellopoint-restaurant-menu' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_label_meta( $term_id ) {
        if ( isset( $_POST['jprm_label_icon_id'] ) ) {
            update_term_meta( $term_id, '_jprm_icon_id', absint( $_POST['jprm_label_icon_id'] ) );
        }
        if ( isset( $_POST['jprm_label_icon_class'] ) ) {
            update_term_meta( $term_id, '_jprm_icon_class', sanitize_text_field( wp_unslash( $_POST['jprm_label_icon_class'] ) ) );
        }
    }

    public function label_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'name' === $key ) { $new['jprm_label_icon'] = __( 'Icon', 'jellopoint-restaurant-menu' ); }
        }
        return $new;
    }

    public function label_column_content( $content, $column, $term_id ) {
        if ( 'jprm_label_icon' === $column ) {
            $icon_id    = (int) get_term_meta( $term_id, '_jprm_icon_id', true );
            $icon_class = (string) get_term_meta( $term_id, '_jprm_icon_class', true );
            if ( $icon_id ) {
                $img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [ 'style' => 'height:32px;width:auto;border-radius:3px' ] );
                if ( $img ) return $img;
            }
            if ( $icon_class ) return '<span class="'. esc_attr( $icon_class ) .'" aria-hidden="true"></span>';
            return '—';
        }
        return $content;
    }
}

/* Bootstrap */
if ( ! function_exists( __NAMESPACE__ . '\\jprm_bootstrap' ) ) {
    function jprm_bootstrap() { return Plugin::instance(); }
}
jprm_bootstrap();
