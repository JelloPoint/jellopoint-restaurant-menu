<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','card','food','price','prices','items' ]; }

    protected function register_controls() {
        $menu_options    = $this->get_terms_options( 'jprm_menu' );
        $section_options = $this->get_terms_options( 'jprm_section' );

        $this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

        $this->add_control( 'data_source', [
            'label'   => __( 'Source', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'dynamic',
            'options' => [
                'dynamic' => __( 'Dynamic (from Admin items)', 'jellopoint-restaurant-menu' ),
                'static'  => __( 'Static (manual items below)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'auto_context', [
            'label'        => __( 'Auto-detect current Menu/Section', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_menus', [
            'label'       => __( 'Menus (taxonomy: jprm_menu)', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'options'     => $menu_options,
            'default'     => [],
            'multiple'    => true,
            'label_block' => true,
            'condition'   => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_sections', [
            'label'       => __( 'Sections (taxonomy: jprm_section)', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'options'     => $section_options,
            'default'     => [],
            'multiple'    => true,
            'label_block' => true,
            'condition'   => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_orderby', [
            'label'     => __( 'Order By', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'menu_order',
            'options'   => [
                'menu_order' => __( 'Menu Order', 'jellopoint-restaurant-menu' ),
                'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
                'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
                'modified'   => __( 'Modified', 'jellopoint-restaurant-menu' ),
                'rand'       => __( 'Random', 'jellopoint-restaurant-menu' ),
            ],
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_order', [
            'label'     => __( 'Order', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'ASC',
            'options'   => [ 'ASC' => 'ASC', 'DESC' => 'DESC' ],
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_limit', [
            'label'     => __( 'Limit', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::NUMBER,
            'default'   => '',
            'min'       => 1,
            'step'      => 1,
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'hide_invisible', [
            'label'        => __( 'Hide invisible items', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => '',
            'condition'    => [ 'data_source' => 'dynamic' ],
        ] );

        // Qty label/icon presentation & position
        $this->add_control( 'label_presentation', [
            'label'   => __( 'Label Presentation (qty)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'text',
            'options' => [
                'text'      => __( 'Text', 'jellopoint-restaurant-menu' ),
                'icon'      => __( 'Icon Only', 'jellopoint-restaurant-menu' ),
                'icon_text' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'label_position', [
            'label'   => __( 'Label Position (qty)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'right',
            'options'  => [
                'left'  => __( 'Left (label | price)', 'jellopoint-restaurant-menu' ),
                'right' => __( 'Right (price | label)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->end_controls_section();

        /* Static items (unchanged) */
        $this->start_controls_section( 'section_static', [
            'label'     => __( 'Static Items', 'jellopoint-restaurant-menu' ),
            'condition' => [ 'data_source' => 'static' ],
        ] );
        $repeater = new Repeater();
        $repeater->add_control( 'item_title', [ 'label' => __( 'Title', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Menu Item', 'jellopoint-restaurant-menu' ) ] );
        $repeater->add_control( 'item_description', [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA, 'default' => '', 'rows' => 2 ] );
        $repeater->add_control( 'item_price', [ 'label' => __( 'Single Price', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT, 'default' => '' ] );
        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_title }}}',
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();
            if ( ! did_action( 'jprm/restaurant_menu_widget_inline_css' ) ) {
                do_action( 'jprm/restaurant_menu_widget_inline_css' );
                $this->print_inline_layout_css(true);
            }
            return;
        }
        $this->render_dynamic( $s );
    }

    /* ---------- STATIC ---------- */
    protected function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset( $s['items'] ) ? $s['items'] : [];
        echo '<ul class="jp-menu">';
        foreach ( $items as $item ) { $this->render_static_item( $item ); }
        echo '</ul>';
        $this->print_inline_layout_css();
    }

    protected function render_static_item( $item ) {
        $title = $item['item_title'] ?? '';
        $desc  = $item['item_description'] ?? '';
        $price = $item['item_price'] ?? '';

        echo '<li class="jp-menu__item">';
        echo '  <div class="jp-menu__inner">';
        echo '    <div class="jp-menu__content">';
        if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
        if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
        echo '    </div>';
        echo '    <div class="jp-menu__pricegroup">';
        if ( $price !== '' ) {
            echo '      <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $price ) . '</span></div>';
        }
        echo '    </div>';
        echo '  </div>';
        echo '</li>';
    }

    /* ---------- DYNAMIC ---------- */
    protected function render_dynamic( array $s ) {
        // labels store
        $labels_file = dirname( __DIR__ ) . '/data/class-labels-store.php';
        if ( file_exists( $labels_file ) ) { require_once $labels_file; }

        $items = $this->collect_dynamic_items( $s );
        if ( empty( $items ) ) { echo '<ul class="jp-menu"></ul>'; return; }

        if ( ! did_action( 'jprm/restaurant_menu_widget_inline_css' ) ) {
            do_action( 'jprm/restaurant_menu_widget_inline_css' );
            $this->print_inline_layout_css(true);
        }

        $presentation    = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'text';
        if ( $presentation === 'badge' ) $presentation = 'text';
        $label_order_cls = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $post_id ) {
            $title = get_the_title( $post_id );

            // Description: prefer explicit meta keys, then excerpt, then trimmed content
            $desc  = $this->first_non_empty_meta( $post_id, [
                '_jprm_desc','jprm_desc','description','item_description','_description','_jprm_item_desc','_jp_desc'
            ] );
            if ( $desc === '' ) {
                $desc = get_post_field( 'post_excerpt', $post_id, 'raw' );
                if ( $desc === '' ) {
                    $content = get_post_field( 'post_content', $post_id, 'raw' );
                    $desc = wp_trim_words( wp_strip_all_tags( $content ), 40, '' );
                }
            }

            $cfg = $this->safe_read_v3( $post_id );
            $has_v3 = ! empty( $cfg );

            echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
            echo '  <div class="jp-menu__content">';
            if ( $title !== '' ) echo    '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo    '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '  </div>';
            echo '  <div class="jp-menu__pricegroup">';

            if ( $has_v3 ) {
                if ( isset($cfg['mode']) && $cfg['mode'] === 'single' && ! empty($cfg['price']) ) {
                    $price = $this->sanitize_price_string( $cfg['price'] ); // <- unwrap nested JSON if needed
                    if ( $price !== '' ) {
                        $res = \JPRM_Labels_Store::resolve( (string)($cfg['label_ref'] ?? '') );
                        $label_text = $res['label_text'];
                        $icon_id    = $res['icon_id'];
                        $hide       = ! empty( $cfg['hide_icon'] );

                        if ( $label_text === '' && ! $icon_id ) {
                            echo '    <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $price ) . '</span></div>';
                        } else {
                            echo '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                            echo '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                            echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                            echo '    </div>';
                        }
                    }
                } elseif ( isset($cfg['mode']) && $cfg['mode'] === 'multi' && ! empty($cfg['rows']) ) {
                    foreach ( $cfg['rows'] as $row ) {
                        $value = isset($row['value']) ? $this->sanitize_price_string( $row['value'] ) : '';
                        if ( $value === '' ) continue;
                        $res  = \JPRM_Labels_Store::resolve( (string)($row['label_ref'] ?? '') );
                        $label_text = $res['label_text'];
                        $icon_id    = $res['icon_id'];
                        $hide       = ! empty( $row['hide_icon'] );

                        echo '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                        echo '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                        echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                        echo '    </div>';
                    }
                }
            } else {
                // Legacy fallback (parallel arrays + single price + single label)
                $this->render_legacy_prices( $post_id, $label_order_cls, $presentation );
            }

            echo '  </div>'; // pricegroup
            echo '</div></li>';
        }

        echo '</ul>';
    }

    /* ---------- helpers ---------- */

    /** Safely read v3 without ever echoing raw JSON. */
    protected function safe_read_v3( $post_id ) : array {
        $raw = get_post_meta( $post_id, 'jprm_price', true );
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) ) return [];
        $trim = trim( $raw );
        if ( $trim === '' ) return [];
        if ( $trim[0] !== '{' && $trim[0] !== '[' ) return [];
        $cfg = json_decode( $trim, true );
        return (json_last_error() === JSON_ERROR_NONE && is_array($cfg)) ? $cfg : [];
    }

    /** Remove nested JSON from price-like strings. */
    protected function sanitize_price_string( $s ) {
        if ( is_string($s) ) {
            $t = trim($s);
            if ( $t !== '' && ($t[0] === '{' || $t[0] === '[') ) {
                $inner = json_decode( $t, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array($inner) ) {
                    if ( isset($inner['price']) && is_scalar($inner['price']) ) return (string)$inner['price'];
                    if ( isset($inner['value']) && is_scalar($inner['value']) ) return (string)$inner['value'];
                }
                return ''; // drop corrupted value
            }
            return $t;
        }
        if ( is_numeric($s) ) return (string)$s;
        return '';
    }

    /** Legacy renderer: parallel arrays + single price + single label keys. */
    protected function render_legacy_prices( $post_id, $label_order_cls, $presentation ) {
        $labels_arr  = $this->to_array( get_post_meta( $post_id, '_jprm_price_labels', true ) );
        $amounts_arr = $this->to_array( get_post_meta( $post_id, '_jprm_price_amounts', true ) );
        $hide_arr    = $this->to_array( get_post_meta( $post_id, '_jprm_price_hideicons', true ) );

        $has_multi = ! empty( $amounts_arr ) || ! empty( $labels_arr );

        if ( $has_multi ) {
            $max = max( count($labels_arr), count($amounts_arr) );
            for ( $i = 0; $i < $max; $i++ ) {
                $label_ref = isset($labels_arr[$i]) ? (string)$labels_arr[$i] : '';
                $value     = isset($amounts_arr[$i]) ? (string)$amounts_arr[$i] : '';
                $hide      = ! empty( $hide_arr[$i] );
                if ( $value === '' ) continue;

                $res  = \JPRM_Labels_Store::resolve( $label_ref );
                $label_text = $res['label_text'];
                $icon_id    = $res['icon_id'];

                echo '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                echo '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                echo '    </div>';
            }
            return;
        }

        // SINGLE (legacy)
        $single = $this->first_scalar( $post_id, [
            '_jprm_price','price','_price','item_price','price_single','single_price',
            'jprm_item_price','_jprm_price_value','_jprm_single_price','_jp_price'
        ] );
        if ( $single === '' ) return;

        $single_label_ref = $this->first_scalar( $post_id, [
            '_jprm_single_label','single_label','price_label','jprm_single_label','jprm_price_label',
            '_jprm_label_single','label_single','_jprm_label_id','_jprm_label_key',
            'label_id','label_key','label_ref','label','preset','slug'
        ] );
        $single_hide_icon = $this->truthy( get_post_meta( $post_id, '_jprm_single_hide_icon', true ) )
                             || $this->truthy( get_post_meta( $post_id, 'single_hide_icon', true ) )
                             || $this->truthy( get_post_meta( $post_id, 'price_hide_icon', true ) );

        $res  = \JPRM_Labels_Store::resolve( $single_label_ref );
        $label_text = $res['label_text'];
        $icon_id    = $res['icon_id'];
        $hide       = $single_hide_icon;

        echo ( $label_text === '' && ! $icon_id )
            ? '    <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $single ) . '</span></div>'
            : '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">'
            . '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>'
            . '      <span class="jp-menu__value jp-col-price">' . esc_html( $single ) . '</span>'
            . '    </div>';
    }

    protected function render_label_html( $label_text, $presentation, $icon_id = 0, $hide_icon = false ) {
        $label_text = (string) $label_text;
        if ( $presentation !== 'text' && $presentation !== 'icon' && $presentation !== 'icon_text' ) {
            $presentation = 'text';
        }
        if ( $hide_icon ) $presentation = 'text';

        $icon_html = '';
        if ( $icon_id ) {
            $img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [
                'class' => 'jp-menu__icon',
                'alt'   => $label_text !== '' ? $label_text : '',
            ] );
            if ( is_string( $img ) && $img !== '' ) $icon_html = $img;
        }

        if ( $presentation === 'icon' ) {
            if ( $icon_html !== '' ) {
                return $icon_html . '<span class="screen-reader-text">' . esc_html( $label_text ) . '</span>';
            }
            return esc_html( $label_text );
        }

        if ( $presentation === 'icon_text' ) {
            if ( $icon_html !== '' ) {
                return $icon_html . ' ' . esc_html( $label_text );
            }
            return esc_html( $label_text );
        }

        return esc_html( $label_text );
    }

    /** Query posts by menus/sections, with optional auto-context. */
    protected function collect_dynamic_items( array $s ) : array {
        $menus    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? $s['query_menus']    : [];
        $sections = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? $s['query_sections'] : [];
        $orderby  = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order    = ! empty( $s['query_order'] )   ? sanitize_text_field( $s['query_order'] )   : 'ASC';
        $limit    = ( isset( $s['query_limit'] ) && $s['query_limit'] !== '' ) ? (int) $s['query_limit'] : -1;

        $auto = isset( $s['auto_context'] ) && $s['auto_context'] === 'yes';
        if ( $auto && empty( $menus ) && empty( $sections ) ) {
            $ctx = $this->detect_context_terms();
            if ( empty( $menus )    && ! empty( $ctx['menus'] ) )    { $menus    = $ctx['menus']; }
            if ( empty( $sections ) && ! empty( $ctx['sections'] ) ) { $sections = $ctx['sections']; }
        }

        $tax_query = [];
        if ( ! empty( $menus ) )    $tax_query[] = [ 'taxonomy' => 'jprm_menu',    'field' => $this->guess_tax_field($menus),    'terms' => $menus ];
        if ( ! empty( $sections ) ) $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => $this->guess_tax_field($sections), 'terms' => $sections ];
        if ( count( $tax_query ) > 1 ) $tax_query['relation'] = 'AND';

        $args = [
            'post_type'           => 'jprm_menu_item',
            'post_status'         => 'publish',
            'nopaging'            => ( $limit === -1 ),
            'posts_per_page'      => $limit,
            'orderby'             => $orderby,
            'order'               => $order,
            'ignore_sticky_posts' => true,
            'fields'              => 'ids',
        ];
        if ( ! empty( $tax_query ) ) $args['tax_query'] = $tax_query;

        $q = new \WP_Query( $args );
        return $q->posts;
    }

    protected function detect_context_terms() : array {
        $out = [ 'menus' => [], 'sections' => [] ];
        $qo = get_queried_object();
        if ( $qo instanceof \WP_Term ) {
            if ( $qo->taxonomy === 'jprm_menu' )    $out['menus'][] = $qo->slug;
            if ( $qo->taxonomy === 'jprm_section' ) $out['sections'][] = $qo->slug;
        }
        $post_id = get_the_ID();
        if ( $post_id ) {
            $terms_menu    = get_the_terms( $post_id, 'jprm_menu' );
            $terms_section = get_the_terms( $post_id, 'jprm_section' );
            if ( is_array( $terms_menu ) )    foreach ( $terms_menu as $t )    $out['menus'][]    = $t->slug;
            if ( is_array( $terms_section ) ) foreach ( $terms_section as $t ) $out['sections'][] = $t->slug;
        }
        $out['menus']    = array_values( array_unique( array_filter( $out['menus'] ) ) );
        $out['sections'] = array_values( array_unique( array_filter( $out['sections'] ) ) );
        return $out;
    }

    protected function guess_tax_field( $vals ) {
        foreach ( (array)$vals as $v ) {
            if ( ! is_numeric( $v ) || intval($v) != $v ) return 'slug';
        }
        return 'term_id';
    }

    protected function get_terms_options( $taxonomy ) {
        $opts = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $opts;
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return $opts;
        foreach ( $terms as $t ) $opts[ $t->slug ] = $t->name;
        return $opts;
    }

    protected function print_inline_layout_css( $tag = false ) {
        $css = '.jp-menu{list-style:none;margin:0;padding:0}'
             . '.jp-menu__item{margin:0 0 .9rem 0}'
             . '.jp-menu__inner{display:grid;grid-template-columns:1fr auto;gap:.4rem .8rem;align-items:start}'
             . '.jp-menu__content{min-width:0}'
             . '.jp-menu__title{margin:0}'
             . '.jp-menu__desc{opacity:.85}'
             . '.jp-menu__pricegroup{display:flex;flex-direction:column;gap:.25rem;text-align:right}'
             . '.jp-menu__price{display:flex;gap:.5rem;justify-content:flex-end;line-height:1.2}'
             . '.jp-menu__label{opacity:.9;white-space:nowrap}'
             . '.jp-menu__icon{width:1.1em;height:1.1em;vertical-align:-0.15em;border-radius:2px;display:inline-block}'
             . '.jp-menu__value{font-weight:600;white-space:nowrap}'
             . '.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}'
             . '.jp-col-label{justify-self:end}'
             . '.jp-col-price{justify-self:end}'
             . '.jp-price-row.jp-order--label-right .jp-col-label{order:2}'
             . '.jp-price-row.jp-order--label-right .jp-col-price{order:1}'
             . '.jp-price-row.jp-order--label-left .jp-col-label{order:1}'
             . '.jp-price-row.jp-order--label-left .jp-col-price{order:2}'
             . '.screen-reader-text{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}';
        echo '<style class="jprm-menu-inline-css">' . $css . '</style>';
    }

    /* ---------- tiny utils ---------- */
    protected function to_array( $v ) {
        if ( is_array( $v ) ) return $v;
        if ( is_string( $v ) ) {
            $j = json_decode( $v, true );
            if ( is_array( $j ) ) return $j;
            $m = maybe_unserialize( $v );
            if ( is_array( $m ) ) return $m;
            if ( strpos($v, ',') !== false ) return array_map( 'trim', explode(',', $v ) );
        }
        return [];
    }

    protected function first_non_empty_meta( $post_id, array $keys ) {
        foreach ( $keys as $k ) {
            $v = get_post_meta( $post_id, $k, true );
            if ( is_string($v) || is_numeric($v) ) {
                $sv = trim((string)$v);
                if ( $sv !== '' ) return $sv;
            }
        }
        return '';
    }

    protected function first_scalar( $post_id, array $keys ) {
        foreach ( $keys as $k ) {
            $v = get_post_meta( $post_id, $k, true );
            if ( is_string($v) || is_numeric($v) ) {
                $sv = trim((string)$v);
                if ( $sv !== '' ) return $sv;
            } elseif ( is_array($v) ) {
                if ( isset($v['formatted']) && $v['formatted'] !== '' ) return (string)$v['formatted'];
                if ( isset($v['value'])     && $v['value'] !== '' )     return (string)$v['value'];
                if ( isset($v['amount'])    && $v['amount'] !== '' )    return (string)$v['amount'];
                if ( isset($v['price'])     && $v['price'] !== '' )     return (string)$v['price'];
                if ( isset($v[0])           && $v[0] !== '' )           return (string)$v[0];
            }
        }
        return '';
    }

    protected function truthy( $v ) {
        return ($v === '1' || $v === 1 || $v === true || $v === 'yes' || $v === 'on');
    }
}
