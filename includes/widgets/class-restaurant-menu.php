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
    public function get_title() { return __( 'JelloPoint Restaurant Menu', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-price-list'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','price list','list','food','drink' ]; }

    protected function register_controls() {
        /* ===== Source ===== */
        $this->start_controls_section( 'section_source', [ 'label'=>__( 'Data Source', 'jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_CONTENT ] );
        $this->add_control( 'data_source', [ 'label'=>__( 'Source','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'options'=>[ 'static'=>__( 'Static (manual items)', 'jellopoint-restaurant-menu' ), 'dynamic'=>__( 'Dynamic (Menu Items CPT)', 'jellopoint-restaurant-menu' ) ], 'default'=>'dynamic' ] );
        $this->add_control( 'query_menus', [ 'label'=>__( 'Menus','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT2, 'options'=>$this->get_tax_options('jprm_menu'), 'multiple'=>true, 'label_block'=>true, 'condition'=>[ 'data_source'=>'dynamic' ] ] );
        $this->add_control( 'query_sections', [ 'label'=>__( 'Sections','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT2, 'options'=>$this->get_tax_options('jprm_section'), 'multiple'=>true, 'label_block'=>true, 'condition'=>[ 'data_source'=>'dynamic' ] ] );
        $this->add_control( 'query_orderby', [ 'label'=>__( 'Order By','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'options'=>[ 'menu_order'=>__( 'Manual (Order field)','jellopoint-restaurant-menu' ), 'title'=>__( 'Title','jellopoint-restaurant-menu' ), 'date'=>__( 'Date','jellopoint-restaurant-menu' ) ], 'default'=>'menu_order', 'condition'=>[ 'data_source'=>'dynamic' ] ] );
        $this->add_control( 'query_order', [ 'label'=>__( 'Order','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'options'=>[ 'ASC'=>'ASC', 'DESC'=>'DESC' ], 'default'=>'ASC', 'condition'=>[ 'data_source'=>'dynamic' ] ] );
        $this->add_control( 'query_limit', [ 'label'=>__( 'Items to Show','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::NUMBER, 'default'=>-1, 'condition'=>[ 'data_source'=>'dynamic' ] ] );
        $this->add_control( 'hide_invisible', [ 'label'=>__( 'Hide Invisible Items','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SWITCHER, 'label_on'=>__( 'Yes','jellopoint-restaurant-menu' ), 'label_off'=>__( 'No','jellopoint-restaurant-menu' ), 'return_value'=>'yes', 'default'=>'yes', 'condition'=>[ 'data_source'=>'dynamic' ] ] );

        $this->add_control( 'dedupe', [
            'label' => __( 'De-duplication', 'jellopoint-restaurant-menu' ),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'deepest_only' => __( 'Deepest only (no parent duplicates)', 'jellopoint-restaurant-menu' ),
                'all_assigned' => __( 'All assigned (allow duplicates)', 'jellopoint-restaurant-menu' ),
                'topmost_only' => __( 'Topmost only', 'jellopoint-restaurant-menu' ),
            ],
            'default' => 'deepest_only',
            'condition' => [ 'data_source' => 'dynamic' ],
        ] );

        $this->end_controls_section();

        /* ===== Static Items ===== */
        $this->start_controls_section( 'section_items', [ 'label'=>__( 'Items', 'jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_CONTENT, 'condition'=>[ 'data_source'=>'static' ] ] );
        $repeater = new Repeater();
        $repeater->add_control( 'item_title', [ 'label'=>__( 'Title','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::TEXT, 'label_block'=>true ] );
        $repeater->add_control( 'item_description', [ 'label'=>__( 'Description','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::TEXTAREA ] );
        $repeater->add_control(
    'show_image',
    [
        'label' => __( 'Show Image', 'jellopoint-restaurant-menu' ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'default' => '',
        'label_on' => __( 'Show', 'jellopoint-restaurant-menu' ),
        'label_off' => __( 'Hide', 'jellopoint-restaurant-menu' ),
        'return_value' => 'yes',
    ]
);
$repeater->add_control( 'image_settings_heading', [
    'type' => Controls_Manager::HEADING,
    'label' => __( 'Image Settings', 'jellopoint-restaurant-menu' ),
    'separator' => 'before',
    'condition' => [ 'show_image' => 'yes' ],
] );
$repeater->add_control( 'item_image', [
    'label' => __( 'Image', 'jellopoint-restaurant-menu' ),
    'type' => Controls_Manager::MEDIA,
    'default' => [ 'url' => Utils::get_placeholder_image_src() ],
    'condition' => [ 'show_image' => 'yes' ],
] );
$repeater->add_control( 'item_image_position', [
    'label' => __( 'Image Position', 'jellopoint-restaurant-menu' ),
    'type' => Controls_Manager::CHOOSE,
    'options' => [
        'left' => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-h-align-left' ],
        'right'=> [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-h-align-right' ],
    ],
    'default' => 'left',
    'condition' => [ 'show_image' => 'yes' ],
] );
$repeater->add_control( 'item_link', [
    'label' => __( 'Link', 'jellopoint-restaurant-menu' ),
    'type' => Controls_Manager::URL,
    'placeholder' => __( 'https://example.com', 'jellopoint-restaurant-menu' ),
    'condition' => [ 'show_image' => 'yes' ],
] );
$repeater->add_group_control( Group_Control_Image_Size::get_type(), [ 'name'=>'item_image_size', 'default'=>'thumbnail', 'condition' => [ 'show_image' => 'yes' ]] );
        // Pricing
        $repeater->add_control( 'price_settings_heading', [
    'type' => Controls_Manager::HEADING,
    'label' => __( 'Price Settings', 'jellopoint-restaurant-menu' ),
    'separator' => 'before',
] );


        // Multiple Prices (Fixed N, preset labels)
        $repeater->add_control(
            'use_multi_prices',
            [
                'label' => __( 'Use Multiple Prices', 'jellopoint-restaurant-menu' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off' => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        
        $repeater->add_control(
            'show_preset_icons',
            [
                'label' => __( 'Show preset icons', 'jellopoint-restaurant-menu' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'jellopoint-restaurant-menu' ),
                'label_off' => __( 'No', 'jellopoint-restaurant-menu' ),
                'return_value' => 'yes',
                'default' => '',
                'condition' => [ 'use_multi_prices' => 'yes' ],
            ]
        );
    $preset_opts = function_exists('jprm_get_price_label_options')
            ? jprm_get_price_label_options()
            : [
                'small'  => __( 'Small', 'jellopoint-restaurant-menu' ),
                'medium' => __( 'Medium', 'jellopoint-restaurant-menu' ),
                'large'  => __( 'Large', 'jellopoint-restaurant-menu' ),
            ];
        $preset_opts = array_merge( [ 'custom' => __( 'Custom', 'jellopoint-restaurant-menu' ) ], $preset_opts );

        
        for ( $i = 1; $i <= 6; $i++ ) {
            $enable_condition = [ 'use_multi_prices' => 'yes' ];
            if ( $i > 1 ) {
                $prev = 'price' . ( $i - 1 ) . '_enable';
                $enable_condition[ $prev ] = 'yes';
            }

            $repeater->add_control( 'price' . $i . '_enable', [
                'label' => sprintf( __( 'Enable Price %d', 'jellopoint-restaurant-menu' ), $i ),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'condition' => $enable_condition,
            ] );

            $repeater->add_control( 'price' . $i . '_label_select', [
                'label' => sprintf( __( 'Price %d Label', 'jellopoint-restaurant-menu' ), $i ),
                'type' => Controls_Manager::SELECT,
                'options' => $preset_opts,
                'default' => 'custom',
                'label_block' => true,
                'condition' => [ 'use_multi_prices' => 'yes', 'price' . $i . '_enable' => 'yes' ],
            ] );

            $repeater->add_control( 'price' . $i . '_label_custom', [
                'label' => sprintf( __( 'Price %d Custom Label', 'jellopoint-restaurant-menu' ), $i ),
                'type' => Controls_Manager::TEXT,
                'condition' => [ 'use_multi_prices' => 'yes', 'price' . $i . '_enable' => 'yes', 'price' . $i . '_label_select' => 'custom' ],
            ] );

            $repeater->add_control( 'price' . $i . '_amount', [
                'label' => sprintf( __( 'Price %d Amount', 'jellopoint-restaurant-menu' ), $i ),
                'type' => Controls_Manager::TEXT,
                'label_block' => false,
                'condition' => [ 'use_multi_prices' => 'yes', 'price' . $i . '_enable' => 'yes' ],
            ] );
        
            // Optional per-slot icon override via Elementor Icon Library
            $repeater->add_control( 'price' . $i . '_icon_override_type', [
                'label' => __( 'Icon Override', 'jellopoint-restaurant-menu' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Library', 'jellopoint-restaurant-menu' ),
                'label_off' => __( 'Preset', 'jellopoint-restaurant-menu' ),
                'return_value' => 'library',
                'default' => '',
                'condition' => [ 'use_multi_prices' => 'yes', 'price' . $i . '_enable' => 'yes' ],
            ] );
            $repeater->add_control( 'price' . $i . '_icon_override', [
                'label' => sprintf( __( 'Price %d Icon', 'jellopoint-restaurant-menu' ), $i ),
                'type' => Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => true,
                'default' => [],
                'condition' => [ 'use_multi_prices' => 'yes', 'price' . $i . '_enable' => 'yes', 'price' . $i . '_icon_override_type' => 'library' ],
            ] );
}
$repeater->add_control( 'item_price_label', [ 'label'=>__( 'Price Label (single)','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::TEXT , 'condition' => [ 'use_multi_prices!' => 'yes' ] ] );
        $repeater->add_control( 'item_price', [ 'label'=>__( 'Price','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::TEXT , 'condition' => [ 'use_multi_prices!' => 'yes' ] ] );
        /* Multiple Prices (editor) removed */
/* multi repeater removed */
        /* Multiple Prices (editor) removed */
$repeater->add_control( 'badge_settings_heading', [
    'type' => Controls_Manager::HEADING,
    'label' => __( 'Badge & Separator', 'jellopoint-restaurant-menu' ),
    'separator' => 'before',
] );
$repeater->add_control( 'item_badge', [ 'label'=>__( 'Badge','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::TEXT ] );
        $repeater->add_control( 'item_badge_position', [ 'label'=>__( 'Badge Position','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'default'=>'corner-right', 'options'=>[ 'corner-left'=>__( 'Corner Left','jellopoint-restaurant-menu' ), 'corner-right'=>__( 'Corner Right','jellopoint-restaurant-menu' ), 'inline'=>__( 'Inline (next to title)','jellopoint-restaurant-menu' ) ], 'condition'=>[ 'item_badge!'=>'' ] ] );
        $repeater->add_control( 'show_separator', [ 'label'=>__( 'Separator','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SWITCHER, 'return_value'=>'yes', 'default'=>'yes' ] );

        $this->add_control( 'items', [ 'label'=>__( 'Items','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::REPEATER, 'fields'=>$repeater->get_controls(), 'title_field'=>'{{{ item_title }}}', 'condition'=>[ 'data_source'=>'static' ] ] );
        $this->end_controls_section();

        /* ===== Layout ===== */
        $this->start_controls_section( 'section_layout', [ 'label'=>__( 'List Layout','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_CONTENT ] );
        $this->add_responsive_control( 'columns', [ 'label'=>__( 'Columns','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'options'=>[ '1'=>'1', '2'=>'2', '3'=>'3' ], 'default'=>'1', 'selectors'=>[ '{{WRAPPER}} .jp-menu'=>'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ] ] );
        $this->add_responsive_control( 'row_gap', [ 'label'=>__( 'Row Gap','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>0, 'max'=>80 ] ], 'default'=>[ 'size'=>20, 'unit'=>'px' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu'=>'grid-row-gap: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'column_gap', [ 'label'=>__( 'Column Gap','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>0, 'max'=>80 ] ], 'default'=>[ 'size'=>20, 'unit'=>'px' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu'=>'grid-column-gap: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'alignment', [ 'label'=>__( 'Alignment','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::CHOOSE, 'options'=>[ 'left'=>[ 'title'=>__( 'Left','jellopoint-restaurant-menu' ), 'icon'=>'eicon-text-align-left' ], 'center'=>[ 'title'=>__( 'Center','jellopoint-restaurant-menu' ), 'icon'=>'eicon-text-align-center' ], 'right'=>[ 'title'=>__( 'Right','jellopoint-restaurant-menu' ), 'icon'=>'eicon-text-align-right' ] ], 'default'=>'left', 'selectors'=>[ '{{WRAPPER}} .jp-menu__content'=>'text-align: {{VALUE}};' ] ] );
        
        $this->add_control( 'row_order', [
            'label' => __( 'Columns Order', 'jellopoint-restaurant-menu' ),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'label_left' => __( 'Label left / Price right', 'jellopoint-restaurant-menu' ),
                'price_left' => __( 'Price left / Label right', 'jellopoint-restaurant-menu' ),
            ],
            'default' => 'label_left',
        ] );
        $this->add_control( 'label_presentation', [
            'label' => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'text' => __( 'Text', 'jellopoint-restaurant-menu' ),
                'icon' => __( 'Icon', 'jellopoint-restaurant-menu' ),
            ],
            'default' => 'text',
        ] );

        $this->end_controls_section();

        /* ===== Style: Row ===== */
        $this->start_controls_section( 'section_style_row', [ 'label'=>__( 'Row','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_responsive_control( 'row_padding', [ 'label'=>__( 'Padding','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::DIMENSIONS, 'size_units'=>[ 'px','%','em' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__item'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->add_group_control( Group_Control_Background::get_type(), [ 'name'=>'row_background', 'selector'=>'{{WRAPPER}} .jp-menu__item' ] );
        $this->add_group_control( Group_Control_Border::get_type(), [ 'name'=>'row_border', 'selector'=>'{{WRAPPER}} .jp-menu__item' ] );
        $this->add_responsive_control( 'row_radius', [ 'label'=>__( 'Border Radius','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::DIMENSIONS, 'size_units'=>[ 'px','%' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__item'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->end_controls_section();

        /* ===== Style: Image ===== */
        $this->start_controls_section( 'section_style_image', [ 'label'=>__( 'Image','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_responsive_control( 'image_width', [ 'label'=>__( 'Width','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>30, 'max'=>300 ] ], 'default'=>[ 'size'=>80, 'unit'=>'px' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__media img'=>'width: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'image_spacing', [ 'label'=>__( 'Spacing','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>0, 'max'=>60 ] ], 'default'=>[ 'size'=>15, 'unit'=>'px' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__media'=>'margin-right: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .jp-menu__item--image-right .jp-menu__media'=>'margin-right: 0; margin-left: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'image_radius', [ 'label'=>__( 'Border Radius','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::DIMENSIONS, 'size_units'=>[ 'px','%' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__media img'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->end_controls_section();

        /* ===== Style: Title ===== */
        $this->start_controls_section( 'section_style_title', [ 'label'=>__( 'Title','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'title_color', [ 'label'=>__( 'Color','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::COLOR, 'selectors'=>[ '{{WRAPPER}} .jp-menu__title'=>'color: {{VALUE}};' ] ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name'=>'title_typo', 'selector'=>'{{WRAPPER}} .jp-menu__title' ] );
        $this->add_responsive_control( 'title_spacing', [ 'label'=>__( 'Spacing','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>0, 'max'=>40 ] ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__title'=>'margin-bottom: {{SIZE}}{{UNIT}};' ] ] );
        $this->end_controls_section();

        
        /* ===== Style: Price ===== */
        $this->start_controls_section( 'section_style_price', [
            'label' => __( 'Price', 'jellopoint-restaurant-menu' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'price_color', [
            'label' => __( 'Color', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .jp-menu__price' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'price_typo',
            'selector' => '{{WRAPPER}} .jp-menu__price',
        ] );

        $this->add_control( 'price_label_color', [
            'label' => __( 'Label Color', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .jp-price-label' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'price_label_typo',
            'selector' => '{{WRAPPER}} .jp-price-label',
        ] );

        $this->add_responsive_control( 'price_label_gap', [
            'label' => __( 'Label Gap', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::SLIDER,
            'range' => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
            'selectors' => [ '{{WRAPPER}} .jp-price-label' => 'margin-right: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'price_min_width', [
            'label' => __( 'Price Min-Width', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::SLIDER,
            'range' => [ 'px' => [ 'min' => 0, 'max' => 300 ] ],
            'selectors' => [ '{{WRAPPER}} .jp-menu__price' => 'min-width: {{SIZE}}{{UNIT}}; display:inline-block; text-align:right;' ],
        ] );

        $this->add_responsive_control( 'multi_row_gap', [
            'label' => __( 'Row Gap (multi)', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::SLIDER,
            'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'selectors' => [ '{{WRAPPER}} .jp-menu__pricegroup' => 'row-gap: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'price_icon_size', [
            'label' => __( 'Icon Size', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::SLIDER,
            'range' => [ 'px' => [ 'min' => 8, 'max' => 64 ] ],
            'default' => [ 'size' => 18, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .jp-price-icon, {{WRAPPER}} .jp-price-icon img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .jp-price-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .jp-price-icon i' => 'font-size: {{SIZE}}{{UNIT}}; line-height: 1;',
            ],
        ] );

        $this->add_responsive_control( 'price_icon_gap', [
            'label' => __( 'Icon Gap', 'jellopoint-restaurant-menu' ),
            'type'  => Controls_Manager::SLIDER,
            'range' => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
            'default' => [ 'size' => 6, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .jp-price-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();

    

        /* =====  Style: Description ===== */
        $this->start_controls_section( 'section_style_desc', [ 'label'=>__( 'Description','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'desc_color', [ 'label'=>__( 'Color','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::COLOR, 'selectors'=>[ '{{WRAPPER}} .jp-menu__desc'=>'color: {{VALUE}};' ] ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name'=>'desc_typo', 'selector'=>'{{WRAPPER}} .jp-menu__desc' ] );
        $this->end_controls_section();

        /* ===== Style: Separator ===== */
        $this->start_controls_section( 'section_style_sep', [ 'label'=>__( 'Separator','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'sep_style', [ 'label'=>__( 'Style','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SELECT, 'default'=>'solid', 'options'=>[ 'none'=>__( 'None','jellopoint-restaurant-menu' ), 'solid'=>__( 'Solid','jellopoint-restaurant-menu' ), 'dashed'=>__( 'Dashed','jellopoint-restaurant-menu' ), 'dotted'=>__( 'Dotted','jellopoint-restaurant-menu' ) ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__separator'=>'border-bottom-style: {{VALUE}};' ] ] );
        $this->add_responsive_control( 'sep_weight', [ 'label'=>__( 'Weight','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>0, 'max'=>10 ] ], 'default'=>[ 'size'=>1, 'unit'=>'px' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__separator'=>'border-bottom-width: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_control( 'sep_color', [ 'label'=>__( 'Color','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::COLOR, 'selectors'=>[ '{{WRAPPER}} .jp-menu__separator'=>'border-bottom-color: {{VALUE}};' ] ] );
        $this->end_controls_section();

        /* ===== Style: Badge ===== */
        $this->start_controls_section( 'section_style_badge', [ 'label'=>__( 'Badge','jellopoint-restaurant-menu' ), 'tab'=>Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'badge_color', [ 'label'=>__( 'Text Color','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::COLOR, 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge'=>'color: {{VALUE}};' ] ] );
        $this->add_control( 'badge_bg', [ 'label'=>__( 'Background','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::COLOR, 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge'=>'background-color: {{VALUE}};' ] ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name'=>'badge_typo', 'selector'=>'{{WRAPPER}} .jp-menu__badge' ] );
        $this->add_responsive_control( 'badge_padding', [ 'label'=>__( 'Padding','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::DIMENSIONS, 'size_units'=>[ 'px','em' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'badge_radius', [ 'label'=>__( 'Border Radius','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::DIMENSIONS, 'size_units'=>[ 'px','%' ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'badge_offset_x', [ 'label'=>__( 'Corner Offset X','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>-40, 'max'=>40 ] ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge--corner.jp-menu__badge--corner-left'=>'left: calc(8px + {{SIZE}}{{UNIT}});', '{{WRAPPER}} .jp-menu__badge--corner.jp-menu__badge--corner-right'=>'right: calc(8px + {{SIZE}}{{UNIT}});' ] ] );
        $this->add_responsive_control( 'badge_offset_y', [ 'label'=>__( 'Corner Offset Y','jellopoint-restaurant-menu' ), 'type'=>Controls_Manager::SLIDER, 'range'=>[ 'px'=>[ 'min'=>-40, 'max'=>40 ] ], 'selectors'=>[ '{{WRAPPER}} .jp-menu__badge--corner'=>'top: calc(8px + {{SIZE}}{{UNIT}});' ] ] );
        $this->end_controls_section();
    }

    private function get_tax_options( $tax ) {
        $opts = [];
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_terms( [ 'taxonomy'=>$tax, 'hide_empty'=>false ] );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) { $opts[ $t->slug ] = $t->name; }
            }
        }
        return $opts;
    }

    /* ===== Rendering ===== */
    protected function render_static_item( $item ) {
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
                        $icon_override_type = $item['price' . $i . '_icon_override_type'] ?? '';
$icon_override = $item['price' . $i . '_icon_override'] ?? [];
if ( is_string($icon_override) ) { $dec = json_decode($icon_override, true); if ( is_array($dec) ) { $icon_override = $dec; } }
$icon_override_active = !empty($icon_override_type) || ( is_array($icon_override) && !empty($icon_override) );
$hide_slot = isset($hide_slot) ? $hide_slot : false;
$rows[] = [ 'label' => $label, 'price' => $amount, 'icon_id' => $iconid, 'icon_override_type' => $icon_override_type, 'icon_override' => $icon_override, 'icon_override_active' => $icon_override_active, 'hide_icon' => $hide_slot ];
                    }
                }
            }
            if ( ! empty($rows) ) { $is_multi = true; }
        }
        $sep  = ( isset( $item['show_separator'] ) && 'yes' === $item['show_separator'] );

        $classes = [ 'jp-menu__item' ];
        if ( $img_pos === 'right' ) $classes.append( 'jp-menu__item--image-right' );
        echo '<li class="'.esc_attr( implode( ' ', $classes ) ).'">';

        if ( $badge ) {
            $badge_class = 'jp-menu__badge';
            if ( $badge_pos === 'inline' ) $badge_class .= ' jp-menu__badge--inline';
            else $badge_class .= ' jp-menu__badge--corner ' . ( $badge_pos === 'corner-left' ? 'jp-menu__badge--corner-left' : 'jp-menu__badge--corner-right' );
            echo '<span class="'.esc_attr($badge_class).'">'.esc_html($badge).'</span>';
        }

        if ( $img ) {
            $img_html = Group_Control_Image_Size::get_attachment_image_html( $item, 'item_image_size', 'item_image' );
            if ( $img_html ) echo '<div class="jp-menu__media">'.$img_html.'</div>';
        }

        
        // Normalize: if not using multi but a single price is set, use the same pricegroup
        if ( ! $is_multi && ! empty( $price ) ) {
            $rows[] = [ 'label' => $price_label, 'price' => $price ];
            $is_multi = true;
            $price = '';
        }
echo '<div class="jp-menu__content">';
            echo '<div class="jp-menu__header">';
                echo '<span class="jp-menu__title">'.esc_html($title).'</span>';
                echo '<span class="jp-menu__price"></span>';
            echo '</div>';

            if ( $desc ) echo '<div class="jp-menu__desc">'.wp_kses_post($desc).'</div>';

            if ( $is_multi && ! empty( $rows ) ) {
                echo '<div class="jp-menu__pricegroup">';
                foreach ( $rows as $r ) {
                    $lbl = isset( $r['label'] ) ? esc_html( $r['label'] ) : '';
                    $val = isset( $r['price'] ) ? wp_kses_post( $r['price'] ) : '';
                    if ( $lbl === '' && $val === '' ) continue;
                    
                    $icon_html = '';
                    if ( empty($r['hide_icon']) && $show_icons ) {
                        $ov = isset($r['icon_override']) ? $r['icon_override'] : [];
                        $ov_active = !empty($r['icon_override_active']);
                        if ( $ov_active && !empty($ov) && class_exists('\Elementor\Icons_Manager') ) {
                            if ( method_exists('\Elementor\Icons_Manager', 'enqueue_icon') ) {
                                \Elementor\Icons_Manager::enqueue_icon( $ov );
                            }
                            
                            // Capture any echo and prefer proper string markup
                            ob_start();
                            $ret_markup  = \Elementor\Icons_Manager::render_icon( $ov, [ 'aria-hidden' => 'true' ] );
                            $echo_markup = ob_get_clean();
                            $icon_markup = '';
                            if ( is_string( $echo_markup ) && trim( $echo_markup ) !== '' ) {
                                $icon_markup = $echo_markup;
                            } elseif ( is_string( $ret_markup ) && trim( $ret_markup ) !== '' ) {
                                $icon_markup = $ret_markup;
                            } else {
                                // Fallback: try with explicit <i> tag for FA
                                ob_start();
                                $ret_markup2  = \Elementor\Icons_Manager::render_icon( $ov, [ 'aria-hidden' => 'true' ], 'i' );
                                $echo_markup2 = ob_get_clean();
                                if ( is_string( $echo_markup2 ) && trim( $echo_markup2 ) !== '' ) {
                                    $icon_markup = $echo_markup2;
                                } elseif ( is_string( $ret_markup2 ) && trim( $ret_markup2 ) !== '' ) {
                                    $icon_markup = $ret_markup2;
                                }
                            }
                            if ( ! empty( $icon_markup ) ) {
                                $icon_html = '<span class="jp-price-icon" aria-hidden="true">' . $icon_markup . '</span>';
                            }
                        
                        } elseif ( !empty($r['icon_id']) ) {
                            $icon_html = '<span class="jp-price-icon" aria-hidden="true">' . wp_get_attachment_image( intval($r['icon_id']), 'thumbnail', false, [ 'alt' => '' ] ) . '</span>';
                        }
                    }
                    $row_order = isset($s['row_order']) ? $s['row_order'] : 'label_left';
$label_presentation = isset($s['label_presentation']) ? $s['label_presentation'] : 'text';
$show_text_label = ($label_presentation === 'text');
$show_icon_label = ($label_presentation === 'icon');
$wrapper_class = 'jp-menu__price-row ' . ( $row_order === 'price_left' ? 'jp-order--price-left' : 'jp-order--label-left' );
$label_html = '';
if ($show_icon_label) {
  $label_html = $icon_html;
} else {
  $label_html = '<span class="jp-price-label">' . esc_html( $lbl ) . '</span>';
  $icon_html = '';
}
echo '<div class="' . $wrapper_class . '">'
   . '<span class="jp-col jp-col-labelwrap">' . $icon_html . $label_html . '</span>'
   . '<span class="jp-col jp-col-price">' . $val . '</span>'
   . '</div>';




                }
                echo '</div>';
            }

            if ( $sep ) echo '<div class="jp-menu__separator" aria-hidden="true"></div>';
        echo '</div></li>';
    }

    protected function render_static() {
        $s = $this->get_settings_for_display();
        $items = isset( $s['items'] ) && is_array( $s['items'] ) ? $s['items'] : [];
        echo '<ul class="jp-menu">';
        foreach ( $items as $it ) { $this->render_static_item( $it ); }
        echo '</ul>';
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        if ( isset( $s['data_source'] ) && $s['data_source'] === 'static' ) {
            $this->render_static();
        // Inline minimal CSS for column ordering and label/icon wrap
        echo '<style>.jp-menu__inner{display:grid;grid-template-columns:1fr auto;align-items:start;gap:1rem}.jp-box-right{display:flex;flex-direction:column;align-items:flex-end}.jp-menu__pricegroup{display:inline-grid;justify-items:end;text-align:right}.jp-menu__price-row{display:flex;align-items:center;justify-content:space-between;gap:.5em;width:100%}.jp-menu__price-row .jp-col{display:block}.jp-menu__price-row .jp-col.jp-col-labelwrap{display:inline-flex;align-items:center;gap:.5em}.jp-menu__price-row.jp-order--price-left .jp-col-price{order:1}.jp-menu__price-row.jp-order--price-left .jp-col-labelwrap{order:2}.jp-menu__price-row.jp-order--label-left .jp-col-labelwrap{order:1}.jp-menu__price-row.jp-order--label-left .jp-col-price{order:2}</style>';

        } else {
            $menus = isset($s['query_menus']) ? (array) $s['query_menus'] : [];
            $sections = isset($s['query_sections']) ? (array) $s['query_sections'] : [];
            $shortcode = '[jprm_menu';
            $shortcode .= ' menu="' . esc_attr( implode( ',', $menus ) ) . '"';
            $shortcode .= ' sections="' . esc_attr( implode( ',', $sections ) ) . '"';
            $shortcode .= ' orderby="' . esc_attr( isset($s['query_orderby']) ? $s['query_orderby'] : 'menu_order' ) . '"';
            $shortcode .= ' order="' . esc_attr( isset($s['query_order']) ? $s['query_order'] : 'ASC' ) . '"';
            $shortcode .= ' limit="' . esc_attr( isset($s['query_limit']) ? $s['query_limit'] : -1 ) . '"';
            $shortcode .= ' hide_invisible="' . ( isset($s['hide_invisible']) && $s['hide_invisible']==='yes' ? '1' : '0' ) . '"';
            $shortcode .= ' row_order="' . esc_attr( isset($s['row_order']) ? $s['row_order'] : 'label_left' ) . '"';
            $shortcode .= ' label_presentation="' . esc_attr( isset($s['label_presentation']) ? $s['label_presentation'] : 'text' ) . '"';
            $shortcode .= ' dedupe="' . esc_attr( isset($s['dedupe']) ? $s['dedupe'] : 'deepest_only' ) . '"';
            $shortcode .= ']';
            echo do_shortcode( $shortcode );
        }
        }
    }
}
