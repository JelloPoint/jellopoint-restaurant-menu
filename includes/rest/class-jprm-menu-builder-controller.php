<?php
namespace JelloPoint\RestaurantMenu\REST;

use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST controller for Menu Builder (menus, sections, items)
 * Guard rails:
 *  - Sections are owned by exactly one Menu via term_meta _jprm_menu_term_id
 *  - You cannot create/move sections across different Menus
 *  - Item assigning respects section ownership (no cross-menu assigns)
 */
class Menu_Builder_Controller extends \WP_REST_Controller {

	/* ------- slugs / meta ------- */
	const NS                  = 'jprm/v1';
	const TAX_MENU            = 'jprm_menu';
	const TAX_SECTION         = 'jprm_section';
	const CPT_ITEM            = 'jprm_menu_item';

	const META_MENU_OWNER     = '_jprm_menu_term_id';    // term meta on jprm_section
	const META_ITEM_ORDER     = '_jprm_order_in_section';// post meta on jprm_menu_item
	const META_SECTION_ORDER  = '_jprm_section_order';   // term meta on jprm_section

	public function __construct() {
		$this->namespace = self::NS;
		$this->rest_base = 'menu-builder';
	}

	public function register_routes() : void {

		/* Sanity */
		register_rest_route( self::NS, '/ping', [
			'methods'             => 'GET',
			'callback'            => function(){ return rest_ensure_response( [ 'ok' => true ] ); },
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Menus */
		register_rest_route( self::NS, '/menu-builder/menus', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_menus' ],
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Sections */
		register_rest_route( self::NS, '/menu-builder/sections', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_sections' ],
			'args'     => [ 'menu_id' => [ 'type' => 'integer', 'required' => true ] ],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/sections/available', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_available_sections' ],
			'args'     => [ 'menu_id' => [ 'type' => 'integer', 'required' => true ] ],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/section/attach', [
			'methods'  => 'POST',
			'callback' => [ $this, 'attach_section' ],
			'args'     => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
				'section_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/section', [
			'methods'  => 'POST',
			'callback' => [ $this, 'create_section' ],
			'args'     => [
				'name'    => [ 'type' => 'string',  'required' => true ],
				'parent'  => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/sections/order', [
			'methods'  => 'POST',
			'callback' => [ $this, 'save_sections_order' ],
			'args'     => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
				// JS sends flat: [{id, parent_id, order}, ...]
				'tree'    => [ 'type' => 'array',   'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/section/unassign', [
			'methods'  => 'POST',
			'callback' => [ $this, 'unassign_section' ],
			'args'     => [
				'menu_id'    => [ 'type' => 'integer', 'required' => true ],
				'section_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Items */
		register_rest_route( self::NS, '/menu-builder/items', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_items' ],
			'args'     => [
				'menu_id'    => [ 'type' => 'integer', 'required' => true ],
				'unassigned' => [ 'type' => 'boolean', 'required' => false, 'default' => false ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/items/order', [
			'methods'  => 'POST',
			'callback' => [ $this, 'save_items_order' ],
			'args'     => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
				'items'   => [ 'type' => 'array',   'required' => true ], // [{id,section_id,order}, ...]
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/item/assign-batch', [
			'methods'  => 'POST',
			'callback' => [ $this, 'assign_items_batch' ],
			'args'     => [
				'menu_id'    => [ 'type' => 'integer', 'required' => true ],
				'section_id' => [ 'type' => 'integer', 'required' => true ],
				'ids'        => [ 'type' => 'array',   'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/item/unassign', [
			'methods'  => 'POST',
			'callback' => [ $this, 'unassign_item' ],
			'args'     => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			'permission_callback' => [ $this, 'cap' ],
		] );
	}

	public function cap( $request ) : bool {
		if ( $request instanceof \WP_REST_Request && 'GET' !== $request->get_method() ) {
			return current_user_can( 'manage_categories' );
		}

		return current_user_can( 'edit_posts' );
	}

	/* ============================================================
	 * Menus
	 * ============================================================ */

	public function get_menus( $request ) {
		$terms = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) {
			return new \WP_Error( 'jprm_terms_err', $terms->get_error_message(), [ 'status' => 500 ] );
		}
		$out = [];
		foreach ( $terms as $t ) {
			$out[] = [ 'id' => (int) $t->term_id, 'title' => (string) $t->name ];
		}
		return rest_ensure_response( [ 'menus' => $out ] );
	}

	/* ============================================================
	 * Sections
	 * ============================================================ */

	public function get_sections( $request ) {
		$menu_id = (int) $request['menu_id'];
		if ( $menu_id <= 0 ) { return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] ); }
		$list = [];
		foreach ( Menu_Structure_Store::get( $menu_id )['sections'] as $row ) {
			$term = get_term( (int) $row['id'], self::TAX_SECTION );
			if ( ! $term || is_wp_error( $term ) ) { continue; }
			$list[] = [ 'id' => (int) $term->term_id, 'title' => (string) $term->name, 'parent_id' => (int) $row['parent_id'], 'order' => (int) $row['order'], 'owner' => $menu_id ];
		}
		return rest_ensure_response( [ 'sections' => $list ] );
	}

	public function get_available_sections( $request ) {
		$menu_id = (int) $request['menu_id'];
		if ( $menu_id <= 0 ) { return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] ); }
		$attached = array_fill_keys( Menu_Structure_Store::section_ids( $menu_id ), true );
		$terms = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
		if ( is_wp_error( $terms ) ) { return new \WP_Error( 'jprm_terms_err', $terms->get_error_message(), [ 'status' => 500 ] ); }
		$list = [];
		foreach ( $terms as $term ) {
			if ( ! isset( $attached[ (int) $term->term_id ] ) ) { $list[] = [ 'id' => (int) $term->term_id, 'title' => (string) $term->name ]; }
		}
		return rest_ensure_response( [ 'sections' => $list ] );
	}

	public function attach_section( $request ) {
		$menu_id = (int) $request['menu_id'];
		$section_id = (int) $request['section_id'];
		$term = get_term( $section_id, self::TAX_SECTION );
		if ( $menu_id <= 0 || ! $term || is_wp_error( $term ) ) { return new \WP_Error( 'jprm_bad_section', __( 'Select a valid existing Section.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] ); }
		if ( ! current_user_can( 'edit_term', $section_id ) ) { return new \WP_Error( 'jprm_perm', __( 'Insufficient permissions.', 'jellopoint-restaurant-menu' ), [ 'status' => 403 ] ); }
		Menu_Structure_Store::attach_section( $menu_id, $section_id );
		return rest_ensure_response( [ 'ok' => true, 'id' => $section_id, 'title' => (string) $term->name ] );
	}


	public function create_section( $request ) {
		$name    = (string) $request['name'];
		$parent  = (int)    $request['parent'];
		$menu_id = (int)    $request['menu_id'];

		if ( $menu_id <= 0 || $name === '' ) {
			return new \WP_Error( 'jprm_bad_params', __( 'Name and menu_id are required.', 'jprm' ), [ 'status' => 400 ] );
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return new \WP_Error( 'jprm_perm', __( 'Insufficient permissions.', 'jellopoint-restaurant-menu' ), [ 'status' => 403 ] );
		}

		// A parent is valid only when attached to this Menu's own structure.
		if ( $parent ) {
			if ( ! in_array( $parent, Menu_Structure_Store::section_ids( $menu_id ), true ) ) {
				return new \WP_Error(
					'jprm_cross_menu',
					__( 'The parent Section is not attached to this Menu.', 'jellopoint-restaurant-menu' ),
					[ 'status' => 400 ]
				);
			}
		}

		$ins = wp_insert_term( $name, self::TAX_SECTION );
		if ( is_wp_error( $ins ) ) {
			return new \WP_Error( 'jprm_term_create', $ins->get_error_message(), [ 'status' => 500 ] );
		}
		$term_id = (int) $ins['term_id'];

		Menu_Structure_Store::attach_section( $menu_id, $term_id, $parent );
		// Temporary legacy compatibility for screens not migrated until 10D-D.
		update_term_meta( $term_id, self::META_MENU_OWNER, $menu_id );

		return rest_ensure_response( [
			'id'        => $term_id,
			'title'     => get_term( $term_id )->name,
			'parent_id' => (int) $parent,
			'menu_id'   => $menu_id,
		] );
	}

	public function save_sections_order( $request ) {
$menu_id = (int) $request['menu_id'];
$flat    = (array) $request['tree']; // [{id, parent_id, order}]

if ( $menu_id <= 0 || ! is_array( $flat ) ) {
	return new \WP_Error( 'jprm_bad_params', __( 'menu_id and tree are required.', 'jprm' ), [ 'status' => 400 ] );
}

$attached_ids = Menu_Structure_Store::section_ids( $menu_id );
// Guard: all Sections in the payload are attached to this Menu.
foreach ( $flat as $row ) {
	$tid = (int) ( $row['id'] ?? 0 );
	if ( ! $tid ) continue;
	if ( ! in_array( $tid, $attached_ids, true ) ) {
		return new \WP_Error(
			'jprm_cross_menu',
			sprintf( __( 'Section %d belongs to another Menu and cannot be moved here.', 'jprm' ), $tid ),
			[ 'status' => 400 ]
		);
	}

	$parent_id = (int) ( $row['parent_id'] ?? 0 );
	if ( $parent_id === $tid ) {
		return new \WP_Error( 'jprm_invalid_parent', __( 'A section cannot be its own parent.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] );
	}
	if ( $parent_id > 0 ) {
		if ( ! in_array( $parent_id, $attached_ids, true ) ) {
			return new \WP_Error( 'jprm_cross_menu', __( 'A parent section must belong to the same Menu.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] );
		}
	}
}

// Store parent and order inside this Menu only.
usort( $flat, static function( $a, $b ) {
	return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
} );
Menu_Structure_Store::save_section_tree( $menu_id, $flat );
return rest_ensure_response( [ 'ok' => true, 'count' => count( $flat ) ] );

	}

	public function unassign_section( $request ) {
		$menu_id    = (int) $request['menu_id'];
		$section_id = (int) $request['section_id'];

		if ( $menu_id <= 0 || $section_id <= 0 ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and section_id are required.', 'jprm' ), [ 'status' => 400 ] );
		}
		if ( ! current_user_can( 'edit_term', $section_id ) ) {
			return new \WP_Error( 'jprm_perm', __( 'Insufficient permissions.', 'jellopoint-restaurant-menu' ), [ 'status' => 403 ] );
		}
		if ( ! in_array( $section_id, Menu_Structure_Store::section_ids( $menu_id ), true ) ) {
			return new \WP_Error( 'jprm_cross_menu', __( 'This Section is not attached to the selected Menu.', 'jellopoint-restaurant-menu' ), [ 'status' => 400 ] );
		}

		Menu_Structure_Store::detach_section( $menu_id, $section_id );

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/* ============================================================
	 * Items
	 * ============================================================ */

	public function get_items( $request ) {
		$menu_id    = (int) $request['menu_id'];
		$unassigned = ! empty( $request['unassigned'] );

		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jprm' ), [ 'status' => 400 ] );
		}

		$q = new \WP_Query( [
			'post_type'      => self::CPT_ITEM,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$out = [];
		foreach ( (array) $q->posts as $pid ) {
			$terms = wp_get_post_terms( $pid, self::TAX_SECTION );
			$sec   = is_wp_error( $terms ) || empty( $terms ) ? null : $terms[0];

			// Determine the item "belongs to menu?" by the section's owner
			$belongs = false;
			$section_id = 0;
			if ( $sec ) {
				$owner = (int) get_term_meta( $sec->term_id, self::META_MENU_OWNER, true );
				$belongs = ( $owner === $menu_id );
				$section_id = (int) $sec->term_id;
			}

			if ( $unassigned ) {
				// Unassigned = either no section or section not owned by this menu
				if ( $belongs ) continue;
			} else {
				// Assigned to this menu only
				if ( ! $belongs ) continue;
			}

			$out[] = [
				'id'               => (int) $pid,
				'title'            => get_the_title( $pid ),
				'section_id'       => $section_id ?: null,
				'order_in_section' => (int) get_post_meta( $pid, self::META_ITEM_ORDER, true ),
				'price'            => '', // keep blank in REST; UI may compute/ignore
			];
		}

		// Keep sort by order_in_section inside each section on the client (UI does that).
		return rest_ensure_response( [ 'items' => $out ] );
	}

public function save_items_order( $request ) {
	$menu_id = (int) $request['menu_id'];
	$items   = (array) $request['items']; // [{id,section_id,order}]

	if ( $menu_id <= 0 || ! is_array( $items ) ) {
		return new \WP_Error(
			'jprm_bad_params',
			__( 'menu_id and items are required.', 'jprm' ),
			[ 'status' => 400 ]
		);
	}

	// Group items per section first
	$by_section = [];

	foreach ( $items as $row ) {
		$pid        = (int) ( $row['id'] ?? 0 );
		$section_id = (int) ( $row['section_id'] ?? 0 );
		$order      = (int) ( $row['order'] ?? 0 );

		if ( ! $pid || ! $section_id ) {
			continue;
		}

		// Guard: section must belong to the menu
		$owner = (int) get_term_meta( $section_id, self::META_MENU_OWNER, true );
		if ( $owner !== $menu_id ) {
			return new \WP_Error(
				'jprm_cross_menu',
				__( 'Cannot assign item to a section owned by another Menu.', 'jprm' ),
				[ 'status' => 400 ]
			);
		}

		$by_section[ $section_id ][] = [
			'id'    => $pid,
			'order' => $order,
		];
	}

	$changed_posts = [];

	// Within each section, rewrite to a dense 0..N-1 order range
	foreach ( $by_section as $section_id => $rows ) {
		usort(
			$rows,
			static function( $a, $b ) {
				return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
			}
		);

		$seq = -1;

		foreach ( $rows as $row ) {
			$pid = (int) ( $row['id'] ?? 0 );
			if ( ! $pid ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $pid ) ) {
				continue;
			}

			$seq++;

			// Assign section (single) and stamp the menu taxonomy (additive)
			wp_set_post_terms( $pid, [ $section_id ], self::TAX_SECTION, false );
			wp_set_post_terms( $pid, [ $menu_id ], self::TAX_MENU, true );

			// Guard-rail: always store a numeric order 0..N-1
			update_post_meta( $pid, self::META_ITEM_ORDER, $seq );
			$changed_posts[] = $pid;
		}
	}

	// Targeted cache clear for changed posts
	foreach ( array_unique( $changed_posts ) as $p ) {
		clean_post_cache( $p );
	}

	return rest_ensure_response( [
		'ok'    => true,
		'count' => count( $changed_posts ),
		'msg'   => __( 'Menu layout saved.', 'jprm' ),
	] );
}


	public function assign_items_batch( $request ) {
		$menu_id    = (int) $request['menu_id'];
		$section_id = (int) $request['section_id'];
		$ids        = (array) $request['ids'];

		if ( $menu_id <= 0 || $section_id <= 0 || empty( $ids ) ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id, section_id and ids are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		$owner = (int) get_term_meta( $section_id, self::META_MENU_OWNER, true );
		if ( $owner !== $menu_id ) {
			return new \WP_Error(
				'jprm_cross_menu',
				__( 'Target section belongs to another Menu.', 'jprm' ),
				[ 'status' => 400 ]
			);
		}

		// Current end order in that section
		$existing = new \WP_Query( [
			'post_type'      => self::CPT_ITEM,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'tax_query'      => [[
				'taxonomy' => self::TAX_SECTION,
				'field'    => 'term_id',
				'terms'    => [ $section_id ],
				'include_children' => false,
			]],
			'fields'        => 'ids',
			'no_found_rows' => true,
		] );
		$next = 0;
		foreach ( (array) $existing->posts as $pid ) {
			$next = max( $next, (int) get_post_meta( $pid, self::META_ITEM_ORDER, true ) );
		}

		$done = 0;
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 ) continue;
			if ( ! current_user_can( 'edit_post', $pid ) ) continue;
			wp_set_post_terms( $pid, [ $section_id ], self::TAX_SECTION, false );
			wp_set_post_terms( $pid, [ $menu_id ], self::TAX_MENU, true );
			$next++;
			update_post_meta( $pid, self::META_ITEM_ORDER, $next );
			$done++;
		}

		return rest_ensure_response( [ 'ok' => true, 'assigned' => $done ] );
	}

	public function unassign_item( $request ) {
		$pid = (int) $request['id'];
		if ( $pid <= 0 ) {
			return new \WP_Error( 'jprm_bad_params', __( 'Missing item id.', 'jprm' ), [ 'status' => 400 ] );
		}
		if ( ! current_user_can( 'edit_post', $pid ) ) {
			return new \WP_Error( 'jprm_perm', __( 'Insufficient permissions.', 'jprm' ), [ 'status' => 403 ] );
		}
		wp_set_post_terms( $pid, [], self::TAX_SECTION, false );
		delete_post_meta( $pid, self::META_ITEM_ORDER );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	/* ============================================================
	 * Helpers
	 * ============================================================ */

	/** Cascade owner to the term and all descendants */
	private function cascade_owner( int $term_id, int $owner_menu_id ) : void {
		if ( $term_id <= 0 ) return;
		update_term_meta( $term_id, self::META_MENU_OWNER, $owner_menu_id );

		$children = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'parent'     => $term_id,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $children ) || empty( $children ) ) return;

		foreach ( $children as $child ) {
			$this->cascade_owner( (int) $child->term_id, $owner_menu_id );
		}
	}
}
