<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * - Panel layout organized into:
 *   • Data Source
 *   • Sections and Menus (with two display toggles)
 *   • Prices and Labels (Labels controls moved here)
 * - Rendering logic unchanged (uses render()).
 */
class Restaurant_Menu extends Widget_Base {

	public function get_name() {
		return 'jprm_restaurant_menu';
	}

	public function get_title() {
		return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	public function get_categories() {
		return [ 'jellopoint-widgets' ];
	}

	public function get_keywords() {
		return [ 'restaurant', 'menu', 'food', 'jellopoint' ];
	}

	/* ---------------------------
	 * Helpers for editor controls
	 * --------------------------- */

	/**
	 * Returns taxonomy terms as id => label.
	 * For jprm_section, returns hierarchical labels with em-dash indentation.
	 */
	private function get_terms_options( string $taxonomy ) : array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		if ( $taxonomy === 'jprm_section' ) {
			// Build hierarchical labels
			$by_parent = [];
			foreach ( $terms as $t ) {
				$by_parent[ (int) $t->parent ][] = $t;
			}
			$roots = $by_parent[0] ?? [];
			usort( $roots, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			$out = [];

			$make_label = static function( \WP_Term $term ) : string {
				$depth  = count( get_ancestors( (int) $term->term_id, 'jprm_section', 'taxonomy' ) );
				$indent = $depth > 0 ? str_repeat( '— ', $depth ) : '';
				return $indent . $term->name;
			};

			$walk = static function( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) {
				if ( empty( $by_parent[ $parent_id ] ) ) return;
				$children = $by_parent[ $parent_id ];
				usort( $children, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
				foreach ( $children as $child ) {
					$out[ (string) $child->term_id ] = $make_label( $child );
					$walk( (int) $child->term_id );
				}
			};

			foreach ( $roots as $root ) {
				$out[ (string) $root->term_id ] = $make_label( $root );
				$walk( (int) $root->term_id );
			}
			return $out;
		}

		$out = [];
		foreach ( $terms as $t ) {
			if ( is_object( $t ) && isset( $t->term_id, $t->name ) ) {
				$out[ (string) $t->term_id ] = $t->name;
			}
		}
		return $out;
	}

	public function register_controls() {
		$menu_options    = $this->get_terms_options( 'jprm_menu' );
		$section_options = $this->get_terms_options( 'jprm_section' );

		// --- Data Source ---------------------------------------------------------
		$this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

		// Source Mode (UI only; rendering keeps legacy behavior when items exist)
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

		$this->add_control( 'allow_all_when_empty', [
			'label'        => __( 'Fallback to all items when no menu/section selected', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		// Static mode controls (shown when data_mode == static)
		$this->add_control( 'static_notice', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( '<strong>Static</strong>: Manually define the items list below.', 'jellopoint-restaurant-menu' ),
			'content_classes' => 'elementor-descriptor',
			'condition'       => [ 'data_mode' => 'static' ],
		] );

		$rep = new Repeater();
		$rep->add_control( 'item_title',        [ 'label' => __( 'Title', 'jellopoint-restaurant-menu' ),       'type' => Controls_Manager::TEXT ] );
		$rep->add_control( 'item_description',  [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'item_price',        [ 'label' => __( 'Price', 'jellopoint-restaurant-menu' ),       'type' => Controls_Manager::TEXT ] );

		$this->add_control( 'items', [
			'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ item_title }}}',
			'condition'   => [ 'data_mode' => 'static' ],
		] );

		$this->end_controls_section();

		// --- Sections and Menus --------------------------------------------------
		$this->start_controls_section(
			'jprm_section_sections_menus',
			[ 'label' => __( 'Sections and Menus', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'jprm_show_section_name', [
			'label'        => __( 'Show section name', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'jprm_show_section_description', [
			'label'        => __( 'Show section description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'menus', [
			'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Choose Menus to include.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $menu_options,
			'multiple'    => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Choose Sections to include.', 'jellopoint-restaurant-menu' ),
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
				'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
				'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
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

		// --- Prices and Labels ----------------------------------------------------
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'jprm_heading_prices', [
			'type'      => Controls_Manager::HEADING,
			'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
			'separator' => 'before',
		] );

		// (Add/move price-related controls here later if needed)

		$this->add_control( 'jprm_heading_labels', [
			'type'      => Controls_Manager::HEADING,
			'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
			'separator' => 'before',
		] );

		$this->add_control( 'label_presentation', [
			'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'icon_text',
			'options' => [
				'text'      => __( 'Text only', 'jellopoint-restaurant-menu' ),
				'icon'      => __( 'Icon only', 'jellopoint-restaurant-menu' ),
				'icon_text' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
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
	}

	/* ---------------------------
	 * Rendering
	 * --------------------------- */

	/**
	 * Convert term ids, slugs or names to slugs for a given taxonomy.
	 */
	private function normalize_to_slugs( $values, string $taxonomy ) : array {
		if ( empty( $values ) ) return [];
		$out = [];
		foreach ( (array) $values as $v ) {
			if ( is_numeric( $v ) ) {
				$term = get_term( (int) $v, $taxonomy );
			} else {
				$term = get_term_by( 'slug', (string) $v, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					$term = get_term_by( 'name', (string) $v, $taxonomy );
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = $term->slug;
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Render the widget on the frontend.
	 * (No changes to behavior; only panel layout reorganized.)
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$mode = (string) ( $s['data_mode'] ?? 'dynamic' );
		if ( $mode === 'static' ) {
			$items = (array) ( $s['items'] ?? [] );
			if ( empty( $items ) ) {
				echo '<div class="jp-menu--empty">' . esc_html__( 'No items defined.', 'jellopoint-restaurant-menu' ) . '</div>';
				return;
			}
			echo '<div class="jp-menu jp-menu--static">';
			foreach ( $items as $row ) {
				$title = isset( $row['item_title'] ) ? esc_html( $row['item_title'] ) : '';
				$desc  = isset( $row['item_description'] ) ? esc_html( $row['item_description'] ) : '';
				$price = isset( $row['item_price'] ) ? esc_html( $row['item_price'] ) : '';
				echo '<div class="jp-menu__item">';
				if ( $title !== '' ) {
					echo '<div class="jp-menu__title">' . $title . '</div>';
				}
				if ( $desc !== '' ) {
					echo '<div class="jp-menu__desc">' . $desc . '</div>';
				}
				if ( $price !== '' ) {
					echo '<div class="jp-menu__price">' . $price . '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
			return;
		}

		// Dynamic: strictly from jprm_menu_item
		if ( ! post_type_exists( 'jprm_menu_item' ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Menu items post type not found (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_presentation = (string) ( $s['label_presentation'] ?? 'icon_text' );
		$label_position     = (string) ( $s['label_position'] ?? 'right' );

		$menus    = $this->normalize_to_slugs( $s['menus']    ?? [], 'jprm_menu' );
		$sections = $this->normalize_to_slugs( $s['sections'] ?? [], 'jprm_section' );

		if ( empty( $menus ) && empty( $sections ) && ( (string) ( $s['allow_all_when_empty'] ?? 'no' ) === 'yes' ) ) {
			$all_menus    = get_terms( [ 'taxonomy' => 'jprm_menu', 'hide_empty' => true ] );
			$all_sections = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => true ] );

			if ( is_array( $all_menus ) )    { $menus    = array_map( fn( $t ) => $t->slug, $all_menus ); }
			if ( is_array( $all_sections ) ) { $sections = array_map( fn( $t ) => $t->slug, $all_sections ); }
		}

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => in_array( (string) ( $s['query_orderby'] ?? 'menu_order' ), [ 'menu_order', 'title', 'date' ], true ) ? (string) $s['query_orderby'] : 'menu_order',
			'order'          => in_array( (string) ( $s['query_order']   ?? 'ASC' ), [ 'ASC', 'DESC' ], true ) ? (string) $s['query_order'] : 'ASC',
		];
		if ( ! empty( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) {
			$args['posts_per_page'] = max( 1, (int) $s['query_limit'] );
		}

		$tax_query = [];
		if ( ! empty( $menus ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'slug',
				'terms'    => $menus,
			];
		}
		if ( ! empty( $sections ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'slug',
				'terms'    => $sections,
			];
		}
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = count( $tax_query ) > 1
				? array_merge( [ 'relation' => 'AND' ], $tax_query )
				: $tax_query;
		}

		$q = new \WP_Query( $args );
		if ( ! $q->have_posts() ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found for the selected Menus/Sections.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		echo '<div class="jp-menu jp-menu--dynamic">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$title = get_the_title();
			$desc  = '';
			$price = '';

			echo '<div class="jp-menu__item">';
			if ( $title !== '' ) {
				echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
			}
			if ( $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}
			if ( $price !== '' ) {
				echo '<div class="jp-menu__price">' . esc_html( $price ) . '</div>';
			}
			echo '<div class="jp-menu__labels jp-menu__labels--pos-' . esc_attr( $label_position ) . ' jp-menu__labels--view-' . esc_attr( $label_presentation ) . '"></div>';
			echo '</div>';
		}
		echo '</div>';
		wp_reset_postdata();
	}
}
