<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Original working logic preserved.
 * Changes in this drop-in:
 *  - Keep Menus/Sections and "Show all items..." under Data Source.
 *  - Move Labels controls into a new "Prices and Labels" section with headings.
 *  - No rendering logic changed, no meta key guesses.
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

		// Keep the fallback toggle here.
		$this->add_control( 'show_all_when_empty', [
			'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		// Keep Menus/Sections here (under Data Source)
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

		// (Price-related controls can be added here later if needed)

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

		/* ---- Static items (shown only when Source Mode = static) ---- */
		$this->start_controls_section(
			'section_static',
			[
				'label'     => __( 'Static Items', 'jellopoint-restaurant-menu' ),
				'condition' => [ 'data_mode' => 'static' ],
			]
		);

		$rep = new Repeater();
		$rep->add_control( 'item_title',        [ 'label' => __( 'Title', 'jellopoint-restaurant-menu' ),       'type' => Controls_Manager::TEXT ] );
		$rep->add_control( 'item_description',  [ 'label' => __( 'Description', 'jellopoint-restaurant-menu' ), 'type' => Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'item_price',        [ 'label' => __( 'Price', 'jellopoint-restaurant-menu' ),       'type' => Controls_Manager::TEXT ] );

		$this->add_control( 'items', [
			'label'       => __( 'Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ item_title }}}',
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
	 * Query items according to selections.
	 * If neither menus nor sections are chosen and show_all_when_empty != 'yes',
	 * we bail out early in render().
	 */
	private function query_items( array $menu_slugs, array $section_slugs, string $orderby, string $order, int $limit ) : array {
		$tax_query = [ 'relation' => 'AND' ];

		if ( ! empty( $menu_slugs ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'slug',
				'terms'    => $menu_slugs,
			];
		}
		if ( ! empty( $section_slugs ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'slug',
				'terms'    => $section_slugs,
			];
		}

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => true,
		];

		// Only set tax_query if filters exist (keeps "show all" working).
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new \WP_Query( $args );
		return $q->have_posts() ? $q->posts : [];
	}

	/** Read price config: meta jprm_price (JSON). */
	protected function read_price_config( int $post_id ) : array {
		$json = (string) get_post_meta( $post_id, 'jprm_price', true );
		if ( $json === '' ) return [];
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : [];
	}

	/** Render one price block (basic; preserves legacy output hooks). */
	protected function render_price_block( array $price_cfg ) : string {
		if ( empty( $price_cfg ) ) return '';
		$amount = isset( $price_cfg['amount'] ) ? (string) $price_cfg['amount'] : '';
		$unit   = isset( $price_cfg['unit'] )   ? (string) $price_cfg['unit']   : '';
		$out = '<span class="jp-menu__price">';
		$out .= esc_html( $amount );
		if ( $unit !== '' ) $out .= ' <span class="jp-menu__unit">' . esc_html( $unit ) . '</span>';
		$out .= '</span>';
		return $out;
	}

	/** Build labels map from meta (non-destructive; existing storage untouched). */
	protected function build_label_map() : array {
		// Placeholder; storage specifics handled elsewhere in plugin.
		// Return an empty map by default; rendering will handle missing labels gracefully.
		return [];
	}

	/** Label HTML helper */
	protected function label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
		$label_text = (string) $label_text;
		$icon_html  = '';
		if ( ! $hide_icon && $icon_id > 0 ) {
			$img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
			if ( is_string($img) ) $icon_html = $img;
		}

		if ( $presentation === 'icon' )      return $icon_html;
		if ( $presentation === 'text' )      return esc_html( $label_text );
		if ( $presentation === 'icon_text' ) return $icon_html ? ($icon_html . ' ' . esc_html($label_text)) : esc_html($label_text);
		return esc_html( $label_text );
	}

	/**
	 * Render the widget on the frontend.
	 * (No changes to behavior; only the panel layout reorganized.)
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$mode = (string) ( $s['data_mode'] ?? 'dynamic' );

		// Static mode render
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

		// Dynamic mode render
		if ( ! post_type_exists( 'jprm_menu_item' ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Menu items post type not found (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$menus    = $this->normalize_to_slugs( $s['menus']    ?? [], 'jprm_menu' );
		$sections = $this->normalize_to_slugs( $s['sections'] ?? [], 'jprm_section' );

		// If nothing selected, optionally show all (when enabled).
		if ( empty( $menus ) && empty( $sections ) && ( $s['show_all_when_empty'] ?? 'no' ) !== 'yes' ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No menu/section selected.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$orderby = in_array( $s['query_orderby'] ?? 'menu_order', [ 'menu_order', 'title', 'date' ], true ) ? (string) $s['query_orderby'] : 'menu_order';
		$order   = in_array( $s['query_order']   ?? 'ASC', [ 'ASC', 'DESC' ], true ) ? (string) $s['query_order'] : 'ASC';
		$limit   = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;

		$items = $this->query_items( $menus, $sections, $orderby, $order, $limit );
		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_presentation = (string) ( $s['label_presentation'] ?? 'icon_text' );
		$label_position     = (string) ( $s['label_position'] ?? 'right' );

		$label_map    = $this->build_label_map();
		$presentation = $label_presentation;
		$position     = $label_position;

		echo '<ul class="jp-menu">';
		foreach ( $items as $post ) {
			$post_id = (int) $post->ID;

			$title = get_the_title( $post_id );
			$desc  = (string) get_post_meta( $post_id, 'jprm_desc', true );
			$price_cfg = $this->read_price_config( $post_id );

			echo '<li class="jp-menu__item">';
			if ( $title !== '' ) {
				echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
			}
			if ( $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}
			if ( ! empty( $price_cfg ) ) {
				echo $this->render_price_block( $price_cfg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Labels container with classes for presentation/position (rendered content handled elsewhere)
			echo '<div class="jp-menu__labels jp-menu__labels--pos-' . esc_attr( $position ) . ' jp-menu__labels--view-' . esc_attr( $presentation ) . '"></div>';

			echo '</li>';
		}
		echo '</ul>';
	}
}
