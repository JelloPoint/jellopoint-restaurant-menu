<?php
namespace JelloPoint\RestaurantMenu\REST;

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

	public function cap() : bool {
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
	if ( $menu_id <= 0 ) {
		return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jprm' ), [ 'status' => 400 ] );
	}

	$terms = get_terms( [ 'taxonomy' => self::TAX_SECTION, 'hide_empty' => false ] );
	if ( is_wp_error( $terms ) ) {
		return new \WP_Error( 'jprm_terms_err', $terms->get_error_message(), [ 'status' => 500 ] );
	}

	$list = [];
	foreach ( $terms as $t ) {
		$owner = (int) get_term_meta( $t->term_id, self::META_MENU_OWNER, true );
		if ( $owner !== $menu_id ) continue;

		$ord = get_term_meta( $t->term_id, self::META_SECTION_ORDER, true );
		$list[] = [
			'id'         => (int) $t->term_id,
			'title'      => (string) $t->name,
			'parent_id'  => (int) $t->parent,
			'order'      => ( $ord !== '' && $ord !== null ) ? (int) $ord : PHP_INT_MAX, // unordered go to end
		];
	}

	usort( $list, static function( $a, $b ) {
		$oa = $a['order'] ?? PHP_INT_MAX;
		$ob = $b['order'] ?? PHP_INT_MAX;
		if ( $oa === $ob ) {
			return strcasecmp( (string) $a['title'], (string) $b['title'] );
		}
		return $oa <=> $ob;
	} );

	return rest_ensure_response( [ 'sections' => $list ] );
}


	public function create_section( $request ) {
		$name    = (string) $request['name'];
		$parent  = (int)    $request['parent'];
		$menu_id = (int)    $request['menu_id'];

		if ( $menu_id <= 0 || $name === '' ) {
			return new \WP_Error( 'jprm_bad_params', __( 'Name and menu_id are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		// Guard: parent (if provided) must belong to same menu
		if ( $parent ) {
			$parent_owner = (int) get_term_meta( $parent, self::META_MENU_OWNER, true );
			if ( $parent_owner && $parent_owner !== $menu_id ) {
				return new \WP_Error(
					'jprm_cross_menu',
					__( 'You cannot create a subsection under a section owned by another Menu.', 'jprm' ),
					[ 'status' => 400 ]
				);
			}
		}

		$ins = wp_insert_term( $name, self::TAX_SECTION, [ 'parent' => $parent ] );
		if ( is_wp_error( $ins ) ) {
			return new \WP_Error( 'jprm_term_create', $ins->get_error_message(), [ 'status' => 500 ] );
		}
		$term_id = (int) $ins['term_id'];

		// Owner: inherit from parent if present, else use menu_id
		$owner_to_set = $menu_id;
		if ( $parent ) {
			$po = (int) get_term_meta( $parent, self::META_MENU_OWNER, true );
			if ( $po ) $owner_to_set = $po;
		}
		update_term_meta( $term_id, self::META_MENU_OWNER, $owner_to_set );

		return rest_ensure_response( [
			'id'        => $term_id,
			'title'     => get_term( $term_id )->name,
			'parent_id' => (int) $parent,
			'menu_id'   => $owner_to_set,
		] );
	}

	public function save_sections_order( $request ) {
		$menu_id = (int) $request['menu_id'];
		$flat    = (array) $request['tree']; // [{id, parent_id, order}]

		if ( $menu_id <= 0 || ! is_array( $flat ) ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and tree are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		// Guard: all section IDs belong to this menu
		foreach ( $flat as $row ) {
			$tid = (int) ( $row['id'] ?? 0 );
			if ( ! $tid ) continue;
			$owner = (int) get_term_meta( $tid, self::META_MENU_OWNER, true );
			if ( $owner !== $menu_id ) {
				return new \WP_Error(
					'jprm_cross_menu',
					sprintf( __( 'Section %d belongs to another Menu and cannot be moved here.', 'jprm' ), $tid ),
					[ 'status' => 400 ]
				);
			}
		}

		// Apply parents and ensure owner cascades
		$seq = 0;
		usort( $flat, static function( $a, $b ) {
			$ao = (int) ( $a['order'] ?? 0 );
			$bo = (int) ( $b['order'] ?? 0 );
			return $ao <=> $bo;
		});

		foreach ( $flat as $row ) {
			$tid   = (int) ( $row['id'] ?? 0 );
			$pid   = (int) ( $row['parent_id'] ?? 0 );
			if ( ! $tid ) continue;

			// parent update
			$term = get_term( $tid, self::TAX_SECTION );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( (int) $term->parent !== $pid ) {
					wp_update_term( $tid, self::TAX_SECTION, [ 'parent' => $pid ] );
				}
			}

			// cascade owner for this term (and its descendants)
			$this->cascade_owner( $tid, $menu_id );

			$seq++;
			update_term_meta( $tid, self::META_SECTION_ORDER, $seq );

		}

		return rest_ensure_response( [ 'ok' => true ] );
	}

	public function unassign_section( $request ) {
		$menu_id    = (int) $request['menu_id'];
		$section_id = (int) $request['section_id'];

		if ( $menu_id <= 0 || $section_id <= 0 ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and section_id are required.', 'jprm' ), [ 'status' => 400 ] );
		}
		$owner = (int) get_term_meta( $section_id, self::META_MENU_OWNER, true );
		if ( $owner !== $menu_id ) {
			return new \WP_Error( 'jprm_cross_menu', __( 'This section belongs to another Menu.', 'jprm' ), [ 'status' => 400 ] );
		}

		// Unset owner on this section and descendants
		$this->cascade_owner( $section_id, 0 );

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
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and items are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		foreach ( $items as $row ) {
			$pid        = (int) ( $row['id'] ?? 0 );
			$section_id = (int) ( $row['section_id'] ?? 0 );
			$order      = (int) ( $row['order'] ?? 0 );
			if ( ! $pid || ! $section_id ) continue;

			// Guard: section must belong to the menu
			$owner = (int) get_term_meta( $section_id, self::META_MENU_OWNER, true );
			if ( $owner !== $menu_id ) {
				return new \WP_Error(
					'jprm_cross_menu',
					__( 'Cannot assign item to a section owned by another Menu.', 'jprm' ),
					[ 'status' => 400 ]
				);
			}

			// Assign single section (replace any previous)
			wp_set_post_terms( $pid, [ $section_id ], self::TAX_SECTION, false );
			update_post_meta( $pid, self::META_ITEM_ORDER, $order );
		}

		return rest_ensure_response( [ 'ok' => true ] );
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
