<?php
namespace JelloPoint\RestaurantMenu\Admin;

use JelloPoint\RestaurantMenu\Admin\Save\MenuItem_Save_Normalizer;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Admin meta UI for jprm_menu_item: stores unified 'jprm_price' (JSON, v3).
 */
class Admin_MenuItem_Meta {

    const NONCE = 'jprm_menuitem_meta_nonce';
    const NONCE_ACTION = 'jprm_menuitem_meta_save';
    const CPT = 'jprm_menu_item';

    public static function init() : void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( $hook ) : void {
        // Only on post editor screens
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== self::CPT ) return;

        // Tiny inline script for adding/removing rows
        $js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
  const box = document.querySelector('#jprm-price-meta');
  if (!box) return;

  const modeSel = box.querySelector('[name="jprm_mode"]');
  const singleWrap = box.querySelector('[data-jprm="single"]');
  const multiWrap  = box.querySelector('[data-jprm="multi"]');
  const addBtn     = box.querySelector('[data-jprm="add-row"]');
  const tbody      = box.querySelector('[data-jprm="rows"]');

  function toggleMode(){
    const m = modeSel.value;
    singleWrap.style.display = (m === 'single') ? '' : 'none';
    multiWrap.style.display  = (m === 'multi')  ? '' : 'none';
  }
  if (modeSel){ modeSel.addEventListener('change', toggleMode); toggleMode(); }

