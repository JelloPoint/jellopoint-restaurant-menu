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
 * - Auto-detect current Menu/Section context (term archive or current post terms)
 * - Robust meta detection for prices/rows and description
 * - Preserves stable HTML wrappers for multiple-price alignment
 */
class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu', 'restaurant', 'card', 'food', 'price', 'prices', 'items' ]; }

    /* =========================
     * Controls
     * ========================= */
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
            'condition' => [ 'data_source' => 'static' ],
        ] );

        $repeater = new Repeater();
        $repeater->add_control( 'item_title', [ 'label' => __( 'Title', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Menu Item', 'jellopoint-restaurant-menu' ) ] );
        $repeater->add_control( 'item_description', [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA, 'default' => '', 'rows' => 2 ] );
        $repeater->add_control( 'item_price', [ 'label' => __( 'Single Price', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT, 'default' => '' ] );
        $repeater->add_control( 'use_multi_prices', [ 'label' => __( 'Enable Multiple Prices', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ] );

        $preset_map  = function_exists( 'jprm_get_price_label_map' ) ? (array) jprm_get_price_label_map() : [];
        $preset_full = function_exists( 'jprm_get_price_label_full_map' ) ? (array) jprm_get_price_label_full_map() : [];
        for ( $i = 1; $i <= 6; $i++ ) {
            $repeater->add_control( "price{$i}_enable", [ 'label' => sprintf( __( 'Enable Row %d', 'jellopoint-restaurant-menu' ), $i ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'condition' => [ 'use_multi_prices' => 'yes' ] ] );
            $repeater->add_control( "price{$i}_label_select", [ 'label' => sprintf( __( 'Label %d (preset)', 'jellopoint-restaurant-menu' ), $i ), 'type' => Controls_Manager::SELECT, 'default' => 'custom', 'options' => array_merge( [ 'custom' => __( 'Custom', 'jellopoint-restaurant-menu' ) ], $preset_map ), 'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ] ] );
            $repeater->add_control( "price{$i}_label_custom", [ 'label' => sprintf( __( 'Label %d (custom)', 'jellopoint-restaurant-menu' ), $i ), 'type' => Controls_Manager::TEXT, 'default' => '', 'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes', "price{$i}_label_select" => 'custom' ] ] );
            $repeater->add_control( "price{$i}_hide_icon", [ 'label' => sprintf( __( 'Hide Icon %d', 'jellopoint-restaurant-menu' ), $i ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ] ] );
            $repeater->add_control( "price{$i}_amount", [ 'label' => sprintf( __( 'Amount %d', 'jellopoint-restaurant-menu' ), $i ), 'type' => Controls_Manager::TEXT, 'default' => '', 'condition' => [ 'use_multi_prices' => 'yes', "price{$i}_enable" => 'yes' ] ] );
        }

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_title }}}',
            'condition'   => [ 'data_source' => 'static' ],
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
                        : ( $preset_full[$sel]['label'] ?? ( $preset_map[$sel] ?? $sel ) );
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
            $item = $this->normalize_item( $raw ); // now exists (no-op passthrough)

            $hide_invisible = isset( $s['hide_invisible'] ) && $s['hide_invisible'] === 'yes';
            if ( $hide_invisible && ! empty( $item['invisible'] ) ) {
                continue;
            }

            $title = isset( $item['title'] ) ? $item['title'] : '';
            $desc  = isset( $item['description'] ) ? $item['description'] : '';

            // Optional debug: show which keys were used (only when WP_DEBUG)
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                $dbg = $item['_debug'] ?? [];
                echo "\n<!-- JPRM DEBUG: "
                   . "single_key=" . ( $dbg['single_key'] ?? '-' )
                   . " | multi_key=" . ( $dbg['multi_key'] ?? '-' )
                   . " | multi_rows=" . ( isset($dbg['multi_rows_count']) ? (int)$dbg['multi_rows_count'] : 0 )
                   . " | desc_key=" . ( $dbg['desc_key'] ?? '-' )
                   . " -->\n";
            }

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            // Single price (only if no multi rows were found)
            if ( empty( $item['prices'] ) && isset( $item['price'] ) && $item['price'] !== '' ) {
                echo '      <div class="jp-menu__price">';
                echo '          <span class="jp-menu__value">' . esc_html( $item['price'] ) . '</span>';
                echo '      </div>';
            }

            // Multiple prices
            if ( ! empty( $item['prices'] ) && is_array( $item['prices'] ) ) {
                foreach ( $item['prices'] as $p ) {
                    $label = isset( $p['label'] ) ? $p['label'] : '';
                    $value = isset( $p['value'] ) ? $p['value'] : '';
                    if ( $label === '' && $value === '' ) {
                        continue;
                    }
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

            echo '  </div>'; // .jp-menu__inner
            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * Collect dynamic items based on widget settings.
     * - CPT: jprm_menu_item
     * - Taxonomies: jprm_menu, jprm_section
     * - Auto-detect context when enabled and no terms selected
     */
    protected function collect_dynamic_items( array $s ) {

        // Allow a provider to override completely
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) {
            return $filtered;
        }

        $menus    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? $s['query_menus']    : [];
        $sections = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? $s['query_sections'] : [];
        $orderby  = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order    = ! empty( $s['query_order'] )   ? sanitize_text_field( $s['query_order'] )   : 'ASC';
        $limit    = ( isset( $s['query_limit'] ) && $s['query_limit'] !== '' ) ? (int) $s['query_limit'] : -1;

        // Auto-detect context slugs if nothing selected
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

        // Build tax_query using slug/ID auto-detect
        $tax_query = [];
        if ( ! empty( $menus ) ) {
            $field = $this->guess_tax_field( $menus );
            $vals  = ( $field === 'term_id' ) ? array_map( 'intval', $menus ) : array_map( 'sanitize_title', $menus );
            $tax_query[] = [ 'taxonomy' => 'jprm_menu', 'field' => $field, 'terms' => $vals ];
        }
        if ( ! empty( $sections ) ) {
            $field = $this->guess_tax_field( $sections );
            $vals  = ( $field === 'term_id' ) ? array_map( 'intval', $sections ) : array_map( 'sanitize_title', $sections );
            $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => $field, 'terms' => $vals ];
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
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $args = apply_filters( 'jprm/widget/query_args', $args, $s, $this );

        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) {
            return [];
        }

        $out = [];
        while ( $q->have_posts() ) {
            $q->the_post();

            $post_id = get_the_ID();
            $parsed  = $this->parse_item_meta( $post_id );

            // Title & description fallbacks
            $parsed['ID']    = $post_id;
            $parsed['title'] = get_the_title();

            if ( empty( $parsed['description'] ) ) {
                $desc = get_the_excerpt();
                if ( empty( $desc ) ) {
                    $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40, '' );
                }
                $parsed['description'] = (string) $desc;
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    $parsed['_debug']['desc_key'] = $parsed['_debug']['desc_key'] ?? 'excerpt/content';
                }
            }

            $out[] = $parsed;
        }
        wp_reset_postdata();

        return $out;
    }

    /**
     * Parse & normalize meta for one item.
     * - Finds single price
     * - Finds multiple prices (rows) in many formats
     * - Finds labels and invisibility flag
     * - Adds _debug keys (only used when WP_DEBUG to print HTML comments)
     */
    protected function parse_item_meta( $post_id ) {
        $debug = [];

        // helper: get first non-empty scalar from keys
        $first_scalar = function( array $keys, &$hit = null ) use ( $post_id ) {
            foreach ( $keys as $k ) {
                $v = get_post_meta( $post_id, $k, true );
                if ( is_string( $v ) || is_numeric( $v ) ) {
                    $sv = trim( (string) $v );
                    if ( $sv !== '' ) { $hit = $k; return $sv; }
                }
            }
            return '';
        };

        // helper: maybe unserialize/json decode
        $to_array = function( $v ) {
            if ( is_array( $v ) ) return $v;
            if ( is_string( $v ) ) {
                $json = json_decode( $v, true );
                if ( is_array( $json ) ) return $json;
                $maybe = maybe_unserialize( $v );
                if ( is_array( $maybe ) ) return $maybe;
            }
            return [];
        };

        // Single price candidates (broad)
        $single_hit = null;
        $single = $first_scalar( [
            '_jprm_price','jprm_price','price','_price','item_price','price_single','single_price',
            'jprm_item_price','_jprm_price_value','_jprm_single_price','_jp_price'
        ], $single_hit );

        // Multiple prices candidates (broad)
        $multi_hit = null;
        $multi_candidates = [
            '_jprm_prices','jprm_prices','prices','price_rows','multiple_prices',
            'jprm_price_rows','_price_rows','_jprm_multi_prices','_jp_prices',
            '_jprm_prices_full','_jprm_prices_json',
        ];
        $rows = [];
        foreach ( $multi_candidates as $k ) {
            $raw = get_post_meta( $post_id, $k, true );
            if ( empty( $raw ) ) continue;
            $arr = $to_array( $raw );
            if ( ! empty( $arr ) ) {
                $norm = $this->normalize_price_rows( $arr );
                if ( ! empty( $norm ) ) { $rows = $norm; $multi_hit = $k; break; }
            }
        }

        // Parallel arrays fallback: labels + amounts stored separately
        if ( empty( $rows ) ) {
            $labels_arr  = $to_array( get_post_meta( $post_id, '_jprm_price_labels', true ) );
            $amounts_arr = $to_array( get_post_meta( $post_id, '_jprm_price_amounts', true ) );
            if ( ! empty( $amounts_arr ) || ! empty( $labels_arr ) ) {
                $max = max( count( $labels_arr ), count( $amounts_arr ) );
                for ( $i = 0; $i < $max; $i++ ) {
                    $lbl = isset( $labels_arr[$i] ) ? (string) $labels_arr[$i] : '';
                    $val = isset( $amounts_arr[$i] ) ? (string) $amounts_arr[$i] : '';
                    if ( $lbl !== '' || $val !== '' ) $rows[] = [ 'label' => $lbl, 'value' => $val ];
                }
                if ( ! empty( $rows ) ) { $multi_hit = '_jprm_price_labels/_jprm_price_amounts'; }
            }
        }

        // Description candidates (broad)
        $desc_hit = null;
        $desc = $first_scalar( [
            '_jprm_desc','jprm_desc','description','item_description','_description',
            '_jprm_item_desc','_jp_desc'
        ], $desc_hit );

        // Labels – array or CSV
        $labels = [];
        foreach ( [ '_jprm_labels','jprm_labels','labels','item_labels' ] as $lk ) {
            $lv = get_post_meta( $post_id, $lk, true );
            if ( empty( $lv ) ) continue;
            if ( is_string( $lv ) ) {
                $tmp = array_map( 'trim', explode( ',', $lv ) );
            } else {
                $tmp = $to_array( $lv );
            }
            $labels = array_values( array_filter( array_map( 'strval', $tmp ), function($x){ return $x !== ''; } ) );
            if ( ! empty( $labels ) ) break;
        }

        // Visibility flag
        $invisible = false;
        foreach ( [ '_jprm_invisible','jprm_invisible','invisible','item_invisible' ] as $ik ) {
            $iv = get_post_meta( $post_id, $ik, true );
            if ( $iv === '1' || $iv === 1 || $iv === true || $iv === 'yes' ) { $invisible = true; break; }
        }

        // Prefer multi rows if present; otherwise use single
        $result = [
            'price'       => $single,
            'prices'      => $rows,
            'description' => $desc,
            'labels'      => $labels,
            'invisible'   => $invisible,
            '_debug'      => [
                'single_key'       => $single_hit,
                'multi_key'        => $multi_hit,
                'multi_rows_count' => count( $rows ),
                'desc_key'         => $desc_hit,
            ],
        ];

        /**
         * Final hook to allow custom resolution of prices.
         * Return a modified $result.
         */
        $result = apply_filters( 'jprm/widget/resolve_prices', $result, $post_id, $this );

        return $result;
    }

    /**
     * Normalize arbitrary array of rows into [['label'=>..,'value'=>..], ...].
     * Accepts many shapes:
     *  - [ ['label'=>'Small','value'=>'7.50'], ... ]
     *  - [ ['title'=>'Small','amount'=>'7.50'], ... ]
     *  - [ ['label'=>'Small','price'=>'7.50'], ... ]
     *  - [ ['Small','7.50'], ... ] (numeric indices)
     *  - [ 'Small' => '7.50', 'Large' => '12.00' ] (assoc map)
     */
    protected function normalize_price_rows( $val ) {
        $out = [];
        if ( ! is_array( $val ) ) return $out;

        // Assoc map case: 'Small' => '7.50'
        $is_assoc_map = array_keys( $val ) !== range( 0, count( $val ) - 1 );
        if ( $is_assoc_map ) {
            foreach ( $val as $k => $v ) {
                $label = (string) $k;
                $value = is_scalar( $v ) ? (string) $v : '';
                if ( $label !== '' || $value !== '' ) $out[] = [ 'label' => $label, 'value' => $value ];
            }
            return $out;
        }

        foreach ( $val as $row ) {
            $label = ''; $value = '';

            if ( is_array( $row ) ) {
                // Common keys
                if ( isset( $row['label'] ) || isset( $row['value'] ) ) {
                    $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                    $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                } elseif ( isset( $row['title'] ) || isset( $row['amount'] ) || isset( $row['price'] ) ) {
                    $label = isset( $row['title'] )  ? (string) $row['title']  : '';
                    $value = isset( $row['amount'] ) ? (string) $row['amount'] : ( isset( $row['price'] ) ? (string) $row['price'] : '' );
                } else {
                    // Numeric indices
                    $label = isset( $row[0] ) ? (string) $row[0] : ( isset( $row['0'] ) ? (string) $row['0'] : '' );
                    $value = isset( $row[1] ) ? (string) $row[1] : ( isset( $row['1'] ) ? (string) $row['1'] : '' );
                }
            } elseif ( is_scalar( $row ) ) {
                // Single scalar row -> treat as value-only (rare)
                $value = (string) $row;
            }

            if ( $label !== '' || $value !== '' ) {
                $out[] = [ 'label' => $label, 'value' => $value ];
            }
        }
        return $out;
    }

    /**
     * Try to infer current Menu/Section context (returns slugs).
     */
    protected function detect_context_terms() {
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

        // De-dup
        $out['menus']    = array_values( array_unique( array_filter( $out['menus'] ) ) );
        $out['sections'] = array_values( array_unique( array_filter( $out['sections'] ) ) );

        return apply_filters( 'jprm/widget/detected_terms_slugs', $out, $this );
    }

    /**
     * Guess if provided term values are IDs or slugs.
     */
    protected function guess_tax_field( $vals ) {
        $all_int = true;
        foreach ( (array) $vals as $v ) {
            if ( ! is_numeric( $v ) || intval( $v ) != $v ) { $all_int = false; break; }
        }
        return $all_int ? 'term_id' : 'slug';
    }

    /**
     * Build term options for SELECT2 controls (uses slugs as values).
     */
    protected function get_terms_options( $taxonomy ) {
        $opts = [];
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return $opts;
        }
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $opts;
        }
        foreach ( $terms as $t ) {
            $opts[ $t->slug ] = $t->name;
        }
        return apply_filters( 'jprm/widget/term_options', $opts, $taxonomy, $this );
    }

    /**
     * No-op normalizer to keep the render pipeline stable.
     * Items coming from collect_dynamic_items() are already normalized.
     */
    protected function normalize_item( array $item ) {
        return $item;
    }
}
