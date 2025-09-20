<?php
/**
 * Elementor Widget: JelloPoint Restaurant Menu
 *
 * - Dynamic mode: renders via [jprm_menu] shortcode (Menus taxonomy + Sections)
 * - Static mode: rich repeater with Multiple Prices, Labels, Icons, Badge, Separator
 * - Adds "De-duplication" control passed to shortcode: deepest_only | all_assigned | topmost_only
 */

namespace JelloPoint\RestaurantMenu\Widgets;

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Icons_Manager;

class Restaurant_Menu extends Widget_Base {

    public function get_name() { return 'jprm_restaurant_menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-price-list'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu', 'restaurant', 'price', 'list', 'food', 'drink' ]; }

    protected function register_controls() {

        /* ===== Source / Mode ===== */
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

        /* ===== Dynamic / Query ===== */
        $this->start_controls_section(
            'section_query',
            [
                'label'     => __( 'Dynamic / Query', 'jellopoint-restaurant-menu' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
                'condition' => [ 'data_source' => 'dynamic' ],
            ]
        );

        $this->add_control(
            'query_menus',
            [
                'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
                'type'         => Controls_Manager::SELECT2,
                'options'      => $this->get_taxonomy_options( 'jprm_menu' ),
                'multiple'     => true,
                'label_block'  => true,
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
                'description'  => __( 'Optional: limit output to these Sections (with their sub-sections).', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->add_control(
            'query_orderby',
            [
                'label'   => __( 'Order By', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'menu_order' => __( 'Menu Order', 'jellopoint-restaurant-menu' ),
                    'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
                    'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
                ],
                'default' => 'menu_order',
            ]
        );

        $this->add_control(
            'query_order',
            [
                'label'   => __( 'Order', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [ 'ASC' => 'ASC', 'DESC' => 'DESC' ],
                'default' => 'ASC',
            ]
        );

        $this->add_control(
            'query_limit',
            [
                'label'   => __( 'Items Limit', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::NUMBER,
                'default' => -1,
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
            ]
        );

        $this->add_control(
            'row_order',
            [
                'label'   => __( 'Row layout', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'label_left' => __( 'Label left, Price right', 'jellopoint-restaurant-menu' ),
                    'price_left' => __( 'Price left, Label right', 'jellopoint-restaurant-menu' ),
                ],
                'default' => 'label_left',
            ]
        );

        $this->add_control(
            'label_presentation',
            [
                'label'   => __( 'Label presentation', 'jellopoint-restaurant-menu' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'text' => __( 'Text', 'jellopoint-restaurant-menu' ),
                ],
                'default' => 'text',
            ]
        );

        $this->add_control(
            'dedupe',
            [
                'label'       => __( 'De-duplication', 'jellopoint-restaurant-menu' ),
                'type'        => Controls_Manager::SELECT,
                'options'     => [
                    'deepest_only' => __( 'Deepest only (recommended)', 'jellopoint-restaurant-menu' ),
                    'all_assigned' => __( 'All assigned (parent + child)', 'jellopoint-restaurant-menu' ),
                    'topmost_only' => __( 'Topmost only', 'jellopoint-restaurant-menu' ),
                ],
                'default'     => 'deepest_only',
                'description' => __( 'When an item is tagged to both a parent and a child section, choose where it should appear.', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->end_controls_section();

        /* ===== Static Items (rich) ===== */
        $this->start_controls_section(
            'section_static',
            [
                'label'     => __( 'Static Items', 'jellopoint-restaurant-menu' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
                'condition' => [ 'data_source' => 'static' ],
            ]
        );

        $row = new Repeater();
        $row->add_control( 'label_custom', [
            'label'   => __( 'Label', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $row->add_control( 'amount', [
            'label'   => __( 'Amount', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $row->add_control( 'hide_icon', [
            'label'        => __( 'Hide icon', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ] );

        $icons_rep = new Repeater();
        $icons_rep->add_control( 'icon', [
            'label'   => __( 'Icon', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-check',
                'library' => 'fa-solid',
            ],
        ] );
        $icons_rep->add_control( 'text', [
            'label'   => __( 'Icon label (optional)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );

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
        $rep->add_control( 'image', [
            'label' => __( 'Image', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::MEDIA,
        ] );
        $rep->add_control( 'badge', [
            'label'   => __( 'Badge text', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ] );
        $rep->add_control( 'badge_position', [
            'label'   => __( 'Badge position', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'corner-left'  => __( 'Corner (left)', 'jellopoint-restaurant-menu' ),
                'corner-right' => __( 'Corner (right)', 'jellopoint-restaurant-menu' ),
                'inline'       => __( 'Inline', 'jellopoint-restaurant-menu' ),
            ],
            'default' => 'corner-right',
        ] );
        $rep->add_control( 'separator', [
            'label'        => __( 'Separator', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ] );
        $rep->add_control( 'visible', [
            'label'        => __( 'Visible', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $rep->add_control( 'price', [
            'label'   => __( 'Single price (leave empty if using Multiple Prices)', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
            'separator' => 'before',
        ] );
        $rep->add_control( 'price_label', [
            'label'   => __( 'Price label', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                ''        => __( 'None', 'jellopoint-restaurant-menu' ),
                'small'   => __( 'Small', 'jellopoint-restaurant-menu' ),
                'medium'  => __( 'Medium', 'jellopoint-restaurant-menu' ),
                'large'   => __( 'Large', 'jellopoint-restaurant-menu' ),
                'custom'  => __( 'Custom…', 'jellopoint-restaurant-menu' ),
            ],
            'default' => '',
        ] );
        $rep->add_control( 'price_label_custom', [
            'label'     => __( 'Custom label', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'condition' => [ 'price_label' => 'custom' ],
        ] );

        $rep->add_control( 'multi', [
            'label'        => __( 'Enable Multiple Prices', 'jellopoint-restaurant-menu' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
            'separator'    => 'before',
        ] );
        $rep->add_control( 'multi_rows', [
            'label'       => __( 'Price rows', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $row->get_controls(),
            'title_field' => '{{{ label_custom || amount }}}',
            'condition'   => [ 'multi' => 'yes' ],
        ] );

        $rep->add_control( 'icons', [
            'label'       => __( 'Icons', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $icons_rep->get_controls(),
            'title_field' => '{{{ text ? text : "Icon" }}}',
            'separator'   => 'before',
        ] );

        $this->add_control( 'items', [
            'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'title_field' => '{{{ title }}}',
        ] );

        $this->end_controls_section();
    }

    private function get_taxonomy_options( $taxonomy, $hierarchical_labels = false ) {
        $out = [];
        if ( ! taxonomy_exists( $taxonomy ) ) return $out;

        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || ! $terms ) return $out;

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
            $out[ $t->slug ] = $label;
        }
        return $out;
    }

    protected function render_static_item( $it ) {
        $title = isset( $it['title'] ) ? $it['title'] : '';
        $desc  = isset( $it['desc'] ) ? $it['desc'] : '';
        $image = isset( $it['image']['id'] ) ? $it['image']['id'] : 0;
        $badge = isset( $it['badge'] ) ? $it['badge'] : '';
        $badge_pos = isset( $it['badge_position'] ) ? $it['badge_position'] : 'corner-right';
        $separator = isset( $it['separator'] ) && $it['separator'] === 'yes';
        $visible = ! isset( $it['visible'] ) || $it['visible'] === 'yes';

        if ( ! $visible ) return;

        $single_price = isset( $it['price'] ) ? $it['price'] : '';
        $price_label = isset( $it['price_label'] ) ? $it['price_label'] : '';
        $price_label_custom = isset( $it['price_label_custom'] ) ? $it['price_label_custom'] : '';

        $multi = isset( $it['multi'] ) && $it['multi'] === 'yes';
        $rows  = ( isset( $it['multi_rows'] ) && is_array( $it['multi_rows'] ) ) ? $it['multi_rows'] : [];

        $badge_class = 'jp-menu__badge' . ( $badge_pos === 'inline' ? ' jp-menu__badge--inline' : ' jp-menu__badge--corner jp-menu__badge--' . ( $badge_pos === 'corner-left' ? 'corner-left' : 'corner-right' ) );

        echo '<li class="jp-menu__item">';
        if ( $badge ) echo '<span class="'. esc_attr( $badge_class ) .'">'. esc_html( $badge ) .'</span>';

        echo '<div class="jp-menu__inner" style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem">';
        echo '<div class="jp-box-left" style="display:flex;gap:.75rem;flex:1 1 auto;min-width:0">';
        if ( $image ) {
            $img = wp_get_attachment_image( $image, 'thumbnail', false, [ 'class'=>'attachment-thumbnail size-thumbnail' ] );
            if ( $img ) echo '<div class="jp-menu__media">'. $img .'</div>';
        }
        echo '<div class="jp-menu__content" style="flex:1 1 auto;min-width:0;width:auto">';
        echo '<div class="jp-menu__header"><span class="jp-menu__title">'. esc_html( $title ) .'</span></div>';
        if ( $desc ) echo '<div class="jp-menu__desc">'. wpautop( wp_kses_post( $desc ) ) .'</div>';

        // Icons row
        if ( ! empty( $it['icons'] ) && is_array( $it['icons'] ) ) {
            echo '<div class="jp-menu__icons" style="margin-top:.25rem;display:flex;flex-wrap:wrap;gap:.35rem .65rem">';
            foreach ( $it['icons'] as $ico ) {
                echo '<span class="jp-icon">';
                if ( ! empty( $ico['icon'] ) ) {
                    ob_start();
                    Icons_Manager::render_icon( $ico['icon'], [ 'aria-hidden' => 'true' ] );
                    echo ob_get_clean();
                }
                if ( ! empty( $ico['text'] ) ) {
                    echo '<span class="jp-icon__text">'. esc_html( $ico['text'] ) .'</span>';
                }
                echo '</span>';
            }
            echo '</div>';
        }

        echo '</div>'; // content
        echo '</div>'; // left

        echo '<div class="jp-box-right" style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end">';
        if ( $multi && ! empty( $rows ) ) {
            echo '<div class="jp-menu__pricegroup" style="display:inline-grid;justify-items:end">';
            foreach ( $rows as $r ) {
                $label = isset( $r['label_custom'] ) ? $r['label_custom'] : '';
                $amount = isset( $r['amount'] ) ? $r['amount'] : '';
                if ( $label === '' && $amount === '' ) continue;
                $label_html = $label ? '<span class="jp-price-label">'. esc_html( $label ) .'</span>' : '';
                echo '<div class="jp-menu__price-row jp-order--label-left">'
                    . '<span class="jp-col jp-col-labelwrap">'. $label_html .'</span>'
                    . '<span class="jp-col jp-col-price">'. wp_kses_post( $amount ) .'</span>'
                    . '</div>';
            }
            echo '</div>';
        } else {
            if ( $single_price !== '' ) {
                $label_text = '';
                if ( $price_label === 'custom' ) { $label_text = $price_label_custom; }
                elseif ( $price_label ) { $label_text = ucwords( str_replace( '-', ' ', $price_label ) ); }
                $label_html = $label_text ? '<span class="jp-price-label">'. esc_html( $label_text ) .'</span>' : '';
                echo '<div class="jp-menu__price-row jp-order--label-left">'
                    . '<span class="jp-col jp-col-labelwrap">'. $label_html .'</span>'
                    . '<span class="jp-col jp-col-price">'. wp_kses_post( $single_price ) .'</span>'
                    . '</div>';
            }
        }
        echo '</div>'; // right
        echo '</div>'; // inner

        if ( $separator ) echo '<div class="jp-menu__separator" aria-hidden="true"></div>';
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

        // Tiny CSS helpers for alignment (kept inline to avoid asset changes)
        echo '<style>
        .jp-menu__inner{display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem}
        .jp-box-right{display:flex;flex-direction:column;align-items:flex-end}
        .jp-menu__pricegroup{display:inline-grid;justify-items:end;text-align:right}
        .jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%}
        .jp-menu__price-row .jp-col{display:block}
        .jp-menu__price-row .jp-col.jp-col-labelwrap{display:inline-flex;align-items:center;gap:.5rem}
        </style>';
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();
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
