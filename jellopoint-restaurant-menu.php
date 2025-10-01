<?php
/**0
 * Plugin Name: JelloPoint Restaurant Menu
 * Description: Elementor  widget for restaurant menus with dynamic CPT and multi-price support.
 * Version: 2.0.1
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 * Domain Path: /languages
 */

// dev flag (follows WP_DEBUG unless you override it)
if ( ! defined('JPRM_DEV') ) {
    define('JPRM_DEV', defined('WP_DEBUG') && WP_DEBUG);
}

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'JPRM_VERSION' ) ) define( 'JPRM_VERSION', '2.0.1' );
if ( ! defined( 'JPRM_PLUGIN_FILE' ) ) define( 'JPRM_PLUGIN_FILE', __FILE__ );
if ( ! defined( 'JPRM_PLUGIN_PATH' ) ) define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_URL' ) ) define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'JPRM_MIN_PHP' ) ) define( 'JPRM_MIN_PHP', '7.2' );

if ( version_compare( PHP_VERSION, JPRM_MIN_PHP, '<' ) ) {
    add_action('admin_notices', function(){
        echo '<div class="notice notice-error"><p>JelloPoint Restaurant Menu requires PHP '.esc_html(JPRM_MIN_PHP).' or higher. Current: '.esc_html(PHP_VERSION).'</p></div>';
    });
    return;
}
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';

if ( class_exists('JPRM_Admin_MenuItem_Meta') ) {
    $ref = new \ReflectionClass('JPRM_Admin_MenuItem_Meta');
    $expected = JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
    if ( wp_normalize_path($ref->getFileName()) !== wp_normalize_path($expected) ) {
        // stop early in dev so we don't debug the wrong file
        if ( JPRM_DEV ) {
            wp_die('JPRM dev guard: Admin meta loaded from unexpected path: <code>'.$ref->getFileName().'</code>');
        }
    }
}

// dev-only diagnostics page
if ( JPRM_DEV ) {
    require_once JPRM_PLUGIN_PATH . 'includes/admin/class-system-check.php';
}

update_option( 'jprm_current_version', JPRM_VERSION );

// Bootstrap safely (supports both Plugin::instance() and jprm_bootstrap())
if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) && method_exists( '\JelloPoint\RestaurantMenu\Plugin', 'instance' ) ) {
    \JelloPoint\RestaurantMenu\Plugin::instance();
} elseif ( function_exists( '\JelloPoint\RestaurantMenu\jprm_bootstrap' ) ) {
    \JelloPoint\RestaurantMenu\jprm_bootstrap();
}


// === JPRM Price Labels Settings ===
if ( is_admin() ) {

add_action('admin_init', function() {
    if ( ! get_option('jprm_price_labels_v2', '') ) {
        // migration seed handled inside register_setting callback too, but ensure option exists
        $seed = get_option('jprm_price_labels', "Small\nMedium\nLarge");
        $lines = preg_split("/\r\n|\r|\n/", (string)$seed);
        $rows = [];
        $order = 0;
        foreach ( $lines as $line ) {
            $t = trim( wp_strip_all_tags( $line ) );
            if ( $t === '' ) continue;
            $rows[] = [
                'id'      => 'pl-' . wp_generate_uuid4(),
                'label'   => $t,
                'slug'    => sanitize_title($t),
                'active'  => true,
                'icon_id' => 0,
                'order'   => $order++,
            ];
        }
        if ( ! empty($rows) ) {
            update_option('jprm_price_labels_v2', wp_json_encode($rows));
        }
    }
    register_setting( 'jprm_settings_v2', 'jprm_price_labels_v2' );
});
add_action('admin_menu', function() {
        // Attach Price Labels under JelloPoint Menu (cutlery)
        if ( ! isset( $GLOBALS['admin_page_hooks']['jprm_admin'] ) ) {
            add_menu_page( 'JelloPoint Menu', 'JelloPoint Menu', 'manage_options', 'jprm_admin', function(){}, 'dashicons-food', 56 );
        }
        add_submenu_page(
            'jprm_admin',
            __('Restaurant Menu - Price Labels','jellopoint-restaurant-menu'),
            __('Restaurant Menu - Price Labels','jellopoint-restaurant-menu'),
            'manage_options',
            'jprm-price-labels',
            'jprm_render_price_labels_page'
        );
    });

    add_action('admin_init', function() {
        register_setting( 'jprm_settings', 'jprm_price_labels', [
            'type' => 'string',
            'sanitize_callback' => function( $input ) {
                $lines = preg_split("/\r\n|\r|\n/", (string)$input);
                $clean = [];
                foreach ( $lines as $line ) {
                    $t = trim( wp_strip_all_tags( $line ) );
                    if ( $t !== '' ) { $clean[$t] = $t; } // dedupe by key
                }
                return implode("\n", array_values($clean));
            },
            'default' => "Small\nMedium\nLarge",
        ]);
    });
}


