
<?php
/**
 * JPRM Labels ↔ Menu Items integration (admin-only, safe, additive).
 * Place this file at: wp-content/plugins/jellopoint-restaurant-menu/includes/jprm-labels-integration.php
 * Then add ONE line in your main plugin file:
 *   require_once __DIR__ . '/includes/jprm-labels-integration.php';
 *
 * This does NOT modify admin menus or existing screens. It only augments the
 * Menu Item edit screen to use the predefined Price Labels list and stores a
 * numeric reference alongside the existing text value for backward compatibility.
 */

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Fetch predefined price labels as a normalized list.
 * Tries, in order:
 *  1) An existing helper jprm_get_price_labels() if your plugin already defines it.
 *  2) The stored option 'jprm_price_labels' used by the labels admin page.
 *  3) Fallback to empty array.
 *
 * Each item is returned as:
 *  [ 'id' => int|string, 'name' => string, 'slug' => string, 'icon_id' => int, 'icon_url' => string ]
 */
if ( ! function_exists('jprm_li_get_price_labels') ) {
    function jprm_li_get_price_labels() : array {
        // Case 1: If the plugin already has a canonical getter, use it.
        if ( function_exists('jprm_get_price_labels') ) {
            $list = jprm_get_price_labels();
            $out = [];
            foreach ( $list as $it ) {
                // Try to normalize common shapes
                $id  = isset($it['id']) ? $it['id'] : ( isset($it->id) ? $it->id : ( isset($it['slug']) ? $it['slug'] : null ) );
                $nm  = isset($it['name']) ? $it['name'] : ( isset($it->name) ? $it->name : ( isset($it['label']) ? $it['label'] : '' ) );
                $iid = isset($it['icon_id']) ? intval($it['icon_id']) : ( isset($it->icon_id) ? intval($it->icon_id) : 0 );
                $url = '';
                if ( $iid ) {
                    $url = wp_get_attachment_image_url( $iid, 'thumbnail' ) ?: '';
                } elseif ( isset($it['icon_url']) && $it['icon_url'] ) {
                    $url = esc_url_raw($it['icon_url']);
                }
                if ($id === null) { $id = sanitize_key($nm); }
                $out[] = [
                    'id'       => $id,
                    'name'     => $nm,
                    'slug'     => sanitize_key( $nm ),
                    'icon_id'  => $iid,
                    'icon_url' => $url,
                ];
            }
            return $out;
        }

        // Case 2: Option-based storage used by the Labels admin page
        $stored = get_option('jprm_price_labels');
        $out = [];
        if ( is_array($stored) ) {
            foreach ( $stored as $idx => $row ) {
                $nm  = isset($row['name']) ? $row['name'] : ( isset($row['label']) ? $row['label'] : '' );
                if ( $nm === '' ) { continue; }
                $iid = isset($row['icon_id']) ? intval($row['icon_id']) : 0;
                $url = $iid ? ( wp_get_attachment_image_url($iid, 'thumbnail') ?: '' ) : '';
                $id  = isset($row['id']) ? $row['id'] : $idx; // keep stable index as id if none provided
                $out[] = [
                    'id'       => $id,
                    'name'     => $nm,
                    'slug'     => sanitize_key( $nm ),
                    'icon_id'  => $iid,
                    'icon_url' => $url,
                ];
            }
        }
        return $out;
    }
}

/**
 * Admin-only: augment Menu Item edit screen selector with predefined labels.
 * - Does not remove the existing fields; only enhances them on the client side.
 * - Adds a hidden 'jprm_price_label_ref' to capture a stable id for the chosen label.
 * - Keeps the select's posted value as the human-readable name (back-compat).
 */
add_action('admin_enqueue_scripts', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) return;

    // Script deps for inline boot
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
      // Provide labels from PHP
      var JPRM_LABELS = <?php echo wp_json_encode( $labels ); ?> || [];
      var CURRENT_TEXT = <?php echo wp_json_encode( (string) $selected ); ?>;
      var CURRENT_REF  = <?php echo wp_json_encode( (string) $ref_id ); ?>;

      function buildOptions(){
        var opts = [];
        // Keep "Select…" placeholder if present
        opts.push({value:'', text:'Select…', id:''});
        for (var i=0; i<JPRM_LABELS.length; i++){
          var L = JPRM_LABELS[i];
          // value is human-readable name for back-compat;
          // attach data-id for numeric reference
          opts.push({value: L.name, text: L.name, id: String(L.id||'')});
        }
        // Custom
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
          // Find matching label by name
          for (var i=0;i<JPRM_LABELS.length;i++){
            if (JPRM_LABELS[i].name === val){
              ref = String(JPRM_LABELS[i].id||'');
              break;
            }
          }
        }
        ensureHiddenRef($sel).val(ref);
        // Toggle custom field
        var isCustom = (val === 'custom');
        $('#jprm_price_label_custom')[isCustom ? 'show' : 'hide']();
      }

      function rebuildSelect(){
        var $sel = $('#jprm_price_label');
        if (!$sel.length) return;

        // Remember current value from DOM if PHP didn't give us one
        var current = CURRENT_TEXT || $sel.val() || '';

        var options = buildOptions();
        $sel.empty();
        options.forEach(function(o){
          var $opt = $('<option>').attr('value', o.value).text(o.text);
          if (o.id) $opt.attr('data-id', o.id);
          $sel.append($opt);
        });

        // Try to match current selection: prefer name, fallback by ref id
        if (current){
          $sel.val(current);
        } else if (CURRENT_REF){
          // locate name by id
          for (var i=0;i<JPRM_LABELS.length;i++){
            if (String(JPRM_LABELS[i].id||'') === String(CURRENT_REF)){
              $sel.val(JPRM_LABELS[i].name);
              break;
            }
          }
        }
        // If still empty and there are labels, keep placeholder selected
        updateHiddenRefFromSelection($sel);
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
 * Does NOT change how the plugin currently saves/uses jprm_price_label.
 * We store our stable id in 'jprm_price_label_ref' for future front-end integration.
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

    // Show/hide of custom field is handled in JS; server keeps back-compat behaviour.
}, 10, 2);
