<?php
/**
 * JelloPoint Restaurant Menu – Plugin core
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
        add_action( 'init', [ $this, 'register_shortcodes' ], 11 );

        // Admin menu
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_head', [ $this, 'hide_parent_duplicate_submenu' ] );
        add_filter( 'parent_file',  [ $this, 'admin_parent_highlight' ] );
        add_filter( 'submenu_file', [ $this, 'admin_submenu_highlight' ], 10, 2 );

        // Meta boxes for Menu Items
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ $this, 'save_meta' ], 10, 2 );

        // Frontend tiny CSS helpers (layout parity)
        add_action( 'wp_head', [ $this, 'output_alignment_css' ] );

        // Elementor
        add_action( 'elementor/init', function () {
            add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
            add_action( 'elementor/widgets/register',               [ $this, 'register_widgets_autoload' ] );
            add_action( 'elementor/widgets/widgets_registered',     [ $this, 'register_widgets_autoload_legacy' ] );
        }, 1 );

        // Price Labels (term meta + list column)
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

    /* ===== CPT ===== */
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
        ] );
    }

    /* ===== Taxonomies ===== */
    public function register_taxonomies() {
        // Menus (non-hierarchical) allow items in multiple menus
        register_taxonomy( 'jprm_menu', [ 'jprm_menu_item' ], [
            'label'             => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'labels'            => [ 'name' => __( 'Menus', 'jellopoint-restaurant-menu' ) ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
        ] );

        // Sections (hierarchical)
        register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
            'label'             => __( 'Sections', 'jellopoint-restaurant-menu' ),
            'labels'            => [ 'name' => __( 'Sections', 'jellopoint-restaurant-menu' ) ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
        ] );

        // Price Labels (non-hierarchical)
        register_taxonomy( 'jprm_label', [ 'jprm_menu_item' ], [
            'label'             => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            'labels'            => [ 'name' => __( 'Price Labels', 'jellopoint-restaurant-menu' ) ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
        ] );
    }

    /* ===== Admin Menu ===== */
    public function register_admin_menu() {
        add_menu_page(
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            __( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
            'edit_posts',
            'jprm_admin',
            [ $this, 'render_admin_welcome' ],
            'dashicons-food',
            25
        );

        add_submenu_page( 'jprm_admin', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Menu Items', 'jellopoint-restaurant-menu' ), __( 'Menu Items', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit.php?post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        add_submenu_page( 'jprm_admin', __( 'Price Labels', 'jellopoint-restaurant-menu' ), __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'edit_posts', 'edit-tags.php?taxonomy=jprm_label&post_type=jprm_menu_item' );
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

    public function render_admin_welcome() {
        echo '<div class="wrap"><h1>'. esc_html__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ) .'</h1><p>'. esc_html__( 'Manage Menus, Menu Items, Sections and Price Labels.', 'jellopoint-restaurant-menu' ) .'</p></div>';
    }

    /* ===== Meta boxes (Menu Items) ===== */
    public function add_meta_boxes() {
        add_meta_box(
            'jprm_item_meta',
            __( 'Menu Item Settings', 'jellopoint-restaurant-menu' ),
            [ $this, 'render_item_meta' ],
            'jprm_menu_item',
            'normal',
            'high'
        );
    }

    public function render_item_meta( $post ) {
        wp_nonce_field( 'jprm_save_meta', 'jprm_meta_nonce' );

        $price        = get_post_meta( $post->ID, '_jprm_price', true );
        $price_label  = get_post_meta( $post->ID, '_jprm_price_label', true );
        $price_label_custom = get_post_meta( $post->ID, '_jprm_price_label_custom', true );
        $multi        = (bool) get_post_meta( $post->ID, '_jprm_multi', true );
        $multi_rows   = get_post_meta( $post->ID, '_jprm_multi_rows', true );
        if ( ! is_array( $multi_rows ) ) {
            $dec = json_decode( (string) $multi_rows, true );
            $multi_rows = is_array( $dec ) ? $dec : [];
        }
        $badge        = get_post_meta( $post->ID, '_jprm_badge', true );
        $badge_pos    = get_post_meta( $post->ID, '_jprm_badge_position', true ); if ( ! $badge_pos ) $badge_pos = 'corner-right';
        $separator    = get_post_meta( $post->ID, '_jprm_separator', true ); // 'yes' legacy
        $visible      = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc         = get_post_meta( $post->ID, '_jprm_desc', true );

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

        ?>
        <style>
            .jprm-table { width:100%; border-collapse: collapse; }
            .jprm-table th, .jprm-table td { padding:6px 8px; border-bottom:1px solid #e5e5e5; vertical-align: middle; }
            .jprm-table th { text-align:left; width: 160px; }
            .jprm-multi-table input[type="text"]{ width: 100%; }
            .jprm-badge-pos { min-width:220px; }
            .jprm-muted { color:#666; }
            .jp-hidden { display:none; }
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
                        <label><input type="checkbox" id="jprm_multi" name="jprm_multi" value="1" <?php checked( $multi ); ?> /> <?php esc_html_e( 'Enable multiple prices (enter rows below)', 'jellopoint-restaurant-menu' ); ?></label>
                        <div id="jprm-multi-admin" style="margin-top:10px; <?php echo $multi ? '' : 'display:none;'; ?>">
                            <table class="widefat fixed striped jprm-multi-table" id="jprm-prices-table">
                                <thead>
                                    <tr>
                                        <th style="width:4%"></th>
                                        <th style="width:26%"><?php esc_html_e( 'Label', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th style="width:26%"><?php esc_html_e( 'Amount', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th style="width:12%"><?php esc_html_e( 'Hide Icon', 'jellopoint-restaurant-menu' ); ?></th>
                                        <th><?php esc_html_e( 'Actions', 'jellopoint-restaurant-menu' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Build select options once
                                $select_html = '<select class="label-select"><option value="">'. esc_html__( 'Select…', 'jellopoint-restaurant-menu' ) .'</option>';
                                foreach ( $preset_map as $slug => $row ) {
                                    $t = isset( $row['label_custom'] ) ? $row['label_custom'] : ucfirst( $slug );
                                    $select_html .= '<option value="'.esc_attr($slug).'">'.$t.'</option>';
                                }
                                $select_html .= '<option value="custom">'. esc_html__( 'Custom', 'jellopoint-restaurant-menu' ) .'</option></select>';

                                // Prefill rows
                                $prefill = [];
                                if ( is_array( $multi_rows ) && ! empty( $multi_rows ) ) {
                                    foreach ( $multi_rows as $r ) {
                                        $prefill[] = [
                                            'enable'       => 1,
                                            'label_select' => isset( $r['label_custom'] ) && $r['label_custom'] !== '' ? 'custom' : '',
                                            'label_custom' => isset( $r['label_custom'] ) ? $r['label_custom'] : '',
                                            'amount'       => isset( $r['amount'] ) ? $r['amount'] : '',
                                            'hide_icon'    => ! empty( $r['hide_icon'] ) ? 1 : 0,
                                        ];
                                    }
                                }
                                if ( empty( $prefill ) ) {
                                    $prefill = [ [ 'enable'=>0,'label_select'=>'','label_custom'=>'','amount'=>'','hide_icon'=>0 ] ];
                                }
                                $row_index = 0;
                                foreach ( $prefill as $r ) {
                                    $row_index++;
                                    $en = ! empty( $r['enable'] );
                                    $ls = isset( $r['label_select'] ) ? $r['label_select'] : '';
                                    $lc = isset( $r['label_custom'] ) ? $r['label_custom'] : '';
                                    $am = isset( $r['amount'] ) ? $r['amount'] : '';
                                    $hi = ! empty( $r['hide_icon'] );
                                    $hidden = ( ! $en && $row_index > 1 ) ? ' class="jp-hidden"' : '';
                                    echo '<tr'.$hidden.'>';
                                    echo '<td><input type="checkbox" class="enable" '.( $en ? 'checked' : '' ).' /></td>';
                                    echo '<td class="label-td">'.$select_html.' <input type="text" class="label-custom regular-text" value="'. esc_attr( $lc ) .'" placeholder="'. esc_attr__( 'Custom label', 'jellopoint-restaurant-menu' ) .'" /></td>';
                                    echo '<td><input type="text" class="amount regular-text" value="'. esc_attr( $am ) .'" placeholder="€ 7,50" /></td>';
                                    echo '<td><input type="checkbox" class="hide-icon" '.( $hi ? 'checked' : '' ).' /></td>';
                                    echo '<td><a href="#" class="button button-secondary jprm-row-remove">'. esc_html__( 'Remove', 'jellopoint-restaurant-menu' ) .'</a></td>';
                                    echo '</tr>';
                                }
                                ?>
                                </tbody>
                            </table>
                            <p><a href="#" class="button" id="jprm-row-add"><?php esc_html_e( 'Add another price', 'jellopoint-restaurant-menu' ); ?></a></p>
                            <input type="hidden" id="jprm_prices_v1" name="jprm_prices_v1" value="<?php echo esc_attr( wp_json_encode( is_array($multi_rows)?$multi_rows:[] ) ); ?>" />
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
                    <td><label><input type="checkbox" id="jprm_separator" name="jprm_separator" value="yes" <?php checked( $separator, 'yes' ); ?> /> <?php esc_html_e( 'Show a divider line after item', 'jellopoint-restaurant-menu' ); ?></label></td>
                </tr>
                <tr>
                    <th><label for="jprm_visible"><?php esc_html_e( 'Visible', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td><label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="yes" <?php checked( (string)$visible, 'yes' ); checked( (string)$visible, '1' ); ?> /> <?php esc_html_e( 'Show this item on the site', 'jellopoint-restaurant-menu' ); ?></label></td>
                </tr>
                <tr>
                    <th><label for="jprm_desc"><?php esc_html_e( 'Short Description', 'jellopoint-restaurant-menu' ); ?></label></th>
                    <td><textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%;"><?php echo esc_textarea( $desc ); ?></textarea></td>
                </tr>
            </tbody>
        </table>

        <script>
        (function($){
            var $toggle = $('#jprm_multi'), $block = $('#jprm-multi-admin');
            function syncToggle(){ $toggle.is(':checked') ? $block.show() : $block.hide(); }
            if ($toggle.length){ $toggle.on('change', syncToggle); syncToggle(); }

            var $table = $('#jprm-prices-table'), $tbody = $table.find('tbody');

            function syncRow($tr){
                var isCustom = $tr.find('select.label-select').val() === 'custom';
                $tr.find('input.label-custom').closest('td').toggle(isCustom);
                var en = $tr.find('input.enable').is(':checked');
                if(!en && $tr.index()>0){ $tr.addClass('jp-hidden'); } else { $tr.removeClass('jp-hidden'); }
            }

            // Initialize existing rows
            $tbody.find('tr').each(function(){ syncRow($(this)); });

            function collect(){
                var out = [];
                $tbody.find('tr').each(function(){
                    var $tr = $(this);
                    var row = {
                        label_custom: $tr.find('input.label-custom').val() || '',
                        amount: $tr.find('input.amount').val() || '',
                        hide_icon: $tr.find('input.hide-icon').is(':checked') ? 1 : 0
                    };
                    if (row.label_custom.length || row.amount.length){ out.push(row); }
                });
                $('#jprm_prices_v1').val(JSON.stringify(out));
            }

            $(document).on('change', '#jprm-prices-table select.label-select', function(){
                syncRow($(this).closest('tr'));
                collect();
            });
            $(document).on('change keyup', '#jprm-prices-table input', collect);

            $('#jprm-row-add').on('click', function(e){
                e.preventDefault();
                var html = '<tr>'
                    + '<td><input type="checkbox" class="enable" checked /></td>'
                    + '<td class="label-td'><?php echo str_replace("'", "\\'", $select_html ); ?> <input type="text" class="label-custom regular-text" value="" placeholder="<?php echo esc_js( __( 'Custom label', 'jellopoint-restaurant-menu' ) ); ?>" /></td>'
                    + '<td><input type="text" class="amount regular-text" value="" placeholder="€ 7,50" /></td>'
                    + '<td><input type="checkbox" class="hide-icon" /></td>'
                    + '<td><a href="#" class="button button-secondary jprm-row-remove"><?php echo esc_js( __( 'Remove', 'jellopoint-restaurant-menu' ) ); ?></a></td>'
                    + '</tr>';
                $tbody.append(html);
                syncRow($tbody.find('tr:last'));
                collect();
            });
            $(document).on('click', '.jprm-row-remove', function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
                collect();
            });
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
        update_post_meta( $post_id, '_jprm_separator',         isset($_POST['jprm_separator']) ? 'yes' : '' );
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

    /* ===== Shortcode ===== */
    public function register_shortcodes() {
        add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] );
    }

    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [
            'menu'               => '',
            'sections'           => '',
            'orderby'            => 'menu_order',
            'order'              => 'ASC',
            'limit'              => -1,
            'hide_invisible'     => 'yes',
            'dedupe'             => 'deepest_only',
        ], $atts, 'jprm_menu' );

        // Resolve menu terms list
        $menu_terms = [];
        if ( $atts['menu'] !== '' ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['menu'] ) ) ) as $m ) {
                if ( is_numeric( $m ) ) { $t = get_term( absint( $m ), 'jprm_menu' ); }
                else { $t = get_term_by( 'slug', sanitize_title( $m ), 'jprm_menu' ); if ( ! $t ) $t = get_term_by( 'name', $m, 'jprm_menu' ); }
                if ( $t && ! is_wp_error( $t ) ) $menu_terms[] = (int) $t->term_id;
            }
        }
        if ( empty( $menu_terms ) ) return '';

        // Sections filter
        $section_filter = [];
        if ( $atts['sections'] !== '' ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $atts['sections'] ) ) ) as $s ) {
                if ( is_numeric( $s ) ) { $t = get_term( absint( $s ), 'jprm_section' ); }
                else { $t = get_term_by( 'slug', sanitize_title( $s ), 'jprm_section' ); if ( ! $t ) $t = get_term_by( 'name', $s, 'jprm_section' ); }
                if ( $t && ! is_wp_error( $t ) ) $section_filter[] = (int) $t->term_id;
            }
            $section_filter = array_values( array_unique( $section_filter ) );
        }

        // Build query
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
        if ( $section_filter ) {
            $taxq[] = [
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => $section_filter,
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

        // Collect all section terms from items
        $item_sections = []; // post_id => [term_ids or 0]
        $all_term_ids  = [];
        foreach ( $items as $p ) {
            $terms = get_the_terms( $p->ID, 'jprm_section' );
            $ids = [];
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) { $ids[] = (int) $t->term_id; $all_term_ids[] = (int) $t->term_id; }
            } else {
                $ids[] = 0; // ungrouped
            }
            $item_sections[ $p->ID ] = array_values( array_unique( $ids ) );
        }
        $all_term_ids = array_values( array_unique( $all_term_ids ) );

        // Build simple parent map + children map
        $children = []; $parents = []; $terms_cache = [];
        if ( $all_term_ids ) {
            $all_terms = get_terms([ 'taxonomy'=>'jprm_section', 'hide_empty'=>false, 'include'=>$all_term_ids ]);
            if ( ! is_wp_error( $all_terms ) ) {
                foreach ( $all_terms as $t ) { $terms_cache[ $t->term_id ] = $t; $parents[ $t->term_id ] = (int) $t->parent; }
                // ensure ancestors are loaded
                $queue = $all_term_ids;
                while ( $queue ) {
                    $tid = array_pop( $queue );
                    $par = isset( $parents[$tid] ) ? $parents[$tid] : ( isset($terms_cache[$tid]) ? (int) $terms_cache[$tid]->parent : 0 );
                    if ( $par && ! isset( $terms_cache[$par] ) ) {
                        $anc = get_term( $par, 'jprm_section' );
                        if ( $anc && ! is_wp_error( $anc ) ) { $terms_cache[$anc->term_id] = $anc; $parents[$anc->term_id] = (int) $anc->parent; $queue[] = $anc->term_id; }
                    }
                }
                foreach ( $terms_cache as $tid => $t ) {
                    $par = (int) $t->parent;
                    if ( ! isset( $children[$par] ) ) $children[$par] = [];
                    $children[$par][] = $tid;
                }
                foreach ( $children as $par => &$_kids ) {
                    usort( $_kids, function( $a, $b ) use ( $terms_cache ) { return strcasecmp( $terms_cache[$a]->name, $terms_cache[$b]->name ); } );
                }
            }
        }

        // Helper: ancestor test
        $is_ancestor = function( $ancestor_id, $child_id ) use ( $parents ) {
            $cur = $child_id;
            while ( $cur && isset( $parents[$cur] ) ) {
                if ( $parents[$cur] === $ancestor_id ) return true;
                $cur = $parents[$cur];
            }
            return false;
        };

        // Dedupe
        $dedupe_mode = in_array( $atts['dedupe'], [ 'deepest_only', 'all_assigned', 'topmost_only' ], true ) ? $atts['dedupe'] : 'deepest_only';
        foreach ( $item_sections as $pid => $sids ) {
            if ( count( $sids ) > 1 && in_array( 0, $sids, true ) ) { $sids = array_values( array_diff( $sids, [0] ) ); }
            if ( $dedupe_mode !== 'all_assigned' && $sids ) {
                $keep = [];
                foreach ( $sids as $sid ) {
                    $discard = false;
                    foreach ( $sids as $other ) {
                        if ( $sid === $other ) continue;
                        if ( $dedupe_mode === 'deepest_only' && $is_ancestor( $sid, $other ) ) { $discard = true; break; }
                        if ( $dedupe_mode === 'topmost_only' && $is_ancestor( $other, $sid ) ) { $discard = true; break; }
                    }
                    if ( ! $discard ) $keep[] = $sid;
                }
                $sids = array_values( array_unique( $keep ) );
            }
            $item_sections[$pid] = $sids ?: [0];
        }

        // Entry roots
        $entry_roots = [];
        if ( $section_filter ) {
            foreach ( $section_filter as $sid ) {
                $root = $sid; $cur = $sid;
                while ( $cur && isset( $parents[$cur] ) && $parents[$cur] ) { $cur = $parents[$cur]; $root = $cur; }
                $entry_roots[$root] = true;
            }
        } else {
            foreach ( $terms_cache as $tid => $t ) { if ( (int) $t->parent === 0 ) $entry_roots[$tid] = true; }
        }
        $entry_roots = array_keys( $entry_roots );
        usort( $entry_roots, function( $a, $b ) use ( $terms_cache ) { return strcasecmp( $terms_cache[$a]->name, $terms_cache[$b]->name ); } );

        // Map posts by section
        $posts_by_section = [];
        foreach ( $items as $p ) {
            $sids = $item_sections[$p->ID];
            foreach ( $sids as $sid ) {
                if ( $section_filter ) {
                    $in_subtree = false;
                    foreach ( $section_filter as $sel ) {
                        if ( $sid === $sel || $is_ancestor( $sel, $sid ) ) { $in_subtree = true; break; }
                    }
                    if ( ! $in_subtree ) continue;
                }
                if ( ! isset( $posts_by_section[$sid] ) ) $posts_by_section[$sid] = [];
                $posts_by_section[$sid][] = $p->ID;
            }
        }

        // Render helpers
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
                      echo '<div class="jp-menu__header"><span class="jp-menu__title">'.esc_html($title).'</span></div>';
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
                            echo '<div class="jp-menu__price-row jp-order--label-left">'
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
                            echo '<div class="jp-menu__price-row jp-order--label-left">'
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

        if ( empty( $terms_cache ) ) {
            ob_start(); $render_items( wp_list_pluck( $items, 'ID' ) ); return ob_get_clean();
        }

        $roots = $entry_roots;
        $render_section = function( $sid, $level ) use ( &$render_section, $children, $terms_cache, $posts_by_section, $render_items ) {
            $has_items_here = ! empty( $posts_by_section[ $sid ] );
            $has_items_below = false;
            foreach ( (array) ($children[$sid] ?? []) as $kid ) {
                if ( ! empty( $posts_by_section[$kid] ) ) { $has_items_below = true; break; }
                $stack = (array) ($children[$kid] ?? []);
                while ( $stack ) {
                    $n = array_pop( $stack );
                    if ( ! empty( $posts_by_section[$n] ) ) { $has_items_below = true; $stack = []; break; }
                    if ( ! empty( $children[$n] ) ) { foreach ( $children[$n] as $_n ) $stack[] = $_n; }
                }
            }
            if ( ! $has_items_here && ! $has_items_below ) return;

            echo '<div class="jp-section jp-section--level-'.intval($level).'">';
            if ( $sid && isset( $terms_cache[$sid] ) ) {
                echo '<h3 class="jp-menu__section-title">'. esc_html( $terms_cache[$sid]->name ) .'</h3>';
            }
            if ( $has_items_here ) { $render_items( $posts_by_section[$sid] ); }
            foreach ( (array) ($children[$sid] ?? []) as $kid ) { $render_section( $kid, $level+1 ); }
            echo '</div>';
        };

        ob_start();
        foreach ( $roots as $root_sid ) { $render_section( $root_sid, 1 ); }
        if ( ! empty( $posts_by_section[0] ) ) {
            echo '<div class="jp-section jp-section--level-1">'; $render_items( $posts_by_section[0] ); echo '</div>';
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
        </style>';
    }

    /* ===== Price Labels term meta (icon) ===== */
    
    
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
        $w.find('.jprm-icon-preview').html('<img src=\"'+url+'\" style=\"height:40px;width:auto;border-radius:3px\" />');
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
});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();var url=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;$w.find('.jprm-icon-id').val(a.id);$w.find('.jprm-icon-preview').html('<img src=\\''+url+'\\' style=\\'height:40px;width:auto;border-radius:3px\\' />');$w.find('.jprm-remove-icon').show();});frame.open();});$(document).on('click','.jprm-remove-icon',function(e){e.preventDefault();var $w=$(this).closest('.form-field, .jprm-term-meta, tr');$w.find('.jprm-icon-id').val('');$w.find('.jprm-icon-preview').empty();$(this).hide();});})(jQuery);";
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
