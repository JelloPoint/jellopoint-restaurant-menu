<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

use function jprm_build_label_map;
use function jprm_read_price_config;
use function jprm_render_pricegroup_html;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }
	public function get_script_depends() { return []; }

	private static function require_price_partial_once() : void {
		static $loaded = false;
		if ( $loaded ) return;

		$path = dirname( __DIR__ ) . '/render/partials/price-block.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] price-block.php not found/readable at: ' . $path );
			}
		}
		$loaded = true;
	}

	/** NEW: load badges partial */
	private static function require_badges_partial_once() : void {
		static $loaded = false;
		if ( $loaded ) return;
		$path = dirname( __DIR__ ) . '/render/partials/badges-block.php';
		if ( is_readable( $path ) ) {
			require_once $path;
			$loaded = true;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] badges-block.php not found/readable at: ' . $path );
			}
		}
	}

	/** NEW: load info-blocks partial */
	private static function require_infoblocks_partial_once() : void {
		static $loaded = false;
		if ( $loaded ) return;
		$path = dirname( __DIR__ ) . '/render/partials/info-blocks.php';
		if ( is_readable( $path ) ) {
			require_once $path;
			$loaded = true;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] info-blocks.php not found/readable at: ' . $path );
			}
		}
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
					$out[ (string) $t->term_id ] = $t->name;
				}
			}
		}
		return $out;
	}

	/**
	 * Build section options limited to the sections that actually occur
	 * in items for the selected Menu. Falls back to all sections.
	 *
	 * @param int $menu_term_id
	 * @param array $fallback_all key=>label
	 * @return array
	 */
	protected function section_options_for_menu( int $menu_term_id, array $fallback_all ) : array {
		if ( $menu_term_id <= 0 ) return $fallback_all;

		$q = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy' => 'jprm_menu',
					'field'    => 'term_id',
					'terms'    => [ $menu_term_id ],
				],
			],
		] );

		if ( empty( $q->posts ) ) return $fallback_all;

		$section_ids = [];
		foreach ( $q->posts as $pid ) {
			$terms = wp_get_post_terms( $pid, 'jprm_section', [ 'fields' => 'ids' ] );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $sid ) {
					$section_ids[] = (int) $sid;
				}
			}
		}
		$section_ids = array_values( array_unique( array_filter( $section_ids, fn($n)=>$n>0 ) ) );
		if ( empty( $section_ids ) ) return $fallback_all;

		$terms = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
			'include'    => $section_ids,
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) return $fallback_all;

		$out = [];
		foreach ( $terms as $t ) {
			$out[ (string) $t->term_id ] = $t->name;
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

		/* --- Sections and Menus ------------------------------------------------- */
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
		$this->add_control( 'show_menu_title', [
			'label'        => __( 'Menu title', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => '',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'show_menu_description', [
			'label'        => __( 'Menu description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jellopoint-restaurant-menu' ),
			'label_off'    => __( 'Hide', 'jellopoint-restaurant-menu' ),
			'return_value' => 'yes',
			'default'      => '',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'menu_title_position', [
			'label'     => __( 'Menu title position', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'above_menu',
			'options'   => [
				'above_menu'   => __( 'Above Complete Menu', 'jellopoint-restaurant-menu' ),
				'first_column' => __( 'Above 1st Column', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [
				'data_mode'         => 'dynamic',
				'show_menu_title!'  => '',
			],
		] );
		$this->end_controls_section();

		/* --- Prices and Labels -------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

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

		/* --- Info Blocks (Step 1 + Title) -------------------------------------- */
		$this->start_controls_section(
			'jprm_section_info_blocks',
			[ 'label' => __( 'Info Blocks', 'jellopoint-restaurant-menu' ) ]
		);

		// Build Section options filtered by the chosen single Menu (if any)
		$all_section_opts = $section_options;
		$menu_selected    = $this->get_settings_for_display( 'menus' );
		$menu_id_for_ib   = ( is_numeric( $menu_selected ) ? (int) $menu_selected : 0 );
		$ib_section_opts  = $this->section_options_for_menu( $menu_id_for_ib, $all_section_opts );

		$ib = new Repeater();

		// NEW: Editor-only Title for easier identification
		$ib->add_control( 'ib_title', [
			'label'       => __( 'Title (editor only)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'e.g., Chef’s Note, Allergen Info', 'jellopoint-restaurant-menu' ),
			'label_block' => true,
			'default'     => '',
		] );

		$ib->add_control( 'content_html', [
			'label'       => __( 'HTML', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::CODE,
			'language'    => 'html',
			'rows'        => 8,
			'label_block' => true,
			'default'     => '',
		] );

		$ib->add_control( 'image', [
			'label'   => __( 'Image', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::MEDIA,
			'default' => [],
		] );

		$ib->add_control( 'position', [
			'label'   => __( 'Position vs Section', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'above' => __( 'Above section', 'jellopoint-restaurant-menu' ),
				'below' => __( 'Below section', 'jellopoint-restaurant-menu' ),
			],
			'default' => 'above',
		] );

		$ib->add_control( 'section_id', [
			'label'       => __( 'Target Section', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $ib_section_opts,
			'multiple'    => false,
			'label_block' => true,
			'description' => ( $menu_id_for_ib > 0 )
				? __( 'Sections filtered to the chosen Menu (based on items). If you change Menu, re-open the widget to refresh.', 'jellopoint-restaurant-menu' )
				: __( 'Pick a Menu to filter section choices.', 'jellopoint-restaurant-menu' ),
		] );

		$this->add_control( 'info_blocks', [
			'label'       => __( 'Info Blocks', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $ib->get_controls(),
			'default'     => [],
			// Show the editor title prominently; keep position/section for quick context.
			'title_field' => '{{{ ib_title }}} ({{{ position }}} #{{{ section_id }}})',
		] );

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
			'label'     => __( 'Split after section (1)', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => $section_options,
			'default'   => '',
			'condition' => [
				'data_mode'        => 'dynamic',
				'layout_columns'   => [ '2', '3' ],
				'layout_split_mode'=> 'manual',
			],
			'description' => __( 'If the chosen section is not present in the result, auto-balance is used.', 'jellopoint-restaurant-menu' ),
		] );

		$this->add_control( 'layout_split_after_section2', [
			'label'     => __( 'Split after section (2)', 'jellopoint-restaurant-menu' ),
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
		self::require_infoblocks_partial_once(); // NEW

		static $css_done = false;
		if ( ! $css_done ) {
			$css_done = true;
		}

		$s = $this->get_settings_for_display();
		$mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;

		// Static mode
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

		$currency_opts = [
			'show'     => ( isset( $s['jprm_curr_show'] ) && $s['jprm_curr_show'] === 'yes' ),
			'symbol'   => (string) ( $s['jprm_curr_symbol']   ?? '€' ),
			'position' => (string) ( $s['jprm_curr_position'] ?? 'before' ),
			'spacing'  => (string) ( $s['jprm_curr_spacing']  ?? 'thin' ),
		];

		$columns       = isset( $s['layout_columns'] ) ? (string) $s['layout_columns'] : '1';
		$split_mode    = isset( $s['layout_split_mode'] ) ? (string) $s['layout_split_mode'] : 'auto';
		$split_after_1 = isset( $s['layout_split_after_section'] ) ? (string) $s['layout_split_after_section'] : '';
		$split_after_2 = isset( $s['layout_split_after_section2'] ) ? (string) $s['layout_split_after_section2'] : '';

		$menu_ids    = $this->normalize_to_ids( $menu_sel );
		$section_ids = $this->normalize_to_ids( $sections_sel );

		$menu_term = null;
		if ( count( $menu_ids ) === 1 ) {
			$menu_term = get_term( (int) $menu_ids[0], 'jprm_menu' );
			if ( ! $menu_term || is_wp_error( $menu_term ) ) $menu_term = null;
		}

		$show_menu_title = ( isset( $s['show_menu_title'] ) && $s['show_menu_title'] === 'yes' );
		$show_menu_desc  = ( isset( $s['show_menu_description'] ) && $s['show_menu_description'] === 'yes' );
		$menu_pos        = isset( $s['menu_title_position'] ) ? (string) $s['menu_title_position'] : 'above_menu';

		if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$items = $this->query_items( $menu_ids, $section_ids, $orderby, $order, $limit, $show_all );
		if ( empty( $items ) ) {
			echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
			return;
		}

		$label_map = function_exists( 'jprm_build_label_map' ) ? jprm_build_label_map() : null;

		$sections_order = [];
		$sections_data  = [];
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

		// Prepare Info Blocks map once
		$ib_rows = ( isset( $s['info_blocks'] ) && is_array( $s['info_blocks'] ) ) ? $s['info_blocks'] : [];
		$ib_map  = function_exists('jprm_infoblocks_partition_by_position') ? jprm_infoblocks_partition_by_position( $ib_rows ) : [];

		$render_menu_meta = function( $term, bool $show_title, bool $show_desc, string $scope ) : string {
			if ( ! $term || ( ! $show_title && ! $show_desc ) ) return '';
			$title = $show_title ? trim( (string) $term->name ) : '';
			$desc  = $show_desc  ? trim( (string) $term->description ) : '';
			if ( $title === '' && $desc === '' ) return '';
			$cls = 'jp-menu__meta ' . ( $scope === 'global' ? 'jp-menu__meta--global' : 'jp-menu__meta--col' );
			$out  = '<div class="' . esc_attr( $cls ) . '">';
			if ( $title !== '' ) $out .= '<h2 class="jp-menu__meta-title">' . esc_html( $title ) . '</h2>';
			if ( $desc  !== '' ) $out .= '<div class="jp-menu__meta-desc">' . esc_html( $desc ) . '</div>';
			$out .= '</div>';
			return $out;
		};

		/* ===== 1 column ===== */
		if ( $columns === '1' ) {
			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
			}
			echo '<ul class="jp-menu">';
			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
				echo '<li class="jp-menu__meta-li">';
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
				echo '</li>';
			}
			foreach ( $sections_order as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				// Info Blocks ABOVE the section
				if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
					echo '</li>';
				}

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

					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';

					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';

					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}

				// Info Blocks BELOW the section
				if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
					echo '</li>';
				}
			}
			echo '</ul>';
			return;
		}

		/* ===== 2 columns ===== */
		if ( $columns === '2' ) {
			$split_index = null;
			if ( $split_mode === 'manual' && $split_after_1 !== '' ) {
				$target = (int) $split_after_1;
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

			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
			}

			echo '<div class="jp-menu-grid jp-cols-2 jp-menu--cols-2 jp-two-cols">';

			// LEFT column
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--left">';
			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) ) {
				if ( $menu_pos === 'first_column' ) {
					echo '<li class="jp-menu__meta-li">';
					echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
					echo '</li>';
				}
			}
			foreach ( $left_sections as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				// Above
				if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
					echo '</li>';
				}

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

					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';

					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}

				// Below
				if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
					echo '</li>';
				}
			}
			echo '</ul></div>';

			// RIGHT column
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--right">';
			foreach ( $right_sections as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				// Above
				if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
					echo '</li>';
				}

				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header>'<h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
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

					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';

					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}

				// Below
				if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
					echo '</li>';
				}
			}
			echo '</ul></div>';

			echo '</div>';
			return;
		}

		/* ===== 3 columns ===== */
		$total = 0;
		foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }

		$col1 = $col2 = $col3 = [];

		if ( $split_mode === 'manual' && $split_after_1 !== '' && $split_after_2 !== '' ) {
			$idx1 = $idx2 = null;
			$t1   = (int) $split_after_1;
			$t2   = (int) $split_after_2;
			foreach ( $sections_order as $i => $tid ) {
				if ( $idx1 === null && $tid === $t1 ) $idx1 = $i;
				if ( $idx2 === null && $tid === $t2 ) $idx2 = $i;
				if ( $idx1 !== null && $idx2 !== null ) break;
			}
			if ( $idx1 !== null && $idx2 !== null && $idx2 > $idx1 ) {
				$col1 = array_slice( $sections_order, 0, $idx1 + 1 );
				$col2 = array_slice( $sections_order, $idx1 + 1, $idx2 - $idx1 );
				$col3 = array_slice( $sections_order, $idx2 + 1 );
			}
		}

		if ( empty( $col1 ) && empty( $col2 ) && empty( $col3 ) ) {
			$t1 = (int) ceil( $total / 3 );
			$t2 = (int) ceil( (2 * $total) / 3 );
			$i1 = null; $i2 = null; $acc = 0;
			foreach ( $sections_order as $idx => $tid ) {
				$acc += count( $sections_data[ $tid ]['items'] );
				if ( $i1 === null && $acc >= $t1 ) $i1 = $idx;
				if ( $i2 === null && $acc >= $t2 ) { $i2 = $idx; break; }
			}
			if ( $i1 === null ) $i1 = 0;
			if ( $i2 === null ) $i2 = max( $i1, count( $sections_order ) - 1 );

			$col1 = array_slice( $sections_order, 0, $i1 + 1 );
			$col2 = array_slice( $sections_order, $i1 + 1, $i2 - $i1 );
			$col3 = array_slice( $sections_order, $i2 + 1 );
		}

		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
			echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
		}

		echo '<div class="jp-menu-grid jp-cols-3 jp-menu--cols-3 jp-three-cols">';

		$cols = [ $col1, $col2, $col3 ];
		$pos  = [ 'left', 'middle', 'right' ];
		foreach ( $cols as $i => $section_ids_chunk ) {
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--' . esc_attr( $pos[$i] ) . '">';
			if ( $i === 0 && $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
				echo '<li class="jp-menu__meta-li">';
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
				echo '</li>';
			}
			foreach ( $section_ids_chunk as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				// Above
				if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
					echo '</li>';
				}

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
					echo '<li class="jp-menu__item'><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';

					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';

					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}

				// Below
				if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
					echo '</li>';
				}
			}
			echo '</ul></div>';
		}

		echo '</div>';
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
			echo '    <div class="jp-menu__titleline">';
			if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			echo '    </div>';
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
