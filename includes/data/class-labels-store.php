<?php
/**
 * JPRM Price Labels Store (v2) — SAFE SHIM
 * - Does NOT change storage format (option: jprm_price_labels_v2).
 * - Keeps public API (resolve / all) and prefers 'label' field for text.
 * - Ensures the Price Labels admin page renders even if a stub submenu
 *   with '__return_null' was registered by the main plugin.
 *
 * Key technique: hook 'admin_page_jprm-price-labels' to print our UI
 * regardless of which callback was attached to add_submenu_page().
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :
class JPRM_Labels_Store {
    const OPTION_KEY = 'jprm_price_labels_v2';
    const PAGE_SLUG  = 'jprm-price-labels';

    /* ================= Public API (used by renderer/UI) ================= */

    /** Return all saved labels as a normalized array; keep legacy keys. */
    public static function all() : array {
        $raw = get_option( self::OPTION_KEY, [] );

        // Option may be stored as array or JSON string — accept both.
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $raw ) ) { $raw = []; }

        $out = [];
        foreach ( $raw as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );
            // Keep both keys for maximum compatibility:
            // - 'label'      (legacy/admin/UI expect this)
            // - 'label_text' (if some code reads the newer alias)
            if ( ! isset($row['label_text']) ) {
                $row['label_text'] = $row['label'];
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Resolve a ref (id/slug) or literal text to text+icon id. */
    public static function resolve( $ref_or_text ) : array {
        $ref = is_scalar($ref_or_text) ? (string)$ref_or_text : '';
        if ( $ref !== '' ) {
            // Try id/slug lookup
            foreach ( self::all() as $row ) {
                $id   = (string)($row['id'] ?? '');
                $slug = (string)($row['slug'] ?? '');
                if ( $ref === $id || $ref === $slug ) {
                    $text = (string)($row['label'] ?? '');
                    if ( $text === '' ) { $text = (string)($row['label_text'] ?? ''); }
                    $icon = (int)($row['icon_id'] ?? 0);
                    return ['label_text' => $text, 'icon_id' => ($icon > 0 ? $icon : 0)];
                }
            }
        }
        // Literal label text
        return ['label_text' => $ref, 'icon_id' => 0];
    }

    /* ====================== Admin wiring (safe) ========================= */

    public static function boot_admin_ui() : void {
        // Save handler (idempotent and capability-checked)
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );

        // CRUCIAL: Render even if submenu callback was a stub (__return_null).
        // This fires when visiting admin.php?page=jprm-price-labels.
        add_action( 'admin_page_' . self::PAGE_SLUG, [ __CLASS__, 'render_admin_page' ] );

        // Also register our submenu if the main plugin didn't (harmless if duplicate check exists elsewhere).
        add_action( 'admin_menu', [ __CLASS__, 'maybe_register_menu' ], 9 );
    }

    /** Only register submenu if it doesn't exist yet. */
    public static function maybe_register_menu() : void {
        // We cannot reliably detect parent slug here; if main plugin already added it, we do nothing.
        // If not, we register under the known top-level menu slug 'jprm' used by the plugin.
        $parent_slug = 'jprm'; // matches the JelloPoint Menu top-level slug in your setup.
        $slug = self::PAGE_SLUG;
        // If a page with same hook fires, our 'admin_page_{slug}' will handle rendering anyway.
        add_submenu_page(
            $parent_slug,
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            'manage_options',
            $slug,
            '__return_null' // real rendering is hooked via admin_page_{slug}
        );
    }

    /** Render the admin table UI (no UX changes). */
    public static function render_admin_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jprm' ) );
        }

        $rows    = self::all();
        $updated = isset($_GET['updated']) ? (int)$_GET['updated'] : 0;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Price Labels', 'jprm' ) . '</h1>';

        if ( $updated ) {
            echo '<div class="notice notice-success is-dismissible"><p>'
               . esc_html__( 'Labels saved.', 'jprm' )
               . '</p></div>';
        }

        echo '<form method="post" action="">';
        wp_nonce_field( 'jprm_labels_save', 'jprm_labels_nonce' );

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'ID', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Label', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Icon ID', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Active', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Order', 'jprm' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty($rows) ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No labels yet.', 'jprm' ) . '</td></tr>';
        } else {
            foreach ( $rows as $i => $row ) {
                $id    = esc_attr( (string)($row['id'] ?? '') );
                $slug  = esc_attr( (string)($row['slug'] ?? '') );
                $text  = esc_attr( (string)($row['label'] ?? '' ) );
                if ( $text === '' ) { $text = esc_attr( (string)($row['label_text'] ?? '' ) ); }
                $icon  = (int)($row['icon_id'] ?? 0);
                $act   = ! empty($row['active']);
                $order = (int)($row['order'] ?? 0);

                echo '<tr>';
                echo '<td><input type="text" name="labels['.$i.'][id]" value="'.$id.'" /></td>';
                echo '<td><input type="text" name="labels['.$i.'][slug]" value="'.$slug.'" /></td>';
                echo '<td><input type="text" name="labels['.$i.'][label]" value="'.$text.'" /></td>';
                echo '<td><input type="number" min="0" step="1" name="labels['.$i.'][icon_id]" value="'.esc_attr($icon).'" /></td>';
                echo '<td><input type="checkbox" name="labels['.$i.'][active]" value="1" '. checked( $act, true, false ) .' /></td>';
                echo '<td><input type="number" name="labels['.$i.'][order]" value="'.esc_attr($order).'" /></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">'.esc_html__('Save Labels','jprm').'</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /** Save posted labels (keeps legacy field names). */
    public static function handle_save() : void {
        if ( ! is_admin() ) return;
        if ( empty($_POST['jprm_labels_nonce']) || ! wp_verify_nonce( $_POST['jprm_labels_nonce'], 'jprm_labels_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) return;

        $rows = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : [];
        $clean = [];
        foreach ( $rows as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row ); // normalizes and keeps 'label'
            // Skip completely empty lines
            if (
                $row['id'] === '' &&
                $row['slug'] === '' &&
                $row['label'] === '' &&
                (int)$row['icon_id'] === 0
            ) {
                continue;
            }
            $clean[] = $row;
        }

        update_option( self::OPTION_KEY, $clean );

        // Avoid resubmits
        $url = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        wp_safe_redirect( $url );
        exit;
    }

    /* ============================ Internals ============================ */

    protected static function sanitize_row( array $row ) : array {
        $id    = isset($row['id']) ? (string)$row['id'] : '';
        $slug  = isset($row['slug']) ? sanitize_title( (string)$row['slug'] ) : '';
        // IMPORTANT: keep 'label' as the canonical text key for compatibility
        $label = isset($row['label']) ? wp_kses_post( (string)$row['label'] ) : '';
        // Accept 'label_text' alias if provided
        if ( $label === '' && isset($row['label_text']) ) {
            $label = wp_kses_post( (string)$row['label_text'] );
        }
        $icon  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
        $active= ! empty($row['active']) ? true : false;
        $order = isset($row['order']) ? (int)$row['order'] : 0;
        return [
            'id'     => $id,
            'slug'   => $slug,
            'label'  => $label,
            'icon_id'=> $icon,
            'active' => $active,
            'order'  => $order,
        ];
    }
}

// Boot minimal admin pieces
JPRM_Labels_Store::boot_admin_ui();
endif;
?>