
<?php
/**
 * JPRM Labels ↔ Menu Items integration (admin-only, safe, additive).
 * Place this file at: wp-content/plugins/jellopoint-restaurant-menu/includes/jprm-labels-integration.php
 * Then add ONE line in your main plugin file:
 *   require_once JPRM_PLUGIN_PATH . 'includes/jprm-labels-integration.php';
 */

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Normalize one label row to a common shape.
 */
if ( ! function_exists('jprm_li_norm') ) {
    function jprm_li_norm( $id, $name, $icon_id = 0, $icon_url = '' ) : array {
        $iid = intval($icon_id);
        $url = $icon_url;
        if ( ! $url && $iid ) {
            $url = wp_get_attachment_image_url( $iid, 'thumbnail' ) ?: '';
        }
        return [
            'id'       => (string) ( $id !== '' ? $id : sanitize_key($name) ),
            'name'     => (string) $name,
            'slug'     => sanitize_key( $name ),
            'icon_id'  => $iid,
            'icon_url' => $url ? esc_url_raw($url) : '',
        ];
    }
}

/**
 * Fetch predefined price labels as a normalized list.
 * Tries, in order:
 *  1) An existing helper jprm_get_price_labels() if your plugin already defines it.
 *  2) v2 option (JSON): 'jprm_price_labels_v2' (array of rows with id,label,slug,icon_id,active,order).
 *  3) v1 option(s): 'jprm_price_labels', etc.
 *  4) CPT fallback: post_type = jprm_price_label
 */
if ( ! function_exists('jprm_li_get_price_labels') ) {
    function jprm_li_get_price_labels() : array {
        // Case 1: Canonical getter present?
        if ( function_exists('jprm_get_price_labels') ) {
            $list = jprm_get_price_labels();
            $out  = [];
            foreach ( (array) $list as $it ) {
                $id   = is_array($it) ? ($it['id'] ?? ($it['slug'] ?? null)) : ( is_object($it) ? ($it->id ?? null) : null );
                $name = is_array($it) ? ($it['name'] ?? ($it['label'] ?? ''))     : ( is_object($it) ? ($it->name ?? ($it->label ?? '')) : '' );
                $iid  = is_array($it) ? intval($it['icon_id'] ?? 0)               : ( is_object($it) ? intval($it->icon_id ?? 0) : 0 );
                $url  = is_array($it) ? ($it['icon_url'] ?? '')                    : ( is_object($it) ? ($it->icon_url ?? '') : '' );
                if ( $name !== '' ) { $out[] = jprm_li_norm( $id ?? '', $name, $iid, $url ); }
            }
            return $out;
        }

        // Case 2: v2 option (JSON-encoded array)
        $v2_raw = get_option('jprm_price_labels_v2', '');
        if ( is_string($v2_raw) && $v2_raw !== '' ) {
            $decoded = json_decode( $v2_raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($decoded) ) {
                $out = [];
                foreach ( $decoded as $row ) {
                    if ( ! is_array($row) ) continue;
                    $active = array_key_exists('active', $row) ? (bool)$row['active'] : true;
                    if ( ! $active ) continue;
                    $id   = (string)($row['id']   ?? '');
                    $name = (string)($row['label']?? '');
                    if ( $name === '' ) continue;
                    $iid  = intval($row['icon_id'] ?? 0);
                    $out[] = jprm_li_norm( $id !== '' ? $id : sanitize_key($name), $name, $iid, '' );
                }
                if ( ! empty($out) ) { return $out; }
            }
        }

        // Case 3: Option-based storage (older keys)
        $option_keys = [
            'jprm_price_labels',
            'jprm_price_labels_v1',
            'jprm_labels',
            'jprm_menu_labels',
            'jellopoint_price_labels',
        ];
        foreach ( $option_keys as $ok ) {
            $stored = get_option( $ok );
            if ( is_array($stored) && ! empty($stored) ) {
                $out = [];
                foreach ( $stored as $idx => $row ) {
                    if ( is_string($row) ) {
                        $name = $row;
                        $id   = $idx;
                        $out[] = jprm_li_norm( $id, $name, 0, '' );
                    } elseif ( is_array($row) ) {
                        $name = $row['name'] ?? ($row['label'] ?? '');
                        if ( $name === '' ) continue;
                        $id   = $row['id'] ?? $idx;
                        $iid  = intval($row['icon_id'] ?? 0);
                        $url  = $row['icon_url'] ?? '';
                        $out[] = jprm_li_norm( $id, $name, $iid, $url );
                    }
                }
                if ( ! empty($out) ) { return $out; }
            }
        }

        // Case 4: CPT fallback
        $posts = get_posts([
            'post_type'      => 'jprm_price_label',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'suppress_filters' => false,
            'no_found_rows'  => true,
        ]);
        if ( $posts ) {
            $out = [];
            foreach ( $posts as $p ) {
                $name = $p->post_title;
                $iid  = intval( get_post_meta($p->ID, 'icon_id', true ) );
                $out[] = jprm_li_norm( $p->ID, $name, $iid, '' );
            }
            return $out;
        }

        return [];
    }
}

