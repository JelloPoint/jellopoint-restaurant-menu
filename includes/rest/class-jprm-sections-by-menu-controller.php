<?php
namespace JelloPoint\RestaurantMenu\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sections_By_Menu_Controller {

	const NAMESPACE = 'jprm/v1';
	const ROUTE     = '/sections';

	public static function register() : void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle' ],
				'permission_callback' => function () {
					// Elementor editor context → user editing posts.
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'menu' => [
						'description' => 'Menu (jprm_menu) as term ID or slug.',
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
			$tid = (int) $menu;
			$term = get_term( $tid, 'jprm_menu' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( '' === $menu ) {
			return 0;
		}
		// Try slug first.
		$term = get_term_by( 'slug', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		// Try name second.
		$term = get_term_by( 'name', $menu, 'jprm_menu' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		return 0;
	}

	public static function handle( \WP_REST_Request $req ) {
		$menu_raw = $req->get_param( 'menu' );
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			return rest_ensure_response( [] ); // empty map → "No results found" UI
		}

		// Query helper: collect section terms used by items in a given menu.
		$get_sections_for_menu = function( bool $suppress_filters ) use ( $menu_id ) : array {
			$q = new \WP_Query( [
				'post_type'        => 'jprm_menu_item',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'tax_query'        => [
					[
						'taxonomy' => 'jprm_menu',
						'field'    => 'term_id',
						'terms'    => [ $menu_id ],
					],
				],
				// When false: language/other filters active (default).
				// When true: bypass filters (fallback to be extra tolerant).
				'suppress_filters' => $suppress_filters,
			] );

			if ( empty( $q->posts ) ) {
				return [];
			}

			$section_map = [];
			foreach ( $q->posts as $pid ) {
				$terms = wp_get_post_terms( $pid, 'jprm_section' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$section_map[ (string) $t->term_id ] = $t->name;
					}
				}
			}
			return $section_map;
		};

		// First pass: normal (respect language filters)
		$sections = $get_sections_for_menu( false );

		// Fallback: bypass filters in case editor context differs (WPML/Polylang)
		if ( empty( $sections ) ) {
			$sections = $get_sections_for_menu( true );
		}

		// Sort for stable UI.
		if ( ! empty( $sections ) ) {
			asort( $sections, SORT_FLAG_CASE | SORT_NATURAL );
		}

		return rest_ensure_response( $sections );
	}
}
