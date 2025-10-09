<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JelloPoint – Restaurant Menu (Elementor Widget)
 * Cleanup-only:
 * - Removed "Auto-detect context" control/logic
 * - Robust label resolution (id / slug / pl-*), no "pl-0" text leakage
 * - Kept DOM structure identical; no extra classes that could affect CSS
 */
class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu', 'restaurant', 'prices', 'jellopoint', 'labels' ]; }

	public function get_style_depends() {
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

		// Explicit Dynamic vs Static
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

		// (Auto-detect context removed)

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

		/* ---- Static items ---- */
		$this->start_controls_section(
			'section_static',
			[
				'label'      => __( 'Static Items', 'jellopoint-restaurant-menu' ),
				'condition'  => [ 'data_mode' => 'static' ],
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

		// Source selection:
		$mode = isset( $s['data_mode'] ) ? $s['data_mode'] : null;
		if ( $mode === 'static' || ( $mode === null && ! empty( $s['items'] ) ) ) {
			if ( ! empty( $s['items'] ) ) {
				$this->render_static_list( (array) $s['items'] );
			}
			return;
		}

		// Dynamic from CPT
		if ( ! post_type_exists( 'jprm_menu_item' ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Menu item type not found (jprm_menu_item).', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_presentation = (string) ( $s['label_presentation'] ?? 'icon_text' );
		$label_position     = (string) ( $s['label_position'] ?? 'right' );

		$menus    = $this->normalize_to_slugs( $s['menus']    ?? [], 'jprm_menu' );
		$sections = $this->normalize_to_slugs( $s['sections'] ?? [], 'jprm_section' );

		// If nothing selected, optionally show all when enabled.
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

		$label_index = $this->build_label_index(); // ['by_slug'=>[], 'by_id'=>[]]

		echo '<div class="jp-menu">';
		foreach ( $items as $post ) {
			$post_id = (int) $post->ID;

			$title = get_the_title( $post_id );
			$desc  = get_post_meta( $post_id, 'jprm_desc', true );

			$cfg = $this->read_price_config( $post_id );
			if ( empty( $cfg ) ) {
				continue;
			}

			echo '  <div class="jp-menu__row">';
			echo '    <div class="jp-menu__content">';
			if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( is_string($desc) && $desc !== '' ) echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '    </div>';

			echo '    <div class="jp-menu__pricegroup">';

			if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
				$r = $this->resolve_label_ref( $cfg['label_ref'], $label_index, (int) ( $cfg['icon_id'] ?? 0 ) );
				$label_html = $this->label_html( $r['text'], (int) $r['icon_id'], $label_presentation, (bool) ( $cfg['hide_icon'] ?? false ), $r['css_class'] );

				if ( $label_position === 'left' ) {
					echo '      <div class="jp-menu__price">';
					echo '        <div class="jp-col-label">' . $label_html . '</div>';
					echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
					echo '      </div>';
				} else {
					echo '      <div class="jp-menu__price">';
					echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
					echo '        <div class="jp-col-label">' . $label_html . '</div>';
					echo '      </div>';
				}
			}

			if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
				foreach ( $cfg['rows'] as $row ) {
					$price     = (string) ( $row['value'] ?? '' );
					if ( $price === '' ) continue;

					$r = $this->resolve_label_ref(
						(string) ( $row['label_ref'] ?? '' ),
						$label_index,
						(int) ( $row['icon_id'] ?? 0 )
					);
					$label_html = $this->label_html(
						$r['text'],
						(int) $r['icon_id'],
						$label_presentation,
						(bool) ( $row['hide_icon'] ?? false ),
						$r['css_class']
					);

					if ( $label_position === 'left' ) {
						echo '      <div class="jp-menu__price">';
						echo '        <div class="jp-col-label">' . $label_html . '</div>';
						echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
						echo '      </div>';
					} else {
						echo '      <div class="jp-menu__price">';
						echo '        <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
						echo '        <div class="jp-col-label">' . $label_html . '</div>';
						echo '      </div>';
					}
				}
			}

			echo '    </div>'; // .jp-menu__pricegroup
			echo '  </div>';   // .jp-menu__row
		}
		echo '</div>';
	}

	/** Query posts with the given menu/section slugs */
	protected function query_items( array $menus_slugs, array $section_slugs, string $orderby, string $order, int $limit ) : array {
		$tax_query = [ 'relation' => 'AND' ];

		if ( ! empty( $menus_slugs ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_menu', 'field' => 'slug', 'terms' => $menus_slugs ];
		}
		if ( ! empty( $section_slugs ) ) {
			$tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'slug', 'terms' => $section_slugs ];
		}

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'tax_query'      => count( $tax_query ) > 1 ? $tax_query : [],
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => true,
		];

		$q = new \WP_Query( $args );
		return $q->have_posts() ? $q->posts : [];
	}

	/** Price config from meta "jprm_price" JSON */
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

	/**
	 * Build label index that works whether the option is a list or a map.
	 * Returns: ['by_slug' => [slug => row], 'by_id' => [id => row]]
	 * Normalized row keys: text, icon_id, css_class, slug, id
	 */
	protected function build_label_index() : array {
		$opt = get_option( 'jprm_price_labels_v2', [] );
		$by_slug = [];
		$by_id   = [];

		if ( is_array( $opt ) ) {
			foreach ( $opt as $k => $row ) {
				if ( ! is_array( $row ) ) continue;

				$slug = (string) ( $row['slug'] ?? $row['code'] ?? '' );
				$id   = null;
				if ( isset( $row['id'] ) && is_numeric( $row['id'] ) ) {
					$id = (int) $row['id'];
				}

				$norm = [
					'text'      => (string) ( $row['text'] ?? $row['label'] ?? '' ),
					'icon_id'   => (int)    ( $row['icon_id'] ?? $row['icon'] ?? 0 ),
					'css_class' => (string) ( $row['class'] ?? $row['css'] ?? $row['css_class'] ?? $row['code'] ?? '' ),
					'slug'      => $slug,
					'id'        => $id,
				];

				if ( $slug !== '' ) $by_slug[ $slug ] = $norm;
				if ( $id   !== null ) $by_id[ $id ]   = $norm;

				// If the option is already keyed by slug string, preserve that too.
				if ( is_string( $k ) && $k !== '' && ! isset( $by_slug[ $k ] ) ) {
					$by_slug[ $k ] = $norm;
				}
				// If numeric top-level keys are actually IDs, we already handled via $id.
			}
		}

		return [ 'by_slug' => $by_slug, 'by_id' => $by_id ];
	}

	/**
	 * Resolve label reference to normalized parts:
	 * - text, icon_id, css_class
	 * Supports numeric id, slug, and "pl-*" code.
	 */
	protected function resolve_label_ref( string $ref, array $index, int $fallback_icon_id = 0 ) : array {
		$text = '';
		$icon_id = 0;
		$css_class = '';

		if ( $ref !== '' ) {
			// Numeric id?
			if ( ctype_digit( $ref ) ) {
				$key = (int) $ref;
				if ( isset( $index['by_id'][ $key ] ) ) {
					$row = $index['by_id'][ $key ];
					$text      = $row['text'];
					$icon_id   = $row['icon_id'];
					$css_class = $row['css_class'];
				}
			}

			// Slug / code?
			if ( $text === '' && isset( $index['by_slug'][ $ref ] ) ) {
				$row = $index['by_slug'][ $ref ];
				$text      = $row['text'];
				$icon_id   = $row['icon_id'];
				$css_class = $row['css_class'];
			}

			// pl-* fallback as CSS class
			if ( $css_class === '' && preg_match( '/^pl-[A-Za-z0-9_-]+$/', $ref ) ) {
				$css_class = $ref;
			}

			// If text itself looks like pl-*, treat as class not text.
			if ( $text !== '' && preg_match( '/^pl-[A-Za-z0-9_-]+$/', $text ) ) {
				if ( $css_class === '' ) $css_class = $text;
				$text = '';
			}

			// Full fallback: custom text
			if ( $text === '' && $icon_id === 0 && $css_class === '' ) {
				$text = $ref;
			}
		}

		if ( $icon_id === 0 && $fallback_icon_id > 0 ) {
			$icon_id = $fallback_icon_id;
		}

		return [ 'text' => $text, 'icon_id' => $icon_id, 'css_class' => $css_class ];
	}

	/** Normalize control values to term slugs */
	protected function normalize_to_slugs( $values, string $taxonomy ) : array {
		if ( empty( $values ) ) return [];
		$slugs = [];
		foreach ( $values as $v ) {
			if ( is_string( $v ) && ! ctype_digit( $v ) ) { $slugs[] = $v; continue; }
			$term_id = is_numeric( $v ) ? (int) $v : 0;
			if ( $term_id > 0 ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) && ! empty( $term->slug ) ) $slugs[] = $term->slug;
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	protected function render_static_list( array $items ) : void {
		echo '<div class="jp-menu">';
		foreach ( $items as $it ) {
			$title = $it['item_title'] ?? '';
			$desc  = $it['item_description'] ?? '';
			$price = $it['item_price'] ?? '';
			if ( $title === '' && $price === '' && $desc === '' ) continue;

			echo '  <div class="jp-menu__row">';
			echo '    <div class="jp-menu__content">';
			if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( $desc !== '' )  echo '      <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			echo '    </div>';
			echo '    <div class="jp-menu__pricegroup">';
			if ( $price !== '' ) echo '      <div class="jp-menu__price"><span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span></div>';
			echo '    </div>';
			echo '  </div>';
		}
		echo '</div>';
	}

	/**
	 * Build label HTML from text/icon_id/css_class, honoring presentation & hide_icon.
	 * - If icon_id > 0 → image icon
	 * - Else if css_class → <span class="jp-menu__icon pl-0"> (no visible "pl-0" text)
	 * - Else text rendering
	 */
	protected function label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon, string $css_class = '' ) : string {
		$icon_html = '';

		if ( ! $hide_icon ) {
			if ( $icon_id > 0 ) {
				$img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
				if ( is_string( $img ) ) $icon_html = $img;
			} elseif ( $css_class !== '' ) {
				$icon_html = '<span class="jp-menu__icon ' . esc_attr( $css_class ) . '" aria-hidden="true"></span>';
			}
		}

		if ( $presentation === 'icon' ) {
			return $icon_html;
		}
		if ( $presentation === 'text' ) {
			return esc_html( $label_text );
		}

		// icon_text
		if ( $icon_html !== '' && $label_text !== '' ) {
			return $icon_html . ' ' . esc_html( $label_text );
		}
		if ( $icon_html !== '' ) return $icon_html;
		return esc_html( $label_text );
	}
}
