<?php
namespace JelloPoint\RestaurantMenu\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menu Builder REST API
 * - Menus: taxonomy 'jprm_menu'
 * - Sections: taxonomy 'jprm_section' (hierarchical, owned by a Menu via _jprm_menu_term_id)
 * - Items: CPT (default 'jprm_menu_item'), linked to sections via term relationship 'jprm_section'
 * - Order: sections => _jprm_term_order (term meta); items => _jprm_order_in_section (post meta)
 */
class Menu_Builder_Controller extends WP_REST_Controller {

    /** ✅ Adjust if your CPT / price meta differ */
    private string $item_post_type = 'jprm_menu_item';
    private string $item_price_key = '_jprm_price';

    public function __construct() {
        $this->namespace = 'jprm/v1';
        $this->rest_base = 'menu-builder';
    }

    public function register_routes() : void {

        // Diagnostics
        register_rest_route( $this->namespace, '/ping', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => static fn() => ['ok'=>1,'time'=>time()],
        ]]);

        // Menus
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/menus', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('edit_posts'),
            'callback'            => [ $this, 'get_menus' ],
        ]]);

        // Sections (scoped to menu)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('edit_posts'),
            'callback'            => [ $this, 'get_sections' ],
            'args'                => [
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Save section nesting & order
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/sections/order', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('manage_categories'),
            'callback'            => [ $this, 'save_sections_order' ],
            'args'                => [
                'tree'    => [ 'type'=>'array',   'required'=>true ],
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Create section
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('manage_categories'),
            'callback'            => [ $this, 'create_section' ],
            'args'                => [
                'name'    => [ 'type'=>'string',  'required'=>true ],
                'parent'  => [ 'type'=>'integer', 'required'=>false, 'default'=>0 ],
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Delete section (only if empty in this menu)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section/delete', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('manage_categories'),
            'callback'            => [ $this, 'delete_section' ],
            'args'                => [
                'menu_id'   => [ 'type'=>'integer', 'required'=>true ],
                'section_id'=> [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Unassign section from this menu (detach only)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/section/unassign', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('manage_categories'),
            'callback'            => [ $this, 'unassign_section' ],
            'args'                => [
                'menu_id'   => [ 'type'=>'integer', 'required'=>true ],
                'section_id'=> [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // List items (assigned to this menu's sections) or unassigned
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/items', [[
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('edit_posts'),
            'callback'            => [ $this, 'list_items' ], // not get_items()
            'args'                => [
                'menu_id'    => [ 'type'=>'integer', 'required'=>true ],
                'unassigned' => [ 'type'=>'boolean', 'required'=>false, 'default'=>false ],
            ],
        ]]);

        // Assign a single existing item to a section
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/item/assign', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function( \WP_REST_Request $req ) {
                $pid = (int) $req->get_param('id');
                return is_user_logged_in() && ( $pid > 0 ) && current_user_can( 'edit_post', $pid );
            },
            'callback'            => [ $this, 'assign_item' ],
            'args'                => [
                'menu_id'    => [ 'type'=>'integer', 'required'=>true ],
                'id'         => [ 'type'=>'integer', 'required'=>true ],
                'section_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Assign multiple items to a section
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/item/assign-batch', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function( \WP_REST_Request $req ) {
                $ids = (array) $req->get_param('ids');
                foreach ( $ids as $pid ) {
                    if ( ! current_user_can( 'edit_post', (int) $pid ) ) return false;
                }
                return is_user_logged_in();
            },
            'callback'            => [ $this, 'assign_item_batch' ],
            'args'                => [
                'menu_id'    => [ 'type'=>'integer', 'required'=>true ],
                'ids'        => [ 'type'=>'array',   'required'=>true ],
                'section_id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Save items order & section after drag
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/items/order', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in() && current_user_can('edit_posts'),
            'callback'            => [ $this, 'save_items_order' ],
            'args'                => [
                'menu_id' => [ 'type'=>'integer', 'required'=>true ],
                'items'   => [ 'type'=>'array',   'required'=>true ], // [{id, section_id, order}]
            ],
        ]]);

        // Unassign single item (remove from section)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/item/unassign', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function( \WP_REST_Request $req ) {
                $pid = (int) $req->get_param('id');
                return is_user_logged_in() && ( $pid > 0 ) && current_user_can( 'edit_post', $pid );
            },
            'callback'            => [ $this, 'unassign_item' ],
            'args'                => [
                'id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);

        // Delete single item
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/item/delete', [[
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => function( \WP_REST_Request $req ) {
                $pid = (int) $req->get_param('id');
                return is_user_logged_in() && ( $pid > 0 ) && current_user_can( 'delete_post', $pid );
            },
            'callback'            => [ $this, 'delete_item' ],
            'args'                => [
                'id' => [ 'type'=>'integer', 'required'=>true ],
            ],
        ]]);
    }

    /* ---------------- Menus & Sections ---------------- */

    public function get_menus( \WP_REST_Request $req ) {
        $terms = get_terms([
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ]);

        // Seed default Main Menu if none
        if ( ! is_wp_error( $terms ) && empty( $terms ) && current_user_can( 'manage_categories' ) ) {
            $seed = wp_insert_term( __( 'Main Menu', 'jprm' ), 'jprm_menu' );
            if ( ! is_wp_error( $seed ) ) {
                $terms = get_terms([ 'taxonomy' => 'jprm_menu', 'hide_empty' => false ]);
            }
        }
        if ( is_wp_error( $terms ) ) return $terms;

        $items = array_map(static fn($t) => [ 'id' => (int) $t->term_id, 'title' => $t->name ], $terms );
        return rest_ensure_response([ 'menus' => $items ]);
    }

    public function get_sections( \WP_REST_Request $req ) {
        $menu_id = (int) $req->get_param('menu_id');

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

    public function save_sections_order( \WP_REST_Request $req ) {
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

            update_term_meta( $id, '_jprm_menu_term_id', $menu_id );

            $term = get_term( $id, 'jprm_section' );
            if ( $term && ! is_wp_error( $term ) ) {
                if ( (int) $term->parent !== $pid ) {
                    $res = wp_update_term( $id, 'jprm_section', [ 'parent' => $pid ] );
                    if ( is_wp_error( $res ) ) return $res;
                }
                update_term_meta( $id, '_jprm_term_order', $order );
            }
        }

        // Return fresh sections
        $r = new \WP_REST_Request( 'GET' );
        $r->set_param( 'menu_id', $menu_id );
        return $this->get_sections( $r );
    }

    public function create_section( \WP_REST_Request $req ) {
        $name    = trim( (string) $req->get_param('name') );
        $parent  = (int) $req->get_param('parent', 0 );
        $menu_id = (int) $req->get_param('menu_id');

        if ( $name === '' ) {
            return new WP_Error( 'jprm_empty', __( 'Section name is required.', 'jprm' ), [ 'status' => 400 ] );
        }

        $res = wp_insert_term( $name, 'jprm_section', [ 'parent' => max( 0, $parent ) ] );
        if ( is_wp_error( $res ) ) return $res;

        $term_id = (int) $res['term_id'];
        update_term_meta( $term_id, '_jprm_menu_term_id', $menu_id );

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

    /* ---------------- Items ---------------- */

    /**
     * List items attached to this menu's sections, or unassigned (?unassigned=1).
     */
    public function list_items( \WP_REST_Request $req ) {
        $menu_id    = (int) $req->get_param('menu_id');
        $unassigned = (bool) $req->get_param('unassigned');

        $all_section_ids = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if ( is_wp_error( $all_section_ids ) ) return $all_section_ids;

        $section_ids = [];
        foreach ( (array) $all_section_ids as $tid ) {
            if ( (int) get_term_meta( $tid, '_jprm_menu_term_id', true ) === $menu_id ) {
                $section_ids[] = (int) $tid;
            }
        }

        $q = new \WP_Query([
            'post_type'      => $this->item_post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        $items = [];
        foreach ( (array) $q->posts as $pid ) {
            $terms = wp_get_post_terms( $pid, 'jprm_section', [ 'fields' => 'ids' ] );
            $section_id = ( $terms && ! is_wp_error( $terms ) ) ? (int) $terms[0] : 0;

            $belongs_to_menu = $section_id && in_array( $section_id, $section_ids, true );

            if ( $unassigned ) {
                if ( $belongs_to_menu ) continue;
            } else {
                if ( ! $belongs_to_menu ) continue;
            }

            $items[] = [
                'id'               => (int) $pid,
                'title'            => get_the_title( $pid ),
                'price'            => (string) get_post_meta( $pid, $this->item_price_key, true ),
                'section_id'       => $belongs_to_menu ? $section_id : 0,
                'order_in_section' => (int) get_post_meta( $pid, '_jprm_order_in_section', true ),
                'badges'           => [],
            ];
        }

        if ( ! $unassigned ) {
            usort( $items, static function( $a, $b ) {
                if ( $a['section_id'] !== $b['section_id'] ) return 0;
                return $a['order_in_section'] <=> $b['order_in_section']
                    ?: strcasecmp( $a['title'], $b['title'] );
            });
        }

        return rest_ensure_response([ 'items' => $items ]);
    }

    /** Assign one item to a section (append to end) */
    public function assign_item( \WP_REST_Request $req ) {
        $menu_id    = (int) $req->get_param('menu_id');
        $pid        = (int) $req->get_param('id');
        $section_id = (int) $req->get_param('section_id');

        if ( $pid <= 0 || $section_id <= 0 ) {
            return new WP_Error( 'jprm_bad_input', __( 'Missing item or section.', 'jprm' ), [ 'status' => 400 ] );
        }

        $owner = (int) get_term_meta( $section_id, '_jprm_menu_term_id', true );
        if ( $owner !== $menu_id ) {
            return new WP_Error( 'jprm_wrong_menu', __( 'Section does not belong to selected Menu.', 'jprm' ), [ 'status' => 400 ] );
        }

        // Assign term
        $res = wp_set_post_terms( $pid, [ $section_id ], 'jprm_section', false );
        if ( is_wp_error( $res ) ) return $res;

        // Append to end
        $siblings = new \WP_Query([
            'post_type'      => $this->item_post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'tax_query'      => [[
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => [ $section_id ],
                'include_children' => false,
            ]],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $max = 0;
        foreach ( (array) $siblings->posts as $sid ) {
            $max = max( $max, (int) get_post_meta( $sid, '_jprm_order_in_section', true ) );
        }
        update_post_meta( $pid, '_jprm_order_in_section', $max + 1 );

        return rest_ensure_response([ 'ok' => 1, 'id' => $pid ]);
    }

    /** Assign multiple items to a section (append in sequence) */
    public function assign_item_batch( \WP_REST_Request $req ) {
        $menu_id    = (int) $req->get_param('menu_id');
        $ids        = array_map('intval', (array) $req->get_param('ids') );
        $section_id = (int) $req->get_param('section_id');

        $owner = (int) get_term_meta( $section_id, '_jprm_menu_term_id', true );
        if ( $owner !== $menu_id ) {
            return new WP_Error( 'jprm_wrong_menu', __( 'Section does not belong to selected Menu.', 'jprm' ), [ 'status' => 400 ] );
        }

        // Current max order
        $siblings = new \WP_Query([
            'post_type'      => $this->item_post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'tax_query'      => [[
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => [ $section_id ],
                'include_children' => false,
            ]],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $next = 0;
        foreach ( (array) $siblings->posts as $sid ) {
            $next = max( $next, (int) get_post_meta( $sid, '_jprm_order_in_section', true ) );
        }

        foreach ( $ids as $pid ) {
            if ( $pid <= 0 ) continue;
            if ( ! current_user_can( 'edit_post', $pid ) ) continue;
            wp_set_post_terms( $pid, [ $section_id ], 'jprm_section', false );
            $next++;
            update_post_meta( $pid, '_jprm_order_in_section', $next );
        }

        return rest_ensure_response([ 'ok' => 1, 'assigned' => array_values($ids) ]);
    }

    /** Save items order & section (batch) */
    public function save_items_order( \WP_REST_Request $req ) {
        $menu_id = (int) $req->get_param('menu_id');
        $items   = (array) $req->get_param('items'); // [{id,section_id,order}]

        // Validate that section_ids belong to this menu
        foreach ( $items as $it ) {
            $sid = isset($it['section_id']) ? (int) $it['section_id'] : 0;
            if ( $sid <= 0 ) continue;
            $owner = (int) get_term_meta( $sid, '_jprm_menu_term_id', true );
            if ( $owner !== $menu_id ) {
                return new WP_Error( 'jprm_wrong_menu', __( 'Section does not belong to selected Menu.', 'jprm' ), [ 'status' => 400 ] );
            }
        }

        foreach ( $items as $it ) {
            $pid  = isset($it['id']) ? (int) $it['id'] : 0;
            $sid  = isset($it['section_id']) ? (int) $it['section_id'] : 0;
            $ord  = isset($it['order']) ? (int) $it['order'] : 0;
            if ( $pid <= 0 || $sid <= 0 ) continue;
            if ( ! current_user_can( 'edit_post', $pid ) ) continue;

            wp_set_post_terms( $pid, [ $sid ], 'jprm_section', false );
            update_post_meta( $pid, '_jprm_order_in_section', $ord );
        }

        // Return fresh items (assigned)
        $r = new \WP_REST_Request( 'GET' );
        $r->set_param( 'menu_id', $menu_id );
        return $this->list_items( $r );
    }

    /** Unassign item (remove term) */
    public function unassign_item( \WP_REST_Request $req ) {
        $pid = (int) $req->get_param('id');
        if ( $pid <= 0 ) return new WP_Error( 'jprm_bad_input', __( 'Missing item.', 'jprm' ), [ 'status' => 400 ] );

        wp_set_post_terms( $pid, [], 'jprm_section', false );
        delete_post_meta( $pid, '_jprm_order_in_section' );

        return rest_ensure_response([ 'ok' => 1, 'id' => $pid ]);
    }

    /** Delete item */
    public function delete_item( \WP_REST_Request $req ) {
        $pid = (int) $req->get_param('id');
        if ( $pid <= 0 ) return new WP_Error( 'jprm_bad_input', __( 'Missing item.', 'jprm' ), [ 'status' => 400 ] );

        $res = wp_delete_post( $pid, true );
        if ( ! $res ) return new WP_Error( 'jprm_delete_failed', __( 'Could not delete item.', 'jprm' ), [ 'status' => 500 ] );

        return rest_ensure_response([ 'ok' => 1, 'id' => $pid ]);
    }

    /** Delete section (only if empty for this menu) */
    public function delete_section( \WP_REST_Request $req ) {
        $menu_id    = (int) $req->get_param('menu_id');
        $section_id = (int) $req->get_param('section_id');

        if ( (int) get_term_meta( $section_id, '_jprm_menu_term_id', true ) !== $menu_id ) {
            return new WP_Error( 'jprm_wrong_menu', __( 'Section does not belong to selected Menu.', 'jprm' ), [ 'status' => 400 ] );
        }

        // Ensure no child sections belonging to this menu
        $children = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'parent'     => $section_id,
            'fields'     => 'ids',
        ]);
        foreach ( (array) $children as $cid ) {
            if ( (int) get_term_meta( $cid, '_jprm_menu_term_id', true ) === $menu_id ) {
                return new WP_Error( 'jprm_not_empty', __( 'Section has subsections. Remove them first.', 'jprm' ), [ 'status' => 400 ] );
            }
        }

        // Ensure no items in this section
        $q = new \WP_Query([
            'post_type'      => $this->item_post_type,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'tax_query'      => [[
                'taxonomy' => 'jprm_section',
                'field'    => 'term_id',
                'terms'    => [ $section_id ],
                'include_children' => false,
            ]],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if ( $q->have_posts() ) {
            return new WP_Error( 'jprm_not_empty', __( 'Section has items. Unassign or move them first.', 'jprm' ), [ 'status' => 400 ] );
        }

        $res = wp_delete_term( $section_id, 'jprm_section' );
        if ( is_wp_error( $res ) ) return $res;

        return rest_ensure_response([ 'ok' => 1, 'section_id' => $section_id ]);
    }

    /** Unassign section (detach from menu; set owner=0 and parent=0) */
    public function unassign_section( \WP_REST_Request $req ) {
        $menu_id    = (int) $req->get_param('menu_id');
        $section_id = (int) $req->get_param('section_id');

        if ( (int) get_term_meta( $section_id, '_jprm_menu_term_id', true ) !== $menu_id ) {
            return new WP_Error( 'jprm_wrong_menu', __( 'Section does not belong to selected Menu.', 'jprm' ), [ 'status' => 400 ] );
        }

        update_term_meta( $section_id, '_jprm_menu_term_id', 0 );
        wp_update_term( $section_id, 'jprm_section', [ 'parent' => 0 ] );

        return rest_ensure_response([ 'ok' => 1, 'section_id' => $section_id ]);
    }
}