function jprm_get_price_label_presets() {
    $v2 = get_option('jprm_price_labels_v2', '');
    if ( $v2 ) {
        $rows = json_decode( $v2, true );
        if ( is_array($rows) ) {
            usort($rows, function($a,$b){ return intval($a['order'] ?? 0) <=> intval($b['order'] ?? 0); });
            $out = [];
            foreach ( $rows as $r ) {
                if ( ! empty($r['active']) ) {
                    $out[] = (string)($r['label'] ?? '');
                }
            }
            if ( ! empty($out) ) {
                return $out;
            }
        }
    }
    $legacy = get_option('jprm_price_labels', "Small\nMedium\nLarge");
    $lines = preg_split("/\r\n|\r|\n/", (string)$legacy);
    $out = [];
    foreach ( $lines as $line ) {
        $t = trim( wp_strip_all_tags( $line ) );
        if ( $t !== '' ) { $out[] = $t; }
    }
    return $out ?: [ 'Small', 'Medium', 'Large' ];
}

function jprm_render_price_labels_page() {
    if ( ! current_user_can('manage_options') ) { return; }
    settings_errors();
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    $current = get_option('jprm_price_labels_v2', '');
    $rows = [];
    if ( $current ) {
        $rows = json_decode( $current, true );
        if ( ! is_array($rows) ) $rows = [];
    }
    usort($rows, function($a,$b){ return intval($a['order'] ?? 0) <=> intval($b['order'] ?? 0); });
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Restaurant Menu – Price Labels', 'jellopoint-restaurant-menu' ); ?></h1>
        <form method="post" action="options.php" id="jprm-price-labels-form">
            <?php settings_fields( 'jprm_settings_v2' ); ?>
            <input type="hidden" name="jprm_price_labels_v2" id="jprm_price_labels_v2" value="<?php echo esc_attr( get_option('jprm_price_labels_v2','') ); ?>" />
            <p class="description"><?php echo esc_html__( 'Manage the preset price labels used in the widget. You can reorder, rename and choose icons. Inactive rows are hidden from dropdowns in the widget.', 'jellopoint-restaurant-menu' ); ?></p>
            <table class="widefat striped" id="jprm-labels-table">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th><?php echo esc_html__( 'Label', 'jellopoint-restaurant-menu' ); ?></th>
                        <th><?php echo esc_html__( 'Slug', 'jellopoint-restaurant-menu' ); ?></th>
                        <th style="width:140px;"><?php echo esc_html__( 'Icon', 'jellopoint-restaurant-menu' ); ?></th>
                        <th style="width:100px;"><?php echo esc_html__( 'Active', 'jellopoint-restaurant-menu' ); ?></th>
                        <th style="width:120px;"><?php echo esc_html__( 'Actions', 'jellopoint-restaurant-menu' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty($rows) ) : $rows = [
                    [ 'label'=>'Small','slug'=>'small','active'=>true,'icon_id'=>0,'order'=>0 ],
                    [ 'label'=>'Medium','slug'=>'medium','active'=>true,'icon_id'=>0,'order'=>1 ],
                    [ 'label'=>'Large','slug'=>'large','active'=>true,'icon_id'=>0,'order'=>2 ],
                ]; endif; ?>
                <?php foreach ( $rows as $r ) : ?>
                    <tr class="jprm-row">
                        <td class="drag">⋮⋮</td>
                        <td><input type="text" class="regular-text label" value="<?php echo esc_attr( (string)($r['label'] ?? '') ); ?>" /></td>
                        <td><input type="text" class="regular-text slug" value="<?php echo esc_attr( (string)($r['slug'] ?? '') ); ?>" /></td>
                        <td class="icon-cell">
                            <div class="jprm-icon-preview">
                            <?php
                                $iid = intval( $r['icon_id'] ?? 0 );
                                if ( $iid ) {
                                    echo wp_get_attachment_image( $iid, 'thumbnail', false, [ 'style'=>'max-width:48px;height:auto;' ] );
                                }
                            ?></div>
                            <input type="hidden" class="icon-id" value="<?php echo esc_attr( intval( $r['icon_id'] ?? 0 ) ); ?>" />
                            <button type="button" class="button jprm-icon-select"><?php echo esc_html__( 'Select', 'jellopoint-restaurant-menu' ); ?></button>
                            <button type="button" class="button-link-delete jprm-icon-remove"><?php echo esc_html__( 'Remove', 'jellopoint-restaurant-menu' ); ?></button>
                        </td>
                        <td style="text-align:center;"><input type="checkbox" class="active" <?php checked( ! empty($r['active']) ); ?> /></td>
                        <td><button type="button" class="button-link-delete jprm-delete-row"><?php echo esc_html__( 'Delete', 'jellopoint-restaurant-menu' ); ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="jprm-add-row"><?php echo esc_html__( 'Add row', 'jellopoint-restaurant-menu' ); ?></button></p>
            <?php submit_button(); ?>
        </form>
        <style>
            #jprm-labels-table .drag { cursor: move; font-size: 18px; text-align:center; }
            #jprm-labels-table input[type=text]{ width:100%; }
            #jprm-labels-table .icon-cell { display:flex; gap:8px; align-items:center; }
            #jprm-labels-table .jprm-icon-preview img { max-width:48px; height:auto; display:block; }
        </style>

    </div>
    <?php
}


