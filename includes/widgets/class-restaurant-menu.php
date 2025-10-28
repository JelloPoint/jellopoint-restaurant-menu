<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;

use function jprm_build_label_map;
use function jprm_read_price_config;
use function jprm_render_pricegroup_html;

require_once __DIR__ . '/traits/restaurant-menu-controls.php';
use JelloPoint\RestaurantMenu\Widgets\Traits\Restaurant_Menu_Controls;

require_once __DIR__ . '/traits/restaurant-menu-style.php';
use JelloPoint\RestaurantMenu\Widgets\Traits\Restaurant_Menu_Style;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Restaurant_Menu extends Widget_Base {
    use Restaurant_Menu_Controls, Restaurant_Menu_Style;

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'jellopoint-widgets' ]; }
	public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
	public function get_style_depends() { return [ 'jprm-menu' ]; }
	public function get_script_depends() { return []; }

	/* ===== Partials / helpers ===== */
	private static function require_price_partial_once() : void {
		static $loaded = false; if ( $loaded ) return;
		$path = dirname( __DIR__ ) . '/render/partials/price-block.php';
		if ( is_readable( $path ) ) require_once $path;
		$loaded = true;
	}
	/* ===== Partials / helpers ===== */
private static function require_badges_partial_once() : void {
	static $loaded = false; if ( $loaded ) return;
	$path = dirname( __DIR__ ) . '/render/partials/badges-block.php';
	if ( is_readable( $path ) ) { require_once $path; }
	$loaded = true;
}
	private static function require_infoblocks_partial_once() : void {
		static $loaded = false; if ( $loaded ) return;
		$path = dirname( __DIR__ ) . '/render/partials/info-blocks.php';
		if ( is_readable( $path ) ) require_once $path;
		$loaded = true;
	}
	private static function ensure_menu_meta_helper() : void {
		if ( function_exists( 'jprm_render_menu_meta' ) ) return;
		function jprm_render_menu_meta( $term, bool $show_title, bool $show_desc, string $scope ) : string {
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
		}
	}
	/**
 * Normalize 'labels_layout_overrides' repeater into a lookup:
 * $map[SECTION_ID]['matrix']['placeholder']
 * $map[SECTION_ID]['inline_below']['separator']
 * (Add other layouts as needed.)
 */
