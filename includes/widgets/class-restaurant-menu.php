<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Same as your working original, with ONLY the "Auto-detect context" control/logic removed.
 * Also: tiny safety tweak in query_items() to omit empty tax_query.
 */
class Restaurant_Menu extends Widget_Base {

	public function get_name() {
		return 'jprm_restaurant_menu';
	}

	public function get_title() {
		return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' );
	}

	public function get_categories() {
		return [ 'jellopoint-widgets' ];
	}

	public function get_keywords() {
		return [ 'menu', 'restaurant', 'prices', 'jellopoint', 'labels' ];
	}

	public function get_style_depends() {
		// Registered in plugin bootstrap; enqueued for editor preview.
		return [ 'jprm-menu' ];
	}

	/** Build a select list of terms for a taxonomy (id => name) */
	protected function get_terms_options( string $taxonomy ) : array {
		$out = [];
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( is_object( $t ) && isset( $t->term_id, $t->name ) ) {
					$out[ (string) $t->term_id ] = $t->name;
				}
			}
		}
		return $out;
	}

	public function register_controls() {
		$menu_options    = $this->get_terms_options( 'jprm_menu' );
		$section_options = $this->get_terms_options( 'jprm_section' );

		$this->start_controls_section( 'section_source', [ 'label' => __( 'Data Source', 'jellopoint-restaurant-menu' ) ] );

		// (Auto-detect context control removed)

		$this->add_control( 'show_all_when_empty', [
			'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		$this->add_control( 'menus', [
			'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Choose Menus to include.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $menu_options,
			'multiple'    => true,
		] );

		$this->add_control( 'sections', [
			'label'       => __( 'Sections', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Choose Sections to include.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $section_options,
			'multiple'    => true,
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
		] );

		$this->add_control( 'query_order', [
			'label'   => __( 'Order', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'ASC',
			'options' => [ 'ASC' => 'ASC', 'DESC' => 'DESC' ],
		] );

		$this->add_control( 'query_limit', [
			'label'       => __( 'Items limit', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Leave empty for no limit.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'step'        => 1,
		] );

		$this->add_control( 'heading_labels', [
			'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
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

		/* ---- Static items (unchanged) ---- */
		$this->start_controls_section(
			'section_static',
			[
				'label'      => __( 'Static Items', 'jellopoint-restaurant-menu' ),
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

	public function render() {
		$s = $this->get_settings_for_display();

		// Legacy static behavior (if repeater items exist, show them)
		if ( ! empty( $s['items'] ) ) {
			$this->render_static_list( (array) $s['items'] );
			return;
		}

		// Dynamic: strictly from jprm_menu_item
		if ( ! post_type_exists( 'jprm_menu_item' ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Menu item type not found (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_presentation = (string) ( $s['label_presentation'] ?? 'icon_text' );
		$label_position     = (string) ( $s['label_position'] ?? 'right' );

		// Explicit selections only (auto-detect removed)
		$menus    = $this->normalize_to_slugs( $s['menus']    ?? [], 'jprm_menu' );
		$sections = $this->normalize_to_slugs( $s['sections'] ?? [], 'jprm_section' );

		// If nothing selected, optionally show all (when enabled).
		if ( empty( $menus ) && empty( $sections ) && ( $s['show_all_when_empty'] ?? 'no' ) !== 'yes' ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No menu/section selected.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$orderby = in_array( $s['query_orderby'] ?? 'menu_order', [ 'menu_order', 'title', 'date' ], true ) ? $s['query_orderby'] : 'menu_order';
		$order   = ( $s['query_order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
		$limit   = isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ? (int) $s['query_limit'] : 0;

		$items = $this->query_items( $menus, $sections, $orderby, $order, $limit );
		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_map    = $this->build_label_map();
		$presentation = $label_presentation;
		$position     = $label_position;

		echo '<ul class="jp-menu">';
		foreach ( $items as $post ) {
			$post_id = (int) $post->ID;

			$title = get_the_title( $post_id );
			$desc  = get_post_meta( $post_id, 'jprm_desc', true );

			$cfg = $this->read_price_config( $post_id );
			if ( empty( $cfg ) ) {
				// Nothing to display: skip gracefully.
				continue;
			}

			echo '<li class="jp-menu__item"><div class="jp-menu__inner">';

			// Left: title + description
			echo '  <div class="jp-menu__content">';
			if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( is_string($desc) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '  </div>';

			// Right: prices
			echo '  <div class="jp-menu__pricegroup">';

			if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
				$resolved   = $this->resolve_label_ref( $cfg['label_ref'], $label_map, $cfg['icon_id'] ?? 0 );
				$label_html = $this->label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) $cfg['hide_icon'] );

				if ( $position === 'left' ) {
					echo '    <div class="jp-menu__price">';
					echo '      <div class="jp-col-label">' . $label_html . '</div>';
					echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
					echo '    </div>';
				} else {
					echo '    <div class="jp-menu__price">';
					echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
					echo '      <div class="jp-col-label">' . $label_html . '</div>';
					echo '    </div>';
				}
			}

			if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
				foreach ( $cfg['rows'] as $row ) {
					$price     = (string) ( $row['value'] ?? '' );
					if ( $price === '' ) continue;
					$ref       = (string) ( $row['label_ref'] ?? '' );
					$hide_icon = (bool)   ( $row['hide_icon'] ?? false );
					$icon_id   = (int)    ( $row['icon_id'] ?? 0 );

					$resolved   = $this->resolve_label_ref( $ref, $label_map, $icon_id );
					$label_html = $this->label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, $hide_icon );

					if ( $position === 'left' ) {
						echo '    <div class="jp-menu__price">';
						echo '      <div class="jp-col-label">' . $label_html . '</div>';
						echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
						echo '    </div>';
					} else {
						echo '    <div class="jp-menu__price">';
						echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
						echo '      <div class="jp-col-label">' . $label_html . '</div>';
						echo '    </div>';
					}
				}
			}

			echo '  </div>'; // .jp-menu__pricegroup
			echo '</div></li>';   // .jp-menu__item/.jp-menu__inner
		}
		echo '</ul>';
	}

	/** Query posts with the given menu/section slugs */
	protected function query_items( array $menus_slugs, array $section_slugs, string $orderby, string $order, int $limit ) : array {
		$tax_query = [ 'relation' => 'AND' ];

		if ( ! empty( $menus_slugs ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'slug',
				'terms'    => $menus_slugs,
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

		// Only set tax_query if we actually have filters.
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new \WP_Query( $args );
		return $q->have_posts() ? $q->posts : [];
	}

	/** Read price config: meta jprm_price (JSON) with structure:
	 *  mode: single|multi
	 *  price: "12.00"
	 *  label_ref: (string) ref to registry (id/slug) OR raw when custom
	 *  hide_icon: bool
	 *  rows: [ { value, label_ref, icon_id, hide_icon }, ... ]
	 */
	protected function read_price_config( int $post_id ) : array {
		$json = get_post_meta( $post_id, 'jprm_price', true );
		if ( ! is_string( $json ) || $json === '' ) return [];

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) return [];

		$mode = isset( $data['mode'] ) && in_array( $data['mode'], [ 'single', 'multi' ], true ) ? $data['mode'] : 'single';

		$out = [ 'mode' => $mode, 'price' => '', 'rows' => [], 'label_ref' => '', 'hide_icon' => false, 'icon_id' => 0 ];

		if ( $mode === 'single' ) {
			$out['price']     = (string) ( $data['price'] ?? '' );
			$out['label_ref'] = (string) ( $data['label_ref'] ?? '' );
			$out['hide_icon'] = (bool)   ( $data['hide_icon'] ?? false );
			$out['icon_id']   = (int)    ( $data['icon_id'] ?? 0 );
		} else {
			$rows = is_array( $data['rows'] ?? null ) ? $data['rows'] : [];
			foreach ( $rows as $row ) {
				$out['rows'][] = [
					'value'     => (string) ( $row['value'] ?? '' ),
					'label_ref' => (string) ( $row['label_ref'] ?? '' ),
					'hide_icon' => (bool)   ( $row['hide_icon'] ?? false ),
					'icon_id'   => (int)    ( $row['icon_id'] ?? 0 ),
				];
			}
		}
		return $out;
	}

	protected function build_label_map() : array {
		$opt = get_option( 'jprm_price_labels_v2' );
		$list = is_string($opt) ? json_decode($opt, true) : ( is_array($opt) ? $opt : [] );
		$map = [];
		if ( is_array($list) ) {
			foreach ( $list as $row ) {
				$id   = isset($row['id']) ? (string)$row['id'] : '';
				$slug = isset($row['slug']) ? (string)$row['slug'] : '';
				$lab  = isset($row['label']) ? (string)$row['label'] : '';
				$ico  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
				if ( $id !== '' )   $map[ $id ]   = [ 'text' => $lab, 'icon_id' => $ico ];
				if ( $slug !== '' ) $map[ $slug ] = [ 'text' => $lab, 'icon_id' => $ico ];
			}
		}
		return $map;
	}

	/** Resolve a label ref against registry; or treat ref as custom text with optional icon override */
	protected function resolve_label_ref( string $ref, array $map, int $icon_override = 0 ) : array {
		$ref = trim( $ref );
		if ( $ref === '' ) {
			return [ 'text' => '', 'icon_id' => 0 ];
		}
		if ( isset( $map[ $ref ] ) ) {
			return [ 'text' => (string)$map[$ref]['text'], 'icon_id' => (int)$map[$ref]['icon_id'] ];
		}
		// Custom text path; allow row-defined icon_id
		return [ 'text' => $ref, 'icon_id' => $icon_override ];
	}

	/* =========================
	 * Small helpers
	 * ========================= */

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
			if ( $price !== '' ) echo '    <div class="jp-menu__price"><span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span></div>';
			echo '  </div>';
			echo '</div></li>';
		}
		echo '</ul>';
	}

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
}
