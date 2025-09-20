<?php
/**
 * JelloPoint Restaurant Menu – main plugin class
 * - Keeps Menus as TAXONOMY (so items can belong to multiple menus)
 * - Restores FULL Menu Item meta boxes (Price, Multiple Prices, Labels, Badge, etc.)
 * - Admin left menu (cutlery):
 *   Menus, Menu Items, Sections, Price Labels
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

        // Full meta boxes for Menu Items
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ $this, 'save_meta' ], 10, 2 );

        // Elementor (deferred)
        add_action( 'elementor/init', function () {
            add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
            add_action( 'elementor/widgets/register',               [ $this, 'register_widgets_autoload' ] );
            add_action( 'elementor/widgets/widgets_registered',     [ $this, 'register_widgets_autoload_legacy' ] );
        }, 1 );
    }

    public function i18n() { load_plugin_textdomain( 'jellopoint-restaurant-menu' ); }

    /* ===== CPTs ===== */
    public function register_cpts() {
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
            'show_in_menu' => 'jprm_admin',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'map_meta_cap' => true,
            'rewrite'      => false,
        ] );
    }

    /* ===== Taxonomies (Menus as TAXONOMY) ===== */
    public function register_taxonomies() {
        // Menus (non-hierarchical)
        if ( ! taxonomy_exists( 'jprm_menu' ) ) {
            register_taxonomy(
                'jprm_menu',
                [ 'jprm_menu_item' ],
                [
                    'label'             => __( 'Menus', 'jellopoint-restaurant-menu' ),
                    'labels'            => [
                        'name'          => __( 'Menus', 'jellopoint-restaurant-menu' ),
                        'singular_name' => __( 'Menu', 'jellopoint-restaurant-menu' ),
                        'search_items'  => __( 'Search Menus', 'jellopoint-restaurant-menu' ),
                        'all_items'     => __( 'All Menus', 'jellopoint-restaurant-menu' ),
                        'edit_item'     => __( 'Edit Menu', 'jellopoint-restaurant-menu' ),
                        'update_item'   => __( 'Update Menu', 'jellopoint-restaurant-menu' ),
                        'add_new_item'  => __( 'Add New Menu', 'jellopoint-restaurant-menu' ),
                        'new_item_name' => __( 'New Menu Name', 'jellopoint-restaurant-menu' ),
                        'menu_name'     => __( 'Menus', 'jellopoint-restaurant-menu' ),
                    ],
                    'public'            => false,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'hierarchical'      => false,
                ]
            );
        } else {
            register_taxonomy_for_object_type( 'jprm_menu', 'jprm_menu_item' );
        }

        // Sections (hierarchical)
        if ( ! taxonomy_exists( 'jprm_section' ) ) {
            register_taxonomy(
                'jprm_section',
                [ 'jprm_menu_item' ],
                [
                    'label'             => __( 'Sections', 'jellopoint-restaurant-menu' ),
                    'labels'            => [
                        'name'              => __( 'Sections', 'jellopoint-restaurant-menu' ),
                        'singular_name'     => __( 'Section', 'jellopoint-restaurant-menu' ),
                        'search_items'      => __( 'Search Sections', 'jellopoint-restaurant-menu' ),
                        'all_items'         => __( 'All Sections', 'jellopoint-restaurant-menu' ),
                        'parent_item'       => __( 'Parent Section', 'jellopoint-restaurant-menu' ),
                        'parent_item_colon' => __( 'Parent Section:', 'jellopoint-restaurant-menu' ),
                        'edit_item'         => __( 'Edit Section', 'jellopoint-restaurant-menu' ),
                        'update_item'       => __( 'Update Section', 'jellopoint-restaurant-menu' ),
                        'add_new_item'      => __( 'Add New Section', 'jellopoint-restaurant-menu' ),
                        'new_item_name'     => __( 'New Section Name', 'jellopoint-restaurant-menu' ),
                        'menu_name'         => __( 'Sections', 'jellopoint-restaurant-menu' ),
                    ],
                    'public'            => false,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'hierarchical'      => true,
                ]
            );
        } else {
            register_taxonomy_for_object_type( 'jprm_section', 'jprm_menu_item' );
        }

        // Price Labels (flat)
        if ( ! taxonomy_exists( 'jprm_label' ) ) {
            register_taxonomy(
                'jprm_label',
                [ 'jprm_menu_item' ],
                [
                    'label'             => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                    'labels'            => [
                        'name'          => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                        'singular_name' => __( 'Price Label', 'jellopoint-restaurant-menu' ),
                        'search_items'  => __( 'Search Price Labels', 'jellopoint-restaurant-menu' ),
                        'all_items'     => __( 'All Price Labels', 'jellopoint-restaurant-menu' ),
                        'edit_item'     => __( 'Edit Price Label', 'jellopoint-restaurant-menu' ),
                        'update_item'   => __( 'Update Price Label', 'jellopoint-restaurant-menu' ),
                        'add_new_item'  => __( 'Add New Price Label', 'jellopoint-restaurant-menu' ),
                        'new_item_name' => __( 'New Price Label Name', 'jellopoint-restaurant-menu' ),
                        'menu_name'     => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                    ],
                    'public'            => false,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'hierarchical'      => false,
                ]
            );
        } else {
            register_taxonomy_for_object_type( 'jprm_label', 'jprm_menu_item' );
        }
    }

    /* ===== Admin Menu ===== */
    public function register_admin_menu() {
        add_menu_page(
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            'edit_posts', 'jprm_admin', [ $this, 'render_admin_welcome' ], 'dashicons-food', 25
        );

        $this->maybe_add_submenu( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        $this->maybe_add_submenu( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' );
    }

    public function cleanup_submenus() {
        global $submenu;
        remove_submenu_page( 'jprm_admin', 'jprm_admin' );
        remove_submenu_page( 'jprm_admin', 'post-new.php?post_type=jprm_menu_item' );

        if ( ! isset( $submenu['jprm_admin'] ) || ! is_array( $submenu['jprm_admin'] ) ) return;

        $canonical = [
            'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item',
            'edit.php?post_type=jprm_menu_item',
            'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item',
            'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item',
        ];

        foreach ( $submenu['jprm_admin'] as $i => $entry ) {
            $slug = isset( $entry[2] ) ? $entry[2] : '';
            $title = isset( $entry[0] ) ? wp_strip_all_tags( $entry[0] ) : '';
            if ( ! in_array( $slug, $canonical, true ) ) {
                if ( is_string( $title ) && ( stripos( $title, 'Restaurant Menu' ) !== false || stripos( $title, 'Price Labels' ) !== false || stripos( $title, 'Labels' ) !== false ) ) {
                    unset( $submenu['jprm_admin'][ $i ] );
                }
            }
        }
        $submenu['jprm_admin'] = array_values( $submenu['jprm_admin'] );

        $this->ensure_submenu( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), $canonical[0] );
        $this->ensure_submenu( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), $canonical[1] );
        $this->ensure_submenu( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), $canonical[2] );
        $this->ensure_submenu( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), $canonical[3] );
    }

    private function ensure_submenu( $parent, $title, $slug ) {
        global $submenu;
        foreach ( (array) $submenu[ $parent ] as $e ) {
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

    public function render_admin_welcome() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ); ?></h1>
            <p><?php esc_html_e( 'Manage Menus, Menu Items, Sections and Price Labels.', 'jellopoint-restaurant-menu' ); ?></p>
        </div>
        <?php
    }

    /* ===== Meta boxes for Menu Items (FULL) ===== */

    public function add_meta_boxes() {
        add_meta_box(
            'jprm_item_meta',
            __( 'Menu Item Settings', 'jellopoint-restaurant-menu' ),
            [ $this, 'render_item_metabox' ],
            'jprm_menu_item',
            'normal',
            'high'
        );
    }

    public function render_item_metabox( $post ) {
        wp_nonce_field( 'jprm_save_meta', 'jprm_meta_nonce' );

        $price        = get_post_meta( $post->ID, '_jprm_price', true );
        $price_label  = get_post_meta( $post->ID, '_jprm_price_label', true );
        $price_label_custom = get_post_meta( $post->ID, '_jprm_price_label_custom', true );
        $multi        = (bool) get_post_meta( $post->ID, '_jprm_multi', true );
        $multi_rows   = get_post_meta( $post->ID, '_jprm_multi_rows', true );
        $badge        = get_post_meta( $post->ID, '_jprm_badge', true );
        $badge_pos    = get_post_meta( $post->ID, '_jprm_badge_position', true );
        $separator    = get_post_meta( $post->ID, '_jprm_separator', true );
        $visible      = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc         = get_post_meta( $post->ID, '_jprm_desc', true );

        if ( ! is_array( $multi_rows ) ) {
            $decoded    = json_decode( (string) $multi_rows, true );
            $multi_rows = is_array( $decoded ) ? $decoded : [];
        }

        $preset_map = apply_filters( 'jprm_price_label_full_map', [
            'small'  => [ 'label_custom' => __( 'Small', 'jellopoint-restaurant-menu' ),  'amount' => '' ],
            'medium' => [ 'label_custom' => __( 'Medium', 'jellopoint-restaurant-menu' ), 'amount' => '' ],
            'large'  => [ 'label_custom' => __( 'Large', 'jellopoint-restaurant-menu' ),  'amount' => '' ],
        ] );

        $badge_options = [
            'corner-left'  => __( 'Corner (left)', 'jellopoint-restaurant-menu' ),
            'corner-right' => __( 'Corner (right)', 'jellopoint-restaurant-menu' ),
            'inline'       => __( 'Inline (next to title)', 'jellopoint-restaurant-menu' ),
        ];
        if ( empty( $badge_pos ) ) $badge_pos = 'corner-right';
        ?>
        <style>
            .jprm-table { width:100%; border-collapse: collapse; }
            .jprm-table th, .jprm-table td { padding:6px 8px; border-bottom:1px solid #e5e5e5; vertical-align: middle; }
            .jprm-table th { text-align:left; width: 160px; }
            .jprm-multi-table td{ vertical-align: middle; }
            .jprm-multi-table input[type="text"]{ width: 100%; }
            .jprm-badge-pos { min-width:220px; }
            .jprm-muted { color:#666; }
        </style>

        <table class="jprm-table">
            <tbody>
                <tr>
                    <th><label for="jprm_price"><?php esc_html_e( 'Price', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <input type="text" id="jprm_price" name="jprm_price" value="<?php echo esc_attr( $price ); ?>" placeholder="€ 7,50" />
                        <span class="jprm-muted"><?php esc_html_e( 'Leave empty if using Multiple Prices.', 'jellopoint-restaurant-menu' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_price_label"><?php esc_html_e( 'Price Label', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <select id="jprm_price_label" name="jprm_price_label">
                            <option value=""><?php esc_html_e( 'Select…', 'jellopoint-restaurant-menu' ); ?></option>
                            <?php
                            $cur = (string) $price_label;
                            foreach ( $preset_map as $slug => $row ) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr( $slug ),
                                    selected( $cur, $slug, false ),
                                    esc_html( isset( $row['label_custom'] ) ? $row['label_custom'] : ucfirst( $slug ) )
                                );
                            }
                            ?>
                            <option value="custom"<?php selected( $cur, 'custom' ); ?>><?php esc_html_e( 'Custom', 'jellopoint-restaurant-menu' ); ?></option>
                        </select>
                        <input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="<?php echo esc_attr( $price_label_custom ); ?>" placeholder="<?php esc_attr_e( 'Custom label', 'jellopoint-restaurant-menu' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_multi"><?php esc_html_e( 'Enable Multiple Prices', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="jprm_multi" name="jprm_multi" value="1" <?php checked( $multi ); ?> />
                            <?php esc_html_e( 'Enable multiple prices (enter rows below)', 'jellopoint-restaurant-menu' ); ?>
                        </label>

                        <div id="jprm_multi_wrap" style="<?php echo $multi ? '' : 'display:none;'; ?>margin-top:10px;">
                            <table class="widefat fixed striped jprm-multi-table" id="jprm_multi_table">
                                <thead>
                                    <tr>
                                        <th style="width:25%"><?php esc_html_e( 'Label', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th style="width:25%"><?php esc_html_e( 'Amount', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th style="width:10%"><?php esc_html_e( 'Hide Icon', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th><?php esc_html_e( 'Actions', 'jellopoint-restaurant-menu' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                if ( empty( $multi_rows ) ) {
                                    $multi_rows = [];
                                }
                                if ( empty( $multi_rows ) ) :
                                ?>
                                    <tr>
                                        <td><input type="text" class="label-custom regular-text" value="" placeholder="<?php esc_attr_e( 'Small / Glass / etc.', 'jellopoint-restaurant-menu' ); ?>" /></td>
                                        <td><input type="text" class="amount regular-text" value="" placeholder="€ 7,50" /></td>
                                        <td><input type="checkbox" class="hide-icon" /></td>
                                        <td><a href="#" class="button button-secondary jprm-row-remove"><?php esc_html_e( 'Remove', 'jellopoint-restaurant-menu' ); ?></a></td>
                                    </tr>
                                <?php
                                else :
                                    foreach ( $multi_rows as $row ) {
                                        $lc = isset( $row['label_custom'] ) ? $row['label_custom'] : '';
                                        $am = isset( $row['amount'] ) ? $row['amount'] : '';
                                        $hi = ! empty( $row['hide_icon'] );
                                        echo '<tr>';
                                        echo '<td><input type="text" class="label-custom regular-text" value="' . esc_attr( $lc ) . '" /></td>';
                                        echo '<td><input type="text" class="amount regular-text" value="' . esc_attr( $am ) . '" placeholder="€ 7,50" /></td>';
                                        echo '<td><input type="checkbox" class="hide-icon" ' . ( $hi ? 'checked' : '' ) . ' /></td>';
                                        echo '<td><a href="#" class="button button-secondary jprm-row-remove">' . esc_html__( 'Remove', 'jellopoint-restaurant-menu' ) . '</a></td>';
                                        echo '</tr>';
                                    }
                                endif;
                                ?>
                                </tbody>
                            </table>
                            <p><a href="#" class="button" id="jprm_row_add"><?php esc_html_e( 'Add another price', 'jellopoint-restaurant-menu' ); ?></a></p>
                            <p class="description"><?php esc_html_e( 'Rows derive their label from preset unless “Custom” is selected.', 'jellopoint-restaurant-menu' ); ?></p>
                            <input type="hidden" id="jprm_prices_v1" name="jprm_prices_v1" value="<?php echo esc_attr( wp_json_encode( $multi_rows ) ); ?>" />
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_badge"><?php esc_html_e( 'Badge Text', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <input type="text" id="jprm_badge" name="jprm_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'e.g. Chef’s choice', 'jellopoint-restaurant-menu' ); ?>" />
                        <select name="jprm_badge_position" id="jprm_badge_position" class="jprm-badge-pos">
                            <?php foreach ( $badge_options as $k => $label ) : ?>
                                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $badge_pos, $k ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_separator"><?php esc_html_e( 'Separator', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <input type="text" id="jprm_separator" name="jprm_separator" value="<?php echo esc_attr( $separator ); ?>" placeholder="·" />
                        <span class="jprm-muted"><?php esc_html_e( 'Used between title and price.', 'jellopoint-restaurant-menu' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_visible"><?php esc_html_e( 'Visible', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="1" <?php checked( (bool) $visible ); ?> /> <?php esc_html_e( 'Show this item on the site', 'jellopoint-restaurant-menu' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_desc"><?php esc_html_e( 'Short Description', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%;"><?php echo esc_textarea( $desc ); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>

        <script>
        (function($){
            function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]});}
            function syncRows(){
                var out = [];
                $('#jprm_multi_table tbody tr').each(function(){
                    var $tr = $(this);
                    var row = {
                        label_custom: $tr.find('input.label-custom').val() || '',
                        amount: $tr.find('input.amount').val() || '',
                        hide_icon: $tr.find('input.hide-icon').is(':checked') ? 1 : 0
                    };
                    if (row.label_custom.length || row.amount.length) out.push(row);
                });
                $('#jprm_prices_v1').val(JSON.stringify(out));
            }
            function addRow(data){
                data = data || {label_custom:'', amount:'', hide_icon:0};
                var html = '' +
                    '<tr>' +
                    '<td><input type="text" class="label-custom regular-text" value="'+esc(data.label_custom)+'" placeholder="<?php echo esc_js( __( 'Small / Glass / etc.', 'jellopoint-restaurant-menu' ) ); ?>" /></td>' +
                    '<td><input type="text" class="amount regular-text" value="'+esc(data.amount)+'" placeholder="€ 7,50" /></td>' +
                    '<td><input type="checkbox" class="hide-icon" '+(data.hide_icon ? 'checked' : '')+' /></td>' +
                    '<td><a href="#" class="button button-secondary jprm-row-remove"><?php echo esc_js( __( 'Remove', 'jellopoint-restaurant-menu' ) ); ?></a></td>' +
                    '</tr>';
                $('#jprm_multi_table tbody').append(html);
                syncRows();
            }

            $(document).on('change keyup', '#jprm_multi_table input', syncRows);
            $(document).on('click', '#jprm_row_add', function(e){ e.preventDefault(); addRow(); });
            $(document).on('click', '.jprm-row-remove', function(e){ e.preventDefault(); $(this).closest('tr').remove(); syncRows(); });
            $(document).on('change', '#jprm_multi', function(){ $('#jprm_multi_wrap').toggle( this.checked ); });

            try {
                var seed = JSON.parse($('#jprm_prices_v1').val() || '[]');
                if (seed && seed.length){
                    $('#jprm_multi_table tbody').empty();
                    seed.forEach(function(r){ addRow(r); });
                }
            } catch(e){}
        })(jQuery);
        </script>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( $post->post_type !== 'jprm_menu_item' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! isset( $_POST['jprm_meta_nonce'] ) || ! wp_verify_nonce( $_POST['jprm_meta_nonce'], 'jprm_save_meta' ) ) return;

        $get_text = function( $k ) { return isset( $_POST[$k] ) ? wp_kses_post( wp_unslash( $_POST[$k] ) ) : ''; };
        $get_bool = function( $k ) { return isset( $_POST[$k] ) ? 1 : 0; };

        update_post_meta( $post_id, '_jprm_price',               $get_text( 'jprm_price' ) );
        update_post_meta( $post_id, '_jprm_price_label',         sanitize_text_field( $get_text( 'jprm_price_label' ) ) );
        update_post_meta( $post_id, '_jprm_price_label_custom',  sanitize_text_field( $get_text( 'jprm_price_label_custom' ) ) );
        update_post_meta( $post_id, '_jprm_multi',               $get_bool( 'jprm_multi' ) );

        $rows_json = isset( $_POST['jprm_prices_v1'] ) ? (string) wp_unslash( $_POST['jprm_prices_v1'] ) : '[]';
        $rows      = json_decode( $rows_json, true );
        $san_rows  = [];
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) {
                $san_rows[] = [
                    'label_custom' => isset( $r['label_custom'] ) ? sanitize_text_field( $r['label_custom'] ) : '',
                    'amount'       => isset( $r['amount'] ) ? sanitize_text_field( $r['amount'] ) : '',
                    'hide_icon'    => ! empty( $r['hide_icon'] ) ? 1 : 0,
                ];
            }
        }
        update_post_meta( $post_id, '_jprm_multi_rows', $san_rows );

        update_post_meta( $post_id, '_jprm_badge',             sanitize_text_field( $get_text( 'jprm_badge' ) ) );
        update_post_meta( $post_id, '_jprm_badge_position',    sanitize_text_field( $get_text( 'jprm_badge_position' ) ) );
        update_post_meta( $post_id, '_jprm_separator',         sanitize_text_field( $get_text( 'jprm_separator' ) ) );
        update_post_meta( $post_id, '_jprm_visible',           $get_bool( 'jprm_visible' ) );
        update_post_meta( $post_id, '_jprm_desc',              $get_text( 'jprm_desc' ) );
    }

    /* ===== Elementor ===== */
    public function register_category( $elements_manager ) {
        $slug = 'jellopoint-widgets';
        $cats = method_exists( $elements_manager, 'get_categories' ) ? $elements_manager->get_categories() : [];
        if ( ! isset( $cats[ $slug ] ) ) {
            $elements_manager->add_category( $slug, [ 'title' => __( 'JelloPoint Widgets', 'jellopoint-restaurant-menu' ), 'icon' => 'fa fa-plug' ] );
        }
    }
    public function register_widgets_autoload( $widgets_manager ) {
        $classes = $this->autoload_widgets();
        foreach ( $classes as $class ) $widgets_manager->register( new $class() );
    }
    public function register_widgets_autoload_legacy() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) ) return;
        $classes = $this->autoload_widgets();
        foreach ( $classes as $class ) \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new $class() );
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

    /* ===== Shortcode (minimal; resolves menu term by id/slug) ===== */
    public function register_shortcodes() { add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] ); }
    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [ 'menu' => '', 'id' => '', 'sections' => '' ], $atts, 'jprm_menu' );

        $menu_arg = $atts['menu'] !== '' ? $atts['menu'] : $atts['id'];
        $term = null;
        if ( $menu_arg !== '' ) {
            if ( is_numeric( $menu_arg ) ) {
                $term = get_term( absint( $menu_arg ), 'jprm_menu' );
            } else {
                $term = get_term_by( 'slug', sanitize_title( (string) $menu_arg ), 'jprm_menu' );
                if ( ! $term ) { $term = get_term_by( 'name', (string) $menu_arg, 'jprm_menu' ); }
            }
        }
        if ( ! $term || is_wp_error( $term ) ) return '';

        ob_start(); ?>
        <div class="jprm-menu" data-menu-term="<?php echo (int) $term->term_id; ?>">
            <div class="jprm-menu__title"><?php echo esc_html( $term->name ); ?></div>
        </div>
        <?php return ob_get_clean();
    }
}

/* Bootstrap */
if ( ! function_exists( __NAMESPACE__ . '\\jprm_bootstrap' ) ) { function jprm_bootstrap() { return Plugin::instance(); } }
jprm_bootstrap();
