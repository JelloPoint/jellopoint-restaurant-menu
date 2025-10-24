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



/* ===== Prices & Labels (wired to current CSS classes) ===== */
$this->start_controls_section(
    'jprm_style_prices_labels',
    [
        'label' => __( 'Prices & Labels', 'jellopoint-restaurant-menu' ),
        'tab'   => Controls_Manager::TAB_STYLE,
    ]
);

/* ==============================
 * Prices (grouped by layout)
 * ============================== */

$this->add_control(
    'jprm_prices_heading',
    [
        'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'none',
    ]
);

/* --- Common  (applies to all layouts) ---------------------- */

$this->add_control(
    'jprm_prices_common_heading',
    [
        'label'     => __( 'Common', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'none',
    ]
);

// Common → Typography — FIX for Matrix value cells (no .jp-price wrapper in Matrix)
$this->add_group_control(
    \Elementor\Group_Control_Typography::get_type(),
    [
        'name'     => 'jprm_price_typography',
        'selector' => '{{WRAPPER}} .jp-price, {{WRAPPER}} .jp-matrix__cell--value',
    ]
);

// Common → Color — FIX for Matrix value cells
$this->add_control(
    'jprm_price_color',
    [
        'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-price'                 => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix__cell--value'   => 'color: {{VALUE}};',
        ],
    ]
);

// Vertical gap between multiple price rows (affects inline & inline-below)
$this->add_responsive_control(
    'jprm_price_rows_gap',
    [
        'label'      => __( 'Row Gap (multiple prices)', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 2,  'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 32, 'step' => 1 ],
        ],
        'selectors'  => [
            // Inline & Inline-Below: each additional .jp-price-row
            '{{WRAPPER}} .jp-menu__pricegroup .jp-price-row + .jp-price-row' => 'margin-top: {{SIZE}}{{UNIT}};',
            // Matrix: multiple .jp-price inside the same value cell
            '{{WRAPPER}} .jp-matrix__cell--value .jp-price + .jp-price' => 'margin-top: {{SIZE}}{{UNIT}};',
        ],
    ]
);

/* --- Inline ------------------------------------------------ */

