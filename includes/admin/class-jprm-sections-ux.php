<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Sections_UX {

	/**
	 * Hook ajax endpoint.
	 */
	public static function init() : void {
		add_action( 'wp_ajax_jprm_sections_for_menu', [ __CLASS__, 'ajax_sections_for_menu' ] );
	}

	/**
	 * AJAX: return sections scoped to a given Menu as a flat tree:
	 * [ {id, text, parent, level}, ... ] in parent-first order.
	 *
	 * Request params (GET or POST both supported):
	 * - menu_id (int) : 0 => all sections (full tree)
	 * - nonce         : wp nonce for 'jprm_sections' (or _ajax_nonce)
	 */
	public static function ajax_sections_for_menu() : void {
		// Accept both 'nonce' and WP's _ajax_nonce.
		$nonce = isset($_REQUEST['nonce']) ? (string) $_REQUEST['nonce'] : ( (isset($_REQUEST['_ajax_nonce'])) ? (string) $_REQUEST['_ajax_nonce'] : '' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'jprm_sections' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		$menu_id = isset($_REQUEST['menu_id']) ? (int) $_REQUEST['menu_id'] : 0;

		try {
			$terms = self::get_scoped_terms( $menu_id );
			$payload = self::build_tree_payload( $terms );
			wp_send_json_success( [ 'sections' => $payload ] );
		} catch ( \Throwable $e ) {
			error_log('[JPRM AJAX] sections_for_menu fatal: ' . $e->getMessage());
			wp_send_json_error( [ 'message' => 'server_error' ], 500 );
		}
	}

	/**
	 * Try provider (filter); if empty, fallback to owner-scoped tree by meta.
	 * If $menu_id === 0, return the full tree (all sections).
	 *
	 * @return \WP_Term[]
	 */
	private static function get_scoped_terms( int $menu_id ) : array {
		$tax  = 'jprm_section';
		$meta = '_jprm_menu_term_id';

		// 1) Preferred: ask a provider filter if someone registered it.
		$provided = apply_filters( 'jprm_get_sections_for_menu', [], $menu_id );
		if ( is_array( $provided ) && ! empty( $provided ) ) {
			// Ensure all entries are WP_Term-like; if not, ignore provider.
			$ok = true;
			foreach ( $provided as $t ) {
				if ( ! is_object( $t ) || ! isset( $t->term_id, $t->name, $t->parent ) ) { $ok = false; break; }
			}
			if ( $ok ) return $provided;
		}

		// 2) Fallback: owner-scoped terms (keeps mains even if they don't have items)
		$args = [
			'taxonomy'   => $tax,
			'hide_empty' => false,
		];
		if ( $menu_id > 0 ) {
			$args['meta_query'] = [
				[
					'key'   => $meta,
					'value' => (string) $menu_id,
				],
			];
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) return [];
		return is_array( $terms ) ? $terms : [];
	}

	/**
	 * Build a flat, ordered payload with level indentation.
	 *
	 * @param \WP_Term[] $terms
	 * @return array<int, array{id:int,text:string,parent:int,level:int}>
	 */
	private static function build_tree_payload( array $terms ) : array {
		if ( empty( $terms ) ) return [];

		// Map & children
		$by_id = []; $children = [];
		foreach ( $terms as $t ) {
			$tid = (int) $t->term_id;
			$pid = (int) $t->parent;
			$by_id[ $tid ] = $t;
			if ( ! isset( $children[ $pid ] ) ) $children[ $pid ] = [];
			$children[ $pid ][] = $tid;
		}

		// Roots: parent==0 or parent not present
		$roots = [];
		foreach ( $by_id as $tid => $t ) {
			$pid = (int) $t->parent;
			if ( $pid === 0 || ! isset( $by_id[ $pid ] ) ) $roots[] = $tid;
		}

		// Sort roots by name (or by term_order if you rely on it)
		usort( $roots, function( $a, $b ) use ( $by_id ) {
			$ta = $by_id[$a]; $tb = $by_id[$b];
			$oa = isset( $ta->term_order ) ? (int)$ta->term_order : 0;
			$ob = isset( $tb->term_order ) ? (int)$tb->term_order : 0;
			if ( $oa !== $ob ) return $oa <=> $ob;
			return strcasecmp( (string)$ta->name, (string)$tb->name );
		} );

		$out = [];
		$emit = function( int $id, int $level ) use ( &$emit, &$out, $by_id, $children ) {
			if ( ! isset( $by_id[$id] ) ) return;
			$t = $by_id[$id];
			$out[] = [
				'id'     => (int) $t->term_id,
				'text'   => (string) $t->name,
				'parent' => (int) $t->parent,
				'level'  => $level,
			];
			if ( ! empty( $children[$id] ) ) {
				foreach ( $children[$id] as $cid ) $emit( (int)$cid, $level + 1 );
			}
		};

		foreach ( $roots as $rid ) $emit( (int)$rid, 0 );

		return $out;
	}
}
