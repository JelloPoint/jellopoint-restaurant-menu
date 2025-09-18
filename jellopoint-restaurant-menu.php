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

require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
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
        if ( $id === 'settings_page_jprm-price-labels' || $id === 'jprm_admin_page_jprm-price-labels' || $id === 'jellopoint-root_page_jprm-price-labels' || $id === 'jellopoint-admin_page_jprm-price-labels' ) {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            wp_enqueue_scrip
        }