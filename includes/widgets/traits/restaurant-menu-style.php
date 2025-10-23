<?php
namespace JelloPoint\RestaurantMenu\Widgets\Traits;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Restaurant_Menu_Style {
	protected function register_style_controls() : void {

		/* ===== Menu Title & Description (scoped to meta only) ===== */
		$this->start_controls_section(
			'jprm_style_menu_meta',
			[
				'label' => __( 'Menu Title & Description', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		/* ==============================
		* Menu Title  (META ONLY)
		* ============================== */
		$this->add_control(
			'jprm_menu_title_heading',
			[
				'label'     => __( 'Menu Title', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_menu_title_typography',
				'selector' => '{{WRAPPER}} .jp-menu__meta .jp-menu__title',
			]
		);

		$this->add_control(
			'jprm_menu_title_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__title' => 'color: {{VALUE}};',
				],
			]
		);

		// Independent alignment — meta title only
		$this->add_responsive_control(
			'jprm_menu_title_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_menu_title_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_menu_title_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		/* ==============================
		* Menu Description  (META ONLY)
		* ============================== */
		$this->add_control(
			'jprm_menu_desc_heading',
			[
				'label'     => __( 'Menu Description', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_menu_desc_typography',
				'selector' => '{{WRAPPER}} .jp-menu__meta .jp-menu__desc',
			]
		);

		$this->add_control(
			'jprm_menu_desc_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__desc' => 'color: {{VALUE}};',
				],
			]
		);

		// Independent alignment — meta description only
		$this->add_responsive_control(
			'jprm_menu_desc_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__desc' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_menu_desc_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_menu_desc_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__meta .jp-menu__desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		/* ===== Item Title & Description (items only, incl. Matrix) ===== */
		$this->start_controls_section(
			'jprm_style_item_text',
			[
				'label' => __( 'Item Title & Description', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		/* ==============================
		* Item Title
		* ============================== */
		$this->add_control(
			'jprm_item_title_heading',
			[
				'label'     => __( 'Item Title', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_item_title_typography',
				'selector' =>
					// List/inline layouts
					'{{WRAPPER}} .jp-menu__item .jp-menu__title, ' .
					// Matrix layout
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title',
			]
		);

		$this->add_control(
			'jprm_item_title_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'        => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_title_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'        => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_title_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'        => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_title_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'        => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		/* ==============================
		* Item Description
		* ============================== */
		$this->add_control(
			'jprm_item_desc_heading',
			[
				'label'     => __( 'Item Description', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_item_desc_typography',
				'selector' =>
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc, ' .
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc',
			]
		);

		$this->add_control(
			'jprm_item_desc_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc'  => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_desc_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc'  => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_desc_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc'  => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_desc_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc'  => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();



/* ===== Prices & Labels ===== */
$this->start_controls_section(
    'jprm_style_prices_labels',
    [
        'label' => __( 'Prices & Labels', 'jellopoint-restaurant-menu' ),
        'tab'   => Controls_Manager::TAB_STYLE,
    ]
);

/* ==============================
 * Prices
 * ============================== */
$this->add_control(
    'jprm_prices_heading',
    [
        'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'none',
    ]
);

// Amount typography
$this->add_group_control(
    \Elementor\Group_Control_Typography::get_type(),
    [
        'name'     => 'jprm_price_amount_typography',
        'selector' =>
            // Inline / Inline-below
            '{{WRAPPER}} .jp-price__amount, ' .
            // Matrix cell
            '{{WRAPPER}} .jp-matrix__cell--price .jp-price__amount',
    ]
);

// Amount color
$this->add_control(
    'jprm_price_amount_color',
    [
        'label'     => __( 'Amount Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-price__amount'                      => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix__cell--price .jp-price__amount' => 'color: {{VALUE}};',
        ],
    ]
);

// Currency color (visual; position stays in Content tab)
$this->add_control(
    'jprm_price_currency_color',
    [
        'label'     => __( 'Currency Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-price__currency'                      => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix__cell--price .jp-price__currency' => 'color: {{VALUE}};',
        ],
    ]
);

// Currency spacing (visual; Content controls Position)
$this->add_responsive_control(
    'jprm_price_currency_gap',
    [
        'label'      => __( 'Currency Spacing', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 1,   'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 16,  'step' => 1 ],
        ],
        'selectors'  => [
            // If currency is before amount
            '{{WRAPPER}} .jp-price--before .jp-price__currency' => 'margin-right: {{SIZE}}{{UNIT}};',
            // If currency is after amount
            '{{WRAPPER}} .jp-price--after .jp-price__currency'  => 'margin-left: {{SIZE}}{{UNIT}};',
            // Matrix variants
            '{{WRAPPER}} .jp-matrix__cell--price .jp-price--before .jp-price__currency' => 'margin-right: {{SIZE}}{{UNIT}};',
            '{{WRAPPER}} .jp-matrix__cell--price .jp-price--after .jp-price__currency'  => 'margin-left: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Alignment (Inline + Inline-below)
$this->add_responsive_control(
    'jprm_price_align',
    [
        'label'   => __( 'Price Alignment', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-left' ],
            'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            '{{WRAPPER}} .jp-inline .jp-price'       => 'text-align: {{VALUE}};',
            '{{WRAPPER}} .jp-inline-below .jp-price' => 'text-align: {{VALUE}};',
        ],
    ]
);

// Matrix: alignment (keep separate; matrix cells behave differently)
$this->add_responsive_control(
    'jprm_price_align_matrix',
    [
        'label'   => __( 'Price Alignment (Matrix)', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-left' ],
            'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            '{{WRAPPER}} .jp-matrix__cell--price' => 'text-align: {{VALUE}};',
        ],
    ]
);

// Inline-below: vertical gap between price row and labels row
$this->add_responsive_control(
    'jprm_price_labels_gap',
    [
        'label'      => __( 'Gap to Labels (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 3,  'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 48, 'step' => 1 ],
        ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-labels' => 'margin-top: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Inline-below separator appearance (toggle lives in Content)
$this->add_control(
    'jprm_sep_heading',
    [
        'label'     => __( 'Separator (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

$this->add_control(
    'jprm_sep_char',
    [
        'label'       => __( 'Character', 'jellopoint-restaurant-menu' ),
        'type'        => Controls_Manager::TEXT,
        'default'     => '•',
        'placeholder' => '•  –  /  ·',
        'selectors'   => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'content: "{{VALUE}}";',
        ],
        'description' => __( 'Used only when separator is enabled in Content tab.', 'jellopoint-restaurant-menu' ),
    ]
);

$this->add_control(
    'jprm_sep_color',
    [
        'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'color: {{VALUE}}; opacity: var(--jprm-sep-opacity,1);',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_sep_opacity',
    [
        'label' => __( 'Opacity', 'jellopoint-restaurant-menu' ),
        'type'  => Controls_Manager::SLIDER,
        'range' => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ] ],
        'selectors' => [
            '{{WRAPPER}}' => '--jprm-sep-opacity: {{SIZE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_sep_xpad',
    [
        'label'      => __( 'Horizontal Padding', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 2,  'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 24, 'step' => 1 ],
        ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'padding: 0 {{SIZE}}{{UNIT}};',
        ],
    ]
);

$this->add_control(
    'jprm_sep_valign',
    [
        'label'   => __( 'Vertical Align', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::SELECT,
        'default' => 'baseline',
        'options' => [
            'baseline' => __( 'Baseline', 'jellopoint-restaurant-menu' ),
            'middle'   => __( 'Middle', 'jellopoint-restaurant-menu' ),
        ],
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'vertical-align: {{VALUE}};',
        ],
    ]
);

/* ==============================
 * Labels
 * ============================== */
$this->add_control(
    'jprm_labels_heading',
    [
        'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

// Label text typography
$this->add_group_control(
    \Elementor\Group_Control_Typography::get_type(),
    [
        'name'     => 'jprm_label_typography',
        'selector' =>
            '{{WRAPPER}} .jp-label, ' .
            '{{WRAPPER}} .jp-matrix__cell--labels .jp-label',
    ]
);

// Label text color (also drives icon color if icon inherits currentColor)
$this->add_control(
    'jprm_label_text_color',
    [
        'label'     => __( 'Text Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-label'                           => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix__cell--labels .jp-label'  => 'color: {{VALUE}};',
        ],
    ]
);

// Optional: explicit icon color (overrides inheritance)
$this->add_control(
    'jprm_label_icon_color',
    [
        'label'     => __( 'Icon Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-label__icon-svg'                          => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix__cell--labels .jp-label__icon-svg' => 'color: {{VALUE}};',
        ],
        'description' => __( 'If unset, icons inherit the label text color.', 'jellopoint-restaurant-menu' ),
    ]
);

// Icon size
$this->add_responsive_control(
    'jprm_label_icon_size',
    [
        'label'      => __( 'Icon Size', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 8, 'max' => 64, 'step' => 1 ] ],
        'selectors'  => [
            '{{WRAPPER}} .jp-label__icon-svg'                          => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            '{{WRAPPER}} .jp-matrix__cell--labels .jp-label__icon-svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Icon gap
$this->add_responsive_control(
    'jprm_label_icon_gap',
    [
        'label'      => __( 'Icon Gap', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 1,  'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 24, 'step' => 1 ],
        ],
        'selectors'  => [
            '{{WRAPPER}} .jp-label__icon-svg'                          => 'margin-right: {{SIZE}}{{UNIT}};',
            '{{WRAPPER}} .jp-matrix__cell--labels .jp-label__icon-svg' => 'margin-right: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Chip styling (Inline-Below / chip variants)
$this->add_responsive_control(
    'jprm_label_chip_padding',
    [
        'label'      => __( 'Chip Padding', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::DIMENSIONS,
        'size_units' => [ 'px', 'em' ],
        'selectors'  => [
            '{{WRAPPER}} .jp-label.is-chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_label_chip_radius',
    [
        'label'      => __( 'Chip Radius', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 0, 'max' => 24, 'step' => 1 ] ],
        'selectors'  => [
            '{{WRAPPER}} .jp-label.is-chip' => 'border-radius: {{SIZE}}{{UNIT}};',
        ],
    ]
);

$this->add_control(
    'jprm_label_chip_bg',
    [
        'label'     => __( 'Chip Background', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-label.is-chip' => 'background-color: {{VALUE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_label_chip_border_width',
    [
        'label'      => __( 'Chip Border Width', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 0, 'max' => 4, 'step' => 1 ] ],
        'selectors'  => [
            '{{WRAPPER}} .jp-label.is-chip' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
        ],
    ]
);

$this->add_control(
    'jprm_label_chip_border_color',
    [
        'label'     => __( 'Chip Border Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-label.is-chip' => 'border-color: {{VALUE}};',
        ],
    ]
);

// Labels flow & alignment
$this->add_responsive_control(
    'jprm_labels_gap',
    [
        'label'      => __( 'Inter-label Gap', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 2, 'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 32, 'step' => 1 ],
        ],
        'selectors'  => [
            '{{WRAPPER}} .jp-labels' => 'gap: {{SIZE}}{{UNIT}};',
        ],
    ]
);

$this->add_control(
    'jprm_labels_wrap',
    [
        'label'        => __( 'Wrap Labels', 'jellopoint-restaurant-menu' ),
        'type'         => Controls_Manager::SWITCHER,
        'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
        'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
        'return_value' => 'wrap',
        'selectors'    => [
            '{{WRAPPER}} .jp-labels' => 'flex-wrap: {{VALUE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_labels_align',
    [
        'label'   => __( 'Labels Alignment', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'flex-start' => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
            'center'     => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'flex-end'   => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            '{{WRAPPER}} .jp-labels' => 'justify-content: {{VALUE}};',
        ],
    ]
);

$this->end_controls_section();



		/* ===== Badges ===== */
		$this->start_controls_section('jprm_style_badges',[
			'label'=>__('Badges','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'badges_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jp-menu__titleline .jp-badge, {{WRAPPER}} .jp-menu__titleline [class*="badge"], {{WRAPPER}} .jp-menu__titleline .badge',
		]);
		$this->add_control('badges_color',[
			'label'=>__('Text Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__titleline .jp-badge'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__titleline [class*="badge"]'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__titleline .badge'=>'color: {{VALUE}};',
			],
		]);
		$this->add_responsive_control('badges_icon_size',[
			'label'=>__('Icon Size','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>8,'max'=>64]],
			'default'=>['size'=>20],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__titleline img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .jp-menu__titleline svg'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		]);
		$this->add_responsive_control('badges_gap',[
			'label'=>__('Gap around badges','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>0,'max'=>24]],
			'default'=>['size'=>0],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__titleline .jp-badge'=>'margin-inline: {{SIZE}}{{UNIT}};',
			],
		]);
		$this->end_controls_section();


		/* ===== Info Blocks ===== */
		$this->start_controls_section('jprm_style_infoblocks',[
			'label'=>__('Info Blocks','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'infob_body_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jprm-infoblock__content',
		]);
		$this->add_control('infob_body_color',[
			'label'=>__('Body Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>['{{WRAPPER}} .jprm-infoblock__content'=>'color: {{VALUE}};'],
		]);
		$this->add_control('infob_bg_color',[
			'label'=>__('Background Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'selectors'=>['{{WRAPPER}} .jprm-infoblock'=>'background-color: {{VALUE}};'],
		]);
		$this->add_responsive_control('infob_image_size',[
			'label'=>__('Image Size','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>16,'max'=>400]],
			'default'=>['size'=>80],
			'selectors'=>['{{WRAPPER}} .jprm-infoblock__image img'=>'width: {{SIZE}}{{UNIT}}; height:auto;'],
		]);
		$this->end_controls_section();


		/* ===== Matrix ===== */
		$this->start_controls_section('jprm_style_matrix',[
			'label'=>__('Matrix','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_control('matrix_title_min_width',[
			'label'=>__('Title column min width','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px','rem'],
			'range'=>[
				'px'=>['min'=>80,'max'=>600,'step'=>1],
				'rem'=>['min'=>6,'max'=>40,'step'=>0.1],
			],
			'default'=>['size'=>12,'unit'=>'rem'],
			'selectors'=>['{{WRAPPER}} .jp-matrix__cell--title'=>'min-width: {{SIZE}}{{UNIT}};'],
		]);
		$this->add_control('matrix_col_gap',[
			'label'=>__('Column gap','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>0,'max'=>48]],
			'default'=>['size'=>0],
			'selectors'=>['{{WRAPPER}} .jp-matrix'=>'column-gap: {{SIZE}}{{UNIT}};'],
		]);
		$this->add_control('matrix_row_gap',[
			'label'=>__('Row gap','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>0,'max'=>48]],
			'default'=>['size'=>0],
			'selectors'=>['{{WRAPPER}} .jp-matrix'=>'row-gap: {{SIZE}}{{UNIT}};'],
		]);
		$this->add_control('matrix_value_align',[
			'label'=>__('Value alignment','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::CHOOSE,
			'default'=>'left',
			'options'=>[
				'left'=>['title'=>__('Left','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-left'],
				'center'=>['title'=>__('Center','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-center'],
				'right'=>['title'=>__('Right','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-right'],
			],
			'selectors'=>['{{WRAPPER}} .jp-matrix__cell--value'=>'text-align: {{VALUE}};'],
		]);
		$this->end_controls_section();
	}
}
