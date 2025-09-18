<?php
/**
 * Main plugin bootstrap for JelloPoint Restaurant Menu.
 *
 * This file registers the CPT, meta boxes and Elementor integration.
 * Drop-in replacement for corrupted admin rendering where placeholders like %s leaked.
 */

namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {

    public function __construct() {
        // Core
        add_action( 'init',               [ $this, 'register_cpt' ] );
        add_action( 'plugins_loaded',     [ $this, 'i18n' ] );

        // Admin (meta boxes + save)
        add_action( 'add_meta_boxes',     [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',          [ $this, 'save_meta' ], 10, 2 );
        add_filter( 'admin_footer_text',  [ $this, 'admin_footer' ] );
        add_action( 'admin_notices',      [ $this, 'admin_notice' ] );

        // Elementor (category + widget)
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
        add_action( 'elementor/widgets/register',               [ $this, 'register_widget' ] );

        // Frontend assets (keep light, widget can enqueue its own too)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function i18n() {
        load_plugin_textdomain( 'jellopoint-restaurant-menu', false, dirname( plugin_basename( JPRM_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Register custom post type for individual menu items.
     */
    public function register_cpt() {
        $labels = [
            'name'               => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'singular_name'      => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
            'add_new'            => __( 'Add New', 'jellopoint-restaurant-menu' ),
            'add_new_item'       => __( 'Add New Menu Item', 'jellopoint-restaurant-menu' ),
            'edit_item'          => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
            'new_item'           => __( 'New Menu Item', 'jellopoint-restaurant-menu' ),
            'view_item'          => __( 'View Menu Item', 'jellopoint-restaurant-menu' ),
            'search_items'       => __( 'Search Menu Items', 'jellopoint-restaurant-menu' ),
            'not_found'          => __( 'No items found', 'jellopoint-restaurant-menu' ),
            'not_found_in_trash' => __( 'No items found in Trash', 'jellopoint-restaurant-menu' ),
            'menu_name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
        ];

        register_post_type( 'jprm_menu_item', [
            'label'               => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon'           => 'dashicons-food',
            'map_meta_cap'        => true,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'rewrite'             => false,
        ] );
    }

    public function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! post_type_exists( 'jprm_menu_item' ) ) {
            echo '<div class="notice notice-error"><p>JelloPoint Restaurant Menu: Post Type not registered.</p></div>';
        }
    }

    public function admin_footer( $text ) {
        if ( current_user_can( 'manage_options' ) ) {
            $text .= ' | JelloPoint Restaurant Menu';
            if ( defined( 'JPRM_VERSION' ) ) {
                $text .= ' v' . esc_html( JPRM_VERSION );
            }
            $text .= ' active';
        }
        return $text;
    }

    /**
     * Meta boxes
     */
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

    /**
     * Render Admin meta box – FIXED: no raw %s placeholders; all fields properly escaped; balanced HTML.
     */
    public function render_item_metabox( $post ) {
        wp_nonce_field( 'jprm_save_meta', 'jprm_meta_nonce' );

        $price        = get_post_meta( $post->ID, '_jprm_price', true );
        $price_label  = get_post_meta( $post->ID, '_jprm_price_label', true );
        $multi        = (bool) get_post_meta( $post->ID, '_jprm_multi', true );
        $multi_rows   = get_post_meta( $post->ID, '_jprm_multi_rows', true );
        $badge        = get_post_meta( $post->ID, '_jprm_badge', true );
        $badge_pos    = get_post_meta( $post->ID, '_jprm_badge_position', true );
        $separator    = get_post_meta( $post->ID, '_jprm_separator', true );
        $visible      = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc         = get_post_meta( $post->ID, '_jprm_desc', true );

        if ( ! is_array( $multi_rows ) ) {
            // Stored as JSON string previously – decode if needed.
            $decoded = json_decode( (string) $multi_rows, true );
            $multi_rows = is_array( $decoded ) ? $decoded : [];
        }

        // Preset label map, filterable.
        $preset_map = apply_filters( 'jprm_price_label_full_map', [
            'small'  => [ 'label_custom' => __( 'Small', 'jellopoint-restaurant-menu' ),  'amount' => '' ],
            'medium' => [ 'label_custom' => __( 'Medium', 'jellopoint-restaurant-menu' ), 'amount' => '' ],
            'large'  => [ 'label_custom' => __( 'Large', 'jellopoint-restaurant-menu' ),  'amount' => '' ],
        ] );

        // Badge position options.
        $badge_options = [
            'corner-left'  => __( 'Corner (left)', 'jellopoint-restaurant-menu' ),
            'corner-right' => __( 'Corner (right)', 'jellopoint-restaurant-menu' ),
            'inline'       => __( 'Inline (next to title)', 'jellopoint-restaurant-menu' ),
        ];
        if ( empty( $badge_pos ) ) {
            $badge_pos = 'corner-right';
        }

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
                        <input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="<?php echo esc_attr( get_post_meta( $post->ID, '_jprm_price_label_custom', true ) ); ?>" placeholder="<?php esc_attr_e( 'Custom label', 'jellopoint-restaurant-menu' ); ?>" />
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
            function syncRows(){
                var out = [];
                $('#jprm_multi_table tbody tr').each(function(){
                    var $tr = $(this);
                    var row = {
                        label_custom: $tr.find('input.label-custom').val() || '',
                        amount: $tr.find('input.amount').val() || '',
                        hide_icon: $tr.find('input.hide-icon').is(':checked') ? 1 : 0
                    };
                    if (row.label_custom.length || row.amount.length) {
                        out.push(row);
                    }
                });
                $('#jprm_prices_v1').val(JSON.stringify(out));
            }
            function addRow(data){
                data = data || {label_custom:'', amount:'', hide_icon:0};
                var html = '' +
                    '<tr>' +
                    '<td><input type="text" class="label-custom regular-text" value="'+_.escape(data.label_custom)+'" placeholder="<?php echo esc_js( __( 'Small / Glass / etc.', 'jellopoint-restaurant-menu' ) ); ?>" /></td>' +
                    '<td><input type="text" class="amount regular-text" value="'+_.escape(data.amount)+'" placeholder="€ 7,50" /></td>' +
                    '<td><input type="checkbox" class="hide-icon" '+(data.hide_icon ? 'checked' : '')+' /></td>' +
                    '<td><a href="#" class="button button-secondary jprm-row-remove"><?php echo esc_js( __( 'Remove', 'jellopoint-restaurant-menu' ) ); ?></a></td>' +
                    '</tr>';
                $('#jprm_multi_table tbody').append(html);
                syncRows();
            }

            $(document).on('change keyup', '#jprm_multi_table input', syncRows);
            $(document).on('click', '#jprm_row_add', function(e){ e.preventDefault(); addRow(); });
            $(document).on('click', '.jprm-row-remove', function(e){ e.preventDefault(); $(this).closest('tr').remove(); syncRows(); });
            $(document).on('change', '#jprm_multi', function(){
                $('#jprm_multi_wrap').toggle( this.checked );
            });

            // Seed from existing hidden input
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

    /**
     * Save meta box fields.
     */
    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['jprm_meta_nonce'] ) || ! wp_verify_nonce( $_POST['jprm_meta_nonce'], 'jprm_save_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( $post->post_type !== 'jprm_menu_item' ) {
            return;
        }

        $get_text = function( $key ) {
            return isset( $_POST[ $key ] ) ? wp_kses_post( wp_unslash( $_POST[ $key ] ) ) : '';
        };
        $get_bool = function( $key ) {
            return isset( $_POST[ $key ] ) ? 1 : 0;
        };

        update_post_meta( $post_id, '_jprm_price',               $get_text( 'jprm_price' ) );
        update_post_meta( $post_id, '_jprm_price_label',         sanitize_text_field( $get_text( 'jprm_price_label' ) ) );
        update_post_meta( $post_id, '_jprm_price_label_custom',  sanitize_text_field( $get_text( 'jprm_price_label_custom' ) ) );
        update_post_meta( $post_id, '_jprm_multi',               $get_bool( 'jprm_multi' ) );
        // Multi rows – sanitize each field.
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

    /**
     * Elementor integration
     */
    public function register_category( $elements_manager ) {
        $elements_manager->add_category(
            'jellopoint-widgets',
            [
                'title' => __( 'JelloPoint Widgets', 'jellopoint-restaurant-menu' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    public function register_widget( $widgets_manager ) {
        if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
            $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
        }
    }

    public function enqueue_assets() {
        // Keep URLs robust if constants are defined in the main plugin file.
        if ( defined( 'JPRM_PLUGIN_URL' ) && defined( 'JPRM_VERSION' ) ) {
            wp_enqueue_style( 'jprm-frontend', JPRM_PLUGIN_URL . 'assets/css/frontend.css', [], JPRM_VERSION );
        }
    }

    /**
     * Optional shortcode renderer for dynamic output (conservative; adapt as needed).
     * Usage: [jprm_menu id="123"]
     */
    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'jprm_menu' );
        $post_id = absint( $atts['id'] );
        if ( ! $post_id ) {
            return '';
        }
        $title   = get_the_title( $post_id );
        $desc    = get_post_meta( $post_id, '_jprm_desc', true );
        $price   = get_post_meta( $post_id, '_jprm_price', true );
        $badge   = get_post_meta( $post_id, '_jprm_badge', true );
        $visible = (bool) get_post_meta( $post_id, '_jprm_visible', true );

        if ( ! $visible ) {
            return '';
        }

        ob_start();
        ?>
        <div class="jprm-item">
            <div class="jprm-item__head">
                <span class="jprm-item__title"><?php echo esc_html( $title ); ?></span>
                <?php if ( $badge ) : ?><span class="jprm-item__badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
            </div>
            <?php if ( $desc ) : ?>
                <div class="jprm-item__desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
            <?php endif; ?>
            <?php if ( $price ) : ?>
                <div class="jprm-item__price"><?php echo esc_html( $price ); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Bootstrap
// Only instantiate once.
if ( ! function_exists( __NAMESPACE__ . '\\jprm_bootstrap' ) ) {
    function jprm_bootstrap() {
        static $inst = null;
        if ( null === $inst ) {
            $inst = new Plugin();
        }
        return $inst;
    }
    jprm_bootstrap();
}