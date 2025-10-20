<?php
namespace JelloPoint\RestaurantMenu\Widgets\Traits;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Restaurant_Menu_Style {
	protected function register_style_controls() : void {

		/* ===== Menu Title & Description ===== */
		$this->start_controls_section('jprm_style_menu_meta',[
			'label'=>__('Menu Title & Description','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_control( 'jprm_curr_heading', [
			'label'     => __( 'Currency', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'menu_title_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY],
			'selector'=>'{{WRAPPER}} .jp-menu__meta-title',
		]);
		$this->add_control('menu_title_color',[
			'label'=>__('Title Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY],
			'selectors'=>['{{WRAPPER}} .jp-menu__meta-title'=>'color: {{VALUE}};'],
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'menu_desc_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jp-menu__meta-desc',
		]);
		$this->add_control('menu_desc_color',[
			'label'=>__('Description Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>['{{WRAPPER}} .jp-menu__meta-desc'=>'color: {{VALUE}};'],
		]);
		$this->end_controls_section();


		/* ===== Section Title & Description ===== */
		$this->start_controls_section('jprm_style_sections',[
			'label'=>__('Section Title & Description','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'section_title_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_SECONDARY],
			'selector'=>'{{WRAPPER}} .jp-section__title',
		]);
		$this->add_control('section_title_color',[
			'label'=>__('Title Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_SECONDARY],
			'selectors'=>['{{WRAPPER}} .jp-section__title'=>'color: {{VALUE}};'],
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'section_desc_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jp-section__desc',
		]);
		$this->add_control('section_desc_color',[
			'label'=>__('Description Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>['{{WRAPPER}} .jp-section__desc'=>'color: {{VALUE}};'],
		]);
		$this->end_controls_section();


		/* ===== Item Title & Description ===== */
		$this->start_controls_section('jprm_style_items',[
			'label'=>__('Item Title & Description','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		/* Inline + Matrix titles */
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'item_title_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY],
			'selector'=>'{{WRAPPER}} .jp-menu__title, {{WRAPPER}} .jp-matrix__title',
		]);
		$this->add_control('item_title_color',[
			'label'=>__('Title Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__title'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-matrix__title'=>'color: {{VALUE}};',
			],
		]);
		/* Inline + Matrix descriptions */
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'item_desc_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jp-menu__desc, {{WRAPPER}} .jp-matrix__desc',
		]);
		$this->add_control('item_desc_color',[
			'label'=>__('Description Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__desc'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-matrix__desc'=>'color: {{VALUE}};',
			],
		]);
		$this->end_controls_section();


		/* ===== Prices ===== */
		$this->start_controls_section('jprm_style_prices',[
			'label'=>__('Prices','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'price_value_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT],
			'selector'=>'{{WRAPPER}} .jp-menu__value, {{WRAPPER}} .jp-matrix__cell--value',
		]);
		$this->add_control('price_value_color',[
			'label'=>__('Price Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT],
			'selectors'=>[
				'{{WRAPPER}} .jp-menu__value'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-matrix__cell--value'=>'color: {{VALUE}};',
			],
		]);
		$this->add_responsive_control('price_align',[
			'label'=>__('Inline Price Align','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::CHOOSE,
			'options'=>[
				'left'=>['title'=>__('Left','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-left'],
				'center'=>['title'=>__('Center','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-center'],
				'right'=>['title'=>__('Right','jellopoint-restaurant-menu'),'icon'=>'eicon-text-align-right'],
			],
			'default'=>'left',
			'selectors'=>['{{WRAPPER}} .jp-menu__pricegroup'=>'text-align: {{VALUE}};'],
		]);
		$this->end_controls_section();


		/* ===== Labels (inline + matrix) ===== */
		$this->start_controls_section('jprm_style_labels',[
			'label'=>__('Labels','jellopoint-restaurant-menu'),
			'tab'=>\Elementor\Controls_Manager::TAB_STYLE,
		]);
		/* Text (inline label text + matrix header text) */
		$this->add_group_control(\Elementor\Group_Control_Typography::get_type(),[
			'name'=>'label_text_typo',
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT],
			'selector'=>'{{WRAPPER}} .jp-col-label, {{WRAPPER}} .jp-lhdr-text',
		]);
		$this->add_control('label_text_color',[
			'label'=>__('Text Color','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::COLOR,
			'global'=>['default'=>\Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT],
			'selectors'=>[
				'{{WRAPPER}} .jp-col-label'=>'color: {{VALUE}};',
				'{{WRAPPER}} .jp-lhdr-text'=>'color: {{VALUE}};',
			],
		]);
		/* Icons:
		   - Inline: icons are inside the label cell. Prefer class .jp-menu__icon if present, else any img/svg in that cell.
		   - Matrix header: label icon is inside .jp-lhdr-ico. */
		$this->add_responsive_control('label_icon_size',[
			'label'=>__('Icon Size','jellopoint-restaurant-menu'),
			'type'=>\Elementor\Controls_Manager::SLIDER,
			'size_units'=>['px'],
			'range'=>['px'=>['min'=>8,'max'=>64]],
			'default'=>['size'=>24],
			'selectors'=>[
				'{{WRAPPER}} .jp-col-label .jp-menu__icon'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .jp-col-label img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .jp-col-label svg'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .jp-lhdr-ico img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .jp-lhdr-ico svg'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			],
		]);
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
