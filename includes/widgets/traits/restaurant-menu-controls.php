<?php
namespace JelloPoint\RestaurantMenu\Widgets\Traits;

use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Restaurant_Menu_Controls {

	/* ========= Helpers (used by controls) ========= */
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
	 * Limit Section dropdown to Sections that occur in items for the chosen Menu (falls back to all).
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
				foreach ( $terms as $sid ) { $section_ids[] = (int) $sid; }
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
		foreach ( $terms as $t ) { $out[ (string) $t->term_id ] = $t->name; }
		return $out;
	}

	/* ========= Controls ========= */
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
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'show_section_description', [
			'label'        => __( 'Show section description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'show_menu_title', [
			'label'        => __( 'Menu title', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'show_menu_description', [
			'label'        => __( 'Menu description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
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
		// === Main Section Visibility (inside "Sections and Menus") ===
		$this->add_control( 'show_main_sections', [
			'label'        => __( 'Show Main Section Headings', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'description'  => __( 'Render level-0 (top-level) section titles like "Drinks".', 'jellopoint-restaurant-menu' ),
		] );

		$this->add_control( 'show_main_even_if_empty', [
			'label'        => __( 'Show Even If Empty (No Items)', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'condition'    => [ 'show_main_sections' => 'yes' ],
			'description'  => __( 'If off, main titles show only when the section has items or child sections.', 'jellopoint-restaurant-menu' ),
		] );

		$this->end_controls_section();

		/* --- Prices and Labels -------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_prices_labels',
			[ 'label' => __( 'Prices and Labels', 'jellopoint-restaurant-menu' ) ]
		);

		$this->add_control( 'jprm_curr_heading', [
			'label'     => __( 'Currency', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::HEADING,
			'separator' => 'before',
		] );
		$this->add_control( 'jprm_curr_show', [
			'label'        => __( 'Show currency symbol', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );
		$this->add_control( 'jprm_curr_symbol', [
			'label'       => __( 'Currency symbol', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '€',
			'default'     => '€',
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
		
		// Label Position (affects Inline-Below chipline order)
		$this->add_control( 'label_position', [
    		'label'        => __( 'Label Position', 'jellopoint-restaurant-menu' ),
    		'type'         => \Elementor\Controls_Manager::SELECT,
    		'default'      => 'right',
    		'options'      => [
        		'left'  => __( 'Left of price', 'jellopoint-restaurant-menu' ),
        		'right' => __( 'Right of price', 'jellopoint-restaurant-menu' ),
    		],
    		// This adds a class on the widget wrapper: jprm-labelpos-left|right
    		'prefix_class' => 'jprm-labelpos-',
		] );

		$this->end_controls_section();

		/* --- Labels Layout ------------------------------------------------------ */
		$this->start_controls_section(
			'jprm_section_labels_layout',
			[ 'label' => __( 'Labels Layout', 'jellopoint-restaurant-menu' ) ]
		);

		/* Global layout */
		$this->add_control( 'labels_layout', [
			'label'   => __( 'Default layout', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'inline',
			'options' => [
				'inline'       => __( 'Inline',  'jellopoint-restaurant-menu' ),
				'inline_below' => __( 'Inline Below',  'jellopoint-restaurant-menu' ),
				'matrix'       => __( 'Matrix',  'jellopoint-restaurant-menu' ),
			],
		] );

		// Toggle: show a separator (Inline Below only)
		$this->add_control( 'inline_below_sep_enable', [
			'label'        => __( 'Show Separator (Inline Below)', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'on',              // wrapper class becomes .jprm-sep--on
			'default'      => '',
			'condition'    => [ 'labels_layout' => 'inline_below' ],
			'prefix_class' => 'jprm-sep--',
			'render_type'  => 'template',
		]);

		// Content
		$this->add_control( 'inline_below_sep_content', [
			'label'       => __( 'Separator Content', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '•',
			'placeholder' => '• | · / or',
			'condition'   => [
				'labels_layout'           => 'inline_below',
				'inline_below_sep_enable' => 'on',
			],
			'selectors'   => [
				'{{WRAPPER}}'                             => '--jprm-inline-sep:"{{VALUE}}";',
				'{{WRAPPER}} .elementor-widget-container' => '--jprm-inline-sep:"{{VALUE}}";',
			],
			'render_type' => 'template',
		]);

		// Spacing
		$this->add_responsive_control( 'inline_below_sep_gap', [
			'label'      => __( 'Separator Spacing', 'jellopoint-restaurant-menu' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em', 'rem' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 24 ], 'em' => [ 'min' => 0, 'max' => 2 ] ],
			'default'    => [ 'size' => 0.6, 'unit' => 'rem' ],
			'condition'  => [
				'labels_layout'           => 'inline_below',
				'inline_below_sep_enable' => 'on',
			],
			'selectors'  => [
				'{{WRAPPER}}'                             => '--jprm-inline-sep-gap: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .elementor-widget-container' => '--jprm-inline-sep-gap: {{SIZE}}{{UNIT}};',
			],
			'render_type' => 'template',
		]);

		$this->add_control(
    		'labels_matrix_placeholder', [
        	'label'       => __( 'Matrix Placeholder', 'jellopoint-restaurant-menu' ),
        	'type'        => \Elementor\Controls_Manager::TEXT,
        	'default'     => '',
        	'placeholder' => '—',
        	'description' => __( 'Shown in Matrix cells when a price is missing. Leave empty for a blank cell.', 'jellopoint-restaurant-menu' ),
        	// IMPORTANT: your file uses `labels_layout`, not `global_labels_layout`
        	'condition'   => [
            'labels_layout' => 'matrix',
        	],
    ]);

// === Section Overrides (ALWAYS VISIBLE; supports Inline / Inline Below / Matrix) ===
$this->add_control( 'matrix_overrides_heading', [
    'type'      => Controls_Manager::HEADING,
    'label'     => __( 'Section Overrides', 'jellopoint-restaurant-menu' ),
    'separator' => 'before',
    // NOTE: no condition here → always visible
] );

/* Per-section overrides (scoped to current Menu) */
$selected_menu_id = 0;
try {
    $tmp = $this->get_settings_for_display();
    if ( ! empty( $tmp['menus'] ) ) $selected_menu_id = (int) $tmp['menus'];
} catch ( \Throwable $e ) {}

$section_options_scoped = [];
if ( $selected_menu_id > 0 && function_exists( 'jprm_infoblocks_sections_for_menu' ) ) {
    $section_options_scoped = jprm_infoblocks_sections_for_menu( $selected_menu_id );
}
if ( empty( $section_options_scoped ) ) {
    $section_options_scoped = $this->get_terms_options( 'jprm_section' );
}

$rep_ov = new Repeater();

// Which section (term) the override applies to
$rep_ov->add_control( 'section_id', [
    'label'   => __( 'Section', 'jellopoint-restaurant-menu' ),
    'type'    => Controls_Manager::SELECT,
    'options' => $section_options_scoped,
] );

// Which layout this override targets
$rep_ov->add_control( 'layout', [
    'label'   => __( 'Layout', 'jellopoint-restaurant-menu' ),
    'type'    => Controls_Manager::SELECT,
    'default' => 'inline',
    'options' => [
        'inline'       => __( 'Inline',        'jellopoint-restaurant-menu' ),
        'inline_below' => __( 'Inline Below',  'jellopoint-restaurant-menu' ),
        'matrix'       => __( 'Matrix',        'jellopoint-restaurant-menu' ),
    ],
] );

// Separator (applies only to Inline Below)
$rep_ov->add_control( 'separator', [
    'label'       => __( 'Separator (Inline Below only)', 'jellopoint-restaurant-menu' ),
    'type'        => Controls_Manager::TEXT,
    'placeholder' => '•',
    'condition'   => [ 'layout' => 'inline_below' ],
]);

// Placeholder (applies only to Matrix)
$rep_ov->add_control( 'placeholder', [
    'label'       => __( 'Placeholder (Matrix only)', 'jellopoint-restaurant-menu' ),
    'type'        => Controls_Manager::TEXT,
    'placeholder' => '—',
    'condition'   => [ 'layout' => 'matrix' ],
]);

$this->add_control( 'labels_layout_overrides', [
  'label'         => __( 'Per-Section Overrides', 'jellopoint-restaurant-menu' ),
  'type'          => Controls_Manager::REPEATER,
  'fields'        => $rep_ov->get_controls(),
  'title_field'   => '{{{ section_id }}} → {{{ layout }}}',
  'default'       => [],
  'prevent_empty' => false,
  'description'   => __( 'Sections list is scoped to the selected Menu. Change Menu and reopen the widget to refresh the list.', 'jellopoint-restaurant-menu' ),
  // NOTE: no condition here → always visible
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
	'return_value' => 'yes',
	'default'      => 'yes',
	'condition'    => [ 'data_mode' => 'dynamic' ],
] );

$this->add_control( 'badges_position', [
	'label'     => __( 'Position', 'jellopoint-restaurant-menu' ),
	'type'      => Controls_Manager::CHOOSE,
	'options'   => [
		'before' => [ 'title' => __( 'Before Title', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-h-align-left' ],
		'after'  => [ 'title' => __( 'After Title',  'jellopoint-restaurant-menu' ), 'icon' => 'eicon-h-align-right' ],
	],
	'default'   => 'after',
	'toggle'    => false,
	'condition' => [ 'show_badges' => 'yes', 'data_mode' => 'dynamic' ],
] );

$this->add_control( 'badges_presentation', [
	'label'     => __( 'Presentation', 'jellopoint-restaurant-menu' ),
	'type'      => Controls_Manager::SELECT,
	'options'   => [
		'icon'      => __( 'Icon', 'jellopoint-restaurant-menu' ),
		'text'      => __( 'Text', 'jellopoint-restaurant-menu' ),
		'icon_text' => __( 'Icon + Text', 'jellopoint-restaurant-menu' ),
	],
	'default'   => 'icon_text',
	'condition' => [ 'show_badges' => 'yes', 'data_mode' => 'dynamic' ],
] );

$this->end_controls_section();


		/* --- Info Blocks (HTML + Image; editor-only title) --------------------- */
		$this->start_controls_section(
			'jprm_section_info_blocks',
			[ 'label' => __( 'Info Blocks', 'jellopoint-restaurant-menu' ) ]
		);

		$all_section_opts = $section_options;
		$menu_selected    = $this->get_settings_for_display( 'menus' );
		$menu_id_for_ib   = ( is_numeric( $menu_selected ) ? (int) $menu_selected : 0 );
		$ib_section_opts  = $this->section_options_for_menu( $menu_id_for_ib, $all_section_opts );

		$ib = new Repeater();
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
			'title_field' => '{{{ ib_title }}} ({{{ position }}} #{{{ section_id }}})',
		] );

		$this->end_controls_section();

		/* --- Layout (columns, split) ------------------------------------------- */
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

		$this->register_style_controls();
	}
}
