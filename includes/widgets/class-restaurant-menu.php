<?php
namespace JelloPoint\RestaurantMenu\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Restaurant Menu Widget
 * (Clean render flow; passes deterministic ctx, includes helpers, includes dispatcher)
 */
class Restaurant_Menu extends Widget_Base {

	public function get_name() { return 'jprm_restaurant_menu'; }
	public function get_title() { return __( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); }
	public function get_icon() { return 'eicon-menu-card'; }
	public function get_categories() { return [ 'general' ]; }

	/* ------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------ */

	/**
	 * Normalize the 'labels_layout_overrides' repeater into a lookup:
	 *   $map[SECTION_ID]['layout']                  = 'inline'|'inline_below'|'matrix'
	 *   $map[SECTION_ID]['matrix']['placeholder']   = string
	 *   $map[SECTION_ID]['inline_below']['separator']= string
	 */
	private function jprm_normalize_section_overrides( $rows ) : array {
		$map = [];
		if ( ! is_array( $rows ) || empty( $rows ) ) return $map;

		foreach ( $rows as $row ) {
			$sec = isset( $row['section_id'] ) ? (int) $row['section_id'] : 0;
			if ( $sec <= 0 ) continue;

			$layout = isset( $row['layout'] ) ? (string) $row['layout'] : '';
			if ( $layout !== '' ) {
				$map[$sec]['layout'] = $layout;
			}

			if ( $layout === 'matrix' && array_key_exists( 'placeholder', $row ) ) {
				$map[$sec]['matrix']['placeholder'] = html_entity_decode( (string) $row['placeholder'], ENT_QUOTES );
			}

			if ( $layout === 'inline_below' && array_key_exists( 'separator', $row ) ) {
				$map[$sec]['inline_below']['separator'] = (string) $row['separator'];
			}
		}

		return $map;
	}

	/* ------------------------------------------------------------
	 * Elementor controls (kept in your trait normally)
	 * ------------------------------------------------------------ */

	protected function register_controls() {
		// Keep your existing trait/controls.
		// This file focuses on a clean render() and deterministic ctx.
	}

	/* ------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------ */

	protected function render() {
		$s = $this->get_settings_for_display();

		// ----- Global layout pickers/flags (names based on your existing ctx usage)
		$global_labels_layout = isset( $s['labels_layout'] ) ? (string) $s['labels_layout'] : 'inline';
		$show_menu_title      = ! empty( $s['show_menu_title'] );
		$show_menu_desc       = ! empty( $s['show_menu_desc'] );
		$show_section_name    = ! empty( $s['show_section_name'] );
		$show_section_desc    = ! empty( $s['show_section_desc'] );

		// ----- Data your code already builds elsewhere (these lines assume your current project conventions)
		$menu_term      = isset( $s['menus'] ) ? get_term( (int) $s['menus'], 'jprm_menu' ) : null;
		$menu_pos       = isset( $s['menu_pos'] ) ? (string) $s['menu_pos'] : 'above_menu';

		// sections_order + sections_data should already be built in your project.
		// If you already build them earlier in this class, keep that and reuse the variables here:
		$sections_order = isset( $s['sections_order'] ) && is_array( $s['sections_order'] ) ? $s['sections_order'] : [];
		$sections_data  = isset( $s['sections_data'] ) && is_array( $s['sections_data'] ) ? $s['sections_data'] : [];

		// If your project builds info blocks and label maps elsewhere, keep them:
		$ib_map         = isset( $s['ib_map'] ) && is_array( $s['ib_map'] ) ? $s['ib_map'] : [];
		$label_map      = isset( $s['label_map'] ) && is_array( $s['label_map'] ) ? $s['label_map'] : [];
		$currency_opts  = isset( $s['currency_opts'] ) && is_array( $s['currency_opts'] ) ? $s['currency_opts'] : [];

		// Badges/labels presentation flags (keep your existing names)
		$show_badges         = ! empty( $s['show_badges'] );
		$badges_presentation = isset( $s['badges_presentation'] ) ? (string) $s['badges_presentation'] : 'icon_text';
		$badges_position     = isset( $s['badges_position'] ) ? (string) $s['badges_position'] : 'after_title';

		$label_presentation  = isset( $s['label_presentation'] ) ? (string) $s['label_presentation'] : 'icon_text';
		$label_position      = isset( $s['label_position'] ) ? (string) $s['label_position'] : 'right';

		// Your existing section layouts (if you have another source, keep it and merge with overrides below)
		$section_layouts = isset( $s['section_layouts'] ) && is_array( $s['section_layouts'] ) ? $s['section_layouts'] : [];

		// ---- NEW: normalized overrides from the repeater (ALWAYS deterministic)
		$section_overrides = $this->jprm_normalize_section_overrides( $s['labels_layout_overrides'] ?? [] );

		// ---- EXACT control values (no $settings; use $s)
		$labels_matrix_placeholder = isset( $s['labels_matrix_placeholder'] )
			? html_entity_decode( (string) $s['labels_matrix_placeholder'], ENT_QUOTES )
			: '';

		$inline_below_separator = isset( $s['inline_below_separator'] )
			? (string) $s['inline_below_separator']
			: '';

		// ---- Optional fallback used elsewhere in legacy code; retain if your theme expects it
		$global_placeholder = isset( $s['labels_matrix_placeholder'] ) && $s['labels_matrix_placeholder'] !== ''
			? (string) $s['labels_matrix_placeholder']
			: '—';

		// ---- Compose ctx for templates
		$ctx = [
			'menu_term'            => $menu_term,
			'show_menu_title'      => $show_menu_title,
			'show_menu_desc'       => $show_menu_desc,
			'menu_pos'             => $menu_pos,

			'sections_order'       => $sections_order,
			'sections_data'        => $sections_data,

			'show_section_name'    => $show_section_name,
			'show_section_desc'    => $show_section_desc,

			'show_badges'          => $show_badges,
			'badges_presentation'  => $badges_presentation,
			'badges_position'      => $badges_position,

			'label_presentation'   => $label_presentation,
			'label_position'       => $label_position,

			'label_map'            => $label_map,
			'currency_opts'        => $currency_opts,

			'ib_map'               => $ib_map,

			'global_labels_layout' => $global_labels_layout,
			'section_layouts'      => $section_layouts,
			'section_overrides'    => $section_overrides,

			// Globals that layouts may use
			'labels_matrix_placeholder' => $labels_matrix_placeholder,
			'inline_below_separator'    => $inline_below_separator,

			// legacy/global placeholder (kept for older paths)
			'global_placeholder'   => $global_placeholder,
		];

		// ---- Make helpers available to templates
		$__jp_overrides = dirname( __DIR__ ) . '/helpers/overrides.php'; // includes/helpers/overrides.php
		if ( file_exists( $__jp_overrides ) ) {
			require_once $__jp_overrides;
		}

		// ---- Include the dispatcher
		$template = dirname( __DIR__ ) . '/render/templates/menu.php';
		if ( file_exists( $template ) ) {
			// expose $ctx locally for the template
			$__jprm_ctx = $ctx;
			unset( $ctx );
			$ctx = $__jprm_ctx;
			unset( $__jprm_ctx );

			include $template;
		} else {
			echo '<div class="jp-menu-error">Template not found: ' . esc_html( $template ) . '</div>';
		}
	}
}
