<?php
namespace JelloPoint\RestaurantMenu\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) exit;

class Menu_Builder_Controller extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'jprm/v1';
        $this->rest_base = 'menu-builder';
    }

    public function register_routes() : void {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/menus', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
                'callback'            => [ $this, 'get_menus' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
                'callback'            => [ $this, 'get_sections' ],
                'args'                => [
                    'menu_id' => [ 'type' => 'integer', 'required' => true ],
                ],
            ],
        ] );
    }

    public function get_menus( WP_REST_Request $req ) {
        $q = new \WP_Query([
            'post_type'      => 'jprm_menu',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        $items = array_map(function( $id ){
            return [ 'id' => (int) $id, 'title' => get_the_title( $id ) ];
        }, $q->posts );

        return rest_ensure_response( [ 'menus' => $items ] );
    }

    public function get_sections( WP_REST_Request $req ) {
        $menu_id = (int) $req->get_param('menu_id');

        // We won’t assume hierarchical yet; we just fetch sections linked to this menu by meta
        $q = new \WP_Query([
            'post_type'      => 'jprm_section',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'meta_key'       => '_jprm_menu_id',
            'meta_value'     => $menu_id,
            'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
            'fields'         => 'ids',
        ]);

        $items = array_map(function( $id ){
            return [
                'id'         => (int) $id,
                'title'      => get_the_title( $id ),
                'parent_id'  => (int) get_post_meta( $id, '_jprm_parent_section_id', true ), // temporary until we flip to post_parent
                'menu_order' => (int) get_post_field( 'menu_order', $id ),
                'count'      => 0, // placeholder for item count (Phase 2)
            ];
        }, $q->posts );

        return rest_ensure_response( [ 'sections' => $items ] );
    }
}
