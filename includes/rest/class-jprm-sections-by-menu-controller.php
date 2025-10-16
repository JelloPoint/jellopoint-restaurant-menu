<?php
namespace JelloPoint\RestaurantMenu\Rest;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sections_By_Menu_Controller {

	const NAMESPACE = 'jprm/v1';
	const ROUTE     = '/sections';

	public static function register() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_route' ] );
	}

	public static function register_route() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle' ],
				'permission_callback' => function () {
					// Only in admin/editor context; still harmless if public.
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'menu' => [
						'description' => 'Menu term ID (jprm_menu).',
						'type'        => 'integer',
						'required'    => true,
					],
				],
			]
		);
	}

	public static function handle( \WP_REST_Request $req ) {
		$menu_id = (int) $req->get_param( 'menu' );
		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'bad_request', 'Missing menu id', [ 'status' => 400 ] );
		}

		// Query items under this Menu to discover used Sections.
		$q = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy' => 'jprm_menu',
					'field'    => 'term_id',
					'terms'    => [ $menu_id ],
				],
			],
			'fields'         => 'ids',
			'suppress_filters' => false, // WPML/Polylang friendly
		] );

		$section_counts = [];
		if ( $q->posts ) {
			foreach ( $q->posts as $pid ) {
				$terms = wp_get_post_terms( $pid, 'jprm_section' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						if ( ! isset( $section_counts[ $t->term_id ] ) ) {
							$section_counts[ $t->term_id ] = [ 'id' => $t->term_id, 'name' => $t->name, 'count' => 0 ];
						}
						$section_counts[ $t->term_id ]['count']++;
					}
				}
			}
		}

		// Sort by name asc for nice UX.
		uasort( $section_counts, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		// Return as a flat id=>label map (Elementor SELECT2 likes that).
		$out = [];
		foreach ( $section_counts as $row ) {
			$out[ (string) $row['id'] ] = $row['name'];
		}

		return rest_ensure_response( $out );
	}
}
