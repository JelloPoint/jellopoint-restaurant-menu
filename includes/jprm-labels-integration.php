
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
    <style>
      .jprm-mode-row { margin: 10px 0; padding: 8px 10px; background:#fafafa; border:1px solid #e3e3e3; display:inline-block; border-radius:4px; }
      .jprm-icon-preview{ display:inline-block; width:48px; min-height:24px; margin-right:8px; vertical-align:middle; }
      .jprm-label-copy { margin-left:6px; }
    </style>
    <script>
    (function($){
      var JPRM_LABELS = <?php echo wp_json_encode( $labels ); ?> || [];
      var CURRENT_TEXT = <?php echo wp_json_encode( (string) $selected ); ?>;
      var CURRENT_REF  = <?php echo wp_json_encode( (string) $ref_id ); ?>;

      // ---------- Helpers ----------
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
            if (JPRM_LABELS[i].name === val){ ref = String(JPRM_LABELS[i].id||''); break; }
          }
        }
        ensureHiddenRef($sel).val(ref);
        var isCustom = (val === 'custom');
        $('#jprm_price_label_custom')[isCustom ? 'show' : 'hide']();
      }
      function buildMainOptions(){
        var opts = [];
        opts.push({value:'', text:'Select…'});
        for (var i=0; i<JPRM_LABELS.length; i++){ opts.push({value:JPRM_LABELS[i].name, text:JPRM_LABELS[i].name}); }
        opts.push({value:'custom', text:'Custom'});
        return opts;
      }
      function buildRowOptions(){
        // For multiple prices rows
        return buildMainOptions();
      }
      function rebuildMainSelect(){
        var $sel = $('#jprm_price_label');
        if (!$sel.length) return;
        var current = CURRENT_TEXT || $sel.val() || '';
        var options = buildMainOptions();
        $sel.empty();
        options.forEach(function(o){
          var $opt = $('<option>').attr('value', o.value).text(o.text);
          $sel.append($opt);
        });
        if (current){ $sel.val(current); }
        updateHiddenRefFromSelection($sel);
      }
      function syncRow($tr){
        var isCustom = $tr.find('select.label-select').val() === 'custom';
        $tr.find('input.label-custom')[isCustom ? 'show' : 'hide']();
      }
      function rebuildRowSelects($scope){
        var $tbody = ($scope && $scope.length) ? $scope : $('#jprm-prices-table tbody');
        if (!$tbody.length) return;
        var options = buildRowOptions();
        $tbody.find('select.label-select').each(function(){
          var $sel = $(this);
          var prev = $sel.val();
          $sel.empty();
          options.forEach(function(o){
            var $opt = $('<option>').attr('value', o.value).text(o.text);
            $sel.append($opt);
          });
          if (prev){ $sel.val(prev); }
          syncRow($sel.closest('tr'));
        });
      }

      // ---------- Mode toggle (Single vs Multiple) ----------
      function ensureModeToggle(){
        var $chk = $('#jprm_multi'); // existing checkbox
        var $row = $chk.closest('tr');
        if (! $row.length) return;

        if ($('.jprm-mode-row').length) return; // already inserted

        var $mode = $('<div class="jprm-mode-row">'+
                      '<label><input type="radio" name="jprm_price_mode" value="single"> Single Price</label> &nbsp; '+
                      '<label><input type="radio" name="jprm_price_mode" value="multi"> Multiple Prices</label>'+
                      '</div>');

        // Insert above the checkbox row
        $row.before( $('<tr><th>Price Mode</th><td></td></tr>') );
        $row.prev().find('td').append($mode);

        function apply(mode){
          var isMulti = (mode === 'multi');
          // keep checkbox in sync for back-compat
          $chk.prop('checked', isMulti).trigger('change');

          // Single fields
          var $rowPrice  = $('#jprm_price').closest('tr');
          var $rowLabel  = $('#jprm_price_label').closest('tr');
          var $rowBadge  = $('#jprm_badge').closest('tr');
          // Multiple block
          var $multiBlk  = $('#jprm-multi-admin');

          if (isMulti){
            $multiBlk.show();
            $rowPrice.hide();
            $rowLabel.show(); // keep label visible? if you want it hidden in multi, set hide()
          } else {
            $multiBlk.hide();
            $rowPrice.show();
            $rowLabel.show();
          }
        }

        // Initial mode from checkbox
        apply( $chk.is(':checked') ? 'multi' : 'single' );

        // Bind radios
        $mode.find('input[type=radio]').on('change', function(){
          apply( this.value );
        });

        // Keep in sync if user toggles the legacy checkbox
        $chk.on('change', function(){
          $mode.find('input[value="'+( $chk.is(':checked') ? 'multi' : 'single')+'"]').prop('checked', true);
          apply( $chk.is(':checked') ? 'multi' : 'single' );
        });

        // Set initial checked radio
        $mode.find('input[value="'+( $chk.is(':checked') ? 'multi' : 'single')+'"]').prop('checked', true);
      }

      // ---------- Boot ----------
      function boot(){
        rebuildMainSelect();       // single price select
        ensureModeToggle();        // radio Single/Multiple + sync with checkbox
        rebuildRowSelects();       // multiple prices row label selects
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
