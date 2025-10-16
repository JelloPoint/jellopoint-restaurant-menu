<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap (CPT/Tax, Elementor, assets) + editor AJAX endpoint.
 * Includes a debug facility to trace filtering logic end-to-end.
 */
class Plugin {

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Canonical meta key on jprm_section that links a Section to a Menu term_id.
	 * You can override via filter: add_filter('jprm/sections_menu_meta_key', fn()=>'_your_key_');
	 */
	private const DEFAULT_SECTION_MENU_META_KEY = '_jprm_menu_id';

	/**
	 * Entry point.
	 */
	public static function init() : void {
		if ( self::$bootstrapped ) return;
		self::$bootstrapped = true;

		// Types & taxonomies.
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Elementor.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Assets.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_editor_assets' ] );

		// Editor AJAX endpoint (always available).
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );

		// Optional modules (if present).
		add_action( 'init', [ __CLASS__, 'load_modules' ], 11 );
	}

	/* =======================================================================
	 * Optional modules (loaded if present; safe to skip)
	 * ======================================================================= */
	public static function load_modules() : void {
		$base_dir = defined( 'JPRM_PLUGIN_DIR' ) ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../';

		$sections_meta_ui = trailingslashit( $base_dir ) . 'includes/admin/class-jprm-sections-meta-ui.php';
		if ( file_exists( $sections_meta_ui ) ) {
			require_once $sections_meta_ui;
			if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI' ) ) {
				\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI::init();
			}
		}

		$inspector_sc = trailingslashit( $base_dir ) . 'includes/debug/class-inspector-shortcode.php';
		if ( file_exists( $inspector_sc ) ) {
			require_once $inspector_sc;
			if ( class_exists( '\JelloPoint\RestaurantMenu\Debug\Inspector_Shortcode' ) ) {
				\JelloPoint\RestaurantMenu\Debug\Inspector_Shortcode::init();
			}
		}
	}

	/* =======================================================================
	 * CPT + Taxonomies
	 * ======================================================================= */
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
					'labels'       => [
						'name'          => __( 'Menus', 'jellopoint-restaurant-menu' ),
						'singular_name' => __( 'Menu', 'jellopoint-restaurant-menu' ),
						'add_new_item'  => __( 'Add Menu', 'jellopoint-restaurant-menu' ),
						'edit_item'     => __( 'Edit Menu', 'jellopoint-restaurant-menu' ),
					],
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
					'labels'       => [
						'name'          => __( 'Sections', 'jellopoint-restaurant-menu' ),
						'singular_name' => __( 'Section', 'jellopoint-restaurant-menu' ),
						'add_new_item'  => __( 'Add Section', 'jellopoint-restaurant-menu' ),
						'edit_item'     => __( 'Edit Section', 'jellopoint-restaurant-menu' ),
					],
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);
		}
	}

	/* =======================================================================
	 * Assets
	 * ======================================================================= */
	public static function register_assets() : void {
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			$base_url = defined( 'JPRM_PLUGIN_URL' ) ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
			$rel_css  = 'includes/render/css/menu.css';

			$abs_css  = trailingslashit( defined( 'JPRM_PLUGIN_DIR' ) ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../' ) . $rel_css;
			$url_css  = trailingslashit( $base_url ) . $rel_css;
			$ver      = defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null;

			if ( file_exists( $abs_css ) ) {
				wp_register_style( 'jprm-menu', $url_css, [], $ver );
			} else {
				wp_register_style( 'jprm-menu', trailingslashit( $base_url ) . 'assets/css/frontend.css', [], $ver );
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

	/* =======================================================================
	 * Elementor registration
	 * ======================================================================= */
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

	/* =======================================================================
	 * Built-in editor AJAX: jprm_sections_by_menu (+ DEBUG)
	 * ======================================================================= */

	public static function ajax_sections_by_menu() : void {
		// Always keep the editor usable; never hard-error.
		$can_edit     = current_user_can( 'edit_posts' );
		$debug_req    = isset( $_REQUEST['debug'] ) && ( '1' === $_REQUEST['debug'] || 'true' === $_REQUEST['debug'] );
		$debug_enable = $can_edit && $debug_req; // only admins/editors can see debug

		// Nonce: if invalid, still deliver ALL sections so UI doesn't break.
		$nonce_ok = ( isset( $_REQUEST['_ajax_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ), 'jprm_sections' ) );

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		$meta_key = apply_filters( 'jprm/sections_menu_meta_key', self::DEFAULT_SECTION_MENU_META_KEY );

		// Gather mapped section IDs by meta.
		$mapped_ids = [];
		if ( $menu_id > 0 ) {
			$mapped_ids = self::get_section_ids_from_meta( $menu_id, $meta_key );
		}

		// Merge optional registry from option (if present).
		$opt_ids = [];
		$opt     = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$opt_ids = array_map( 'intval', $opt[ $menu_id ] );
		}

		$ids = array_values( array_unique( array_filter( array_merge( $mapped_ids, $opt_ids ), static fn( $n ) => $n > 0 ) ) );

		// Load terms according to what we found; fall back to ALL.
		$terms = [];
		if ( ! empty( $ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $ids,
			] );
		}
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
		}
		$map = self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );

		// Optional DEBUG payload (visible only when debug=1 and user can edit posts).
		if ( $debug_enable ) {
			$debug_terms_sample = [];
			$all_terms_for_sample = is_array( $terms ) ? $terms : [];
			$sample_count = 0;
			foreach ( $all_terms_for_sample as $t ) {
				if ( $sample_count >= 8 ) break; // cap
				$debug_terms_sample[] = [
					'id'       => (int) $t->term_id,
					'name'     => $t->name,
					'parent'   => (int) $t->parent,
					'meta_val' => get_term_meta( (int) $t->term_id, $meta_key, true ),
				];
				$sample_count++;
			}

			wp_send_json_success( [
				'options' => $map,                  // id => label
				'debug'   => [
					'nonce_ok'     => (bool) $nonce_ok,
					'menu_raw'     => $menu_raw,
					'resolved_menu_id' => (int) $menu_id,
					'meta_key'     => $meta_key,
					'mapped_ids'   => $mapped_ids,
					'opt_ids'      => $opt_ids,
					'delivered'    => array_keys( $map ),
					'sample_terms' => $debug_terms_sample,
				],
			], 200 );
		}

		// Normal payload.
		wp_send_json_success( $map, 200 );
	}

	/* =======================================================================
	 * Helpers
	 * ======================================================================= */

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

	private static function get_section_ids_from_meta( int $menu_id, string $meta_key ) : array {
		$terms = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
		] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) return [];

		$out = [];
		foreach ( $terms as $t ) {
			$val = get_term_meta( (int) $t->term_id, $meta_key, true );
			if ( $val === '' || $val === null ) continue;
			if ( (int) $val === $menu_id ) $out[] = (int) $t->term_id;
		}
		return $out;
	}

	private static function terms_to_hierarchical_options( array $terms ) : array {
		if ( empty( $terms ) ) return [];

		$by_parent = [];
		foreach ( $terms as $t ) $by_parent[ (int) $t->parent ][] = $t;

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

/** Bootstrap */
\JelloPoint\RestaurantMenu\Plugin::init();
