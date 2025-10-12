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
        // Diagnostics
        register_rest_route( $this->namespace, '/ping', [[
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => function(){ return ['ok'=>1,'time'=>time()]; },
        ]]);

        // Menus (taxonomy terms)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/menus', [[
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('edit_posts'); },
            'callback' => [ $this, 'get_menus' ],
        ]]);

        // Sections (taxonomy terms)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections', [[
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('edit_posts'); },
            'callback' => [ $this, 'get_sections' ],
            'args' => [
                'menu_id' => [ 'type'=>'integer', 'required'=>false ],
            ],
        ]]);

        // Save nesting & ordering
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections/order', [[
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('manage_categories'); },
            'callback' => [ $this, 'save_sections_order' ],
            'args' => [
                'tree' => [ 'type'=>'array', 'required'=>true ],
            ],
        ]]);

        // Create Section term
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section', [[
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('manage_categories'); },
            'callback' => [ $this, 'create_section' ],
            'args' => [
                'name'   => [ 'type'=>'string', 'required'=>true ],
                'parent' => [ 'type'=>'integer', 'required'=>false, 'default'=>0 ],
            ],
        ]]);
    }

    public function get_menus( WP_REST_Request $req ) {
        $terms = get_terms([
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ]);
        if ( is_wp_error($terms) ) return $terms;

        $items = array_map(fn($t)=>['id'=>(int)$t->term_id,'title'=>$t->name], $terms);
        return rest_ensure_response(['menus'=>$items]);
    }

    public function get_sections( WP_REST_Request $req ) {
        $terms = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
        ]);
        if ( is_wp_error($terms) ) return $terms;

        // Sort by our meta order within siblings; fallback by name
        $with_meta = array_map(function($t){
            $order = (int) get_term_meta( $t->term_id, '_jprm_term_order', true );
            return [$t, $order];
        }, $terms);

        usort($with_meta, function($a,$b){
            [$ta,$oa] = $a; [$tb,$ob] = $b;
            if ( $ta->parent !== $tb->parent ) return 0; // different parents => DOM will group anyway
            return $oa <=> $ob ?: strcasecmp($ta->name, $tb->name);
        });

        $items = array_map(function($pair){
            [$t,$o] = $pair;
            return [
                'id'         => (int) $t->term_id,
                'title'      => $t->name,
                'parent_id'  => (int) $t->parent,
                'menu_order' => (int) $o,
                'count'      => (int) $t->count,
            ];
        }, $with_meta);

        return rest_ensure_response(['sections'=>$items]);
    }

    public function save_sections_order( WP_REST_Request $req ) {
        $tree = $req->get_param('tree');
        if ( ! is_array($tree) ) {
            return new WP_Error('jprm_bad_tree', __('Invalid tree payload.', 'jprm'), ['status'=>400]);
        }

        // $tree is a flat array of nodes: [{id, parent_id, order}]
        foreach ( $tree as $node ) {
            $id    = isset($node['id']) ? (int)$node['id'] : 0;
            $pid   = isset($node['parent_id']) ? (int)$node['parent_id'] : 0;
            $order = isset($node['order']) ? (int)$node['order'] : 0;

            if ( $id <= 0 ) { continue; }

            // Update parent if needed
            $term = get_term( $id, 'jprm_section' );
            if ( $term && ! is_wp_error($term) ) {
                if ( (int)$term->parent !== $pid ) {
                    $res = wp_update_term( $id, 'jprm_section', [ 'parent' => $pid ] );
                    if ( is_wp_error($res) ) { return $res; }
                }
                update_term_meta( $id, '_jprm_term_order', $order );
            }
        }

        // response with refreshed list
        return $this->get_sections( $req );
    }

    public function create_section( WP_REST_Request $req ) {
        $name   = trim( (string) $req->get_param('name') );
        $parent = (int) $req->get_param('parent');
        if ( $name === '' ) {
            return new WP_Error('jprm_empty', __('Section name is required.', 'jprm'), ['status'=>400]);
        }

        $res = wp_insert_term( $name, 'jprm_section', [ 'parent' => max(0,$parent) ] );
        if ( is_wp_error($res) ) return $res;

        $term_id = (int) $res['term_id'];
        // put new terms at the bottom by setting order = max+1 within its parent
        $siblings = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'parent'     => max(0,$parent),
            'fields'     => 'ids',
        ]);
        $max = 0;
        foreach ( (array)$siblings as $sid ) {
            $max = max( $max, (int) get_term_meta( $sid, '_jprm_term_order', true ) );
        }
        update_term_meta( $term_id, '_jprm_term_order', $max + 1 );

        $term = get_term( $term_id, 'jprm_section' );
        return rest_ensure_response([
            'id'        => (int) $term->term_id,
            'title'     => $term->name,
            'parent_id' => (int) $term->parent,
            'menu_order'=> (int) get_term_meta( $term->term_id, '_jprm_term_order', true ),
        ]);
    }
}
