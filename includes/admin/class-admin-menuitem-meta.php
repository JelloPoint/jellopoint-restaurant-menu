<?php
/**
 * Admin: Menu Item Meta (Pricing + Basics)
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Admin_MenuItem_Meta') ) {

class JPRM_Admin_MenuItem_Meta {

    public static function init(){
        add_action('add_meta_boxes', [__CLASS__, 'register_metabox']);
        add_action('save_post_jprm_menu_item', [__CLASS__, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        // As a safety net, hide the core editor if CPT still has 'editor' support.
        add_action('admin_head', [__CLASS__, 'hide_core_editor']);
    }

    public static function hide_core_editor(){
        $scr = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( $scr && $scr->post_type === 'jprm_menu_item' ){
            // Hide classic editor canvas and "Add Media" if somehow present.
            echo '<style>#postdivrich, #wp-content-media-buttons{display:none!important;}</style>';
        }
    }

    public static function enqueue($hook){
        $scr = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $scr || $scr->post_type !== 'jprm_menu_item' ) return;

        wp_enqueue_script('jquery');
        if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();

        // Build script URL relative to plugin root (robust if includes/ changes).
        $plugin_root_dir = dirname(dirname(__FILE__)); // .../includes
        $plugin_url      = plugin_dir_url($plugin_root_dir); // URL to plugin root
        $src             = $plugin_url . 'assets/admin/menu-item-meta.js';

        wp_enqueue_script('jprm-menu-item-meta', $src, ['jquery'], '1.0.3', true);

        $labels = class_exists('JPRM_Labels_Store') ? JPRM_Labels_Store::all() : [];
        wp_localize_script('jprm-menu-item-meta', 'JPRM_META', [
            'labels' => $labels,
            'postId' => get_the_ID(),
            'i18n'   => [
                'priceMode' => __('Price Mode', 'jellopoint-restaurant-menu'),
                'single'    => __('Single Price', 'jellopoint-restaurant-menu'),
                'multi'     => __('Multiple Prices', 'jellopoint-restaurant-menu'),
                'select'    => __('Select…', 'jellopoint-restaurant-menu'),
                'custom'    => __('Custom', 'jellopoint-restaurant-menu'),
                'label'     => __('Label', 'jellopoint-restaurant-menu'),
                'amount'    => __('Amount', 'jellopoint-restaurant-menu'),
                'hideIcon'  => __('Hide Icon', 'jellopoint-restaurant-menu'),
                'actions'   => __('Actions', 'jellopoint-restaurant-menu'),
                'addRow'    => __('Add another price', 'jellopoint-restaurant-menu'),
                'remove'    => __('Remove', 'jellopoint-restaurant-menu'),
                'predefined'=> __('Predefined', 'jellopoint-restaurant-menu'),
            ]
        ]);
    }

    public static function register_metabox(){
        // Remove a known legacy box ID if still registered (prevents duplicates).
        remove_meta_box('jprm_menu_item_settings', 'jprm_menu_item', 'normal');

        add_meta_box(
            'jprm_price_meta',
            __('Pricing', 'jellopoint-restaurant-menu'),
            [__CLASS__, 'render'],
            'jprm_menu_item',
            'normal',
            'default'
        );
    }

    public static function render($post){
        wp_nonce_field('jprm_meta', 'jprm_meta_nonce');

        // Basics
        $desc   = get_post_meta($post->ID, 'jprm_desc', true);
        $badge  = get_post_meta($post->ID, 'jprm_badge', true);
        $vis    = get_post_meta($post->ID, 'jprm_visible', true) === 'yes';

        // Pricing
        $mode   = get_post_meta($post->ID, 'jprm_price_mode', true) ?: 'single';
        $amount = get_post_meta($post->ID, 'jprm_price_amount', true);
        $lm     = get_post_meta($post->ID, 'jprm_price_label_mode', true) ?: 'ref';
        $lref   = get_post_meta($post->ID, 'jprm_price_label_ref', true);
        $lcus   = get_post_meta($post->ID, 'jprm_price_label_custom', true);
        $rows   = get_post_meta($post->ID, 'jprm_prices', true);
        if (is_string($rows) && $rows !== '') $rows = json_decode($rows, true);
        if (!is_array($rows)) $rows = [];

        echo '<table class="form-table"><tbody>';

        // Short Description
        echo '<tr><th><label for="jprm_desc">'.esc_html__('Short Description','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%%;">%s</textarea>', esc_textarea($desc));
        echo '</td></tr>';

        // Badge Text
        echo '<tr><th><label for="jprm_badge">'.esc_html__('Badge Text','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<input type="text" id="jprm_badge" name="jprm_badge" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($badge), esc_attr__('e.g. Chef’s choice','jellopoint-restaurant-menu'));
        echo '</td></tr>';

        // Visible
        echo '<tr><th><label for="jprm_visible">'.esc_html__('Visible','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="yes" %s> %s</label>',
            checked($vis, true, false),
            esc_html__('Show this item on the site','jellopoint-restaurant-menu')
        );
        echo '</td></tr>';

        // Divider
        echo '<tr><td colspan="2"><hr /></td></tr>';

        // Price Mode
        echo '<tr><th><label>'.esc_html__('Price Mode','jellopoint-restaurant-menu').'</label></th><td>';
        echo '<label><input type="radio" name="jprm_price_mode" value="single" '.checked($mode,'single',false).'> '.esc_html__('Single Price','jellopoint-restaurant-menu').'</label> &nbsp; ';
        echo '<label><input type="radio" name="jprm_price_mode" value="multi"  '.checked($mode,'multi', false).'> '.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</label>';
        echo '</td></tr>';

        // Single Price block
        echo '<tr class="jprm-block-single"><th><label for="jprm_price_amount">'.esc_html__('Price','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<input type="text" id="jprm_price_amount" name="jprm_price_amount" value="%s" class="regular-text" placeholder="%s" /> ',
            esc_attr($amount), esc_attr('€ 7,50'));
        echo '</td></tr>';

        echo '<tr class="jprm-block-single"><th><label>'.esc_html__('Price Label','jellopoint-restaurant-menu').'</label></th><td>';
        echo '<select id="jprm_price_label_mode" name="jprm_price_label_mode">';
        echo '<option value="ref" '.selected($lm,'ref',false).'>'.esc_html__('Predefined','jellopoint-restaurant-menu').'</option>';
        echo '<option value="custom" '.selected($lm,'custom',false).'>'.esc_html__('Custom','jellopoint-restaurant-menu').'</option>';
        echo '</select> ';
        printf('<select id="jprm_price_label_ref" name="jprm_price_label_ref" data-current="%s"></select> ',
            esc_attr($lref));
        printf('<input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($lcus), esc_attr__('Custom label','jellopoint-restaurant-menu'));
        echo '</td></tr>';

        // Multiple Prices block
        echo '<tr class="jprm-block-multi"><th>'.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</th><td>';
        ?>
        <table class="widefat fixed striped" id="jprm-prices-table">
            <thead>
                <tr>
                    <th style="width:4%"></th>
                    <th style="width:26%"><?php echo esc_html__('Label','jellopoint-restaurant-menu'); ?></th>
                    <th style="width:26%"><?php echo esc_html__('Amount','jellopoint-restaurant-menu'); ?></th>
                    <th style="width:12%"><?php echo esc_html__('Hide Icon','jellopoint-restaurant-menu'); ?></th>
                    <th><?php echo esc_html__('Actions','jellopoint-restaurant-menu'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) :
                    $en   = !empty($r['enabled']);
                    $lmd  = $r['label_mode'] ?? 'ref';
                    $lrf  = $r['label_ref']  ?? '';
                    $lct  = $r['label_custom'] ?? '';
                    $amt  = $r['amount'] ?? '';
                    $hide = !empty($r['hide_icon']);
                ?>
                <tr>
                    <td><input type="checkbox" class="enable" <?php checked($en, true); ?> /></td>
                    <td class="label-td">
                        <select class="label-mode"><option value="ref" <?php selected($lmd,'ref'); ?>><?php echo esc_html__('Predefined','jellopoint-restaurant-menu'); ?></option><option value="custom" <?php selected($lmd,'custom'); ?>><?php echo esc_html__('Custom','jellopoint-restaurant-menu'); ?></option></select>
                        <select class="label-ref" data-current="<?php echo esc_attr($lrf); ?>"></select>
                        <input type="text" class="label-custom regular-text" value="<?php echo esc_attr($lct); ?>" placeholder="<?php echo esc_attr__('Custom label','jellopoint-restaurant-menu'); ?>" />
                    </td>
                    <td><input type="text" class="amount regular-text" value="<?php echo esc_attr($amt); ?>" placeholder="€ 7,50" /></td>
                    <td><input type="checkbox" class="hide-icon" <?php checked($hide, true); ?> /></td>
                    <td><a href="#" class="button button-secondary jprm-row-remove"><?php echo esc_html__('Remove','jellopoint-restaurant-menu'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="#" class="button" id="jprm-row-add"><?php echo esc_html__('Add another price','jellopoint-restaurant-menu'); ?></a></p>
        <input type="hidden" id="jprm_prices" name="jprm_prices" value="<?php echo esc_attr( json_encode($rows) ); ?>" />
        <?php
        echo '</td></tr>';
        echo '</tbody></table>';
    }

    public static function save($post_id, $post){
        if ( ! isset($_POST['jprm_meta_nonce']) || ! wp_verify_nonce($_POST['jprm_meta_nonce'], 'jprm_meta') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        // Basics
        $desc  = isset($_POST['jprm_desc']) ? wp_kses_post($_POST['jprm_desc']) : '';
        $badge = sanitize_text_field( $_POST['jprm_badge'] ?? '' );
        $vis   = isset($_POST['jprm_visible']) && $_POST['jprm_visible'] === 'yes' ? 'yes' : 'no';

        update_post_meta($post_id, 'jprm_desc', $desc);
        update_post_meta($post_id, 'jprm_badge', $badge);
        update_post_meta($post_id, 'jprm_visible', $vis);

        // Pricing
        $mode = ($_POST['jprm_price_mode'] ?? 'single') === 'multi' ? 'multi' : 'single';
        update_post_meta($post_id, 'jprm_price_mode', $mode);

        if ( $mode === 'single' ) {
            $amount = sanitize_text_field( $_POST['jprm_price_amount'] ?? '' );
            update_post_meta($post_id, 'jprm_price_amount', $amount);

            $lm = ($_POST['jprm_price_label_mode'] ?? 'ref') === 'custom' ? 'custom' : 'ref';
            update_post_meta($post_id, 'jprm_price_label_mode', $lm);

            if ( $lm === 'ref' ) {
                $ref = sanitize_text_field( $_POST['jprm_price_label_ref'] ?? '' );
                update_post_meta($post_id, 'jprm_price_label_ref', $ref );
                delete_post_meta($post_id, 'jprm_price_label_custom');
            } else {
                $cus = sanitize_text_field( $_POST['jprm_price_label_custom'] ?? '' );
                update_post_meta($post_id, 'jprm_price_label_custom', $cus );
                delete_post_meta($post_id, 'jprm_price_label_ref');
            }
            // Clean multi if switching from multi to single
            if ( isset($_POST['jprm_prices']) === false ) {
                delete_post_meta($post_id, 'jprm_prices');
            }
        } else {
            $json = $_POST['jprm_prices'] ?? '[]';
            if ( is_string($json) ) {
                $rows = json_decode( wp_unslash($json), true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array($rows) ) {
                    $out = [];
                    foreach ( $rows as $r ) {
                        if ( ! is_array($r) ) continue;
                        $out[] = [
                            'enabled'      => !empty($r['enabled']),
                            'label_mode'   => ($r['label_mode'] ?? 'ref') === 'custom' ? 'custom' : 'ref',
                            'label_ref'    => sanitize_text_field($r['label_ref'] ?? ''),
                            'label_custom' => sanitize_text_field($r['label_custom'] ?? ''),
                            'amount'       => sanitize_text_field($r['amount'] ?? ''),
                            'hide_icon'    => !empty($r['hide_icon']),
                        ];
                    }
                    update_post_meta($post_id, 'jprm_prices', wp_json_encode($out));
                }
            }
            // Clean single fields if in multi mode
            delete_post_meta($post_id, 'jprm_price_amount');
            delete_post_meta($post_id, 'jprm_price_label_mode');
            delete_post_meta($post_id, 'jprm_price_label_ref');
            delete_post_meta($post_id, 'jprm_price_label_custom');
        }
    }
}

}

JPRM_Admin_MenuItem_Meta::init();