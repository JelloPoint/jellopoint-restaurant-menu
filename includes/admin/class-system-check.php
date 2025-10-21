<?php
/** Dev-only diagnostics for JelloPoint Restaurant Menu */
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('JPRM_System_Check') ) {
class JPRM_System_Check {

    protected static $registered = false;

    public static function init(){
        if ( ! defined('JPRM_DEV') || ! JPRM_DEV ) return;

        // Add pages under Tools and Settings so it's easy to find
        add_action('admin_menu', [__CLASS__, 'add_pages']);
        // Capture assets on the Menu Item edit screen
        add_action('admin_enqueue_scripts', [__CLASS__, 'capture_meta_assets'], 200);

        // Log that init ran
        error_log('[JPRM] System_Check::init ran (dev mode).');
    }

    public static function add_pages(){
        // Tools → JelloPoint System Check
        add_management_page(
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            'manage_options',
            'jprm-system-check',
            [__CLASS__, 'render_page']
        );

        // Settings → JelloPoint System Check (alternate entry)
        add_options_page(
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            __('JelloPoint System Check','jellopoint-restaurant-menu'),
            'manage_options',
            'jprm-system-check-settings',
            [__CLASS__, 'render_page']
        );

        self::$registered = true;
        error_log('[JPRM] System_Check pages registered (Tools + Settings).');
    }

    public static function capture_meta_assets(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) return;

        $scripts = $styles = [];
        if ( function_exists('wp_scripts') && wp_scripts() ) {
            $s = wp_scripts();
            $scripts = array_values(array_unique(array_merge($s->queue, $s->done)));
        }
        if ( function_exists('wp_styles') && wp_styles() ) {
            $st = wp_styles();
            $styles = array_values(array_unique(array_merge($st->queue, $st->done)));
        }
        update_option('jprm_last_meta_assets', [
            'time'    => current_time('mysql'),
            'scripts' => $scripts,
            'styles'  => $styles,
        ], false);
    }

    public static function render_page(){
        if ( ! current_user_can('manage_options') ) return;

        echo '<div class="wrap"><h1>JelloPoint System Check (Dev)</h1>';

        // Show dev + registration status
        echo '<p><strong>Dev Mode:</strong> ' . ( (defined('JPRM_DEV') && JPRM_DEV) ? 'ON' : 'OFF' ) . '</p>';
        echo '<p><strong>Menu registered this request:</strong> ' . ( self::$registered ? 'YES' : 'NO' ) . '</p>';

        // Which file drives the admin meta UI?
        $metaFile = 'N/A'; $metaHash='N/A'; $metaTime='N/A';
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
        echo '<table class="widefat striped" style="max-width:1000px"><tbody>';
        echo '<tr><th style="width:200px">Class</th><td>JPRM_Admin_MenuItem_Meta</td></tr>';
        echo '<tr><th>File</th><td><code>'.esc_html($metaFile).'</code></td></tr>';
        echo '<tr><th>Last modified</th><td>'.esc_html($metaTime).'</td></tr>';
        echo '<tr><th>md5</th><td><code>'.esc_html($metaHash).'</code></td></tr>';
        echo '</tbody></table>';

        // Metabox IDs
        global $wp_meta_boxes;
        $ids = [];
        if ( isset($wp_meta_boxes['jprm_menu_item']) ) {
            $normal = $wp_meta_boxes['jprm_menu_item']['normal']['default'] ?? [];
            foreach ($normal as $id => $box) { $ids[] = $id; }
        }
        echo '<h2>Metaboxes on jprm_menu_item (normal)</h2>';
        if ( empty($ids) ) {
            echo '<p><em>Open a Menu Item edit screen, then refresh.</em></p>';
        } else {
            echo '<ul>'; foreach ($ids as $id) echo '<li><code>'.esc_html($id).'</code></li>'; echo '</ul>';
        }

        // Assets captured
        $assets = get_option('jprm_last_meta_assets');
        echo '<h2>Last Captured Assets (Menu Item edit)</h2>';
        if ( empty($assets) ) {
            echo '<p><em>Open any Menu Item edit screen once, then refresh this page.</em></p>';
        } else {
            echo '<p><strong>Captured at:</strong> '.esc_html($assets['time']).'</p>';
            echo '<div style="display:flex;gap:40px;flex-wrap:wrap">';
            echo '<div><h3>Scripts</h3><ul>'; foreach (($assets['scripts'] ?? []) as $h) echo '<li><code>'.esc_html($h).'</code></li>'; echo '</ul></div>';
            echo '<div><h3>Styles</h3><ul>';  foreach (($assets['styles']  ?? []) as $h) echo '<li><code>'.esc_html($h).'</code></li>'; echo '</ul></div>';
            echo '</div>';
        }

        echo '<hr/><p><em>Dev-only page (controlled by <code>JPRM_DEV</code>).</em></p>';
        echo '</div>';
    }
}
}
JPRM_System_Check::init();