$this->add_control(
    'jprm_prices_inline_heading',
    [
        'label'     => __( 'Inline', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

// Inline → Price Column Alignment — FIX
$this->add_responsive_control(
    'jprm_price_align_inline',
    [
        'label'   => __( 'Price Column Alignment', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'start'  => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
            'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'end'    => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            // Grid item alignment + text fallback (covers varied CSS)
            '{{WRAPPER}} .jp-inline .jp-menu__pricegroup' => 'justify-self: {{VALUE}}; text-align: {{VALUE == "start" ? "left" : (VALUE == "end" ? "right" : "center")}};',
        ],
    ]
);

/* --- Inline-Below ----------------------------------------- */

$this->add_control(
    'jprm_prices_inline_below_heading',
    [
        'label'     => __( 'Inline-Below', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

// Column alignment (Inline-Below)
$this->add_responsive_control(
    'jprm_price_align_inline_below',
    [
        'label'   => __( 'Price Column Alignment', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'start'  => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
            'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'end'    => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-menu__pricegroup' => 'justify-self: {{VALUE}};',
        ],
    ]
);

// Gap from price rows to chip/labels line (Inline-Below)
$this->add_responsive_control(
    'jprm_price_labels_gap_inline_below',
    [
        'label'      => __( 'Gap to Labels', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 3,  'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 48, 'step' => 1 ],
        ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-chipline' => 'margin-top: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Separator appearance (toggle stays in Content)
$this->add_control(
    'jprm_sep_inline_below_heading',
    [
        'label'     => __( 'Separator', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

$this->add_control(
    'jprm_sep_color_inline_below',
    [
        'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'color: {{VALUE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_sep_opacity_inline_below',
    [
        'label' => __( 'Opacity', 'jellopoint-restaurant-menu' ),
        'type'  => Controls_Manager::SLIDER,
        'range' => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ] ],
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-sep' => 'opacity: {{SIZE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_sep_xpad_inline_below',
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

/* --- Matrix ------------------------------------------------ */

$this->add_control(
    'jprm_prices_matrix_heading',
    [
        'label'     => __( 'Matrix', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::HEADING,
        'separator' => 'before',
    ]
);

// Matrix → Value Cell Alignment — target the actual value cells
$this->add_responsive_control(
    'jprm_price_align_matrix',
    [
        'label'   => __( 'Value Cell Alignment', 'jellopoint-restaurant-menu' ),
        'type'    => Controls_Manager::CHOOSE,
        'options' => [
            'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
            'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
            'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
        ],
        'selectors' => [
            // Apply directly on the cell so both plain text "$ 5" and placeholders align
            '{{WRAPPER}} .jp-matrix__row .jp-matrix__cell--value' => 'text-align: {{VALUE}};',
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
            '{{WRAPPER}} .jp-menu__label, ' .
            '{{WRAPPER}} .jp-matrix .jp-menu__label',
    ]
);

// Label text color (affects only text now; icon color will work after renderer tweak)
$this->add_control(
    'jprm_label_text_color',
    [
        'label'     => __( 'Text Color', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-menu__label'          => 'color: {{VALUE}};',
            '{{WRAPPER}} .jp-matrix .jp-menu__label' => 'color: {{VALUE}};',
        ],
    ]
);

// Labels → Icon Size — FORCE override (works in Matrix header/rows and inline)
$this->add_responsive_control(
    'jprm_label_icon_size',
    [
        'label'      => __( 'Icon Size', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 8, 'max' => 64, 'step' => 1 ] ],
        'selectors'  => [
            // Override HTML width/height attributes and any theme max-width
            '{{WRAPPER}} img.jp-menu__icon' => 'width: {{SIZE}}{{UNIT}} !important; height: auto !important; max-width: none !important;',
        ],
    ]
);

// Icon gap (works for IMG now; will also apply to inline SVG later)
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
            '{{WRAPPER}} .jp-menu__icon'            => 'margin-right: {{SIZE}}{{UNIT}};',
            '{{WRAPPER}} .jp-matrix .jp-menu__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
        ],
    ]
);

// Chip styling (Inline-Below chips)
$this->add_responsive_control(
    'jprm_label_chip_padding',
    [
        'label'      => __( 'Chip Padding (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::DIMENSIONS,
        'size_units' => [ 'px', 'em' ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_label_chip_radius',
    [
        'label'      => __( 'Chip Radius (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 0, 'max' => 24, 'step' => 1 ] ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' => 'border-radius: {{SIZE}}{{UNIT}};',
        ],
    ]
);

$this->add_control(
    'jprm_label_chip_bg',
    [
        'label'     => __( 'Chip Background (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' => 'background-color: {{VALUE}};',
        ],
    ]
);

$this->add_responsive_control(
    'jprm_label_chip_border_width',
    [
        'label'      => __( 'Chip Border Width (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'px' ],
        'range'      => [ 'px' => [ 'min' => 0, 'max' => 4, 'step' => 1 ] ],
        'selectors'  => [
            '{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
        ],
    ]
);

$this->add_control(
    'jprm_label_chip_border_color',
    [
        'label'     => __( 'Chip Border Color (Inline-Below)', 'jellopoint-restaurant-menu' ),
        'type'      => Controls_Manager::COLOR,
        'selectors' => [
            '{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' => 'border-color: {{VALUE}};',
        ],
    ]
);

// Labels row gap
$this->add_responsive_control(
    'jprm_labels_gap',
    [
        'label'      => __( 'Labels Row Gap', 'jellopoint-restaurant-menu' ),
        'type'       => Controls_Manager::SLIDER,
        'size_units' => [ 'em', 'px' ],
        'range'      => [
            'em' => [ 'min' => 0, 'max' => 2, 'step' => 0.05 ],
            'px' => [ 'min' => 0, 'max' => 32, 'step' => 1 ],
        ],
        'selectors'  => [
            // General rows inside pricegroup
            '{{WRAPPER}} .jp-pricegroup .jp-menu__row' => 'gap: {{SIZE}}{{UNIT}};',
            // Inline-Below chip line
            '{{WRAPPER}} .jp-inline-below .jp-chipline'      => 'gap: {{SIZE}}{{UNIT}};',
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
