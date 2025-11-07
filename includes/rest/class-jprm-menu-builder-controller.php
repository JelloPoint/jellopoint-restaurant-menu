<?php
namespace JelloPoint\RestaurantMenu\REST;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menu Builder REST controller
 * Matches jprm-menu-builder.js expectations exactly.
 *
 * Taxonomies / Post Types:
 * - Menu taxonomy:     jprm_menu
 * - Section taxonomy:  jprm_section
 * - Menu Item CPT:     jprm_menu_item
 *
 * Meta Keys:
 * - Section owner (term meta):      _jprm_menu_term_id
 * - Section order (term meta):      _jprm_section_order   (NEW explicit key)
 * - Item order in section (post meta): _jprm_order_in_section
 */
class Menu_Builder_Controller extends \WP_REST_Controller {

	const NS                  = 'jprm/v1';
	const TAX_MENU            = 'jprm_menu';
	const TAX_SECTION         = 'jprm_section';
	const CPT_ITEM            = 'jprm_menu_item';

	const META_MENU_OWNER     = '_jprm_menu_term_id';
	const META_SECTION_ORDER  = '_jprm_section_order';
	const META_ITEM_ORDER     = '_jprm_order_in_section';

	public function __construct() {
		$this->namespace = self::NS;
		$this->rest_base = 'menu-builder';
	}

	public function register_routes() : void {

		/* Ping */
		register_rest_route( self::NS, '/ping', [
			'methods'             => 'GET',
			'callback'            => function(){ return rest_ensure_response( ['ok'=>true] ); },
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Menus */
		register_rest_route( self::NS, '/menu-builder/menus', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_menus' ],
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Sections */
		register_rest_route( self::NS, '/menu-builder/sections', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_sections' ],
			'args'                => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		register_rest_route( self::NS, '/menu-builder/sections/order', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_sections_order' ],
			'args'                => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
				'tree'    => [ 'type' => 'array',   'required' => true ], // [{id,parent_id,order}]
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		/* Items */
		register_rest_route( self::NS, '/menu-builder/items', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_items' ],
			'args'                => [
				'menu_id'    => [ 'type' => 'integer', 'required' => true ],
				'unassigned' => [ 'type' => 'boolean', 'required' => false, 'default' => false ],
			],
			'permission_callback' => [ $this, 'cap' ],
		] );

		// Matches jprm-menu-builder.js: items/order
		register_rest_route( self::NS, '/menu-builder/items/order', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_items_order' ],
			'args'                => [
				'menu_id' => [ 'type' => 'integer', 'required' => true ],
				'items'   => [ 'type' => 'array',   'required' => true ], // [{id,section_id,order}]
			],
			'permission_callback' => [ $this, 'cap' ],
		] );
	}

	public function cap() : bool {
		return current_user_can( 'manage_options' );
	}

	/* ===================== MENUS ===================== */

	public function get_menus( $request ) {
		$terms = get_terms( [
			'taxonomy'   => self::TAX_MENU,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $terms ) ) {
			return new \WP_Error( 'jprm_terms_err', $terms->get_error_message(), [ 'status' => 500 ] );
		}
		$menus = [];
		foreach ( $terms as $t ) {
			$menus[] = [ 'id' => (int) $t->term_id, 'title' => (string) $t->name ];
		}
		// JS expects {menus:[...]}
		return rest_ensure_response( [ 'menus' => $menus ] );
	}

	/* ==================== SECTIONS ==================== */

	public function get_sections( $request ) {
		$menu_id = (int) $request['menu_id'];
		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jprm' ), [ 'status' => 400 ] );
		}

		$terms = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $terms ) ) {
			return new \WP_Error( 'jprm_terms_err', $terms->get_error_message(), [ 'status' => 500 ] );
		}

		$sections = [];
		foreach ( $terms as $t ) {
			$owner = (int) get_term_meta( $t->term_id, self::META_MENU_OWNER, true );
			if ( $owner !== $menu_id ) continue;

			$sections[] = [
				'id'        => (int) $t->term_id,
				'title'     => (string) $t->name,  // JS uses .title
				'parent_id' => (int) $t->parent,
				'order'     => (int) get_term_meta( $t->term_id, self::META_SECTION_ORDER, true ),
			];
		}

		// stable order: by saved order then title
		usort( $sections, static function( $a, $b ) {
			$ao = (int) ($a['order'] ?? 0);
			$bo = (int) ($b['order'] ?? 0);
			if ( $ao !== $bo ) return $ao <=> $bo;
			return strcasecmp( (string)$a['title'], (string)$b['title'] );
		} );

		// JS expects {sections:[...]}
		return rest_ensure_response( [ 'sections' => $sections ] );
	}

