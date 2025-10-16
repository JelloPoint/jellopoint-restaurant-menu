<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap: registers CPT/Tax, Elementor, assets, and provides
 * a built-in editor AJAX endpoint so the Elementor panel always works.
 *
 * This class intentionally guarantees the AJAX route exists here, even if
 * other admin modules fail to load.
 */
class Plugin {

	/** @var bool */
	private static $bootstrapped = false;

	/** Canonical meta key stored on jprm_section terms that links a Section to a Menu term_id. */
	private const SECTION_MENU_META_KEY = '_jprm_menu_id';

	/**
	 * Entry point.
	 */
	public static function init() : void {
		if ( self::$bootstrapped ) {
			return;
		}
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

		// Editor AJAX endpoint (built-in, always available).
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );

		// Optional: load modular classes (kept for maintainability, not required).
		add_action( 'init', [ __CLASS__, 'load_modules' ], 11 );
	}

	/* =======================================================================
	 * Optional modules (loaded if present; safe to skip)
	 * ======================================================================= */

	public static function load_modules() : void {
		$base_dir = defined( 'JPRM_PLUGIN_DIR' ) ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../';

		// Admin: sections meta UI (owning menu dropdown). Optional.
		$sections_meta_ui = trailingslashit( $base_dir ) . 'includes/admin/class-jprm-sections-meta-ui.php';
		if ( file_exists( $sections_meta_ui ) ) {
			require_once $sections_meta_ui;
			if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI' ) ) {
				\JelloPoint\RestaurantMenu\Admin\JPRM_Sections_Meta_UI::init();
			}
		}

		// Debug inspector shortcode. Optional.
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
		if ( post_type_exists( 'jprm_menu_item' ) ) {
			return;
		}

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
				// List screen attachment to a parent menu is handled elsewhere.
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
		// Menus (e.g., Lunch, Dinner).
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

		// Sections (hierarchical).
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
		// Register (not enqueue) the frontend stylesheet used by the widget/shortcodes.
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
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			self::register_assets();
		}
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

		// Provide ajaxurl + nonce to the script (used for the sections dropdown).
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
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$file = defined( 'JPRM_PLUGIN_DIR' )
			? trailingslashit( JPRM_PLUGIN_DIR ) . 'includes/widgets/class-restaurant-menu.php'
			: plugin_dir_path( __DIR__ ) . 'widgets/class-restaurant-menu.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}

		if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) && is_object( $widgets_manager ) ) {
			$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
		}
	}

	/* =======================================================================
	 * Built-in editor AJAX: jprm_sections_by_menu
	 * ======================================================================= */

	/**
	 * Returns sections for a selected Menu (Elementor editor).
	 * Source of truth: `_jprm_menu_id` on `jprm_section` (integer menu term_id).
	 * Fallback: if no mapping exists or anything fails, return **ALL** sections with hierarchy.
	 *
	 * Request: POST admin-ajax.php
	 *  - action: jprm_sections_by_menu
	 *  - menu: (string|int)
	 *  - _ajax_nonce: wp_create_nonce('jprm_sections')
	 */
	public static function ajax_sections_by_menu() : void {
		// Editor context — keep strict but don't break UX.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		// Nonce check; on failure still return ALL so editor stays usable.
		if ( ! isset( $_REQUEST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ), 'jprm_sections' ) ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		// No valid menu selected → show ALL sections (hierarchical).
		if ( $menu_id <= 0 ) {
			wp_send_json_success( self::all_sections_map(), 200 );
		}

		// A) Sections explicitly linked via _jprm_menu_id.
		$ids = self::get_section_ids_from_meta( $menu_id );

		// B) Optional registry from option (additive).
		$opt = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $opt[ $menu_id ] ) );
		}

		$ids = array_values( array_unique( array_filter( $ids, static fn( $n ) => $n > 0 ) ) );

		if ( ! empty( $ids ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => 'jprm_section',
					'hide_empty' => false,
					'include'    => $ids,
				]
			);
			if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_send_json_success( self::terms_to_hierarchical_options( $terms ), 200 );
			}
		}

		// C) Fallback: ALL sections (hierarchical).
		wp_send_json_success( self::all_sections_map(), 200 );
	}

	/* =======================================================================
	 * Helpers
	 * ======================================================================= */

	/** Normalize menu input (id/slug/name) to term_id in taxonomy jprm_menu. */
	private static function normalize_menu_to_id( $menu ) : int {
		if ( is_numeric( $menu ) ) {
			$tid  = (int) $menu;
			$term = get_term( $tid, 'jprm_menu' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( $menu === '' ) {
			return 0;
		}

		$term = get_term_by( 'slug', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$term = get_term_by( 'name', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}

	/** Return ALL sections as id => label (hierarchical indentation). */
	private static function all_sections_map() : array {
		$terms = get_terms(
			[
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			]
		);
		return self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );
	}

	/** Get section IDs where `_jprm_menu_id` equals the given menu_id. */
	private static function get_section_ids_from_meta( int $menu_id ) : array {
		$terms = get_terms(
			[
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			]
		);
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}
		$out = [];
		foreach ( $terms as $t ) {
			$val = get_term_meta( $t->term_id, self::SECTION_MENU_META_KEY, true );
			if ( $val === '' || $val === null ) {
				continue;
			}
			if ( (int) $val === $menu_id ) {
				$out[] = (int) $t->term_id;
			}
		}
		return $out;
	}

	/**
	 * Build a flat id => label map with hierarchy indentation for jprm_section terms.
	 * Uses em dash indentation ("— ") per depth level.
	 *
	 * @param \WP_Term[] $terms
	 * @return array<string,string>
	 */
	private static function terms_to_hierarchical_options( array $terms ) : array {
		if ( empty( $terms ) ) {
			return [];
		}

		// Index by parent term_id.
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
		usort(
			$roots,
			static function ( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

		$out = [];

		$walk = static function ( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) : void {
			if ( empty( $by_parent[ $parent_id ] ) ) {
				return;
			}
			$children = $by_parent[ $parent_id ];
			usort(
				$children,
				static function ( $a, $b ) {
					return strcasecmp( $a->name, $b->name );
				}
			);
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

/** Bootstrap the plugin class. */
\JelloPoint\RestaurantMenu\Plugin::init();
