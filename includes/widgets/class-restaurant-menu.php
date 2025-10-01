<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * - Dynamic items (CPT jprm_menu_item) with Menu/Section taxonomy filters
 * - Static items (manual) as fallback
 * - Auto-detect current Menu/Section context when not explicitly selected
 * - Robust meta resolution for NEW admin storage (multiple candidate keys + JSON/serialized support)
 * - Preserves stable HTML wrappers for multiple-price alignment
 */
class Restaurant_Menu extends Widget_Base {

    /* =========================
     * Widget meta
     * ========================= */
    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'JelloPoint Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-price-list'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','price list','list','food','drink' ]; }

    /* =========================
     * Controls
     * ========================= */
    protected function register_controls() {

        // Build options for Menus/Sections selectors
        $menu_options    = $this->get_terms_options( 'jprm_menu' );
        $section_options = $this->get_terms_options( 'jprm_section' );

        /*  ===== Source ===== */
        $this->start_controls_section( 'section_source', [
            'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

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

        // Dynamic query controls
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
            'options'   => [
                'ASC'  => 'ASC',
                'DESC' => 'DESC',
            ],
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_limit', [
            'label'     => __( 'Items Limit', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::NUMBER,
            'default'   => -1,
            'min'       => -1,
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

        // Presentation
        $this->add_control( 'label_presentation', [
            'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'text',
            'options' => [
                'text'  => __( 'Text', 'jellopoint-restaurant-menu' ),
                'badge' => __( 'Badge', 'jellopoint-restaurant-menu' ),
                'icon'  => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'label_position', [
            'label'   => __( 'Label Position', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'right',
            'options' => [
                'left'  => __( 'Left (label | price)', 'jellopoint-restaurant-menu' ),
                'right' => __( 'Right (price | label)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->end_controls_section();

        /* ====== Static Items ====== */
        $this->start_controls_section( 'section_static', [
            'label'     => __( 'Static Items', 'jellopoint-restaurant-menu' ),
            'tab'       => Controls_Manager::TAB_CONTENT,
            'condition' => [ 'data_source' => 'static' ],
        ] );

        $repeater = new Repeater();
        $repeater->add_control( 'item_title', [
            'label'   => __( 'Title', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
        ] );
        $repeater->add_control( 'item_description', [
            'label'   => __( 'Description', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
            'rows'    => 2,
        ] );
        $repeater->add_control( 'item_price', [
            'label'   => __( 'Single Price', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $repeater->add_control( 'use_multi_prices', [
            'label'        => __( 'Enable Multiple Prices', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => '',
        ] );

        // Preset maps if provided by plugin (optional)
        $preset_map  = function_exists( 'jprm_get_price_label_map' ) ? (array) jprm_get_price_label_map() : [];
        $preset_full = function_exists( 'jprm_get_price_label_full_map' ) ? (array) jprm_get_price_label_full_map() : [];

        // Fixed 6 multiple-price rows
        for ( $i = 1; $i <= 6; $i++ ) {
            $repeater->add_control( "price{$i}_enable", [
                'label'        => sprintf( __( 'Enable Row %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [ 'use_multi_prices' => 'yes' ],
            ] );
            $repeater->add_control( "price{$i}_label_select", [
                'label'     => sprintf( __( 'Label %d (preset)', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'custom',
                'options'   => array_merge( [ 'custom' => __( 'Custom', 'jellopoint-restaurant-menu' ) ], $preset_map ),
                'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ],
            ] );
            $repeater->add_control( "price{$i}_label_custom", [
                'label'     => sprintf( __( 'Label %d (custom)', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '',
                'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes', "price{$i}_label_select" => 'custom' ],
            ] );
            $repeater->add_control( "price{$i}_hide_icon", [
                'label'        => sprintf( __( 'Hide Icon %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ],
            ] );
            $repeater->add_control( "price{$i}_amount", [
                'label'     => sprintf( __( 'Amount %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '',
                'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ],
            ] );
        }

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_title }}}',
            'condition'   => [ 'data_source' => 'static' ],
        ] );

        $this->end_controls_section();

        /*  ===== Style (minimal – keep deep styling in theme/CSS) ===== */
        $this->start_controls_section( 'section_style', [
            'label' => __( 'Style', 'jellopoint-restaurant-menu' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );
        $this->add_control( 'style_note', [
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __( 'Core layout uses stable HTML wrappers; adjust CSS in your theme if needed.', 'jellopoint-restaurant-menu' ),
        ] );
        $this->end_controls_section();
    }

    /* =========================
     * Static rendering
     * ========================= */
    protected function render_static_item( $item ) {
        $title = $item['item_title'] ?? '';
        $desc  = $item['item_description'] ?? '';
        $price = $item['item_price'] ?? '';

        $is_multi = ( isset( $item['use_multi_prices'] ) && $item['use_multi_prices'] === 'yes' );
        $rows = [];

        if ( $is_multi ) {
            $preset_map  = function_exists( 'jprm_get_price_label_map' ) ? (array) jprm_get_price_label_map() : [];
            $preset_full = function_exists( 'jprm_get_price_label_full_map' ) ? (array) jprm_get_price_label_full_map() : [];
            for ( $i = 1; $i <= 6; $i++ ) {
                if ( isset( $item["price{$i}_enable"] ) && $item["price{$i}_enable"] === 'yes' ) {
                    $sel   = $item["price{$i}_label_select"] ?? 'custom';
                    $label = ( $sel === 'custom' )
                        ? ( $item["price{$i}_label_custom"] ?? '' )
                        : ( $preset_full[ $sel ]['label'] ?? ( $preset_map[ $sel ] ?? $sel ) );
                    $amount = $item["price{$i}_amount"] ?? '';
                    if ( $label !== '' || $amount !== '' ) {
                        $rows[] = [ 'label' => $label, 'value' => $amount ];
                    }
                }
            }
        }

        echo '<li class="jp-menu__item">';
        echo '  <div class="jp-menu__inner">';

        echo '    <div class="jp-menu__content">';
        if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
        if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
        echo '    </div>';

        echo '    <div class="jp-menu__pricegroup">';

        if ( ! $is_multi && $price !== '' ) {
            echo '      <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $price ) . '</span></div>';
        }

        if ( $is_multi && ! empty( $rows ) ) {
            $label_order_class = 'jp-order--label-right';
            foreach ( $rows as $r ) {
                $label = $r['label'] ?? '';
                $value = $r['value'] ?? '';
                echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_class ) . '">';
                echo '          <span class="jp-menu__label jp-col-label">' . esc_html( $label ) . '</span>';
                echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                echo '      </div>';
            }
        }

        echo '    </div>'; // .jp-menu__pricegroup
        echo '  </div>';   // .jp-menu__inner
        echo '</li>';
    }

    protected function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset( $s['items'] ) ? $s['items'] : [];
        echo '<ul class="jp-menu">';
        foreach ( $items as $item ) { $this->render_static_item( $item ); }
        echo '</ul>';

        // Minimal CSS for alignment (printed inline)
        echo '<style>'
           . '.jp-menu{list-style:none;margin:0;padding:0}'
           . '.jp-menu__item{margin:0 0 .9rem 0}'
           . '.jp-menu__inner{display:grid;grid-template-columns:1fr auto;gap:.4rem .8rem;align-items:start}'
           . '.jp-menu__content{min-width:0}'
           . '.jp-menu__title{margin:0}'
           . '.jp-menu__desc{opacity:.85}'
           . '.jp-menu__pricegroup{display:flex;flex-direction:column;gap:.25rem;text-align:right}'
           . '.jp-menu__price{display:flex;gap:.5rem;justify-content:flex-end;line-height:1.2}'
           . '.jp-menu__label{opacity:.8;white-space:nowrap}'
           . '.jp-menu__value{font-weight:600;white-space:nowrap}'
           . '.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}'
           . '.jp-col-label{justify-self:end}'
           . '.jp-col-price{justify-self:end}'
           . '.jp-price-row.jp-order--label-right .jp-col-label{order:2}'
           . '.jp-price-row.jp-order--label-right .jp-col-price{order:1}'
           . '.jp-price-row.jp-order--label-left .jp-col-label{order:1}'
           . '.jp-price-row.jp-order--label-left .jp-col-price{order:2}'
           . '</style>';
    }

    /* =========================
     * Render (entry)
     * ========================= */
    protected function render() {
        $s = $this->get_settings_for_display();

        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();

            // Once-per-page inline CSS (also ensure in dynamic path)
            if ( ! did_action( 'jprm/restaurant_menu_widget_inline_css' ) ) {
                do_action( 'jprm/restaurant_menu_widget_inline_css' );
                echo '<style class="jprm-menu-inline-css">'
                   . '.jp-menu{list-style:none;margin:0;padding:0}'
                   . '.jp-menu__item{margin:0 0 .9rem 0}'
                   . '.jp-menu__inner{display:grid;grid-template-columns:1fr auto;gap:.4rem .8rem;align-items:start}'
                   . '.jp-menu__content{min-width:0}'
                   . '.jp-menu__title{margin:0}'
                   . '.jp-menu__desc{opacity:.85}'
                   . '.jp-menu__pricegroup{display:flex;flex-direction:column;gap:.25rem;text-align:right}'
                   . '.jp-menu__price{display:flex;gap:.5rem;justify-content:flex-end;line-height:1.2}'
                   . '.jp-menu__label{opacity:.8;white-space:nowrap}'
                   . '.jp-menu__value{font-weight:600;white-space:nowrap}'
                   . '.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}'
                   . '.jp-col-label{justify-self:end}'
                   . '.jp-col-price{justify-self:end}'
                   . '.jp-price-row.jp-order--label-right .jp-col-label{order:2}'
                   . '.jp-price-row.jp-order--label-right .jp-col-price{order:1}'
                   . '.jp-price-row.jp-order--label-left .jp-col-label{order:1}'
                   . '.jp-price-row.jp-order--label-left .jp-col-price{order:2}'
                   . '</style>';
            }
            return;
        }

        // Dynamic
        $this->render_dynamic( $s );
    }

    /* =========================
     * Dynamic rendering
     * ========================= */
    protected function render_dynamic( array $s ) {

        $items = $this->collect_dynamic_items( $s );

        if ( empty( $items ) ) {
            echo '<ul class="jp-menu"></ul>';
            return;
        }

        if ( ! did_action( 'jprm/restaurant_menu_widget_inline_css' ) ) {
            do_action( 'jprm/restaurant_menu_widget_inline_css' );
            echo '<style class="jprm-menu-inline-css">'
               . '.jp-menu{list-style:none;margin:0;padding:0}'
               . '.jp-menu__item{margin:0 0 .9rem 0}'
               . '.jp-menu__inner{display:grid;grid-template-columns:1fr auto;gap:.4rem .8rem;align-items:start}'
               . '.jp-menu__content{min-width:0}'
               . '.jp-menu__title{margin:0}'
               . '.jp-menu__desc{opacity:.85}'
               . '.jp-menu__pricegroup{display:flex;flex-direction:column;gap:.25rem;text-align:right}'
               . '.jp-menu__price{display:flex;gap:.5rem;justify-content:flex-end;line-height:1.2}'
               . '.jp-menu__label{opacity:.8;white-space:nowrap}'
               . '.jp-menu__value{font-weight:600;white-space:nowrap}'
               . '.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}'
               . '.jp-col-label{justify-self:end}'
               . '.jp-col-price{justify-self:end}'
               . '.jp-price-row.jp-order--label-right .jp-col-label{order:2}'
               . '.jp-price-row.jp-order--label-right .jp-col-price{order:1}'
               . '.jp-price-row.jp-order--label-left .jp-col-label{order:1}'
               . '.jp-price-row.jp-order--label-left .jp-col-price{order:2}'
               . '</style>';
        }

        $label_presentation = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'text';
        $label_order_class  = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $raw ) {
            $item = $this->normalize_item( $raw );

            $hide_invisible = isset( $s['hide_invisible'] ) && $s['hide_invisible'] === 'yes';
            if ( $hide_invisible && ! empty( $item['invisible'] ) ) {
                continue;
            }

            $title = $item['title'];
            $desc  = $item['description'];

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            // Single price
            if ( empty( $item['prices'] ) && $item['price'] !== '' ) {
                echo '      <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $item['price'] ) . '</span></div>';
            }

            // Multiple prices
            if ( ! empty( $item['prices'] ) ) {
                foreach ( $item['prices'] as $p ) {
                    $label = $p['label'];
                    $value = $p['value'];
                    if ( $label === '' && $value === '' ) continue;

                    echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_class ) . '">';
                    echo '          <span class="jp-menu__label jp-col-label">';
                    if ( $label_presentation === 'badge' && $label !== '' ) {
                        echo '<span class="jp-badge">' . esc_html( $label ) . '</span>';
                    } elseif ( $label_presentation === 'icon' && $label !== '' ) {
                        $icon = apply_filters( 'jprm/label/icon', '', $label );
                        if ( $icon ) echo '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
                        echo esc_html( $label );
                    } else {
                        echo esc_html( $label );
                    }
                    echo '          </span>';
                    echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                    echo '      </div>';
                }
            }

            echo '    </div>'; // .jp-menu__pricegroup
            echo '  </div>';   // .jp-menu__inner
            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * Collect dynamic items based on widget settings.
     * - CPT: jprm_menu_item
     * - Taxonomies: jprm_menu, jprm_section
     * - Auto-detect context when enabled and no terms selected
     * - Robust meta resolution for new admin keys/structures
     */
    protected function collect_dynamic_items( array $s ) {

        // Allow a provider to override completely
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) return $filtered;

        // Read control values
        $menus    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? array_map( 'intval', $s['query_menus'] )       : [];
        $sections = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? array_map( 'intval', $s['query_sections'] )    : [];
        $orderby  = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order    = ! empty( $s['query_order'] )   ? sanitize_text_field( $s['query_order'] )   : 'ASC';
        $limit    = ( isset( $s['query_limit'] ) && $s['query_limit'] !== '' ) ? (int) $s['query_limit'] : -1;

        // Auto-detect context if enabled and terms not chosen
        $auto = isset( $s['auto_context'] ) && $s['auto_context'] === 'yes';
        if ( $auto && empty( $menus ) && empty( $sections ) ) {
            $ctx = $this->detect_context_terms();
            if ( empty( $menus ) && ! empty( $ctx['menus'] ) ) {
                $menus = $ctx['menus'];
            }
            if ( empty( $sections ) && ! empty( $ctx['sections'] ) ) {
                $sections = $ctx['sections'];
            }
        }

        // Build tax_query
        $tax_query = [];
        if ( ! empty( $menus ) ) {
            $tax_query[] = [
                'taxonomy' => 'jprm_menu',
                'field'    => 'term_id',
                'terms'    => $menus,
            ];
        }
        if ( ! empty( $sections ) ) {
            $tax_query[] = [
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => $sections,
            ];
        }
        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        $args = [
            'post_type'           => 'jprm_menu_item',
            'post_status'         => 'publish',
            'nopaging'            => ( $limit === -1 ),
            'posts_per_page'      => $limit,
            'orderby'             => $orderby,
            'order'               => $order,
            'ignore_sticky_posts' => true,
        ];
        if ( ! empty( $tax_query ) ) $args['tax_query'] = $tax_query;

        // Let plugin adjust query if needed
        $args = apply_filters( 'jprm/widget/query_args', $args, $s, $this );

        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) {
            return [];
        }

        $out = [];
        while ( $q->have_posts() ) {
            $q->the_post();
            $post_id = get_the_ID();

            // title
            $title = get_the_title();

            // description (meta candidates -> excerpt -> clean content)
            $description = $this->read_first_nonempty_meta( $post_id, [
                '_jprm_desc','jprm_desc','_jprm_description','jprm_description','_description','description',
            ] );
            if ( $description === '' ) {
                $description = get_the_excerpt();
            }
            if ( $description === '' ) {
                $description = $this->clean_content_as_excerpt( get_post_field( 'post_content', $post_id ), 220 );
            }

            // single price (meta candidates)
            $single_price = $this->read_first_nonempty_meta( $post_id, [
                '_jprm_price','jprm_price','_price','price','_jprm_single_price','jprm_single_price',
            ] );

            // multiple prices (meta candidates; accept serialized/json/array)
            $multi_prices = $this->read_prices_array( $post_id, [
                '_jprm_prices','jprm_prices','_prices','prices','_jprm_price_rows','jprm_price_rows','_jprm_multi_prices','jprm_multi_prices',
            ] );

            // labels (optional, various keys)
            $labels = $this->read_labels_array( $post_id, [
                '_jprm_labels','jprm_labels','_labels','labels','_jprm_item_labels','jprm_item_labels',
            ] );

            // invisible flag (bool-ish)
            $invisible = $this->read_boolish_meta( $post_id, [
                '_jprm_invisible','jprm_invisible','_invisible','invisible','_jprm_hidden','jprm_hidden',
            ] );

            $out[] = [
                'ID'          => $post_id,
                'title'       => $title,
                'description' => $description,
                'price'       => $single_price,
                'prices'      => $multi_prices,
                'labels'      => $labels,
                'invisible'   => $invisible,
            ];
        }
        wp_reset_postdata();

        return $out;
    }

    /**
     * Try to infer current Menu/Section context.
     * - If viewing a jprm_menu or jprm_section term archive → use that term
     * - Else, if current post has these terms attached → use them
     * - Extensible via filter 'jprm/widget/detected_terms'
     */
    protected function detect_context_terms() {
        $out = [ 'menus' => [], 'sections' => [] ];

        $qo = get_queried_object();
        if ( $qo instanceof \WP_Term ) {
            if ( $qo->taxonomy === 'jprm_menu' ) {
                $out['menus'][] = (int) $qo->term_id;
            } elseif ( $qo->taxonomy === 'jprm_section' ) {
                $out['sections'][] = (int) $qo->term_id;
            }
        }

        $post_id = get_the_ID();
        if ( $post_id ) {
            $terms_menu    = get_the_terms( $post_id, 'jprm_menu' );
            $terms_section = get_the_terms( $post_id, 'jprm_section' );
            if ( is_array( $terms_menu ) )    foreach ( $terms_menu as $t )    { $out['menus'][]    = (int) $t->term_id; }
            if ( is_array( $terms_section ) ) foreach ( $terms_section as $t ) { $out['sections'][] = (int) $t->term_id; }
        }

        $out['menus']    = array_values( array_unique( $out['menus'] ) );
        $out['sections'] = array_values( array_unique( $out['sections'] ) );

        return apply_filters( 'jprm/widget/detected_terms', $out, $this );
    }

    /**
     * Normalize an item into a stable shape for rendering.
     */
    protected function normalize_item( array $raw ) {
        $item = [
            'ID'          => isset( $raw['ID'] ) ? (int) $raw['ID'] : 0,
            'title'       => isset( $raw['title'] ) ? (string) $raw['title'] : '',
            'description' => isset( $raw['description'] ) ? (string) $raw['description'] : '',
            'price'       => '',
            'prices'      => [],
            'labels'      => [],
            'invisible'   => ! empty( $raw['invisible'] ),
        ];

        if ( isset( $raw['price'] ) && $raw['price'] !== '' ) {
            $item['price'] = (string) $raw['price'];
        }

        if ( ! empty( $raw['prices'] ) && is_array( $raw['prices'] ) ) {
            $rows = [];
            foreach ( $raw['prices'] as $row ) {
                if ( is_array( $row ) ) {
                    $label = '';
                    $value = '';
                    if ( array_key_exists( 'label', $row ) || array_key_exists( 'value', $row ) ) {
                        $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                        $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                    } else {
                        $label = isset( $row[0] ) ? (string) $row[0] : '';
                        $value = isset( $row[1] ) ? (string) $row[1] : '';
                    }
                    if ( $label !== '' || $value !== '' ) {
                        $rows[] = [ 'label' => $label, 'value' => $value ];
                    }
                }
            }
            $item['prices'] = $rows;
        }

        if ( ! empty( $raw['labels'] ) && is_array( $raw['labels'] ) ) {
            $item['labels'] = array_values( array_filter( array_map( 'strval', $raw['labels'] ) ) );
        }

        return apply_filters( 'jprm/widget/normalize_item', $item, $raw, $this );
    }

    /**
     * Build term options for SELECT2 controls.
     */
    protected function get_terms_options( $taxonomy ) {
        $opts = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $opts;
        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return $opts;
        foreach ( $terms as $t ) {
            $opts[ (int) $t->term_id ] = $t->name;
        }
        return apply_filters( 'jprm/widget/term_options', $opts, $taxonomy, $this );
    }

    /* =========================
     * Meta helpers (robust readers)
     * ========================= */

    /**
     * Read first non-empty meta among candidate keys.
     * Accepts scalars/strings. If array/JSON/serialized is encountered for a scalar,
     * returns empty string (use dedicated readers for arrays).
     */
    protected function read_first_nonempty_meta( $post_id, array $candidates ) {
        foreach ( $candidates as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( $val === '' || $val === null ) continue;
            if ( is_scalar( $val ) ) {
                $s = (string) $val;
                if ( trim( $s ) !== '' ) return $s;
            }
        }
        return '';
    }

    /**
     * Read a boolean-ish meta among candidate keys.
     * Accepts '1','yes','true',1,true as truthy.
     */
    protected function read_boolish_meta( $post_id, array $candidates ) {
        foreach ( $candidates as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( $val === '' || $val === null ) continue;
            if ( is_bool( $val ) ) return $val;
            $s = strtolower( trim( (string) $val ) );
            if ( $s === '1' || $s === 'yes' || $s === 'true' ) return true;
            if ( $s === '0' || $s === 'no'  || $s === 'false' ) return false;
        }
        return false;
    }

    /**
     * Read labels array (flexible).
     * Accepts:
     *  - array of strings
     *  - array of arrays with 'label'
     *  - JSON string
     *  - serialized PHP
     */
    protected function read_labels_array( $post_id, array $candidates ) {
        $raw = $this->read_any_array_like_meta( $post_id, $candidates );
        if ( empty( $raw ) ) return [];
        $out = [];
        foreach ( $raw as $row ) {
            if ( is_array( $row ) ) {
                if ( isset( $row['label'] ) && $row['label'] !== '' ) {
                    $out[] = (string) $row['label'];
                } elseif ( isset( $row[0] ) && $row[0] !== '' ) {
                    $out[] = (string) $row[0];
                }
            } elseif ( is_scalar( $row ) && trim( (string) $row ) !== '' ) {
                $out[] = (string) $row;
            }
        }
        return array_values( array_filter( $out, static function( $v ){ return $v !== ''; } ) );
    }

    /**
     * Read prices array (flexible).
     * Accepts:
     *  - array of ['label'=>..,'value'=>..]
     *  - array of [label,value]
     *  - JSON string
     *  - serialized PHP
     */
    protected function read_prices_array( $post_id, array $candidates ) {
        $raw = $this->read_any_array_like_meta( $post_id, $candidates );
        if ( empty( $raw ) ) return [];

        $out = [];
        foreach ( $raw as $row ) {
            if ( is_array( $row ) ) {
                $label = '';
                $value = '';
                if ( array_key_exists( 'label', $row ) || array_key_exists( 'value', $row ) ) {
                    $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                    $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                } else {
                    $label = isset( $row[0] ) ? (string) $row[0] : '';
                    $value = isset( $row[1] ) ? (string) $row[1] : '';
                }
                if ( $label !== '' || $value !== '' ) {
                    $out[] = [ 'label' => $label, 'value' => $value ];
                }
            } elseif ( is_scalar( $row ) && trim( (string) $row ) !== '' ) {
                // A single scalar “price” without label -> treat as single price line with empty label
                $out[] = [ 'label' => '', 'value' => (string) $row ];
            }
        }

        return $out;
    }

    /**
     * Generic reader that understands array-like meta:
     * - returns array
     * - accepts real arrays, JSON arrays, serialized arrays
     * - ignores scalars
     */
    protected function read_any_array_like_meta( $post_id, array $candidates ) {
        foreach ( $candidates as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( $val === '' || $val === null ) continue;

            // Already an array saved by update_post_meta
            if ( is_array( $val ) ) {
                return $val;
            }

            // Serialized PHP?
            if ( is_string( $val ) && strpos( $val, 'a:' ) === 0 ) {
                $maybe = @unserialize( $val );
                if ( is_array( $maybe ) ) return $maybe;
            }

            // JSON?
            if ( is_string( $val ) ) {
                $trim = trim( $val );
                if ( ( strlen( $trim ) > 1 ) && ( $trim[0] === '[' || $trim[0] === '{' ) ) {
                    $maybe = json_decode( $trim, true );
                    if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) {
                        // If object with rows property
                        if ( isset( $maybe['rows'] ) && is_array( $maybe['rows'] ) ) {
                            return $maybe['rows'];
                        }
                        return $maybe;
                    }
                }
            }
        }
        return [];
    }

    /**
     * Clean content → strip shortcodes/tags and trim to length.
     */
    protected function clean_content_as_excerpt( $content, $max_len = 220 ) {
        $content = (string) $content;
        if ( $content === '' ) return '';
        $content = strip_shortcodes( $content );
        $content = wp_strip_all_tags( $content, true );
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = trim( $content );
        if ( $max_len > 0 && strlen( $content ) > $max_len ) {
            $content = mb_substr( $content, 0, $max_len ) . '…';
        }
        return $content;
    }
}
