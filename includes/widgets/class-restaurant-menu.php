<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Background;
use Elementor\Repeater;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Restaurant_Menu extends Widget_Base {
    public function get_name() { return 'jprm-restaurant-menu'; }
    public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-menu-card'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu', 'restaurant', 'card', 'food', 'price', 'prices', 'items' ]; }

    protected function register_controls() {
        /*  ===== Source ===== */
        $this->start_controls_section( 'section_source', [ 'label'=>__( 'Data Source', 'jellopoint-restaurant-menu' ) ] );
        $this->add_control( 'data_source', [
            'label'   => __( 'Source', 'jellopoint-restaurant-menu' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'dynamic',
            'options' => [
                'dynamic' => __( 'Dynamic (from Admin items)', 'jellopoint-restaurant-menu' ),
                'static'  => __( 'Static (manual items below)', 'jellopoint-restaurant-menu' ),
            ],
        ] );

        // Dynamic query controls (shown when source=dynamic)
        $this->add_control( 'query_menus', [
            'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'options'     => [], // can be populated via filter in plugin init
            'default'     => [],
            'multiple'    => true,
            'label_block' => true,
            'condition'   => [ 'data_source' => 'dynamic' ],
        ] );
        $this->add_control( 'query_sections', [
            'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
            'type'        => Controls_Manager::SELECT2,
            'options'     => [],
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

        // Presentation
        $this->add_control( 'label_presentation', [
            'label'     => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'text',
            'options'   => [
                'text'  => __( 'Text', 'jellopoint-restaurant-menu' ),
                'badge' => __( 'Badge', 'jellopoint-restaurant-menu' ),
                'icon'  => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
            ],
        ] );
        $this->add_control( 'label_position', [
            'label'     => __( 'Label Position', 'jellopoint-restaurant-menu' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'right',
            'options'   => [
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

        // Preset map for labels/icons (filled at runtime if available)
        $preset_map = function_exists('jprm_get_price_label_map') ? jprm_get_price_label_map() : [];
        $preset_full = function_exists('jprm_get_price_label_full_map') ? jprm_get_price_label_full_map() : [];

        // Up to 6 multi-price rows (safe, fixed count, no repeater nesting issues)
        for ( $i = 1; $i <= 6; $i++ ) {
            $repeater->add_control( 'price'.$i.'_enable', [
                'label'        => sprintf( __( 'Enable Row %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [ 'use_multi_prices' => 'yes' ],
            ] );
            $repeater->add_control( 'price'.$i.'_label_select', [
                'label'     => sprintf( __( 'Label %d (preset)', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'custom',
                'options'   => array_merge( [ 'custom' => __( 'Custom', 'jellopoint-restaurant-menu' ) ], $preset_map ),
                'condition' => [ 'use_multi_prices' => 'yes', 'price'.$i.'_enable' => 'yes' ],
            ] );
            $repeater->add_control( 'price'.$i.'_label_custom', [
                'label'     => sprintf( __( 'Label %d (custom)', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '',
                'condition' => [ 'use_multi_prices' => 'yes', 'price'.$i.'_enable' => 'yes', 'price'.$i.'_label_select' => 'custom' ],
            ] );
            $repeater->add_control( 'price'.$i.'_hide_icon', [
                'label'        => sprintf( __( 'Hide Icon %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [ 'use_multi_prices' => 'yes', 'price'.$i.'_enable' => 'yes' ],
            ] );
            $repeater->add_control( 'price'.$i.'_amount', [
                'label'     => sprintf( __( 'Amount %d', 'jellopoint-restaurant-menu' ), $i ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '',
                'condition' => [ 'use_multi_prices' => 'yes', 'price'.$i.'_enable' => 'yes' ],
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

        /*  ===== Style ===== */
        $this->start_controls_section( 'section_style', [ 'label'=>__( 'Style', 'jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'section_note', [
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __( 'Basic skinning; core layout is handled by HTML structure. Use your theme/CSS for deeper styling.', 'jellopoint-restaurant-menu' ),
        ] );
        $this->end_controls_section();
    }

    /* =========================
     * Static rendering helpers (kept as-is)
     * ========================= */

    function render_static_item( $item ) {
        $title = $item['item_title'] ?? '';
        $desc  = $item['item_description'] ?? '';
        $img   = $item['item_image']['id'] ?? 0;
        $img_pos = $item['item_image_position'] ?? 'left';
        $price = $item['item_price'] ?? '';
        $price_label = $item['item_price_label'] ?? '';
        $badge = $item['item_badge'] ?? '';
        $badge_pos = $item['item_badge_position'] ?? 'corner-right';
        $show_icons = isset($item['show_preset_icons']) && $item['show_preset_icons']==='yes';
        
        $is_multi = false;
        $rows = [];
        // Build multi-price rows from fixed slots
        if ( isset($item['use_multi_prices']) && $item['use_multi_prices'] === 'yes' ) {
            $is_multi = true;
            $preset_map = function_exists('jprm_get_price_label_map') ? jprm_get_price_label_map() : [];
            $preset_full = function_exists('jprm_get_price_label_full_map') ? jprm_get_price_label_full_map() : [];
            for ( $i = 1; $i <= 6; $i++ ) {
                $en_key = 'price'.$i.'_enable';
                if ( isset($item[$en_key]) && $item[$en_key] === 'yes' ) {
                    $sel_key = 'price'.$i.'_label_select';
                    $cus_key = 'price'.$i.'_label_custom';
                    $amt_key = 'price'.$i.'_amount';
                    $sel = isset($item[$sel_key]) ? $item[$sel_key] : 'custom';
                    $label = ($sel === 'custom') ? ( $item[$cus_key] ?? '' ) : ( $preset_full[$sel]['label'] ?? ($preset_map[$sel] ?? $sel) );
                    $iconid = ($sel === 'custom') ? 0 : intval( $preset_full[$sel]['icon_id'] ?? 0 );
                    $hide_key = 'price' . $i . '_hide_icon';
                    if ( isset($item[$hide_key]) && $item[$hide_key] === 'yes' ) { $iconid = 0; }
                    $amount = isset($item[$amt_key]) ? $item[$amt_key] : '';
                    if ( $label !== '' || $amount !== '' ) {
                        $rows[] = [
                            'label'  => $label,
                            'icon'   => $iconid,
                            'amount' => $amount,
                        ];
                    }
                }
            }
        }

        echo '<li class="jp-menu__item">';
        echo '  <div class="jp-menu__inner">';

        echo '    <div class="jp-menu__content">';
        if ( $title !== '' ) {
            echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
        }
        if ( $desc !== '' ) {
            echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
        }
        echo '    </div>';

        echo '    <div class="jp-menu__pricegroup">';

        if ( ! $is_multi && $price !== '' ) {
            echo '      <div class="jp-menu__price">';
            echo '          <span class="jp-menu__value">' . esc_html( $price ) . '</span>';
            echo '      </div>';
        }

        if ( $is_multi && ! empty( $rows ) ) {
            $label_presentation = 'text'; // static uses text; can be upgraded by control if desired
            $label_order_class  = 'jp-order--label-right';
            foreach ( $rows as $r ) {
                $label = $r['label'] ?? '';
                $value = $r['amount'] ?? '';
                echo '      <div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_class ) . '">';
                echo '          <span class="jp-menu__label jp-col-label">';
                if ( $label_presentation === 'badge' && $label !== '' ) {
                    echo '<span class="jp-badge">' . esc_html( $label ) . '</span>';
                } elseif ( $label_presentation === 'icon' && $label !== '' ) {
                    echo esc_html( $label ); // static path has no icon map here
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

    function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset($s['items']) ? $s['items'] : [];
        echo '<ul class="jp-menu">';
        foreach ( $items as $item ) { $this->render_static_item( $item ); }
        echo '</ul>';
        // Inline minimal CSS for column ordering and label/icon wrap
        echo '<style>.jp-menu{list-style:none;margin:0;padding:0}.jp-menu__item{margin:0 0 .9rem 0}.jp-menu__inner{display:grid;grid-template-columns:1fr auto;gap:.4rem .8rem;align-items:start}.jp-menu__content{min-width:0}.jp-menu__title{margin:0}.jp-menu__desc{opacity:.85}.jp-menu__pricegroup{display:flex;flex-direction:column;gap:.25rem;text-align:right}.jp-menu__price{display:flex;gap:.5rem;justify-content:flex-end;line-height:1.2}.jp-menu__label{opacity:.8;white-space:nowrap}.jp-menu__value{font-weight:600;white-space:nowrap}.jp-price-row{display:grid;grid-template-columns:auto auto;gap:.5rem;align-items:center}.jp-col-label{justify-self:end}.jp-col-price{justify-self:end}.jp-menu__price-row.jp-order--price-left .jp-col-label{order:2}.jp-menu__price-row.jp-order--price-left .jp-col-price{order:1}.jp-menu__price-row.jp-order--label-left .jp-col-label{order:1}.jp-menu__price-row.jp-order--label-left .jp-col-price{order:2}</style>';
    }

    /* =========================
     * REPLACED render(): now renders dynamic items natively (no shortcode)
     * ========================= */
    protected function render() {
        $s = $this->get_settings_for_display();

        // Static source – keep existing behavior
        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            if ( method_exists( $this, 'render_static' ) ) {
                $this->render_static();
            }
            // Ensure minimal CSS printed once
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

        // Dynamic source – render directly (no shortcode)
        $this->render_dynamic( $s );
    }

    /**
     * Dynamic renderer that preserves the proven HTML wrappers for layout.
     */
    protected function render_dynamic( array $s ) {
        // 1) Collect items from CPT or via filter
        $items = $this->collect_dynamic_items( $s );

        // 2) Early exit if no items
        if ( empty( $items ) ) {
            echo '<ul class="jp-menu"></ul>';
            return;
        }

        // 3) Output once-per-page minimal CSS (same as static path)
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

        // Optional: label presentation from widget (text|badge|icon)
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

            $title = isset( $item['title'] ) ? $item['title'] : '';
            $desc  = isset( $item['description'] ) ? $item['description'] : '';

            echo '<li class="jp-menu__item">';
            echo '  <div class="jp-menu__inner">';

            echo '    <div class="jp-menu__content">';
            if ( $title !== '' ) {
                echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            }
            if ( $desc !== '' ) {
                echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            }
            echo '    </div>';

            echo '    <div class="jp-menu__pricegroup">';

            if ( empty( $item['prices'] ) && isset( $item['price'] ) && $item['price'] !== '' ) {
                echo '      <div class="jp-menu__price">';
                echo '          <span class="jp-menu__value">' . esc_html( $item['price'] ) . '</span>';
                echo '      </div>';
            }

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
                        if ( $icon ) {
                            echo '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
                        }
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
     * Falls back to a CPT query for 'jprm_menu_item', with taxonomy filters:
     *  - jprm_menu
     *  - jprm_section
     * Expects meta keys (adapt if yours differ):
     *  - _jprm_price    (string)
     *  - _jprm_prices   (array of [label, value])
     *  - _jprm_labels   (array)
     *  - _jprm_invisible (bool)
     */
    protected function collect_dynamic_items( array $s ) {
        // Allow external provider to override completely
        $filtered = apply_filters( 'jprm/widget/get_items', null, $s, $this );
        if ( is_array( $filtered ) ) {
            return $filtered;
        }

        $orderby = ! empty( $s['query_orderby'] ) ? sanitize_text_field( $s['query_orderby'] ) : 'menu_order';
        $order   = ! empty( $s['query_order'] ) ? sanitize_text_field( $s['query_order'] ) : 'ASC';
        $limit   = isset( $s['query_limit'] ) && $s['query_limit'] !== '' ? (int) $s['query_limit'] : -1;

        $tax_query = [];

        if ( ! empty( $s['query_menus'] ) && is_array( $s['query_menus'] ) ) {
            $tax_query[] = [
                'taxonomy' => 'jprm_menu',
                'field'    => 'term_id',
                'terms'    => array_map( 'intval', $s['query_menus'] ),
            ];
        }

        if ( ! empty( $s['query_sections'] ) && is_array( $s['query_sections'] ) ) {
            $tax_query[] = [
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => array_map( 'intval', $s['query_sections'] ),
            ];
        }

        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        $args = [
            'post_type'           => 'jprm_menu_item',
            'post_status'         => 'publish',
            'nopaging'            => $limit === -1,
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

            $title = get_the_title();
            $desc  = get_the_excerpt();
            if ( ! $desc ) {
                $desc = get_post_meta( $post_id, '_jprm_desc', true );
            }

            $single_price = get_post_meta( $post_id, '_jprm_price', true );

            $multi_prices = get_post_meta( $post_id, '_jprm_prices', true );
            if ( ! is_array( $multi_prices ) ) {
                $multi_prices = [];
            }

            $labels = get_post_meta( $post_id, '_jprm_labels', true );
            if ( ! is_array( $labels ) ) {
                $labels = [];
            }

            $invisible = (bool) get_post_meta( $post_id, '_jprm_invisible', false );

            $out[] = [
                'ID'          => $post_id,
                'title'       => $title,
                'description' => $desc,
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
     * Normalize an item into a stable shape for the renderer.
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

        $item = apply_filters( 'jprm/widget/normalize_item', $item, $raw, $this );

        return $item;
    }
}