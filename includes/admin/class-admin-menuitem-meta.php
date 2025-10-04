<?php
/**
 * Admin editor for jprm_menu_item — clean v3 schema only.
 * - Description: jprm_desc (string)
 * - Price JSON: jprm_price {"_schema":"3.0","mode":"single|multi", ...}
 */
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined('ABSPATH') ) { exit; }

class MenuItem_Meta {

    const NONCE = 'jprm_menuitem_meta_nonce';

    public static function init() : void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_jprm_menu_item', [ __CLASS__, 'save_post' ] );
    }

    public static function add_meta_boxes() : void {
        add_meta_box(
            'jprm_menuitem_details',
            __( 'Menu Item Details', 'jellopoint-restaurant-menu' ),
            [ __CLASS__, 'render_box' ],
            'jprm_menu_item',
            'normal',
            'high'
        );
    }

    public static function render_box( \WP_Post $post ) : void {
        wp_nonce_field( __CLASS__, self::NONCE );

        $desc = get_post_meta( $post->ID, 'jprm_desc', true );
        $desc = is_string($desc) ? $desc : '';

        $cfg_raw = get_post_meta( $post->ID, 'jprm_price', true );
        $cfg     = is_string($cfg_raw) && $cfg_raw !== '' ? json_decode($cfg_raw, true) : [];
        if ( ! is_array($cfg) ) $cfg = [];

        $mode    = isset($cfg['mode']) ? (string)$cfg['mode'] : 'single';
        $single_price     = $mode === 'single' ? (string)($cfg['price'] ?? '') : '';
        $single_label_ref = $mode === 'single' ? (string)($cfg['label_ref'] ?? '') : '';
        $single_hide_icon = $mode === 'single' ? (bool)($cfg['hide_icon'] ?? false) : false;
        $single_icon_id   = $mode === 'single' ? (int)($cfg['icon_id'] ?? 0) : 0;

        $rows = ($mode === 'multi' && ! empty($cfg['rows']) && is_array($cfg['rows'])) ? $cfg['rows'] : [];

        ?>
        <style>
            .jprm-field{margin:12px 0;}
            .jprm-grid{display:grid;grid-template-columns:1fr 2fr;gap:8px 12px;align-items:center;}
            .jprm-rows{margin-top:10px;}
            .jprm-rows table{width:100%;border-collapse:collapse}
            .jprm-rows th,.jprm-rows td{border:1px solid #ccc;padding:6px 8px;text-align:left}
            .jprm-rows input[type="text"]{width:100%}
            .jprm-rows .button{white-space:nowrap}
        </style>

        <div class="jprm-grid">
            <label for="jprm_desc"><strong><?php esc_html_e('Description', 'jellopoint-restaurant-menu'); ?></strong></label>
            <textarea id="jprm_desc" name="jprm_desc" rows="3" class="widefat"><?php echo esc_textarea($desc); ?></textarea>

            <label><strong><?php esc_html_e('Price Mode', 'jellopoint-restaurant-menu'); ?></strong></label>
            <div>
                <label><input type="radio" name="jprm_mode" value="single" <?php checked($mode==='single'); ?> /> <?php esc_html_e('Single', 'jellopoint-restaurant-menu'); ?></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="jprm_mode" value="multi"  <?php checked($mode==='multi'); ?>  /> <?php esc_html_e('Multiple', 'jellopoint-restaurant-menu'); ?></label>
            </div>

            <div class="jprm-field" style="<?php echo $mode==='single'?'':'display:none'; ?>" id="jprm_single_wrap">
                <div class="jprm-grid">
                    <label for="jprm_single_price"><?php esc_html_e('Single Price', 'jellopoint-restaurant-menu'); ?></label>
                    <input type="text" id="jprm_single_price" name="jprm_single_price" value="<?php echo esc_attr($single_price); ?>" />

                    <label for="jprm_single_label_ref"><?php esc_html_e('Label Ref (id/slug/custom text)', 'jellopoint-restaurant-menu'); ?></label>
                    <input type="text" id="jprm_single_label_ref" name="jprm_single_label_ref" value="<?php echo esc_attr($single_label_ref); ?>" />

                    <label for="jprm_single_icon_id"><?php esc_html_e('Icon ID (optional)', 'jellopoint-restaurant-menu'); ?></label>
                    <input type="number" id="jprm_single_icon_id" name="jprm_single_icon_id" value="<?php echo (int)$single_icon_id; ?>" min="0" step="1" />

                    <label for="jprm_single_hide_icon"><?php esc_html_e('Hide Icon', 'jellopoint-restaurant-menu'); ?></label>
                    <input type="checkbox" id="jprm_single_hide_icon" name="jprm_single_hide_icon" value="1" <?php checked($single_hide_icon); ?> />
                </div>
            </div>

            <div class="jprm-rows" style="<?php echo $mode==='multi'?'':'display:none'; ?>" id="jprm_multi_wrap">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Label Ref (id/slug/custom text)', 'jellopoint-restaurant-menu'); ?></th>
                            <th><?php esc_html_e('Price (value)', 'jellopoint-restaurant-menu'); ?></th>
                            <th><?php esc_html_e('Icon ID', 'jellopoint-restaurant-menu'); ?></th>
                            <th><?php esc_html_e('Hide Icon', 'jellopoint-restaurant-menu'); ?></th>
                            <th><?php esc_html_e('Actions', 'jellopoint-restaurant-menu'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="jprm_rows_body">
                        <?php
                        if (empty($rows)) {
                            $rows = [['label_ref'=>'','value'=>'','icon_id'=>0,'hide_icon'=>false]];
                        }
                        foreach ($rows as $idx => $r) :
                            $lr = isset($r['label_ref']) ? (string)$r['label_ref'] : '';
                            $val= isset($r['value']) ? (string)$r['value'] : '';
                            $ico= isset($r['icon_id']) ? (int)$r['icon_id'] : 0;
                            $hid= !empty($r['hide_icon']);
                        ?>
                        <tr>
                            <td><input type="text" name="jprm_rows[<?php echo (int)$idx; ?>][label_ref]" value="<?php echo esc_attr($lr); ?>" /></td>
                            <td><input type="text" name="jprm_rows[<?php echo (int)$idx; ?>][value]"      value="<?php echo esc_attr($val); ?>" /></td>
                            <td><input type="number" name="jprm_rows[<?php echo (int)$idx; ?>][icon_id]"   value="<?php echo (int)$ico; ?>" min="0" step="1" /></td>
                            <td><input type="checkbox" name="jprm_rows[<?php echo (int)$idx; ?>][hide_icon]" value="1" <?php checked($hid); ?> /></td>
                            <td><button type="button" class="button jprm-del-row"><?php esc_html_e('Delete', 'jellopoint-restaurant-menu'); ?></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button button-secondary" id="jprm_add_row"><?php esc_html_e('Add Row', 'jellopoint-restaurant-menu'); ?></button></p>
            </div>
        </div>

        <script>
        (function(){
            const modeRadios = document.querySelectorAll('input[name="jprm_mode"]');
            const singleWrap = document.getElementById('jprm_single_wrap');
            const multiWrap  = document.getElementById('jprm_multi_wrap');
            modeRadios.forEach(r => r.addEventListener('change', () => {
                if (r.checked && r.value === 'single') { singleWrap.style.display=''; multiWrap.style.display='none'; }
                if (r.checked && r.value === 'multi')  { singleWrap.style.display='none'; multiWrap.style.display=''; }
            }));

            const body = document.getElementById('jprm_rows_body');
            document.getElementById('jprm_add_row').addEventListener('click', () => {
                const idx = body.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" name="jprm_rows['+idx+'][label_ref]" value="" /></td>'+
                    '<td><input type="text" name="jprm_rows['+idx+'][value]" value="" /></td>'+
                    '<td><input type="number" name="jprm_rows['+idx+'][icon_id]" value="0" min="0" step="1" /></td>'+
                    '<td><input type="checkbox" name="jprm_rows['+idx+'][hide_icon]" value="1" /></td>'+
                    '<td><button type="button" class="button jprm-del-row"><?php echo esc_js(__('Delete','jellopoint-restaurant-menu')); ?></button></td>';
                body.appendChild(tr);
            });
            body.addEventListener('click', (e) => {
                if (e.target && e.target.classList.contains('jprm-del-row')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }

    public static function save_post( int $post_id ) : void {
        if ( ! isset($_POST[self::NONCE]) || ! wp_verify_nonce( $_POST[self::NONCE], __CLASS__ ) ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Description
        $desc = isset($_POST['jprm_desc']) ? wp_kses_post( wp_unslash($_POST['jprm_desc']) ) : '';
        update_post_meta( $post_id, 'jprm_desc', $desc );

        // Price config
        $mode = isset($_POST['jprm_mode']) && $_POST['jprm_mode']==='multi' ? 'multi' : 'single';
        $cfg  = [ '_schema' => '3.0', 'mode' => $mode ];

        if ( $mode === 'single' ) {
            $price     = isset($_POST['jprm_single_price']) ? sanitize_text_field( wp_unslash($_POST['jprm_single_price']) ) : '';
            $label_ref = isset($_POST['jprm_single_label_ref']) ? sanitize_text_field( wp_unslash($_POST['jprm_single_label_ref']) ) : '';
            $hide_icon = ! empty($_POST['jprm_single_hide_icon']);
            $icon_id   = isset($_POST['jprm_single_icon_id']) ? (int) $_POST['jprm_single_icon_id'] : 0;

            $cfg['price']     = $price;
            $cfg['label_ref'] = $label_ref;
            $cfg['hide_icon'] = $hide_icon;
            if ( $icon_id > 0 ) $cfg['icon_id'] = $icon_id;
        } else {
            $rows_in = isset($_POST['jprm_rows']) && is_array($_POST['jprm_rows']) ? $_POST['jprm_rows'] : [];
            $rows = [];
            foreach ( $rows_in as $r ) {
                $value = isset($r['value']) ? sanitize_text_field( wp_unslash($r['value']) ) : '';
                if ( $value === '' ) continue;
                $label_ref = isset($r['label_ref']) ? sanitize_text_field( wp_unslash($r['label_ref']) ) : '';
                $icon_id   = isset($r['icon_id']) ? (int) $r['icon_id'] : 0;
                $hide_icon = ! empty( $r['hide_icon'] );
                $row = [
                    'label_ref' => $label_ref,
                    'value'     => $value,
                    'hide_icon' => $hide_icon,
                ];
                if ( $icon_id > 0 ) $row['icon_id'] = $icon_id;
                $rows[] = $row;
            }
            $cfg['rows'] = array_values($rows);
        }

        update_post_meta( $post_id, 'jprm_price', wp_json_encode( $cfg ) );
    }
}

MenuItem_Meta::init();