/**
 * Admin-only: augment Menu Item edit screen selector with predefined labels.
 * - Does not remove your existing fields; only enhances them on the client side.
 * - Adds a hidden 'jprm_price_label_ref' to capture a stable id for the chosen label.
 * - Keeps the select's posted value as the human-readable name (back-compat).
 */
add_action('admin_enqueue_scripts', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) return;
    wp_enqueue_script('jquery');
});

add_action('admin_footer', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) return;

    global $post;
    if ( ! $post || $post->post_type !== 'jprm_menu_item' ) return;

    $labels   = jprm_li_get_price_labels();
    $selected = get_post_meta( $post->ID, 'jprm_price_label', true );
    $ref_id   = get_post_meta( $post->ID, 'jprm_price_label_ref', true );

    ?>
    <script>
    (function($){
      var JPRM_LABELS = <?php echo wp_json_encode( $labels ); ?> || [];
      var CURRENT_TEXT = <?php echo wp_json_encode( (string) $selected ); ?>;
      var CURRENT_REF  = <?php echo wp_json_encode( (string) $ref_id ); ?>;

      function buildOptions(){
        var opts = [];
        opts.push({value:'', text:'Select…', id:''});
        for (var i=0; i<JPRM_LABELS.length; i++){
          var L = JPRM_LABELS[i];
          opts.push({value: L.name, text: L.name, id: String(L.id||'')});
        }
        opts.push({value:'custom', text:'Custom', id:''});
        return opts;
      }

      function ensureHiddenRef($sel){
        var $hidden = $('#jprm_price_label_ref');
        if (!$hidden.length){
          $hidden = $('<input type="hidden" id="jprm_price_label_ref" name="jprm_price_label_ref" value="">');
          $sel.after($hidden);
        }
        return $hidden;
      }

      function updateHiddenRefFromSelection($sel){
        var val = $sel.val();
        var ref = '';
        if (val && val !== 'custom'){
          for (var i=0;i<JPRM_LABELS.length;i++){
            if (JPRM_LABELS[i].name === val){
              ref = String(JPRM_LABELS[i].id||'');
              break;
            }
          }
        }
        ensureHiddenRef($sel).val(ref);
        var isCustom = (val === 'custom');
        $('#jprm_price_label_custom')[isCustom ? 'show' : 'hide']();
      }

      function rebuildSelect(){
        var $sel = $('#jprm_price_label');
        if (!$sel.length) return;

        var current = CURRENT_TEXT || $sel.val() || '';
        var options = buildOptions();
        $sel.empty();
        for (var i=0;i<options.length;i++){
          var o = options[i];
          var $opt = $('<option>').attr('value', o.value).text(o.text);
          if (o.id) $opt.attr('data-id', o.id);
          $sel.append($opt);
        }

        if (current){
          $sel.val(current);
        } else if (CURRENT_REF){
          for (var i=0;i<JPRM_LABELS.length;i++){
            if (String(JPRM_LABELS[i].id||'') === String(CURRENT_REF)){
              $sel.val(JPRM_LABELS[i].name);
              break;
            }
          }
        }
        updateHiddenRefFromSelection($sel);

        if (JPRM_LABELS.length === 0){
          console.warn('JPRM: No Price Labels found in jprm_price_labels_v2 (or other fallbacks).');
        }
      }

      function bind(){
        var $sel = $('#jprm_price_label');
        if (!$sel.length) return;
        $sel.off('change.jprmLab').on('change.jprmLab', function(){
          updateHiddenRefFromSelection($sel);
        });
      }

      function boot(){
        rebuildSelect();
        bind();
      }

      $(boot);
    })(jQuery);
    </script>
    <?php
}, 20);

/**
 * Save the numeric label reference alongside the existing text value.
 */
add_action('save_post_jprm_menu_item', function($post_id, $post){
    if ( ! isset($_POST['jprm_meta_nonce']) || ! wp_verify_nonce( $_POST['jprm_meta_nonce'], 'jprm_meta' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $ref = isset($_POST['jprm_price_label_ref']) ? sanitize_text_field($_POST['jprm_price_label_ref']) : '';
    if ( $ref !== '' ) {
        update_post_meta( $post_id, 'jprm_price_label_ref', $ref );
    } else {
        delete_post_meta( $post_id, 'jprm_price_label_ref' );
    }
}, 10, 2);
