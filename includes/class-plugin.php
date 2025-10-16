<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap (CPT/Tax, Elementor, assets).
 * Adds an AJAX endpoint for the Elementor editor to filter Sections by the selected Menu.
 */
class Plugin {

	/** @var bool */
	private static $bootstrapped = false;

	/**
	 * Public entry.
	 */
	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		// Types.
		add_action( 'init', [ __CLASS__, 'register_types' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Elementor.
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_elementor_category' ] );
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );

		// Styles (frontend registration + Elementor editor enqueue).
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_editor_styles' ] );

		// Editor-only assets (JS that filters Sections list by selected Menu).
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_editor_assets' ] );

		// AJAX endpoint for editor (no REST dependency).
		add_action( 'wp_ajax_jprm_sections_by_menu', [ __CLASS__, 'ajax_sections_by_menu' ] );
	}

	/* =========================
	 * CPT + Taxonomies
	 * ========================= */

	public static function register_types(): void {
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

	public static function register_taxonomies(): void {
		// High-level "Menu" grouping (e.g., Lunch, Dinner).
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

		// Sections within a Menu (e.g., Starters, Mains).
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

	/* =========================
	 * Assets
	 * ========================= */

	public static function register_assets(): void {
		// Register (not enqueue) the frontend stylesheet used by the widget/shortcodes.
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			$base = defined( 'JPRM_PLUGIN_URL' ) ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';

			// Prefer includes/render/css/menu.css (per your tree)
			$primary_rel = 'includes/render/css/menu.css';
			$primary_abs = trailingslashit( defined('JPRM_PLUGIN_DIR') ? JPRM_PLUGIN_DIR : plugin_dir_path( __DIR__ ) . '../' ) . $primary_rel;
			$primary_url = trailingslashit( $base ) . $primary_rel;

			$ver = defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : null;

			if ( file_exists( $primary_abs ) ) {
				wp_register_style( 'jprm-menu', $primary_url, [], $ver );
			} else {
				// Fallback (legacy path)
				wp_register_style( 'jprm-menu', trailingslashit( $base ) . 'assets/css/frontend.css', [], $ver );
			}
		}
	}

	public static function enqueue_editor_styles(): void {
		// Ensure style is available in Elementor editor preview.
		if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'jprm-menu' );
	}

	/* =========================
	 * Elementor
	 * ========================= */

