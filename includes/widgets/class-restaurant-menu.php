<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * - Left pane structure:
 *   Data Source → Sections and Menus → Prices and Labels → Badges → Layout
 * - Rendering of price+labels is delegated to includes/render/partials/price-block.php
 * - Items WITHOUT price config are skipped (same behavior as before)
 */
class Restaurant_Menu extends Widget_Base {

	public function get_name() {
		return 'jprm-restaurant-menu';
	}

	public function get_title() {
		return __( 'JelloPoint – Restaurant Menu', 'jellopoint-restaurant-menu' );
	}

	public function get_icon() {
		return 'eicon-table-of-contents';
	}

	public function get_categories() {
		return [ 'jellopoint-widgets' ];
	}

	/* =========================
	 * Controls
	 * ========================= */
	protected function get_terms_options( string $taxonomy ) : array {
		$out = [];
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( is_object( $t ) && isset( $t->term_id, $t->name ) ) {
					$out[ (string) $t->term_id ] = $t->name; // keys are IDs (strings)
				}
			}
		}
		return $out;
	}

	protected function register_controls() {
		$menu_options    = $this->get_terms_options( 'jprm_menu' );
		$section_options = $this->get_terms_options( 'jprm_section' );

		/* --- Data Source -------------------------------------------------------- */
		$this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

		$this->add_control( 'data_mode', [
			'label'   => __( 'Source Mode', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::CHOOSE,
			'toggle'  => true,
			'default' => 'dynamic',
			'options' => [
				'dynamic' => [ 'title' => __( 'Dynamic', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-database' ],
				'static'  => [ 'title' => __( 'Static', 'jellopoint-restaurant-menu' ),  'icon' => 'eicon-editor-list-ul' ],
			],
		] );

		$this->add_control( 'show_all_when_empty', [
			'label'        => __( 'Fallback to all items when no Menu/Section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'menu_term_id', [
			'label'     => __( 'Menu (jprm_menu)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $menu_options,
			'default'   => '',
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'section_term_id', [
			'label'     => __( 'Section (jprm_section)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$rep = new Repeater();
		$rep->add_control( 'item_id', [
			'label'       => __( 'Menu Item ID', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'label_block' => true,
		] );
		$rep->add_control( 'item_title', [
			'label'       => __( 'Optional title (fallback)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'label_block' => true,
		] );

		$this->add_control( 'static_items', [
			'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => [],
			'title_field' => '{{{ item_title }}}',
			'condition'   => [ 'data_mode' => 'static' ],
		] );

		$this->end_controls_section();

		/* --- Sections and Menus (SHOW/HIDE toggles only) ------------------------ */
		$this->start_controls_section(
			'jprm_section_sections_menus',
			[ 'label' => __( 'Sections and Menus', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'show_section_name', [
			'label'        => __( 'Show section name', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'show_section_description', [
			'label'        => __( 'Show section description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->end_controls_section();

		/* --- Prices and Labels -------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'heading_prices', [
			'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		// --- Prices: currency options
		$this->add_control( 'show_currency', [
			'label'        => __( 'Show currency', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'currency_symbol', [
			'label'       => __( 'Currency symbol', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '€',
			'default'     => '€',
		] );
		$this->add_control( 'currency_position', [
			'label'   => __( 'Currency position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'before',
			'options' => [
				'before' => __( 'Before amount', 'jellopoint-restaurant-menu' ),
				'after'  => __( 'After amount', 'jellopoint-restaurant-menu' ),
			],
		] );
		$this->add_control( 'currency_spacing', [
			'label'   => __( 'Currency spacing', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'thin',
			'options' => [
				'none'   => __( 'No space', 'jellopoint-restaurant-menu' ),
				'thin'   => __( 'Thin space', 'jellopoint-restaurant-menu' ),
				'normal' => __( 'Non-breaking space', 'jellopoint-restaurant-menu' ),
			],
		] );

		$this->add_control( 'heading_labels', [
			'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );
		$this->add_control( 'label_presentation', [
			'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'chip',
			'options' => [
				'chip'   => __( 'Chip (rounded pill)', 'jellopoint-restaurant-menu' ),
				'plain'  => __( 'Plain text', 'jellopoint-restaurant-menu' ),
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

		/* --- Badges (empty for now) -------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_badges',
			[ 'label' => __( 'Badges', 'jellopoint-restaurant-menu' ) ]
		);
		$this->end_controls_section();

		/* --- Layout (basic for now) -------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_layout',
			[ 'label' => __( 'Layout', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'gap_between_items', [
			'label'   => __( 'Gap between items', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
			'default' => [ 'size' => 9 ],
			'selectors' => [
				'{{WRAPPER}} .jp-menu__item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
			],
		] );
		$this->end_controls_section();
	}

	/* =========================
	 * Render
	 * ========================= */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Resolve source items
		$items = [];
		if ( ( $settings['data_mode'] ?? 'dynamic' ) === 'static' ) {
			$rep = is_array( $settings['static_items'] ?? null ) ? $settings['static_items'] : [];
			foreach ( $rep as $row ) {
				$id = (int) ( $row['item_id'] ?? 0 );
				if ( $id > 0 ) {
					$post = get_post( $id );
					if ( $post && $post->post_type === 'jprm_menu_item' ) {
						$items[] = $post;
					}
				}
			}
		} else {
			$menu_id    = (int) ( $settings['menu_term_id'] ?? 0 );
			$section_id = (int) ( $settings['section_term_id'] ?? 0 );
			$args = [
				'post_type'      => 'jprm_menu_item',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			];

			if ( $menu_id || $section_id ) {
				$tax_query = [ 'relation' => 'AND' ];
				if ( $menu_id ) {
					$tax_query[] = [ 'taxonomy' => 'jprm_menu', 'terms' => [ $menu_id ], 'field' => 'term_id' ];
				}
				if ( $section_id ) {
					$tax_query[] = [ 'taxonomy' => 'jprm_section', 'terms' => [ $section_id ], 'field' => 'term_id' ];
				}
				$args['tax_query'] = $tax_query;
			} elseif ( ( $settings['show_all_when_empty'] ?? 'yes' ) !== 'yes' ) {
				echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
				return;
			}

			$q = new \WP_Query( $args );
			if ( $q->have_posts() ) {
				$items = $q->posts;
			}
		}

		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		// Optional: preload label map once per render for perf (partial can also build it)
		$label_map = function_exists( 'jprm_build_label_map' ) ? jprm_build_label_map() : null;

		echo '<ul class="jp-menu">';
		foreach ( $items as $post ) {
			$post_id = (int) $post->ID;
			$title   = get_the_title( $post_id );
			$desc    = get_post_meta( $post_id, 'jprm_desc', true );

			// Keep behavior: skip items without price config entirely
			$cfg = function_exists( 'jprm_read_price_config' ) ? jprm_read_price_config( $post_id ) : [];
			if ( empty( $cfg ) ) { continue; }

			echo '<li class="jp-menu__item"><div class="jp-menu__inner">';

			// Left: title + description
			echo '  <div class="jp-menu__content">';
			if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '  </div>';

			// Right: prices+labels via partial (canonical)
			if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
				$label_presentation = (string) ( $settings['label_presentation'] ?? 'chip' );
				$label_position     = (string) ( $settings['label_position'] ?? 'right' );

				echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, [
					'show'     => ( isset( $settings['show_currency'] ) && $settings['show_currency'] === 'yes' ),
					'symbol'   => (string) ( $settings['currency_symbol'] ?? '€' ),
					'position' => (string) ( $settings['currency_position'] ?? 'before' ),
					'spacing'  => (string) ( $settings['currency_spacing'] ?? 'thin' ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				// Should not happen if partial is present; emit empty container to preserve layout.
				echo '<div class="jp-menu__pricegroup"></div>';
			}

			echo '</div></li>';
		}
		echo '</ul>';
	}

	/* =========================
	 * Data helpers
	 * ========================= */
	protected function get_post_field( int $post_id, string $key, $default = '' ) {
		$v = get_post_meta( $post_id, $key, true );
		return is_string( $v ) ? $v : $default;
	}

	public function get_style_depends() {
		return [ 'jprm-menu-css' ];
	}

	public function get_script_depends() {
		return [];
	}
}
