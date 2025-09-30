<?php
/**
 * Admin: Menu Item Meta
 *  - Separate meta boxes:
 *      1) Description  (top)
 *      2) Pricing      (middle)
 *      3) Visibility & Badge (bottom)
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Admin_MenuItem_Meta') ) {

class JPRM_Admin_MenuItem_Meta {

    public static function init(){
        add_action('add_meta_boxes', [__CLASS__, 'register_metaboxes']);
        add_action('save_post_jprm_menu_item', [__CLASS__, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_head', [__CLASS__, 'hide_core_editor']);
    }

    public static function hide_core_editor(){
        $scr = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( $scr && $scr->post_type === 'jprm_menu_item' ){
            echo '<style>#postdivrich, #wp-content-media-buttons{display:none!important;}</style>';
        }
    }

    public static function enqueue($hook){
        $scr = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $scr || $scr->post_type !== 'jprm_menu_item' ) return;

        wp_enqueue_script('jquery');
        if ( function_exists('wp_enqueue_media') ) wp_enqueue_media();

        // Keep path correct to avoid 404s in console.
        $plugin_main_file = JPRM_PLUGIN_PATH . 'jellopoint-restaurant-menu.php';
        $rel  = 'assets/admin/menu-item-meta.js';
        $src  = plugins_url($rel, $plugin_main_file);
        $path = JPRM_PLUGIN_PATH . $rel;
        $ver  = file_exists($path) ? (string) filemtime($path) : '1.1.1';

        wp_enqueue_script('jprm-menu-item-meta', $src, ['jquery'], $ver, true);

        // Optional localize
        $labels = class_exists('JPRM_Labels_Store') ? JPRM_Labels_Store::all() : [];
        foreach ($labels as &$L){
            $iid = isset($L['icon_id']) ? intval($L['icon_id']) : (isset($L['icon']) ? intval($L['icon']) : 0);
            $L['icon_url'] = $iid ? wp_get_attachment_url($iid) : '';
        }
        unset($L);
        wp_localize_script('jprm-menu-item-meta', 'JPRM_META', [
            'labels' => $labels,
            'postId' => get_the_ID(),
        ]);
    }

    public static function register_metaboxes(){
        remove_meta_box('jprm_menu_item_settings', 'jprm_menu_item', 'normal');

        add_meta_box(
            'jprm_item_desc',
            __('Description', 'jellopoint-restaurant-menu'),
            [__CLASS__, 'render_desc'],
            'jprm_menu_item',
            'normal',
            'high'
        );

        add_meta_box(
            'jprm_price_meta',
            __('Pricing', 'jellopoint-restaurant-menu'),
            [__CLASS__, 'render_pricing'],
            'jprm_menu_item',
            'normal',
            'default'
        );

        add_meta_box(
            'jprm_item_vis',
            __('Visibility & Badge', 'jellopoint-restaurant-menu'),
            [__CLASS__, 'render_visibility'],
            'jprm_menu_item',
            'normal',
            'low'
        );
    }

    /* -------------------- RENDERERS -------------------- */

    public static function render_desc($post){
        wp_nonce_field('jprm_meta', 'jprm_meta_nonce');

        $desc = get_post_meta($post->ID, 'jprm_desc', true);

        echo '<table class="form-table"><tbody>';
        echo '<tr><th style="width:180px;"><label for="jprm_desc">'.esc_html__('Short Description','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%%;">%s</textarea>', esc_textarea($desc));
        echo '</td></tr>';
        echo '</tbody></table>';
    }

    public static function render_pricing($post){
        // Values
        $mode   = get_post_meta($post->ID, 'jprm_price_mode', true) ?: 'single';
        $amount = get_post_meta($post->ID, 'jprm_price_amount', true);

        $lm     = get_post_meta($post->ID, 'jprm_price_label_mode', true) ?: 'ref'; // ref|custom
        $lref   = (string) get_post_meta($post->ID, 'jprm_price_label_ref', true);
        $lcus   = get_post_meta($post->ID, 'jprm_price_label_custom', true);
        $icon   = (int) get_post_meta($post->ID, 'jprm_price_label_icon_id', true); // custom icon id

        $rows   = get_post_meta($post->ID, 'jprm_prices', true);
        if (is_string($rows) && $rows !== '') $rows = json_decode($rows, true);
        if (!is_array($rows)) $rows = [];

        // Labels and options (server-side)
        $labels = class_exists('JPRM_Labels_Store') ? JPRM_Labels_Store::all() : [];

        // Robust URL resolver (handles SVG): thumb → medium → full → attachment_url
        $get_icon_url = function( $attachment_id ) {
            $sizes = ['thumbnail', 'medium', 'full'];
            foreach ( $sizes as $size ) {
                $src = wp_get_attachment_image_src( (int)$attachment_id, $size );
                if ( is_array($src) && ! empty($src[0]) ) {
                    return $src[0];
                }
            }
            $fallback = wp_get_attachment_url( (int)$attachment_id );
            return $fallback ? $fallback : '';
        };

        $label_options    = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
        $predef_icon_url  = '';

        foreach ( $labels as $L ) {
            $id   = isset($L['id']) ? (string)$L['id'] : '';
            $text = isset($L['label']) ? (string)$L['label'] : $id;

            // accept both icon_id and icon keys
            $iid  = isset($L['icon_id']) ? (int)$L['icon_id'] : (isset($L['icon']) ? (int)$L['icon'] : 0);
            $iurl = $iid ? $get_icon_url($iid) : '';

            $sel  = selected($lref, $id, false);
            $label_options .= '<option value="'.esc_attr($id).'" '.$sel.' data-icon="'.esc_attr($iurl).'">'.esc_html($text).'</option>';

            if ( $lm === 'ref' && $lref !== '' && $lref === $id ) {
                $predef_icon_url = $iurl;
            }
        }

        // Custom icon URL (if any; works for SVG too)
        $custom_icon_url = $icon ? $get_icon_url($icon) : '';
        $initial_icon_url = $lm === 'ref' ? $predef_icon_url : ($custom_icon_url ?: '');

        echo '<table class="form-table"><tbody>';

        // Mode
        echo '<tr><th style="width:180px;"><label>'.esc_html__('Price Mode','jellopoint-restaurant-menu').'</label></th><td>';
        echo '<label><input type="radio" name="jprm_price_mode" value="single" '.checked($mode,'single',false).'> '.esc_html__('Single Price','jellopoint-restaurant-menu').'</label> &nbsp; ';
        echo '<label><input type="radio" name="jprm_price_mode" value="multi"  '.checked($mode,'multi', false).'> '.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</label>';
        echo '</td></tr>';

        // Single amount
        echo '<tr class="jprm-block-single"><th><label for="jprm_price_amount">'.esc_html__('Price','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<input type="text" id="jprm_price_amount" name="jprm_price_amount" value="%s" class="regular-text" placeholder="%s" /> ',
            esc_attr($amount), esc_attr('€ 7,50'));
        echo '</td></tr>';

        // Single label + icon (one line)
        echo '<tr class="jprm-block-single"><th><label>'.esc_html__('Price Label','jellopoint-restaurant-menu').'</label></th><td>';

        echo '<style>
            .jprm-inline { display:flex; gap:8px; align-items:center; flex-wrap:nowrap; }
            .jprm-inline select, .jprm-inline input[type="text"] { max-width:220px; }
            /* IMPORTANT: give SVG a concrete width so it renders */
            .jprm-inline .jprm-icon-preview img { width:32px; height:auto; display:block; }
            .jprm-inline .button, .jprm-inline .button-link { white-space:nowrap; }
        </style>';

        echo '<div class="jprm-inline">';
            echo '<select id="jprm_price_label_mode" name="jprm_price_label_mode">';
            echo '<option value="ref" '.selected($lm,'ref',false).'>'.esc_html__('Predefined','jellopoint-restaurant-menu').'</option>';
            echo '<option value="custom" '.selected($lm,'custom',false).'>'.esc_html__('Custom','jellopoint-restaurant-menu').'</option>';
            echo '</select>';

            echo '<select id="jprm_price_label_ref" name="jprm_price_label_ref">'.$label_options.'</select> ';

            printf('<input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="%s" class="regular-text" placeholder="%s" />',
                esc_attr($lcus), esc_attr__('Custom','jellopoint-restaurant-menu'));

            echo '<div id="jprm_single_icon_wrap" class="jprm-icon-wrap">';
                echo '<div id="jprm_single_icon_preview" class="jprm-icon-preview">';
                if ($initial_icon_url) { echo '<img src="'.esc_url($initial_icon_url).'" alt="" style="width:32px;height:auto;" />'; }
                echo '</div>';
                // Pass data-url so JS can render even if server didn’t print <img>
                printf('<input type="hidden" id="jprm_price_label_icon_id" name="jprm_price_label_icon_id" value="%d" data-url="%s" />',
                    $icon, esc_attr($custom_icon_url));
            echo '</div>';

            echo '<div id="jprm_single_icon_actions" class="jprm-icon-actions" style="'.($lm==='custom' ? '' : 'display:none;').'">';
                echo '<button type="button" class="button jprm-single-icon-select">'.esc_html__('Select Icon','jellopoint-restaurant-menu').'</button> ';
                echo '<button type="button" class="button-link jprm-single-icon-clear" style="'.($icon ? '' : 'display:none;').'">'.esc_html__('Remove Icon','jellopoint-restaurant-menu').'</button>';
            echo '</div>';
        echo '</div>';

        echo '</td></tr>';

        // Multiple Prices (unchanged)
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
                        <select class="label-mode">
                            <option value="ref"   <?php selected($lmd,'ref'); ?>><?php echo esc_html__('Predefined','jellopoint-restaurant-menu'); ?></option>
                            <option value="custom"<?php selected($lmd,'custom'); ?>><?php echo esc_html__('Custom','jellopoint-restaurant-menu'); ?></option>
                        </select>
                        <select class="label-ref" data-current="<?php echo esc_attr($lrf); ?>">
                            <?php echo $label_options; ?>
                        </select>
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

        // Inline JS (tiny & robust)
        ?>
        <script>
        (function($){
            function setModeUI(){
                var mode = $('input[name="jprm_price_mode"]:checked').val() || 'single';
                if (mode === 'single'){ $('.jprm-block-single').show(); $('.jprm-block-multi').hide(); }
                else { $('.jprm-block-single').hide(); $('.jprm-block-multi').show(); }
            }
            function toggleSingleControls(){
                var mode = $('#jprm_price_label_mode').val();
                if (mode === 'custom'){
                    $('#jprm_price_label_custom').show();
                    $('#jprm_price_label_ref').hide();
                    $('#jprm_single_icon_actions').show();
                } else {
                    $('#jprm_price_label_custom').hide();
                    $('#jprm_price_label_ref').show();
                    $('#jprm_single_icon_actions').hide();
                }
                refreshSingleIcon();
            }
            function refreshSingleIcon(){
                var mode = $('#jprm_price_label_mode').val();
                if (mode === 'custom'){
                    var id  = $('#jprm_price_label_icon_id').val();
                    var url = $('#jprm_price_label_icon_id').data('url') || '';
                    if (id && id !== '0' && $('#jprm_single_icon_preview').is(':empty') && url){
                        $('#jprm_single_icon_preview').html('<img src="'+url+'" style="width:32px;height:auto;" alt="" />');
                    }
                    $('.jprm-single-icon-clear').toggle(!!id && id !== '0');
                } else {
                    var $opt = $('#jprm_price_label_ref').find('option:selected');
                    var url  = $opt.data('icon') || '';
                    if (url){
                        $('#jprm_single_icon_preview').html('<img src="'+url+'" style="width:32px;height:auto;" alt="" />');
                    } else {
                        $('#jprm_single_icon_preview').empty();
                    }
                }
            }
            var mediaFrame = null;
            function ensureFrame(){
                if (mediaFrame) return mediaFrame;
                mediaFrame = wp.media({
                    title: '<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>',
                    multiple: false,
                    library: { type: 'image' },
                    button: { text: '<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>' }
                });
                mediaFrame.on('select', function(){
                    var file = mediaFrame.state().get('selection').first();
                    if (!file) return;
                    var id  = file.get('id');
                    var url = (file.get('sizes') && file.get('sizes').thumbnail && file.get('sizes').thumbnail.url) || file.get('url');
                    $('#jprm_price_label_icon_id').val(String(id)).attr('data-url', url || '');
                    $('#jprm_single_icon_preview').html('<img src="'+(url||'')+'" style="width:32px;height:auto;" alt="" />');
                    $('.jprm-single-icon-clear').show();
                });
                return mediaFrame;
            }

            // Multi helpers
            function rowToObj($tr){
                return {
                    enabled:     $tr.find('input.enable').is(':checked'),
                    label_mode:  $tr.find('select.label-mode').val() === 'custom' ? 'custom' : 'ref',
                    label_ref:   $tr.find('select.label-ref').val() || '',
                    label_custom:$tr.find('input.label-custom').val() || '',
                    amount:      $tr.find('input.amount').val() || '',
                    hide_icon:   $tr.find('input.hide-icon').is(':checked')
                };
            }
            function collectMulti(){
                var out = [];
                $('#jprm-prices-table tbody tr').each(function(){ out.push(rowToObj($(this))); });
                $('#jprm_prices').val( JSON.stringify(out) );
            }
            function syncRowUI($tr){
                var lm = $tr.find('select.label-mode').val();
                if (lm === 'custom'){ $tr.find('input.label-custom').show(); $tr.find('select.label-ref').hide(); }
                else { $tr.find('input.label-custom').hide(); $tr.find('select.label-ref').show(); }
            }

            $(function(){
                $('input[name="jprm_price_mode"]').on('change', setModeUI);
                setModeUI();

                $('#jprm_price_label_mode').on('change', toggleSingleControls);
                $('#jprm_price_label_ref').on('change', refreshSingleIcon);
                toggleSingleControls(); // initial + icon

                $(document).on('click', '.jprm-single-icon-select', function(e){ e.preventDefault(); ensureFrame().open(); });
                $(document).on('click', '.jprm-single-icon-clear', function(e){
                    e.preventDefault();
                    $('#jprm_price_label_icon_id').val('0').attr('data-url','');
                    $('#jprm_single_icon_preview').empty();
                    $(this).hide();
                });

                var $tb = $('#jprm-prices-table tbody');
                $tb.find('tr').each(function(){ syncRowUI($(this)); });
                $tb.on('change', 'input,select', collectMulti);
                $tb.on('click', '.jprm-row-remove', function(e){ e.preventDefault(); $(this).closest('tr').remove(); collectMulti(); });

                $('#jprm-row-add').on('click', function(e){
                    e.preventDefault();
                    var $tr = $('<tr/>');
                    $tr.append('<td><input type="checkbox" class="enable" checked /></td>');
                    $tr.append('<td class="label-td">\
                        <select class="label-mode">\
                            <option value="ref"><?php echo esc_js(__('Predefined','jellopoint-restaurant-menu')); ?></option>\
                            <option value="custom"><?php echo esc_js(__('Custom','jellopoint-restaurant-menu')); ?></option>\
                        </select> \
                        <select class="label-ref"><?php echo str_replace(array("\n","\r"), '', $label_options); ?></select> \
                        <input type="text" class="label-custom regular-text" value="" placeholder="<?php echo esc_js(__('Custom label','jellopoint-restaurant-menu')); ?>" />\
                    </td>');
                    $tr.append('<td><input type="text" class="amount regular-text" value="" placeholder="€ 7,50" /></td>');
                    $tr.append('<td><input type="checkbox" class="hide-icon" /></td>');
                    $tr.append('<td><a href="#" class="button button-secondary jprm-row-remove"><?php echo esc_js(__('Remove','jellopoint-restaurant-menu')); ?></a></td>');
                    $('#jprm-prices-table tbody').append($tr);
                    syncRowUI($tr);
                    collectMulti();
                });

                collectMulti();
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function render_visibility($post){
        $badge  = get_post_meta($post->ID, 'jprm_badge', true);
        $vis    = get_post_meta($post->ID, 'jprm_visible', true) === 'yes';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th style="width:180px;"><label for="jprm_visible">'.esc_html__('Visible','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<label><input type="checkbox" id="jprm_visible" name="jprm_visible" value="yes" %s> %s</label>',
            checked($vis, true, false),
            esc_html__('Show this item on the site','jellopoint-restaurant-menu')
        );
        echo '</td></tr>';

        echo '<tr><th><label for="jprm_badge">'.esc_html__('Badge Text','jellopoint-restaurant-menu').'</label></th><td>';
        printf('<input type="text" id="jprm_badge" name="jprm_badge" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($badge), esc_attr__('e.g. Chef’s choice','jellopoint-restaurant-menu'));
        echo '</td></tr>';

        echo '</tbody></table>';
    }

    /* -------------------- SAVE -------------------- */

    public static function save($post_id, $post){
        if ( ! isset($_POST['jprm_meta_nonce']) || ! wp_verify_nonce($_POST['jprm_meta_nonce'], 'jprm_meta') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $desc  = isset($_POST['jprm_desc']) ? wp_kses_post($_POST['jprm_desc']) : '';
        update_post_meta($post_id, 'jprm_desc', $desc);

        $badge = sanitize_text_field( $_POST['jprm_badge'] ?? '' );
        $vis   = isset($_POST['jprm_visible']) && $_POST['jprm_visible'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, 'jprm_badge', $badge);
        update_post_meta($post_id, 'jprm_visible', $vis);

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
                delete_post_meta($post_id, 'jprm_price_label_icon_id');
            } else {
                $cus  = sanitize_text_field( $_POST['jprm_price_label_custom'] ?? '' );
                $icon = isset($_POST['jprm_price_label_icon_id']) ? intval($_POST['jprm_price_label_icon_id']) : 0;
                update_post_meta($post_id, 'jprm_price_label_custom', $cus );
                update_post_meta($post_id, 'jprm_price_label_icon_id', $icon );
                delete_post_meta($post_id, 'jprm_price_label_ref');
            }

            if ( ! isset($_POST['jprm_prices']) ) {
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
            delete_post_meta($post_id, 'jprm_price_amount');
            delete_post_meta($post_id, 'jprm_price_label_mode');
            delete_post_meta($post_id, 'jprm_price_label_ref');
            delete_post_meta($post_id, 'jprm_price_label_custom');
            delete_post_meta($post_id, 'jprm_price_label_icon_id');
        }
    }
}

}

JPRM_Admin_MenuItem_Meta::init();
