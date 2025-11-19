<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap: CPT/Tax, Elementor, assets, and the editor AJAX endpoint
 * that filters Sections by the selected Menu using the exact term-meta key:
 *
 *   _jprm_menu_term_id  (integer, jprm_menu term_id)
 *
 * No guessing, no extras.
 */
class Plugin {

	private static $bootstrapped = false;

	/** Canonical meta key on jprm_section that links a Section to its owning Menu term_id. */
	private const SECTION_MENU_META_KEY = '_jprm_menu_term_id';

	public static function init() : void {
		if ( self::$bootstrapped ) return;
		self::$bootstrapped = true;

		// Types & taxonomies.
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Elementor.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Assets for frontend/editor.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_editor_assets' ] );

		// Editor AJAX for Sections -> Menu filtering.
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );
	}

	/* =========================
	 * CPT + Taxonomies
	 * ========================= */

	public static function register_types() : void {
		if ( post_type_exists( 'jprm_menu_item' ) ) return;

		register_post_type(
			'jprm_menu_item',
			[
				'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
				'labels'       => [
					'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
					'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
					'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
					'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
				],
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'page-attributes' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'menu-item' ],
				'menu_icon'    => 'dashicons-carrot',
			]
		);
	}

	public static function register_taxonomies() : void {
		if ( ! taxonomy_exists( 'jprm_menu' ) ) {
			register_taxonomy(
				'jprm_menu',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Menus', 'jellopoint-restaurant-menu' ),
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}
		if ( ! taxonomy_exists( 'jprm_section' ) ) {
			register_taxonomy(
				'jprm_section',
				[ 'jprm_menu_item' ],
				[
					'label'        => __( 'Sections', 'jellopoint-restaurant-menu' ),
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}
	}

	/* =========================
	 * Assets
	 * ========================= */

	public static function register_assets() : void {
		// Register the widget stylesheet so Elementor can enqueue it via get_style_depends().
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			// Absolute path to assets/css/menu.css relative to this file.
			$abs_css = __DIR__ . '/assets/menu.css';
			// URL built relative to this file (this file is in /includes/), so we pass __FILE__
			// and a relative path from /includes/ to /assets/css/menu.css.
			$url_css = plugins_url( 'assets/css/menu.css', __FILE__ );

			$ver = defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null;

			// Even if the file check fails on some environments, still register so Elementor can try to enqueue.
			if ( file_exists( $abs_css ) ) {
				wp_register_style( 'jprm-menu', $url_css, [], $ver );
			} else {
				// Fallback: still register with the computed URL (helps in symlinked or atypical installs).
				wp_register_style( 'jprm-menu', $url_css, [], $ver );
			}
		}
	}

	public static function enqueue_editor_styles() : void {
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) self::register_assets();
		wp_enqueue_style( 'jprm-menu' );
	}

	public static function enqueue_elementor_editor_assets() : void {
		$base_url = defined( 'JPRM_PLUGIN_URL' ) ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
		$handle   = 'jprm-elementor-sections-dep';
		$src      = trailingslashit( $base_url ) . 'assets/admin/elementor-sections-dep.js';

		wp_register_script(
			$handle,
			$src,
			[ 'jquery', 'elementor-editor' ],
			defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null,
			true
		);

		wp_localize_script(
			$handle,
			'JPRMAjax',
			[
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'jprm_sections' ),
			]
		);

		wp_enqueue_script( $handle );
	}

	/* =========================
	 * Elementor registration
	 * ========================= */

	public static function register_elementor_category( $elements_manager ) : void {
		if ( is_object( $elements_manager ) && method_exists( $elements_manager, 'add_category' ) ) {
			$elements_manager->add_category(
				'jellopoint-widgets',
				[
					'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
					'icon'  => 'fa fa-plug',
				]
			);
		}
	}

	public static function register_elementor_widget( $widgets_manager ) : void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

		$file = defined( 'JPRM_PLUGIN_DIR' )
			? trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/widgets/class-restaurant-menu.php'
			: plugin_dir_path( __DIR__ ) . 'widgets/class-restaurant-menu.php';

		if ( file_exists( $file ) ) require_once $file;

		if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) && is_object( $widgets_manager ) ) {
			$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
		}
	}

	/* =========================
	 * Editor AJAX
	 * ========================= */

	/**
	 * Returns Sections for the selected Menu (Elementor editor).
	 * Uses ONLY the term-meta key: _jprm_menu_term_id (int menu term_id).
	 * If no sections are mapped yet, falls back to ALL sections (hierarchical).
	 */
	public static function ajax_sections_by_menu() : void {
		// Keep the editor usable even on nonce/capability issues.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}
		if ( ! isset( $_REQUEST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ), 'jprm_sections' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		$ids = self::get_section_ids_from_meta( $menu_id );

		if ( ! empty( $ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $ids,
			] );
			if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_send_json_success( self::terms_to_hierarchical_options( $terms ), 200 );
			}
		}

		// Fallback: all sections, hierarchical.
		wp_send_json_success( self::all_sections_map(), 200 );
	}

	/* =========================
	 * Helpers
	 * ========================= */

	/** Normalize menu input (id/slug/name) to term_id in jprm_menu. */
	private static function normalize_menu_to_id( $menu ) : int {
		if ( is_numeric( $menu ) ) {
			$tid  = (int) $menu;
			$term = get_term( $tid, 'jprm_menu' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( $menu === '' ) return 0;

		$term = get_term_by( 'slug', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		$term = get_term_by( 'name', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		return 0;
	}

	/** Get section IDs where _jprm_menu_term_id equals the given $menu_id. */
	private static function get_section_ids_from_meta( int $menu_id ) : array {
		$terms = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
		] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) return [];

		$out = [];
		foreach ( $terms as $t ) {
			$val = get_term_meta( (int) $t->term_id, self::SECTION_MENU_META_KEY, true );
			if ( $val === '' || $val === null ) continue;
			if ( (int) $val === $menu_id ) {
				$out[] = (int) $t->term_id;
			}
		}
		return $out;
	}

	/** Return ALL Sections as id => label (hierarchical). */
	private static function all_sections_map() : array {
		$terms = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
		] );
		return self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );
	}

	/** Build id => label map with hierarchy indentation ("— " per depth). */
	private static function terms_to_hierarchical_options( array $terms ) : array {
		if ( empty( $terms ) ) return [];

		$by_parent = [];
		foreach ( $terms as $t ) {
			$by_parent[ (int) $t->parent ][] = $t;
		}

		$make_label = static function( \WP_Term $term ) : string {
			$depth  = count( get_ancestors( (int) $term->term_id, 'jprm_section', 'taxonomy' ) );
			$indent = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			return $indent . $term->name;
		};

		$roots = $by_parent[0] ?? [];
		usort( $roots, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );

		$out = [];

		$walk = static function ( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) : void {
			if ( empty( $by_parent[ $parent_id ] ) ) return;
			$children = $by_parent[ $parent_id ];
			usort( $children, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			foreach ( $children as $child ) {
				$out[ (string) $child->term_id ] = $make_label( $child );
				$walk( (int) $child->term_id );
			}
		};

		foreach ( $roots as $root ) {
			$out[ (string) $root->term_id ] = $make_label( $root );
			$walk( (int) $root->term_id );
		}

		return $out;
	}
}

\JelloPoint\RestaurantMenu\Plugin::init();
