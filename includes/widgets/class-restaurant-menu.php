<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

// Use your canonical price renderer (drives icons/labels + multi rows)
use JelloPoint\RestaurantMenu\Render\Price_Renderer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 *
 * Panel structure (as requested):
 *  - Data Source (unchanged; Menus/Sections + fallback + order/limit)
 *  - Sections and Menus (new; only display toggles)
 *  - Prices and Labels (new; small "Prices" heading + Labels controls moved here)
 *
 * Preview pane rendering:
 *  - Uses Price_Renderer::render_from_meta( $post_id, $opts ) so labels/icons & multi prices
 *    output EXACTLY as your existing pipeline expects.
 */
final class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-menu-card'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu', 'restaurant', 'prices', 'jellopoint', 'labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }

	/* =========================
	 * Controls
	 * ========================= */

	private function get_terms_options( string $taxonomy ) : array {
		if ( ! taxonomy_exists( $taxonomy ) ) return [];
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) return [];
		$out = [];
		// Plain labels (no hierarchy indentation changes)
		foreach ( $terms as $t ) { $out[ (string) $t->term_id ] = $t->name; }
		return $out;
	}

	public function register_controls() {
		$menu_options    = $this->get_terms_options( 'jprm_menu' );
		$section_options = $this->get_terms_options( 'jprm_section' );

		/* --- Data Source (leave content as-is; labels moved out) ---------------- */
		$this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

		$this->add_control( 'data_mode', [
			'label'   => __( 'Source Mode', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::CHOOSE,
			'toggle'  => true,
			'default' => 'dynamic',
			'options' => [
				'dynamic' => [ 'title' => __( 'Dynamic', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-database' ],
				'static'  => [ 'title' => __( 'Static',  'jellopoint-restaurant-menu' ), 'icon' => 'eicon-editor-list-ul' ],
			],
		] );

		$this->add_control( 'show_all_when_empty', [
			'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'No',  'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		$this->add_control( 'menus', [
			'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $menu_options,
			'multiple'    => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $section_options,
			'multiple'    => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'query_orderby', [
			'label'   => __( 'Order by', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'menu_order',
			'options' => [
				'menu_order' => __( 'Menu order', 'jellopoint-restaurant-menu' ),
				'title'      => __( 'Title',      'jellopoint-restaurant-menu' ),
				'date'       => __( 'Date',       'jellopoint-restaurant-menu' ),
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'query_order', [
			'label'     => __( 'Order', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'ASC',
			'options'   => [ 'ASC' => 'ASC', 'DESC' => 'DESC' ],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'query_limit', [
			'label'       => __( 'Items limit', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Leave empty for no limit.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'step'        => 1,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->end_controls_section();

		/* --- Sections and Menus (expandable; only two toggles) ------------------ */
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

		/* --- Prices and Labels (expandable; labels moved here) ------------------ */
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'heading_prices', [
			'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_control( 'heading_labels', [
			'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		// (MOVED here from the original Data Source group)
		$this->add_control( 'label_presentation', [
			'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'icon_text',
			'options' => [
				'text'      => __( 'Text only',    'jellopoint-restaurant-menu' ),
				'icon'      => __( 'Icon only',    'jellopoint-restaurant-menu' ),
				'icon_text' => __( 'Icon + Text',  'jellopoint-restaurant-menu' ),
			],
		] );

		$this->add_control( 'label_position', [
			'label'   => __( 'Label Position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'right',
			'options' => [
				'left'  => __( 'Left of price',  'jellopoint-restaurant-menu' ),
				'right' => __( 'Right of price', 'jellopoint-restaurant-menu' ),
			],
		] );

		$this->end_controls_section();

		/* --- Static items (only when Source Mode = static) ---------------------- */
		$this->start_controls_section(
			'section_static',
			[ 'label' => __( 'Static Items', 'jellopoint-restaurant-menu' ), 'condition' => [ 'data_mode' => 'static' ] ]
		);

		$rep = new Repeater();
		$rep->add_control( 'item_title',       [ 'label' => __( 'Title',       'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT ] );
		$rep->add_control( 'item_description', [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'item_price',       [ 'label' => __( 'Price',       'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXT ] );

		$this->add_control( 'items', [
			'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ item_title }}}',
		] );

		$this->end_controls_section();
	}

	/* =========================
	 * Rendering
	 * ========================= */

	private function normalize_to_ids( $values ) : array {
		$out = [];
		foreach ( (array) $values as $v ) {
			if ( $v === '' || $v === null ) continue;
			$out[] = (int) $v;
		}
		return array_values( array_unique( array_filter( $out, fn( $n ) => $n > 0 ) ) );
	}

	protected function render_static_list( array $items ) : void {
		echo '<ul class="jp-menu">';
		foreach ( $items as $it ) {
			$title = $it['item_title'] ?? '';
			$desc  = $it['item_description'] ?? '';
			$price = $it['item_price'] ?? '';
			echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
			echo '  <div class="jp-menu__content">';
			if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( $desc  !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '  </div>';
			echo '  <div class="jp-menu__pricegroup">';
			if ( $price !== '' ) {
				echo '    <div class="jp-menu__price">';
				echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
				echo '    </div>';
			}
			echo '  </div>';
			echo '</div></li>';
		}
		echo '</ul>';
	}

	public function render() {
		$s = $this->get_settings_for_display();

		$mode = (string) ( $s['data_mode'] ?? 'dynamic' );
		if ( $mode === 'static' ) {
			$items = (array) ( $s['items'] ?? [] );
			$this->render_static_list( $items );
			return;
		}

		$show_all   = ( isset( $s['show_all_when_empty'] ) && $s['show_all_when_empty'] === 'yes' );
		$menu_ids   = $this->normalize_to_ids( $s['menus']    ?? [] );
		$section_ids= $this->normalize_to_ids( $s['sections'] ?? [] );

		if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$orderby = in_array( $s['query_orderby'] ?? 'menu_order', [ 'menu_order','title','date' ], true ) ? (string) $s['query_orderby'] : 'menu_order';
		$order   = in_array( $s['query_order']   ?? 'ASC',        [ 'ASC','DESC' ], true )              ? (string) $s['query_order']   : 'ASC';
		$limit   = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => true,
		];

		$tax_query = [];
		if ( ! empty( $menu_ids ) )    $tax_query[] = [ 'taxonomy' => 'jprm_menu',    'field' => 'term_id', 'terms' => $menu_ids ];
		if ( ! empty( $section_ids ) ) $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'term_id', 'terms' => $section_ids ];
		if ( ! empty( $tax_query ) )   $args['tax_query'] = count( $tax_query ) > 1 ? array_merge( [ 'relation' => 'AND' ], $tax_query ) : $tax_query;

		$q = new \WP_Query( $args );
		if ( ! $q->have_posts() ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$presentation = (string) ( $s['label_presentation'] ?? 'icon_text' );
		$order_class  = ( (string) ( $s['label_position'] ?? 'right' ) === 'left' ) ? 'jp-order--label-left' : 'jp-order--label-right';

		$show_section_name        = ( $s['show_section_name'] ?? 'yes' ) === 'yes';
		$show_section_description = ( $s['show_section_description'] ?? 'yes' ) === 'yes';

		echo '<ul class="jp-menu">';

		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();
			$title   = get_the_title();
			$desc    = (string) get_post_meta( $post_id, 'jprm_desc', true );

			echo '<li class="jp-menu__item"><div class="jp-menu__inner">';

			echo '  <div class="jp-menu__content">';
			if ( $show_section_name && $title !== '' ) {
				echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			}
			if ( $show_section_description && $desc !== '' ) {
				echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}
			echo '  </div>';

			// === PRICE + LABELS, rendered by your canonical renderer ===
			$pg_html = '';
			if ( class_exists( Price_Renderer::class ) ) {
				$pg_html = Price_Renderer::render_from_meta( (int) $post_id, [
					'presentation' => $presentation,
					'order_class'  => $order_class,
				] );
			}
			if ( is_string( $pg_html ) && $pg_html !== '' ) {
				// Safe to echo; renderer already escapes/structures markup consistently
				echo $pg_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				// If no price config, output empty container to preserve layout
				echo '<div class="jp-menu__pricegroup"></div>';
			}

			echo '</div></li>';
		}

		echo '</ul>';
		wp_reset_postdata();
	}
}