	public function save_sections_order( $request ) {
		$menu_id = (int) $request['menu_id'];
		$flat    = (array) $request['tree']; // [{id,parent_id,order}]
		if ( $menu_id <= 0 || ! is_array( $flat ) ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and tree are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		// Guard: sections must belong to this menu
		foreach ( $flat as $row ) {
			$tid = (int) ( $row['id'] ?? 0 );
			if ( ! $tid ) continue;
			$owner = (int) get_term_meta( $tid, self::META_MENU_OWNER, true );
			if ( $owner !== $menu_id ) {
				return new \WP_Error( 'jprm_cross_menu', __( 'Cannot move sections across Menus.', 'jprm' ), [ 'status' => 400 ] );
			}
		}

		// Sort by provided flat order, then persist parent + global sequence
		usort( $flat, static function( $a, $b ) {
			return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
		});

		$seq = 0;
		foreach ( $flat as $row ) {
			$tid = (int) ( $row['id'] ?? 0 );
			$pid = (int) ( $row['parent_id'] ?? 0 );
			if ( ! $tid ) continue;

			$term = get_term( $tid, self::TAX_SECTION );
			if ( $term && ! is_wp_error( $term ) && (int) $term->parent !== $pid ) {
				wp_update_term( $tid, self::TAX_SECTION, [ 'parent' => $pid ] );
			}

			// Keep/cascade owner safety
			$this->cascade_owner( $tid, $menu_id );

			// Persist global sequence order within this menu
			$seq++;
			update_term_meta( $tid, self::META_SECTION_ORDER, $seq );
		}

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/* ===================== ITEMS ===================== */

	public function get_items( $request ) {
		$menu_id    = (int) $request['menu_id'];
		$unassigned = ! empty( $request['unassigned'] );

		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'jprm_bad_menu', __( 'Missing or invalid menu_id.', 'jprm' ), [ 'status' => 400 ] );
		}

		// We do NOT guess your item→section linkage. We simply return items with {id,title}
		// so the builder can render and send back per-section ordering in /items/order.
		$items = [];
		$posts = get_posts( [
			'post_type'      => self::CPT_ITEM,
			'post_status'    => 'any',
			'posts_per_page' => 9999,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );
		foreach ( $posts as $pid ) {
			$items[] = [ 'id' => (int) $pid, 'title' => (string) get_the_title( $pid ) ];
		}

		// JS expects {items:[...]}
		return rest_ensure_response( [ 'items' => $items ] );
	}

	/**
	 * Persist per-section item order.
	 * Body: { menu_id:int, items:[{id:int, section_id:int, order:int}, ...] }
	 *
	 * NOTE: We deliberately do NOT modify how items are *linked* to sections (taxonomy or meta),
	 * because you asked to avoid guessing. Keep your existing linking code where it belongs.
	 * This only writes the `_jprm_order_in_section` meta for ordering inside the section.
	 */
	public function save_items_order( $request ) {
		$menu_id = (int) $request['menu_id'];
		$rows    = (array) $request['items']; // [{id,section_id,order}]
		if ( $menu_id <= 0 || ! is_array( $rows ) ) {
			return new \WP_Error( 'jprm_bad_params', __( 'menu_id and items are required.', 'jprm' ), [ 'status' => 400 ] );
		}

		foreach ( $rows as $row ) {
			$pid       = (int) ( $row['id'] ?? 0 );
			$sectionId = (int) ( $row['section_id'] ?? 0 );
			$order     = (int) ( $row['order'] ?? 0 );
			if ( ! $pid || ! $sectionId ) continue;

			// Guard section owner
			$owner = (int) get_term_meta( $sectionId, self::META_MENU_OWNER, true );
			if ( $owner !== $menu_id ) {
				return new \WP_Error( 'jprm_cross_menu', __( 'Cannot assign to a Section in another Menu.', 'jprm' ), [ 'status' => 400 ] );
			}

			// Persist per-section order for this item
			update_post_meta( $pid, self::META_ITEM_ORDER, $order );
		}

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/* ===================== Helpers ===================== */

	/**
	 * Ensure owner is set for a section and its descendants.
	 */
	private function cascade_owner( int $section_id, int $menu_id ) : void {
		update_term_meta( $section_id, self::META_MENU_OWNER, $menu_id );

		$children = get_terms( [
			'taxonomy'   => self::TAX_SECTION,
			'hide_empty' => false,
			'parent'     => $section_id,
		] );

		if ( is_wp_error( $children ) ) return;

		foreach ( $children as $c ) {
			update_term_meta( $c->term_id, self::META_MENU_OWNER, $menu_id );
		}
	}
}
