<?php
namespace JelloPoint\RestaurantMenu\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sections_By_Menu_Controller {

	const NAMESPACE          = 'jprm/v1';
	const ROUTE              = '/sections';

	/** Canonical keys (confirmed) */
	const TAX_MENU           = 'jprm_menu';
	const TAX_SECTION        = 'jprm_section';
	const META_MENU_OWNER    = '_jprm_menu_term_id';
	const META_SECTION_ORDER = '_jprm_section_order';

	public static function register() : void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'menu' => [
						'description' => 'Menu (jprm_menu) as term ID, slug, or name.',
						'type'        => 'string',
						'required'    => true,
					],
				],
			]
		);
	}

	/**
	 * Normalize 'menu' to a valid term_id in taxonomy jprm_menu.
	 */
	protected static function normalize_menu_to_id( $menu ) : int {
		if ( is_numeric( $menu ) ) {
			$tid  = (int) $menu;
			$term = get_term( $tid, self::TAX_MENU );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( $menu === '' ) return 0;

		// slug
		$term = get_term_by( 'slug', $menu, self::TAX_MENU );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		// name
		$term = get_term_by( 'name', $menu, self::TAX_MENU );
		if ( $term && ! is_wp_error( $term ) ) return (int) $term->term_id;

		return 0;
	}

	public static function handle( \WP_REST_Request $req ) {
		$menu_raw = $req->get_param( 'menu' );
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			// Keep prior consumer behavior: empty map when no results/invalid.
			return rest_ensure_response( [] );
		}

		/**
		 * Fetch sections that are OWNED by this menu (single source of truth),
		 * ordered by _jprm_section_order (ASC) with name as a tie-breaker.
		 */
		$terms = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => self::META_MENU_OWNER,
					'value' => (string) $menu_id,
				],
			],
			'meta_key'   => self::META_SECTION_ORDER,
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return rest_ensure_response( [] );
		}

		// Stable secondary sort by name for equal/missing order values.
		usort( $terms, static function( $a, $b ) {
			$ao = (int) get_term_meta( $a->term_id, self::META_SECTION_ORDER, true );
			$bo = (int) get_term_meta( $b->term_id, self::META_SECTION_ORDER, true );
			if ( $ao !== $bo ) return $ao <=> $bo;
			return strcasecmp( (string) $a->name, (string) $b->name );
		} );

		// Response shape expected by consumer: map "term_id" => "name".
		$out = [];
		foreach ( $terms as $t ) {
			$out[ (string) (int) $t->term_id ] = (string) $t->name;
		}

		return rest_ensure_response( $out );
	}
}
