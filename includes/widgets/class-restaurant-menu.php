<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Clean reader for the current schema:
 * - CPT: jprm_menu_item
 * - Description: jprm_desc
 * - Prices: jprm_price JSON (mode: single|multi)
 * - Labels: option jprm_price_labels_v2 (id/slug -> label + icon), or custom text per row with optional icon_id
 */
class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','card','food','price','prices','items' ]; }

    // Load the plugin stylesheet (registered elsewhere)
    public function get_style_depends() { return [ 'jprm-menu' ]; }

    /* =========================
     * Controls
     * ========================= */
    protected function register_controls() {
        $menu_options    = $this->get_terms_options( 'jprm_menu' );
        $section_options = $this->get_terms_options( 'jprm_section' );

        $this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

        $this->add_control( 'auto_detect_context', [
            'label'        => __( 'Auto-detect context (this page terms)', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'show_all_when_empty', [
            'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
            'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'query_menus', [
            'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $menu_options,
            'label_block' => true,
        ] );

        $this->add_control( 'query_sections', [
            'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $section_options,
            'label_block' => true,
        ] );

        $this->add_control( 'query_orderby', [
            'label'   => __( 'Order by', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'menu_order',
            'options' => [
                'menu_order' => __( 'Menu order', 'jellopoint-restaurant-menu' ),
                'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
                'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        $this->add_control( 'query_order', [
            'label'   => __( 'Order', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'ASC',
            'options' => [ 'ASC' => 'ASC', 'DESC' => 'DESC' ],
        ] );

        $this->add_control( 'query_limit', [
            'label'       => __( 'Items limit', 'jellopoint-restaurant-menu' ),
            'description' => __( 'Leave empty for no limit.', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::NUMBER,
            'default'     => '',
            'min'         => 1,
            'step'        => 1,
        ] );

        $this->end_controls_section();

        /* ---- Presentation ---- */
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

        /* ---- Static items (kept minimal for parity, optional to hide in UI) ---- */
        $this->start_controls_section( 'section_static', [ 'label' => __( 'Static Items', 'jellopoint-restaurant-menu' ) ] );
        $rep = new Repeater();
        $rep->add_control( 'item_title', [ 'label' => __( 'Title', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT ] );
        $rep->add_control( 'item_description', [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA ] );
        $rep->add_control( 'item_price', [ 'label' => __( 'Price', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT ] );

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'title_field' => '{{{ item_title }}}',
        ] );
        $this->end_controls_section();
    }

    /* =========================
     * Render
     * ========================= */
    protected function render() {
        $s = $this->get_settings_for_display();

        // If static items provided, render those and exit.
        if ( ! empty( $s['items'] ) ) {
            $this->render_static_list( (array) $s['items'] );
            return;
        }

        // Dynamic: strictly from jprm_menu_item
        if ( ! post_type_exists( 'jprm_menu_item' ) ) {
            echo '<div class="jp-menu--empty">' . esc_html__( 'Menu item type not registered (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
            return;
        }

        $items = $this->query_items( $s );
        if ( empty( $items ) ) {
            echo '<div class="jp-menu--empty">' . esc_html__( 'No items found for this selection.', 'jellopoint-restaurant-menu' ) . '</div>';
            return;
        }

        // Label registry map (id + slug → [label_text, icon_id])
        $label_map = $this->build_label_map();

        $presentation = isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'icon_text';
        $order_class  = ( isset( $s['label_position'] ) && $s['label_position'] === 'left' )
            ? 'jp-order--label-left' : 'jp-order--label-right';

        echo '<ul class="jp-menu">';

        foreach ( $items as $pid ) {
            $title = get_the_title( $pid );
            $desc  = get_post_meta( $pid, 'jprm_desc', true ); // exact key
            $cfg   = $this->read_price_config( $pid );         // jprm_price only

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            // Left: title + description
            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( is_string($desc) && $desc !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            // Right: prices
            echo '    <div class="jp-menu__pricegroup">';

            if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
                // Resolve label (registry by id/slug or treat as custom)
                $resolved = $this->resolve_label_ref( $cfg['label_ref'], $label_map, $cfg['icon_id'] ?? 0 );
                $label_html = $this->label_html( $resolved['text'], $resolved['icon_id'], $presentation, (bool)$cfg['hide_icon'] );

                echo '      <div class="jp-price-row ' . esc_attr( $order_class ) . '">';
                if ( $order_class === 'jp-order--label-left' ) {
                    echo '        <div class="jp-col-label">' . $label_html . '</div>';
                    echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
                } else {
                    echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
                    echo '        <div class="jp-col-label">' . $label_html . '</div>';
                }
                echo '      </div>';
            }

            if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
                foreach ( $cfg['rows'] as $row ) {
                    $price     = (string) ( $row['value'] ?? '' );
                    if ( $price === '' ) continue;
                    $ref       = (string) ( $row['label_ref'] ?? '' );
                    $hide_icon = (bool)   ( $row['hide_icon'] ?? false );
                    $icon_id   = (int)    ( $row['icon_id'] ?? 0 );

                    $resolved  = $this->resolve_label_ref( $ref, $label_map, $icon_id );
                    $label_html= $this->label_html( $resolved['text'], $resolved['icon_id'], $presentation, $hide_icon );

                    echo '      <div class="jp-price-row ' . esc_attr( $order_class ) . '">';
                    if ( $order_class === 'jp-order--label-left' ) {
                        echo '        <div class="jp-col-label">' . $label_html . '</div>';
                        echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                    } else {
                        echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                        echo '        <div class="jp-col-label">' . $label_html . '</div>';
                    }
                    echo '      </div>';
                }
            }

            echo '    </div>'; // pricegroup
            echo '  </div>';   // inner
            echo '</li>';
        }

        echo '</ul>';
    }

    /* =========================
     * Data access (CLEAN)
     * ========================= */

    /** Query jprm_menu_item posts respecting menus/sections + editor fallback */
    protected function query_items( array $s ) : array {
        $menus    = isset($s['query_menus']) && is_array($s['query_menus']) ? $s['query_menus'] : [];
        $sections = isset($s['query_sections']) && is_array($s['query_sections']) ? $s['query_sections'] : [];

        // Normalize to slugs (Elementor may save IDs)
        $menus    = $this->normalize_to_slugs( $menus, 'jprm_menu' );
        $sections = $this->normalize_to_slugs( $sections, 'jprm_section' );

        if ( $s['auto_detect_context'] ?? 'yes' === 'yes' ) {
            if ( empty($menus) || empty($sections) ) {
                $ctx = $this->autodetect_context_slugs();
                if ( empty($menus) )    $menus    = $ctx['menus'];
                if ( empty($sections) ) $sections = $ctx['sections'];
            }
        }

        $orderby  = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order    = ! empty( $s['query_order'] )   ? sanitize_text_field( $s['query_order'] )   : 'ASC';
        $limit    = ( isset( $s['query_limit'] ) && $s['query_limit'] !== '' ) ? (int)$s['query_limit'] : -1;

        $tax_query = [ 'relation' => 'AND' ];
        if ( ! empty($menus) )    $tax_query[] = [ 'taxonomy' => 'jprm_menu',    'field' => 'slug', 'terms' => $menus ];
        if ( ! empty($sections) ) $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'slug', 'terms' => $sections ];

        $fallback_all = ($s['show_all_when_empty'] ?? 'yes') === 'yes';
        if ( $fallback_all && count($tax_query) === 1 ) $tax_query = [];
        elseif ( ! $fallback_all && count($tax_query) === 1 ) return [];

        $q = new \WP_Query( [
            'post_type'      => 'jprm_menu_item',
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
            'posts_per_page' => $limit,
            'tax_query'      => $tax_query,
            'no_found_rows'  => true,
        ] );

        if ( ! $q->have_posts() ) return [];
        $ids = [];
        while ( $q->have_posts() ) { $q->the_post(); $ids[] = get_the_ID(); }
        wp_reset_postdata();
        return $ids;
    }

    /** Read jprm_price JSON for a post; return normalized array */
    protected function read_price_config( int $pid ) : array {
        $cfg = [ 'mode' => '', 'price' => '', 'label_ref' => '', 'hide_icon' => false, 'rows' => [] ];

        $raw = get_post_meta( $pid, 'jprm_price', true );
        if ( ! is_string($raw) || $raw === '' ) return $cfg;

        $dec = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array($dec) ) return $cfg;

        $mode = isset($dec['mode']) ? (string)$dec['mode'] : '';
        if ( $mode === 'single' ) {
            $cfg['mode']      = 'single';
            $cfg['price']     = isset($dec['price']) ? (string)$dec['price'] : '';
            $cfg['label_ref'] = isset($dec['label_ref']) ? (string)$dec['label_ref'] : '';
            $cfg['hide_icon'] = ! empty($dec['hide_icon']);
            if ( isset($dec['icon_id']) ) $cfg['icon_id'] = (int)$dec['icon_id']; // optional custom icon for single
        } elseif ( $mode === 'multi' ) {
            $cfg['mode'] = 'multi';
            $rows = [];
            if ( ! empty($dec['rows']) && is_array($dec['rows']) ) {
                foreach ( $dec['rows'] as $r ) {
                    $value = isset($r['value']) ? (string)$r['value'] : '';
                    if ( $value === '' ) continue;
                    $rows[] = [
                        'label_ref' => isset($r['label_ref']) ? (string)$r['label_ref'] : '',
                        'value'     => $value,
                        'hide_icon' => ! empty($r['hide_icon']),
                        'icon_id'   => isset($r['icon_id']) ? (int)$r['icon_id'] : 0,
                    ];
                }
            }
            $cfg['rows'] = $rows;
        }
        return $cfg;
    }

    /** Build map from option jprm_price_labels_v2: id + slug → [text, icon_id] */
    protected function build_label_map() : array {
        $opt = get_option( 'jprm_price_labels_v2' );
        $list = is_string($opt) ? json_decode($opt, true) : ( is_array($opt) ? $opt : [] );
        $map = [];
        if ( is_array($list) ) {
            foreach ( $list as $row ) {
                $id   = isset($row['id']) ? (string)$row['id'] : '';
                $slug = isset($row['slug']) ? (string)$row['slug'] : '';
                $lab  = isset($row['label']) ? (string)$row['label'] : '';
                $ico  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
                if ( $id !== '' )   $map[ $id ]   = [ 'text' => $lab, 'icon_id' => $ico ];
                if ( $slug !== '' ) $map[ $slug ] = [ 'text' => $lab, 'icon_id' => $ico ];
            }
        }
        return $map;
    }

    /** Resolve a label ref against registry; or treat ref as custom text with optional icon override */
    protected function resolve_label_ref( string $ref, array $map, int $icon_override = 0 ) : array {
        $ref = trim( $ref );
        if ( $ref === '' ) {
            return [ 'text' => '', 'icon_id' => 0 ];
        }
        if ( isset( $map[ $ref ] ) ) {
            return [ 'text' => (string)$map[$ref]['text'], 'icon_id' => (int)$map[$ref]['icon_id'] ];
        }
        // Custom text path; allow row-defined icon_id
        return [ 'text' => $ref, 'icon_id' => $icon_override ];
    }

    /* =========================
     * Small helpers
     * ========================= */

    protected function render_static_list( array $items ) : void {
        echo '<ul class="jp-menu">';
        foreach ( $items as $it ) {
            $title = $it['item_title'] ?? '';
            $desc  = $it['item_description'] ?? '';
            $price = $it['item_price'] ?? '';
            echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
            echo '  <div class="jp-menu__content">';
            if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '  </div>';
            echo '  <div class="jp-menu__pricegroup">';
            if ( $price !== '' ) echo '    <div class="jp-price-row jp-order--label-right"><span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span></div>';
            echo '  </div>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    /** Build select options for a taxonomy (slug => "Name (slug)") */
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

    /** Normalize possible term IDs to slugs (Elementor can save IDs on SELECT2) */
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

    /** Auto-detect context terms from current post (slugs) */
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

    /** Render a label+icon combo according to presentation rules. */
    protected function label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
        $label_text = (string) $label_text;
        $icon_html  = '';
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
