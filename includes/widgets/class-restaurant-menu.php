<?php
/**
 * Elementor Widget: JelloPoint Restaurant Menu
 *
 * - Dynamic mode: renders via [jprm_menu] shortcode (Menus taxonomy + Sections)
 * - Static mode: simple repeater (unchanged minimal)
 * - Adds "De-duplication" control passed to shortcode: deepest_only (default) | all_assigned | topmost_only
 */

namespace JelloPoint\RestaurantMenu\Widgets;

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class Restaurant_Menu extends Widget_Base {

    public function get_name() {
        return 'jprm_restaurant_menu';
    }

    public function get_title() {
        return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' );
    }

    public function get_icon() {
        return 'eicon-price-list';
    }

    public function get_categories() {
        return [ 'jellopoint-widgets' ];
    }

    public function get_keywords() {
        return [ 'menu', 'restaurant', 'price', 'list', 'food', 'drink' ];
    }

    protected function register_controls() {

        /* ===== Source ===== */
        $this->start_controls_section(
            'section_source',
            [ 'label' => __( 'Source', 'jellopoint-restaurant-menu' ), 'tab' => Controls_Manager::TAB_CONTENT ]
        );

        $this->add_control(
            'data_source',
            [
                'label'   => __( 'Source', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'dynamic' => __( 'Dynamic (taxonomies)', 'jellopoint-restaurant-menu' ),
                    'static'  => __( 'Static (manual list)', 'jellopoint-restaurant-menu' ),
                ],
                'default' => 'dynamic',
            ]
        );

        $this->end_controls_section();

        /* ===== Dynamic Query ===== */
        $this->start_controls_section(
            'section_query',
            [ 'label' => __( 'Dynamic / Query', 'jellopoint-restaurant-menu' ), 'tab' => Controls_Manager::TAB_CONTENT ]
        );

        $this->add_control(
            'query_menus',
            [
                'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
                'type'         => Controls_Manager::SELECT2,
                'options'      => $this->get_taxonomy_options( 'jprm_menu' ),
                'multiple'     => true,
                'label_block'  => true,
                'condition'    => [ 'data_source' => 'dynamic' ],
                'description'  => __( 'Select Menu terms. Items can belong to multiple menus.', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->add_control(
            'query_sections',
            [
                'label'        => __( 'Sections', 'jellopoint-restaurant-menu' ),
                'type'         => Controls_Manager::SELECT2,
                'options'      => $this->get_taxonomy_options( 'jprm_section', true ),
                'multiple'     => true,
                'label_block'  => true,
                'condition'    => [ 'data_source' => 'dynamic' ],
                'description'  => __( 'Optional: limit output to these Sections (with their sub-sections).', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->add_control(
            'query_orderby',
            [
                'label'     => __( 'Order By', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'menu_order' => __( 'Menu Order', 'jellopoint-restaurant-menu' ),
                    'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
                    'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
                ],
                'default'   => 'menu_order',
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'query_order',
            [
                'label'     => __( 'Order', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'ASC'  => __( 'ASC', 'jellopoint-restaurant-menu' ),
                    'DESC' => __( 'DESC', 'jellopoint-restaurant-menu' ),
                ],
                'default'   => 'ASC',
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'query_limit',
            [
                'label'     => __( 'Items Limit', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::NUMBER,
                'default'   => -1,
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'hide_invisible',
            [
                'label'        => __( 'Hide invisible items', 'jellopoint-restaurant-menu' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'row_order',
            [
                'label'     => __( 'Row layout', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'label_left' => __( 'Label left, Price right', 'jellopoint-restaurant-menu' ),
                    'price_left' => __( 'Price left, Label right', 'jellopoint-restaurant-menu' ),
                ],
                'default'   => 'label_left',
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'label_presentation',
            [
                'label'     => __( 'Label presentation', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'text' => __( 'Text', 'jellopoint-restaurant-menu' ),
                    //'icon' => __( 'Icon (if styled)', 'jellopoint-restaurant-menu' ),
                ],
                'default'   => 'text',
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'dedupe',
            [
                'label'     => __( 'De-duplication', 'jellopoint-restaurant-menu' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'deepest_only' => __( 'Deepest only (recommended)', 'jellopoint-restaurant-menu' ),
                    'all_assigned' => __( 'All assigned (parent + child)', 'jellopoint-restaurant-menu' ),
                    'topmost_only' => __( 'Topmost only', 'jellopoint-restaurant-menu' ),
                ],
                'default'   => 'deepest_only',
                'condition' => [ 'data_source' => 'dynamic' ],
                'description' => __( 'When an item is tagged to both a parent and a child section, choose where it should appear.', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->end_controls_section();

        /* ===== Static Items (minimal) ===== */
        $this->start_controls_section(
            'section_static',
            [ 'label' => __( 'Static Items', 'jellopoint-restaurant-menu' ), 'tab' => Controls_Manager::TAB_CONTENT, 'condition' => [ 'data_source' => 'static' ] ]
        );

        $rep = new Repeater();
        $rep->add_control( 'title', [
            'label'   => __( 'Title', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Menu item', 'jellopoint-restaurant-menu' ),
        ] );
        $rep->add_control( 'desc', [
            'label'   => __( 'Description', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => '',
        ] );
        $rep->add_control( 'price', [
            'label'   => __( 'Price', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'title_field' => '{{{ title }}}',
            'condition'   => [ 'data_source' => 'static' ],
        ] );

        $this->end_controls_section();
    }

    private function get_taxonomy_options( $taxonomy, $hierarchical_labels = false ) {
        $out = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $out;

        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || ! $terms ) return $out;

        // Build label as "Parent > Child" if requested
        $by_id = [];
        foreach ( $terms as $t ) $by_id[ $t->term_id ] = $t;

        foreach ( $terms as $t ) {
            $label = $t->name;
            if ( $hierarchical_labels && is_taxonomy_hierarchical( $taxonomy ) && ! empty( $t->parent ) ) {
                $trail = [ $t->name ];
                $cur = $t;
                $guard = 0;
                while ( ! empty( $cur->parent ) && isset( $by_id[ $cur->parent ] ) && $guard++ < 20 ) {
                    $cur = $by_id[ $cur->parent ];
                    array_unshift( $trail, $cur->name );
                }
                $label = implode( ' › ', $trail );
            }
            // Use slug values to be stable across environments; shortcode accepts slug or ID
            $out[ $t->slug ] = $label;
        }
        return $out;
    }

    protected function render_static_item( $it ) {
        $title = isset( $it['title'] ) ? $it['title'] : '';
        $desc  = isset( $it['desc'] ) ? $it['desc'] : '';
        $price = isset( $it['price'] ) ? $it['price'] : '';

        echo '<li class="jp-menu__item">';
        echo '<div class="jp-menu__inner" style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem">';
        echo '<div class="jp-box-left"><div class="jp-menu__content"><div class="jp-menu__header"><span class="jp-menu__title">'. esc_html( $title ) .'</span></div>';
        if ( $desc ) echo '<div class="jp-menu__desc">'. wp_kses_post( wpautop( $desc ) ) .'</div>';
        echo '</div></div>';
        echo '<div class="jp-box-right">';
        if ( $price !== '' ) {
            echo '<div class="jp-menu__price-row jp-order--label-left">';
            echo '<span class="jp-col jp-col-labelwrap"></span>';
            echo '<span class="jp-col jp-col-price">'. esc_html( $price ) .'</span>';
            echo '</div>';
        }
        echo '</div></div>';
        echo '</li>';
    }

    protected function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : [];
        if ( empty( $items ) ) return;

        echo '<ul class="jp-menu">';
        foreach ( $items as $it ) {
            $this->render_static_item( $it );
        }
        echo '</ul>';
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();

            // Minimal CSS helpers for alignment (kept tiny & inline to avoid asset changes)
            echo '<style>
            .jp-menu__inner{display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem}
            .jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%}
            .jp-menu__price-row .jp-col{display:block}
            .jp-menu__price-row.jp-order--label-left .jp-col-labelwrap{order:1}
            .jp-menu__price-row.jp-order--label-left .jp-col-price{order:2}
            .jp-menu__price-row.jp-order--price-left .jp-col-price{order:1}
            .jp-menu__price-row.jp-order--price-left .jp-col-labelwrap{order:2}
            </style>';

        } else {
            $menus    = isset( $s['query_menus'] ) && is_array( $s['query_menus'] ) ? array_filter( array_map( 'sanitize_text_field', $s['query_menus'] ) ) : [];
            $sections = isset( $s['query_sections'] ) && is_array( $s['query_sections'] ) ? array_filter( array_map( 'sanitize_text_field', $s['query_sections'] ) ) : [];

            if ( empty( $menus ) ) {
                echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'Select at least one Menu term.', 'jellopoint-restaurant-menu' ) . '</div>';
                return;
            }

            $shortcode = '[jprm_menu';
            $shortcode .= ' menu="' . esc_attr( implode( ',', $menus ) ) . '"';
            if ( ! empty( $sections ) ) {
                $shortcode .= ' sections="' . esc_attr( implode( ',', $sections ) ) . '"';
            }
            $shortcode .= ' orderby="' . esc_attr( isset( $s['query_orderby'] ) ? $s['query_orderby'] : 'menu_order' ) . '"';
            $shortcode .= ' order="' . esc_attr( isset( $s['query_order'] ) ? $s['query_order'] : 'ASC' ) . '"';
            $shortcode .= ' limit="' . esc_attr( isset( $s['query_limit'] ) ? $s['query_limit'] : -1 ) . '"';
            $shortcode .= ' hide_invisible="' . ( isset( $s['hide_invisible'] ) && $s['hide_invisible'] === 'yes' ? 'yes' : 'no' ) . '"';
            $shortcode .= ' row_order="' . esc_attr( isset( $s['row_order'] ) ? $s['row_order'] : 'label_left' ) . '"';
            $shortcode .= ' label_presentation="' . esc_attr( isset( $s['label_presentation'] ) ? $s['label_presentation'] : 'text' ) . '"';
            $shortcode .= ' dedupe="' . esc_attr( isset( $s['dedupe'] ) ? $s['dedupe'] : 'deepest_only' ) . '"';
            $shortcode .= ']';

            echo do_shortcode( $shortcode );
        }
    }
}

