<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }

	/** NEW: load price-block partial once */
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

		$path = dirname( __DIR__ ) . '/render/partials/badges-inline.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JPRM] badges-inline.php not found/readable at: ' . $path );
			}
		}
		$loaded = true;
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

	/* =========================
	 * Elementor Controls
	 * ========================= */
	protected function register_controls() {

		/* --- Data source -------------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_data',
			[ 'label' => __( 'Data', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'data_mode', [
			'label'   => __( 'Mode', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'dynamic' => [ 'title' => __( 'From Menus/Sections', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-database' ],
				'static'  => [ 'title' => __( 'Typed List', 'jellopoint-restaurant-menu' ), 'icon' => 'eicon-editor-list-ul' ],
			],
			'default' => 'dynamic',
			'toggle'  => false,
		] );

		$this->add_control( 'menu_select', [
			'label'       => __( 'Menu(s)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $this->get_terms_options( 'jprm_menu' ),
			'label_block' => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'sections_select', [
			'label'       => __( 'Section(s)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $this->get_terms_options( 'jprm_section' ),
			'label_block' => true,
			'condition'   => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'show_all_sections', [
			'label'        => __( 'Show all sections (ignore selection)', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );

		$this->end_controls_section();

		/* --- Menu meta (title/desc/badges) ------------------------------------- */
		$this->start_controls_section(
			'jprm_section_menu_meta',
			[ 'label' => __( 'Menu Meta', 'jellopoint-restaurant-menu' ) ]
		);
		$this->add_control( 'show_menu_title', [
			'label'        => __( 'Show menu title', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'show_menu_description', [
			'label'        => __( 'Show menu description', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'condition'    => [ 'data_mode' => 'dynamic' ],
		] );
		$this->add_control( 'menu_title_position', [
			'label'   => __( 'Menu title position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'above_menu',
			'options' => [
				'above_menu'   => __( 'Above complete menu', 'jellopoint-restaurant-menu' ),
				'first_column' => __( 'Inside first column', 'jellopoint-restaurant-menu' ),
			],
			'condition' => [ 'data_mode' => 'dynamic' ],
		] );

		$this->add_control( 'show_badges', [
			'label'        => __( 'Show dietary badges', 'jellopoint-restaurant-menu' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => [ 'data_mode' => 'dynamic' ],
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

		/* --- Info Blocks -------------------------------------------------------- */
		$this->start_controls_section(
			'jprm_section_info_blocks',
			[ 'label' => __( 'Info Blocks', 'jellopoint-restaurant-menu' ) ]
		);

		// NEW repeater
		$ib = new Repeater();
		$ib->add_control( 'type', [
			'label'   => __( 'Type', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'html',
			'options' => [
				'html'   => __( 'HTML/Text', 'jellopoint-restaurant-menu' ),
				'image'  => __( 'Image', 'jellopoint-restaurant-menu' ),
				'button' => __( 'Button', 'jellopoint-restaurant-menu' ),
			],
		] );
		$ib->add_control( 'content_html', [
			'label'     => __( 'HTML content', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::TEXTAREA,
			'rows'      => 3,
			'condition' => [ 'type' => 'html' ],
		] );
		$ib->add_control( 'image_id', [
			'label'     => __( 'Image', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::MEDIA,
			'condition' => [ 'type' => 'image' ],
		] );
		$ib->add_control( 'image_alt', [
			'label'     => __( 'Alt text', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => [ 'type' => 'image' ],
		] );
		$ib->add_control( 'button_text', [
			'label'     => __( 'Button text', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => [ 'type' => 'button' ],
		] );
		$ib->add_control( 'button_url', [
			'label'     => __( 'Button URL', 'jellopoint-restaurant-menu' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'type' => 'button' ],
		] );
		$ib->add_control( 'style_variant', [
			'label'   => __( 'Style', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'subtle',
			'options' => [
				'subtle' => __( 'Subtle', 'jellopoint-restaurant-menu' ),
				'accent' => __( 'Accent', 'jellopoint-restaurant-menu' ),
				'note'   => __( 'Note', 'jellopoint-restaurant-menu' ),
			],
		] );
		$ib->add_control( 'position', [
			'label'   => __( 'Position', 'jellopoint-restaurant-menu' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'between_sections',
			'options' => [
				'before_menu'      => __( 'Above Complete Menu', 'jellopoint-restaurant-menu' ),
				'between_sections' => __( 'Between Sections', 'jellopoint-restaurant-menu' ),
				'after_menu'       => __( 'Below Complete Menu', 'jellopoint-restaurant-menu' ),
			],
		] );

		/* NEW: shown only when Position = Between Sections */
		$ib->add_control( 'after_section', [
			'label'       => __( 'After Section', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'label_block' => true,
			'options'     => $this->get_terms_options( 'jprm_section' ),
			'default'     => '',
			'description' => __( 'Choose the section after which this Info Block should appear. Leave empty to insert after every section.', 'jellopoint-restaurant-menu' ),
			'condition'   => [ 'position' => 'between_sections' ],
		] );

		$this->add_control( 'info_blocks', [
			'label'       => __( 'Blocks', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $ib->get_controls(),
			'default'     => [],
			'title_field' => '{{{ type }}} – {{{ position }}}',
			'condition'   => [ 'data_mode' => 'dynamic' ],
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
			'label'       => __( 'Split after section (for 2/3 columns)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'label_block' => true,
			'multiple'    => false,
			'options'     => $this->get_terms_options( 'jprm_section' ),
			'condition'   => [
				'data_mode'      => 'dynamic',
				'layout_columns' => [ '2' ],
				'layout_split_mode' => 'manual',
			],
		] );

		$this->add_control( 'layout_split_after_section2', [
			'label'       => __( 'Second split (for 3 columns)', 'jellopoint-restaurant-menu' ),
			'type'        => Controls_Manager::SELECT2,
			'label_block' => true,
			'multiple'    => false,
			'options'     => $this->get_terms_options( 'jprm_section' ),
			'condition'   => [
				'data_mode'      => 'dynamic',
				'layout_columns' => [ '3' ],
				'layout_split_mode' => 'manual',
			],
		] );

		$this->end_controls_section();

		/* --- Currency / Labels / Static etc. … (unchanged) --------------------- */
	}

	/* =========================
	 * Renderer
	 * ========================= */
	protected function render() {
		self::require_price_partial_once();
		self::require_badges_partial_once();
		self::require_infoblocks_partial_once();

		// One-time tiny inline CSS (temporary)
		static $css_done = false;
		if ( ! $css_done ) {
			$css_done = true;
			echo '<style>
				.jp-menu__titleline{display:flex;align-items:center;gap:.5em;flex-wrap:wrap}
				.jp-menu__titleline .jp-menu__title{margin:0}
				.jp-menu__badges{display:inline-flex;gap:.35em}
				.jp-badge__icon{width:16px;height:16px;display:inline-block;vertical-align:middle}
				.jp-badge--icontext .jp-badge__label{margin-left:.35em}
				.jp-infoblocks{display:flex;flex-wrap:wrap;gap:.75rem;margin:.75rem 0}
				.jp-infoblock{padding:.65rem .8rem;border-radius:.5rem}
				.jp-infoblock--subtle{background:#f7f7f7}
				.jp-infoblock--accent{background:#fff3cd}
				.jp-infoblock--note{background:#e9f7ff}
				.jp-menu-grid{display:grid;gap:1rem}
				.jp-cols-2{grid-template-columns:1fr 1fr}
				.jp-cols-3{grid-template-columns:1fr 1fr 1fr}
			</style>';
		}

		$s = $this->get_settings_for_display();

		/* … your existing normalization, queries, assembling $sections_data, $sections_order, $menu_term, etc … */

		/* Booleans */
		$show_section_name = ( isset( $s['show_section_name'] ) && $s['show_section_name'] === 'yes' );
		$show_section_desc = ( isset( $s['show_section_description'] ) && $s['show_section_description'] === 'yes' );
		$show_menu_title   = ( isset( $s['show_menu_title'] ) && $s['show_menu_title'] === 'yes' );
		$show_menu_desc    = ( isset( $s['show_menu_description'] ) && $s['show_menu_description'] === 'yes' );

		$badges_position   = $s['badges_position'] ?? 'after_title';
		$badges_presentation = $s['badges_presentation'] ?? 'icon_text';

		$menu_pos = $s['menu_title_position'] ?? 'above_menu';

		/* Info Blocks (collect & bucket) */
		$info_rows = is_array( $s['info_blocks'] ?? null ) ? $s['info_blocks'] : [];
		$ibuckets  = function_exists( 'jprm_infoblocks_partition_by_position' )
			? jprm_infoblocks_partition_by_position( $info_rows )
			: [ 'before_menu'=>[], 'between_sections'=>[], 'after_menu'=>[] ];

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
		if ( $s['layout_columns'] === '1' ) {

			// BEFORE MENU blocks
			if ( ! empty( $ibuckets['before_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['before_menu'], 'before_menu' ); // phpcs:ignore
			}

			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
			}

			echo '<ul class="jp-menu">';

			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
				echo '<li class="jp-menu__meta-li">';
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
				echo '</li>';
			}

			$first_section = true;
			foreach ( $sections_order as $tid ) {
				if ( ! $first_section && ! empty( $ibuckets['between_sections'] ) ) {
					/* Info Blocks: targeted between-sections */
					$__rows = [];
					foreach ( $ibuckets['between_sections'] as $__row ) {
						if ( function_exists( 'jprm_infoblocks_matches_section' ) ? jprm_infoblocks_matches_section( $__row, $tid ) : true ) { $__rows[] = $__row; }
					}
					if ( ! empty( $__rows ) && function_exists( 'jprm_infoblocks_render_rows' ) ) {
						echo '<li class="jp-menu__infoblocks-li">' . jprm_infoblocks_render_rows( $__rows, 'between_sections' ) . '</li>';
					}
				}
				$first_section = false;

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
					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';

					// prices block
					if ( function_exists( 'jprm_render_price_block_html' ) ) {
						echo jprm_render_price_block_html( $post_id ); // phpcs:ignore
					}

					echo '</div></li>';
				}
			}
			echo '</ul>';

			// AFTER MENU blocks
			if ( ! empty( $ibuckets['after_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['after_menu'], 'after_menu' ); // phpcs:ignore
			}
			return;
		}

		/* ===== 2 columns ===== */
		if ( $s['layout_columns'] === '2' ) {

			/* ... your existing logic to compute $left_sections / $right_sections ... */

			// BEFORE MENU
			if ( ! empty( $ibuckets['before_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['before_menu'], 'before_menu' ); // phpcs:ignore
			}

			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
			}

			echo '<div class="jp-menu-grid jp-cols-2 jp-menu--cols-2">';
			/* LEFT COL */
			echo '<ul class="jp-menu jp-menu--col jp-menu--left">';
			if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
				echo '<li class="jp-menu__meta-li">';
				echo $render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
				echo '</li>';
			}
			$first_left = true;
			foreach ( $left_sections as $tid ) {
				if ( ! $first_left && ! empty( $ibuckets['between_sections'] ) ) {
					/* Info Blocks: targeted between-sections */
					$__rows = [];
					foreach ( $ibuckets['between_sections'] as $__row ) {
						if ( function_exists( 'jprm_infoblocks_matches_section' ) ? jprm_infoblocks_matches_section( $__row, $tid ) : true ) { $__rows[] = $__row; }
					}
					if ( ! empty( $__rows ) && function_exists( 'jprm_infoblocks_render_rows' ) ) {
						echo '<li class="jp-menu__infoblocks-li">' . jprm_infoblocks_render_rows( $__rows, 'between_sections' ) . '</li>';
					}
				}
				$first_left = false;

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
					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';

					if ( function_exists( 'jprm_render_price_block_html' ) ) {
						echo jprm_render_price_block_html( $post_id ); // phpcs:ignore
					}

					echo '</div></li>';
				}
			}
			echo '</ul>';

			/* RIGHT COL */
			echo '<ul class="jp-menu jp-menu--col jp-menu--right">';
			$first_right = true;
			foreach ( $right_sections as $tid ) {
				if ( ! $first_right && ! empty( $ibuckets['between_sections'] ) ) {
					/* Info Blocks: targeted between-sections */
					$__rows = [];
					foreach ( $ibuckets['between_sections'] as $__row ) {
						if ( function_exists( 'jprm_infoblocks_matches_section' ) ? jprm_infoblocks_matches_section( $__row, $tid ) : true ) { $__rows[] = $__row; }
					}
					if ( ! empty( $__rows ) && function_exists( 'jprm_infoblocks_render_rows' ) ) {
						echo '<li class="jp-menu__infoblocks-li">' . jprm_infoblocks_render_rows( $__rows, 'between_sections' ) . '</li>';
					}
				}
				$first_right = false;

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
					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';
					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';

					if ( function_exists( 'jprm_render_price_block_html' ) ) {
						echo jprm_render_price_block_html( $post_id ); // phpcs:ignore
					}

					echo '</div></li>';
				}
			}
			echo '</ul>';
			echo '</div>';

			// AFTER MENU
			if ( ! empty( $ibuckets['after_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['after_menu'], 'after_menu' ); // phpcs:ignore
			}
			return;
		}

		/* ===== 3 columns ===== */
		if ( $s['layout_columns'] === '3' ) {

			/* ... your existing logic to compute $col1, $col2, $col3 ... */

			// BEFORE MENU
			if ( ! empty( $ibuckets['before_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['before_menu'], 'before_menu' ); // phpcs:ignore
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
				$first_in_col = true;
				foreach ( $section_ids_chunk as $tid ) {
					if ( ! $first_in_col && ! empty( $ibuckets['between_sections'] ) ) {
						/* Info Blocks: targeted between-sections */
						$__rows = [];
						foreach ( $ibuckets['between_sections'] as $__row ) {
							if ( function_exists( 'jprm_infoblocks_matches_section' ) ? jprm_infoblocks_matches_section( $__row, $tid ) : true ) { $__rows[] = $__row; }
						}
						if ( ! empty( $__rows ) && function_exists( 'jprm_infoblocks_render_rows' ) ) {
							echo '<li class="jp-menu__infoblocks-li">' . jprm_infoblocks_render_rows( $__rows, 'between_sections' ) . '</li>';
						}
					}
					$first_in_col = false;

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
						echo '    <div class="jp-menu__titleline">';
						if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
							echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
						}
						if ( $title !== '' ) echo '    <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
						if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
							echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
						}
						echo '    </div>';
						if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
						echo '  </div>';
						if ( function_exists( 'jprm_render_price_block_html' ) ) {
							echo jprm_render_price_block_html( $post_id ); // phpcs:ignore
						}
						echo '</div></li>';
					}
				}
				echo '</ul></div>';
			}

			echo '</div>';

			// AFTER MENU
			if ( ! empty( $ibuckets['after_menu'] ) && function_exists( 'jprm_infoblocks_render_group' ) ) {
				echo jprm_infoblocks_render_group( $ibuckets['after_menu'], 'after_menu' ); // phpcs:ignore
			}
			return;
		}

		/* … static mode etc. unchanged … */
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

	/* … other helpers unchanged … */
}