	public static function register_elementor_category( $elements_manager ): void {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category(
			'jellopoint-widgets',
			[
				'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}

	public static function register_elementor_widget( $widgets_manager ): void {
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

	/* =========================
	 * Editor UX assets (AJAX-based)
	 * ========================= */

	/**
	 * Enqueue Elementor editor-only JS that watches the Menu control and updates the Sections control.
	 * Localize ajaxurl + nonce for secure calls.
	 */
	public static function enqueue_elementor_editor_assets(): void {
		$base_url = defined('JPRM_PLUGIN_URL') ? JPRM_PLUGIN_URL : plugin_dir_url( __DIR__ ) . '../';
		$handle   = 'jprm-elementor-sections-dep';
		$src      = trailingslashit( $base_url ) . 'assets/admin/elementor-sections-dep.js';

		wp_register_script(
			$handle,
			$src,
			[ 'jquery', 'elementor-editor' ],
			defined('JPRM_PLUGIN_VERSION') ? JPRM_PLUGIN_VERSION : null,
			true
		);

		// Provide ajaxurl + nonce to the script.
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

	/**
	 * AJAX: return **available Sections for a Menu** with hierarchy in labels.
	 * Strategy (in priority order):
	 *  A) Sections linked to the Menu via section term-meta (tolerant keys, any match).
	 *  B) Sections listed in option 'jprm_sections_catalog' (array: menu_id => [section_ids]).
	 *  C) Fallback: **all sections** (so the control stays useful even if nothing is mapped yet).
	 *
	 * NO filtering by “items in this menu” anymore (per your request).
	 *
	 * Action: jprm_sections_by_menu
	 * Params: menu (string|int), _ajax_nonce (via JPRMAjax.nonce)
	 */
	public static function ajax_sections_by_menu(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		check_ajax_referer( 'jprm_sections' );

		$menu_raw = isset( $_REQUEST['menu'] ) ? wp_unslash( $_REQUEST['menu'] ) : '';
		$menu_id  = self::normalize_menu_to_id( $menu_raw );
		if ( $menu_id <= 0 ) {
			wp_send_json_success( [] ); // empty map
		}

		// A) Sections explicitly linked to this menu via term meta.
		$sections_ids = self::get_section_ids_from_termmeta( $menu_id );

		// B) Add explicit catalog from option (if configured).
		$opt = get_option( 'jprm_sections_catalog' );
		if ( is_array( $opt ) && ! empty( $opt[ $menu_id ] ) && is_array( $opt[ $menu_id ] ) ) {
			$sections_ids = array_merge( $sections_ids, array_map( 'intval', $opt[ $menu_id ] ) );
		}

		$sections_ids = array_values( array_unique( array_filter( $sections_ids, static fn( $n ) => $n > 0 ) ) );

		$terms = [];
		if ( ! empty( $sections_ids ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
				'include'    => $sections_ids,
			] );
		}

		// C) Fallback: if no mapping exists, show **all sections**.
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
		}

		$map = self::terms_to_hierarchical_options( is_array( $terms ) ? $terms : [] );

		wp_send_json_success( $map );
	}

	/** Normalize menu input (id/slug/name) to term_id in taxonomy jprm_menu. */
	private static function normalize_menu_to_id( $menu ): int {
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

	/**
	 * Discover sections that "belong" to a menu via term meta on jprm_section.
	 * Accepted meta keys (any one is enough):
	 *   jprm_menu_id, _jprm_menu_id, jprm_menu, _jprm_menu, menu_id, _menu_id, jprm_menu_ids, _jprm_menu_ids
	 * Values can be a single ID, array of IDs, or CSV.
	 *
	 * @return int[] section term IDs
	 */
	private static function get_section_ids_from_termmeta( int $menu_id ): array {
		$terms = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$keys = [
			'jprm_menu_id', '_jprm_menu_id',
			'jprm_menu',    '_jprm_menu',
			'menu_id',      '_menu_id',
			'jprm_menu_ids','_jprm_menu_ids',
		];

		$out = [];
		foreach ( $terms as $t ) {
			foreach ( $keys as $k ) {
				$val = get_term_meta( $t->term_id, $k, true );
				if ( empty( $val ) ) {
					continue;
				}
				// Normalize to array of ints.
				$list = [];
				if ( is_array( $val ) ) {
					$list = $val;
				} elseif ( is_string( $val ) ) {
					// Split CSV if necessary.
					$list = preg_split( '/\s*,\s*/', $val );
				} else {
					$list = [ $val ];
				}
				$list = array_unique( array_map( 'intval', array_filter( $list, static function( $v ) {
					return ( '' !== $v && $v !== null );
				} ) ) );

				if ( in_array( $menu_id, $list, true ) ) {
					$out[] = (int) $t->term_id;
					break; // no need to check other keys for this term
				}
			}
		}

		return $out;
	}

	/**
	 * Build a flat id => label map with hierarchy indentation for jprm_section terms.
	 * Uses em-dash indentation ("— ") per depth level.
	 *
	 * @param \WP_Term[] $terms
	 * @return array<string,string>
	 */
	private static function terms_to_hierarchical_options( array $terms ): array {
		if ( empty( $terms ) ) {
			return [];
		}

		// Index by parent
		$by_parent = [];
		foreach ( $terms as $t ) {
			$by_parent[ (int) $t->parent ][] = $t;
		}

		$make_label = function( \WP_Term $term ): string {
			$depth = count( get_ancestors( (int) $term->term_id, 'jprm_section', 'taxonomy' ) );
			$indent = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			return $indent . $term->name;
		};

		$stack = isset( $by_parent[0] ) ? $by_parent[0] : [];
		// Sort root level alphabetical for stable UX.
		usort( $stack, static function( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		} );

		$out = [];

		$walk = function( $parent_id ) use ( &$walk, &$out, $by_parent, $make_label ) {
			if ( empty( $by_parent[ $parent_id ] ) ) return;
			$children = $by_parent[ $parent_id ];
			usort( $children, static function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			} );
			foreach ( $children as $child ) {
				$out[ (string) $child->term_id ] = $make_label( $child );
				$walk( (int) $child->term_id );
			}
		};

		// Emit root then descendents
		foreach ( $stack as $root ) {
			$out[ (string) $root->term_id ] = $make_label( $root );
			$walk( (int) $root->term_id );
		}

		return $out;
	}
}

/**
 * Ensure init() runs even if the bootstrap forgets to call it.
 * Safe due to the $bootstrapped guard above.
 */
\JelloPoint\RestaurantMenu\Plugin::init();