  if (addBtn && tbody) {
    addBtn.addEventListener('click', function(e){
      e.preventDefault();
      const tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="text" name="jprm_rows_label_ref[]" class="regular-text" placeholder="pl-0 or slug or text" /></td>'
                   + '<td><input type="text" name="jprm_rows_value[]" class="regular-text" placeholder="€0.00" /></td>'
                   + '<td><label><input type="checkbox" name="jprm_rows_hide_icon[]" value="1" /> ' + (box.dataset.hideIconLabel || 'Hide icon') + '</label></td>'
                   + '<td><button type="button" class="button link-button" data-jprm="del-row">&times;</button></td>';
      tbody.appendChild(tr);
    });

    tbody.addEventListener('click', function(e){
      const btn = e.target.closest('[data-jprm="del-row"]');
      if (btn) {
        e.preventDefault();
        const tr = btn.closest('tr');
        if (tr) tr.remove();
      }
    });
  }
});
JS;
        wp_register_script( 'jprm-admin-meta', false, [], false, true );
        wp_enqueue_script( 'jprm-admin-meta' );
        wp_add_inline_script( 'jprm-admin-meta', $js );
    }

    public static function add_meta_box() : void {
        add_meta_box(
            'jprm-price-meta',
            __( 'Menu Item Price (v3)', 'jellopoint-restaurant-menu' ),
            [ __CLASS__, 'render_meta_box' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    protected static function get_existing_cfg( $post_id ) : array {
        $raw = get_post_meta( $post_id, 'jprm_price', true );
        $cfg = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        return is_array($cfg) ? $cfg : [];
    }

    public static function render_meta_box( $post ) : void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE );

        // Include normalizer (and ensure class exists)
        $save_path = plugin_dir_path( __FILE__ ) . 'save/class-menuitem-save-normalizer.php';
        if ( file_exists( $save_path ) ) { require_once $save_path; }

        $cfg = self::get_existing_cfg( $post->ID );
        $mode = $cfg['mode'] ?? 'single';
        $single_price = $cfg['price'] ?? '';
        $single_label = $cfg['label_ref'] ?? '';
        $single_hide  = ! empty( $cfg['hide_icon'] );
        $rows = [];
        if ( isset($cfg['mode']) && $cfg['mode'] === 'multi' && ! empty($cfg['rows']) && is_array($cfg['rows']) ) {
            $rows = $cfg['rows'];
        }

        echo '<div id="jprm-price-meta" class="jprm-meta" data-hide-icon-label="' . esc_attr__( 'Hide icon', 'jellopoint-restaurant-menu' ) . '">';

        echo '<p><label><strong>' . esc_html__( 'Mode', 'jellopoint-restaurant-menu' ) . '</strong></label><br/>';
        echo '<select name="jprm_mode">';
        echo '<option value="single"' . selected( $mode, 'single', false ) . '>' . esc_html__('Single','jellopoint-restaurant-menu') . '</option>';
        echo '<option value="multi"'  . selected( $mode, 'multi',  false ) . '>' . esc_html__('Multiple','jellopoint-restaurant-menu') . '</option>';
        echo '</select></p>';

        // Single
        echo '<div data-jprm="single" style="margin:10px 0;">';
        echo '<p><label>' . esc_html__('Single Price','jellopoint-restaurant-menu') . '<br/>';
        echo '<input type="text" class="regular-text" name="jprm_single_price" value="' . esc_attr( $single_price ) . '" placeholder="€0.00" /></label></p>';

        echo '<p><label>' . esc_html__('Label Ref (id/slug/text)','jellopoint-restaurant-menu') . '<br/>';
        echo '<input type="text" class="regular-text" name="jprm_single_label_ref" value="' . esc_attr( $single_label ) . '" placeholder="pl-3 or slug or text" /></label></p>';

        echo '<p><label><input type="checkbox" name="jprm_single_hide_icon" value="1" ' . checked( $single_hide, true, false ) . ' /> ' . esc_html__('Hide icon','jellopoint-restaurant-menu') . '</label></p>';
        echo '</div>';

        // Multi
        echo '<div data-jprm="multi" style="margin:10px 0;">';
        echo '<p><button type="button" class="button" data-jprm="add-row">' . esc_html__('Add Row', 'jellopoint-restaurant-menu') . '</button></p>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Label Ref (id/slug/text)','jellopoint-restaurant-menu') . '</th>';
        echo '<th>' . esc_html__('Price Value','jellopoint-restaurant-menu') . '</th>';
        echo '<th>' . esc_html__('Hide Icon?','jellopoint-restaurant-menu') . '</th>';
        echo '<th></th>';
        echo '</tr></thead><tbody data-jprm="rows">';
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $r ) {
                $lr = isset($r['label_ref']) ? (string)$r['label_ref'] : '';
                $vv = isset($r['value']) ? (string)$r['value'] : '';
                $hi = ! empty($r['hide_icon']);
                echo '<tr>';
                echo '<td><input type="text" name="jprm_rows_label_ref[]" class="regular-text" value="' . esc_attr($lr) . '" /></td>';
                echo '<td><input type="text" name="jprm_rows_value[]" class="regular-text" value="' . esc_attr($vv) . '" /></td>';
                echo '<td><label><input type="checkbox" name="jprm_rows_hide_icon[]" value="1" ' . checked($hi, true, false) . ' /> ' . esc_html__('Hide icon', 'jellopoint-restaurant-menu') . '</label></td>';
                echo '<td><button type="button" class="button link-button" data-jprm="del-row">&times;</button></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';
    }

    public static function save( $post_id, $post ) : void {
        // Nonce & capability
        if ( ! isset($_POST[self::NONCE]) || ! wp_verify_nonce( $_POST[self::NONCE], self::NONCE_ACTION ) ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Include normalizer at save time
        $save_path = plugin_dir_path( __FILE__ ) . 'save/class-menuitem-save-normalizer.php';
        if ( file_exists( $save_path ) ) { require_once $save_path; }

        $data = MenuItem_Save_Normalizer::from_post( $_POST );
        if ( empty( $data ) ) {
            delete_post_meta( $post_id, 'jprm_price' );
            return;
        }

        update_post_meta( $post_id, 'jprm_price', wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );

        // No legacy keys left around (no backward compatibility required).
    }
}
Admin_MenuItem_Meta::init();