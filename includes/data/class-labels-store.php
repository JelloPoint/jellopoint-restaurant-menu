<?php
/**
 * JPRM Price Labels Store (v2 storage) — POLISH v4
 *
 * Changes in this revision:
 * - Delete row button (trash icon)
 * - Clickable icon preview opens media frame; if empty shows a placeholder icon button
 * - Clear icon is an icon button (cross)
 * - Slug is hidden from UI (kept as hidden input). On save, if empty, auto from Name.
 *
 * Storage option remains: jprm_price_labels_v2
 * Public API unchanged; resolve() prefers 'label' for text.
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :
class JPRM_Labels_Store {
    const OPTION_KEY = 'jprm_price_labels_v2';
    const PAGE_SLUG  = 'jprm-price-labels';

    /* ================= Public API ================= */
    public static function all() : array {
        $raw = get_option( self::OPTION_KEY, [] );
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $raw ) ) { $raw = []; }
        $out = [];
        foreach ( $raw as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );
            if ( ! isset($row['label_text']) ) { $row['label_text'] = $row['label']; }
            $out[] = $row;
        }
        return $out;
    }

    public static function resolve( $ref_or_text ) : array {
        $ref = is_scalar($ref_or_text) ? (string)$ref_or_text : '';
        if ( $ref !== '' ) {
            $name_matches = [];
            foreach ( self::all() as $row ) {
                // Keep ID/slug matches authoritative, even if an earlier name matches.
                if ( $ref === (string) ( $row['label'] ?? '' ) ) {
                    $name_matches[] = $row;
                }
                $id   = (string)($row['id'] ?? '');
                $slug = (string)($row['slug'] ?? '');
                if ( $ref === $id || $ref === $slug ) {
                    $text = (string)($row['label'] ?? '');
                    if ( $text === '' ) { $text = (string)($row['label_text'] ?? ''); }
                    $icon = (int)($row['icon_id'] ?? 0);
                    $icon_url = (string)($row['icon_url'] ?? '');
                    return ['label_text' => $text, 'icon_id' => ($icon > 0 ? $icon : 0), 'icon_url' => $icon_url];
                }
            }
            // Legacy/custom references can contain the visible catalog name.
            // Ambiguous names stay plain text rather than choosing an arbitrary icon.
            if ( count( $name_matches ) === 1 ) {
                $match = $name_matches[0];
                return [
                    'label_text' => $ref,
                    'icon_id'    => max( 0, (int) ( $match['icon_id'] ?? 0 ) ),
                    'icon_url'   => (string) ( $match['icon_url'] ?? '' ),
                ];
            }
        }
        return ['label_text' => $ref, 'icon_id' => 0, 'icon_url' => ''];
    }

    /* ================= Admin wiring ================= */
    public static function boot_admin_ui() : void {
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_page_' . self::PAGE_SLUG, [ __CLASS__, 'render_admin_page' ] );
        add_action( 'admin_menu', [ __CLASS__, 'maybe_register_menu' ], 9 );
    }

    public static function enqueue_assets( $hook ) : void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only current-page selector.
        if ( self::PAGE_SLUG === $page ) {
            wp_enqueue_media();
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_style( 'jprm-price-labels', JPRM_PLUGIN_URL . 'assets/admin/price-labels.css', [], JPRM_VERSION );
            wp_enqueue_script( 'jprm-price-labels', JPRM_PLUGIN_URL . 'assets/admin/price-labels.js', [ 'jquery', 'jquery-ui-sortable', 'media-editor' ], JPRM_VERSION, true );
            wp_localize_script( 'jprm-price-labels', 'jprmPriceLabels', [
                'chooseIcon' => __( 'Choose icon', 'jellopoint-restaurant-menu' ),
                'drag'       => __( 'Drag', 'jellopoint-restaurant-menu' ),
                'clearIcon'  => __( 'Clear icon', 'jellopoint-restaurant-menu' ),
                'clear'      => __( 'Clear', 'jellopoint-restaurant-menu' ),
                'active'     => __( 'Active', 'jellopoint-restaurant-menu' ),
                'deleteRow'  => __( 'Delete row', 'jellopoint-restaurant-menu' ),
                'delete'     => __( 'Delete', 'jellopoint-restaurant-menu' ),
                'selectIcon' => __( 'Select Icon', 'jellopoint-restaurant-menu' ),
                'useIcon'    => __( 'Use this icon', 'jellopoint-restaurant-menu' ),
            ] );
        }
    }

    public static function maybe_register_menu() : void {
        $parent_slug = 'jprm'; // adjust if your top-level slug differs
        add_submenu_page(
            $parent_slug,
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            'manage_options',
            self::PAGE_SLUG,
            '__return_null'
        );
    }

    public static function render_admin_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
        }

        $rows    = self::all();
        usort( $rows, function($a,$b){ return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0); } );
        $updated = isset( $_GET['updated'] ) ? absint( wp_unslash( $_GET['updated'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect status.

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Price Labels', 'jellopoint-restaurant-menu' ) . '</h1>';

        if ( $updated ) {
            echo '<div class="notice notice-success is-dismissible"><p>'
               . esc_html__( 'Labels saved.', 'jellopoint-restaurant-menu' )
               . '</p></div>';
        }

        echo '<form method="post" action="">';
        wp_nonce_field( 'jprm_labels_save', 'jprm_labels_nonce' );

        echo '<p class="description">' . esc_html__( 'Drag rows to reorder. Click the icon to choose or clear. Use the trash to delete a row.', 'jellopoint-restaurant-menu' ) . '</p>';

        echo '<table class="widefat striped jprm-labels-table">';
        echo '<thead><tr>';
        echo '<th class="col-drag" style="width:34px"></th>';
        echo '<th>' . esc_html__( 'Name', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th>' . esc_html__( 'Icon', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th style="width:120px">' . esc_html__( 'Active', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th style="width:90px">' . esc_html__( 'Actions', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '</tr></thead><tbody id="jprm-labels-tbody">';

        // This is a form, not post content: preserve only the required input attributes.
        // Keep this allowlist local; never enable form fields in general post HTML.
        $allowed_html = wp_kses_allowed_html( 'post' );
        $allowed_html['input'] = array(
            'type' => true, 'class' => true, 'name' => true,
            'value' => true, 'checked' => true,
        );

        if ( empty($rows) ) {
            // row_html() escapes each stored value and returns the fixed admin row markup.
			echo wp_kses( self::row_html( 0, [
                'id' => '',
                'label' => '',
                'slug' => '',
                'icon_id' => 0,
                'icon_url' => '',
                'active' => true,
                'order' => 0,
			] ), $allowed_html );
        } else {
            foreach ( $rows as $i => $row ) {
                // row_html() escapes each stored value and returns the fixed admin row markup.
				echo wp_kses( self::row_html( $i, $row ), $allowed_html );
            }
        }

        echo '</tbody></table>';

        echo '<p><button type="button" class="button" id="jprm-add-row">'.esc_html__('Add Row','jellopoint-restaurant-menu').'</button></p>';
        echo '<p><button type="submit" class="button button-primary">'.esc_html__('Save Labels','jellopoint-restaurant-menu').'</button></p>';
        echo '</form>';

        echo '</div>';
    }

    /** Save posted labels. */
    public static function handle_save() : void {
        if ( ! is_admin() ) return;
        $nonce = isset( $_POST['jprm_labels_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['jprm_labels_nonce'] ) )
            : '';
        if ( ! wp_verify_nonce( $nonce, 'jprm_labels_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) return;

        $rows = isset( $_POST['labels'] ) && is_array( $_POST['labels'] )
			// sanitize_row() applies the context-specific sanitizer to every value below.
			? wp_unslash( $_POST['labels'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : [];
        $clean = [];
        $seen_ids = [];
        $i = 0;
        foreach ( $rows as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );

            // Skip empty lines
            if ( $row['label'] === '' && $row['slug'] === '' && (int)$row['icon_id'] === 0 && $row['icon_url'] === '' ) {
                continue;
            }

            // Auto-fill slug if missing
            if ( $row['slug'] === '' && $row['label'] !== '' ) {
                $row['slug'] = sanitize_title( $row['label'] );
            }

            // Auto-fill ID if missing; prefer slug
            if ( $row['id'] === '' ) {
                $row['id'] = $row['slug'] !== '' ? $row['slug'] : uniqid('lbl_');
            }

            // Ensure uniqueness of ID in this save pass
            if ( isset($seen_ids[$row['id']]) ) {
                $row['id'] .= '_' . $i;
            }
            $seen_ids[$row['id']] = true;

            // Ensure order present
            $row['order'] = isset($row['order']) ? (int)$row['order'] : $i;

            $clean[] = $row;
            $i++;
        }

        // Normalize order
        usort( $clean, function($a,$b){ return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0); } );
        foreach ( $clean as $k => $r ) { $clean[$k]['order'] = $k; }

        update_option( self::OPTION_KEY, $clean );

        $url = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        wp_safe_redirect( $url );
        exit;
    }

    

    /* ================= Internals ================= */
    protected static function sanitize_row( array $row ) : array {
        $id    = isset($row['id']) ? sanitize_key( (string)$row['id'] ) : '';
        $slug  = isset($row['slug']) ? sanitize_title( (string)$row['slug'] ) : '';
        $label = isset($row['label']) ? wp_kses_post( (string)$row['label'] ) : '';
        if ( $label === '' && isset($row['label_text']) ) {
            $label = wp_kses_post( (string)$row['label_text'] );
        }
        $icon  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
        $icon_url = isset($row['icon_url']) ? esc_url_raw( (string)$row['icon_url'] ) : '';
        $active= ! empty($row['active']) ? true : false;
        $order = isset($row['order']) ? (int)$row['order'] : 0;
        return [
            'id'     => $id,
            'slug'   => $slug,
            'label'  => $label,
            'icon_id'=> $icon,
            'icon_url'=> $icon_url,
            'active' => $active,
            'order'  => $order,
        ];
    }

    /** Render a single <tr>. */
    protected static function row_html( int $index, array $row ) : string {
		$id    = (string) ( $row['id'] ?? '' );
		$slug  = (string) ( $row['slug'] ?? '' );
		$label = (string) ( $row['label'] ?? '' );
		if ( $label === '' ) { $label = (string) ( $row['label_text'] ?? '' ); }
        $icon  = (int)($row['icon_id'] ?? 0);
		$icon_url = (string) ( $row['icon_url'] ?? '' );
        $act   = ! empty($row['active']);
        $order = (int)($row['order'] ?? $index);

        $preview = '';
        if ( $icon > 0 ) {
            $img = wp_get_attachment_image( $icon, [28,28], false );
            if ( is_string($img) ) { $preview = $img; }
        } elseif ( $icon_url !== '' ) {
            $preview = '<img src="' . esc_url( $icon_url ) . '" alt="" />';
        } else {
            $preview = '<span class="dashicons dashicons-format-image" title="'.esc_attr__('Choose icon','jellopoint-restaurant-menu').'"></span>';
        }

        ob_start();
        ?>
        <tr class="jprm-row">
            <td class="col-drag"><span class="dashicons dashicons-menu jprm-drag" title="<?php echo esc_attr__( 'Drag', 'jellopoint-restaurant-menu' ); ?>"></span></td>
            <td>
				<input type="text" class="regular-text" name="labels[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" />
				<input type="hidden" name="labels[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" />
				<input type="hidden" name="labels[<?php echo esc_attr( (string) $index ); ?>][order]" value="<?php echo esc_attr($order); ?>" />
				<input type="hidden" name="labels[<?php echo esc_attr( (string) $index ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" />
            </td>
            <td>
                <div class="jprm-icon-wrap">
					<span class="jprm-icon-preview" role="button" tabindex="0"><?php echo wp_kses_post( $preview ); ?></span>
					<input type="hidden" name="labels[<?php echo esc_attr( (string) $index ); ?>][icon_id]" value="<?php echo esc_attr($icon); ?>" />
					<input type="hidden" name="labels[<?php echo esc_attr( (string) $index ); ?>][icon_url]" value="<?php echo esc_url($icon_url); ?>" />
                    <button type="button" class="button jprm-icon-btn jprm-icon-clear" title="<?php echo esc_attr__('Clear icon','jellopoint-restaurant-menu'); ?>"><span class="dashicons dashicons-no"></span><span class="screen-reader-text"><?php echo esc_html__('Clear','jellopoint-restaurant-menu'); ?></span></button>
                </div>
            </td>
			<td><label><input type="checkbox" name="labels[<?php echo esc_attr( (string) $index ); ?>][active]" value="1" <?php checked( $act, true ); ?> /> <?php echo esc_html__( 'Active', 'jellopoint-restaurant-menu' ); ?></label></td>
            <td class="jprm-actions">
                <button type="button" class="button jprm-icon-btn jprm-row-delete" title="<?php echo esc_attr__('Delete row','jellopoint-restaurant-menu'); ?>"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text"><?php echo esc_html__('Delete','jellopoint-restaurant-menu'); ?></span></button>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
}

// Boot minimal admin pieces
JPRM_Labels_Store::boot_admin_ui();
endif;
?>
