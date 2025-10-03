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

    // Load the plugin stylesheet registered in the plugin bootstrap.
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

        // Auto-detect + fallback controls
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

        /* ---- Presentation controls (labels/icons order & type) ---- */
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
     * Render (entry)
     * ========================= */
    protected function render() {
        $s = $this->get_settings_for_display();

        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();
            return;
        }

        // Dynamic data
        $items = $this->collect_dynamic_items( $s );

        // Editor safety net: if nothing came back, force a loose query so preview never shows empty
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

            // Multi rows?
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

    /** Elementor edit mode? */
    protected function is_elementor_edit_mode() : bool {
        if ( class_exists('\Elementor\Plugin') ) {
            $inst = \Elementor\Plugin::$instance;
            if ( isset($inst->editor) && method_exists($inst->editor, 'is_edit_mode') ) {
                return (bool) $inst->editor->is_edit_mode();
            }
        }
        return false;
    }

    /** Resolve the correct Menu Item post type at runtime. */
    protected function resolve_item_post_type() : string {
        // 1) Preferred slug
        if ( post_type_exists('jprm_item') ) return 'jprm_item';

        // 2) Common alternatives
        $candidates = [ 'jprm_menu_item', 'jprm_menuitem', 'jp_menu_item', 'menu_item' ];
        foreach ( $candidates as $pt ) {
            if ( post_type_exists( $pt ) ) return $pt;
        }

        // 3) Probe across ANY post type: find a post that has our price meta
        $probe = new \WP_Query( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_query'     => [
                [
                    'key'     => 'jprm_price',
                    'compare' => 'EXISTS',
                ]
            ],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        if ( $probe->have_posts() ) {
            $probe->the_post();
            $pt = get_post_type( get_the_ID() );
            wp_reset_postdata();
            if ( is_string($pt) && $pt !== '' ) return $pt;
        }
        wp_reset_postdata();

        // Final fallback to the historical default
        return 'jprm_item';
    }

    /** Build select options for a taxonomy (slug => "Name (slug)"). */
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

    /** Normalize possible term IDs to slugs. */
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

    /** Auto-detect context terms from current post. */
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

    /** Primary collector honoring controls/filters. */
    protected function collect_dynamic_items( array $s ) : array {
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) return $filtered;

        $post_type = $this->resolve_item_post_type();

        $menus_in    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? $s['query_menus']    : [];
        $sections_in = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? $s['query_sections'] : [];

        $menus    = $this->normalize_to_slugs( $menus_in, 'jprm_menu' );
        $sections = $this->normalize_to_slugs( $sections_in, 'jprm_section' );

        // Auto-detect from current post if enabled and nothing selected
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

        $args = [
            'post_type'      => $post_type,
            'post_status'    => $this->is_elementor_edit_mode() ? 'any' : 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
            'posts_per_page' => $limit,
            'tax_query'      => $tax_query,
        ];

        $q = new \WP_Query( $args );
        if ( ! $q->have_posts() ) return [];

        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $items[] = $this->build_item_from_post( get_the_ID() );
        }
        wp_reset_postdata();

        // Apply visibility filter only outside Elementor edit mode
        if ( ! $this->is_elementor_edit_mode() && isset($s['hide_invisible']) && $s['hide_invisible'] === 'yes' ) {
            $items = array_filter( $items, function($it){
                return apply_filters( 'jprm/item/is_visible', true, $it );
            } );
        }

        return $items;
    }

    /** Ultra-safe fallback used only in Elementor edit mode if primary query found nothing. */
    protected function collect_dynamic_items_fallback_all() : array {
        $post_type = $this->resolve_item_post_type();

        $q = new \WP_Query( [
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ] );
        if ( ! $q->have_posts() ) {
            // As a last resort, try ANY post type with our meta key in editor
            $q = new \WP_Query( [
                'post_type'      => 'any',
                'post_status'    => 'any',
                'meta_query'     => [ [ 'key' => 'jprm_price', 'compare' => 'EXISTS' ] ],
                'posts_per_page' => -1,
            ] );
            if ( ! $q->have_posts() ) return [];
        }
        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $items[] = $this->build_item_from_post( get_the_ID() );
        }
        wp_reset_postdata();
        return $items;
    }

    /** Build one item array from a post ID (kept identical to your previous pipeline). */
    protected function build_item_from_post( int $pid ) : array {
        $title = get_the_title( $pid );
        $desc  = get_post_meta( $pid, 'jprm_description', true );
        $desc  = is_string($desc) ? $desc : '';

        $cfg_json = get_post_meta( $pid, 'jprm_price', true );
        $cfg      = [];
        if ( is_string($cfg_json) && $cfg_json !== '' ) {
            $tmp = json_decode($cfg_json, true);
            if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $cfg = $tmp;
        }

        // Legacy fallbacks (temporary)
        $single_price = get_post_meta( $pid, 'single_price', true );
        $rows_json    = get_post_meta( $pid, 'jprm_price_rows', true );
        $rows         = [];
        if ( is_string($rows_json) && $rows_json !== '' ) {
            $t = json_decode($rows_json, true);
            if ( json_last_error() === JSON_ERROR_NONE && is_array($t) ) $rows = $t;
        }

        return [
            'ID'          => $pid,
            'title'       => $title,
            'description' => $desc,
            'price_cfg'   => $cfg,
            'price'       => $single_price,
            'prices'      => $rows,
            'single_label_ref' => get_post_meta($pid, '_jprm_single_label_ref', true),
            'single_hide_icon' => ! empty( get_post_meta($pid, '_jprm_single_hide_icon', true) ),
            'labels'           => get_post_meta($pid, '_jprm_labels', true),
            'hide_icon'        => ! empty( get_post_meta($pid, '_jprm_hide_icon', true) ),
        ];
    }

    /** Render a label+icon combo according to presentation rules. */
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

    // No inline CSS here; stylesheet is loaded via get_style_depends()
    protected function print_inline_layout_css() { /* no-op */ }
}