add_action('admin_menu', function() {
    // Build a full map that is usable by widgets etc.
    // Keyed by slug => [ 'label' => string, 'icon_id' => int ]
    // (Function lives below)
}, 99);

function jprm_get_price_label_map() {
    $out = [];
    $v2 = get_option('jprm_price_labels_v2', '');
    if ( $v2 ) {
        $rows = json_decode( $v2, true );
        if ( is_array($rows) ) {
            foreach ( $rows as $r ) {
                if ( empty($r['active']) ) continue;
                $slug  = sanitize_title( (string)($r['slug'] ?? '') );
                $label = (string)($r['label'] ?? '');
                if ( $slug && $label ) {
                    $out[$slug] = [
                        'label'   => $label,
                        'icon_id' => intval( $r['icon_id'] ?? 0 ),
                    ];
                }
            }
        }
    }
    if ( empty($out) ) {
        // Fallback from simple presets
        foreach ( jprm_get_price_label_presets() as $label ) {
            $out[ sanitize_title( $label ) ] = [ 'label' => $label, 'icon_id' => 0 ];
        }
    }
    return $out;
}


add_action('admin_enqueue_scripts', function($hook){
    if ( function_exists('get_current_screen') ) {
        $screen = get_current_screen();
        $id = $screen ? $screen->id : '';
        if (
    $id === 'settings_page_jprm-price-labels' ||
    $id === 'jellopoint-root_page_jprm-price-labels' ||
    $id === 'jellopoint-admin_page_jprm-price-labels' ||
    $id === 'toplevel_page_jprm-price-labels' ||
    ( isset($_GET['page']) && sanitize_key($_GET['page']) === 'jprm-price-labels' )
) {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_add_inline_script('jquery-ui-sortable', '
jQuery(function($){
    function slugify(str){
        return (str || \'\').toString().toLowerCase()
            .replace(/[^a-z0-9\s\-]/g,\'\')
            .trim().replace(/\s+/g,\'-\').replace(/\-+/g,\'-\');
    }
    function collectRows(){
        var rows = [];
        $(\'#jprm-labels-table tbody tr.jprm-row\').each(function(i){
            var $tr = $(this);
            var label = $.trim($tr.find(\'input.label\').val());
            var slug = $.trim($tr.find(\'input.slug\').val()) || slugify(label);
            var icon = parseInt($tr.find(\'input.icon-id\').val(), 10) || 0;
            var active = $tr.find(\'input.active\').is(\':checked\') ? 1 : 0;
            rows.push({ id: \'pl-\'+i, label: label, slug: slug, icon_id: icon, active: active, order: i });
        });
        $(\'#jprm_price_labels_v2\').val(JSON.stringify(rows));
    }
    $(\'#jprm-add-row\').on(\'click\', function(e){
        e.preventDefault();
        var $tbody = $(\'#jprm-labels-table tbody\');
        var idx = $tbody.find(\'tr.jprm-row\').length;
        var html = \'<tr class="jprm-row">\' +
            \'<td class="drag">⋮⋮</td>\' +
            \'<td><input type="text" class="regular-text label" value="" /></td>\' +
            \'<td><input type="text" class="regular-text slug" value="" /></td>\' +
            \'<td class="icon-cell"><div class="jprm-icon-preview"></div><input type="hidden" class="icon-id" value="0" />\' +
            \'<button type="button" class="button jprm-icon-select">Select</button> \' +
            \'<button type="button" class="button-link-delete jprm-icon-remove">Remove</button></td>\' +
            \'<td style="text-align:center;"><input type="checkbox" class="active" checked /></td>\' +
            \'<td><button type="button" class="button-link-delete jprm-delete-row">Delete</button></td>\' +
            \'</tr>\';
        $tbody.append(html);
        collectRows();
    });
    $(document).on(\'click\', \'.jprm-delete-row\', function(e){
        e.preventDefault(); $(this).closest(\'tr\').remove(); collectRows();
    });
    if ($.fn.sortable) {
        $(\'#jprm-labels-table tbody\').sortable({
            handle: \'.drag\',
            stop: collectRows,
            helper: function(e, ui){
                ui.children().each(function(index){
                    var $orig = ui.children();
                    $(this).width($orig.eq(index).width());
                });
                return ui;
            }
        });
    } else {
        console.warn(\'jQuery UI Sortable not loaded\');
    }
    // Media select
    $(\'#jprm-labels-table\').on(\'click\', \'.jprm-icon-select\', function(e){
        e.preventDefault();
        var $cell = $(this).closest(\'.icon-cell\');
        var frame = wp.media({
            title: \'Select Icon\',
            button: { text: \'Use this icon\' },
            library: { type: \'image\' },
            multiple: false
        });
        frame.on(\'select\', function(){
            var att = frame.state().get(\'selection\').first().toJSON();
            $cell.find(\'input.icon-id\').val(att.id);
            var $prev = $cell.find(\'.jprm-icon-preview\').empty();
            if (att.sizes && att.sizes.thumbnail) {
                $prev.append(\'<img src="\'+att.sizes.thumbnail.url+\'" alt="" />\');
            } else if (att.url) {
                $prev.append(\'<img src="\'+att.url+\'" alt="" />\');
            }
        });
        frame.open();
    });
    $(\'#jprm-labels-table\').on(\'click\', \'.jprm-icon-remove\', function(e){
        e.preventDefault();
        var $cell = $(this).closest(\'.icon-cell\');
        $cell.find(\'.icon-id\').val(\'0\');
        $cell.find(\'.jprm-icon-preview\').empty();
        collectRows();
    });
    // Auto-slug when editing label if slug empty
    $(\'#jprm-labels-table\').on(\'input\', \'input.label\', function(){
        var $tr = $(this).closest(\'tr\');
        var $slug = $tr.find(\'input.slug\');
        if ( $.trim($slug.val()) === \'\' ) {
            $slug.val( slugify( $(this).val() ) );
        }
    });
    // keep option up-to-date
    $(document).on(\'input change\', \'#jprm-labels-table input\', collectRows);
    collectRows();
});
', 'after');
        }
    }
});


// Public filter that widgets can use:
function jprm_get_price_label_full_map() {
    // result: [ 'slug' => [ 'label' => 'Large', 'icon_id' => 123 ] ]
    $map = jprm_get_price_label_map();
    return apply_filters( 'jprm_price_label_full_map', $map );
}


// JPRM shim: buttons for jprm_menu_item edit screen (no sorting)
add_action('admin_footer', function(){
    if ( ! function_exists('get_current_screen') ) return;
    $screen = get_current_screen();
    if ( ! $screen || ( isset($screen->post_type) ? $screen->post_type : '' ) !== 'jprm_menu_item' ) return;

    wp_enqueue_script('jquery');
    ?>
    <script type="text/javascript">
    (function($){
        // Helpers mirrored from your current inline script
        function syncRow($tr){
            var isCustom = $tr.find('select.label-select').val() === 'custom';
            $tr.find('input.label-custom').closest('td').toggle(isCustom);
            var en = $tr.find('input.enable').is(':checked');
            if(!en && $tr.index()>0){ $tr.addClass('jp-hidden'); } else { $tr.removeClass('jp-hidden'); }
        }
        function collect(){
            var out = [];
            var $tbody = $('#jprm-prices-table tbody');
            $tbody.find('tr').each(function(){
                var $tr = $(this);
                var row = {
                    label_custom: $tr.find('input.label-custom').val() || '',
                    amount: $tr.find('input.amount').val() || '',
                    hide_icon: $tr.find('input.hide-icon').is(':checked') ? 1 : 0
                };
                if (row.label_custom.length || row.amount.length){ out.push(row); }
            });
            $('#jprm_prices_v1').val(JSON.stringify(out));
        }

        // Initialize existing rows once
        $(function(){
            var $tbody = $('#jprm-prices-table tbody');
            if ($tbody.length){
                $tbody.find('tr').each(function(){ syncRow($(this)); });
                collect();
            }
        });

        // Rebind using delegated events so other scripts can't break them
        $(document)
            .off('change.jprmFix', '#jprm-prices-table select.label-select')
            .on('change.jprmFix', '#jprm-prices-table select.label-select', function(){
                syncRow($(this).closest('tr')); collect();
            })
            .off('change.jprmFix keyup.jprmFix', '#jprm-prices-table input')
            .on('change.jprmFix keyup.jprmFix', '#jprm-prices-table input', function(){ collect(); })
            .off('click.jprmFix', '#jprm-row-add')
            .on('click.jprmFix', '#jprm-row-add', function(e){
                e.preventDefault();
                var html = '<tr>'
                    + '<td><input type="checkbox" class="enable" checked /></td>'
                    + '<td class="label-td"><select class="label-select"><option value="">Select…</option><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option><option value="custom">Custom</option></select> <input type="text" class="label-custom regular-text" value="" placeholder="Custom label" /></td>'
                    + '<td><input type="text" class="amount regular-text" value="" placeholder="€ 7,50" /></td>'
                    + '<td><input type="checkbox" class="hide-icon" /></td>'
                    + '<td><a href="#" class="button button-secondary jprm-row-remove">Remove</a></td>'
                    + '</tr>';
                var $tbody = $('#jprm-prices-table tbody');
                $tbody.append(html);
                var $last = $tbody.find('tr:last');
                syncRow($last); collect();
            })
            .off('click.jprmFix', '.jprm-row-remove')
            .on('click.jprmFix', '.jprm-row-remove', function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
                collect();
            });
    })(jQuery);
    </script>
    <?php
}, 22);


// JPRM: Price Labels UI enhancements (preview + remove toggle + button alignment)
add_action('admin_footer', function(){
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ( $page !== 'jprm-price-labels' ) { return; }

    if ( function_exists('wp_enqueue_media') ) { wp_enqueue_media(); }
    wp_enqueue_script('jquery');
    ?>
    <style>
        .jprm-icon-preview{ display:inline-block; width:48px; min-height:48px; margin-right:8px; vertical-align:middle; }
        .jprm-icon-select, .jprm-icon-clear, .jprm-label-copy{ vertical-align:middle; }
        .jprm-icon-clear{ display:none; } /* hidden by default; JS shows when icon exists */
    </style>
    <script>
    (function($){
      if (window.__JPRM_PL_INIT__) return; window.__JPRM_PL_INIT__ = true;
      var CACHE = {};
      var frame = null;

      function getHidden($row){
        var $hid = $row.find('input.icon-id');
        if (!$hid.length) $hid = $row.find('input.icon_id');
        if (!$hid.length) $hid = $row.find('input[type="hidden"][name$="[icon_id]"], input[type="hidden"][name$="[icon]"]');
        return $hid;
      }
      function ensurePreview($row){
        var $prev = $row.find('.jprm-icon-preview');
        if (!$prev.length){
          var $cell = $row.find('td.icon-cell, td').last();
          $prev = $('<div class="jprm-icon-preview"></div>');
          $cell.prepend($prev);
        }
        return $prev;
      }
      function setPreview($prev, url){
        $prev.empty();
        if (url) $prev.append($('<img>', {src:url, alt:''}).css({maxWidth:'48px', height:'auto'}));
      }
      function hasIcon($row){
        var v = (getHidden($row).val() || '').toString().trim();
        if (v === '' || v === '0') return false;
        var n = parseInt(v, 10);
        return isNaN(n) ? !!v : n > 0;
      }
      function restoreFromId($row){
        var $hid = getHidden($row), id = parseInt(($hid.val()||'0'),10);
        if (!(id>0)) return;
        var $prev = ensurePreview($row);
        if ($prev.find('img').length) return;         // already visible
        if (CACHE[id]) { setPreview($prev, CACHE[id]); return; }
        if (window.wp && wp.media && typeof wp.media.attachment === 'function'){
          var att = wp.media.attachment(id);
          if (att){
            att.fetch().done(function(){
              var sizes = att.get('sizes') || (att.attributes && att.attributes.sizes) || {};
              var url = att.get('url') || (sizes.thumbnail && sizes.thumbnail.url) || (sizes.medium && sizes.medium.url) || '';
              if (url){ CACHE[id] = url; setPreview($prev, url); }
            });
          }
        }
      }
      function refreshRow($row, knownUrl){
        var $prev = ensurePreview($row);
        var $clr  = $row.find('.jprm-icon-clear');
        // Ensure Copy button exists and looks like a WP button
        if (!$row.find('.jprm-label-copy').length){
          $('<button type="button" class="button button-secondary jprm-label-copy">Copy</button>').appendTo($row.find('td').last());
        }
        if (hasIcon($row)){
          if (knownUrl) setPreview($prev, knownUrl); else restoreFromId($row);
          $clr.show();
        } else {
          setPreview($prev, '');
          $clr.hide();
        }
      }

      function getFrame(){
        if (frame && frame.state()){
          // Reconfigure title/button on reuse to avoid stale labels
          try {
            frame.options.title = 'Select Icon';
            if (frame.options.button) frame.options.button.text = 'Select Icon';
          } catch(e){}
          return frame;
        }
        frame = wp.media({
          title: 'Select Icon',
          button: { text: 'Select Icon' },
          library: { type: 'image' },
          multiple: false
        });
        return frame;
      }

      function bindExisting($tb){
        // Remove any prior click handlers on existing buttons then bind ours
        $tb.find('.jprm-icon-select').off('click').on('click.jprmIconSel', function(e){
          e.preventDefault(); e.stopImmediatePropagation();
          var $row = $(this).closest('tr');
          var $hid = getHidden($row);
          var f = getFrame();
          f.off('select').once('select', function(){
            var att = f.state().get('selection').first().toJSON();
            $hid.val(att.id).trigger('change');
            if (att.url){ CACHE[parseInt(att.id,10)] = att.url; }
            refreshRow($row, att.url);
          });
          f.open();
        });
        $tb.find('.jprm-icon-clear').off('click').on('click.jprmIconClr', function(e){
          e.preventDefault(); e.stopImmediatePropagation();
          var $row = $(this).closest('tr');
          var $hid = getHidden($row);
          $hid.val('0').trigger('change');
          refreshRow($row);
        });
      }

      function bindDelegated(){
        // Also bind delegated for any rows added later
        $(document).off('click.jprmIconSel', '.jprm-icon-select').on('click.jprmIconSel', '.jprm-icon-select', function(e){
          e.preventDefault(); e.stopImmediatePropagation();
          var $row = $(this).closest('tr');
          var $hid = getHidden($row);
          var f = getFrame();
          f.off('select').once('select', function(){
            var att = f.state().get('selection').first().toJSON();
            $hid.val(att.id).trigger('change');
            if (att.url){ CACHE[parseInt(att.id,10)] = att.url; }
            refreshRow($row, att.url);
          });
          f.open();
        });
        $(document).off('click.jprmIconClr', '.jprm-icon-clear').on('click.jprmIconClr', '.jprm-icon-clear', function(e){
          e.preventDefault(); e.stopImmediatePropagation();
          var $row = $(this).closest('tr');
          var $hid = getHidden($row);
          $hid.val('0').trigger('change');
          refreshRow($row);
        });
        $(document).off('click.jprmLblCopy', '.jprm-label-copy').on('click.jprmLblCopy', '.jprm-label-copy', function(e){
          e.preventDefault(); e.stopImmediatePropagation();
          var $row = $(this).closest('tr');
          var $clone = $row.clone(true, true);
          $row.after($clone);
          refreshRow($clone);
        });
      }

      function init(){
        var $tb = $('#jprm-labels-table tbody');
        if (!$tb.length) return;
        $tb.find('tr').each(function(){ refreshRow($(this)); });
        bindExisting($tb);
        bindDelegated();
      }

      $(init); // run once
    })(jQuery);
    </script>
    <?php
}, 24);

