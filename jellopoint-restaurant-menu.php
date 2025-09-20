<?php
/**0
 * Plugin Name: JelloPoint Restaurant Menu
 * Description: Elementor  widget for restaurant menus with dynamic CPT and multi-price support.
 * Version: 2.0.1
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'JPRM_VERSION' ) ) define( 'JPRM_VERSION', '1.3.2' );
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

require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
update_option( 'jprm_current_version', JPRM_VERSION );
JelloPoint\RestaurantMenu\Plugin::instance();


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
        // Top-level Jellopoint menu (safe fallback)
        if ( ! isset( $GLOBALS['admin_page_hooks']['jellopoint-admin'] ) ) {
            add_menu_page( 'Jellopoint', 'Jellopoint', 'manage_options', 'jellopoint-admin', function(){}, 'dashicons-index-card', 60 );
        }
        add_submenu_page(
            'jellopoint-admin',
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
                if ( empty($r['active']) ) continue;
                $label = isset($r['label']) ? wp_strip_all_tags( $r['label'] ) : '';
                if ( $label !== '' ) $out[] = $label;
            }
            if ( ! empty($out) ) return $out;
        }
    }
    // Fallback to old option
    $raw = get_option('jprm_price_labels', "Small\nMedium\nLarge");
    $lines = preg_split("/\r\n|\r|\n/", (string)$raw);
    $out = [];
    foreach ( $lines as $line ) {
        $t = trim( wp_strip_all_tags( $line ) );
        if ( $t !== '' ) { $out[] = $t; }
    }
    if ( empty($out) ) { $out = [ 'Small', 'Medium', 'Large' ]; }
    return $out;
}


function jprm_get_price_label_map() {
    $v2 = get_option('jprm_price_labels_v2', '');
    if ( $v2 ) {
        $rows = json_decode( $v2, true );
        if ( is_array($rows) ) {
            usort($rows, function($a,$b){ return intval($a['order'] ?? 0) <=> intval($b['order'] ?? 0); });
            $map = [];
            foreach ( $rows as $r ) {
                if ( empty($r['active']) ) continue;
                $label = isset($r['label']) ? wp_strip_all_tags( $r['label'] ) : '';
                $slug  = isset($r['slug']) ? sanitize_title( $r['slug'] ) : sanitize_title( $label );
                if ( $label !== '' && $slug !== '' ) $map[$slug] = $label;
            }
            if ( ! empty($map) ) return $map;
        }
    }
    $map = [];
    foreach ( jprm_get_price_label_presets() as $label ) {
        $map[ sanitize_title( $label ) ] = $label;
    }
    return $map;
}


function jprm_get_price_label_options() {
    return jprm_get_price_label_map();
}



function jprm_render_price_labels_page() {
    if ( ! current_user_can('manage_options') ) return;
    wp_enqueue_script('jquery-ui-sortable');
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
            <p class="description"><?php echo esc_html__( 'Manage preset price labels. Drag to reorder. These labels feed the dropdowns in the widget.', 'jellopoint-restaurant-menu' ); ?></p>
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
                ]; endif; foreach ( $rows as $r ) : ?>
                    <tr class="jprm-row">
                        <td class="drag">↕︎</td>
                        <td><input type="text" class="label" value="<?php echo esc_attr( $r['label'] ?? '' ); ?>" /></td>
                        <td><input type="text" class="slug" value="<?php echo esc_attr( $r['slug'] ?? '' ); ?>" /></td>
                        <td class="icon-cell">
                            <div class="jprm-icon-preview"><?php
                                $iid = intval( $r['icon_id'] ?? 0 );
                                if ( $iid ) {
                                    echo wp_get_attachment_image( $iid, 'thumbnail', false, [ 'style'=>'max-width:48px;height:auto;' ] );
                                }
                            ?></div>
                            <input type="hidden" class="icon-id" value="<?php echo esc_attr( intval( $r['icon_id'] ?? 0 ) ); ?>" />
                            <button type="button" class="button jprm-icon-select"><?php echo esc_html__( 'Select', 'jellopoint-restaurant-menu' ); ?></button>
                            <button type="button" class="button-link jprm-icon-clear"><?php echo esc_html__( 'Remove', 'jellopoint-restaurant-menu' ); ?></button>
                        </td>
                        <td style="text-align:center;"><input type="checkbox" class="active" <?php checked( ! empty($r['active']) ); ?> /></td>
                        <td><button type="button" class="button jprm-dup">Duplicate</button> <button type="button" class="button-link-delete jprm-del"><?php echo esc_html__( 'Delete', 'jellopoint-restaurant-menu' ); ?></button></td>
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
    // Always add a Settings submenu as a reliable entry point
    add_options_page(
        __('Restaurant Menu – Price Labels','jellopoint-restaurant-menu'),
        __('Price Labels (Restaurant Menu)','jellopoint-restaurant-menu'),
        'manage_options',
        'jprm-price-labels',
        'jprm_render_price_labels_page'
    );
}, 20);

// Admin menus for Price Labels
add_action('admin_menu', function() {
    // Add under Settings
    add_options_page(
        __('Restaurant Menu – Price Labels','jellopoint-restaurant-menu'),
        __('Price Labels (Restaurant Menu)','jellopoint-restaurant-menu'),
        'manage_options',
        'jprm-price-labels',
        'jprm_render_price_labels_page'
    );
    // Also add under JelloPoint root menu if present
    if ( isset( $GLOBALS['admin_page_hooks']['jellopoint-root'] ) ) {
        
    }
}, 20);

function jprm_get_price_label_full_map() {
    $v2 = get_option('jprm_price_labels_v2', '');
    $out = [];
    if ( $v2 ) {
        $rows = json_decode( $v2, true );
        if ( is_array($rows) ) {
            usort($rows, function($a,$b){ return intval($a['order'] ?? 0) <=> intval($b['order'] ?? 0); });
            foreach ( $rows as $r ) {
                if ( empty($r['active']) ) continue;
                $label = isset($r['label']) ? wp_strip_all_tags( $r['label'] ) : '';
                $slug  = isset($r['slug']) ? sanitize_title( $r['slug'] ) : sanitize_title( $label );
                $icon  = isset($r['icon_id']) ? absint( $r['icon_id'] ) : 0;
                if ( $label !== '' && $slug !== '' ) {
                    $out[$slug] = [ 'label' => $label, 'icon_id' => $icon ];
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
        if ( $id === 'settings_page_jprm-price-labels' || $id === 'jellopoint-root_page_jprm-price-labels' ) {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_add_inline_script('jquery-ui-sortable', '
jQuery(function($){
    function slugify(str){
        return (str || \'\').toString().toLowerCase()
            .replace(/[^a-z0-9\\s\\-]/g,\'\')
            .trim().replace(/\\s+/g,\'-\').replace(/\\-+/g,\'-\');
    }
    function collectRows(){
        var rows = [];
        $(\'#jprm-labels-table tbody tr.jprm-row\').each(function(i){
            var $tr = $(this);
            var label = $.trim($tr.find(\'input.label\').val());
            var slug  = $.trim($tr.find(\'input.slug\').val());
            var active= $tr.find(\'input.active\').is(\':checked\');
            var icon  = parseInt($tr.find(\'input.icon-id\').val() || \'0\', 10);
            if(!label){ return; }
            if(!slug){ slug = slugify(label); }
            rows.push({ id: \'pl-\'+i+\'-\'+Date.now(), label: label, slug: slug, active: active, icon_id: icon, order: i });
        });
        $(\'#jprm_price_labels_v2\').val( JSON.stringify(rows) );
    }
    $(\'#jprm-add-row\').on(\'click\', function(){
        var $row = $(\'<tr class="jprm-row">\\
            <td class="drag">↕︎</td>\\
            <td><input type="text" class="label" value="" /></td>\\
            <td><input type="text" class="slug" value="" /></td>\\
            <td class="icon-cell">\\
                <div class="jprm-icon-preview"></div>\\
                <input type="hidden" class="icon-id" value="0" />\\
                <button type="button" class="button jprm-icon-select">Select</button>\\
                <button type="button" class="button-link jprm-icon-clear">Remove</button>\\
            </td>\\
            <td style="text-align:center;"><input type="checkbox" class="active" checked /></td>\\
            <td><button type="button" class="button jprm-dup">Duplicate</button> <button type="button" class="button-link-delete jprm-del">Delete</button></td>\\
        </tr>\');
        $(\'#jprm-labels-table tbody\').append($row);
    });
    $(\'#jprm-labels-table\').on(\'click\', \'.jprm-del\', function(){
        $(this).closest(\'tr\').remove();
    });
    $(\'#jprm-labels-table\').on(\'click\', \'.jprm-dup\', function(){
        var $tr = $(this).closest(\'tr\');
        var $clone = $tr.clone();
        $(\'#jprm-labels-table tbody\').append($clone);
    });
    if ($.fn.sortable) {
        $(\'#jprm-labels-table tbody\').sortable({
            handle: \'.drag\',
            helper: function(e, tr){
                var $orig = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index){
                    $(this).width($orig.eq(index).width());
                });
                return $helper;
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
    $(\'#jprm-labels-table\').on(\'click\', \'.jprm-icon-clear\', function(e){
        e.preventDefault();
        var $cell = $(this).closest(\'.icon-cell\');
        $cell.find(\'input.icon-id\').val(\'0\');
        $cell.find(\'.jprm-icon-preview\').empty();
    });
    $(\'#jprm-price-labels-form\').on(\'submit\', function(){
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
});
', 'after');
        }
    }
});
