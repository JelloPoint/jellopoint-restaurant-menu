<?php
namespace JelloPoint\RestaurantMenu\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sections_By_Menu_Controller {

	const NAMESPACE = 'jprm/v1';
	const ROUTE     = '/sections';

	/** Canonical keys (confirmed) */
	const TAX_MENU            = 'jprm_menu';
	const TAX_SECTION         = 'jprm_section';
	const META_SECTION_ORDER  = '_jprm_section_order';

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
			$tid  = (int) $menu;
			$term = get_term( $tid, self::TAX_MENU );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		$menu = (string) $menu;
		if ( '' === $menu ) {
			return 0;
		}
		// Try slug first.
		$term = get_term_by( 'slug', $menu, self::TAX_MENU );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		// Try name second.
		$term = get_term_by( 'name', $menu, self::TAX_MENU );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		return 0;
	}

	public static function handle( \WP_REST_Request $req ) {
		$menu_raw = $req->get_param( 'menu' );
		$menu_id  = self::normalize_menu_to_id( $menu_raw );

		if ( $menu_id <= 0 ) {
			// Response shape remains an object/map in consumer JS:
			// previously empty array worked as "no results". Keep that behavior.
			return rest_ensure_response( [] );
		}

		/**
		 * Query helper: collect section terms used by items in a given menu.
		 * NOTE: We do not change selection logic; we only change the final sort order.
		 */
		$get_sections_for_menu = function( bool $suppress_filters ) use ( $menu_id ) : array {
			$q = new \WP_Query( [
				'post_type'        => 'jprm_menu_item',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'tax_query'        => [
					[
						'taxonomy' => self::TAX_MENU,
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

			// Build a unique set of sections with their order meta for sorting.
			$tmp = []; // [term_id => ['id'=>int,'name'=>string,'order'=>int]]
			foreach ( $q->posts as $pid ) {
				$terms = wp_get_post_terms( $pid, self::TAX_SECTION );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$tid = (int) $t->term_id;
						if ( isset( $tmp[ $tid ] ) ) {
							continue;
						}
						$tmp[ $tid ] = [
							'id'    => $tid,
							'name'  => (string) $t->name,
							'order' => (int) get_term_meta( $tid, self::META_SECTION_ORDER, true ),
						];
					}
				}
			}

			if ( empty( $tmp ) ) {
				return [];
			}

			// Sort by _jprm_section_order ASC, then by name to break ties.
			uasort( $tmp, static function( $a, $b ) {
				$ao = (int) ( $a['order'] ?? 0 );
				$bo = (int) ( $b['order'] ?? 0 );
				if ( $ao !== $bo ) {
					return $ao <=> $bo;
				}
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			} );

			// Return shape remains: map "term_id" => "name"
			$out = [];
			foreach ( $tmp as $row ) {
				$out[ (string) $row['id'] ] = $row['name'];
			}
			return $out;
		};

		// First pass: normal (respect language filters)
		$sections = $get_sections_for_menu( false );

		// Fallback: bypass filters in case editor context differs (WPML/Polylang, etc.)
		if ( empty( $sections ) ) {
			$sections = $get_sections_for_menu( true );
		}

		// We already returned in desired order; no further sorting needed.
		return rest_ensure_response( $sections );
	}
}