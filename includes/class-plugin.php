<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {
    private $current_shortcode = [];

    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', [ $this, 'output_alignment_css' ] );

        add_action( 'plugins_loaded', [ $this, 'i18n' ] );
        add_action( 'init', [ $this, 'register_content_model' ] );

        // Admin UI
        add_action( 'admin_menu', [ $this, 'ensure_admin_menu' ] );
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
        add_filter( 'admin_footer_text', [ $this, 'admin_footer' ] );

        // Editor experience
        add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_block_editor' ], 10, 2 );
        if ( method_exists( $this, 'cleanup_metaboxes' ) ) { add_action( 'admin_init', [ $this, 'cleanup_metaboxes' ] ); }

        
        add_action( 'admin_enqueue_scripts', function( $hook ) {
            global $typenow;
            if ( $typenow !== 'jprm_menu_item' ) { return; }
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-sortable' );

            $js = <<<'JS'
(function($){
$(function(){
  var $box = $('#jprm-multi-admin');
  var $tb = $('#jprm-prices-table');
  function updateVisibility(){
    var on = $('input[name=\"jprm_multi\"]').is(':checked');
    $box.toggle(on);
  }
  function serialize(){
    var out=[];
    $tb.find('tbody tr').each(function(i){
      var $r=$(this);
      if($r.hasClass('jp-hidden')) return;
      out.push({ enable: $r.find('input.enable').is(':checked')?1:0, label_select: $r.find('select.label-select').val()||'', label_custom: $r.find('.label-custom').val()||'', amount: $r.find('.amount').val()||'', hide_icon: $r.find('.hide-icon').is(':checked')?1:0, order: i });
    });
    $('#jprm_prices_v1').val(JSON.stringify(out));
  }
  function sequentialReveal(){
    var $rows = $tb.find('tbody tr');
    $rows.removeClass('jp-hidden');
    for(var i=1;i<$rows.length;i++){
      var prevEn = $rows.eq(i-1).find('input.enable').is(':checked');
      if(!prevEn){ for(var j=i;j<$rows.length;j++){ $rows.eq(j).addClass('jp-hidden'); } break; }
    }
  }
  function toggleCustomLabel($row){
    var sel = $row.find('select.label-select').val();
    $row.find('.label-custom').closest('td').toggle(sel==='custom');
  }
  function refresh(){
    $tb.find('tbody tr').each(function(){ toggleCustomLabel($(this)); });
    sequentialReveal();
    serialize();
    updateVisibility();
  }
  $tb.on('change input','input,select', function(){ refresh(); });
  $tb.find('tbody').sortable({ handle: '.sort-handle', update: refresh });
  $('#jprm-add-price').on('click', function(e){ e.preventDefault(); var $last=$tb.find('tbody tr:last'); var $new=$last.clone(); $new.removeClass('jp-hidden'); $new.find('input.enable').prop('checked', false); $new.find('select.label-select').val(''); $new.find('.label-custom').val(''); $new.find('.amount').val(''); $new.find('.hide-icon').prop('checked', false); $tb.find('tbody').append($new); refresh(); });
  $tb.on('click','.btn-dup', function(e){ e.preventDefault(); var $r=$(this).closest('tr'); var $new=$r.clone(); $tb.find('tbody').append($new); refresh(); });
  $tb.on('click','.btn-del', function(e){ e.preventDefault(); var $r=$(this).closest('tr'); if($tb.find('tbody tr').length>1){ $r.remove(); refresh(); } });
  $('input[name=\"jprm_multi\"]').on('change', updateVisibility);
  updateVisibility();
  refresh();
});
})(jQuery);
JS;

            wp_add_inline_script( 'jquery-ui-sortable', $js, 'after' );

            $css = "#jprm-multi-admin{margin-top:8px} #jprm-prices-table .sort-handle{cursor:move;opacity:.6} #jprm-prices-table .jp-hidden{display:none} #jprm-prices-table td,#jprm-prices-table th{vertical-align:middle} #jprm-prices-table input::placeholder{color:#8c8f94} #jprm-prices-table .regular-text::placeholder{color:#8c8f94}";
            wp_register_style( 'jprm-admin-inline', false );
            wp_enqueue_style( 'jprm-admin-inline' );
            wp_add_inline_style( 'jprm-admin-inline', $css );
        } );
add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        add_action( 'save_post_jprm_menu_item', [ $this, 'save_meta' ], 10, 3 );

        // Elementor guarded
        add_action( 'init', function() {
            if ( did_action( 'elementor/loaded' ) ) {
                add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
                add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
                add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_assets' ] );
            }
        } );

        // Shortcode
        add_shortcode( 'jprm_menu', [ $this, 'shortcode_menu' ] );
    }

    public function i18n() {
        load_plugin_textdomain( 'jellopoint-restaurant-menu', false, dirname( plugin_basename( JPRM_PLUGIN_FILE ) ) . '/languages' );
    }

    public function register_content_model() {
        register_post_type( 'jprm_menu_item', [
            'labels' => [
                'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
                'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
                'add_new_item'  => __( 'Add New Menu Item', 'jellopoint-restaurant-menu' ),
                'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => 'jellopoint-root',
            'show_in_admin_bar' => true,
            'show_in_rest'      => true,
            'supports'          => [ 'title', 'thumbnail', 'page-attributes' ],
            'map_meta_cap'      => true,
        ] );

        register_taxonomy( 'jprm_menu', [ 'jprm_menu_item' ], [
            'labels'            => [ 'name' => __( 'Menus', 'jellopoint-restaurant-menu' ) ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
        ] );

        register_taxonomy( 'jprm_section', [ 'jprm_menu_item' ], [
            'labels'            => [ 'name' => __( 'Sections', 'jellopoint-restaurant-menu' ) ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
        ] );
    }

    public function ensure_admin_menu() {
        add_menu_page( __( 'JelloPoint', 'jellopoint-restaurant-menu' ), __( 'JelloPoint', 'jellopoint-restaurant-menu' ), 'edit_posts', 'jellopoint-root', [ $this, 'render_root' ], 'dashicons-carrot', 56 );
        add_submenu_page( 'jellopoint-root', __( 'Menus', 'jellopoint-restaurant-menu' ), __( 'Menus', 'jellopoint-restaurant-menu' ), 'manage_options', 'edit-tags.php?taxonomy=jprm_menu&post_type=jprm_menu_item' );
        add_submenu_page( 'jellopoint-root', __( 'Sections', 'jellopoint-restaurant-menu' ), __( 'Sections', 'jellopoint-restaurant-menu' ), 'manage_options', 'edit-tags.php?taxonomy=jprm_section&post_type=jprm_menu_item' );
        add_submenu_page( 'jellopoint-root', __( 'Help / Diagnostics', 'jellopoint-restaurant-menu' ), __( 'Help / Diagnostics', 'jellopoint-restaurant-menu' ), 'manage_options', 'jprm-help', [ $this, 'render_help' ] );
    }

    public function render_root() { echo '<div class="wrap"><h1>JelloPoint</h1><p>Use the submenu to manage Menu Items.</p></div>'; }

    public function render_help() { ?>
        <div class="wrap">
            <h1><?php _e('Restaurant Menu - Diagnostics', 'jellopoint-restaurant-menu'); ?></h1>
            <table class="widefat striped"><tbody>
                <tr><th><?php _e('Plugin Version','jellopoint-restaurant-menu'); ?></th><td><?php echo esc_html( JPRM_VERSION ); ?></td></tr>
                <tr><th><?php _e('Option jprm_current_version','jellopoint-restaurant-menu'); ?></th><td><?php echo esc_html( get_option('jprm_current_version') ); ?></td></tr>
                <tr><th><?php _e('Post Type Registered','jellopoint-restaurant-menu'); ?></th><td><?php echo post_type_exists('jprm_menu_item') ?> 'yes' : 'no'; ?></td></tr>
                <tr><th><?php _e('Menus Taxonomy Registered','jellopoint-restaurant-menu'); ?></th><td><?php echo taxonomy_exists('jprm_menu') ?> 'yes' : 'no'; ?></td></tr>
                <tr><th><?php _e('Sections Taxonomy Registered','jellopoint-restaurant-menu'); ?></th><td><?php echo taxonomy_exists('jprm_section') ?> 'yes' : 'no'; ?></td></tr>
            </tbody></table>
            <p><a class="button button-primary" href="<?php echo admin_url('edit.php?post_type=jprm_menu_item'); ?>"><?php _e('Go to Menu Items','jellopoint-restaurant-menu'); ?></a></p>
        </div>
    <?php }

    public function disable_block_editor( $use, $post_type ) { return ( 'jprm_menu_item' === $post_type ) ?> false : $use; }

    public function cleanup_metaboxes() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || 'jprm_menu_item' !== $screen->post_type ) return;
        remove_meta_box( 'commentstatusdiv', 'jprm_menu_item', 'normal' );
        remove_meta_box( 'commentsdiv',      'jprm_menu_item', 'normal' );
        remove_meta_box( 'trackbacksdiv',    'jprm_menu_item', 'normal' );
        remove_meta_box( 'authordiv',        'jprm_menu_item', 'normal' );
        remove_meta_box( 'slugdiv',          'jprm_menu_item', 'normal' );
        remove_meta_box( 'revisionsdiv',     'jprm_menu_item', 'normal' );
        remove_meta_box( 'postcustom',       'jprm_menu_item', 'normal' );
        remove_meta_box( 'postexcerpt',      'jprm_menu_item', 'normal' );
    }

    public function register_metaboxes() { add_meta_box( 'jprm_item_details', __( 'Menu Item Details', 'jellopoint-restaurant-menu' ), [ $this, 'metabox_html' ], 'jprm_menu_item', 'normal', 'high' ); }

    public function metabox_html( $post ) {
        wp_nonce_field( 'jprm_save_meta', 'jprm_nonce' );
        $price   = get_post_meta( $post->ID, '_jprm_price', true );
        $price_label = get_post_meta( $post->ID, '_jprm_price_label', true );
        $multi  = get_post_meta( $post->ID, '_jprm_multi', true );
        $multi_rows = get_post_meta( $post->ID, '_jprm_multi_rows', true );
        $badge   = get_post_meta( $post->ID, '_jprm_badge', true );
        $badge_p = get_post_meta( $post->ID, '_jprm_badge_position', true );
        $sep     = get_post_meta( $post->ID, '_jprm_separator', true );
        $visible = get_post_meta( $post->ID, '_jprm_visible', true );
        $desc    = get_post_meta( $post->ID, '_jprm_desc', true );
        $order   = get_post_field( 'menu_order', $post );
        if ( '' === $visible && 'auto-draft' === $post->post_status ) { $visible = 'yes'; }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="jprm_desc"><?php _e( 'Description', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td><textarea name="jprm_desc" id="jprm_desc" rows="4" class="large-text" placeholder="<?php esc_attr_e('Short description goes here.', 'jellopoint-restaurant-menu'); ?>"><?php echo esc_textarea( $desc ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="jprm_price_label"><?php _e( 'Price Label (single price)', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td><input type="text" name="jprm_price_label" id="jprm_price_label" class="regular-text" value="<?php echo esc_attr( $price_label ); ?>" placeholder="<?php esc_attr_e('e.g. Regular', 'jellopoint-restaurant-menu'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="jprm_price"><?php _e( 'Price', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td><input type="text" name="jprm_price" id="jprm_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text" placeholder="€ 9,50" /></td>
            </tr>
            <tr>
                <th><label for="jprm_multi"><?php _e( 'Multiple Prices', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td>
                    <label><input type="checkbox" name="jprm_multi" value="yes" <?php checked( $multi, 'yes' ); ?> id="jprm_multi">/> <?php _e( 'Enable multiple prices (enter rows below)', 'jellopoint-restaurant-menu' ); ?></label>
                    
                    
<style id="jprm-metabox-css">
#jprm-multi-admin{overflow-x:auto}
#jprm-prices-table{table-layout:fixed;width:100%}
#jprm-prices-table th:nth-child(1),#jprm-prices-table td:nth-child(1){width:70px}
#jprm-prices-table th:nth-child(2),#jprm-prices-table td:nth-child(2){width:240px}
#jprm-prices-table th:nth-child(4),#jprm-prices-table td:nth-child(4){width:160px}
#jprm-prices-table th:nth-child(5),#jprm-prices-table td:nth-child(5){width:110px}
#jprm-prices-table th:nth-child(6),#jprm-prices-table td:nth-child(6){width:90px}
#jprm-prices-table input[type=text]{max-width:100%}
#jprm-prices-table .amount{max-width:150px}
</style>

                    <div id="jprm-multi-admin" <?php echo ($multi==='yes'?'':'style="display:none"'); ?>>
                        <input type="hidden" name="jprm_prices_v1" id="jprm_prices_v1" value="" />
                        <table class="widefat striped" id="jprm-prices-table">
                            <thead>
                                <tr>
                                    <th style="width:70px;"><?php _e('Enable','jellopoint-restaurant-menu'); ?></th>
                                    <th style="width:240px;"><?php _e('Label','jellopoint-restaurant-menu'); ?></th>
                                    <th><?php _e('Custom Label','jellopoint-restaurant-menu'); ?></th>
                                    <th style="width:160px;"><?php _e('Price','jellopoint-restaurant-menu'); ?></th>
                                    <th style="width:110px;"><?php _e('Hide Icon','jellopoint-restaurant-menu'); ?></th>
                                    <th style="width:90px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $preset_map = function_exists('jprm_get_price_label_full_map') ?> jprm_get_price_label_full_map() : [];
                            $options = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
                            $options .= '<option value="custom">'.esc_html__('Custom','jellopoint-restaurant-menu').'</option>';
                            foreach( $preset_map as $slug => $row ){
                                $t = esc_html( $row['label'] );
                                $options .= '<option value="'.esc_attr($slug).'">'.$t.'</option>';
                            }
                            // Prefill from existing meta (new JSON) or from legacy textarea
                            $prefill = [];
                            $json = get_post_meta( $post->ID, '_jprm_prices_v1', true );
                            if ( $json ) {
                                $arr = json_decode( $json, true );
                                if ( is_array($arr) ) { $prefill = $arr; }
                            } elseif ( $multi_rows ) {
                                foreach ( preg_split('/
?
/', $multi_rows) as $line ) {
                                    $line = trim($line); if ( $line === '' ) continue;
                                    $parts = explode('|',$line,2);
                                    $lbl = trim($parts[0]); $amt = isset($parts[1])?trim($parts[1]):'';
                                    $slug = sanitize_title($lbl);
                                    $label_select = isset($preset_map[$slug]) ? $slug : 'custom';
                                    $label_custom = $label_select==='custom' ? $lbl : '';
                                    $prefill[] = [ 'enable'=>1,'label_select'=>$label_select,'label_custom'=>$label_custom,'amount'=>$amt,'hide_icon'=>0 ];
                                }
                            }
                            if ( empty($prefill) ) { $prefill = [ [ 'enable'=>0,'label_select'=>'','label_custom'=>'','amount'=>'','hide_icon'=>0 ] ]; }
                            $row_index = 0;
                            foreach ( $prefill as $r ) {
                                $row_index++;
                                $en = !empty($r['enable']);
                                $ls = isset($r['label_select']) ? $r['label_select'] : '';
                                $lc = isset($r['label_custom']) ? $r['label_custom'] : '';
                                $am = isset($r['amount']) ? $r['amount'] : '';
                                $hi = !empty($r['hide_icon']);
                                $hidden = (!$en && $row_index>1) ? ' class="jp-hidden"' : '';
                                echo '<tr'.$hidden.'>';
                                echo '<td><input type="checkbox" class="enable" '.( $en?'checked':'' ).' /></td>';
                                echo '<td><select class="label-select">'.$options.'</select>
<script>
(function($){
  $(function(){
    // Ensure ID and toggle behavior
    $('input[name="jprm_multi"]').attr('id','jprm_multi');
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

    // Change handlers
    $tbody.on('change','select.label-select',function(){ syncRow($(this).closest('tr')); });
    $tbody.on('change','input.enable',function(){ syncRow($(this).closest('tr')); });

    // Add row
    $('#jprm-add-price').on('click', function(e){
      e.preventDefault();
      var $last = $tbody.find('tr').last();
      var $new  = $last.clone(true, true).removeClass('jp-hidden');
      $new.find('input.enable').prop('checked', true);
      $new.find('select.label-select').val('custom');
      $new.find('input.label-custom').val('');
      $new.find('input.amount').val('');
      $tbody.append($new);
      syncRow($new);
    });

    // Delete row
    $tbody.on('click','a.jp-del-row, a:contains("Delete")', function(e){
      e.preventDefault();
      var $rows = $tbody.find('tr');
      if ($rows.length <= 1) return;
      $(this).closest('tr').remove();
    });

    // Serialize on submit
    function collectRows(){
      var out = [];
      $tbody.find('tr').each(function(){
        var $tr = $(this);
        var row = {
          enable: $tr.find('input.enable').is(':checked') ? 1 : 0,
          label_select: $tr.find('select.label-select').val() || '',
          label_custom: $tr.find('input.label-custom').val() || '',
          amount: $tr.find('input.amount').val() || '',
          hide_icon: $tr.find('input.hide-icon').is(':checked') ? 1 : 0
        };
        if (row.enable || row.label_select || row.label_custom || row.amount) out.push(row);
      });
      return out;
    }
    $('#post').on('submit', function(){
      $('#jprm_prices_v1').val(JSON.stringify(collectRows()));
    });
  });
})(jQuery);
</script>
	echo '</td>';
                                echo '<td><input type="text" class="label-custom regular-text" value="'.esc_attr($lc).'" /></td>';
                                echo '<td><input type="text" class="amount regular-text" value="'.esc_attr($am).'" placeholder="€ 7,50" /></td>';
                                echo '<td><input type="checkbox" class="hide-icon" '.( $hi?'checked':'' ).' /></td>';
                                echo '<td><span class="dashicons dashicons-move sort-handle" title="'.esc_attr__('Drag to reorder','jellopoint-restaurant-menu').'"></span> <a href="#" class="button button-small btn-dup">'.esc_html__('Duplicate','jellopoint-restaurant-menu').'</a> <a href="#" class="button button-small btn-del">'.esc_html__('Delete','jellopoint-restaurant-menu').'</a></td>';
                                echo '</tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                        <p><a href="#" class="button" id="jprm-add-price"><?php _e('Add another price','jellopoint-restaurant-menu'); ?></a></p>
                        <p class="description"><?php _e('Rows are used when “Multiple Prices” is enabled. Label uses your Price Labels presets unless “Custom” is selected.','jellopoint-restaurant-menu'); ?></p>
                    </div>

                </td>
            </tr>
            <tr>
                <th><label for="jprm_badge"><?php _e( 'Badge', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td><input type="text" name="jprm_badge" id="jprm_badge" value="<?php echo esc_attr( $badge ); ?>" class="regular-text" placeholder="<?php esc_attr_e('NEW', 'jellopoint-restaurant-menu'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="jprm_badge_position"><?php _e( 'Badge Position', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td>
                    <select name="jprm_badge_position" id="jprm_badge_position">
                        <?php $opts = [ 'corner-left' => __( 'Corner Left', 'jellopoint-restaurant-menu' ), 'corner-right' => __( 'Corner Right', 'jellopoint-restaurant-menu' ), 'inline' => __( 'Inline (next to title)', 'jellopoint-restaurant-menu' ) ];
                        $cur = $badge_p ?> $badge_p : 'corner-right'; foreach ( $opts as $k=>$label ) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($cur,$k,false), esc_html($label)); ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php _e( 'Separator', 'jellopoint-restaurant-menu' ); ?></th>
                <td><label><input type="checkbox" name="jprm_separator" value="yes" <?php checked( $sep, 'yes' ); ?>/> <?php _e( 'Show separator after item', 'jellopoint-restaurant-menu' ); ?></label></td>
            </tr>
            <tr>
                <th><?php _e( 'Visible', 'jellopoint-restaurant-menu' ); ?></th>
                <td><label><input type="checkbox" name="jprm_visible" value="yes" <?php checked( $visible, 'yes' ); ?>/> <?php _e( 'Item is visible on the menu', 'jellopoint-restaurant-menu' ); ?></label></td>
            </tr>
            <tr>
                <th><label for="jprm_order"><?php _e( 'Order', 'jellopoint-restaurant-menu' ); ?></label></th>
                <td><input type="number" name="jprm_order" id="jprm_order" value="<?php echo esc_attr( $order ); ?>" class="small-text" /> <span class="description"><?php _e('Lower numbers appear first.', 'jellopoint-restaurant-menu'); ?></span></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta( $post_id, $post, $update ) {
        if ( ! isset( $_POST['jprm_nonce'] ) || ! wp_verify_nonce( $_POST['jprm_nonce'], 'jprm_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        update_post_meta( $post_id, '_jprm_price', isset($_POST['jprm_price']) ? sanitize_text_field($_POST['jprm_price']) : '' );
        update_post_meta( $post_id, '_jprm_price_label', isset($_POST['jprm_price_label']) ? sanitize_text_field($_POST['jprm_price_label']) : '' );
        update_post_meta( $post_id, '_jprm_multi', isset($_POST['jprm_multi']) ? 'yes' : '' );
        update_post_meta( $post_id, '_jprm_multi_rows', isset($_POST['jprm_multi_rows']) ? wp_kses_post($_POST['jprm_multi_rows']) : '' );
        $json = isset($_POST['jprm_prices_v1']) ? wp_unslash( $_POST['jprm_prices_v1'] ) : '';
        if ( $json ) { $arr = json_decode( $json, true ); if ( is_array($arr) ) { foreach ( $arr as &$r ){ $r = [ 'enable'=> !empty($r['enable']) ? 1:0, 'label_select'=> sanitize_title( $r['label_select'] ?? '' ), 'label_custom'=> sanitize_text_field( $r['label_custom'] ?? '' ), 'amount'=> wp_kses_post( $r['amount'] ?? '' ), 'hide_icon'=> !empty($r['hide_icon']) ? 1:0, 'order'=> intval($r['order'] ?? 0 ) ]; } unset($r); $json = wp_json_encode( $arr ); } }
        update_post_meta( $post_id, '_jprm_prices_v1', $json );
        update_post_meta( $post_id, '_jprm_badge', isset($_POST['jprm_badge']) ? sanitize_text_field($_POST['jprm_badge']) : '' );
        update_post_meta( $post_id, '_jprm_badge_position', isset($_POST['jprm_badge_position']) ? sanitize_text_field($_POST['jprm_badge_position']) : 'corner-right' );
        update_post_meta( $post_id, '_jprm_separator', isset($_POST['jprm_separator']) ? 'yes' : '' );
        $json = isset($_POST['jprm_prices_v1']) ? wp_unslash($_POST['jprm_prices_v1']) : '';
        if ( $json ) { $arr = json_decode($json,true); if ( is_array($arr) ) { foreach ($arr as &$r){ $r = [ 'enable'=> !empty($r['enable'])?1:0, 'label_select'=> sanitize_title($r['label_select']??''), 'label_custom'=> sanitize_text_field($r['label_custom']??''), 'amount'=> wp_kses_post($r['amount']??''), 'hide_icon'=> !empty($r['hide_icon'])?1:0, 'order'=> intval($r['order']??0) ]; } unset($r); $json = wp_json_encode($arr); } } update_post_meta( $post_id, '_jprm_prices_v1', $json );
        update_post_meta( $post_id, '_jprm_visible', isset($_POST['jprm_visible']) ? 'yes' : '' );
        update_post_meta( $post_id, '_jprm_desc', isset($_POST['jprm_desc']) ? wp_kses_post($_POST['jprm_desc']) : '' );
        if ( isset($_POST['jprm_order']) ) { $order = intval($_POST['jprm_order']); if ( $post->menu_order !== $order ) wp_update_post( [ 'ID'=>$post_id, 'menu_order'=>$order ] ); }
    }

    public function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! post_type_exists( 'jprm_menu_item' ) ) echo '<div class="notice notice-error"><p>JelloPoint Restaurant Menu: Post Type not registered.</p></div>';
    }

    public function admin_footer( $text ) { if ( current_user_can('manage_options') ) $text .= ' | JelloPoint Restaurant Menu v' . esc_html( JPRM_VERSION ) . ' active'; return $text; }

    // Elementor
    public function register_category( $elements_manager ) { $elements_manager->add_category( 'jellopoint-widgets', [ 'title' => __( 'JelloPoint Widgets', 'jellopoint-restaurant-menu' ), 'icon' => 'fa fa-plug' ], 1 ); }
    public function register_widget( $widgets_manager ) { require_once JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php'; $widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() ); }
    public function enqueue_assets() { wp_enqueue_style( 'jprm-frontend', JPRM_PLUGIN_URL . 'assets/css/frontend.css', [], JPRM_VERSION ); }

    // Shortcode renderer (dynamic)
    public function shortcode_menu( $atts ) {
        $atts = shortcode_atts( [ 'menu'=>'', 'sections'=>'', 'orderby'=>'menu_order', 'order'=>'ASC', 'limit'=>-1, 'hide_invisible'=>1, 'row_order'=>'label_left', 'label_presentation'=>'text' ], $atts, 'jprm_menu' );
        $tax_query = [];
        if ( $atts['menu'] ) $tax_query[] = [ 'taxonomy'=>'jprm_menu', 'field'=>'slug', 'terms'=>array_map('trim', explode(',', $atts['menu'])) ];
        if ( $atts['sections'] ) $tax_query[] = [ 'taxonomy'=>'jprm_section', 'field'=>'slug', 'terms'=>array_map('trim', explode(',', $atts['sections'])) ];
        $meta_query = [];
        if ( intval($atts['hide_invisible']) ) $meta_query[] = [ 'key'=>'_jprm_visible', 'value'=>'yes', 'compare'=>'=' ];

        $q = new \WP_Query([
            'post_type'      => 'jprm_menu_item',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
            'tax_query'      => $tax_query ?: null,
            'meta_query'     => $meta_query ?: null,
            'no_found_rows'  => true,
        ]);

        ob_start();
        $this->current_shortcode = $atts;
        echo '<style>.jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5em;}.jp-menu__price-row .jp-col.jp-col-labelwrap{display:inline-flex;align-items:center;gap:.5em;}.jp-menu__price-row.jp-order--price-left .jp-col-price{order:1}.jp-menu__price-row.jp-order--price-left .jp-col-labelwrap{order:2}.jp-menu__price-row.jp-order--label-left .jp-col-labelwrap{order:1}.jp-menu__price-row.jp-order--label-left .jp-col-price{order:2}</style>';
        echo '<ul class="jp-menu">';
        while($q->have_posts()){ $q->the_post(); $this->render_menu_item_from_post(get_the_ID()); }
        echo '</ul>';
        wp_reset_postdata();
        $this->current_shortcode = [];
        return ob_get_clean();
    }

    public function render_menu_item_from_post( $post_id ) {
        $title = get_the_title($post_id);
        $desc_meta = get_post_meta( $post_id, '_jprm_desc', true );
        $desc = $desc_meta ? wpautop($desc_meta) : apply_filters( 'the_content', get_post_field('post_content', $post_id) );
        $price = get_post_meta( $post_id, '_jprm_price', true );
        $price_label = get_post_meta( $post_id, '_jprm_price_label', true );
        $multi = get_post_meta( $post_id, '_jprm_multi', true ) === 'yes';
        $multi_rows_raw = get_post_meta( $post_id, '_jprm_multi_rows', true );
        $rows = [];
        $admin_rows_json = get_post_meta( $post_id, '_jprm_prices_v1', true );
        if ( $admin_rows_json ) { $arr = json_decode($admin_rows_json, true); if ( is_array($arr) ) { $preset = function_exists('jprm_get_price_label_full_map') ? jprm_get_price_label_full_map() : []; usort($arr, function($a,$b){ return intval($a['order']??0) <=> intval($b['order']??0); }); foreach ($arr as $r){ if (empty($r['enable'])) continue; $sel = $r['label_select']??''; $lbl = ($sel==='custom'||$sel==='') ? ($r['label_custom']??'') : ($preset[$sel]['label']??''); $amt = $r['amount']??''; if ($lbl==='' && $amt==='') continue; $rows[] = [ 'label'=>$lbl, 'price'=>$amt ]; } } }
        if ( empty($rows) && $multi_rows_raw ) {
            foreach ( preg_split('/\r?\n/', $multi_rows_raw) as $line ) {
                $line = trim($line); if ( $line === '' ) continue;
                $parts = explode('|', $line, 2);
                $label = trim($parts[0]); $pval = isset($parts[1]) ? trim($parts[1]) : '';
                $rows[] = [ 'label'=>$label, 'price'=>$pval ];
            }
        }
        $badge = get_post_meta( $post_id, '_jprm_badge', true );
        $badge_p = get_post_meta( $post_id, '_jprm_badge_position', true ) ?: 'corner-right';
        $sep  = get_post_meta( $post_id, '_jprm_separator', true ) === 'yes';
        $img  = get_the_post_thumbnail( $post_id, 'thumbnail', [ 'class'=>'attachment-thumbnail size-thumbnail' ] );
        $badge_class = 'jp-menu__badge' . ( $badge_p === 'inline' ? ' jp-menu__badge--inline' : ( $badge_p === 'corner-left' ? ' jp-menu__badge--corner jp-menu__badge--corner-left' : ' jp-menu__badge--corner jp-menu__badge--corner-right' ) );

        echo '<li class="jp-menu__item">';
        if ( $badge ) echo '<span class="'.esc_attr($badge_class).'">'.esc_html($badge).'</span>';
        
echo '<div class="jp-menu__inner" style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem">';
  echo '<div class="jp-box-left" style="display:flex;gap:.75rem;flex:1 1 auto;min-width:0">';
    if ( $img ) echo '<div class="jp-menu__media">'.$img.'</div>';
    echo '<div class="jp-menu__content" style="flex:1 1 auto;min-width:0;width:auto">';
      echo '<div class="jp-menu__header">';
        echo '<span class="jp-menu__title">'.esc_html($title).'</span>';
      echo '</div>';
      if ( $desc ) echo '<div class="jp-menu__desc">'.$desc.'</div>';
    echo '</div>';
  echo '</div>';
  echo '<div class="jp-box-right" style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end">';
    if ( $multi && ! empty($rows) ) {
        echo '<div class="jp-menu__pricegroup" style="display:inline-grid;justify-items:end">';
        foreach ( $rows as $r ) {
            $icon_html = '';
            if ( !empty($r['icon_id']) ) { $icon_html = '<span class="jp-price-icon" aria-hidden="true">' . wp_get_attachment_image( intval($r['icon_id']), 'thumbnail', false, [ 'alt' => '' ] ) . '</span>'; }
            $row_order = isset($this->current_shortcode['row_order']) ? $this->current_shortcode['row_order'] : 'label_left';
            $label_presentation = isset($this->current_shortcode['label_presentation']) ? $this->current_shortcode['label_presentation'] : 'text';
            $wrapper_class = 'jp-menu__price-row ' . ( $row_order === 'price_left' ? 'jp-order--price-left' : 'jp-order--label-left' );
            $label_html = ($label_presentation === 'icon' && !empty($icon_html)) ? $icon_html : ( isset($r['label']) ? '<span class="jp-price-label">'.esc_html( $r['label'] ).'</span>' : '' );
            if ( $label_presentation !== 'icon' ) { $icon_html = ''; }
            echo '<div class="' . $wrapper_class . '"><span class="jp-col jp-col-labelwrap">' . $label_html . '</span><span class="jp-col jp-col-price">' . wp_kses_post( $r['price'] ) . '</span></div>';
        }
        echo '</div>';
    } else {
        if ( $price ) {
            $row_order = isset($this->current_shortcode['row_order']) ? $this->current_shortcode['row_order'] : 'label_left';
            $label_presentation = isset($this->current_shortcode['label_presentation']) ? $this->current_shortcode['label_presentation'] : 'text';
            $wrapper_class = 'jp-menu__price-row ' . ( $row_order === 'price_left' ? 'jp-order--price-left' : 'jp-order--label-left' );
            $preset_full = function_exists('jprm_get_price_label_full_map') ? jprm_get_price_label_full_map() : [];
            $slug_guess = sanitize_title( $price_label );
            $icon_html = ( $label_presentation === 'icon' && isset($preset_full[$slug_guess]['icon_id']) && $preset_full[$slug_guess]['icon_id'] )
                ? '<span class="jp-price-icon" aria-hidden="true">' . wp_get_attachment_image( intval($preset_full[$slug_guess]['icon_id']), 'thumbnail', false, [ 'alt' => '' ] ) . '</span>'
                : '';
            $label_html = ($label_presentation === 'icon' && !empty($icon_html))
                ? $icon_html
                : ( $price_label ? '<span class="jp-price-label">'.esc_html( $price_label ).'</span>' : '' );
            echo '<div class="jp-menu__pricegroup" style="display:inline-grid;justify-items:end">';
            echo '<div class="' . $wrapper_class . '"><span class="jp-col jp-col-labelwrap">' . $label_html . '</span><span class="jp-col jp-col-price">' . wp_kses_post( $price ) . '</span></div>';
            echo '</div>';
        }
    }
  echo '</div>';
echo '</div>';
if ( $sep ) echo '<div class="jp-menu__separator" aria-hidden="true"></div>';
echo '</li>';

    }

    public function output_alignment_css() {
        echo '<style id="jprm-fixes-inline-css">
        .jp-menu__inner{display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem}
        .jp-box-right{display:flex;flex-direction:column;align-items:flex-end}
        .jp-menu__pricegroup{display:inline-grid;justify-items:end;text-align:right}
        .jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%}
        .jp-menu__price-row .jp-col{display:block}
        .jp-menu__price-row .jp-col.jp-col-labelwrap{display:inline-flex;align-items:center;gap:.5rem}
        .jp-menu__item{display:grid;grid-template-columns:1fr auto;gap:1rem}
        .jp-menu__item .jp-menu__pricegroup,.jp-menu__item .jp-menu__price{justify-self:end;text-align:right}
        </style>';
    }
}