private function jprm_normalize_section_overrides( $rows ) : array {
    $map = [];
    if ( ! is_array( $rows ) || empty( $rows ) ) return $map;

    foreach ( $rows as $row ) {
        $sec = isset( $row['section_id'] ) ? (int) $row['section_id'] : 0;
        if ( $sec <= 0 ) continue;

        $layout = isset( $row['layout'] ) ? (string) $row['layout'] : '';

        // Matrix → placeholder
        if ( $layout === 'matrix' && array_key_exists( 'placeholder', $row ) ) {
            $map[$sec]['matrix']['placeholder'] = html_entity_decode( (string) $row['placeholder'], ENT_QUOTES );
        }

        // Inline-Below → separator
        if ( $layout === 'inline_below' && array_key_exists( 'separator', $row ) ) {
            $map[$sec]['inline_below']['separator'] = (string) $row['separator'];
        }

        // (Inline or other layouts can be added here later if needed.)
    }

    return $map;
}

	/* =========================
	 * Render
	 * ========================= */
	public function render() {
		self::require_price_partial_once();
		self::require_badges_partial_once();
		self::require_infoblocks_partial_once();
		self::ensure_menu_meta_helper();

		// DEBUG: verify badges partial + function
$__badges_partial_path = dirname( __DIR__ ) . '/render/partials/badges-block.php';
$__badges_readable     = is_readable( $__badges_partial_path ) ? '1' : '0';

if ( function_exists( 'jprm_render_badges_inline_html' ) ) {
    error_log( 'JPRM: OK → badges function loaded. path=' . $__badges_partial_path . ' readable=' . $__badges_readable );
} else {
    error_log( 'JPRM: ERROR → badges function NOT loaded. path=' . $__badges_partial_path . ' readable=' . $__badges_readable . ' (check file and path)' );
}


		static $css_done = false;
		if ( ! $css_done ) { $css_done = true; }

		$s = $this->get_settings_for_display();
		$section_overrides = $this->jprm_normalize_section_overrides( $s['labels_layout_overrides'] ?? [] );
		$mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;

		// Static mode (unchanged)
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
		$badges_position     = isset( $s['badges_position'] ) ? (string) $s['badges_position'] : 'after';

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
			if ( empty( $cfg ) ) continue;

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

		// Info Blocks map
		$ib_rows = ( isset( $s['info_blocks'] ) && is_array( $s['info_blocks'] ) ) ? $s['info_blocks'] : [];
		$ib_map  = function_exists('jprm_infoblocks_partition_by_position') ? jprm_infoblocks_partition_by_position( $ib_rows ) : [];

		// NEW: Labels Layout (global + per-section overrides)
$global_labels_layout = isset( $s['labels_layout'] ) ? (string) $s['labels_layout'] : 'inline';
$global_placeholder   = isset( $s['labels_matrix_placeholder'] ) ? (string) $s['labels_matrix_placeholder'] : '—';

// Build per-section overrides into $section_layouts, supporting both Matrix (placeholder) and Inline Below (separator)
$section_layouts = [];
$overrides = ( isset( $s['labels_layout_overrides'] ) && is_array( $s['labels_layout_overrides'] ) ) ? $s['labels_layout_overrides'] : [];
foreach ( $overrides as $ov ) {
    $sid = isset( $ov['section_id'] ) ? (int) $ov['section_id'] : 0;
    if ( $sid <= 0 ) continue;

    $layout      = isset( $ov['layout'] ) ? (string) $ov['layout'] : '';
    $placeholder = isset( $ov['placeholder'] ) ? (string) $ov['placeholder'] : '';
    $separator   = isset( $ov['separator'] )   ? (string) $ov['separator']   : '';

    // Start with an existing row if one was set earlier
    if ( ! isset( $section_layouts[ $sid ] ) ) {
        $section_layouts[ $sid ] = [ 'layout' => '', 'placeholder' => '', 'separator' => '' ];
    }
    if ( $layout !== '' ) {
        $section_layouts[ $sid ]['layout'] = $layout;
    }
    // If this row targets Matrix, capture its placeholder if provided
    if ( $layout === 'matrix' && $placeholder !== '' ) {
        $section_layouts[ $sid ]['placeholder'] = $placeholder;
    }
    // If this row targets Inline Below, capture its separator if provided
    if ( $layout === 'inline_below' && $separator !== '' ) {
        $section_layouts[ $sid ]['separator'] = $separator;
    }
}


		$ctx = [
			'columns'             => $columns,
			'menu_term'           => $menu_term,
			'show_menu_title'     => $show_menu_title,
			'show_menu_desc'      => $show_menu_desc,
			'menu_pos'            => $menu_pos,
			'sections_order'      => $sections_order,
			'sections_data'       => $sections_data,
			'show_section_name'   => $show_section_name,
			'show_section_desc'   => $show_section_desc,
			'show_badges'         => $show_badges,
			'badges_presentation' => $badges_presentation,
			'badges_position'     => $badges_position,
			'label_presentation'  => $label_presentation,
			'label_position'      => $label_position,
			'label_map'           => $label_map,
			'currency_opts'       => $currency_opts,
			'split_mode'          => $split_mode,
			'split_after_1'       => $split_after_1,
			'split_after_2'       => $split_after_2,
			'ib_map'              => $ib_map,
			'section_layouts'       => $section_layouts,
			// Multi-column controls (new keys) + legacy fallbacks
			'layout_columns'               => isset($s['layout_columns']) ? (string)$s['layout_columns'] : ( isset($s['columns']) ? (string)$s['columns'] : '1' ),
			'layout_split_mode'            => isset($s['layout_split_mode']) ? (string)$s['layout_split_mode'] : ( isset($s['split_mode']) ? (string)$s['split_mode'] : 'auto' ),
			'layout_split_after_section'   => isset($s['layout_split_after_section']) ? (int)$s['layout_split_after_section'] : 0,
			'layout_split_after_section2'  => isset($s['layout_split_after_section2']) ? (int)$s['layout_split_after_section2'] : 0,

			// (optional) keep legacy keys too, in case something else still reads them
			'columns'                      => isset($s['layout_columns']) ? (string)$s['layout_columns'] : ( isset($s['columns']) ? (string)$s['columns'] : '1' ),
			'split_mode'                   => isset($s['layout_split_mode']) ? (string)$s['layout_split_mode'] : ( isset($s['split_mode']) ? (string)$s['split_mode'] : 'auto' ),


			// globals used by templates
			'global_labels_layout'  => $global_labels_layout,
			'labels_matrix_placeholder' => isset( $s['labels_matrix_placeholder'] )
    		? html_entity_decode( (string) $s['labels_matrix_placeholder'], ENT_QUOTES )
    		: '',
			// Inline-Below separator (from Content tab controls)
			'inline_below_separator' => (
    		! empty( $s['inline_below_sep_enable'] ) && $s['inline_below_sep_enable'] === 'on'
			)
    		? (string) ( $s['inline_below_sep_content'] ?? '' )
    		: '',
			'global_placeholder'    => $global_placeholder,
				];

// Load overrides helper so templates can call jprm_effective_*()
$__jp_overrides = dirname( __DIR__ ) . '/helpers/overrides.php'; // path: includes/helpers/overrides.php
if ( file_exists( $__jp_overrides ) ) {
    require_once $__jp_overrides;
}
// Load icon helpers so templates/badges can colorize SVGs consistently
$__jp_icons = dirname( __DIR__ ) . '/helpers/icons.php';
if ( file_exists( $__jp_icons ) ) {
    require_once $__jp_icons;
}

		$template = dirname( __DIR__ ) . '/render/templates/menu.php';
		if ( is_readable( $template ) ) {
			$ctx = $ctx; // local scope for template
			require $template;
		} else {
			echo '<div class="jp-menu--empty">Template missing.</div>';
		}
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
