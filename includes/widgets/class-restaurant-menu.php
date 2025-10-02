<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use JelloPoint\RestaurantMenu\Data\Price_Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Restaurant Menu Widget (reads ONLY 'jprm_price' v3).
 * Preserves the front-end layout classes you validated earlier.
 */
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

        // Qty label/icon presentation & position (applies to both single and multi)
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

        /* ====== Static Items (unchanged) ====== */
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

    protected function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset( $s['items'] ) ? $s['items'] : [];
        echo '<ul class="jp-menu">';
        foreach ( $items as $item ) { $this->render_static_item( $item ); }
        echo '</ul>';
        $this->print_inline_layout_css();
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

    protected function render_dynamic( array $s ) {
        // Ensure helpers available
        $schema_file = dirname( __DIR__ ) . '/data/class-price-schema.php';
        if ( file_exists( $schema_file ) ) { require_once $schema_file; }
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
            $cfg = Price_Schema::from_post( $post_id );
            $title = get_the_title( $post_id );
            $desc  = get_the_excerpt( $post_id );
            if ( empty( $desc ) ) { $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40, '' ); }

            echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
            echo '  <div class="jp-menu__content">';
            if ( $title !== '' ) echo    '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo    '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '  </div>';

            echo '  <div class="jp-menu__pricegroup">';

            if ( Price_Schema::is_single( $cfg ) ) {
                $ref  = $cfg['label_ref'] ?? '';
                $hide = ! empty( $cfg['hide_icon'] );
                $res  = \JPRM_Labels_Store::resolve( $ref );
                $label_text = $res['label_text'];
                $icon_id    = $res['icon_id'];

                if ( $label_text === '' && ! $icon_id ) {
                    echo '    <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $cfg['price'] ) . '</span></div>';
                } else {
                    echo '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                    echo '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                    echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
                    echo '    </div>';
                }
            } else {
                foreach ( Price_Schema::iter_rows( $cfg ) as $row ) {
                    $res  = \JPRM_Labels_Store::resolve( (string)($row['label_ref'] ?? '') );
                    $label_text = $res['label_text'];
                    $icon_id    = $res['icon_id'];
                    $hide       = ! empty( $row['hide_icon'] );
                    $value      = (string) $row['value'];
                    if ( $label_text === '' && ! $icon_id && $value === '' ) continue;

                    echo '    <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                    echo '      <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                    echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                    echo '    </div>';
                }
            }

            echo '  </div>'; // .jp-menu__pricegroup
            echo '</div></li>';
        }
        echo '</ul>';
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

        // Auto-detect context slugs if nothing selected
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
}
