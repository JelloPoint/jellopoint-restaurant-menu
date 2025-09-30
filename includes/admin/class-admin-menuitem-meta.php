<?php
/**
 * Admin: Menu Item Meta
 *  - Meta boxes:
 *      1) Description
 *      2) Pricing
 *      3) Visibility & Badge
 *
 * Multiple Prices (compact):
 *   - Preset/Custom pill switch per row (inline)
 *   - 24px icon preview (explicit width → SVG renders)
 *   - Click preview/placeholder to select icon (Custom mode)
 *   - Small × clears icon (Custom mode)
 *   - Trash icon removes row
 *   - Robust save/load (no losing labels after save)
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

        // Keep your external admin JS enqueued (bump ver to bust cache if needed)
        $plugin_main_file = JPRM_PLUGIN_PATH . 'jellopoint-restaurant-menu.php';
        $rel  = 'assets/admin/menu-item-meta.js';
        $src  = plugins_url($rel, $plugin_main_file);
        $path = JPRM_PLUGIN_PATH . $rel;
        $ver  = file_exists($path) ? (string) filemtime($path) : '1.3.2';
        wp_enqueue_script('jprm-menu-item-meta', $src, ['jquery'], $ver, true);
    }

    public static function register_metaboxes(){
        // Remove legacy to avoid duplicates
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
        // Current values (single)
        $mode   = get_post_meta($post->ID, 'jprm_price_mode', true) ?: 'single';
        $amount = get_post_meta($post->ID, 'jprm_price_amount', true);

        $lm     = get_post_meta($post->ID, 'jprm_price_label_mode', true) ?: 'ref'; // ref|custom
        $lref   = (string) get_post_meta($post->ID, 'jprm_price_label_ref', true);
        $lcus   = get_post_meta($post->ID, 'jprm_price_label_custom', true);
        $icon   = (int) get_post_meta($post->ID, 'jprm_price_label_icon_id', true); // custom icon id (single)

        // Multiple rows
        $rows   = get_post_meta($post->ID, 'jprm_prices', true);
        if (is_string($rows) && $rows !== '') $rows = json_decode($rows, true);
        if (!is_array($rows)) $rows = [];

        // Labels (predefined)
        $labels = class_exists('JPRM_Labels_Store') ? JPRM_Labels_Store::all() : [];
        $label_map = []; // id => ['text'=>, 'icon_id'=>]
        foreach ($labels as $L){
            $label_map[ (string)($L['id'] ?? '') ] = [
                'text'    => (string)($L['label'] ?? ''),
                'icon_id' => isset($L['icon_id']) ? (int)$L['icon_id'] : (isset($L['icon']) ? (int)$L['icon'] : 0),
            ];
        }

        // SVG-safe URL resolver
        $get_icon_url = function( $attachment_id ) {
            $sizes = ['thumbnail', 'medium', 'full'];
            foreach ( $sizes as $size ) {
                $src = wp_get_attachment_image_src( (int)$attachment_id, $size );
                if ( is_array($src) && ! empty($src[0]) ) return $src[0];
            }
            $fallback = wp_get_attachment_url( (int)$attachment_id );
            return $fallback ? $fallback : '';
        };

        // Build options for SINGLE preset select
        $single_options = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
        $predef_icon_url = '';
        foreach ($label_map as $id => $info){
            $text = $info['text'] ?: $id;
            $iurl = $info['icon_id'] ? $get_icon_url($info['icon_id']) : '';
            $sel  = selected($lref, $id, false);
            $single_options .= '<option value="'.esc_attr($id).'" '.$sel.' data-icon="'.esc_attr($iurl).'">'.esc_html($text).'</option>';
            if ($lm === 'ref' && $lref === $id) $predef_icon_url = $iurl;
        }

        // Single: Custom icon URL (if any)
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
            .jprm-inline { display:inline-flex; gap:8px; align-items:center; flex-wrap:nowrap; }
            .jprm-inline select, .jprm-inline input[type="text"] { max-width:220px; }
            .jprm-inline .jprm-icon-preview img { width:32px; height:auto; display:block; } /* explicit width → SVG ok */
            .jprm-inline .button, .jprm-inline .button-link { white-space:nowrap; }

            /* Multiple table compact layout */
            #jprm-prices-table .small { width:110px; }
            #jprm-prices-table .label-cell { min-width:360px; }
            #jprm-prices-table .icon-col { width:150px; }
            #jprm-prices-table td, #jprm-prices-table th { vertical-align:middle; }

            .jprm-mode-switch { display:inline-flex; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden; }
            .jprm-pill { padding:2px 8px; cursor:pointer; background:#f6f7f7; border-right:1px solid #ccd0d4; user-select:none; }
            .jprm-pill:last-child { border-right:none; }
            .jprm-pill.active { background:#2271b1; color:#fff; }

            .label-td .inline-field { display:inline-flex; gap:8px; align-items:center; }
            .label-td select.label-ref { max-width:220px; }
            .label-td input.label-custom { max-width:220px; }

            .jprm-icon-cell { display:inline-flex; align-items:center; gap:8px; }
            .jprm-row-icon-preview img { width:24px; height:auto; display:block; }
            .jprm-icon-ph { width:24px; height:24px; border:1px dashed #ccd0d4; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; color:#777; background:#fff; cursor:pointer; }
            .jprm-icon-ph .dashicons { line-height:24px; }

            .btn-link-icon { border:none; background:transparent; color:#b32d2e; height:28px; width:28px; }
            .btn-link-icon:hover { color:#dc3232; background:#fbeaea; border-radius:3px; }

            .btn-trash { border:none; background:transparent; width:32px; height:32px; color:#646970; cursor:pointer; }
            .btn-trash:hover { color:#b32d2e; background:#fbeaea; border-radius:3px; }
        </style>';

        echo '<div class="jprm-inline">';
            echo '<select id="jprm_price_label_mode" name="jprm_price_label_mode" style="display:none;">';
            echo '<option value="ref" '.selected($lm,'ref',false).'>ref</option>';
            echo '<option value="custom" '.selected($lm,'custom',false).'>custom</option>';
            echo '</select>';

            // Pills
            echo '<div class="jprm-mode-switch" id="jprm_single_mode_switch">';
            echo '<span class="jprm-pill '.($lm==='ref'?'active':'').'" data-mode="ref">'.esc_html__('Preset','jellopoint-restaurant-menu').'</span>';
            echo '<span class="jprm-pill '.($lm==='custom'?'active':'').'" data-mode="custom">'.esc_html__('Custom','jellopoint-restaurant-menu').'</span>';
            echo '</div>';

            // Preset dropdown
            echo '<select id="jprm_price_label_ref" name="jprm_price_label_ref">'.$single_options.'</select> ';

            // Custom text
            printf('<input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="%s" class="regular-text" placeholder="%s" />',
                esc_attr($lcus), esc_attr__('Custom','jellopoint-restaurant-menu'));

            // Icon preview + hidden id
            echo '<div id="jprm_single_icon_wrap" class="jprm-icon-wrap">';
                echo '<div id="jprm_single_icon_preview" class="jprm-icon-preview" title="'.esc_attr__('Change icon','jellopoint-restaurant-menu').'" style="cursor:pointer;">';
                if ($initial_icon_url) {
                    echo '<img src="'.esc_url($initial_icon_url).'" alt="" />';
                } else {
                    echo '<span class="jprm-icon-ph" title="'.esc_attr__('Select icon','jellopoint-restaurant-menu').'"><span class="dashicons dashicons-format-image"></span></span>';
                }
                echo '</div>';
                printf('<input type="hidden" id="jprm_price_label_icon_id" name="jprm_price_label_icon_id" value="%d" data-url="%s" />',
                    $icon, esc_attr($custom_icon_url));
            echo '</div>';

            // Clear (only for Custom)
            echo '<div id="jprm_single_icon_actions" class="jprm-icon-actions" style="'.($lm==='custom' ? '' : 'display:none;').'">';
                echo '<button type="button" class="btn-link-icon jprm-single-icon-clear" aria-label="'.esc_attr__('Remove Icon','jellopoint-restaurant-menu').'" style="'.($icon ? '' : 'display:none;').'"><span class="dashicons dashicons-no-alt"></span></button>';
            echo '</div>';
        echo '</div>';

        echo '</td></tr>';

        // Multiple Prices
        echo '<tr class="jprm-block-multi"><th>'.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</th><td>';
        ?>
        <table class="widefat fixed striped" id="jprm-prices-table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th class="label-cell"><?php echo esc_html__('Label','jellopoint-restaurant-menu'); ?></th>
                    <th class="small"><?php echo esc_html__('Amount','jellopoint-restaurant-menu'); ?></th>
                    <th class="icon-col"><?php echo esc_html__('Icon','jellopoint-restaurant-menu'); ?></th>
                    <th style="width:70px;"><?php echo esc_html__('Hide','jellopoint-restaurant-menu'); ?></th>
                    <th style="width:60px;"><?php echo esc_html__('Actions','jellopoint-restaurant-menu'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $r) :
                    // Defaults for legacy rows
                    $en    = array_key_exists('enabled',$r) ? !empty($r['enabled']) : true;
                    $lmd   = ($r['label_mode'] ?? 'ref') === 'custom' ? 'custom' : 'ref';
                    $lrf   = $r['label_ref']    ?? '';
                    $lct   = $r['label_custom'] ?? '';
                    $amt   = $r['amount']       ?? '';
                    $hide  = !empty($r['hide_icon']);
                    $rid   = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;

                    // Build options for THIS row (with selected)
                    $row_options = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
                    $pred_url = '';
                    foreach ($label_map as $id => $info){
                        $text = $info['text'] ?: $id;
                        $iurl = $info['icon_id'] ? $get_icon_url($info['icon_id']) : '';
                        $sel  = selected($lrf, $id, false);
                        $row_options .= '<option value="'.esc_attr($id).'" '.$sel.' data-icon="'.esc_attr($iurl).'">'.esc_html($text).'</option>';
                        if ($lmd==='ref' && $lrf === $id) $pred_url = $iurl;
                    }
                    $cust_url = $rid ? $get_icon_url($rid) : '';
                ?>
                <tr>
                    <td><input type="checkbox" class="enable" <?php checked($en, true); ?> /></td>

                    <td class="label-td">
                        <select class="label-mode" style="display:none;">
                            <option value="ref"   <?php selected($lmd,'ref'); ?>>ref</option>
                            <option value="custom"<?php selected($lmd,'custom'); ?>>custom</option>
                        </select>

                        <div class="jprm-mode-switch">
                            <span class="jprm-pill <?php echo $lmd==='ref'?'active':''; ?>" data-mode="ref"><?php echo esc_html__('Preset','jellopoint-restaurant-menu'); ?></span>
                            <span class="jprm-pill <?php echo $lmd==='custom'?'active':''; ?>" data-mode="custom"><?php echo esc_html__('Custom','jellopoint-restaurant-menu'); ?></span>
                        </div>

                        <span class="inline-field">
                            <select class="label-ref" <?php echo $lmd==='ref' ? '' : 'style="display:none;"'; ?>>
                                <?php echo $row_options; ?>
                            </select>
                            <input type="text" class="label-custom regular-text" value="<?php echo esc_attr($lct); ?>" placeholder="<?php echo esc_attr__('Custom label','jellopoint-restaurant-menu'); ?>" <?php echo $lmd==='custom' ? '' : 'style="display:none;"'; ?> />
                        </span>
                    </td>

                    <td><input type="text" class="amount regular-text small" value="<?php echo esc_attr($amt); ?>" placeholder="€ 7,50" /></td>

                    <td class="jprm-icon-cell">
                        <div class="jprm-row-icon-preview" title="<?php echo esc_attr__('Change icon','jellopoint-restaurant-menu'); ?>" style="cursor:pointer;">
                            <?php if ($lmd==='ref' && $pred_url): ?>
                                <img src="<?php echo esc_url($pred_url); ?>" alt="" />
                            <?php elseif ($lmd==='custom' && $cust_url): ?>
                                <img src="<?php echo esc_url($cust_url); ?>" alt="" />
                            <?php else: ?>
                                <span class="jprm-icon-ph" title="<?php echo esc_attr__('Select icon','jellopoint-restaurant-menu'); ?>"><span class="dashicons dashicons-format-image"></span></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" class="icon-id" value="<?php echo esc_attr($rid); ?>" data-url="<?php echo esc_attr($cust_url); ?>" />
                        <button type="button" class="btn-link-icon jprm-row-icon-clear" aria-label="<?php echo esc_attr__('Remove Icon','jellopoint-restaurant-menu'); ?>" <?php echo ($lmd==='custom' && $rid) ? '' : 'style="display:none;"'; ?>><span class="dashicons dashicons-no-alt"></span></button>
                    </td>

                    <td style="text-align:center;"><input type="checkbox" class="hide-icon" <?php checked($hide, true); ?> /></td>

                    <td>
                        <button type="button" class="btn-trash jprm-row-remove" aria-label="<?php echo esc_attr__('Remove row','jellopoint-restaurant-menu'); ?>"><span class="dashicons dashicons-trash"></span></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="#" class="button" id="jprm-row-add"><?php echo esc_html__('Add another price','jellopoint-restaurant-menu'); ?></a></p>
        <input type="hidden" id="jprm_prices" name="jprm_prices" value="<?php echo esc_attr( json_encode($rows) ); ?>" />
        <?php
        echo '</td></tr>';

        echo '</tbody></table>';

        // Inline JS (compact + persistent)
        ?>
        <script>
        (function($){
            /* ---------- Single (unchanged behavior + pill switch) ---------- */
            function setModeUI(){
                var mode = $('input[name="jprm_price_mode"]:checked').val() || 'single';
                if (mode === 'single'){ $('.jprm-block-single').show(); $('.jprm-block-multi').hide(); }
                else { $('.jprm-block-single').hide(); $('.jprm-block-multi').show(); }
            }
            function setSingleMode(mode){
                $('#jprm_price_label_mode').val(mode);
                $('#jprm_single_mode_switch .jprm-pill').removeClass('active');
                $('#jprm_single_mode_switch .jprm-pill[data-mode="'+mode+'"]').addClass('active');
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
                    if (id && id !== '0' && url){
                        $('#jprm_single_icon_preview').html('<img src="'+url+'" alt="" />');
                    } else {
                        $('#jprm_single_icon_preview').html('<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>');
                    }
                    $('.jprm-single-icon-clear').toggle(!!id && id !== '0');
                } else {
                    var $opt = $('#jprm_price_label_ref').find('option:selected');
                    var url  = $opt.data('icon') || '';
                    if (url){ $('#jprm_single_icon_preview').html('<img src="'+url+'" alt="" />'); }
                    else { $('#jprm_single_icon_preview').empty(); }
                }
            }
            var singleFrame = null;
            function ensureSingleFrame(){
                if (singleFrame) return singleFrame;
                singleFrame = wp.media({ title:'<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>', multiple:false, library:{type:'image'}, button:{text:'<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>'} });
                singleFrame.on('select', function(){
                    var file = singleFrame.state().get('selection').first(); if (!file) return;
                    var id  = file.get('id');
                    var url = (file.get('sizes') && file.get('sizes').thumbnail && file.get('sizes').thumbnail.url) || file.get('url');
                    $('#jprm_price_label_icon_id').val(String(id)).attr('data-url', url || '');
                    $('#jprm_single_icon_preview').html('<img src="'+(url||'')+'" alt="" />');
                    $('.jprm-single-icon-clear').show();
                });
                return singleFrame;
            }

            /* ---------- Multiple rows ---------- */
            function rowObj($tr){
                return {
                    enabled:     $tr.find('input.enable').is(':checked'),
                    label_mode:  $tr.find('select.label-mode').val() === 'custom' ? 'custom' : 'ref',
                    label_ref:   $tr.find('select.label-ref').val() || '',
                    label_custom:$tr.find('input.label-custom').val() || '',
                    icon_id:     parseInt($tr.find('input.icon-id').val() || '0', 10) || 0,
                    amount:      $tr.find('input.amount').val() || '',
                    hide_icon:   $tr.find('input.hide-icon').is(':checked')
                };
            }
            function collectMulti(){
                var out = [];
                $('#jprm-prices-table tbody tr').each(function(){ out.push(rowObj($(this))); });
                $('#jprm_prices').val( JSON.stringify(out) );
            }
            function setRowMode($tr, mode){
                $tr.find('select.label-mode').val(mode);
                $tr.find('.jprm-pill').removeClass('active');
                $tr.find('.jprm-pill[data-mode="'+mode+'"]').addClass('active');

                if (mode === 'custom'){
                    $tr.find('input.label-custom').show();
                    $tr.find('select.label-ref').hide();
                    $tr.find('.jprm-row-icon-clear').show();
                    // Show custom preview or placeholder
                    var id  = $tr.find('input.icon-id').val();
                    var url = $tr.find('input.icon-id').data('url') || '';
                    if (id && id !== '0' && url){
                        $tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" alt="" />');
                    } else {
                        $tr.find('.jprm-row-icon-preview').html('<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>');
                    }
                } else {
                    $tr.find('input.label-custom').hide();
                    $tr.find('select.label-ref').show();
                    // icon preview from preset (no clear in preset mode)
                    var $opt = $tr.find('select.label-ref option:selected');
                    var url  = $opt.data('icon') || '';
                    if (url){ $tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" alt="" />'); }
                    else { $tr.find('.jprm-row-icon-preview').html(''); }
                    $tr.find('.jprm-row-icon-clear').hide();
                }
                collectMulti();
            }
            function attachRowHandlers($tr){
                // pill clicks
                $tr.on('click', '.jprm-pill', function(){ setRowMode($tr, $(this).data('mode')); });
                // preset change
                $tr.on('change', 'select.label-ref', function(){
                    var url  = $(this).find('option:selected').data('icon') || '';
                    if (url){ $tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" alt="" />'); }
                    else { $tr.find('.jprm-row-icon-preview').empty(); }
                    collectMulti();
                });
                // click preview/placeholder → open media only in custom mode
                $tr.on('click', '.jprm-row-icon-preview', function(e){
                    if ($tr.find('select.label-mode').val() !== 'custom') return;
                    e.preventDefault();
                    activeRow = $tr;
                    ensureRowFrame().open();
                });
                // any input change
                $tr.on('change keyup', 'input,select', function(){ collectMulti(); });
            }

            // Media frame for rows
            var rowFrame = null, activeRow = null;
            function ensureRowFrame(){
                if (rowFrame) return rowFrame;
                rowFrame = wp.media({ title:'<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>', multiple:false, library:{type:'image'}, button:{text:'<?php echo esc_js(__('Select Icon','jellopoint-restaurant-menu')); ?>'} });
                rowFrame.on('select', function(){
                    if (!activeRow) return;
                    var file = rowFrame.state().get('selection').first(); if (!file) return;
                    var id  = file.get('id');
                    var url = (file.get('sizes') && file.get('sizes').thumbnail && file.get('sizes').thumbnail.url) || file.get('url');
                    activeRow.find('input.icon-id').val(String(id)).attr('data-url', url || '');
                    activeRow.find('.jprm-row-icon-preview').html('<img src="'+(url||'')+'" alt="" />');
                    activeRow.find('.jprm-row-icon-clear').show();
                    collectMulti();
                    activeRow = null;
                });
                return rowFrame;
            }

            // Single icon click (only in custom mode)
            $('#jprm_single_icon_preview').on('click', function(e){
                if ($('#jprm_price_label_mode').val() !== 'custom') return;
                e.preventDefault(); ensureSingleFrame().open();
            });

            // Clear icon (single)
            $(document).on('click', '.jprm-single-icon-clear', function(e){
                e.preventDefault();
                $('#jprm_price_label_icon_id').val('0').attr('data-url','');
                $('#jprm_single_icon_preview').html('<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>');
                $(this).hide();
            });

            // Init editor
            $(function(){
                // Single
                $('input[name="jprm_price_mode"]').on('change', setModeUI);
                setModeUI();

                $('#jprm_single_mode_switch .jprm-pill').on('click', function(){
                    setSingleMode($(this).data('mode'));
                });
                $('#jprm_price_label_ref').on('change', refreshSingleIcon);
                setSingleMode($('#jprm_price_label_mode').val()); // initial + icon

                // Multi existing rows
                var $tb = $('#jprm-prices-table tbody');
                $tb.find('tr').each(function(){
                    var $tr = $(this);
                    attachRowHandlers($tr);
                    setRowMode($tr, $tr.find('select.label-mode').val());
                });

                // Clear icon (row)
                $(document).on('click', '.jprm-row-icon-clear', function(e){
                    e.preventDefault();
                    var $tr = $(this).closest('tr');
                    $tr.find('input.icon-id').val('0').attr('data-url','');
                    $tr.find('.jprm-row-icon-preview').html('<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>');
                    $(this).hide();
                    collectMulti();
                });

                // Remove row
                $tb.on('click', '.jprm-row-remove', function(e){
                    e.preventDefault();
                    $(this).closest('tr').remove();
                    collectMulti();
                });

                // Add row
                $('#jprm-row-add').on('click', function(e){
                    e.preventDefault();
                    var $tr = $('<tr/>');
                    $tr.append('<td><input type="checkbox" class="enable" checked /></td>');
                    $tr.append('<td class="label-td">\
                        <select class="label-mode" style="display:none;"><option value="ref">ref</option><option value="custom">custom</option></select>\
                        <div class="jprm-mode-switch">\
                            <span class="jprm-pill active" data-mode="ref"><?php echo esc_js(__('Preset','jellopoint-restaurant-menu')); ?></span>\
                            <span class="jprm-pill" data-mode="custom"><?php echo esc_js(__('Custom','jellopoint-restaurant-menu')); ?></span>\
                        </div>\
                        <span class="inline-field">\
                            <select class="label-ref"><?php echo str_replace(array("\n","\r"), '', $single_options); ?></select>\
                            <input type="text" class="label-custom regular-text" value="" placeholder="<?php echo esc_js(__('Custom label','jellopoint-restaurant-menu')); ?>" style="display:none;" />\
                        </span>');
                    $tr.append('<td><input type="text" class="amount regular-text small" value="" placeholder="€ 7,50" /></td>');
                    $tr.append('<td class="jprm-icon-cell">\
                        <div class="jprm-row-icon-preview" title="<?php echo esc_js(__('Select icon','jellopoint-restaurant-menu')); ?>"><span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span></div>\
                        <input type="hidden" class="icon-id" value="0" data-url="" />\
                        <button type="button" class="btn-link-icon jprm-row-icon-clear" style="display:none;" aria-label="<?php echo esc_js(__('Remove Icon','jellopoint-restaurant-menu')); ?>"><span class="dashicons dashicons-no-alt"></span></button>');
                    $tr.append('<td style="text-align:center;"><input type="checkbox" class="hide-icon" /></td>');
                    $tr.append('<td><button type="button" class="btn-trash jprm-row-remove" aria-label="<?php echo esc_js(__('Remove row','jellopoint-restaurant-menu')); ?>"><span class="dashicons dashicons-trash"></span></button></td>');
                    $('#jprm-prices-table tbody').append($tr);
                    attachRowHandlers($tr);
                    setRowMode($tr, 'ref');
                    collectMulti();
                });

                // Ensure latest state saved on submit (avoids last-change-not-captured)
                $('#post').on('submit', function(){ collectMulti(); });
                collectMulti(); // initial
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

        // Description
        $desc  = isset($_POST['jprm_desc']) ? wp_kses_post($_POST['jprm_desc']) : '';
        update_post_meta($post_id, 'jprm_desc', $desc);

        // Visibility & Badge
        $badge = sanitize_text_field( $_POST['jprm_badge'] ?? '' );
        $vis   = isset($_POST['jprm_visible']) && $_POST['jprm_visible'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, 'jprm_badge', $badge);
        update_post_meta($post_id, 'jprm_visible', $vis);

        // Pricing mode
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
                // clear custom fields when switching to preset
                delete_post_meta($post_id, 'jprm_price_label_custom');
                delete_post_meta($post_id, 'jprm_price_label_icon_id');
            } else {
                $cus  = sanitize_text_field( $_POST['jprm_price_label_custom'] ?? '' );
                $icon = isset($_POST['jprm_price_label_icon_id']) ? intval($_POST['jprm_price_label_icon_id']) : 0;
                update_post_meta($post_id, 'jprm_price_label_custom', $cus );
                update_post_meta($post_id, 'jprm_price_label_icon_id', $icon );
                // clear preset ref when custom
                delete_post_meta($post_id, 'jprm_price_label_ref');
            }

            // If single mode, remove multi data if not present
            if ( ! isset($_POST['jprm_prices']) ) {
                delete_post_meta($post_id, 'jprm_prices');
            }
        } else {
            // Multi mode save
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
                            'icon_id'      => isset($r['icon_id']) ? (int)$r['icon_id'] : 0,
                            'amount'       => sanitize_text_field($r['amount'] ?? ''),
                            'hide_icon'    => !empty($r['hide_icon']),
                        ];
                    }
                    update_post_meta($post_id, 'jprm_prices', wp_json_encode($out));
                }
            }
            // Clean single fields in multi mode
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