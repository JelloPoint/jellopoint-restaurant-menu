<?php
/** Dev-only diagnostics for JelloPoint Restaurant Menu */
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('JPRM_System_Check') ) {
class JPRM_System_Check {

    public static function init(){
        if ( ! JPRM_DEV ) return;

        // Add a Tools → JelloPoint System Check page (dev only)
        add_action('admin_menu', [__CLASS__, 'add_tools_page']);

        // Capture the *actual* assets used on the Menu Item edit screen
        add_action('admin_enqueue_scripts', [__CLASS__, 'capture_meta_assets'], 200);
    }

    public static function add_tools_page(){
        add_management_page(
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            'manage_options',
            'jprm-system-check',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Capture script/style handles the moment you're on the Menu Item edit screen.
     * Stored in an option so we can display it on the System Check page.
     */
    public static function capture_meta_assets(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) return;

        $scripts = [];
        $styles  = [];

        if ( function_exists('wp_scripts') ) {
            $wp_scripts = wp_scripts();
            if ( $wp_scripts ) {
                // queued + done (what WP plans to print + already printed)
                $scripts = array_values(array_unique(array_merge($wp_scripts->queue, $wp_scripts->done)));
            }
        }
        if ( function_exists('wp_styles') ) {
            $wp_styles = wp_styles();
            if ( $wp_styles ) {
                $styles = array_values(array_unique(array_merge($wp_styles->queue, $wp_styles->done)));
            }
        }

        update_option('jprm_last_meta_assets', [
            'time'    => current_time('mysql'),
            'scripts' => $scripts,
            'styles'  => $styles,
        ], false);
    }

    public static function render_page(){
        echo '<div class="wrap"><h1>JelloPoint System Check (Dev)</h1>';

        // 1) Which file renders the Menu Item editor?
        $metaFile = 'N/A';
        $metaHash = 'N/A';
        $metaTime = 'N/A';
        if ( class_exists('JPRM_Admin_MenuItem_Meta') ) {
            try {
                $ref = new \ReflectionClass('JPRM_Admin_MenuItem_Meta');
                $metaFile = $ref->getFileName();
                if ( $metaFile && file_exists($metaFile) ) {
                    $metaHash = md5_file($metaFile);
                    $metaTime = date('Y-m-d H:i:s', filemtime($metaFile));
                }
            } catch (\Throwable $e) {}
        }

        echo '<h2>Admin Meta Source</h2>';
        echo '<table class="widefat striped" style="max-width:1000px">';
        echo '<tbody>';
        echo '<tr><th style="width:200px">Class</th><td>JPRM_Admin_MenuItem_Meta</td></tr>';
        echo '<tr><th>File</th><td><code>'.esc_html($metaFile).'</code></td></tr>';
        echo '<tr><th>Last modified</th><td>'.esc_html($metaTime).'</td></tr>';
        echo '<tr><th>md5</th><td><code>'.esc_html($metaHash).'</code></td></tr>';
        echo '</tbody></table>';

        // 2) Any duplicate metaboxes?
        global $wp_meta_boxes;
        $dups = [];
        if ( isset($wp_meta_boxes['jprm_menu_item']) ) {
            $map = $wp_meta_boxes['jprm_menu_item'];
            $normal = $map['normal']['default'] ?? [];
            foreach ($normal as $id => $box) {
                $dups[] = $id;
            }
        }
        echo '<h2>Metaboxes Registered on jprm_menu_item (normal)</h2>';
        if ( empty($dups) ) {
            echo '<p><em>No metaboxes detected (unexpected). Open a Menu Item edit screen first.</em></p>';
        } else {
            echo '<ul>';
            foreach ($dups as $id) {
                echo '<li><code>'.esc_html($id).'</code></li>';
            }
            echo '</ul>';
            echo '<p><strong>Tip:</strong> You should see only the boxes we add: '
               . '<code>jprm_item_desc</code>, <code>jprm_price_meta</code>, <code>jprm_item_vis</code>. '
               . 'If you see legacy IDs like <code>jprm_menu_item_settings</code>, remove that code/file.</p>';
        }

        // 3) Assets that loaded on the last Menu Item edit screen
        $assets = get_option('jprm_last_meta_assets');
        echo '<h2>Last Captured Assets on Menu Item Edit Screen</h2>';
        if ( empty($assets) ) {
            echo '<p><em>Open any Menu Item edit screen once, then refresh this page.</em></p>';
        } else {
            echo '<p><strong>Captured at:</strong> '.esc_html($assets['time']).'</p>';
            echo '<div style="display:flex;gap:40px;flex-wrap:wrap">';
            echo '<div><h3>Scripts</h3><ul>';
            foreach (($assets['scripts'] ?? []) as $h) echo '<li><code>'.esc_html($h).'</code></li>';
            echo '</ul></div>';
            echo '<div><h3>Styles</h3><ul>';
            foreach (($assets['styles'] ?? []) as $h) echo '<li><code>'.esc_html($h).'</code></li>';
            echo '</ul></div>';
            echo '</div>';
            echo '<p><strong>Tip:</strong> If you see unexpected handles here (legacy admin JS/CSS), dequeue them where they’re being added or keep the guard you already have in <code>class-admin-menuitem-meta.php</code>.</p>';
        }

        echo '<hr/><p><em>Dev-only page. Controlled by <code>JPRM_DEV</code>.</em></p>';
        echo '</div>';
    }
}
}
JPRM_System_Check::init();