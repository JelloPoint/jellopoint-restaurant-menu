<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Restored layout & label logic exactly as in your working file, with minor fixes:
 *  - Fixed truncated strings/lines.
 *  - Fixed resolver variable name.
 *  - Avoids Elementor\Controls_Stack::render_static() clash by using render_static_list().
 */
final class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }
	public function get_script_depends() { return []; }

	/* =========================
	 * Controls
	 * ========================= */
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
			'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'menus', [
			'label'     => __( 'Filter by Menus', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT2,
			'multiple'  => true,
			'options'   => $menu_options,
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'     => __( 'Filter by Sections', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT2,
			'multiple'  => true,
			'options'   => $section_options,
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'query_orderby', [
			'label'     => __( 'Order By', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'menu_order',
			'options'   => [
				'menu_order' => __( 'Menu Order', 'jellopoint-restaurant-menu' ),
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
			'label'       => __( 'Max Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'step'        => 1,
			'default'     => 0,
			'description' => __( '0 = no limit', 'jellopoint-restaurant-menu' ),
			'condition'   => [ 'data_mode' => 'dynamic' ],
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

		// Static items (uses item_* keys to match your renderer).
		$rep = new Repeater();
		$rep->add_control( 'item_title', [
			'label'       => __( 'Title', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'label_block' => true,
		] );
		$rep->add_control( 'item_description', [
			'label'       => __( 'Description', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXTAREA,
			'rows'        => 2,
			'label_block' => true,
		] );
		$rep->add_control( 'item_price', [
			'label' => __( 'Price', 'jellopoint-restaurant-menu' ),
			'type'  => Controls_Manager::TEXT,
		] );

		$this->add_control( 'items', [
			'label'       => __( 'Static Items', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'default'     => [],
			'title_field' => '{{{ item_title }}}',
			'condition'   => [ 'data_mode' => 'static' ],
		] );

		$this->end_controls_section();
	}

	/* =========================
	 * Render
	 * ========================= */
	public function render() {
		$s = $this->get_settings_for_display();

		$mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;

		// Legacy behaviour: if static items exist and no explicit mode, use static.
		if ( 'static' === $mode || ( null === $mode && ! empty( $s['items'] ) ) ) {
			$this->render_static_list( is_array($s['items']) ? $s['items'] : [] );
			return;
		}

		$show_all          = ( isset( $s['show_all_when_empty'] ) && 'yes' === $s['show_all_when_empty'] );
		$menus             = isset( $s['menus'] ) ? (array) $s['menus'] : [];
		$sections          = isset( $s['sections'] ) ? (array) $s['sections'] : [];
		$orderby           = isset( $s['query_orderby'] ) ? (string) $s['query_orderby'] : 'menu_order';
		$order             = isset( $s['query_order'] ) ? (string) $s['query_order'] : 'ASC';
		$limit             = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;
		$label_presentation= isset( $s['label_presentation'] ) ? (string) $s['label_presentation'] : 'icon_text';
		$label_position    = isset( $s['label_position'] ) ? (string) $s['label_position'] : 'right';

		$menus    = $this->normalize_to_slugs( $menus, 'jprm_menu' );
		$sections = $this->normalize_to_slugs( $sections, 'jprm_section' );

		if ( empty( $menus ) && empty( $sections ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

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
			$title   = get_the_title( $post_id );
			$desc    = get_post_meta( $post_id, 'jprm_desc', true );

			$cfg = $this->read_price_config( $post_id );
			if ( empty( $cfg ) ) { continue; }

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
					$val = (string) ( $row['value'] ?? '' );
					if ( $val === '' ) { continue; }
					$resolved   = $this->resolve_label_ref( (string) ( $row['label_ref'] ?? '' ), $label_map, (int) ( $row['icon_id'] ?? 0 ) );
					$label_html = $this->label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) ( $row['hide_icon'] ?? false ) );

					if ( $position === 'left' ) {
						echo '    <div class="jp-menu__price">';
						echo '      <div class="jp-col-label">' . $label_html . '</div>';
						echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $val ) . '</span>';
						echo '    </div>';
					} else {
						echo '    <div class="jp-menu__price">';
						echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $val ) . '</span>';
						echo '      <div class="jp-col-label">' . $label_html . '</div>';
						echo '    </div>';
					}
				}
			}

			echo '  </div>'; // .jp-menu__pricegroup
			echo '</div></li>';
		}
		echo '</ul>';
	}

	/* =========================
	 * Data helpers
	 * ========================= */

	protected function query_items( array $menus_slugs, array $sections_slugs, string $orderby, string $order, int $limit ) : array {
		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'orderby'        => in_array( $orderby, [ 'menu_order','title','date' ], true ) ? $orderby : 'menu_order',
			'order'          => ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC',
			'posts_per_page' => ( $limit > 0 ) ? $limit : -1,
		];

		$tax_query = [];
		if ( ! empty( $menus_slugs ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_menu', 'field' => 'slug', 'terms' => $menus_slugs ];
		}
		if ( ! empty( $sections_slugs ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'slug', 'terms' => $sections_slugs ];
		}
		if ( ! empty( $tax_query ) ) { $args['tax_query'] = $tax_query; }

		$q = new \WP_Query( $args );
		return $q->posts ?? [];
	}

	/** Read price config: meta jprm_price (JSON with mode/single/multi). */
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

	/** Build label map from option jprm_price_labels_v2 (ids and slugs to text/icon_id). */
	protected function build_label_map() : array {
		$opt  = get_option( 'jprm_price_labels_v2' );
		$list = is_string($opt) ? json_decode($opt, true) : ( is_array($opt) ? $opt : [] );
		$map  = [];
		if ( is_array($list) ) {
			foreach ( $list as $row ) {
				$id   = isset($row['id']) ? (string)$row['id'] : '';
				$slug = isset($row['slug']) ? (string)$row['slug'] : '';
				$lab  = isset($row['label']) ? (string)$row['label'] : '';
				$ico  = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
				if ( $id   !== '' ) $map[ $id ]   = [ 'text' => $lab, 'icon_id' => $ico ];
				if ( $slug !== '' ) $map[ $slug ] = [ 'text' => $lab, 'icon_id' => $ico ];
			}
		}
		return $map;
 	}

	/** Resolve label_ref against registry; or treat ref as custom text with optional icon override */
	protected function resolve_label_ref( string $ref, array $map, int $icon_override = 0 ) : array {
		$ref = trim( $ref );
		if ( $ref === '' ) {
			return [ 'text' => '', 'icon_id' => $icon_override ];
		}
		if ( isset( $map[ $ref ] ) ) {
			return [ 'text' => (string)$map[$ref]['text'], 'icon_id' => (int)$map[$ref]['icon_id'] ];
		}
		// Custom text path; allow row-defined icon_id override.
		return [ 'text' => $ref, 'icon_id' => $icon_override ];
	}

	/* =========================
	 * Small helpers
	 * ========================= */

	/** Convert selected term IDs or slugs into slugs for the given taxonomy. */
	protected function normalize_to_slugs( $values, string $taxonomy ) : array {
		if ( empty( $values ) ) return [];
		$slugs = [];
		foreach ( (array) $values as $v ) {
			// If it's a non-numeric string, assume it's already a slug.
			if ( is_string( $v ) && ! ctype_digit( $v ) ) {
				$slugs[] = $v;
				continue;
			}
			// Numeric id: resolve to slug.
			$term = get_term( (int) $v, $taxonomy );
			if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) && $term->slug !== '' ) {
				$slugs[] = $term->slug;
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	/** Render static items list (legacy/unchanged keys item_title/item_description/item_price) */
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
