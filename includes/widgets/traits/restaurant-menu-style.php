<?php
namespace JelloPoint\RestaurantMenu\Widgets\Traits;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Restaurant_Menu_Style {
	protected function register_style_controls() : void {
		/* ===== Design preset ===== */
		$this->start_controls_section(
			'section_design_preset',
			[
				'label' => __( 'Design Preset', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'style_preset',
			[
				'label'       => __( 'Predefined Style', 'jellopoint-restaurant-menu' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'default',
				'options'     => [
					'default' => __( 'Default / Custom', 'jellopoint-restaurant-menu' ),
					'classic' => __( 'Classic', 'jellopoint-restaurant-menu' ),
					'modern'  => __( 'Modern', 'jellopoint-restaurant-menu' ),
					'elegant' => __( 'Elegant', 'jellopoint-restaurant-menu' ),
				],
				'description' => __( 'Choose a starting design. The controls below can still be used to customize it.', 'jellopoint-restaurant-menu' ),
			]
		);

		$this->end_controls_section();

		/* ===== Menu Wrapper ===== */

		/* MENU */
		$this->start_controls_section(
			'jprm_wrapper_menu',
			[
				'label' => __( 'Menu & Section Styling', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_control(
			'jprm_menu_wrapper_heading',
			[
				'label'     => __( 'Menu', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);
		$this->add_responsive_control(
			'jprm_menu_wrapper_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu-grid--cols-1' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu-grid--cols-2' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu-grid--cols-3' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'jprm_menu_wrapper_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu-grid--cols-3' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu-grid--cols-2' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu-grid--cols-1' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_control( 'jprm_menu_wrapper_background', [
			'label'     => __( 'Background Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu-grid--cols-1' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu-grid--cols-2' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu-grid--cols-3' => 'background-color: {{VALUE}};',
			],
		] );

		// FIX: use selector (singular) so border actually renders
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'border-wrapper',
				'selector' => '{{WRAPPER}} .jp-menu-grid--cols-1, {{WRAPPER}} .jp-menu-grid--cols-2, {{WRAPPER}} .jp-menu-grid--cols-3',
			]
		);

		/* COLUMN */
		$this->add_control(
			'jprm_menu_column_heading',
			[
				'label'     => __( 'Menu Columns', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);
		$this->add_responsive_control(
			'jprm_menu_menu_column_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu--col' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'jprm_menu_column_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu--col' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_control( 'jprm_menu_column_background', [
			'label'     => __( 'Background Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu--col' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'menu-column-wrapper',
				'selector' => '{{WRAPPER}} .jp-menu--col',
			]
		);

		/* SECTIONS */
		$this->add_control(
			'jprm_menu_sections_heading',
			[
				'label'     => __( 'Menu Sections', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		// Margin around each section box
		$this->add_responsive_control(
			'jprm_menu_section_margin',
			[
				'label'      => __( 'Margin (outside box)', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section-box' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// Padding inside each section box
		$this->add_responsive_control(
			'jprm_menu_section_padding',
			[
				'label'      => __( 'Padding (inside box)', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section-box' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// Background for whole section box
		$this->add_control(
			'jprm_menu_section_background',
			[
				'label'     => __( 'Background Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__section-box' => 'background-color: {{VALUE}};',
				],
			]
		);

		// Border for whole section box
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'jprm_menu_section_border',
				'label'    => __( 'Border', 'jellopoint-restaurant-menu' ),
				'selector' => '{{WRAPPER}} .jp-menu__section-box',
			]
		);

		$this->end_controls_section();

		/* ===== Menu Title  & Description (scoped to meta only) ===== */
		$this->start_controls_section(
			'jprm_style_menu_meta',
			[
				'label' => __( 'Menu Title & Description', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		/* Menu Title (META ONLY) */
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

		/* Menu Description (META ONLY) */
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

		$this->add_control(
			'jprm_daily_menu_heading',
			[
				'label'     => __( 'Daily Menu Details', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'jprm_daily_menu_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-right' ],
				],
				'default' => 'center',
				'selectors' => [ '{{WRAPPER}} .jp-menu__daily-meta' => 'justify-content: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'jprm_daily_menu_spacing',
			[
				'label'      => __( 'Spacing', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [ '{{WRAPPER}} .jp-menu__daily-meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_control( 'jprm_daily_date_heading', [ 'label' => __( 'Date', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::HEADING ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'jprm_daily_date_typography', 'selector' => '{{WRAPPER}} .jp-menu__daily-date' ] );
		$this->add_control( 'jprm_daily_date_color', [
			'label' => __( 'Color', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .jp-menu__daily-date' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'jprm_daily_price_heading', [ 'label' => __( 'Fixed Price', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'jprm_daily_price_typography', 'selector' => '{{WRAPPER}} .jp-menu__daily-price' ] );
		$this->add_control( 'jprm_daily_price_color', [
			'label' => __( 'Color', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .jp-menu__daily-price' => 'color: {{VALUE}};' ],
		] );

		// Line height for items & matrix cells
		$this->add_responsive_control(
			'jprm_item_line_height',
			[
				'label'      => __( 'Line Height', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'em', 'px' ],
				'range'      => [
					'em' => [
						'min'  => 1,
						'max'  => 2,
						'step' => 0.05,
					],
					'px' => [
						'min'  => 14,
						'max'  => 40,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__item, {{WRAPPER}} .jp-menu__item *' =>
						'line-height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item, {{WRAPPER}} .jp-matrix__cell--item *' =>
						'line-height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		/* ===== Main Section Heading (Style Tab) ===== */
		$this->start_controls_section( 'jprm_section_style_main_section', [
			'label' => __( 'Main Section Heading', 'jellopoint-restaurant-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		// FIX: no direct child (>) because title now sits inside header wrapper
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'jprm_main_section_typo',
			'label'    => __( 'Typography', 'jellopoint-restaurant-menu' ),
			'selector' => '{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title',
		] );

		$this->add_control( 'jprm_main_section_color', [
			'label'     => __( 'Text Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title' => 'color: {{VALUE}};',
			],
		] );
		$this->add_responsive_control(
			'jprm_main_section_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_main_section_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'jprm_main_section_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_control( 'jprm_main_section_background', [
			'label'     => __( 'Background Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title' => 'background-color: {{VALUE}};',
			],
		] );
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'border-main',
				'selector' => '{{WRAPPER}} .jp-menu__section--level-0 .jp-section__title',
			]
		);

		$this->end_controls_section();

		/* ===== Subsection Heading (Style Tab) ===== */
		$this->start_controls_section( 'jprm_section_style_sub_section', [
			'label' => __( 'Subsection Heading', 'jellopoint-restaurant-menu' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		// FIX: descendant selector for all subsection levels
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'jprm_sub_section_typo',
			'label'    => __( 'Typography', 'jellopoint-restaurant-menu' ),
			'selector' => '{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title, {{WRAPPER}} .jp-menu__section--level-2 .jp-section__title, {{WRAPPER}} .jp-menu__section--level-3 .jp-section__title',
		] );

		$this->add_control( 'jprm_sub_section_color', [
			'label'     => __( 'Text Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title' => 'color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__section--level-2 .jp-section__title' => 'color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__section--level-3 .jp-section__title' => 'color: {{VALUE}};',
			],
		] );

		$this->add_responsive_control(
			'jprm_sub_section_align',
			[
				'label'   => __( 'Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title' => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__section--level-2 .jp-section__title' => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__section--level-3 .jp-section__title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_sub_section_margin',
			[
				'label'      => __( 'Margin', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu__section--level-2 .jp-section__title' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu__section--level-3 .jp-section__title' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_responsive_control(
			'jprm_sub_section_padding',
			[
				'label'      => __( 'Padding', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu__section--level-2 .jp-section__title' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-menu__section--level-3 .jp-section__title' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
		$this->add_control( 'jprm_sub_section_background', [
			'label'     => __( 'Background Color', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__section--level-2 .jp-section__title' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__section--level-3 .jp-section__title' => 'background-color: {{VALUE}};',
			],
		] );
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'border-sub',
				'selector' => '{{WRAPPER}} .jp-menu__section--level-1 .jp-section__title, {{WRAPPER}} .jp-menu__section--level-2 .jp-section__title, {{WRAPPER}} .jp-menu__section--level-3 .jp-section__title',
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

		/* Item Title */
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__title, ' .
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title',
			]
		);

		$this->add_control(
			'jprm_item_title_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'         => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'jprm_item_title_align',
			[
				'label'   => __( 'Title & Description Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .jp-menu__item .jp-menu__titlewrap'         => 'justify-content: {{VALUE}}; text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'             => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'              => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__titlewrap' => 'justify-content: {{VALUE}}; text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title'     => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc'      => 'text-align: {{VALUE}};',
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'         =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__title'         =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__title' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		/* Item Description */
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'        => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc' => 'color: {{VALUE}};',
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
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc' => 'text-align: {{VALUE}};',
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc' =>
						'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .jp-menu__item .jp-menu__desc'         =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .jp-matrix__cell--item .jp-menu__desc' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

		$this->add_control(
			'jprm_prices_heading',
			[
				'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		// Common price typography (inline + inline-below + matrix)
		$this->add_control(
			'jprm_prices_common_heading',
			[
				'label'     => __( 'Common', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_price_typography',
				'selector' => '{{WRAPPER}} .jp-price, {{WRAPPER}} .jp-matrix__cell--value',
			]
		);

		$this->add_control(
			'jprm_price_color',
			[
				'label'     => __( 'Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-price'               => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-matrix__cell--value' => 'color: {{VALUE}};',
				],
			]
		);

		// Vertical gap between multiple price rows
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
					// Inline & legacy pricegroup
					'{{WRAPPER}} .jp-menu__pricegroup .jp-price-row + .jp-price-row' =>
						'margin-top: {{SIZE}}{{UNIT}};',
					// NEW inline container
					'{{WRAPPER}} .jp-right-pricegroup .jp-price-row + .jp-price-row' =>
						'margin-top: {{SIZE}}{{UNIT}};',
					// Matrix: multiple .jp-price inside the same value cell
					'{{WRAPPER}} .jp-matrix__cell--value .jp-price + .jp-price' =>
						'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		/* --- Inline --- */
		$this->add_control(
			'jprm_prices_inline_heading',
			[
				'label'     => __( 'Inline', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		// FIX: match new inline structure (.jp-layout-inline .jp-right-pricegroup)
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
				'selectors_dictionary' => [
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				],
				'selectors' => [
					'{{WRAPPER}} .jp-layout-inline .jp-right-pricegroup' =>
						'align-items: {{VALUE}};',
				],
			]
		);

		/* --- Inline-Below --- */
		$this->add_control(
			'jprm_prices_inline_below_heading',
			[
				'label'     => __( 'Inline-Below', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'jprm_price_align_inline_below',
			[
				'label'   => __( 'Price Column Alignment', 'jellopoint-restaurant-menu' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'start'  => [ 'title' => __( 'Left', 'jellopoint-restaurant-menu' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => __( 'Center', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-text-align-center' ],
					'end'    => [ 'title' => __( 'Right', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-text-align-right' ],
				],
				'selectors_dictionary' => [
					'start'  => 'flex-start',
					'center' => 'center',
					'end'    => 'flex-end',
				],
				'selectors' => [
					'{{WRAPPER}} .jp-inline-below .jp-menu__pricegroup--below .jp-inline-below__line' =>
						'justify-content: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__pricegroup--below .jp-inline-below__line' =>
						'justify-content: {{VALUE}};',
				],
			]
		);

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

		/* --- Matrix --- */
		$this->add_control(
			'jprm_prices_matrix_heading',
			[
				'label'     => __( 'Matrix', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

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
					'{{WRAPPER}} .jp-matrix__row .jp-matrix__cell--value' => 'text-align: {{VALUE}};',
				],
			]
		);

		/* Labels */
		$this->add_control(
			'jprm_labels_heading',
			[
				'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'jprm_label_typography',
				'selector' =>
					'{{WRAPPER}} .jp-menu__label, ' .
					'{{WRAPPER}} .jp-matrix .jp-menu__label',
			]
		);

		$this->add_control(
			'jprm_label_text_color',
			[
				'label'     => __( 'Text & Icon Color', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-menu__label' => 'color: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__label .jp-label__icon--mask' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .jp-menu__label svg, {{WRAPPER}} .jp-menu__label .jp-label__svg' =>
						'fill: {{VALUE}} !important; stroke: {{VALUE}} !important;',
					'{{WRAPPER}} .jp-menu__label .jp-menu__icon' =>
						'fill: {{VALUE}} !important; stroke: {{VALUE}} !important; background-color: {{VALUE}} !important;',
				],
			]
		);

		// Labels → Icon Size — apply to IMG, SVG and mask span
		$this->add_responsive_control(
			'jprm_label_icon_size',
			[
				'label'      => __( 'Icon Size', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min'  => 12,
						'max'  => 64,
						'step' => 1,
					],
				],
				'selectors'  => [
					// Plain <img class="jp-menu__icon">
					'{{WRAPPER}} .jp-menu__label img.jp-menu__icon' =>
						'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; max-width: none !important;',

					// Inline SVG icons inside labels
					'{{WRAPPER}} .jp-menu__label svg' =>
						'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',

					// Masked icon span from jprm_colorize_icon()
					'{{WRAPPER}} .jp-menu__label .jp-label__icon' =>
						'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; flex: 0 0 auto;',
				],
			]
		);

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

		$this->add_responsive_control(
			'jprm_label_chip_padding',
			[
				'label'      => __( 'Chip Padding (Inline-Below)', 'jellopoint-restaurant-menu' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' =>
						'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' =>
						'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'jprm_label_chip_bg',
			[
				'label'     => __( 'Chip Background (Inline-Below)', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' =>
						'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' =>
						'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
				],
			]
		);

		$this->add_control(
			'jprm_label_chip_border_color',
			[
				'label'     => __( 'Chip Border Color (Inline-Below)', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .jp-inline-below .jp-chip .jp-menu__label' =>
						'border-color: {{VALUE}};',
				],
			]
		);

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
					'{{WRAPPER}} .jp-pricegroup .jp-menu__row' => 'gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .jp-inline-below .jp-chipline' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		/* Leader controls (CSS vars only) */
		$this->add_control( 'inline_leader_hr', [
			'type'      => \Elementor\Controls_Manager::DIVIDER,
			'condition' => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->add_control( 'inline_leader_style', [
			'label'     => __( 'Leader Style', 'jprm' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'dotted',
			'options'   => [
				'dotted' => __( 'Dotted', 'jprm' ),
				'dashed' => __( 'Dashed', 'jprm' ),
				'solid'  => __( 'Solid',  'jprm' ),
			],
			'condition' => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->add_control( 'inline_leader_color', [
			'label'     => __( 'Leader Color', 'jprm' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-layout-inline, {{WRAPPER}} .jp-inline' =>
					'--jprm-leader-color: {{VALUE}};',
			],
			'condition' => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->add_responsive_control( 'inline_leader_thickness', [
			'label'      => __( 'Leader Thickness', 'jprm' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
			'default'    => [ 'size' => 2, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-layout-inline, {{WRAPPER}} .jp-inline' =>
					'--jprm-leader-weight: {{SIZE}}{{UNIT}};',
			],
			'condition'  => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->add_responsive_control( 'inline_leader_gap', [
			'label'      => __( 'Leader Gap', 'jprm' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
			'default'    => [ 'size' => 2, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-layout-inline, {{WRAPPER}} .jp-inline' =>
					'--jprm-leader-gap: {{SIZE}}{{UNIT}};',
			],
			'condition'  => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->add_responsive_control( 'inline_leader_offset', [
			'label'      => __( 'Leader Vertical Offset', 'jprm' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => -6, 'max' => 6, 'step' => 1 ] ],
			'default'    => [ 'size' => 0, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-layout-inline, {{WRAPPER}} .jp-inline' =>
					'--jprm-leader-offset: {{SIZE}}{{UNIT}};',
			],
			'condition'  => [
				'labels_layout'        => 'inline',
				'inline_leader_enable' => 'yes',
			],
		] );

		$this->end_controls_section();

		/* ===== Badges (Style) ===== */
		$this->start_controls_section('jprm_style_badges', [
			'label' => __('Badges','jellopoint-restaurant-menu'),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		]);

		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'badges_typo',
			'global'   => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ],
			'selector' => '{{WRAPPER}} .jp-menu__badges, {{WRAPPER}} .jp-menu__badges .jp-badge, {{WRAPPER}} .jp-menu__badges .jp-badge__label',
		]);

		$this->add_control('badges_color', [
			'label'   => __('Text Color','jellopoint-restaurant-menu'),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'global'  => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ],
			'selectors' => [
				'{{WRAPPER}} .jp-menu__badges'                  => 'color: {{VALUE}};',
				'{{WRAPPER}} .jp-menu__badges .jp-badge__label' => 'color: {{VALUE}};',
			],
		]);

		$this->add_responsive_control('badges_icon_size_style', [
			'label'      => __('Icon Size','jellopoint-restaurant-menu'),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => ['px','em','rem'],
			'range'      => [ 'px' => [ 'min' => 8, 'max' => 64 ] ],
			'default'    => [ 'size' => 18, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-menu__badges .jp-badge__icon' =>
					'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; flex: 0 0 auto;',
				'{{WRAPPER}} .jp-menu__badges svg, {{WRAPPER}} .jp-menu__badges .jp-badge svg' =>
					'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
			],
		]);

		$this->add_responsive_control('badges_title_gap', [
			'label'      => __('Gap: Title ↔ Badges','jellopoint-restaurant-menu'),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => ['px','em','rem'],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 48 ] ],
			'default'    => [ 'size' => 6, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-menu__titlewrap' => 'gap: {{SIZE}}{{UNIT}};',
			],
		]);

		$this->add_responsive_control('badges_gap_style', [
			'label'      => __('Gap: Between badges','jellopoint-restaurant-menu'),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => ['px','em','rem'],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 24 ] ],
			'default'    => [ 'size' => 6, 'unit' => 'px' ],
			'selectors'  => [
				'{{WRAPPER}} .jp-menu__badges' => 'gap: {{SIZE}}{{UNIT}};',
			],
		]);

		$this->add_responsive_control('badges_padding', [
			'label'      => __('Badge Padding','jellopoint-restaurant-menu'),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => ['px','em','rem'],
			'default'    => [
				'top' => '2', 'right' => '6', 'bottom' => '2', 'left' => '6', 'unit' => 'px', 'isLinked' => false,
			],
			'selectors'  => [
				'{{WRAPPER}} .jp-menu__badges .jp-badge' =>
					'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]);

		$this->add_control('badges_bg_color', [
			'label'   => __('Badge Background','jellopoint-restaurant-menu'),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .jp-menu__badges .jp-badge' => 'background-color: {{VALUE}};',
			],
		]);

		$this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
			'name'     => 'badges_border',
			'selector' => '{{WRAPPER}} .jp-menu__badges .jp-badge',
		]);

		$this->add_responsive_control('badges_radius', [
			'label'      => __('Badge Radius','jellopoint-restaurant-menu'),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => ['px','%','em','rem'],
			'default'    => [ 'top'=>'999', 'right'=>'999', 'bottom'=>'999', 'left'=>'999', 'unit'=>'px', 'isLinked'=>true ],
			'selectors'  => [
				'{{WRAPPER}} .jp-menu__badges .jp-badge' =>
					'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]);

		$this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
			'name'     => 'badges_shadow',
			'selector' => '{{WRAPPER}} .jp-menu__badges .jp-badge',
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
		$this->add_responsive_control('infob_alignment',[
			'label'=>__('Alignment','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::CHOOSE,
			'options'=>[
				'left'=>['title'=>__('Left','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-left'],
				'center'=>['title'=>__('Center','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-center'],
				'right'=>['title'=>__('Right','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-right'],
			],
			'default'=>'left',
			'selectors'=>['{{WRAPPER}} .jprm-infoblock'=>'text-align: {{VALUE}};'],
		]);
		$this->add_responsive_control('infob_image_size',[
			'label'=>__('Image Size','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px','%'],
			'range'=>[
				'px'=>['min'=>16,'max'=>500],
				'%'=>['min'=>5,'max'=>100],
			],
			'default'=>['size'=>80],
			'selectors'=>['{{WRAPPER}} .jprm-infoblock__image img'=>'width: {{SIZE}}{{UNIT}}; height:auto;'],
		]);
		$this->end_controls_section();

		/* ===== Matrix ===== */
		$this->start_controls_section('jprm_style_matrix',[
			'label'=>__('Matrix','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);

		// FIX: first column cell is .jp-matrix__cell--item, not --title
		$this->add_control('matrix_title_min_width',[
			'label'=>__('Title column min width','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px','rem'],
			'range'=>[
				'px'=>['min'=>80,'max'=>600,'step'=>1],
				'rem'=>['min'=>6,'max'=>40,'step'=>0.1],
			],
			'default'=>['size'=>12,'unit'=>'rem'],
			'selectors'=>['{{WRAPPER}} .jp-matrix__cell--item'=>'min-width: {{SIZE}}{{UNIT}};'],
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
