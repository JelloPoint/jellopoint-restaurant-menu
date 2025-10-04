<?php
/**
 * JPRM Price Labels Store (v2 storage) — FIX-ONLY DROP-IN
 * - Keeps storage in option: jprm_price_labels_v2
 * - Provides resolve() API used by frontend
 * - Admin page callback: render_admin_page() (no CPT capability checks)
 * - Safe save handler: handle_save()
 *
 * No visual/UX changes intended; only stability/capability fixes and guards.
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :
class JPRM_Labels_Store {
    const OPTION_KEY = 'jprm_price_labels_v2';

    /* ===================== Public API (frontend) ===================== */

    /** Return all saved labels as a normalized array. */
    public static function all() : array {
        $raw = get_option( self::OPTION_KEY, [] );
        if ( is_string( $raw ) ) {
            $json = json_decode( $raw, true );
            $raw = is_array( $json ) ? $json : [];
        }
        if ( ! is_array( $raw ) ) { $raw = []; }

        $out = [];
        foreach ( $raw as $row ) {
            $row = is_array($row) ? $row : [];
            $out[] = self::sanitize_row( $row );
        }
        return $out;
    }

    /** Map by numeric/string id. */
    public static function map_by_id() : array {
        $m = [];
        foreach ( self::all() as $row ) {
            $id = (string)($row['id'] ?? '');
            if ( $id !== '' ) { $m[$id] = $row; }
        }
        return $m;
    }

    /** Map by slug. */
    public static function map_by_slug() : array {
        $m = [];
        foreach ( self::all() as $row ) {
            $slug = (string)($row['slug'] ?? '');
            if ( $slug !== '' ) { $m[$slug] = $row; }
        }
        return $m;
    }

    /** Get row by reference (id or slug). */
    public static function get_by_ref( $ref ) {
        $ref = is_scalar($ref) ? (string)$ref : '';
        if ( $ref === '' ) { return null; }
        $by_id   = self::map_by_id();
        $by_slug = self::map_by_slug();
        if ( isset( $by_id[$ref] ) )   { return $by_id[$ref]; }
        if ( isset( $by_slug[$ref] ) ) { return $by_slug[$ref]; }
        return null;
    }

    /**
     * Resolve a label reference (id/slug) OR literal text into:
     *   ['label_text' => string, 'icon_id' => int]
     */
    public static function resolve( $ref_or_text ) : array {
        // If it's an existing id/slug use it, otherwise treat as literal text.
        $row = self::get_by_ref( $ref_or_text );
        if ( is_array( $row ) ) {
            $text = (string)($row['label_text'] ?? '');
            $icon = (int)($row['icon_id'] ?? 0);
            return [
                'label_text' => $text,
                'icon_id'    => $icon > 0 ? $icon : 0,
            ];
        }
        // Literal (unregistered) label text
        $text = is_scalar($ref_or_text) ? (string)$ref_or_text : '';
        return [
            'label_text' => $text,
            'icon_id'    => 0,
        ];
    }

    /* ===================== Admin wiring ===================== */

    /** Boot minimal admin hooks (no CPT-based caps). */
    public static function boot_admin_ui() : void {
        // Save handler early, independent of menu wiring.
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
    }

    /** Admin page renderer (submenu callback). */
    public static function render_admin_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jprm' ) );
        }

        $rows = self::all();
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

        echo '<table class="widefat striped" style="max-width:960px">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'ID', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Text', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Icon', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Active', 'jprm' ) . '</th>';
        echo '<th>' . esc_html__( 'Order', 'jprm' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty($rows) ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No labels yet.', 'jprm' ) . '</td></tr>';
        } else {
            foreach ( $rows as $i => $row ) {
                $id    = esc_attr( (string)($row['id'] ?? '') );
                $slug  = esc_attr( (string)($row['slug'] ?? '') );
                $text  = esc_attr( (string)($row['label_text'] ?? '') );
                $icon  = (int)($row['icon_id'] ?? 0);
                $act   = ! empty($row['active']);
                $order = (int)($row['order'] ?? 0);

                echo '<tr>';
                echo '<td><input type="text" name="jprm_labels['.$i.'][id]" value="'.$id.'" /></td>';
                echo '<td><input type="text" name="jprm_labels['.$i.'][slug]" value="'.$slug.'" /></td>';
                echo '<td><input type="text" name="jprm_labels['.$i.'][label_text]" value="'.$text.'" /></td>';
                echo '<td>';
                if ( $icon > 0 ) {
                    $img = wp_get_attachment_image( $icon, [24,24], false, [ 'style' => 'vertical-align:middle' ] );
                    if ( is_string($img) ) {
                        echo $img . ' ';
                    }
                }
                echo '<input type="number" min="0" step="1" name="jprm_labels['.$i.'][icon_id]" value="'.esc_attr($icon).'" />';
                echo '</td>';
                echo '<td><input type="checkbox" name="jprm_labels['.$i.'][active]" value="1" '. checked( $act, true, false ) .' /></td>';
                echo '<td><input type="number" name="jprm_labels['.$i.'][order]" value="'.esc_attr($order).'" /></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        // Add-row helper (kept simple so we don't change UX significantly)
        echo '<p><button type="button" class="button" onclick="jprmAddRow()">'.esc_html__('Add Row','jprm').'</button></p>';

        echo '<p><button type="submit" class="button button-primary">'.esc_html__('Save Labels','jprm').'</button></p>';
        echo '</form>';

        // Lightweight JS to append a blank row
        ?>
        <script>
        function jprmAddRow(){
            const tbody = document.querySelector('.widefat tbody');
            if(!tbody) return;
            const idx = tbody.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" name="jprm_labels['+idx+'][id]" value="" /></td>' +
                '<td><input type="text" name="jprm_labels['+idx+'][slug]" value="" /></td>' +
                '<td><input type="text" name="jprm_labels['+idx+'][label_text]" value="" /></td>' +
                '<td><input type="number" min="0" step="1" name="jprm_labels['+idx+'][icon_id]" value="0" /></td>' +
                '<td><input type="checkbox" name="jprm_labels['+idx+'][active]" value="1" /></td>' +
                '<td><input type="number" name="jprm_labels['+idx+'][order]" value="0" /></td>';
            tbody.appendChild(tr);
        }
        </script>
        <?php
        echo '</div>';
    }

    /** Save labels if posted from our page. */
    public static function handle_save() : void {
        if ( ! is_admin() ) { return; }
        if ( empty($_POST['jprm_labels_nonce']) || ! wp_verify_nonce( $_POST['jprm_labels_nonce'], 'jprm_labels_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows = isset($_POST['jprm_labels']) && is_array($_POST['jprm_labels']) ? $_POST['jprm_labels'] : [];
        $clean = [];
        foreach ( $rows as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );
            // Skip completely empty lines
            if (
                $row['id'] === '' &&
                $row['slug'] === '' &&
                $row['label_text'] === '' &&
                $row['icon_id'] === 0
            ) {
                continue;
            }
            $clean[] = $row;
        }

        update_option( self::OPTION_KEY, $clean );

        // Redirect back to avoid resubmits
        $url = wp_get_referer();
        if ( ! $url ) {
            $url = admin_url( 'admin.php?page=jprm-price-labels' );
        }
        $url = add_query_arg( 'updated', 1, $url );
        wp_safe_redirect( $url );
        exit;
    }

    /* ===================== Internals ===================== */

    protected static function sanitize_row( array $row ) : array {
        $id    = isset($row['id']) ? (string)$row['id'] : '';
        $slug  = isset($row['slug']) ? sanitize_title( (string)$row['slug'] ) : '';
        $text  = isset($row['label_text']) ? wp_kses_post( (string)$row['label_text'] ) : '';
        $icon  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
        $active= ! empty($row['active']) ? true : false;
        $order = isset($row['order']) ? (int)$row['order'] : 0;
        return [
            'id'         => $id,
            'slug'       => $slug,
            'label_text' => $text,
            'icon_id'    => $icon,
            'active'     => $active,
            'order'      => $order,
        ];
    }
}

// Boot minimal admin pieces
JPRM_Labels_Store::boot_admin_ui();
endif;
?>