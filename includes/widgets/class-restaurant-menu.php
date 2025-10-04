<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Stable rendering: title, description, single/multiple prices, labels & icons.
 * CPT autodetect: jprm_item and/or jprm_menu_item
 * Meta autodetect: v3 JSON + legacy (rows JSON, split arrays, broad prices/description keys).
 */
class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','card','food','price','prices','items' ]; }

    public function get_style_depends() { return [ 'jprm-menu' ]; }

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

        $this->add_control( 'auto_detect_context', [
            'label'        => __( 'Auto-detect context (from this page)', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'show_all_when_empty', [
            'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_menus', [
            'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $menu_options,
            'label_block' => true,
            'condition'   => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_sections', [
            'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $section_options,
            'label_block' => true,
            'condition'   => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_orderby', [
            'label'     => __( 'Order by', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'menu_order',
            'options'   => [
                'menu_order' => __( 'Menu order', 'jellopoint-restaurant-menu' ),
                'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
                'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
            ],
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_order', [
            'label'     => __( 'Order', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'ASC',
            'options'   => [
                'ASC'  => __( 'ASC', 'jellopoint-restaurant-menu' ),
                'DESC' => __( 'DESC', 'jellopoint-restaurant-menu' ),
            ],
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->add_control( 'query_limit', [
            'label'       => __( 'Items limit', 'jellopoint-restaurant-menu' ),
            'description' => __( 'Leave empty for no limit.', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::NUMBER,
            'default'     => '',
            'min'         => 1,
            'step'        => 1,
            'condition'   => [ 'data_source' => 'dynamic' ],
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

        $this->end_controls_section();

        /* ---- Presentation controls ---- */
        $this->start_controls_section( 'section_presentation', [ 'label' => __( 'Price Labels', 'jellopoint-restaurant-menu' ) ] );

        $this->add_control( 'label_presentation', [
            'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'icon_text',
            'options' => [
                'text'      => __( 'Text only', 'jellopoint-restaurant-menu' ),
                'icon'      => __( 'Icon only', 'jellopoint-restaurant-menu' ),
                'icon_text' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'label_position', [
            'label'   => __( 'Label Position', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'right',
            'options' => [
                'left'  => __( 'Left of price', 'jellopoint-restaurant-menu' ),
                'right' => __( 'Right of price', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->end_controls_section();

        /* ---- Static items ---- */
        $this->start_controls_section( 'section_static', [ 'label' => __( 'Static Items', 'jellopoint-restaurant-menu' ), 'condition' => [ 'data_source' => 'static' ] ] );

        $repeater = new Repeater();
        $repeater->add_control( 'item_title', [
            'label'   => __( 'Title', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $repeater->add_control( 'item_description', [
            'label'   => __( 'Description', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
        ] );
        $repeater->add_control( 'item_price', [
            'label'   => __( 'Price', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_title }}}',
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
    }

    /* =========================
     * Render
     * ========================= */
    protected function render() {
        $s = $this->get_settings_for_display();

        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();
            return;
        }

        $items = $this->collect_dynamic_items( $s );

        if ( empty( $items ) && $this->is_elementor_edit_mode() ) {
            $items = $this->collect_dynamic_items_fallback_all();
        }

        if ( empty( $items ) ) {
            echo '<div class="jp-menu--empty">' . esc_html__( 'No items found for this selection.', 'jellopoint-restaurant-menu' ) . '</div>';
            return;
        }

        $presentation = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'icon_text';
        $order_class  = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $item ) {
            $title = $item['title'] ?? '';
            $desc  = $item['description'] ?? '';

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            $has_multi_rows = ! empty( $item['prices'] ) && is_array( $item['prices'] );

            // SINGLE price
            if ( ! $has_multi_rows && isset( $item['price'] ) && $item['price'] !== '' ) {
                $label_text = '';
                $icon_id    = 0;
                $hide_icon  = false;

                $single_ref  = isset($item['single_label_ref']) ? (string)$item['single_label_ref'] : '';
                $single_hide = isset($item['single_hide_icon']) ? (bool)$item['single_hide_icon'] : null;

                if ( $single_ref === '' && ! empty( $item['labels'] ) && is_array( $item['labels'] ) ) {
                    $single_ref = (string) reset( $item['labels'] );
                }
                if ( $single_ref === '' && ! empty( $item['ID'] ) ) {
                    $meta_all = get_post_meta( (int)$item['ID'] );
                    foreach ( $meta_all as $k => $vals ) {
                        if ( stripos($k, 'label') !== false && ! empty($vals[0]) ) { $single_ref = (string)$vals[0]; break; }
                    }
                }

                if ( $single_ref !== '' && class_exists('JPRM_Labels_Store') ) {
                    $res = \JPRM_Labels_Store::resolve( $single_ref );
                    $label_text = (string)($res['label_text'] ?? '');
                    $icon_id    = (int)($res['icon_id'] ?? 0);
                }

                if ( $single_hide === null ) { $single_hide = ! empty( $item['hide_icon'] ); }

                $row_order = $order_class;
                $value     = (string)$item['price'];

                echo '      <div class="jp-price-row ' . esc_attr( $row_order ) . '">';
                if ( $order_class === 'jp-order--label-left' ) {
                    echo '          <div class="jp-col-label">' . $this->label_html( $label_text, $icon_id, $presentation, (bool)$single_hide ) . '</div>';
                    echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                } else {
                    echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                    echo '          <div class="jp-col-label">' . $this->label_html( $label_text, $icon_id, $presentation, (bool)$single_hide ) . '</div>';
                }
                echo '      </div>';
            }

            // MULTIPLE prices
            if ( $has_multi_rows ) {
                foreach ( $item['prices'] as $row ) {
                    if ( empty($row['price']) ) continue;
                    $value     = (string)$row['price'];
                    $ref       = isset($row['label_ref']) ? (string)$row['label_ref'] : '';
                    $hide_icon = ! empty( $row['hide_icon'] );

                    $label_text = '';
                    $icon_id    = 0;
                    if ( $ref !== '' && class_exists('JPRM_Labels_Store') ) {
                        $res = \JPRM_Labels_Store::resolve( $ref );
                        $label_text = (string)($res['label_text'] ?? '');
                        $icon_id    = (int)($res['icon_id'] ?? 0);
                    }

                    $row_order = $order_class;
                    echo '      <div class="jp-price-row ' . esc_attr( $row_order ) . '">';
                    if ( $order_class === 'jp-order--label-left' ) {
                        echo '          <div class="jp-col-label">' . $this->label_html( $label_text, $icon_id, $presentation, (bool)$hide_icon ) . '</div>';
                        echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                    } else {
                        echo '          <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                        echo '          <div class="jp-col-label">' . $this->label_html( $label_text, $icon_id, $presentation, (bool)$hide_icon ) . '</div>';
                    }
                    echo '      </div>';
                }
            }

            echo '    </div>'; // .jp-menu__pricegroup
            echo '  </div>';   // .jp-menu__inner
            echo '</li>';
        }

        echo '</ul>';
    }

    /* =========================
     * Helpers
     * ========================= */

    protected function is_elementor_edit_mode() : bool {
        if ( class_exists('\Elementor\Plugin') ) {
            $inst = \Elementor\Plugin::$instance;
            if ( isset($inst->editor) && method_exists($inst->editor, 'is_edit_mode') ) {
                return (bool) $inst->editor->is_edit_mode();
            }
        }
        return false;
    }

    protected function get_terms_options( string $taxonomy ) : array {
        $out = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $out;
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return $out;
        foreach ( $terms as $t ) {
            $name = isset($t->name) ? (string)$t->name : '';
            $slug = isset($t->slug) ? (string)$t->slug : '';
            if ( $slug === '' ) continue;
            $out[ $slug ] = $name !== '' ? sprintf( '%s (%s)', $name, $slug ) : $slug;
        }
        return $out;
    }

    protected function normalize_to_slugs( array $values, string $taxonomy ) : array {
        if ( empty($values) ) return [];
        $slugs = [];
        foreach ( $values as $v ) {
            if ( is_string($v) && ! ctype_digit($v) ) { $slugs[] = $v; continue; }
            $term_id = is_numeric($v) ? (int)$v : 0;
            if ( $term_id > 0 ) {
                $term = get_term( $term_id, $taxonomy );
                if ( $term && ! is_wp_error($term) && ! empty($term->slug) ) $slugs[] = $term->slug;
            }
        }
        return array_values( array_unique( $slugs ) );
    }

    protected function autodetect_context_slugs() : array {
        $slugs = [ 'menus' => [], 'sections' => [] ];
        $post = get_post();
        if ( ! $post ) return $slugs;

        foreach ( [ 'jprm_menu', 'jprm_section' ] as $tax ) {
            if ( ! taxonomy_exists( $tax ) ) continue;
            $terms = wp_get_post_terms( $post->ID, $tax, [ 'fields' => 'slugs' ] );
            if ( is_wp_error( $terms ) || empty( $terms ) ) continue;
            if ( $tax === 'jprm_menu' )    $slugs['menus']    = $terms;
            if ( $tax === 'jprm_section' ) $slugs['sections'] = $terms;
        }
        return $slugs;
    }

    /** Safely get first non-empty meta from a list of keys (string result). */
    protected function first_meta_string( int $pid, array $keys ) : string {
        foreach ( $keys as $k ) {
            $v = get_post_meta( $pid, $k, true );
            if ( is_string($v) && $v !== '' ) return $v;
        }
        return '';
    }

    /** Try to decode a meta value into an array: JSON → maybe_unserialize → ensure array. */
    protected function meta_to_array( $raw ) : array {
        if ( is_array( $raw ) ) return $raw;

        if ( is_string( $raw ) ) {
            $raw = trim( $raw );
            if ( $raw === '' ) return [];
            // JSON?
            $j = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($j) ) return $j;

            // Serialized?
            $maybe = @maybe_unserialize( $raw );
            if ( is_array( $maybe ) ) return $maybe;
        }
        return [];
    }

    /** Normalize a loose array of price rows into [ [price, label_ref, hide_icon], ... ] */
    protected function normalize_rows( array $rows_in ) : array {
        $out = [];
        // Pattern A: list of row arrays
        if ( isset($rows_in[0]) && is_array($rows_in[0]) ) {
            foreach ( $rows_in as $r ) {
                $price = '';
                if ( isset($r['price']) ) $price = (string)$r['price'];
                elseif ( isset($r['amount']) ) $price = (string)$r['amount'];
                elseif ( isset($r['value']) ) $price = (string)$r['value'];

                if ( $price === '' ) continue;

                $ref  = '';
                if ( isset($r['label_ref']) ) $ref = (string)$r['label_ref'];
                elseif ( isset($r['label']) ) $ref = (string)$r['label'];
                elseif ( isset($r['ref']) ) $ref = (string)$r['ref'];

                $hide = false;
                if ( isset($r['hide_icon']) ) $hide = (bool)$r['hide_icon'];
                elseif ( isset($r['hide']) ) $hide = (bool)$r['hide'];
                elseif ( isset($r['icon_hidden']) ) $hide = (bool)$r['icon_hidden'];

                $out[] = [
                    'price'     => $price,
                    'label_ref' => $ref,
                    'hide_icon' => $hide,
                ];
            }
            return $out;
        }

        // Pattern B: list of scalars (just prices)
        if ( isset($rows_in[0]) && ! is_array($rows_in[0]) ) {
            foreach ( $rows_in as $p ) {
                $p = (string)$p;
                if ( $p === '' ) continue;
                $out[] = [ 'price' => $p, 'label_ref' => '', 'hide_icon' => false ];
            }
            return $out;
        }

        // Pattern C: assoc with separate arrays (already handled elsewhere), keep noop here
        return $out;
    }

    /**
     * Collect dynamic items from whichever CPT slug(s) exist: jprm_item, jprm_menu_item.
     */
    protected function collect_dynamic_items( array $s ) : array {
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) return $filtered;

        // Determine available CPTs
        $candidate_slugs = array_values( array_filter( [
            post_type_exists('jprm_item') ? 'jprm_item' : null,
            post_type_exists('jprm_menu_item') ? 'jprm_menu_item' : null,
        ] ) );
        if ( empty( $candidate_slugs ) ) {
            return [];
        }

        $menus_in    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? $s['query_menus']    : [];
        $sections_in = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? $s['query_sections'] : [];

        $menus    = $this->normalize_to_slugs( $menus_in, 'jprm_menu' );
        $sections = $this->normalize_to_slugs( $sections_in, 'jprm_section' );

        $auto = isset($s['auto_detect_context']) && $s['auto_detect_context'] === 'yes';
        if ( $auto && empty($menus) && empty($sections) ) {
            $ctx = $this->autodetect_context_slugs();
            $menus    = $ctx['menus'];
            $sections = $ctx['sections'];
        }

        $orderby  = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order    = ! empty( $s['query_order'] )   ? sanitize_text_field( $s['query_order'] )   : 'ASC';
        $limit    = ( isset( $s['query_limit'] ) && $s['query_limit'] !== '' ) ? (int) $s['query_limit'] : -1;

        $tax_query = [ 'relation' => 'AND' ];
        if ( ! empty($menus) ) {
            $tax_query[] = [ 'taxonomy' => 'jprm_menu', 'field' => 'slug', 'terms' => $menus ];
        }
        if ( ! empty($sections) ) {
            $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'slug', 'terms' => $sections ];
        }

        $fallback_all = isset($s['show_all_when_empty']) && $s['show_all_when_empty'] === 'yes';
        if ( $fallback_all && count($tax_query) === 1 ) { $tax_query = []; }
        elseif ( ! $fallback_all && count($tax_query) === 1 ) { return []; }

        // Query across whichever CPTs exist
        $q = new \WP_Query( [
            'post_type'      => count($candidate_slugs) === 1 ? $candidate_slugs[0] : $candidate_slugs,
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
            'posts_per_page' => $limit,
            'tax_query'      => $tax_query,
        ] );
        if ( ! $q->have_posts() ) return [];

        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $items[] = $this->build_item_from_post( get_the_ID() );
        }
        wp_reset_postdata();

        // Frontend visibility filter
        if ( ! $this->is_elementor_edit_mode() && isset($s['hide_invisible']) && $s['hide_invisible'] === 'yes' ) {
            $items = array_filter( $items, function($it){
                return apply_filters( 'jprm/item/is_visible', true, $it );
            } );
        }

        return $items;
    }

    /**
     * Ultra-safe fallback used only in Elementor edit mode.
     */
    protected function collect_dynamic_items_fallback_all() : array {
        $q = new \WP_Query( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => 'jprm_price',            'compare' => 'EXISTS' ],
                [ 'key' => 'jprm_price_rows',       'compare' => 'EXISTS' ],
                [ 'key' => 'single_price',          'compare' => 'EXISTS' ],
                [ 'key' => '_jprm_price',           'compare' => 'EXISTS' ],
                [ 'key' => '_jprm_price_amounts',   'compare' => 'EXISTS' ],
                [ 'key' => '_jprm_price_labels',    'compare' => 'EXISTS' ],
                [ 'key' => '_jprm_price_hideicons', 'compare' => 'EXISTS' ],
                [ 'key' => 'jprm_price_v2',         'compare' => 'EXISTS' ],
                [ 'key' => 'jprm_prices',           'compare' => 'EXISTS' ],
                [ 'key' => 'prices',                'compare' => 'EXISTS' ],
            ],
        ] );
        if ( ! $q->have_posts() ) return [];
        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $items[] = $this->build_item_from_post( get_the_ID() );
        }
        wp_reset_postdata();
        return $items;
    }

    /** Build one item array from a post ID (robust across versions & key shapes). */
    protected function build_item_from_post( int $pid ) : array {
        // --- Description (broadened keys) ---
        $desc = $this->first_meta_string( $pid, [ 'jprm_description', '_jprm_description', 'description', '_description' ] );
        if ( $desc === '' ) {
            $excerpt = get_post_field( 'post_excerpt', $pid );
            if ( ! is_string($excerpt) || $excerpt === '' ) {
                $content = get_post_field( 'post_content', $pid );
                $desc = is_string($content) ? trim( wp_strip_all_tags( strip_shortcodes( $content ) ) ) : '';
            } else {
                $desc = $excerpt;
            }
        }

        // --- v3 JSON (preferred) ---
        $cfg      = [];
        $cfg_json = get_post_meta( $pid, 'jprm_price', true );
        if ( is_string($cfg_json) && $cfg_json !== '' ) {
            $tmp = json_decode($cfg_json, true);
            if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $cfg = $tmp;
        }

        $single_price     = '';
        $single_label_ref = '';
        $single_hide_icon = false;
        $rows             = [];

        if ( ! empty($cfg) && is_array($cfg) ) {
            $mode = isset($cfg['mode']) ? (string)$cfg['mode'] : '';
            if ( $mode === 'single' ) {
                $single_price     = isset($cfg['price']) ? (string)$cfg['price'] : '';
                $single_label_ref = isset($cfg['label_ref']) ? (string)$cfg['label_ref'] : '';
                $single_hide_icon = ! empty($cfg['hide_icon']);
            } elseif ( $mode === 'multi' && ! empty($cfg['rows']) && is_array($cfg['rows']) ) {
                foreach ( $cfg['rows'] as $r ) {
                    if ( empty($r['price']) ) continue;
                    $rows[] = [
                        'price'     => (string)$r['price'],
                        'label_ref' => isset($r['label_ref']) ? (string)$r['label_ref'] : '',
                        'hide_icon' => ! empty($r['hide_icon']),
                    ];
                }
            }
        }

        // --- Legacy JSON rows ---
        if ( empty($rows) ) {
            $rows_json = get_post_meta( $pid, 'jprm_price_rows', true );
            if ( is_string($rows_json) && $rows_json !== '' ) {
                $t = json_decode($rows_json, true);
                if ( json_last_error() === JSON_ERROR_NONE && is_array($t) ) {
                    $rows = $this->normalize_rows( $t );
                }
            }
        }

        // --- Legacy split arrays (_jprm_price_amounts/_labels/_hideicons) ---
        if ( empty($rows) ) {
            $amts = get_post_meta( $pid, '_jprm_price_amounts', true );
            $labs = get_post_meta( $pid, '_jprm_price_labels', true );
            $hids = get_post_meta( $pid, '_jprm_price_hideicons', true );

            $amts = $this->meta_to_array( $amts );
            $labs = $this->meta_to_array( $labs );
            $hids = $this->meta_to_array( $hids );

            if ( is_array($amts) && ! empty($amts) ) {
                $max = max( count($amts), is_array($labs)?count($labs):0, is_array($hids)?count($hids):0 );
                for ( $i = 0; $i < $max; $i++ ) {
                    $p = isset($amts[$i]) ? (string)$amts[$i] : '';
                    if ( $p === '' ) continue;
                    $rows[] = [
                        'price'     => $p,
                        'label_ref' => isset($labs[$i]) ? (string)$labs[$i] : '',
                        'hide_icon' => ! empty($hids[$i]),
                    ];
                }
            }
        }

        // --- Broad multi price keys: jprm_prices / prices (JSON, serialized, or array) ---
        if ( empty($rows) ) {
            foreach ( [ 'jprm_prices', 'prices' ] as $k ) {
                $raw = get_post_meta( $pid, $k, true );
                $arr = $this->meta_to_array( $raw );
                if ( ! empty( $arr ) ) {
                    $rows = $this->normalize_rows( $arr );
                    if ( ! empty($rows) ) break;
                }
            }
        }

        // --- Legacy single ---
        if ( $single_price === '' ) {
            $sp = get_post_meta( $pid, 'single_price', true );
            if ( is_string($sp) && $sp !== '' ) {
                $single_price = $sp;
            }
        }
        if ( $single_label_ref === '' ) {
            $single_label_ref = get_post_meta( $pid, '_jprm_single_label_ref', true );
            if ( ! is_string($single_label_ref) ) $single_label_ref = '';
        }
        $single_hide_icon = $single_hide_icon || ! empty( get_post_meta( $pid, '_jprm_single_hide_icon', true ) );

        // --- Item-level defaults ---
        $labels    = get_post_meta( $pid, '_jprm_labels', true );
        $labels    = is_array($labels) ? $labels : $this->meta_to_array($labels);
        $hide_icon = ! empty( get_post_meta( $pid, '_jprm_hide_icon', true ) );

        return [
            'ID'               => $pid,
            'title'            => get_the_title( $pid ),
            'description'      => is_string($desc) ? $desc : '',
            'price_cfg'        => $cfg,
            'price'            => $single_price,
            'prices'           => $rows,
            'single_label_ref' => $single_label_ref,
            'single_hide_icon' => (bool)$single_hide_icon,
            'labels'           => is_array($labels) ? $labels : [],
            'hide_icon'        => (bool)$hide_icon,
        ];
    }

    protected function label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
        $icon_html = '';
        if ( ! $hide_icon && $icon_id > 0 ) {
            $img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
            if ( is_string($img) ) $icon_html = $img;
        }
        if ( $presentation === 'icon' )      return $icon_html;
        if ( $presentation === 'text' )      return esc_html( $label_text );
        if ( $presentation === 'icon_text' ) return $icon_html ? ($icon_html . ' ' . esc_html($label_text)) : esc_html($label_text);
        return esc_html( $label_text );
    }
}
