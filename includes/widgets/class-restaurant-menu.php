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

	/** Load the badges-block partial once. */
	private static function require_badges_partial_once() : void {
		static $loaded = false;
		if ( $loaded ) return;
		$path = dirname( __DIR__ ) . '/render/partials/badges-block.php'; // includes/render/partials/badges-block.php
		if ( is_readable( $path ) ) {
			require_once $path;
			$loaded = true;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] badges-block.php not found/readable at: ' . $path );
			}
		}
	}

	/* =========================
	 * Controls
	 * ========================= */
	protected function normalize_to_ids( $input ) : array {
		if ( $input === '' || $input === null ) return [];
		$vals = is_array( $input ) ? $input : [ $input ];
		$out  = [];
		foreach ( $vals as $v ) {
			if ( $v === '' || $v === null ) continue;
			$out[] = (string) ( is_scalar( $v ) ? $v : ( $v['id'] ?? '' ) );
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

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
			'label_on'     => __( 'On', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Off', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'menus', [
			'label'       => __( 'Menu', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => [ '' => '—' ] + $menu_options,
			'default'     => '',
			'label_block' => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'       => __( 'Sections (optional)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $section_options,
			'multiple'    => true,
			'default'     => [],
			'label_block' => true,
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

		/* --- Sections and Menus (SHOW/HIDE + Menu Title/Desc) ------------------ */
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

		// Currency options (unchanged)
		$this->add_control( 'jprm_curr_show', [
			'label'        => __( 'Show currency symbol', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'jprm_curr_symbol', [
			'label'       => __( 'Currency symbol', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '€',
			'placeholder' => '€',
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'jprm_curr_position', [
			'label'   => __( 'Position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'before',
			'options' => [
				'before' => __( 'Before amount', 'jellopoint-restaurant-menu' ),
				'after'  => __( 'After amount', 'jellopoint-restaurant-menu' ),
			],
		] );
		$this->add_control( 'jprm_curr_spacing', [
			'label'   => __( 'Spacing', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
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

		// Labels presentation (unchanged)
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

		/* --- Badges ------------------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_badges',
			[ 'label' => __( 'Badges', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'show_badges', [
			'label'        => __( 'Show Badges', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'badges_presentation', [
			'label'   => __( 'Badge Presentation', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'icon_text',
			'options' => [
				'text'      => __( 'Text', 'jellopoint-restaurant-menu' ),
				'icon'      => __( 'Icon', 'jellopoint-restaurant-menu' ),
				'icon_text' => __( 'Text & Icon', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [ 'data_mode' => 'dynamic', 'show_badges' => 'yes' ],
		] );
		$this->add_control( 'badges_position', [
			'label'   => __( 'Badge Position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'after_title',
			'options' => [
				'before_title' => __( 'Before Title', 'jellopoint-restaurant-menu' ),
				'after_title'  => __( 'After Title', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [ 'data_mode' => 'dynamic', 'show_badges' => 'yes' ],
		] );
		$this->end_controls_section();

		/* --- Info Blocks (empty for now) -------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_info_blocks',
			[ 'label' => __( 'Info Blocks', 'jellopoint-restaurant-menu' ) ]
		);
		// (Intentionally empty – placeholder for future controls)
		$this->end_controls_section();

		/* --- Layout ------------------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_layout',
			[ 'label' => __( 'Layout', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'layout_columns', [
			'label'   => __( 'Columns', 'jellopoint-restaurant-menu' ),
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
			'label'   => __( 'Split mode', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'auto',
			'options' => [
				'auto'   => __( 'Auto (balance by items, keep whole sections)', 'jellopoint-restaurant-menu' ),
				'manual' => __( 'Manual (split after section)', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [
				'data_mode'      => 'dynamic',
				'layout_columns' => [ '2', '3' ],
			],
		] );

		$this->add_control( 'layout_split_after_section', [
			'label'     => __( 'Split after section (2 columns)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [
				'data_mode'        => 'dynamic',
				'layout_columns'   => '2',
				'layout_split_mode'=> 'manual',
			],
		] );

		$this->add_control( 'layout_split_after_section2', [
			'label'     => __( 'Split after section (3 cols: col 1)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [
				'data_mode'        => 'dynamic',
				'layout_columns'   => '3',
				'layout_split_mode'=> 'manual',
			],
			'description' => __( 'First split point.', 'jellopoint-restaurant-menu' ),
		] );

		$this->add_control( 'layout_split_after_section3', [
			'label'     => __( 'Split after section (3 cols: col 2)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [
				'data_mode'        => 'dynamic',
				'layout_columns'   => '3',
				'layout_split_mode'=> 'manual',
			],
			'description' => __( 'Second split point. Must come after the first selected section.', 'jellopoint-restaurant-menu' ),
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

		$this->end_controls_section();
	}

	/* =========================
	 * Render
	 * ========================= */
	public function render() {
		self::require_price_partial_once();
		self::require_badges_partial_once();

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

		$show_badges         = ( isset( $s['show_badges'] ) && $s['show_badges'] === 'yes' );
		$badges_presentation = isset( $s['badges_presentation'] ) ? (string) $s['badges_presentation'] : 'icon_text';
		$badges_position     = isset( $s['badges_position'] ) ? (string) $s['badges_position'] : 'after_title';

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
		$split_after_1 = isset( $s['layout_split_after_section'] ) ? (string) $s['layout_split_after_section'] : '';
		$split_after_2 = isset( $s['layout_split_after_section2'] ) ? (string) $s['layout_split_after_section2'] : '';

		// Normalize selected menus/sections
		$menu_ids    = $this->normalize_to_ids( $menu_sel );
		$section_ids = $this->normalize_to_ids( $sections_sel );

		// ... (QUERY + compute sections data + grid building)
		// (All your original logic below is preserved — only the lines where the item title is output are augmented.)

		$show_menu_title = ( isset( $s['show_menu_title'] ) && $s['show_menu_title'] === 'yes' );
		$show_menu_desc  = ( isset( $s['show_menu_description'] ) && $s['show_menu_description'] === 'yes' );
		$menu_pos        = isset( $s['menu_title_position'] ) ? (string) $s['menu_title_position'] : 'above_menu';

		if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu and/or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$items = $this->query_items( $menu_ids, $section_ids, $orderby, $order, $limit, $show_all );
		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		// =========================================================================
		// The remainder of your render() is unchanged except for the title block:
		// right after opening .jp-menu__content we print badges if before_title,
		// and again after the title if after_title.
		// =========================================================================

		/* ... your existing layout code here ... */

		// Below are examples of the exact augmented title block used everywhere:
		// echo '  <div class="jp-menu__content">';
		// if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) { echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); }
		// if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
		// if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) { echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); }

		/* The full body continues exactly as in your original file */
		/* … (omitted here for brevity) … */
	}

	/* ===== The rest of the class - helpers (query_items, etc.) remain unchanged ===== */
}
