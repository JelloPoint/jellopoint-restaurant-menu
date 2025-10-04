<?php
/**
 * Labels admin — manages only jprm_price_labels_v2.
 * Shows icon thumbnails and lets you pick icons via WP media library.
 */
if ( ! defined('ABSPATH') ) { exit; }

class JPRM_Labels_Store {

    const OPTION = 'jprm_price_labels_v2';
    const NONCE  = 'jprm_labels_save';
    const PARENT = \JelloPoint\RestaurantMenu\Plugin::PARENT_SLUG; // 'jprm_root'

    public static function init() : void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_jprm_save_labels', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    public static function register_menu() : void {
        add_submenu_page(
            self::PARENT,
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            __( 'Price Labels', 'jellopoint-restaurant-menu' ),
            'manage_options',
            'jprm-price-labels',
            [ __CLASS__, 'render_page' ],
            40
        );
    }

    public static function enqueue_admin_assets( $hook ) : void {
        // Load media frame + small CSS/JS on our page only
        if ( isset($_GET['page']) && $_GET['page'] === 'jprm-price-labels' ) {
            wp_enqueue_media();
            wp_add_inline_style( 'wp-admin', '.jprm-thumb{width:28px;height:28px;object-fit:contain;border-radius:3px;background:#fff;border:1px solid #ccd0d4;display:inline-block;vertical-align:middle}' );
            wp_add_inline_script( 'jquery-core', "
                jQuery(function($){
                    $(document).on('click','.jprm-pick-icon',function(e){
                        e.preventDefault();
                        var btn = $(this), row = btn.closest('tr');
                        var input = row.find('input.jprm-icon-id');
                        var img   = row.find('img.jprm-thumb');
                        var frame = wp.media({title:'Select Icon', multiple:false, library:{type:'image'}});
                        frame.on('select', function(){
                            var att = frame.state().get('selection').first().toJSON();
                            input.val(att.id);
                            if(att.sizes && att.sizes.thumbnail){ img.attr('src', att.sizes.thumbnail.url); }
                            else { img.attr('src', att.url); }
                        });
                        frame.open();
                    });
                    $(document).on('click','.jprm-clear-icon',function(e){
                        e.preventDefault();
                        var row = jQuery(this).closest('tr');
                        row.find('input.jprm-icon-id').val('0');
                        row.find('img.jprm-thumb').attr('src','');
                    });
                });
            " );
        }
    }

    public static function get_list() : array {
        $opt = get_option( self::OPTION );
        $list = is_string($opt) ? json_decode($opt, true) : ( is_array($opt) ? $opt : [] );
        if ( ! is_array($list) ) $list = [];
        usort( $list, function($a,$b){
            $ao = isset($a['order']) ? (int)$a['order'] : 0;
            $bo = isset($b['order']) ? (int)$b['order'] : 0;
            return $ao <=> $bo;
        } );
        return $list;
    }

    public static function render_page() : void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( __('Sorry, you are not allowed to access this page.', 'jellopoint-restaurant-menu') );
        }

        $list = self::get_list();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Price Labels', 'jellopoint-restaurant-menu'); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="jprm_save_labels" />
                <?php wp_nonce_field( self::NONCE ); ?>

                <table class="widefat fixed striped" style="max-width:1100px;">
                    <thead>
                    <tr>
                        <th style="width:120px;"><?php esc_html_e('ID', 'jellopoint-restaurant-menu'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Slug', 'jellopoint-restaurant-menu'); ?></th>
                        <th><?php esc_html_e('Label', 'jellopoint-restaurant-menu'); ?></th>
                        <th style="width:150px;"><?php esc_html_e('Icon', 'jellopoint-restaurant-menu'); ?></th>
                        <th style="width:90px;"><?php esc_html_e('Active', 'jellopoint-restaurant-menu'); ?></th>
                        <th style="width:90px;"><?php esc_html_e('Order', 'jellopoint-restaurant-menu'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Actions', 'jellopoint-restaurant-menu'); ?></th>
                    </tr>
                    </thead>
                    <tbody id="jprm-labels-body">
                    <?php
                    if ( empty($list) ) {
                        $list = [
                            [ 'id'=>'pl-0','slug'=>'','label'=>'','icon_id'=>0,'active'=>1,'order'=>0 ]
                        ];
                    }
                    foreach ( $list as $i => $row ) :
                        $id     = isset($row['id']) ? (string)$row['id'] : '';
                        $slug   = isset($row['slug']) ? (string)$row['slug'] : '';
                        $label  = isset($row['label']) ? (string)$row['label'] : '';
                        $icon   = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
                        $active = ! empty( $row['active'] );
                        $order  = isset($row['order']) ? (int)$row['order'] : 0;
                        $thumb  = $icon ? wp_get_attachment_image_url( $icon, 'thumbnail' ) : '';
                        ?>
                        <tr>
                            <td><input type="text" name="labels[<?php echo (int)$i; ?>][id]" value="<?php echo esc_attr($id); ?>" style="width:110px;" /></td>
                            <td><input type="text" name="labels[<?php echo (int)$i; ?>][slug]" value="<?php echo esc_attr($slug); ?>" style="width:150px;" /></td>
                            <td><input type="text" name="labels[<?php echo (int)$i; ?>][label]" value="<?php echo esc_attr($label); ?>" /></td>
                            <td>
                                <img class="jprm-thumb" src="<?php echo esc_url($thumb ?: ''); ?>" alt="" />
                                <input type="number" class="jprm-icon-id" name="labels[<?php echo (int)$i; ?>][icon_id]" value="<?php echo (int)$icon; ?>" min="0" step="1" style="width:90px;margin:0 6px;" />
                                <a href="#" class="button button-secondary jprm-pick-icon"><?php esc_html_e('Pick', 'jellopoint-restaurant-menu'); ?></a>
                                <a href="#" class="button jprm-clear-icon"><?php esc_html_e('Clear', 'jellopoint-restaurant-menu'); ?></a>
                            </td>
                            <td><input type="checkbox" name="labels[<?php echo (int)$i; ?>][active]" value="1" <?php checked($active); ?> /></td>
                            <td><input type="number" name="labels[<?php echo (int)$i; ?>][order]" value="<?php echo (int)$order; ?>" step="1" style="width:80px;" /></td>
                            <td><button type="button" class="button jprm-del-row"><?php esc_html_e('Delete', 'jellopoint-restaurant-menu'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:12px;">
                    <button type="button" class="button button-secondary" id="jprm-add-row"><?php esc_html_e('Add Row', 'jellopoint-restaurant-menu'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Labels', 'jellopoint-restaurant-menu'); ?></button>
                </p>
            </form>
        </div>

        <script>
        (function(){
            const body = document.getElementById('jprm-labels-body');
            document.getElementById('jprm-add-row').addEventListener('click', () => {
                const idx = body.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" name="labels['+idx+'][id]" value="" style="width:110px;" /></td>'+
                    '<td><input type="text" name="labels['+idx+'][slug]" value="" style="width:150px;" /></td>'+
                    '<td><input type="text" name="labels['+idx+'][label]" value="" /></td>'+
                    '<td>'+
                    '  <img class="jprm-thumb" src="" alt="" /> '+
                    '  <input type="number" class="jprm-icon-id" name="labels['+idx+'][icon_id]" value="0" min="0" step="1" style="width:90px;margin:0 6px;" /> '+
                    '  <a href="#" class="button button-secondary jprm-pick-icon"><?php echo esc_js(__('Pick','jellopoint-restaurant-menu')); ?></a> '+
                    '  <a href="#" class="button jprm-clear-icon"><?php echo esc_js(__('Clear','jellopoint-restaurant-menu')); ?></a>'+
                    '</td>'+
                    '<td><input type="checkbox" name="labels['+idx+'][active]" value="1" checked /></td>'+
                    '<td><input type="number" name="labels['+idx+'][order]" value="'+idx+'" step="1" style="width:80px;" /></td>'+
                    '<td><button type="button" class="button jprm-del-row"><?php echo esc_js(__('Delete','jellopoint-restaurant-menu')); ?></button></td>';
                body.appendChild(tr);
            });
            body.addEventListener('click', (e) => {
                if (e.target && e.target.classList.contains('jprm-del-row')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }

    public static function handle_save() : void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( __('Sorry, you are not allowed to access this page.', 'jellopoint-restaurant-menu') );
        }
        check_admin_referer( self::NONCE );

        $labels_in = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : [];
        $out = [];
        foreach ( $labels_in as $row ) {
            $id   = isset($row['id']) ? sanitize_text_field( wp_unslash($row['id']) ) : '';
            $slug = isset($row['slug']) ? sanitize_text_field( wp_unslash($row['slug']) ) : '';
            $lab  = isset($row['label']) ? sanitize_text_field( wp_unslash($row['label']) ) : '';
            $ico  = isset($row['icon_id']) ? (int) $row['icon_id'] : 0;
            $act  = ! empty( $row['active'] );
            $ord  = isset($row['order']) ? (int) $row['order'] : 0;

            if ( $id === '' && $slug === '' && $lab === '' ) continue;

            $out[] = [
                'id'      => $id,
                'slug'    => $slug,
                'label'   => $lab,
                'icon_id' => $ico,
                'active'  => $act ? 1 : 0,
                'order'   => $ord,
            ];
        }

        update_option( self::OPTION, $out );
        wp_safe_redirect( add_query_arg( [ 'page' => 'jprm-price-labels', 'updated' => '1' ], admin_url('admin.php') ) );
        exit;
    }

    /** Optional resolver map */
    public static function map() : array {
        $map = [];
        foreach ( self::get_list() as $r ) {
            $id   = isset($r['id']) ? (string)$r['id'] : '';
            $slug = isset($r['slug']) ? (string)$r['slug'] : '';
            $text = isset($r['label']) ? (string)$r['label'] : '';
            $ico  = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;
            if ( $id !== '' )   $map[$id]   = ['text'=>$text,'icon_id'=>$ico];
            if ( $slug !== '' ) $map[$slug] = ['text'=>$text,'icon_id'=>$ico];
        }
        return $map;
    }
}

JPRM_Labels_Store::init();
