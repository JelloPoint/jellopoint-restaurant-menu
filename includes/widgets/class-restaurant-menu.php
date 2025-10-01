<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Dynamic items with qty labels/icons placed before/after the price.
 * Static items unchanged for now.
 */
class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','card','food','price','prices','items' ]; }

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

        // Label/Icon presentation and position for qty
        $this->add_control( 'label_presentation', [
            'label'   => __( 'Label Presentation (qty)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'text',
            'options' => [
                'text'      => __( 'Text', 'jellopoint-restaurant-menu' ),
                'icon'      => __( 'Icon Only', 'jellopoint-restaurant-menu' ),
                'icon_text' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
                'badge'     => __( 'Badge (legacy → text)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'label_position', [
            'label'   => __( 'Label Position (qty)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'right',
            'options' => [
                'left'  => __( 'Left (label | price)', 'jellopoint-restaurant-menu' ),
                'right' => __( 'Right (price | label)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->end_controls_section();

        /* ====== Static Items (unchanged for now) ====== */
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
        $repeater->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_title }}}',
        ] );

        $this->end_controls_section();
    }

    /* =========================
     * Static rendering (unchanged)
     * ========================= */
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

    /* =========================
     * Render (entry)
     * ========================= */
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
            $this->print_inline_layout_css(true);
        }

        $presentation = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'text';
        if ( $presentation === 'badge' ) $presentation = 'text';
        $label_order_class = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $raw ) {
            $item = $this->normalize_item( $raw );

            // Debug
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                $dbg = $item['_debug'] ?? [];
                echo "\n<!-- JPRM DEBUG: "
                   . "single_key=" . ( $dbg['single_key'] ?? '-' )
                   . " | multi_key=" . ( $dbg['multi_key'] ?? '-' )
                   . " | multi_rows=" . ( isset($dbg['multi_rows_count']) ? (int)$dbg['multi_rows_count'] : 0 )
                   . " | desc_key=" . ( $dbg['desc_key'] ?? '-' )
                   . " -->\n";
            }

            $hide_invisible = isset( $s['hide_invisible'] ) && $s['hide_invisible'] === 'yes';
            if ( $hide_invisible && ! empty( $item['invisible'] ) ) continue;

            $title = isset( $item['title'] ) ? $item['title'] : '';
            $desc  = isset( $item['description'] ) ? $item['description'] : '';

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            // Do we have multi rows?
            $has_multi_rows = false;
            if ( ! empty( $item['prices'] ) && is_array( $item['prices'] ) ) {
                foreach ( $item['prices'] as $r ) {
                    if ( ( isset($r['label']) && $r['label'] !== '' ) || ( isset($r['value']) && $r['value'] !== '' ) ) { $has_multi_rows = true; break; }
                }
            }

            // SINGLE price (with optional single label from item-level labels)
            if ( ! $has_multi_rows && isset( $item['price'] ) && $item['price'] !== '' ) {
                $label_text = '';
                $icon_class = '';
                $hide_icon  = false;

                if ( ! empty( $item['labels'] ) && is_array( $item['labels'] ) ) {
                    $first = (string) reset( $item['labels'] );
                    $resolved = $this->resolve_qty_label( $first );
                    $label_text = $resolved['label_text'];
                    $icon_class = $resolved['icon_class'];
                    $hide_icon  = $resolved['hide_icon'];
                }

                if ( $label_text === '' && $icon_class === '' ) {
                    // No label → original single price block (preserves prior layout)
                    echo '      <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $item['price'] ) . '</span></div>';
                } else {
                    echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_class ) . '">';
                    echo '          <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_class, $hide_icon ) . '</span>';
                    echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $item['price'] ) . '</span>';
                    echo '      </div>';
                }
            }

            // MULTI rows (qty labels per row)
            if ( $has_multi_rows ) {
                foreach ( $item['prices'] as $p ) {
                    // Resolve row label through store (supports keys/slugs)
                    $resolved = $this->resolve_qty_label(
                        isset( $p['label'] ) ? (string)$p['label'] : '',
                        isset( $p['hide_icon'] ) ? (bool)$p['hide_icon'] : null
                    );
                    $label_txt = $resolved['label_text'];
                    $icon_cls  = $resolved['icon_class'];
                    $hide_icon = $resolved['hide_icon'];

                    $value     = isset( $p['value'] ) ? (string) $p['value'] : '';
                    if ( $label_txt === '' && $icon_cls === '' && $value === '' ) continue;

                    echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_class ) . '">';
                    echo '          <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_txt, $presentation, $icon_cls, $hide_icon ) . '</span>';
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
     * Resolve a qty label (text + icon) using labels store + filters.
     * Accepts ready text or a key/slug/id commonly used in admin rows.
     */
    protected function resolve_qty_label( $raw, $row_hide_icon = null ) {
        $out = [ 'label_text' => '', 'icon_class' => '', 'hide_icon' => false ];
        $key_or_text = trim( (string) $raw );
        if ( $key_or_text === '' ) return $out;

        $lower = sanitize_title( $key_or_text ); // good for matching slugs

        // 1) Full map (preferred): key => [ 'label' => 'Small Glass', 'icon' => 'fa-solid fa-wine-glass', 'abbr' => 'S' ]
        if ( function_exists( 'jprm_get_price_label_full_map' ) ) {
            $full = (array) jprm_get_price_label_full_map();
            if ( isset( $full[$key_or_text] ) ) {
                $meta = (array) $full[$key_or_text];
            } elseif ( isset( $full[$lower] ) ) {
                $meta = (array) $full[$lower];
            } else {
                // try case-insensitive scan
                $meta = null;
                foreach ( $full as $k => $v ) {
                    if ( strtolower( (string) $k ) === $lower ) { $meta = (array) $v; break; }
                }
            }
            if ( ! empty( $meta ) ) {
                $out['label_text'] = (string) ( $meta['label'] ?? $key_or_text );
                $out['icon_class'] = (string) ( $meta['icon']  ?? '' );
            }
        }

        // 2) Simple map fallback: key => 'Small Glass'
        if ( $out['label_text'] === '' && function_exists( 'jprm_get_price_label_map' ) ) {
            $map = (array) jprm_get_price_label_map();
            if ( isset( $map[$key_or_text] ) ) {
                $out['label_text'] = (string) $map[$key_or_text];
            } elseif ( isset( $map[$lower] ) ) {
                $out['label_text'] = (string) $map[$lower];
            } else {
                foreach ( $map as $k => $v ) {
                    if ( strtolower( (string) $k ) === $lower ) { $out['label_text'] = (string) $v; break; }
                }
            }
        }

        // 3) If still not found in maps, treat as ready-to-print text
        if ( $out['label_text'] === '' ) {
            $out['label_text'] = $key_or_text;
        }

        // 4) External icon mapping filter (can override store)
        $icon_from_filter = apply_filters( 'jprm/label/icon', '', $out['label_text'] );
        if ( is_string( $icon_from_filter ) && $icon_from_filter !== '' ) {
            $out['icon_class'] = $icon_from_filter;
        }

        // 5) Respect per-row hide_icon if provided
        if ( $row_hide_icon !== null ) {
            $out['hide_icon'] = (bool) $row_hide_icon;
        }

        return $out;
    }

    /**
     * Build inner HTML for a label based on the chosen presentation and icon availability.
     */
    protected function render_label_html( $label_text, $presentation, $icon_class = '', $hide_icon = false ) {
        $label_text = (string) $label_text;
        $icon_class = (string) $icon_class;

        // Normalize presentation
        if ( $presentation !== 'text' && $presentation !== 'icon' && $presentation !== 'icon_text' ) {
            $presentation = 'text';
        }

        // If icon is globally/row hidden, degrade to text-only
        if ( $hide_icon ) $presentation = 'text';

        if ( $presentation === 'icon' ) {
            if ( $icon_class !== '' ) {
                return '<i class="' . esc_attr( $icon_class ) . '" aria-hidden="true"></i><span class="screen-reader-text">' . esc_html( $label_text ) . '</span>';
            }
            // No icon available → show text, never blank
            return esc_html( $label_text );
        }

        if ( $presentation === 'icon_text' ) {
            if ( $icon_class !== '' ) {
                return '<i class="' . esc_attr( $icon_class ) . '" aria-hidden="true"></i> ' . esc_html( $label_text );
            }
            // Fallback to text if no icon class
            return esc_html( $label_text );
        }

        // Default: text
        return esc_html( $label_text );
    }

    /**
     * Collect dynamic items.
     */
    protected function collect_dynamic_items( array $s ) {
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) return $filtered;

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
        if ( count( $tax_query ) > 1 ) $tax_query['relation'] = 'AND';

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

        $args = apply_filters( 'jprm/widget/query_args', $args, $s, $this );

        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) return [];

        $out = [];
        while ( $q->have_posts() ) {
            $q->the_post();
            $post_id = get_the_ID();

            $parsed  = $this->parse_item_meta( $post_id );

            $parsed['ID']    = $post_id;
            $parsed['title'] = get_the_title();

            if ( empty( $parsed['description'] ) ) {
                $desc = get_the_excerpt();
                if ( empty( $desc ) ) $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40, '' );
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
     * Parse & normalize meta.
     */
    protected function parse_item_meta( $post_id ) {
        // helper: first non-empty scalar from keys
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

        // helper: to array (json/unserialize aware)
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

        // MULTI PRICES
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

        // Parallel arrays fallback
        if ( empty( $rows ) ) {
            $labels_arr  = $to_array( get_post_meta( $post_id, '_jprm_price_labels', true ) );
            $amounts_arr = $to_array( get_post_meta( $post_id, '_jprm_price_amounts', true ) );
            $hide_arr    = $to_array( get_post_meta( $post_id, '_jprm_price_hideicons', true ) );
            if ( ! empty( $amounts_arr ) || ! empty( $labels_arr ) ) {
                $max = max( count( $labels_arr ), count( $amounts_arr ) );
                for ( $i = 0; $i < $max; $i++ ) {
                    $lbl = isset( $labels_arr[$i] ) ? (string) $labels_arr[$i] : '';
                    $val = isset( $amounts_arr[$i] ) ? (string) $amounts_arr[$i] : '';
                    $hid = isset( $hide_arr[$i] ) ? (bool) $hide_arr[$i] : false;
                    if ( $lbl !== '' || $val !== '' ) $rows[] = [ 'label' => $lbl, 'value' => $val, 'hide_icon' => $hid ];
                }
                if ( ! empty( $rows ) ) { $multi_hit = '_jprm_price_labels/_jprm_price_amounts'; }
            }
        }

        // SINGLE PRICE
        $single_hit = null;
        $single = $first_scalar( [
            '_jprm_price','jprm_price','price','_price','item_price','price_single','single_price',
            'jprm_item_price','_jprm_price_value','_jprm_single_price','_jp_price'
        ], $single_hit );

        if ( $single === '' ) {
            $single_keys = [
                '_jprm_price','jprm_price','price','_price','item_price','price_single','single_price',
                'jprm_item_price','_jprm_price_value','_jprm_single_price','_jp_price'
            ];
            foreach ( $single_keys as $k ) {
                $raw = get_post_meta( $post_id, $k, true );
                if ( empty( $raw ) ) continue;
                $arr = $to_array( $raw );
                if ( empty( $arr ) && is_array( $raw ) ) $arr = $raw;
                if ( is_array( $arr ) ) {
                    $cand = $this->extract_single_from_array_like( $arr );
                    if ( $cand !== '' ) { $single = $cand; $single_hit = $k . ' (array)'; break; }
                }
            }
        }

        if ( $single === '' ) {
            $avoid = array_map( 'strval', $multi_candidates );
            $auto = $this->guess_single_price_from_all_meta( $post_id, $avoid );
            if ( $auto['value'] !== '' ) {
                $single = $auto['value'];
                $single_hit = $auto['key'];
            }
        }

        // DESCRIPTION
        $desc_hit = null;
        $desc = $first_scalar( [
            '_jprm_desc','jprm_desc','description','item_description','_description',
            '_jprm_item_desc','_jp_desc'
        ], $desc_hit );

        // ITEM-LEVEL LABELS (for single price label)
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

        // VISIBILITY
        $invisible = false;
        foreach ( [ '_jprm_invisible','jprm_invisible','invisible','item_invisible' ] as $ik ) {
            $iv = get_post_meta( $post_id, $ik, true );
            if ( $iv === '1' || $iv === 1 || $iv === true || $iv === 'yes' ) { $invisible = true; break; }
        }

        return [
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
    }

    protected function extract_single_from_array_like( $arr ) {
        if ( ! is_array( $arr ) ) return '';
        if ( isset($arr['formatted']) && $this->looks_like_price_string( $arr['formatted'] ) ) return (string) $arr['formatted'];
        if ( isset($arr['value'])     && $arr['value'] !== '' )     return (string) $arr['value'];
        if ( isset($arr['amount'])    && $arr['amount'] !== '' )    return (string) $arr['amount'];
        if ( isset($arr['price'])     && $arr['price'] !== '' )     return (string) $arr['price'];
        if ( isset($arr[0])           && $arr[0] !== '' )           return (string) $arr[0];
        if ( isset($arr['currency'], $arr['amount']) ) {
            $c = (string) $arr['currency']; $a = (string) $arr['amount'];
            if ( $a !== '' ) return trim($c . ' ' . $a);
        }
        return '';
    }

    protected function guess_single_price_from_all_meta( $post_id, array $avoid_keys = [] ) {
        $all = get_post_meta( $post_id );
        if ( empty( $all ) || ! is_array( $all ) ) return [ 'key' => null, 'value' => '' ];
        foreach ( $all as $key => $values ) {
            $lk = strtolower( (string) $key );
            if ( in_array( $key, $avoid_keys, true ) ) continue;
            if ( strpos($lk,'prices') !== false || strpos($lk,'rows') !== false || strpos($lk,'multiple') !== false ) continue;
            if ( strpos($lk,'label') !== false ) continue;
            $pricey = ( strpos($lk,'price') !== false ) || ( strpos($lk,'amount') !== false ) || ( strpos($lk,'value') !== false ) || ( strpos($lk,'cost') !== false );
            if ( ! $pricey ) continue;

            $val = get_post_meta( $post_id, $key, true );
            if ( is_string( $val ) || is_numeric( $val ) ) {
                $sv = trim( (string) $val );
                if ( $sv !== '' && $this->looks_like_price_string( $sv ) ) {
                    return [ 'key' => $key, 'value' => $sv ];
                }
            } elseif ( is_array( $val ) ) {
                $cand = $this->extract_single_from_array_like( $val );
                if ( $cand !== '' && $this->looks_like_price_string( $cand ) ) {
                    return [ 'key' => $key . ' (auto/array)', 'value' => $cand ];
                }
            }
        }
        return [ 'key' => null, 'value' => '' ];
    }

    protected function looks_like_price_string( $s ) {
        if ( ! is_string( $s ) && ! is_numeric( $s ) ) return false;
        $s = (string) $s;
        if ( $s === '' ) return false;
        return (bool) preg_match( '/[0-9]/', $s );
    }

    /**
     * Normalize rows from admin into [['label'=>..., 'value'=>..., 'hide_icon'=>bool], ...]
     * Also supports keys like label_key/preset/key and resolves to display text via store during render.
     */
    protected function normalize_price_rows( $val ) {
        $out = [];
        if ( ! is_array( $val ) ) return $out;

        $is_assoc_map = array_keys( $val ) !== range( 0, count( $val ) - 1 );
        if ( $is_assoc_map ) {
            // Map form: 'Small Glass' => '7.50'  OR  'small_glass' => '7.50'
            foreach ( $val as $k => $v ) {
                $label = (string) $k;
                $value = is_scalar( $v ) ? (string) $v : '';
                if ( $label !== '' || $value !== '' ) $out[] = [ 'label' => $label, 'value' => $value ];
            }
            return $out;
        }

        foreach ( $val as $row ) {
            $label = ''; $value = ''; $hide_icon = false;

            if ( is_array( $row ) ) {
                // Standard shapes
                if ( isset( $row['label'] ) || isset( $row['value'] ) ) {
                    $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                    $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                } elseif ( isset( $row['title'] ) || isset( $row['amount'] ) || isset( $row['price'] ) ) {
                    $label = isset( $row['title'] )  ? (string) $row['title']  : '';
                    $value = isset( $row['amount'] ) ? (string) $row['amount'] : ( isset( $row['price'] ) ? (string) $row['price'] : '' );
                } else {
                    $label = isset( $row[0] ) ? (string) $row[0] : ( isset( $row['0'] ) ? (string) $row['0'] : '' );
                    $value = isset( $row[1] ) ? (string) $row[1] : ( isset( $row['1'] ) ? (string) $row['1'] : '' );
                }

                // Extended keys from admin: 'label_key', 'preset', 'key'
                if ( $label === '' ) {
                    if ( isset( $row['label_key'] ) ) $label = (string) $row['label_key'];
                    elseif ( isset( $row['preset'] ) )  $label = (string) $row['preset'];
                    elseif ( isset( $row['key'] ) )     $label = (string) $row['key'];
                }

                if ( isset( $row['hide_icon'] ) ) {
                    $hide_icon = (bool) $row['hide_icon'];
                }
            } elseif ( is_scalar( $row ) ) {
                $value = (string) $row;
            }

            if ( $label !== '' || $value !== '' ) {
                $out[] = [ 'label' => $label, 'value' => $value, 'hide_icon' => $hide_icon ];
            }
        }
        return $out;
    }

    /**
     * Context terms
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

        $out['menus']    = array_values( array_unique( array_filter( $out['menus'] ) ) );
        $out['sections'] = array_values( array_unique( array_filter( $out['sections'] ) ) );

        return apply_filters( 'jprm/widget/detected_terms_slugs', $out, $this );
    }

    protected function guess_tax_field( $vals ) {
        $all_int = true;
        foreach ( (array) $vals as $v ) {
            if ( ! is_numeric( $v ) || intval( $v ) != $v ) { $all_int = false; break; }
        }
        return $all_int ? 'term_id' : 'slug';
    }

    protected function get_terms_options( $taxonomy ) {
        $opts = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $opts;
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return $opts;
        foreach ( $terms as $t ) $opts[ $t->slug ] = $t->name;
        return apply_filters( 'jprm/widget/term_options', $opts, $taxonomy, $this );
    }

    protected function normalize_item( array $item ) { return $item; }

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
             . '.jp-menu__value{font-weight:600;white-space:nowrap}'
             . '.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}'
             . '.jp-col-label{justify-self:end}'
             . '.jp-col-price{justify-self:end}'
             . '.jp-price-row.jp-order--label-right .jp-col-label{order:2}'
             . '.jp-price-row.jp-order--label-right .jp-col-price{order:1}'
             . '.jp-price-row.jp-order--label-left .jp-col-label{order:1}'
             . '.jp-price-row.jp-order--label-left .jp-col-price{order:2}'
             . '.screen-reader-text{position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden}';
        if ( $tag ) {
            echo '<style class="jprm-menu-inline-css">' . $css . '</style>';
        } else {
            echo '<style>' . $css . '</style>';
        }
    }
}
