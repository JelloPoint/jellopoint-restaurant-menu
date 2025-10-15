<?php
/**
 * Elementor Widget: JelloPoint Restaurant Menu
 *
 * Drop-in: restores label/icon rendering + stable DOM wrappers for CSS,
 * keeps Dynamic/Static behaviour and control IDs unchanged, and avoids
 * clashing with Elementor\Controls_Stack::render_static().
 *
 * @package JelloPoint\RestaurantMenu
 */

namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Restaurant_Menu extends Widget_Base {

	public function get_name() {
		return 'jprm_restaurant_menu';
	}

	public function get_title() {
		return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return [ 'jellopoint-widgets' ];
	}

	public function get_keywords() {
		return [ 'menu', 'restaurant', 'prices', 'jellopoint', 'labels' ];
	}

	public function get_style_depends() {
		return [ 'jprm-menu' ];
	}

	public function get_script_depends() {
		return [];
	}

	/* ---------------------------
	 *  Helpers
	 * --------------------------- */

	/** Build a select list of terms for a taxonomy (id => name) */
	protected function get_terms_options( string $taxonomy ) : array {
		$out   = [];
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( is_object( $t ) && isset( $t->term_id, $t->name ) ) {
					$out[ (string) $t->term_id ] = $t->name;
				}
			}
		}
		return $out;
	}

	/**
	 * Read saved price labels (option jprm_price_labels_v2) and map by both id and slug.
	 * Be liberal with keys: support legacy shapes (icon, iconId, attachment_id).
	 */
	protected function build_label_map() : array {
		$map     = [];
		$raw_opt = get_option( 'jprm_price_labels_v2' );

		if ( is_array( $raw_opt ) ) {
			foreach ( $raw_opt as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id   = isset( $row['id'] ) ? (string) $row['id'] : '';
				$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';

				// Name.
				$name = '';
				if ( isset( $row['name'] ) ) {
					$name = (string) $row['name'];
				} elseif ( isset( $row['label'] ) ) {
					$name = (string) $row['label'];
				}

				// Icon id (accept various legacy keys).
				$icon_id = 0;
				foreach ( [ 'icon_id', 'iconId', 'icon', 'attachment_id', 'attachmentId' ] as $k ) {
					if ( isset( $row[ $k ] ) && (int) $row[ $k ] > 0 ) {
						$icon_id = (int) $row[ $k ];
						break;
					}
				}

				if ( ! $id && ! $slug ) {
					continue;
				}

				$normalized = [
					'id'      => $id,
					'slug'    => $slug,
					'name'    => $name,
					'icon_id' => $icon_id,
				];

				if ( $id ) {
					$map[ 'id:' . $id ] = $normalized;
				}
				if ( $slug ) {
					$map[ 'slug:' . $slug ] = $normalized;
				}
			}
		}

		return $map;
	}

	/**
	 * Find a label row by flexible ref (accept: ['type'=>'id'|'slug','value'=>..], raw int id, numeric-string id, or slug).
	 */
	protected function resolve_label_ref( array $label_map, $ref ) : ?array {
		// Structured ref: ['type' => 'id'|'slug', 'value' => '...'].
		if ( is_array( $ref ) && isset( $ref['type'], $ref['value'] ) ) {
			$type = (string) $ref['type'];
			$val  = (string) $ref['value'];
			$key  = ( 'id' === $type ? 'id:' . $val : 'slug:' . $val );
			return $label_map[ $key ] ?? null;
		}

		// Plain integer (id).
		if ( is_int( $ref ) ) {
			return $label_map[ 'id:' . (string) $ref ] ?? null;
		}

		// String: maybe numeric id, else slug.
		if ( is_string( $ref ) && $ref !== '' ) {
			if ( ctype_digit( $ref ) ) {
				return $label_map[ 'id:' . $ref ] ?? null;
			}
			return $label_map[ 'slug:' . $ref ] ?? null;
		}

		return null;
	}

	/** Render a label (icon/text/both) */
	protected function render_label_html( array $label, string $presentation ) : string {
		$name    = isset( $label['name'] ) ? (string) $label['name'] : '';
		$icon_id = isset( $label['icon_id'] ) ? (int) $label['icon_id'] : 0;

		$parts = [];
		if ( ( 'icon' === $presentation || 'both' === $presentation ) && $icon_id ) {
			$img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [ 'class' => 'jp-label__icon' ] );
			if ( $img ) {
				$parts[] = $img;
			}
		}
		if ( ( 'text' === $presentation || 'both' === $presentation ) && $name !== '' ) {
			$parts[] = '<span class="jp-label__text">' . esc_html( $name ) . '</span>';
		}

		if ( empty( $parts ) ) {
			return '';
		}
		return implode( '', $parts );
	}

	/**
	 * Render a price value with optional label on left/right.
	 * DOM structure designed to match legacy CSS expectations.
	 */
	protected function render_price_value( string $price, ?array $label, string $presentation, string $label_pos ) : string {
		$label_inner = $label ? $this->render_label_html( $label, $presentation ) : '';
		$value_html  = '<span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';

		$left  = '';
		$right = '';

		if ( $label_inner ) {
			if ( 'left' === $label_pos ) {
				$left = '<span class="jp-label jp-label--left">' . $label_inner . '</span>';
			} else {
				$right = '<span class="jp-label jp-label--right">' . $label_inner . '</span>';
			}
		}

		return '<div class="jp-menu__price">' . $left . $value_html . $right . '</div>';
	}

	/**
	 * Render the price block from stored meta (supports legacy shapes):
	 * - Single: { type:'single', price:'...', label:'slug' | '12' | {type,value} | label_id | label_slug }
	 * - Multi:  { type:'multi', rows:[ { price:'...', label:... | label_id | label_slug }, ... ] }
	 */
	protected function render_price_block_from_meta( $prices_meta, array $label_map, string $presentation, string $label_pos ) : string {
		// $prices_meta can be JSON or structured array.
		if ( is_string( $prices_meta ) && $prices_meta ) {
			$decoded = json_decode( $prices_meta, true );
			if ( is_array( $decoded ) ) {
				$prices_meta = $decoded;
			}
		}
		if ( ! is_array( $prices_meta ) ) {
			return '';
		}

		$type = isset( $prices_meta['type'] ) ? (string) $prices_meta['type'] : 'single';

		// Helper to extract a label "ref" from various keys (label / label_id / label_slug).
		$extract_ref = function( array $row ) {
			if ( isset( $row['label'] ) ) {
				return $row['label'];
			}
			if ( isset( $row['label_id'] ) ) {
				return $row['label_id']; // numeric or string id.
			}
			if ( isset( $row['label_slug'] ) ) {
				return $row['label_slug']; // slug string.
			}
			return null;
		};

		$html = '';

		if ( 'single' === $type ) {
			$price = isset( $prices_meta['price'] ) ? (string) $prices_meta['price'] : '';
			$lab   = $extract_ref( $prices_meta );

			$lab_r = $this->resolve_label_ref( $label_map, $lab );

			if ( '' !== $price ) {
				$html .= $this->render_price_value( $price, $lab_r, $presentation, $label_pos );
			}
		} elseif ( 'multi' === $type ) {
			$rows = isset( $prices_meta['rows'] ) && is_array( $prices_meta['rows'] ) ? $prices_meta['rows'] : [];
			if ( $rows ) {
				$html .= '<div class="jp-menu__prices jp-menu__prices--multi">';
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$price = isset( $row['price'] ) ? (string) $row['price'] : '';
					if ( '' === $price ) {
						continue;
					}
					$lab   = $extract_ref( $row );
					$lab_r = $this->resolve_label_ref( $label_map, $lab );

					$html .= $this->render_price_value( $price, $lab_r, $presentation, $label_pos );
				}
				$html .= '</div>';
			}
		}

		return $html;
	}

	protected function sanitize_allowed_html_desc() : array {
		return [
			'br'     => [],
			'em'     => [],
			'strong' => [],
			'span'   => [ 'class' => [] ],
		];
	}

	/* ---------------------------
	 *  Controls
	 * --------------------------- */

	protected function register_controls() {

		$this->start_controls_section(
			'section_source',
			[
				'label' => __( 'Source', 'jellopoint-restaurant-menu' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'data_mode',
			[
				'label'   => __( 'Source Mode', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::CHOOSE,
				'toggle'  => true,
				'default' => 'dynamic',
				'options' => [
					'dynamic' => [
						'title' => __( 'Dynamic', 'jellopoint-restaurant-menu' ),
						'icon'  => 'eicon-database',
					],
					'static'  => [
						'title' => __( 'Static', 'jellopoint-restaurant-menu' ),
						'icon'  => 'eicon-editor-list-ul',
					],
				],
			]
		);

		$this->add_control(
			'show_all_when_empty',
			[
				'label'        => __( 'Show all items when no Menu/Section is selected', 'jellopoint-restaurant-menu' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'menus',
			[
				'label'     => __( 'Filter by Menus', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::SELECT2,
				'multiple'  => true,
				'options'   => $this->get_terms_options( 'jprm_menu' ),
				'condition' => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'sections',
			[
				'label'     => __( 'Filter by Sections', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::SELECT2,
				'multiple'  => true,
				'options'   => $this->get_terms_options( 'jprm_section' ),
				'condition' => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'query_orderby',
			[
				'label'     => __( 'Order By', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'menu_order',
				'options'   => [
					'menu_order' => __( 'Menu Order', 'jellopoint-restaurant-menu' ),
					'title'      => __( 'Title', 'jellopoint-restaurant-menu' ),
					'date'       => __( 'Date', 'jellopoint-restaurant-menu' ),
				],
				'condition' => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'query_order',
			[
				'label'     => __( 'Order', 'jellopoint-restaurant-menu' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'ASC',
				'options'   => [
					'ASC'  => 'ASC',
					'DESC' => 'DESC',
				],
				'condition' => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'query_limit',
			[
				'label'       => __( 'Max Items', 'jellopoint-restaurant-menu' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'step'        => 1,
				'default'     => 0, // 0 = no limit.
				'description' => __( '0 = no limit', 'jellopoint-restaurant-menu' ),
				'condition'   => [ 'data_mode' => 'dynamic' ],
			]
		);

		$this->add_control(
			'label_presentation',
			[
				'label'   => __( 'Label Presentation', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'icon',
				'options' => [
					'icon' => __( 'Icon', 'jellopoint-restaurant-menu' ),
					'text' => __( 'Text', 'jellopoint-restaurant-menu' ),
					'both' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
				],
			]
		);

		$this->add_control(
			'label_position',
			[
				'label'   => __( 'Label Position', 'jellopoint-restaurant-menu' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'left'  => __( 'Left of price', 'jellopoint-restaurant-menu' ),
					'right' => __( 'Right of price', 'jellopoint-restaurant-menu' ),
				],
			]
		);

		// Static items repeater (legacy compatible).
		$rep = new Repeater();
		$rep->add_control(
			'title',
			[
				'label'       => __( 'Title', 'jellopoint-restaurant-menu' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			]
		);
		$rep->add_control(
			'description',
			[
				'label'       => __( 'Description', 'jellopoint-restaurant-menu' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'label_block' => true,
			]
		);
		$rep->add_control(
			'price',
			[
				'label' => __( 'Price', 'jellopoint-restaurant-menu' ),
				'type'  => Controls_Manager::TEXT,
			]
		);

		$this->add_control(
			'items',
			[
				'label'       => __( 'Static Items', 'jellopoint-restaurant-menu' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => [],
				'title_field' => '{{{ title }}}',
				'condition'   => [ 'data_mode' => 'static' ],
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------
	 *  Rendering
	 * --------------------------- */

	public function render() {
		$settings = $this->get_settings_for_display();

		$mode = isset( $settings['data_mode'] ) ? (string) $settings['data_mode'] : null;

		// Legacy: if items are present and no explicit mode, render static.
		if ( 'static' === $mode || ( null === $mode && ! empty( $settings['items'] ) ) ) {
			$this->render_static_mode( $settings );
			return;
		}

		// Default: dynamic.
		$this->render_dynamic( $settings );
	}

	/** Render Static list (repeater) — renamed to avoid Elementor method clash */
	protected function render_static_mode( array $settings ) : void {
		$items        = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : [];
		// Keep these to preserve DOM shape if we add static labels later.
		$presentation = isset( $settings['label_presentation'] ) ? (string) $settings['label_presentation'] : 'icon';
		$label_pos    = isset( $settings['label_position'] ) ? (string) $settings['label_position'] : 'left';

		if ( empty( $items ) ) {
			echo '<div class="jp-menu jp-menu--empty">' . esc_html__( 'No items defined.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		echo '<div class="jp-menu">';
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$desc  = isset( $item['description'] ) ? (string) $item['description'] : '';
			$price = isset( $item['price'] ) ? (string) $item['price'] : '';

			echo '<div class="jp-menu__item">';

			if ( '' !== $title ) {
				echo '<div class="jp-menu__title jp-col-title">' . esc_html( $title ) . '</div>';
			}

			if ( '' !== $desc ) {
				echo '<div class="jp-menu__desc">' . wp_kses( $desc, $this->sanitize_allowed_html_desc() ) . '</div>';
			}

			if ( '' !== $price ) {
				$value_html = '<span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
				echo '<div class="jp-menu__price">' . $value_html . '</div>';
			}

			echo '</div>'; // .jp-menu__item
		}
		echo '</div>'; // .jp-menu
	}

	/** Render Dynamic query (CPT jprm_menu_item) */
	protected function render_dynamic( array $settings ) : void {
		$show_all   = ( isset( $settings['show_all_when_empty'] ) && 'yes' === $settings['show_all_when_empty'] );
		$menus      = isset( $settings['menus'] ) && is_array( $settings['menus'] ) ? array_values( $settings['menus'] ) : [];
		$sections   = isset( $settings['sections'] ) && is_array( $settings['sections'] ) ? array_values( $settings['sections'] ) : [];
		$orderby    = isset( $settings['query_orderby'] ) ? (string) $settings['query_orderby'] : 'menu_order';
		$order      = isset( $settings['query_order'] ) ? (string) $settings['query_order'] : 'ASC';
		$limit      = isset( $settings['query_limit'] ) ? (int) $settings['query_limit'] : 0;

		$tax_query = [];

		if ( ! empty( $menus ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'term_id',
				'terms'    => array_map( 'intval', $menus ),
			];
		}

		if ( ! empty( $sections ) ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'term_id',
				'terms'    => array_map( 'intval', $sections ),
			];
		}

		if ( empty( $tax_query ) && ! $show_all ) {
			echo '<div class="jp-menu jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'orderby'        => in_array( $orderby, [ 'menu_order', 'title', 'date' ], true ) ? $orderby : 'menu_order',
			'order'          => ( 'DESC' === strtoupper( $order ) ) ? 'DESC' : 'ASC',
			'posts_per_page' => ( $limit > 0 ) ? $limit : -1,
		];

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new \WP_Query( $args );

		if ( ! $q->have_posts() ) {
			echo '<div class="jp-menu jp-menu--empty">' . esc_html__( 'Menu items not found (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_map    = $this->build_label_map();
		$presentation = isset( $settings['label_presentation'] ) ? (string) $settings['label_presentation'] : 'icon';
		$label_pos    = isset( $settings['label_position'] ) ? (string) $settings['label_position'] : 'left';

		echo '<div class="jp-menu">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();

			$title = get_the_title( $post_id );
			$desc  = (string) get_post_meta( $post_id, 'jprm_desc', true );
			$meta  = get_post_meta( $post_id, 'jprm_price', true );

			echo '<div class="jp-menu__item">';

			if ( '' !== $title ) {
				echo '<div class="jp-menu__title jp-col-title">' . esc_html( $title ) . '</div>';
			}

			if ( '' !== $desc ) {
				echo '<div class="jp-menu__desc">' . wp_kses( $desc, $this->sanitize_allowed_html_desc() ) . '</div>';
			}

			$price_html = $this->render_price_block_from_meta( $meta, $label_map, $presentation, $label_pos );
			if ( $price_html ) {
				echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo '</div>'; // .jp-menu__item
		}
		wp_reset_postdata();
		echo '</div>'; // .jp-menu
	}
}
