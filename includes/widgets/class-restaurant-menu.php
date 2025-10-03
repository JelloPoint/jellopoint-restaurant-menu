<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Stable rendering: title, description, single/multiple prices, labels & icons.
 * Minimal extras: editor fallback + legacy term ID normalization.
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

        // Keep the two toggles that helped before
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

        /* ---- Static items (simple) ---- */
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

        // Dynamic data (stable behavior)
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
            $cfg   = $item['price_cfg'] ?? []; // (kept for future use)

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            if ( $desc  !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            // Multi rows?
            $has_multi_rows = ! empty( $item['prices'] ) && is_array( $item['prices'] );

            // SINGLE price (stable logic)
            if ( ! $has_multi_rows && isset( $item['price'] ) && $item['price'] !== '' ) {
                $label_text = '';
                $icon_id    = 0;
                $hide_icon  = false;

                $single_ref  = isset($item['single_label_ref']) ? (string)$item['single_label_ref'] : '';
                $single_hide = isset($item['single_hide_icon']) ? (bool)$item['single_hide_icon'] : null;

                // fallback to first item-level label if not set
                if ( $single_ref === '' && ! empty( $item['labels'] ) && is_array( $item['labels'] ) ) {
                    $single_ref = (string) reset( $item['labels'] );
                }
                // last resort: scan meta (legacy)
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

            // MULTIPLE prices (stable logic)
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

    /** Primary collector honoring controls/filters — STABLE */
    protected function collect_dynamic_items( array $s ) : array {
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) return $filtered;

        // Hard-lock to the known CPT that worked before
        $post_type = 'jprm_item';

        $menus_in    = ( ! empty( $s['query_menus'] )    && is_array( $s['query_menus'] ) )    ? $s['query_menus']    : [];
        $sections_in = ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) ? $s['query_sections'] : [];

        // Support legacy IDs stored in saved widgets
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

        // Fallback to all items if still empty and user allows it
        $fallback_all = isset($s['show_all_when_empty']) && $s['show_all_when_empty'] === 'yes';
        if ( $fallback_all && count($tax_query) === 1 ) { $tax_query = []; }
        elseif ( ! $fallback_all && count($tax_query) === 1 ) { return []; }

        $q = new \WP_Query( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
            'posts_per_page' => $limit,
            'tax_query'      => $tax_query,
        ] );
        if ( ! $q->have_posts() ) return [];

        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $pid   = get_the_ID();
            $title = get_the_title();
            $desc  = get_post_meta( $pid, 'jprm_description', true );
            $desc  = is_string($desc) ? $desc : '';

            // v3 cfg (if present; we still render from legacy arrays here)
            $cfg_json = get_post_meta( $pid, 'jprm_price', true );
            $cfg      = [];
            if ( is_string($cfg_json) && $cfg_json !== '' ) {
                $tmp = json_decode($cfg_json, true);
                if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $cfg = $tmp;
            }

            // Legacy single/multiple (STABLE)
            $single_price = get_post_meta( $pid, 'single_price', true );

            $rows_json = get_post_meta( $pid, 'jprm_price_rows', true );
            $rows      = [];
            if ( is_string($rows_json) && $rows_json !== '' ) {
                $t = json_decode($rows_json, true);
                if ( json_last_error() === JSON_ERROR_NONE && is_array($t) ) $rows = $t;
            }

            $items[] = [
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
        wp_reset_postdata();

        // Apply visibility filter (only on frontend to avoid empty editor)
        if ( ! $this->is_elementor_edit_mode() && isset($s['hide_invisible']) && $s['hide_invisible'] === 'yes' ) {
            $items = array_filter( $items, function($it){
                return apply_filters( 'jprm/item/is_visible', true, $it );
            } );
        }

        return $items;
    }

    /** Ultra-safe fallback used only in Elementor edit mode if primary query found nothing. */
    protected function collect_dynamic_items_fallback_all() : array {
        $q = new \WP_Query( [
            'post_type'      => 'jprm_item',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ] );
        if ( ! $q->have_posts() ) return [];
        $items = [];
        while ( $q->have_posts() ) { $q->the_post();
            $pid   = get_the_ID();
            $title = get_the_title();
            $desc  = get_post_meta( $pid, 'jprm_description', true );
            $desc  = is_string($desc) ? $desc : '';

            $cfg_json = get_post_meta( $pid, 'jprm_price', true );
            $cfg      = [];
            if ( is_string($cfg_json) && $cfg_json !== '' ) {
                $tmp = json_decode($cfg_json, true);
                if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $cfg = $tmp;
            }

            $single_price = get_post_meta( $pid, 'single_price', true );

            $rows_json = get_post_meta( $pid, 'jprm_price_rows', true );
            $rows      = [];
            if ( is_string($rows_json) && $rows_json !== '' ) {
                $t = json_decode($rows_json, true);
                if ( json_last_error() === JSON_ERROR_NONE && is_array($t) ) $rows = $t;
            }

            $items[] = [
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
        wp_reset_postdata();
        return $items;
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
 