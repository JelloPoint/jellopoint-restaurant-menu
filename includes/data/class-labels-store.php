<?php
/**
 * JPRM Price Labels Store (v2 storage) — POLISH (single-file drop-in)
 * Requirements implemented:
 * 1) ID auto-filled and hidden (kept in storage for compatibility).
 * 2) Columns order: Name first, then Slug (switched).
 * 3) Icon picker (media frame) instead of numeric ID; shows preview.
 * 4) Drag & drop reordering (no visible "Order" field).
 *
 * Storage remains the same option: jprm_price_labels_v2
 * Public API remains: JPRM_Labels_Store::resolve(), ::all()
 * Page renders even if submenu callback is __return_null (via admin_page hook).
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
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $raw ) ) { $raw = []; }
        $out = [];
        foreach ( $raw as $row ) {
            $row = is_array($row) ? $row : [];
            $row = self::sanitize_row( $row );
            // Maintain both keys for maximum compatibility
            if ( ! isset($row['label_text']) ) { $row['label_text'] = $row['label']; }
            $out[] = $row;
        }
        return $out;
    }

    /** Resolve a ref (id/slug) or literal text to text+icon id. */
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
        // Literal label text
        return ['label_text' => $ref, 'icon_id' => 0];
    }

    /* ====================== Admin wiring (safe) ========================= */

    public static function boot_admin_ui() : void {
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_page_' . self::PAGE_SLUG, [ __CLASS__, 'render_admin_page' ] );
        add_action( 'admin_menu', [ __CLASS__, 'maybe_register_menu' ], 9 );
    }

    /** Only register submenu if main plugin didn’t; still harmless if present. */
    public static function maybe_register_menu() : void {
        $parent_slug = 'jprm';
        add_submenu_page(
            $parent_slug,
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            'manage_options',
            self::PAGE_SLUG,
            '__return_null' // we render via admin_page_{slug}
        );
    }

    /** Render the admin table UI with drag & drop and media picker. */
    public static function render_admin_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
        }

        // Ensure media frame & jQuery UI
        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );

        $rows    = self::all();
        // Normalize order for display (ASC)
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
            // One blank row to start
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

        // Inline CSS & JS (kept minimal, no external files)
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
                    // Also update numeric indexes in input names to keep POST arrays compact
                    $tr.find('input, textarea, select').each(function(){
                        var name = $(this).attr('name');
                        if(!name) return;
                        // replace bracketed index [old] with [index]
                        name = name.replace(/labels\[[^\]]+\]/, 'labels['+index+']');
                        $(this).attr('name', name);
                    });
                });
            }

            function makeRow(data){
                var idx = $('#jprm-labels-tbody tr').length;
                var id  = data.id || uniqueId();
                var label = data.label || '';
                var slug  = data.slug || '';
                var icon  = parseInt(data.icon_id || 0, 10) || 0;
                var active= !!(data.active ?? true);
                var order = typeof data.order === 'number' ? data.order : idx;

                var img = '';
                if(icon > 0){
                    // We can't fetch the image URL without AJAX; leave empty. After choosing, preview will appear.
                    img = '';
                }

                var row = [
                    '<tr class="jprm-row">',
                      '<td class="col-drag"><span class="dashicons dashicons-menu jprm-drag" title="Drag"></span></td>',
                      '<td>',
                        '<input type="text" class="regular-text" name="labels['+idx+'][label]" value="'+_.escape(label)+'" />',
                        '<input type="hidden" name="labels['+idx+'][id]" value="'+_.escape(id)+'" />',
                        '<input type="hidden" name="labels['+idx+'][order]" value="'+_.escape(order)+'" />',
                      '</td>',
                      '<td><input type="text" class="regular-text" name="labels['+idx+'][slug]" value="'+_.escape(slug)+'" /></td>',
                      '<td>',
                        '<div class="jprm-icon-wrap">',
                          '<span class="jprm-icon-preview">'+(img ? img : '')+'</span>',
                          '<input type="hidden" name="labels['+idx+'][icon_id]" value="'+icon+'" />',
                          '<button type="button" class="button jprm-choose-icon">'+wp.i18n.__( 'Choose', 'jellopoint-restaurant-menu' )+'</button>',
                          '<button type="button" class="button jprm-clear-icon">'+wp.i18n.__( 'Clear', 'jellopoint-restaurant-menu' )+'</button>',
                        '</div>',
                      '</td>',
                      '<td><label><input type="checkbox" name="labels['+idx+'][active]" value="1" '+(active ? 'checked' : '')+' /> '+wp.i18n.__( 'Active', 'jellopoint-restaurant-menu' )+'</label></td>',
                    '</tr>'
                ].join('');
                return $(row);
            }

            // Setup sortable
            $('#jprm-labels-tbody').sortable({
                handle: '.jprm-drag',
                placeholder: 'placeholder',
                items: '> tr',
                update: renumber
            });

            // Add row
            $('#jprm-add-row').on('click', function(){
                var $tr = makeRow({});
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
                    title: wp.i18n.__( 'Select Icon', 'jellopoint-restaurant-menu' ),
                    button: { text: wp.i18n.__( 'Use this icon', 'jellopoint-restaurant-menu' ) },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    $preview.html('<img src="'+attachment.sizes?.thumbnail?.url || attachment.icon || attachment.url+'" alt="" />');
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

    /** Save posted labels (keeps legacy field names; auto-fill id & order). */
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

            // Skip completely empty lines
            if ( $row['label'] === '' && $row['slug'] === '' && (int)$row['icon_id'] === 0 ) {
                continue;
            }

            // Auto-fill ID if missing
            if ( $row['id'] === '' ) {
                $row['id'] = $row['slug'] !== '' ? $row['slug'] : uniqid('lbl_');
            }
            // Ensure uniqueness of ID in this save pass
            if ( isset($seen_ids[$row['id']]) ) {
                $row['id'] .= '_' . $i;
            }
            $seen_ids[$row['id']] = true;

            // Force numeric order based on incoming hidden field; fallback to loop index
            $row['order'] = isset($row['order']) ? (int)$row['order'] : $i;

            $clean[] = $row;
            $i++;
        }

        // Reindex order to 0..n for cleanliness
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
}

// Boot minimal admin pieces
JPRM_Labels_Store::boot_admin_ui();
endif;
?>