<?php
namespace JelloPoint\RestaurantMenu\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class Menu_Builder_Controller extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'jprm/v1';
        $this->rest_base = 'menu-builder';
    }

    public function register_routes() : void {

        // GET /wp-json/jprm/v1/menu-builder/menus
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/menus', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
                'callback'            => [ $this, 'get_menus' ],
            ],
        ] );

        // GET /wp-json/jprm/v1/menu-builder/sections?menu_id=123
        // For now we return all sections; later we can filter by relation.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
                'callback'            => [ $this, 'get_sections' ],
                'args'                => [
                    'menu_id' => [ 'type' => 'integer', 'required' => false ],
                ],
            ],
        ] );

        // POST /wp-json/jprm/v1/menu-builder/section  (create section term)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => function () { return current_user_can( 'manage_categories' ); },
                'callback'            => [ $this, 'create_section' ],
                'args'                => [
                    'name'   => [ 'type' => 'string', 'required' => true ],
                    'parent' => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
                    // optional: 'menu_id' => [ 'type' => 'integer', 'required' => false ],
                ],
            ],
        ] );
    }

    public function get_menus( WP_REST_Request $req ) {
        $terms = get_terms([
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ]);

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $items = array_map(function( $t ){
            return [ 'id' => (int) $t->term_id, 'title' => $t->name ];
        }, $terms );

        return rest_ensure_response( [ 'menus' => $items ] );
    }

    public function get_sections( WP_REST_Request $req ) {
        // In Phase 1 we ignore menu_id and list all sections.
        // Later we’ll filter by relation once we define one.
        $terms = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
        ]);

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $items = array_map(function( $t ){
            return [
                'id'         => (int) $t->term_id,
                'title'      => $t->name,
                'parent_id'  => (int) $t->parent,
                'menu_order' => 0, // placeholder; we’ll introduce a term order meta when we wire drag-drop save.
                'count'      => (int) $t->count,
            ];
        }, $terms );

        return rest_ensure_response( [ 'sections' => $items ] );
    }

    public function create_section( WP_REST_Request $req ) {
        $name   = trim( (string) $req->get_param('name') );
        $parent = (int) $req->get_param('parent');

        if ( $name === '' ) {
            return new WP_Error( 'jprm_empty', __( 'Section name is required.', 'jprm' ), [ 'status' => 400 ] );
        }

        $res = wp_insert_term( $name, 'jprm_section', [
            'parent' => $parent > 0 ? $parent : 0,
        ] );

        if ( is_wp_error( $res ) ) {
            return $res;
        }

        $term = get_term( (int) $res['term_id'], 'jprm_section' );
        return rest_ensure_response([
            'id'    => (int) $term->term_id,
            'title' => $term->name,
            'parent_id' => (int) $term->parent,
        ]);
    }
}
