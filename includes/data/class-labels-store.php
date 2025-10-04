<?php
/**
 * JPRM Price Labels Store (v2 storage) — POLISH (fixed)
 * Implements:
 * 1) ID hidden + auto-filled
 * 2) Columns: Name, Slug, Icon, Active (drag handle at left)
 * 3) Icon picker (media library) with preview
 * 4) Drag & drop ordering (hidden order field)
 *
 * Storage option: jprm_price_labels_v2
 * Public API stays compatible; page renders via admin_page_{slug}.
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :
class JPRM_Labels_Store {
    const OPTION_KEY = 'jprm_price_labels_v2';
    const PAGE_SLUG  = 'jprm-price-labels';

    /* ================= Public API (used by renderer/UI) ================= */

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
        return ['label_text' => $ref, 'icon_id' => 0];
    }

    /* ====================== Admin wiring (safe) ========================= */

    public static function boot_admin_ui() : void {
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_page_' . self::PAGE_SLUG, [ __CLASS__, 'render_admin_page' ] );
        add_action( 'admin_menu', [ __CLASS__, 'maybe_register_menu' ], 9 );
    }

    public static function maybe_register_menu() : void {
        $parent_slug = 'jprm';
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

        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );

        $rows    = self::all();
        usort( $rows, function($a,$b){ return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0); } );
        $updated = isset($_GET['updated']) ? (int)$_GET['updated'] : 0;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Price Labels', 'jellopoint-restaurant-menu' ) . '</h1>';

        if ( $updated ) {
            echo '<div class="notice notice-success is-dismissible"><p>'
               . esc_html__( 'Labels saved.', 'jellopoint-restaurant-menu' )
               . '</p></div>';
        }

        echo '<form method="post" action="">';
        wp_nonce_field( 'jprm_labels_save', 'jprm_labels_nonce' );

        echo '<p class="description">' . esc_html__( 'Drag rows to reorder. Click “Choose” to select an icon from the media library.', 'jellopoint-restaurant-menu' ) . '</p>';

        echo '<table class="widefat striped jprm-labels-table">';
        echo '<thead><tr>';
        echo '<th class="col-drag" style="width:34px"></th>';
        echo '<th>' . esc_html__( 'Name', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th>' . esc_html__( 'Icon', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '<th style="width:90px">' . esc_html__( 'Active', 'jellopoint-restaurant-menu' ) . '</th>';
        echo '</tr></thead><tbody id="jprm-labels-tbody">';

        if ( empty($rows) ) {
            echo self::row_html( 0, [
                'id' => '',
                'label' => '',
                'slug' => '',
                'icon_id' => 0,
                'active' => true,
                'order' => 0,
            ] );
        } else {
            foreach ( $rows as $i => $row ) {
                echo self::row_html( $i, $row );
            }
        }

        echo '</tbody></table>';

        echo '<p><button type="button" class="button" id="jprm-add-row">'.esc_html__('Add Row','jellopoint-restaurant-menu').'</button></p>';
        echo '<p><button type="submit" class="button button-primary">'.esc_html__('Save Labels','jellopoint-restaurant-menu').'</button></p>';
        echo '</form>';

        // Inline CSS & JS
        ?>
        <style>
        .jprm-labels-table .col-drag { width:34px; }
        .jprm-drag { cursor: move; display: inline-block; width: 20px; height: 20px; vertical-align: middle; }
        .jprm-icon-wrap { display:flex; align-items:center; gap:8px; }
        .jprm-icon-preview img { width:24px; height:24px; object-fit:contain; }
        .jprm-icon-preview { width:24px; height:24px; border:1px solid #ccd0d4; display:flex; align-items:center; justify-content:center; background:#fff; }
        .jprm-row { background:#fff; }
        .jprm-row.placeholder { background:#f6f7f7; }
        .jprm-hidden { display:none !important; }
        </style>
        <script>
        (function($){
            function uniqueId(){ return 'lbl_' + (Date.now().toString(36)) + '_' + Math.random().toString(36).slice(2,7); }

            function renumber(){
                $('#jprm-labels-tbody tr').each(function(index){
                    var $tr = $(this);
                    $tr.find('input[name$="[order]"]').val(index);
                    // Update the first bracket index in input names to maintain compact arrays
                    $tr.find('input, textarea, select').each(function(){
                        var name = $(this).attr('name');
                        if(!name) return;
                        name = name.replace(/labels\[[^\]]+\]/, 'labels['+index+']');
                        $(this).attr('name', name);
                    });
                });
            }

            function makeRow(){
                var idx = $('#jprm-labels-tbody tr').length;
                var id  = uniqueId();
                var row = [
                    '<tr class="jprm-row">',
                      '<td class="col-drag"><span class="dashicons dashicons-menu jprm-drag" title="Drag"></span></td>',
                      '<td>',
                        '<input type="text" class="regular-text" name="labels['+idx+'][label]" value="" />',
                        '<input type="hidden" name="labels['+idx+'][id]" value="'+id+'" />',
                        '<input type="hidden" name="labels['+idx+'][order]" value="'+idx+'" />',
                      '</td>',
                      '<td><input type="text" class="regular-text" name="labels['+idx+'][slug]" value="" /></td>',
                      '<td>',
                        '<div class="jprm-icon-wrap">',
                          '<span class="jprm-icon-preview"></span>',
                          '<input type="hidden" name="labels['+idx+'][icon_id]" value="0" />',
                          '<button type="button" class="button jprm-choose-icon">Choose</button>',
                          '<button type="button" class="button jprm-clear-icon">Clear</button>',
                        '</div>',
                      '</td>',
                      '<td><label><input type="checkbox" name="labels['+idx+'][active]" value="1" checked /> Active</label></td>',
                    '</tr>'
                ].join('');
                return $(row);
            }

            // Sortable
            $('#jprm-labels-tbody').sortable({
                handle: '.jprm-drag',
                placeholder: 'placeholder',
                items: '> tr',
                update: renumber
            });

            // Add row
            $('#jprm-add-row').on('click', function(){
                var $tr = makeRow();
                $('#jprm-labels-tbody').append($tr);
                renumber();
            });

            // Media frame for icon
            var frame;
            $(document).on('click', '.jprm-choose-icon', function(){
                var $wrap = $(this).closest('.jprm-icon-wrap');
                var $input = $wrap.find('input[type="hidden"][name*="[icon_id]"]');
                var $preview = $wrap.find('.jprm-icon-preview');
                if (frame) { frame.close(); }
                frame = wp.media({
                    title: 'Select Icon',
                    button: { text: 'Use this icon' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    var url = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ? attachment.sizes.thumbnail.url : (attachment.icon || attachment.url);
                    $preview.html('<img src="'+url+'" alt="" />');
                });
                frame.open();
            });

            // Clear icon
            $(document).on('click', '.jprm-clear-icon', function(){
                var $wrap = $(this).closest('.jprm-icon-wrap');
                $wrap.find('input[type="hidden"][name*="[icon_id]"]').val('0');
                $wrap.find('.jprm-icon-preview').empty();
            });

            // On submit, renumber to capture final order
            $('form').on('submit', function(){ renumber(); });
        })(jQuery);
        </script>
        <?php
        echo '</div>';
    }

    /** Save posted labels (hidden id & order respected). */
    public static function handle_save() : void {
        if ( ! is_admin() ) return;
        if ( empty($_POST['jprm_labels_nonce']) || ! wp_verify_nonce( $_POST['jprm_labels_nonce'], 'jprm_labels_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) return;

        $rows = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : [];
        $clean = [];
        $seen_ids = [];
        $i = 0;
        foreach ( $rows as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );

            if ( $row['label'] === '' && $row['slug'] === '' && (int)$row['icon_id'] === 0 ) {
                continue;
            }

            if ( $row['id'] === '' ) {
                $row['id'] = $row['slug'] !== '' ? $row['slug'] : uniqid('lbl_');
            }
            if ( isset($seen_ids[$row['id']]) ) {
                $row['id'] .= '_' . $i;
            }
            $seen_ids[$row['id']] = true;

            $row['order'] = isset($row['order']) ? (int)$row['order'] : $i;

            $clean[] = $row;
            $i++;
        }

        usort( $clean, function($a,$b){ return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0); } );
        foreach ( $clean as $k => $r ) { $clean[$k]['order'] = $k; }

        update_option( self::OPTION_KEY, $clean );

        $url = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        wp_safe_redirect( $url );
        exit;
    }

    /* ============================ Internals ============================ */

    protected static function sanitize_row( array $row ) : array {
        $id    = isset($row['id']) ? (string)$row['id'] : '';
        $slug  = isset($row['slug']) ? sanitize_title( (string)$row['slug'] ) : '';
        $label = isset($row['label']) ? wp_kses_post( (string)$row['label'] ) : '';
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

    /** Render a single <tr> for the table body. */
    protected static function row_html( int $index, array $row ) : string {
        $id    = esc_attr( (string)($row['id'] ?? '') );
        $slug  = esc_attr( (string)($row['slug'] ?? '') );
        $label = esc_attr( (string)($row['label'] ?? '') );
        if ( $label === '' ) { $label = esc_attr( (string)($row['label_text'] ?? '') ); }
        $icon  = (int)($row['icon_id'] ?? 0);
        $act   = ! empty($row['active']);
        $order = (int)($row['order'] ?? $index);

        $preview = '';
        if ( $icon > 0 ) {
            $img = wp_get_attachment_image( $icon, [24,24], false );
            if ( is_string($img) ) { $preview = $img; }
        }

        ob_start();
        ?>
        <tr class="jprm-row">
            <td class="col-drag"><span class="dashicons dashicons-menu jprm-drag" title="<?php echo esc_attr__( 'Drag', 'jellopoint-restaurant-menu' ); ?>"></span></td>
            <td>
                <input type="text" class="regular-text" name="labels[<?php echo $index; ?>][label]" value="<?php echo $label; ?>" />
                <input type="hidden" name="labels[<?php echo $index; ?>][id]" value="<?php echo $id; ?>" />
                <input type="hidden" name="labels[<?php echo $index; ?>][order]" value="<?php echo esc_attr($order); ?>" />
            </td>
            <td><input type="text" class="regular-text" name="labels[<?php echo $index; ?>][slug]" value="<?php echo $slug; ?>" /></td>
            <td>
                <div class="jprm-icon-wrap">
                    <span class="jprm-icon-preview"><?php echo $preview; ?></span>
                    <input type="hidden" name="labels[<?php echo $index; ?>][icon_id]" value="<?php echo esc_attr($icon); ?>" />
                    <button type="button" class="button jprm-choose-icon"><?php echo esc_html__( 'Choose', 'jellopoint-restaurant-menu' ); ?></button>
                    <button type="button" class="button jprm-clear-icon"><?php echo esc_html__( 'Clear', 'jellopoint-restaurant-menu' ); ?></button>
                </div>
            </td>
            <td><label><input type="checkbox" name="labels[<?php echo $index; ?>][active]" value="1" <?php checked( $act, true ); ?> /> <?php echo esc_html__( 'Active', 'jellopoint-restaurant-menu' ); ?></label></td>
        </tr>
        <?php
        return ob_get_clean();
    }
}

// Boot minimal admin pieces
JPRM_Labels_Store::boot_admin_ui();
endif;
?>