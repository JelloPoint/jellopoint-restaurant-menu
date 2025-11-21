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
	 * (Kept for compatibility; not used for the primary scoped list anymore.)
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
	/** Build select options for Sections: [term_id => "Parent › Child"] */
	private function jprm_get_sections_options(): array {
		$out = [];
		// Get ALL sections (we’re not filtering by Owner Menu here).
		$terms = \get_terms([
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if ( \is_wp_error( $terms ) || ! \is_array( $terms ) ) {
			return $out;
		}

		// Build a quick id->term map and parent->children map to format a path label.
		$by_id = [];
		$kids  = [];
		foreach ( $terms as $t ) {
			$by_id[ (int) $t->term_id ] = $t;
			$p = (int) $t->parent;
			if ( ! isset( $kids[$p] ) ) $kids[$p] = [];
			$kids[$p][] = (int) $t->term_id;
		}

		// Helper to build "Parent › Child › Sub" labels
		$make_label = function( \WP_Term $term ) use ( $by_id ): string {
			$parts = [ $term->name ];
			$p = (int) $term->parent;
			$guard = 0;
			while ( $p && isset( $by_id[$p] ) && $guard < 10 ) {
				\array_unshift( $parts, $by_id[$p]->name );
				$p = (int) $by_id[$p]->parent;
				$guard++;
			}
			return \implode( ' › ', $parts );
		};

		foreach ( $terms as $t ) {
			$out[ (int) $t->term_id ] = $make_label( $t );
		}

		// Sort labels naturally for nicer UX
		\natcasesort( $out );
		return $out;
	}

	/**
	 * Preferred provider for "scoped + tree-indented" section options for a given Menu.
	 * Uses jprm_infoblocks_sections_for_menu() if present; otherwise falls back to $all_sections (flat).
	 */
	protected function scoped_sections_for_menu( int $menu_term_id, array $all_sections ) : array {
		if ( $menu_term_id > 0 && function_exists( 'jprm_infoblocks_sections_for_menu' ) ) {
			$map = jprm_infoblocks_sections_for_menu( $menu_term_id );
			if ( is_array( $map ) && ! empty( $map ) ) {
				// Expected shape: [ '98' => 'Drinks', '99' => '— Beers', ... ]
				return $map;
			}
		}
		return $all_sections; // flat fallback
	}

	/* ========= Controls ========= */
	protected function register_controls() {

	/* --- Preload option sources (neutral, unscoped) ----------------------- */
	$menu_options_all    = $this->get_terms_options( 'jprm_menu' );
	$section_options_all = $this->get_terms_options( 'jprm_section' );

	// IMPORTANT:
	// - Do NOT call get_settings() or get_settings_for_display() here.
	// - Do NOT try to scope to a menu here.
	// - Provide non-null defaults for ALL controls.
	$_scoped_sections = $section_options_all; // editor JS will scope + indent dynamically

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
			'options'   => $menu_options_all,
			'default'      => '',
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections', [
			'label'     => __( 'Sections', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::SELECT2,
			'multiple'  => true,
			'options'   => $_scoped_sections, // ← scoped to current Menu (tree if helper provides it)
			'default'   => [],
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

		// --- Items ordering (global) ----------------------------------------------
		$this->add_control( 'items_orderby', [
			'label'   => __( 'Items Order By', 'jprm' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'menu_order',
			'options' => [
				'menu_order' => __( 'Order (menu order)', 'jprm' ),
				'title'      => __( 'Title', 'jprm' ),
				'price'      => __( 'Price', 'jprm' ), // first/primary price; see renderer notes
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		]);

		$this->add_control( 'items_order', [
			'label'   => __( 'Direction', 'jprm' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'ASC',
			'options' => [
				'ASC'  => __( 'Ascending', 'jprm' ),
				'DESC' => __( 'Descending', 'jprm' ),
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		]);

// -------------------------------------------------------------------------
// Item order – per Section (optional overrides)
// -------------------------------------------------------------------------

$section_order_repeater = new \Elementor\Repeater();

$section_order_repeater->add_control( 'section_id', [
    'label'       => __( 'Section', 'jprm' ),
    'type'        => \Elementor\Controls_Manager::SELECT2,
    'options'     => $this->jprm_get_sections_options(), // uses helper on the widget class
    'multiple'    => false,
    'label_block' => true,
]);

$section_order_repeater->add_control( 'orderby', [
    'label'   => __( 'Order items by', 'jprm' ),
    'type'    => \Elementor\Controls_Manager::SELECT,
    'default' => 'menu_order',
    'options' => [
        'menu_order' => __( 'Manual order (menu_order)', 'jprm' ),
        'title'      => __( 'Title', 'jprm' ),
        'price'      => __( 'Price (effective)', 'jprm' ),
    ],
]);

$section_order_repeater->add_control( 'order', [
    'label'   => __( 'Direction', 'jprm' ),
    'type'    => \Elementor\Controls_Manager::SELECT,
    'default' => 'ASC',
    'options' => [
        'ASC'  => __( 'Ascending (A → Z / low → high)', 'jprm' ),
        'DESC' => __( 'Descending (Z → A / high → low)', 'jprm' ),
    ],
]);

$this->add_control( 'items_order_overrides', [
    'label'       => __( 'Item order per Section', 'jprm' ),
    'type'        => \Elementor\Controls_Manager::REPEATER,
    'fields'      => $section_order_repeater->get_controls(),
    'title_field' => '{{{ section_id }}} — {{{ order }}} / {{{ orderby }}}',
    'description' => __(
        'Optional: override the global item order (by title / price / menu order) for specific Sections.',
        'jprm'
    ),
    'condition'   => [
        'data_mode' => 'dynamic',
    ],
]);



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

		$this->add_control( 'label_position', [
			'label'        => __( 'Label Position', 'jellopoint-restaurant-menu' ),
			'type'         => \Elementor\Controls_Manager::SELECT,
			'default'      => 'right',
			'options'      => [
				'left'  => __( 'Left of price', 'jellopoint-restaurant-menu' ),
				'right' => __( 'Right of price', 'jellopoint-restaurant-menu' ),
			],
			'prefix_class' => 'jprm-labelpos-',
		] );

		// --- Inline leader (title ↔ price) ------------------------------------------
		$this->add_control( 'inline_leader_enable', [
			'label'        => __( 'Leader between Title and Price', 'jprm' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'jprm' ),
			'label_off'    => __( 'Hide', 'jprm' ),
			'return_value' => 'yes',
			'default'      => '',
			// only makes sense for the Inline template
			'condition'    => [ 'labels_layout' => 'inline' ],
		]);


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
			'return_value' => 'on',
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
				'condition'   => [ 'labels_layout' => 'matrix' ],
		] );

		// === Section Overrides (ALWAYS VISIBLE; supports Inline / Inline Below / Matrix)
		$this->add_control( 'matrix_overrides_heading', [
			'type'      => Controls_Manager::HEADING,
			'label'     => __( 'Section Overrides', 'jellopoint-restaurant-menu' ),
			'separator' => 'before',
		] );

		$rep_ov = new Repeater();

		$rep_ov->add_control( 'section_id', [
			'label'   => __( 'Section', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'options' => $_scoped_sections,
			'classes'  => 'jprm-scope-target',
			'default'      => '',
		] );

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

		$rep_ov->add_control( 'separator', [
			'label'       => __( 'Separator (Inline Below only)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '•',
			'condition'   => [ 'layout' => 'inline_below' ],
		] );

		$rep_ov->add_control( 'placeholder', [
			'label'       => __( 'Placeholder (Matrix only)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '—',
			'condition'   => [ 'layout' => 'matrix' ],
		] );

		$this->add_responsive_control( 'labels_layout_overrides', [
			'label'         => __( 'Per-Section Overrides', 'jellopoint-restaurant-menu' ),
			'type'          => Controls_Manager::REPEATER,
			'fields'        => $rep_ov->get_controls(),
			'title_field'   => '{{{ section_id }}} → {{{ layout }}}',
			'default'       => [],
			'prevent_empty' => false,
			'description'   => __( 'Sections list is scoped to the selected Menu. Change Menu and reopen the widget to refresh the list.', 'jellopoint-restaurant-menu' ),
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

		// Use the unified scoped list here as well
		$ib = new Repeater();
		$ib->add_control( 'ib_title', [
			'label'       => __( 'Title (editor only)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'e.g., Chef’s Note, Allergen Info', 'jellopoint-restaurant-menu' ),
			'label_block' => true,
			'default'     => '',
		] );
		/*$ib->add_control( 'content_html', [
			'label' => __( 'Description', 'textdomain' ),
			'type'	=> Controls_Manager::WYSIWYG,
			'default' => __( 'Default description', 'textdomain' ),
			'placeholder' => __( 'Type your description here', 'textdomain' ),
		] );*/
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
			'options'     => $_scoped_sections,
			'classes'  => 'jprm-scope-target',
			'default'   => [],
			'multiple'    => false,
			'label_block' => true,
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

		$this->add_responsive_control( 'layout_columns', [
		'label'        => __( 'Columns', 'jprm' ),
		'type'         => \Elementor\Controls_Manager::SELECT,
		'default'      => '2',          // desktop default
		'tablet_default' => '2',
		'mobile_default' => '1',        // mobile → 1 column by default
		'options'      => [
			'1' => '1',
			'2' => '2',
			'3' => '3',
		],
		// Override the CSS variable per breakpoint; the !important ensures it beats inline style
		'selectors'    => [
			'{{WRAPPER}} .jp-menu-grid' => '--jp-cols: {{VALUE}} !important;',
		],
		]);

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
			'options'   => $_scoped_sections,
			'classes'   => 'jprm-scope-target',
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
			'options'   => $_scoped_sections,
			'classes'   => 'jprm-scope-target',
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
