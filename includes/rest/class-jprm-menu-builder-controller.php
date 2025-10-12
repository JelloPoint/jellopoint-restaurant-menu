<?php
namespace JelloPoint\RestaurantMenu\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menu Builder REST API
 * - Menus: taxonomy 'jprm_menu'
 * - Sections: taxonomy 'jprm_section' (hierarchical)
 * - Per-menu ownership: section term meta '_jprm_menu_term_id' = menu term_id
 * - Sibling order: section term meta '_jprm_term_order'
 */
class Menu_Builder_Controller extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'jprm/v1';
        $this->rest_base = 'menu-builder';
    }

    public function register_routes() : void {

        // GET /wp-json/jprm/v1/ping  (diagnostics)
        register_rest_route( $this->namespace, '/ping', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => function(){ return ['ok'=>1,'time'=>time()]; },
        ]]);

        // GET /wp-json/jprm/v1/menu-builder/menus  (list jprm_menu terms)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/menus', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('edit_posts'); },
            'callback'            => [ $this, 'get_menus' ],
        ]]);

        // GET /wp-json/jprm/v1/menu-builder/sections?menu_id=###  (sections of a menu)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('edit_posts'); },
            'callback'            => [ $this, 'get_sections' ],
            'args'                => [
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // POST /wp-json/jprm/v1/menu-builder/sections/order  (save nesting+order)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections/order', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('manage_categories'); },
            'callback'            => [ $this, 'save_sections_order' ],
            'args'                => [
                'tree'    => [ 'type'=>'array',   'required'=>true ],
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // POST /wp-json/jprm/v1/menu-builder/section  (create a section in a menu)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('manage_categories'); },
            'callback'            => [ $this, 'create_section' ],
            'args'                => [
                'name'    => [ 'type'=>'string',  'required'=>true ],
                'parent'  => [ 'type'=>'integer', 'required'=>false, 'default'=>0 ],
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);
    }

    /** -------- Handlers -------- */

    public function get_menus( WP_REST_Request $req ) {
        $terms = get_terms([
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ]);
        if ( is_wp_error( $terms ) ) return $terms;

        $items = array_map(static function($t){
            return [ 'id' => (int) $t->term_id, 'title' => $t->name ];
        }, $terms );

        return rest_ensure_response([ 'menus' => $items ]);
    }

    public function get_sections( WP_REST_Request $req ) {
        $menu_id = (int) $req->get_param('menu_id');

        // Fetch all section IDs and filter by ownership meta
        $all_ids = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if ( is_wp_error( $all_ids ) ) return $all_ids;

        $ids = [];
        foreach ( (array) $all_ids as $tid ) {
            if ( (int) get_term_meta( $tid, '_jprm_menu_term_id', true ) === $menu_id ) {
                $ids[] = (int) $tid;
            }
        }

        // Build payload and sort (stable within siblings)
        $pairs = array_map(static function($id){
            $t = get_term( $id, 'jprm_section' );
            $o = (int) get_term_meta( $id, '_jprm_term_order', true );
            return [$t, $o];
        }, $ids);

        usort($pairs, static function($a,$b){
            [$ta,$oa] = $a; [$tb,$ob] = $b;
            if ( $ta->parent !== $tb->parent ) return 0;
            return $oa <=> $ob ?: strcasecmp( $ta->name, $tb->name );
        });

        $items = array_map(static function($pair){
            [$t,$o] = $pair;
            return [
                'id'         => (int) $t->term_id,
                'title'      => $t->name,
                'parent_id'  => (int) $t->parent,
                'menu_order' => (int) $o,
                'count'      => (int) $t->count,
            ];
        }, $pairs);

        return rest_ensure_response([ 'sections' => $items ]);
    }

    public function save_sections_order( WP_REST_Request $req ) {
        $tree    = $req->get_param('tree');
        $menu_id = (int) $req->get_param('menu_id');

        if ( ! is_array( $tree ) ) {
            return new WP_Error( 'jprm_bad_tree', __( 'Invalid tree payload.', 'jprm' ), [ 'status' => 400 ] );
        }

        foreach ( $tree as $node ) {
            $id    = isset($node['id']) ? (int) $node['id'] : 0;
            $pid   = isset($node['parent_id']) ? (int) $node['parent_id'] : 0;
            $order = isset($node['order']) ? (int) $node['order'] : 0;
            if ( $id <= 0 ) continue;

            // Guarantee ownership to the selected menu
            update_term_meta( $id, '_jprm_menu_term_id', $menu_id );

            // Update parent + order
            $term = get_term( $id, 'jprm_section' );
            if ( $term && ! is_wp_error( $term ) ) {
                if ( (int) $term->parent !== $pid ) {
                    $res = wp_update_term( $id, 'jprm_section', [ 'parent' => $pid ] );
                    if ( is_wp_error( $res ) ) return $res;
                }
                update_term_meta( $id, '_jprm_term_order', $order );
            }
        }

        // Return fresh list for the same menu
        $r = new WP_REST_Request( 'GET' );
        $r->set_param( 'menu_id', $menu_id );
        return $this->get_sections( $r );
    }

    public function create_section( WP_REST_Request $req ) {
        $name    = trim( (string) $req->get_param('name') );
        $parent  = (int) $req->get_param('parent', 0 );
        $menu_id = (int) $req->get_param('menu_id');

        if ( $name === '' ) {
            return new WP_Error( 'jprm_empty', __( 'Section name is required.', 'jprm' ), [ 'status' => 400 ] );
        }

        $res = wp_insert_term( $name, 'jprm_section', [ 'parent' => max( 0, $parent ) ] );
        if ( is_wp_error( $res ) ) return $res;

        $term_id = (int) $res['term_id'];

        // Link to Menu
        update_term_meta( $term_id, '_jprm_menu_term_id', $menu_id );

        // Place at the end of siblings (within same menu)
        $siblings = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'parent'     => max( 0, $parent ),
            'fields'     => 'ids',
        ]);
        $max = 0;
        foreach ( (array) $siblings as $sid ) {
            if ( (int) get_term_meta( $sid, '_jprm_menu_term_id', true ) !== $menu_id ) continue;
            $max = max( $max, (int) get_term_meta( $sid, '_jprm_term_order', true ) );
        }
        update_term_meta( $term_id, '_jprm_term_order', $max + 1 );

        $t = get_term( $term_id, 'jprm_section' );
        return rest_ensure_response([
            'id'         => (int) $t->term_id,
            'title'      => $t->name,
            'parent_id'  => (int) $t->parent,
            'menu_order' => (int) get_term_meta( $t->term_id, '_jprm_term_order', true ),
        ]);
    }
}
