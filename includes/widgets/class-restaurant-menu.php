<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * - Layout & label/icon logic as approved.
 * - Uses STRICT term_id filtering for both Menu (single) and Sections (multi).
 * - Keeps "Fallback to all items" behaviour.
 * - No clash with Elementor\Controls_Stack::render_static().
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
					$out[ (string) $t->term_id ] = $t->name; // <-- keys are IDs (strings)
				}
			}
		}
		return $out;
	}

	public function register_controls() {
		$menu_options    = $this->get_terms_options( 'jprm_menu' );
		$section_options = $this->get_terms_options( 'jprm_section' );

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

		// (Auto-detect context control removed)

		$this->add_control( 'show_all_when_empty', [
			'label'        => __( 'Fallback to all items when no menu/section', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'No', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		// MENUS: multi select (IDs as strings).
		$this->add_control( 'menus', [
			'label'       => __( 'Menus', 'jellopoint-restaurant-menu' ),
			'description' => __( 'Choose Menus to include.', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $menu_options,
			'multiple'    => true,
		] );

		// SECTIONS: multi select (IDs as strings).
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

		$this->end_controls_section();

		// --- Sections and Menus (empty group except for display toggles) ----------
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

		// --- Prices and Labels ----------------------------------------------------
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

		// Small heading: Prices (placeholder; logic untouched)
		$this->add_control( 'heading_prices', [
			'label'     => __( 'Prices', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		// Small heading: Labels
		$this->add_control( 'heading_labels', [
			'label'     => __( 'Labels', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		// Moved from Data Source (no functional change)
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

	/* =========================
	 * Rendering
	 * ========================= */

	/**
	 * Normalize to array of INT term IDs (strings/ints accepted).
	 */
	protected function normalize_to_ids( $values ) : array {
		$out = [];
		foreach ( (array) $values as $v ) {
			if ( $v === '' || $v === null ) continue;
			$out[] = (int) $v;
		}
		return array_values( array_unique( array_filter( $out, fn( $n ) => $n > 0 ) ) );
	}

	public function render() {
		$s = $this->get_settings_for_display();

		$mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;

		// Legacy: if static items exist and no explicit mode, use static.
		if ( 'static' === $mode || ( null === $mode && ! empty( $s['items'] ) ) ) {
			$this->render_static_list( is_array($s['items']) ? $s['items'] : [] );
			return;
		}

		$show_all           = ( isset( $s['show_all_when_empty'] ) && 'yes' === $s['show_all_when_empty'] );
		$menu_sel           = $s['menus'] ?? '';      // single select: string term_id or ''.
		$sections_sel       = $s['sections'] ?? [];   // multi select: array of term_id strings.
		$orderby            = isset( $s['query_orderby'] ) ? (string) $s['query_orderby'] : 'menu_order';
		$order              = isset( $s['query_order'] ) ? (string) $s['query_order'] : 'ASC';
		$limit              = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;
		$label_presentation = isset( $s['label_presentation'] ) ? (string) $s['label_presentation'] : 'icon_text';
		$label_position     = isset( $s['label_position'] ) ? (string) $s['label_position'] : 'right';

		// Normalize to arrays of INT term IDs (strict).
		$menu_ids     = $this->normalize_to_ids( $menu_sel );
		$section_ids  = $this->normalize_to_ids( $sections_sel );

		// If nothing selected and fallback off -> empty message.
		if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$items = $this->query_items( $menu_ids, $section_ids, $orderby, $order, $limit, $show_all );
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
			$desc  = (string) get_post_meta( $post_id, 'jprm_desc', true );
			$cfg   = $this->read_price_config( $post_id );

			echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
			echo '  <div class="jp-menu__content">';
			if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( $desc  !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '  </div>';

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

			if ( $cfg['mode'] === 'matrix' && ! empty( $cfg['rows'] ) ) {
				echo '    <div class="jp-menu__matrix">';
				foreach ( $cfg['rows'] as $row ) {
					$row_price = $row['price'] ?? '';
					if ( $row_price === '' ) { continue; }

					$resolved   = $this->resolve_label_ref( $row['label_ref'] ?? '', $label_map, (int) ( $row['icon_id'] ?? 0 ) );
					$label_html = $this->label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) ( $row['hide_icon'] ?? false ) );

					if ( $position === 'left' ) {
						echo '      <div class="jp-menu__price">';
						echo '        <div class="jp-col-label">' . $label_html . '</div>';
						echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $row_price ) . '</span>';
						echo '      </div>';
					} else {
						echo '      <div class="jp-menu__price">';
						echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $row_price ) . '</span>';
						echo '        <div class="jp-col-label">' . $label_html . '</div>';
						echo '      </div>';
					}
				}
				echo '    </div>';
			}

			echo '  </div>'; // .jp-menu__pricegroup

			echo '</div></li>';
		}
		echo '</ul>';
	}

	/**
	 * Query items. If both $menu_ids and $section_ids are empty and $fallback_all is true,
	 * returns all published items. Filters STRICTLY by term_id (no slug ambiguity).
	 */
	protected function query_items( array $menu_ids, array $section_ids, string $orderby, string $order, int $limit, bool $fallback_all ) : array {
		$args = [
			'post_type'        => 'jprm_menu_item',
			'post_status'      => 'publish',
			'orderby'          => in_array( $orderby, [ 'menu_order','title','date' ], true ) ? $orderby : 'menu_order',
			'order'            => ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC',
			'posts_per_page'   => ( $limit > 0 ) ? $limit : -1,
			'suppress_filters' => false, // WPML/Polylang friendly
		];

		$tax_query = [];

		if ( ! empty( $menu_ids ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_menu', 'field' => 'term_id', 'terms' => $menu_ids ];
		}
		if ( ! empty( $section_ids ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'term_id', 'terms' => $section_ids ];
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = ( count( $tax_query ) > 1 ) ? array_merge( [ 'relation' => 'AND' ], $tax_query ) : $tax_query;
		} elseif ( ! $fallback_all ) {
			return [];
		}

		$q = new \WP_Query( $args );
		return $q->have_posts() ? $q->posts : [];
	}

	/** Read price config JSON (meta: jprm_price). */
	protected function read_price_config( int $post_id ) : array {
		$json = (string) get_post_meta( $post_id, 'jprm_price', true );
		if ( $json === '' ) return [];
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : [];
	}

	/** Resolve a label reference into text/icon pair from the labels map. */
	protected function resolve_label_ref( $ref, array $labels, int $fallback_icon_id = 0 ) : array {
		$out = [ 'text' => '', 'icon_id' => $fallback_icon_id ];
		if ( is_string( $ref ) && $ref !== '' && isset( $labels[ $ref ] ) ) {
			$val = $labels[ $ref ];
			if ( is_array( $val ) ) {
				$out['text']    = (string) ( $val['text'] ?? '' );
				$out['icon_id'] = (int) ( $val['icon_id'] ?? $fallback_icon_id );
			} elseif ( is_string( $val ) ) {
				$out['text'] = $val;
			}
		}
		return $out;
	}

	/** Build labels map (placeholder; real source elsewhere). */
	protected function build_label_map() : array {
		return []; // keep existing behaviour; rendering tolerates empty labels gracefully.
	}

	/* =========================
	 * Static renderer
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
