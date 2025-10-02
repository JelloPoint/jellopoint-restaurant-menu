<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Central admin menu registrar for the JelloPoint plugin.
 * - Ensures a single top-level "JelloPoint Menu"
 * - Provides a stable parent slug for all submenus
 * - Avoids duplicate roots; runs late to reuse existing parent if already registered
 */
class Admin_Menu {

	/** Cached detected parent slug */
	protected static $parent_slug = null;

	/** Submenu registrations queued by other components */
	protected static $queued_submenus = [];

	public static function init() : void {
		// Run late so any previously-registered parent can be reused.
		add_action( 'admin_menu', [ __CLASS__, 'ensure_parent_and_attach_submenus' ], 99 );
	}

	/**
	 * Public API: enqueue a submenu to be attached under the JelloPoint parent.
	 * Example:
	 * Admin_Menu::register_submenu([
	 *   'page_title' => 'Price Labels',
	 *   'menu_title' => 'Price Labels',
	 *   'capability' => 'manage_options',
	 *   'menu_slug'  => 'jprm-price-labels',
	 *   'callback'   => [ClassName::class, 'render_page'],
	 *   'position'   => 10,
	 * ]);
	 */
	public static function register_submenu( array $args ) : void {
		self::$queued_submenus[] = $args;
	}

	/**
	 * Returns the detected/created parent slug (string) or empty string if not available yet.
	 */
	public static function get_parent_slug() : string {
		if ( is_string( self::$parent_slug ) ) return self::$parent_slug;

		// 1) Try candidates from filter or common names
		global $admin_page_hooks, $menu;
		$candidates = apply_filters( 'jprm/admin_parent_slug_candidates', [] );
		if ( ! is_array( $candidates ) ) $candidates = [];
		$common = [ 'jellopoint-menu', 'jellopoint_root', 'jellopoint', 'jprm_root' ];
		$candidates = array_values( array_unique( array_merge( $candidates, $common ) ) );

		foreach ( $candidates as $slug ) {
			if ( isset( $admin_page_hooks[ $slug ] ) ) {
				self::$parent_slug = $slug;
				return self::$parent_slug;
			}
		}

		// 2) Scan admin menu titles for anything containing "JelloPoint"
		if ( is_array( $menu ) ) {
			foreach ( $menu as $m ) {
				$title = isset( $m[0] ) ? wp_strip_all_tags( (string)$m[0] ) : '';
				$slug  = isset( $m[2] ) ? (string)$m[2] : '';
				if ( $title !== '' && stripos( $title, 'jellopoint' ) !== false && $slug !== '' ) {
					self::$parent_slug = $slug;
					return self::$parent_slug;
				}
			}
		}

		// Not found yet.
		self::$parent_slug = '';
		return self::$parent_slug;
	}

	/**
	 * Creates the parent if still missing, then attaches any queued submenus.
	 * Does NOT create a duplicate parent if one already exists.
	 */
	public static function ensure_parent_and_attach_submenus() : void {
		$parent = self::get_parent_slug();

		// If still not found, create the parent now (once).
		if ( $parent === '' ) {
			$parent = 'jellopoint-menu';
			add_menu_page(
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), // page title
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ), // menu title
				'manage_options',
				$parent,     // slug we standardize on
				'__return_null',
				'dashicons-store',
				56
			);
			self::$parent_slug = $parent; // cache it
		}

		// Attach queued submenus
		if ( ! empty( self::$queued_submenus ) ) {
			foreach ( self::$queued_submenus as $args ) {
				$page_title = $args['page_title'] ?? '';
				$menu_title = $args['menu_title'] ?? $page_title;
				$capability = $args['capability'] ?? 'manage_options';
				$menu_slug  = $args['menu_slug']  ?? '';
				$callback   = $args['callback']   ?? '__return_null';
				$position   = isset($args['position']) ? (int)$args['position'] : null;

				if ( $page_title && $menu_title && $menu_slug ) {
					add_submenu_page(
						self::$parent_slug,
						$page_title,
						$menu_title,
						$capability,
						$menu_slug,
						$callback,
						$position
					);
				}
			}
			// Clear the queue once attached
			self::$queued_submenus = [];
		}
	}
}
