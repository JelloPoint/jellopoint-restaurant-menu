<?php
/**
 * Elementor Widget (fallback): JelloPoint Restaurant Menu
 * Safe widget that renders a supplied shortcode via do_shortcode().
 * If you already have your own widget, you can skip this file;
 * auto-discovery will load your existing widget class instead.
 */

namespace JelloPoint\RestaurantMenu\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Restaurant_Menu extends Widget_Base {

    public function get_name() {
        return 'jprm_restaurant_menu';
    }

    public function get_title() {
        return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' );
    }

    public function get_icon() {
        return 'eicon-menu-card';
    }

    public function get_categories() {
        return [ 'jellopoint-widgets' ];
    }

    public function get_keywords() {
        return [ 'menu', 'restaurant', 'price', 'jellopoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [ 'label' => __( 'Content', 'jellopoint-restaurant-menu' ) ]
        );

        $this->add_control(
            'shortcode',
            [
                'label'       => __( 'Shortcode', 'jellopoint-restaurant-menu' ),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => '[jprm_menu id="0"]',
                'placeholder' => '[jprm_menu id="123"]',
                'rows'        => 2,
            ]
        );

        $this->add_control(
            'menu_id',
            [
                'label'       => __( 'Menu Item ID (optional)', 'jellopoint-restaurant-menu' ),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'step'        => 1,
                'default'     => 0,
                'description' => __( 'If set (>0), the widget will render [jprm_menu id="..."]. Otherwise, the Shortcode field above is used as-is.', 'jellopoint-restaurant-menu' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $id = isset( $settings['menu_id'] ) ? absint( $settings['menu_id'] ) : 0;
        $shortcode = $id > 0 ? sprintf( '[jprm_menu id="%d"]', $id ) : ( isset( $settings['shortcode'] ) ? trim( (string) $settings['shortcode'] ) : '' );

        if ( $shortcode === '' ) {
            echo '<div class="jprm-empty">'. esc_html__( 'Please provide a shortcode or Menu Item ID.', 'jellopoint-restaurant-menu' ) .'</div>';
            return;
        }
        echo do_shortcode( $shortcode );
    }

    protected function content_template() {}
}
