<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Dynamic items with qty labels/icons placed before/after the price (single + multi).
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

        /* ====== Static Items (unchanged for now) ====== */
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

        $presentation    = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'text';
        if ( $presentation === 'badge' ) $presentation = 'text';
        $label_order_cls = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $raw ) {
            $item = $this->normalize_item( $raw );

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

            // Multi rows?
            $has_multi_rows = ! empty( $item['prices'] ) && is_array( $item['prices'] );

            // SINGLE price (now with explicit single label logic)
            if ( ! $has_multi_rows && isset( $item['price'] ) && $item['price'] !== '' ) {
                // prefer explicit single label ref; fallback to first item-level label id/slug/text
                $single_ref  = isset($item['single_label_ref']) ? (string)$item['single_label_ref'] : '';
                $single_hide = isset($item['single_hide_icon']) ? (bool)$item['single_hide_icon'] : null;

                if ( $single_ref === '' && ! empty( $item['labels'] ) && is_array( $item['labels'] ) ) {
                    $single_ref = (string) reset( $item['labels'] );
                }

                $label_text = '';
                $icon_id    = 0;
                $hide_icon  = false;

                if ( $single_ref !== '' ) {
                    $resolved   = $this->resolve_qty_label_via_store( $single_ref, $single_hide );
                    $label_text = $resolved['label_text'];
                    $icon_id    = $resolved['icon_id'];
                    $hide_icon  = $resolved['hide_icon'];
                }

                if ( $label_text === '' && ! $icon_id ) {
                    echo '      <div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $item['price'] ) . '</span></div>';
                } else {
                    echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                    echo '          <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_text, $presentation, $icon_id, $hide_icon ) . '</span>';
                    echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $item['price'] ) . '</span>';
                    echo '      </div>';
                }
            }

            // MULTI rows (qty labels per row)
            if ( $has_multi_rows ) {
                foreach ( $item['prices'] as $p ) {
                    $label_ref    = isset( $p['label_ref'] ) ? (string)$p['label_ref'] : ( isset($p['label']) ? (string)$p['label'] : '' );
                    $row_hide_icon= isset( $p['hide_icon'] ) ? (bool)$p['hide_icon'] : null;

                    $resolved = $this->resolve_qty_label_via_store( $label_ref, $row_hide_icon );
                    $label_txt = $resolved['label_text'];
                    $icon_id   = $resolved['icon_id'];
                    $hide_icon = $resolved['hide_icon'];

                    $value = isset( $p['value'] ) ? (string) $p['value'] : '';
                    if ( $label_txt === '' && ! $icon_id && $value === '' ) continue;

                    echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                    echo '          <span class="jp-menu__label jp-col-label">' . $this->render_label_html( $label_txt, $presentation, $icon_id, $hide_icon ) . '</span>';
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
     * Resolve a qty label via Labels Store (supports IDs, slugs, or ready text).
     * Returns text + icon_id. Falls back to text if no icon exists.
     */
    protected function resolve_qty_label_via_store( $raw, $row_hide_icon = null ) {
        $out = [ 'label_text' => '', 'icon_id' => 0, 'hide_icon' => false ];
        $key_or_text = trim( (string) $raw );
        if ( $key_or_text === '' ) return $out;

        // Ensure the Labels Store class is available; if not, include it.
        if ( ! class_exists( 'JPRM_Labels_Store' ) ) {
            $maybe = dirname( __DIR__ ) . '/data/class-labels-store.php';
            if ( file_exists( $maybe ) ) { require_once $maybe; }
        }

        $row = null;

        // Lookup by ID/slug or name (case-insensitive)
        if ( class_exists( 'JPRM_Labels_Store' ) && method_exists( 'JPRM_Labels_Store', 'map_by_id' ) ) {
            $map = \JPRM_Labels_Store::map_by_id();
            if ( isset( $map[ $key_or_text ] ) ) {
                $row = (array) $map[ $key_or_text ];
            } else {
                $needle = strtolower( $key_or_text );
                foreach ( $map as $r ) {
                    $id   = strtolower( (string) ( $r['id']    ?? '' ) );
                    $slug = strtolower( (string) ( $r['slug']  ?? '' ) );
                    $name = strtolower( (string) ( $r['label'] ?? '' ) );
                    if ( $needle === $id || $needle === $slug || $needle === $name ) { $row = (array) $r; break; }
                }
            }
        } else {
            // Fallback: read options directly (v2 first)
            $fallback = get_option( 'jprm_price_labels_v2', [] );
            $arr      = is_string($fallback) ? json_decode($fallback, true) : (array) $fallback;
            if ( is_array($arr) ) {
                $needle = strtolower( $key_or_text );
                foreach ( $arr as $r ) {
                    $id   = strtolower( (string) ( $r['id']    ?? '' ) );
                    $slug = strtolower( (string) ( $r['slug']  ?? '' ) );
                    $name = strtolower( (string) ( $r['label'] ?? '' ) );
                    if ( $needle === $id || $needle === $slug || $needle === $name ) { $row = (array) $r; break; }
                }
            }
        }

        if ( $row ) {
            $out['label_text'] = (string) ( $row['label'] ?? $key_or_text );
            $out['icon_id']    = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
        } else {
            // Treat as ready text
            $out['label_text'] = $key_or_text;
        }

        if ( $row_hide_icon !== null ) {
            $out['hide_icon'] = (bool) $row_hide_icon;
        }

        return $out;
    }

    /**
     * Extract the label reference from a row supporting multiple admin keys.
     * (Not used directly anymore; we keep a normalized 'label_ref' in rows.)
     */
    protected function extract_row_label_reference( $row ) {
        if ( ! is_array( $row ) ) return '';
        foreach ( [ 'label_ref', 'label', 'label_key', 'label_id', 'id', 'key', 'preset', 'slug' ] as $k ) {
            if ( isset( $row[$k] ) && $row[$k] !== '' ) return (string) $row[$k];
        }
        return '';
    }

    /**
     * Build the inner HTML for a label based on the chosen presentation.
     * - 'text'      => plain text
     * - 'icon'      => icon only (with SR-only text fallback)
     * - 'icon_text' => icon + text (falls back to text if no icon)
     */
    protected function render_label_html( $label_text, $presentation, $icon_id = 0, $hide_icon = false ) {
        $label_text = (string) $label_text;
        if ( $presentation !== 'text' && $presentation !== 'icon' && $presentation !== 'icon_text' ) {
            $presentation = 'text';
        }
        if ( $hide_icon ) $presentation = 'text';

        // Helper: build icon img if available
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
            return esc_html( $label_text ); // fallback
        }

        if ( $presentation === 'icon_text' ) {
            if ( $icon_html !== '' ) {
                return $icon_html . ' ' . esc_html( $label_text );
            }
            return esc_html( $label_text );
        }

        return esc_html( $label_text );
    }

    /**
     * Collect dynamic items based on widget settings.
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

            // Title & description fallbacks
            $parsed['ID']    = $post_id;
            $parsed['title'] = get_the_title();

            if ( empty( $parsed['description'] ) ) {
                $desc = get_the_excerpt();
                if ( empty( $desc ) ) $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40, '' );
                $parsed['description'] = (string) $desc;
            }

            $out[] = $parsed;
        }
        wp_reset_postdata();

        return $out;
    }

    /**
     * Parse & normalize meta for one item.
     * Single price + multi prices + labels array + invisible flag.
     * Also extracts explicit single-price label reference if present.
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

        // ---------- MULTIPLE PRICES ----------
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
                    $rows[] = [ 'label' => $lbl, 'label_ref' => $lbl, 'value' => $val, 'hide_icon' => $hid ];
                }
                if ( ! empty( $rows ) ) { $multi_hit = '_jprm_price_labels/_jprm_price_amounts'; }
            }
        }

        // ---------- SINGLE PRICE ----------
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

        // ---------- DESCRIPTION ----------
        $desc_hit = null;
        $desc = $first_scalar( [
            '_jprm_desc','jprm_desc','description','item_description','_description',
            '_jprm_item_desc','_jp_desc'
        ], $desc_hit );

        // ---------- SINGLE PRICE LABEL REF (NEW) ----------
        $single_label_ref = '';
        foreach ( [
            '_jprm_single_label','single_label','price_label','jprm_single_label','jprm_price_label',
            '_jprm_label_single','label_single','_jprm_label_id','_jprm_label_key',
            'label_id','label_key','label_ref','label','preset','slug'
        ] as $lk ) {
            $val = get_post_meta( $post_id, $lk, true );
            if ( is_string($val) || is_numeric($val) ) {
                $val = trim( (string)$val );
                if ( $val !== '' ) { $single_label_ref = $val; break; }
            }
        }

        // Optional single hide icon flag
        $single_hide_icon = false;
        foreach ( [ '_jprm_single_hide_icon','single_hide_icon','price_hide_icon','_jprm_hide_icon_single' ] as $hk ) {
            $flag = get_post_meta( $post_id, $hk, true );
            if ( $flag === '1' || $flag === 1 || $flag === true || $flag === 'yes' ) { $single_hide_icon = true; break; }
        }

        // ---------- ITEM-LEVEL LABELS ARRAY (legacy / fallback)
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

        // ---------- VISIBILITY ----------
        $invisible = false;
        foreach ( [ '_jprm_invisible','jprm_invisible','invisible','item_invisible' ] as $ik ) {
            $iv = get_post_meta( $post_id, $ik, true );
            if ( $iv === '1' || $iv === 1 || $iv === true || $iv === 'yes' ) { $invisible = true; break; }
        }

        return [
            'price'            => $single,
            'prices'           => $rows,
            'description'      => $desc,
            'labels'           => $labels,           // legacy array
            'single_label_ref' => $single_label_ref, // NEW: explicit single label id/slug/text
            'single_hide_icon' => $single_hide_icon, // NEW
            'invisible'        => $invisible,
            '_debug'           => [
                'single_key'       => $single_hit,
                'multi_key'        => $multi_hit,
                'multi_rows_count' => is_array($rows) ? count( $rows ) : 0,
                'desc_key'         => $desc_hit,
                'single_label_ref' => $single_label_ref,
                'single_hide_icon' => $single_hide_icon ? '1':'0',
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
     * Normalize rows from admin into a stable shape, but **preserve label reference**.
     * Result rows contain:
     *   - label       (display text if provided in row; else empty)
     *   - label_ref   (id/slug/key used to resolve against Labels Store)
     *   - value       (price)
     *   - hide_icon   (bool)
     * Any unknown keys are preserved to not lose data.
     */
    protected function normalize_price_rows( $val ) {
        $out = [];
        if ( ! is_array( $val ) ) return $out;

        $is_assoc_map = array_keys( $val ) !== range( 0, count( $val ) - 1 );
        if ( $is_assoc_map ) {
            foreach ( $val as $k => $v ) {
                $row = [ 'label' => (string) $k, 'label_ref' => (string) $k, 'value' => is_scalar($v) ? (string)$v : '' ];
                $out[] = $row;
            }
            return $out;
        }

        foreach ( $val as $row ) {
            if ( is_array( $row ) ) {
                $r = $row; // preserve unknown keys

                // Value
                if ( ! isset($r['value']) ) {
                    if ( isset($r['amount']) ) $r['value'] = (string) $r['amount'];
                    elseif ( isset($r['price']) ) $r['value'] = (string) $r['price'];
                    elseif ( isset($r[1]) ) $r['value'] = (string) $r[1];
                    else $r['value'] = '';
                } else {
                    $r['value'] = (string) $r['value'];
                }

                // Label text (if any direct)
                if ( ! isset($r['label']) ) {
                    if ( isset($r['title']) ) $r['label'] = (string) $r['title'];
                    elseif ( isset($r[0]) ) $r['label'] = (string) $r[0];
                    else $r['label'] = '';
                } else {
                    $r['label'] = (string) $r['label'];
                }

                // Label ref (id/slug/key)
                $ref = '';
                foreach ( [ 'label_ref','label_key','label_id','id','key','preset','slug' ] as $k ) {
                    if ( isset( $row[$k] ) && $row[$k] !== '' ) { $ref = (string) $row[$k]; break; }
                }
                if ( $ref === '' ) $ref = $r['label'];
                $r['label_ref'] = $ref;

                // Hide icon normalize
                $r['hide_icon'] = isset($r['hide_icon']) ? (bool)$r['hide_icon'] : false;

                $out[] = $r;

            } elseif ( is_scalar( $row ) ) {
                $out[] = [ 'label' => '', 'label_ref' => '', 'value' => (string)$row, 'hide_icon' => false ];
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
        if ( ! taxonomy_exists( $taxonomy ) ) return $opts;
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return $opts;
        foreach ( $terms as $t ) $opts[ $t->slug ] = $t->name;
        return apply_filters( 'jprm/widget/term_options', $opts, $taxonomy, $this );
    }

    /**
     * Keep the render pipeline stable.
     */
    protected function normalize_item( array $item ) { return $item; }

    /**
     * Inline CSS used by both static and dynamic rendering.
     */
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
        if ( $tag ) {
            echo '<style class="jprm-menu-inline-css">' . $css . '</style>';
        } else {
            echo '<style>' . $css . '</style>';
        }
    }
}
