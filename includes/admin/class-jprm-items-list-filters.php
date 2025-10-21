<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Chained filters + AJAX for jprm_menu_item list (no duplicate UI):
 * - Binds to existing "Menu" and "Section" dropdowns (ids: jprm_filter_menu, jprm_filter_section)
 * - Server-side filter via pre_get_posts
 * - AJAX refresh of table/pagers (graceful fallback)
 */
class Items_List_Filters {

	const CPT_ITEM        = 'jprm_menu_item';
	const TAX_MENU        = 'jprm_menu';
	const TAX_SECTION     = 'jprm_section';
	const META_MENU_OWNER = '_jprm_menu_term_id';

	const Q_MENU    = 'jprm_filter_menu';
	const Q_SECTION = 'jprm_filter_section';

	public static function init() : void {
		// DO NOT render filters here to avoid duplicates; we piggy-back existing UI.
		add_action( 'pre_get_posts',         [ __CLASS__, 'apply_filters_to_query' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_js' ] );
	}

	/** Server-side: constrain list by chosen Menu/Section. */
	public static function apply_filters_to_query( \WP_Query $q ) : void {
		if ( ! is_admin() || ! $q->is_main_query() ) return;
		if ( ( $q->get( 'post_type' ) !== self::CPT_ITEM ) ) return;

		$sel_menu    = isset( $_GET[ self::Q_MENU ] )    ? (int) $_GET[ self::Q_MENU ]    : 0; // phpcs:ignore
		$sel_section = isset( $_GET[ self::Q_SECTION ] ) ? (int) $_GET[ self::Q_SECTION ] : 0; // phpcs:ignore

		// If a specific Section is chosen -> filter by that section only.
		if ( $sel_section > 0 ) {
			$tax = (array) $q->get( 'tax_query', [] );
			$tax[] = [
				'taxonomy'         => self::TAX_SECTION,
				'field'            => 'term_id',
				'terms'            => [ $sel_section ],
				'include_children' => true,
			];
			$q->set( 'tax_query', $tax );
			return;
		}

		// Else if a Menu is chosen -> collect its Sections and filter by them.
		if ( $sel_menu > 0 ) {
			$sections = get_terms( [
				'taxonomy'   => self::TAX_SECTION,
				'hide_empty' => 0,
				'fields'     => 'ids',
				'meta_query' => [
					[
						'key'     => self::META_MENU_OWNER,
						'value'   => $sel_menu,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			] );
			if ( ! is_wp_error( $sections ) && ! empty( $sections ) ) {
				$tax = (array) $q->get( 'tax_query', [] );
				$tax[] = [
					'taxonomy'         => self::TAX_SECTION,
					'field'            => 'term_id',
					'terms'            => array_map( 'intval', $sections ),
					'include_children' => true,
				];
				$q->set( 'tax_query', $tax );
			} else {
				// No sections owned by that menu — show none.
				$q->set( 'post__in', [ 0 ] );
			}
		}
	}

	/** Load the small JS only on Items list screen. */
	public static function enqueue_js( $hook ) : void {
		// Only on the items list table
		if ( $hook !== 'edit.php' ) return;
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::CPT_ITEM ) return;

		$handle = 'jprm-items-list';

		// URL to includes/admin/assets/jprm-items-list.js
		$src_path = plugin_dir_path( __FILE__ ) . 'assets/jprm-items-list.js';
		$src_url  = plugin_dir_url( __FILE__ ) . 'assets/jprm-items-list.js';
		$version  = file_exists( $src_path ) ? filemtime( $src_path ) : false;

		wp_enqueue_script( $handle, $src_url, [], $version, true );

		wp_localize_script( $handle, 'JPRM_ITEMS', [
			'rest' => [
				'sectionsByMenu' => esc_url_raw( rest_url( 'jprm/v1/menu-builder/sections' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
			],
			'qs' => [
				'menu'    => self::Q_MENU,
				'section' => self::Q_SECTION,
			],
			'labels' => [
				'allSections' => __( 'All Sections', 'jprm' ),
			],
		] );
	}
}
