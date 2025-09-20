<?php
/**
 * JelloPoint Restaurant Menu – main plugin class
 * - Menus as TAXONOMY (items can belong to multiple menus)
 * - Full Menu Item meta boxes (Price, Multiple Prices, Badge, Separator, etc.)
 * - Admin left menu (cutlery): Menus, Menu Items, Sections, Price Labels
 * - Shortcode renders with v2.1.0-compatible markup/classes for layout parity
 * - Sections render hierarchically; only sections with items are shown
 * - Deduplication policy to avoid parent/child duplicates (deepest_only default)
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

        // Output small alignment CSS helpers (compatible with old styling)
        add_action( 'wp_head', [ $this, 'output_alignment_css' ] );

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

    /* ===== Taxonomies ===== */
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
                        'name'          => __( 'Sections', 'jellopoint-restaurant-menu' ),
                        'singular_name' => __( 'Section', 'jellopoint-restaurant-menu' ),
                        'menu_name'     => __( 'Sections', 'jellopoint-restaurant-menu' ),
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

        // Price Labels
        if ( ! taxonomy_exists( 'jprm_label' ) ) {
            register_taxonomy(
                'jprm_label',
                [ 'jprm_menu_item' ],
                [
                    'label'             => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                    'labels'            => [
                        'name'          => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
                        'singular_name' => __( 'Price Label', 'jellopoint-restaurant-menu' ),
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
        $separator    = get_post_meta( $post->ID, '_jprm_separator', true ); // 'yes' legacy
        $visible      = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc         = get_post_meta( $post->ID, '_jprm_desc', true );

        if ( ! is_array( $multi_rows ) ) {
            $decoded    = json_decode( (string) $multi_rows, true );
            $multi_rows = is_array( $decoded ) ? $decoded : [];
        }

        $preset_map = apply_filters( 'jprm_price_label_full_map', [
            'small'  => [ 'label_custom' => __( 'Small', 'jellopoint-restaurant-menu' ) ],
            'medium' => [ 'label_custom' => __( 'Medium', 'jellopoint-restaurant-menu' ) ],
            'large'  => [ 'label_custom' => __( 'Large', 'jellopoint-restaurant-menu' ) ],
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
                                if ( empty( $multi_rows ) ) { $multi_rows = []; }
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
                        <label><input type="checkbox" id="jprm_separator" name="jprm_separator" value="yes" <?php checked( $separator, 'yes' ); ?> /> <?php esc_html_e( 'Show a divider line after item', 'jellopoint-restaurant-menu' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="jprm_visible"><?php esc_html_e( 'Visible', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="yes" <?php checked( (string)$visible, 'yes' ); checked( (string)$visible, '1' ); ?> /> <?php esc_html_e( 'Show this item on the site', 'jellopoint-restaurant-menu' ); ?></label>
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
        // Separator: store 'yes' when checked for legacy layout compatibility
        update_post_meta( $post_id, '_jprm_separator',         isset($_POST['jprm_separator']) ? 'yes' : '' );
        // Visible: store 'yes' like legacy, but also accept '1' on read
        update_post_meta( $post_id, '_jprm_visible',           isset($_POST['jprm_visible']) ? 'yes' : '' );
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

    /* ===== Shortcode (renders items with v2.1.0 markup, hierarchical sections, dedupe) ===== */
    public function register_shortcodes() { add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] ); }

    /**
     * [jprm_menu menu="term_id_or_slug[,slug]" sections="slug,slug" orderby="menu_order" order="ASC" limit="-1" hide_invisible="yes" dedupe="deepest_only|all_assigned|topmost_only"]
     */
    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [
            'menu'               => '',
            'sections'           => '',
            'orderby'            => 'menu_order',
            'order'              => 'ASC',
            'limit'              => -1,
            'hide_invisible'     => 'yes',
            'dedupe'             => 'deepest_only', // deepest_only|all_assigned|topmost_only
        ], $atts, 'jprm_menu' );

        // Resolve menu terms (allow list)
        $menu_terms = [];
        if ( $atts['menu'] !== '' ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['menu'] ) ) ) as $m ) {
                if ( is_numeric( $m ) ) { $t = get_term( absint( $m ), 'jprm_menu' ); }
                else { $t = get_term_by( 'slug', sanitize_title( $m ), 'jprm_menu' ); if ( ! $t ) $t = get_term_by( 'name', $m, 'jprm_menu' ); }
                if ( $t && ! is_wp_error( $t ) ) $menu_terms[] = (int) $t->term_id;
            }
        }
        if ( empty( $menu_terms ) ) return '';

        // Resolve sections filter (list)
        $section_ids_filter = [];
        if ( $atts['sections'] !== '' ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['sections'] ) ) ) as $s ) {
                if ( is_numeric( $s ) ) { $t = get_term( absint( $s ), 'jprm_section' ); }
                else { $t = get_term_by( 'slug', sanitize_title( $s ), 'jprm_section' ); if ( ! $t ) $t = get_term_by( 'name', $s, 'jprm_section' ); }
                if ( $t && ! is_wp_error( $t ) ) $section_ids_filter[] = (int) $t->term_id;
            }
            $section_ids_filter = array_values( array_unique( $section_ids_filter ) );
        }

        $taxq = [
            'relation' => 'AND',
            [
                'taxonomy' => 'jprm_menu',
                'field'    => 'term_id',
                'terms'    => $menu_terms,
                'include_children' => false,
                'operator' => 'IN',
            ],
        ];
        if ( $section_ids_filter ) {
            $taxq[] = [
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => $section_ids_filter,
                'operator' => 'IN',
            ];
        }

        $meta_q = [];
        if ( strtolower( (string) $atts['hide_invisible'] ) === 'yes' ) {
            $meta_q[] = [
                'relation' => 'OR',
                [ 'key' => '_jprm_visible', 'value' => 'yes', 'compare' => '=' ],
                [ 'key' => '_jprm_visible', 'value' => '1',   'compare' => '=' ],
                [ 'key' => '_jprm_visible', 'compare' => 'NOT EXISTS' ],
            ];
        }

        $args = [
            'post_type'      => 'jprm_menu_item',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
            'tax_query'      => $taxq,
            'meta_query'     => $meta_q ?: null,
            'no_found_rows'  => true,
        ];
        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) return '';

        $items = $q->posts;

        // Collect all section terms seen on items
        $item_sections = []; // post_id => [term_ids]
        $all_term_ids  = [];
        foreach ( $items as $p ) {
            $terms = get_the_terms( $p->ID, 'jprm_section' );
            $ids = [];
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) { $ids[] = (int) $t->term_id; $all_term_ids[] = (int) $t->term_id; }
            } else {
                $ids[] = 0; // ungrouped bucket
            }
            $item_sections[ $p->ID ] = array_values( array_unique( $ids ) );
        }
        $all_term_ids = array_values( array_unique( $all_term_ids ) );

        // Build section tree for the terms we need (and their ancestors)
        $children = []; $parents = []; $terms_cache = [];
        $ancestors_cache = []; // tid => [ancestors...]
        if ( $all_term_ids ) {
            $all_terms = get_terms([ 'taxonomy'=>'jprm_section', 'hide_empty'=>false, 'include'=>$all_term_ids ]);
            if ( ! is_wp_error( $all_terms ) ) {
                foreach ( $all_terms as $t ) { $terms_cache[ $t->term_id ] = $t; $parents[ $t->term_id ] = (int) $t->parent; }
                // ensure all ancestors are present
                $queue = $all_term_ids;
                while ( $queue ) {
                    $tid = array_pop( $queue );
                    $anc = get_ancestors( $tid, 'jprm_section' );
                    $ancestors_cache[ $tid ] = array_map( 'intval', $anc );
                    foreach ( $anc as $par ) {
                        $par = (int) $par;
                        if ( ! isset( $terms_cache[$par] ) ) {
                            $anc_t = get_term( $par, 'jprm_section' );
                            if ( $anc_t && ! is_wp_error( $anc_t ) ) { $terms_cache[$par] = $anc_t; $parents[$par] = (int) $anc_t->parent; $queue[] = $par; }
                        }
                    }
                }
                // Children map
                foreach ( $terms_cache as $tid => $t ) {
                    $par = (int) $t->parent;
                    if ( ! isset( $children[$par] ) ) $children[$par] = [];
                    $children[$par][] = $tid;
                }
                foreach ( $children as $par => &$_kids ) {
                    usort( $_kids, function( $a, $b ) use ( $terms_cache ) {
                        return strcasecmp( $terms_cache[$a]->name, $terms_cache[$b]->name );
                    } );
                }
            }
        }

        // De-duplication of sections per item
        $dedupe_mode = in_array( $atts['dedupe'], [ 'deepest_only', 'all_assigned', 'topmost_only' ], true ) ? $atts['dedupe'] : 'deepest_only';
        foreach ( $item_sections as $pid => $sids ) {
            if ( empty( $sids ) ) continue;
            $sids = array_values( array_unique( $sids ) );
            if ( $dedupe_mode === 'all_assigned' ) { $item_sections[$pid] = $sids; continue; }

            // Compute keep set
            $keep = [];
            foreach ( $sids as $sid ) {
                $keep[$sid] = true;
            }
            // Compare each pair and drop according to mode
            foreach ( $sids as $a ) {
                foreach ( $sids as $b ) {
                    if ( $a === $b ) continue;
                    // is $a ancestor of $b ?
                    $anc_b = isset( $ancestors_cache[$b] ) ? $ancestors_cache[$b] : get_ancestors( $b, 'jprm_section' );
                    if ( in_array( (int) $a, array_map('intval', $anc_b), true ) ) {
                        if ( $dedupe_mode === 'deepest_only' ) {
                            // drop ancestor $a
                            unset( $keep[$a] );
                        } elseif ( $dedupe_mode === 'topmost_only' ) {
                            // drop descendant $b
                            unset( $keep[$b] );
                        }
                    }
                }
            }
            $kept = array_keys( $keep );
            $item_sections[$pid] = $kept ? array_values( array_unique( $kept ) ) : $sids;
        }

        // Constrain to selected sections (if filter is provided): treat selected as entry roots
        $entry_roots = [];
        if ( $section_ids_filter ) {
            foreach ( $section_ids_filter as $sid ) {
                // highest ancestor among selected chain
                $anc = get_ancestors( $sid, 'jprm_section' );
                $root = $sid; if ( $anc ) { $root = end( $anc ); }
                $entry_roots[$root] = true;
            }
        } else {
            // all roots in our terms cache
            foreach ( $terms_cache as $tid => $t ) if ( (int) $t->parent === 0 ) $entry_roots[$tid] = true;
        }
        $entry_roots = array_keys( $entry_roots );
        usort( $entry_roots, function( $a, $b ) use ( $terms_cache ) {
            $an = isset($terms_cache[$a]) ? $terms_cache[$a]->name : '';
            $bn = isset($terms_cache[$b]) ? $terms_cache[$b]->name : '';
            return strcasecmp( $an, $bn );
        });

        // Partition posts by the (possibly reduced) section ids
        $posts_by_section = []; // section_id => [post IDs]
        foreach ( $items as $p ) {
            $ids = isset( $item_sections[$p->ID] ) ? $item_sections[$p->ID] : [0];
            foreach ( $ids as $sid ) {
                if ( ! isset( $posts_by_section[$sid] ) ) $posts_by_section[$sid] = [];
                $posts_by_section[$sid][] = $p->ID;
            }
        }

        // Helper: render items list (jp-* markup)
        $render_items = function( $post_ids ) {
            echo '<ul class="jp-menu">';
            foreach ( $post_ids as $pid ) {
                $title = get_the_title( $pid );
                $desc_meta = get_post_meta( $pid, '_jprm_desc', true );
                $desc = $desc_meta ? wpautop( $desc_meta ) : apply_filters( 'the_content', get_post_field( 'post_content', $pid ) );
                $price = get_post_meta( $pid, '_jprm_price', true );
                $price_label = get_post_meta( $pid, '_jprm_price_label', true );
                $price_label_custom = get_post_meta( $pid, '_jprm_price_label_custom', true );
                $multi = (bool) get_post_meta( $pid, '_jprm_multi', true );
                $rows  = get_post_meta( $pid, '_jprm_multi_rows', true );
                if ( ! is_array( $rows ) ) { $dec = json_decode( (string) $rows, true ); $rows = is_array( $dec ) ? $dec : []; }
                $badge = get_post_meta( $pid, '_jprm_badge', true );
                $badge_p = get_post_meta( $pid, '_jprm_badge_position', true ); if ( ! $badge_p ) $badge_p = 'corner-right';
                $sep  = ( get_post_meta( $pid, '_jprm_separator', true ) === 'yes' );
                $img  = get_the_post_thumbnail( $pid, 'thumbnail', [ 'class'=>'attachment-thumbnail size-thumbnail' ] );
                $badge_class = 'jp-menu__badge' . ( $badge_p === 'inline' ? ' jp-menu__badge--inline' : ' jp-menu__badge--corner jp-menu__badge--' . ( $badge_p === 'corner-left' ? 'corner-left' : 'corner-right' ) );

                echo '<li class="jp-menu__item">';
                if ( $badge ) echo '<span class="'.esc_attr($badge_class).'">'.esc_html($badge).'</span>';

                echo '<div class="jp-menu__inner" style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem">';
                  echo '<div class="jp-box-left" style="display:flex;gap:.75rem;flex:1 1 auto;min-width:0">';
                    if ( $img ) echo '<div class="jp-menu__media">'.$img.'</div>';
                    echo '<div class="jp-menu__content" style="flex:1 1 auto;min-width:0;width:auto">';
                      echo '<div class="jp-menu__header">';
                        echo '<span class="jp-menu__title">'.esc_html($title).'</span>';
                      echo '</div>';
                      if ( $desc ) echo '<div class="jp-menu__desc">'.$desc.'</div>';
                    echo '</div>';
                  echo '</div>';
                  echo '<div class="jp-box-right" style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end">';

                    if ( $multi && ! empty( $rows ) ) {
                        echo '<div class="jp-menu__pricegroup" style="display:inline-grid;justify-items:end">';
                        foreach ( $rows as $r ) {
                            $label = isset( $r['label_custom'] ) ? $r['label_custom'] : '';
                            $amount = isset( $r['amount'] ) ? $r['amount'] : '';
                            if ( $label === '' && $amount === '' ) continue;

                            $label_html = $label ? '<span class="jp-price-label">'.esc_html($label).'</span>' : '';
                            echo '<div class="jp-menu__price-row">'
                                . '<span class="jp-col jp-col-labelwrap">'.$label_html.'</span>'
                                . '<span class="jp-col jp-col-price">'.wp_kses_post( $amount ).'</span>'
                                . '</div>';
                        }
                        echo '</div>';
                    } else {
                        if ( $price !== '' ) {
                            $label_text = '';
                            if ( $price_label === 'custom' ) { $label_text = $price_label_custom; }
                            elseif ( $price_label ) { $label_text = ucwords( str_replace( '-', ' ', $price_label ) ); }
                            $label_html = $label_text ? '<span class="jp-price-label">'.esc_html($label_text).'</span>' : '';
                            echo '<div class="jp-menu__price-row">'
                                . '<span class="jp-col jp-col-labelwrap">'.$label_html.'</span>'
                                . '<span class="jp-col jp-col-price">'.wp_kses_post( $price ).'</span>'
                                . '</div>';
                        }
                    }

                  echo '</div>';
                echo '</div>';
                if ( $sep ) echo '<div class="jp-menu__separator" aria-hidden="true"></div>';
                echo '</li>';
            }
            echo '</ul>';
        };

        // Recursive renderer for section tree starting from roots or selected entries
        $render_section = function( $sid, $level ) use ( &$render_section, $children, $terms_cache, $posts_by_section, $render_items ) {
            $has_items_here = ! empty( $posts_by_section[ $sid ] );
            $has_items_below = false;
            foreach ( (array) ($children[$sid] ?? []) as $kid ) {
                if ( ! empty( $posts_by_section[$kid] ) ) { $has_items_below = true; break; }
            }
            if ( ! $has_items_here && ! $has_items_below ) return; // prune empty branches

            echo '<div class="jp-section jp-section--level-'.intval($level).'">';
            if ( $sid && isset( $terms_cache[$sid] ) ) {
                echo '<h3 class="jp-menu__section-title">'. esc_html( $terms_cache[$sid]->name ) .'</h3>';
            }
            if ( $has_items_here ) { $render_items( $posts_by_section[$sid] ); }
            foreach ( (array) ($children[$sid] ?? []) as $kid ) {
                $render_section( $kid, $level+1 );
            }
            echo '</div>';
        };

        ob_start();
        if ( empty( $terms_cache ) ) {
            // No sections at all -> flat
            $all_ids = array_unique( array_merge( $posts_by_section[0] ?? [], ...array_values( $posts_by_section ) ) );
            $render_items( $all_ids );
        } else {
            foreach ( $entry_roots as $root_sid ) {
                $render_section( $root_sid, 1 );
            }
            // Ungrouped items (sid 0) after the tree
            if ( ! empty( $posts_by_section[0] ) ) {
                echo '<div class="jp-section jp-section--level-1">';
                $render_items( $posts_by_section[0] );
                echo '</div>';
            }
        }
        return ob_get_clean();
    }

    public function output_alignment_css() {
        echo '<style id="jprm-fixes-inline-css">
        .jp-menu__inner{display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem}
        .jp-box-right{display:flex;flex-direction:column;align-items:flex-end}
        .jp-menu__pricegroup{display:inline-grid;justify-items:end;text-align:right}
        .jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%}
        .jp-menu__price-row .jp-col{display:block}
        .jp-menu__price-row .jp-col.jp-col-labelwrap{display:inline-flex;align-items:center;gap:.5rem}
        .jp-menu__item{display:grid;grid-template-columns:1fr auto;gap:1rem}
        .jp-menu__item .jp-menu__pricegroup,.jp-menu__item .jp-menu__price{justify-self:end;text-align:right}
        /* indent nested sections a bit */
        .jp-section--level-2{padding-left:1rem}
        .jp-section--level-3{padding-left:2rem}
        .jp-section--level-4{padding-left:3rem}
        </style>';
    }
}

/* Bootstrap */
if ( ! function_exists( __NAMESPACE__ . '\\jprm_bootstrap' ) ) { function jprm_bootstrap() { return Plugin::instance(); } }
jprm_bootstrap();
