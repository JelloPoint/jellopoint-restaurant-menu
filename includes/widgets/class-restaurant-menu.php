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
final class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }
	public function get_script_depends() { return []; }

	/** Load the canonical price-block partial once. */
	private static function require_price_partial_once() : void {
		static $loaded = false;
		if ( $loaded ) return;

		$path = dirname( __DIR__ ) . '/render/partials/price-block.php'; // includes/render/partials/price-block.php
		if ( is_readable( $path ) ) {
			require_once $path;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] price-block.php not found/readable at: ' . $path );
			}
		}
		$loaded = true;
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

		$this->add_control( 'menus', [
			'label'     => __( 'Menu', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'multiple'  => false,
			'options'   => $menu_options,
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'     => __( 'Sections', 'jellopoint-restaurant-menu' ),
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

		// Static mode controls
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

		/* --- Prices and Labels (labels kept intact) ----------------------------- */
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

		// Currency (presentational only)
		$this->add_control( 'jprm_curr_heading', [
			'label'     => __( 'Currency', 'jellopoint-restaurant-menu' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );
		$this->add_control( 'jprm_curr_show', [
			'label'        => __( 'Show currency symbol', 'jellopoint-restaurant-menu' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'jprm_curr_symbol', [
			'label'       => __( 'Currency symbol', 'jellopoint-restaurant-menu' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => '€',
			'default'     => '€',
		] );
		$this->add_control( 'jprm_curr_position', [
			'label'   => __( 'Position', 'jellopoint-restaurant-menu' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'before',
			'options' => [
				'before' => __( 'Before amount', 'jellopoint-restaurant-menu' ),
				'after'  => __( 'After amount', 'jellopoint-restaurant-menu' ),
			],
		] );
		$this->add_control( 'jprm_curr_spacing', [
			'label'   => __( 'Spacing', 'jellopoint-restaurant-menu' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'thin',
			'options' => [
				'none'   => __( 'No space', 'jellopoint-restaurant-menu' ),
				'thin'   => __( 'Thin space', 'jellopoint-restaurant-menu' ),
				'normal' => __( 'Non-breaking space', 'jellopoint-restaurant-menu' ),
			],
		] );

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

		/* --- Badges (empty for now) -------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_badges',
			[ 'label' => __( 'Badges', 'jellopoint-restaurant-menu' ) ]
		);
		// (Intentionally empty – placeholder for future controls)
		$this->end_controls_section();

		/* --- Layout ------------------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_layout',
			[ 'label' => __( 'Layout', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'layout_columns', [
			'label'   => __( 'Columns (Desktop)', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '1',
			'options' => [
				'1' => __( '1 column', 'jellopoint-restaurant-menu' ),
				'2' => __( '2 columns', 'jellopoint-restaurant-menu' ),
				'3' => __( '3 columns', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'layout_split_mode', [
			'label'   => __( 'Split mode (Desktop 2 cols)', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'auto',
			'options' => [
				'auto'   => __( 'Auto (balance by items, keep whole sections)', 'jellopoint-restaurant-menu' ),
				'manual' => __( 'Manual (split after section)', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [
				'data_mode'      => 'dynamic',
				'layout_columns' => '2',
			],
		] );

		$this->add_control( 'layout_split_after_section', [
			'label'     => __( 'Split after section', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [
				'data_mode'        => 'dynamic',
				'layout_columns'   => '2',
				'layout_split_mode'=> 'manual',
			],
			'description' => __( 'If the chosen section is not present in the current result, auto-balance is used.', 'jellopoint-restaurant-menu' ),
		] );

		$this->add_control( 'layout_column_gap', [
			'label'   => __( 'Column gap', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 48 ] ],
			'default' => [ 'size' => 24 ],
			'selectors' => [
				'{{WRAPPER}} .jp-menu-grid' => 'gap: {{SIZE}}{{UNIT}};',
			],
			'condition' => [
				'data_mode'      => 'dynamic',
				'layout_columns' => [ '2', '3' ],
			],
		] );

		// ----- Responsive controls -----
		$this->add_control( 'layout_resp_heading', [
			'label'     => __( 'Responsive', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_control( 'layout_cols_tablet', [
			'label'   => __( 'Columns (Tablet)', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '2',
			'options' => [
				'1' => '1',
				'2' => '2',
				'3' => '3',
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'layout_cols_mobile', [
			'label'   => __( 'Columns (Mobile)', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '1',
			'options' => [
				'1' => '1',
				'2' => '2',
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'layout_bp_tablet', [
			'label'       => __( 'Tablet breakpoint (max-width, px)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'default'     => 992,
			'min'         => 480,
			'step'        => 1,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'layout_bp_mobile', [
			'label'       => __( 'Mobile breakpoint (max-width, px)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::NUMBER,
			'default'     => 768,
			'min'         => 320,
			'step'        => 1,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->end_controls_section();
	}

	/* =========================
	 * Render
	 * ========================= */
	public function render() {
		self::require_price_partial_once();

		$s = $this->get_settings_for_display();
		$mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;

		// Static mode – leave as-is for now (columns ignored)
		if ( 'static' === $mode || ( null === $mode && ! empty( $s['items'] ) ) ) {
			$this->render_static_list( is_array( $s['items'] ) ? $s['items'] : [] );
			return;
		}

		$show_all           = ( isset( $s['show_all_when_empty'] ) && 'yes' === $s['show_all_when_empty'] );
		$menu_sel           = $s['menus'] ?? '';
		$sections_sel       = $s['sections'] ?? [];
		$orderby            = isset( $s['query_orderby'] ) ? (string) $s['query_orderby'] : 'menu_order';
		$order              = isset( $s['query_order'] ) ? (string) $s['query_order'] : 'ASC';
		$limit              = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;
		$label_presentation = isset( $s['label_presentation'] ) ? (string) $s['label_presentation'] : 'icon_text';
		$label_position     = isset( $s['label_position'] ) ? (string) $s['label_position'] : 'right';

		// Currency options
		$currency_opts = [
			'show'     => ( isset( $s['jprm_curr_show'] ) && $s['jprm_curr_show'] === 'yes' ),
			'symbol'   => (string) ( $s['jprm_curr_symbol']   ?? '€' ),
			'position' => (string) ( $s['jprm_curr_position'] ?? 'before' ),
			'spacing'  => (string) ( $s['jprm_curr_spacing']  ?? 'thin' ),
		];

		// Layout options (dynamic only)
		$columns       = isset( $s['layout_columns'] ) ? (string) $s['layout_columns'] : '1';
		$split_mode    = isset( $s['layout_split_mode'] ) ? (string) $s['layout_split_mode'] : 'auto';
		$split_after   = isset( $s['layout_split_after_section'] ) ? (string) $s['layout_split_after_section'] : '';

		// Responsive options
		$cols_tablet = isset( $s['layout_cols_tablet'] ) ? (string) $s['layout_cols_tablet'] : '2';
		$cols_mobile = isset( $s['layout_cols_mobile'] ) ? (string) $s['layout_cols_mobile'] : '1';
		$bp_tablet   = isset( $s['layout_bp_tablet'] ) && is_numeric( $s['layout_bp_tablet'] ) ? (int) $s['layout_bp_tablet'] : 992;
		$bp_mobile   = isset( $s['layout_bp_mobile'] ) && is_numeric( $s['layout_bp_mobile'] ) ? (int) $s['layout_bp_mobile'] : 768;

		$menu_ids    = $this->.normalize_to_ids( $menu_sel );
		$section_ids = $this->normalize_to_ids( $sections_sel );

		if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$items = $this->query_items( $menu_ids, $section_ids, $orderby, $order, $limit, $show_all );
		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		// Preload label map for perf (partial can also build it if unavailable)
		$label_map = function_exists( 'jprm_build_label_map' ) ? jprm_build_label_map() : null;

		// Group by primary section (first jprm_section term)
		$sections_order = [];
		$sections_data  = []; // term_id => ['term'=>WP_Term|null, 'items'=>[]]
		foreach ( $items as $post ) {
			$post_id = (int) $post->ID;
			$cfg     = function_exists( 'jprm_read_price_config' ) ? jprm_read_price_config( $post_id ) : [];
			if ( empty( $cfg ) ) { continue; }

			$terms = wp_get_post_terms( $post_id, 'jprm_section', [ 'orderby' => 'name', 'order' => 'ASC' ] );
			$primary_tid  = 0;
			$primary_term = null;
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$primary_term = $terms[0];
				$primary_tid  = (int) $primary_term->term_id;
			}
			if ( ! isset( $sections_data[ $primary_tid ] ) ) {
				$sections_data[ $primary_tid ] = [ 'term' => $primary_term, 'items' => [] ];
				$sections_order[] = $primary_tid;
			}
			$sections_data[ $primary_tid ]['items'][] = $post;
		}

		$show_section_name = ( isset( $s['show_section_name'] ) && $s['show_section_name'] === 'yes' );
		$show_section_desc = ( isset( $s['show_section_description'] ) && $s['show_section_description'] === 'yes' );

		/* ===== 1 column: with section headers ===== */
		if ( $columns === '1' ) {
			echo '<ul class="jp-menu">';
			foreach ( $sections_order as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
					if ( $show_section_desc && ! empty( $term->description ) ) {
						echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
					}
					echo '</li>';
				}

				foreach ( $blk['items'] as $post ) {
					$post_id = (int) $post->ID;
					$title   = get_the_title( $post_id );
					$desc    = get_post_meta( $post_id, 'jprm_desc', true );

					echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';

					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}
			}
			echo '</ul>';
			return;
		}

		/* ===== Helper to print responsive inline CSS for this instance ===== */
		$widget_id = 'jprm-' . esc_attr( $this->get_id() );
		$gap_px    = 24;
		if ( isset( $s['layout_column_gap']['size'] ) && is_numeric( $s['layout_column_gap']['size'] ) ) {
			$gap_px = (int) $s['layout_column_gap']['size'];
		}
		$cols_desktop = (int) $columns;
		$cols_tab     = max( 1, min( 3, (int) $cols_tablet ) );
		$cols_mob     = max( 1, min( 2, (int) $cols_mobile ) );

		$inline_css = '<style id="'. $widget_id .'-grid-css">#'. $widget_id .' .jp-menu-grid{display:grid;grid-template-columns:repeat('. $cols_desktop .',1fr);gap:'. $gap_px .'px;}';
		$inline_css .= '@media(max-width:'. $bp_tablet .'px){#'. $widget_id .' .jp-menu-grid{grid-template-columns:repeat('. $cols_tab .',1fr);}}';
		$inline_css .= '@media(max-width:'. $bp_mobile .'px){#'. $widget_id .' .jp-menu-grid{grid-template-columns:repeat('. $cols_mob .',1fr);}}';
		$inline_css .= '</style>';

		/* ===== 2 columns: split on section boundaries (auto/manual) ===== */
		if ( $columns === '2' ) {
			// Determine split index
			$split_index = null;
			if ( $split_mode === 'manual' && $split_after !== '' ) {
				$target = (int) $split_after;
				foreach ( $sections_order as $idx => $tid ) {
					if ( $tid === $target ) { $split_index = $idx; break; }
				}
			}
			if ( $split_index === null ) {
				$total = 0;
				foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }
				$half = (int) ceil( $total / 2 );
				$acc  = 0;
				foreach ( $sections_order as $idx => $tid ) {
					$acc += count( $sections_data[ $tid ]['items'] );
					if ( $acc >= $half ) { $split_index = $idx; break; }
				}
				if ( $split_index === null ) $split_index = count( $sections_order ) - 1;
			}

			$left_sections  = array_slice( $sections_order, 0, $split_index + 1 );
			$right_sections = array_slice( $sections_order, $split_index + 1 );

			// If right column empty → render single column (headers on)
			if ( empty( $right_sections ) ) {
				echo '<ul class="jp-menu">';
				foreach ( $left_sections as $tid ) {
					$blk  = $sections_data[ $tid ];
					$term = $blk['term'];
					if ( $term && $show_section_name ) {
						echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
						if ( $show_section_desc && ! empty( $term->description ) ) {
							echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
						}
						echo '</li>';
					}
					foreach ( $blk['items'] as $post ) {
						$post_id = (int) $post->ID;
						$title   = get_the_title( $post_id );
						$desc    = get_post_meta( $post_id, 'jprm_desc', true );
						echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
						echo '  <div class="jp-menu__content">';
						if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
						if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
						echo '  </div>';
						if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
							echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
						} else {
							echo '<div class="jp-menu__pricegroup"></div>';
						}
						echo '</div></li>';
					}
				}
				echo '</ul>';
				return;
			}

			// Render grid
			echo $inline_css; // responsive columns CSS (scoped)
			echo '<div id="'. $widget_id .'" class="jp-menu-grid jp-cols-2 jp-menu--cols-2 jp-two-cols">';

			// LEFT
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--left">';
			foreach ( $left_sections as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];
				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
					if ( $show_section_desc && ! empty( $term->description ) ) {
						echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
					}
					echo '</li>';
				}
				foreach ( $blk['items'] as $post ) {
					$post_id = (int) $post->ID;
					$title   = get_the_title( $post_id );
					$desc    = get_post_meta( $post_id, 'jprm_desc', true );
					echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}
			}
			echo '</ul></div>';

			// RIGHT
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--right">';
			foreach ( $right_sections as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];
				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
					if ( $show_section_desc && ! empty( $term->description ) ) {
						echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
					}
					echo '</li>';
				}
				foreach ( $blk['items'] as $post ) {
					$post_id = (int) $post->ID;
					$title   = get_the_title( $post_id );
					$desc    = get_post_meta( $post_id, 'jprm_desc', true );
					echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}
			}
			echo '</ul></div>';

			echo '</div>'; // .jp-menu-grid
			return;
		}

		/* ===== 3 columns: auto-balance by items, keep section boundaries ===== */
		// Compute targets at ~1/3 and ~2/3 of items
		$total = 0;
		foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }
		$t1 = (int) ceil( $total / 3 );        // first break
		$t2 = (int) ceil( (2 * $total) / 3 );  // second break

		$i1 = null; $i2 = null; $acc = 0;
		foreach ( $sections_order as $idx => $tid ) {
			$acc += count( $sections_data[ $tid ]['items'] );
			if ( $i1 === null && $acc >= $t1 ) { $i1 = $idx; }
			if ( $i2 === null && $acc >= $t2 ) { $i2 = $idx; break; }
		}
		if ( $i1 === null ) $i1 = min( 0, count( $sections_order ) - 1 );
		if ( $i2 === null ) $i2 = max( $i1, count( $sections_order ) - 1 );

		$col1 = array_slice( $sections_order, 0, $i1 + 1 );
		$col2 = array_slice( $sections_order, $i1 + 1, $i2 - $i1 );
		$col3 = array_slice( $sections_order, $i2 + 1 );

		// Render grid (fallbacks handled naturally by CSS if columns are empty)
		echo $inline_css; // responsive columns CSS (scoped)
		echo '<div id="'. $widget_id .'" class="jp-menu-grid jp-cols-3 jp-menu--cols-3 jp-three-cols">';

		$cols = [ $col1, $col2, $col3 ];
		$pos  = [ 'left', 'middle', 'right' ];
		foreach ( $cols as $i => $section_ids_chunk ) {
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--' . esc_attr( $pos[$i] ) . '">';
			foreach ( $section_ids_chunk as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];
				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
					if ( $show_section_desc && ! empty( $term->description ) ) {
						echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
					}
					echo '</li>';
				}
				foreach ( $blk['items'] as $post ) {
					$post_id = (int) $post->ID;
					$title   = get_the_title( $post_id );
					$desc    = get_post_meta( $post_id, 'jprm_desc', true );
					echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}
			}
			echo '</ul></div>';
		}

		echo '</div>'; // .jp-menu-grid
	}

	/* =========================
	 * Data helpers
	 * ========================= */
	protected function normalize_to_ids( $input ) : array {
		if ( $input === '' || $input === null ) return [];
		$vals = is_array( $input ) ? $input : [ $input ];
		$out  = [];
		foreach ( $vals as $v ) {
			if ( $v === '' || $v === null ) continue;
			$out[] = (int) $v;
		}
		return array_values( array_unique( array_filter( $out, fn( $n ) => $n > 0 ) ) );
	}

	protected function query_items( array $menu_ids, array $section_ids, string $orderby, string $order, int $limit, bool $fallback_all ) : array {
		$args = [
			'post_type'        => 'jprm_menu_item',
			'post_status'      => 'publish',
			'orderby'          => in_array( $orderby, [ 'menu_order','title','date' ], true ) ? $orderby : 'menu_order',
			'order'            => ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC',
			'posts_per_page'   => ( $limit > 0 ) ? $limit : -1,
			'suppress_filters' => false,
		];

		$tax_query = [];
		if ( ! empty( $menu_ids ) )    $tax_query[] = [ 'taxonomy' => 'jprm_menu',    'field' => 'term_id', 'terms' => $menu_ids ];
		if ( ! empty( $section_ids ) ) $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'term_id', 'terms' => $section_ids ];
		if ( ! empty( $tax_query ) )   $args['tax_query'] = $tax_query;
		elseif ( ! $fallback_all )     return [];

		$q = new \WP_Query( $args );
		return is_array( $q->posts ?? null ) ? $q->posts : [];
	}

	/* =========================
	 * Static renderer (unchanged) 
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
}
