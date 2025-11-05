<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Sections_UX {

	public static function init() : void {
		add_action( 'wp_ajax_jprm_sections_for_menu', [ __CLASS__, 'ajax_sections_for_menu' ] );
	}

	public static function ajax_sections_for_menu() : void {
		// Matches the nonce you localize in class-plugin.php as JPRMAjax.nonce
		check_ajax_referer( 'jprm_sections', 'nonce' );

		$menu_id = isset($_GET['menu_id']) ? (int) $_GET['menu_id'] : 0;
		if ( $menu_id <= 0 ) {
			wp_send_json_success( [ 'sections' => [] ] );
		}

		// Ask the provider we just added in Sections_Admin via filter:
		$terms = apply_filters( 'jprm_get_sections_for_menu', [], $menu_id );

		// Normalize to a simple array with id/text/parent; add level (computed here)
		$nodes    = [];
		$children = [];
		foreach ( (array) $terms as $t ) {
			if ( $t && ! is_wp_error( $t ) ) {
				$tid = (int) $t->term_id;
				$txt = (string) $t->name;
				$par = (int) $t->parent;
				$nodes[ $tid ] = [ 'id' => $tid, 'text' => $txt, 'parent' => $par ];
				if ( ! isset( $children[ $par ] ) ) $children[ $par ] = [];
				$children[ $par ][] = $tid;
			}
		}

		$out = [];
		$walk = function( int $tid, int $level ) use ( &$walk, &$out, $nodes, $children ) {
			if ( ! isset( $nodes[ $tid ] ) ) return;
			$n = $nodes[ $tid ];
			$out[] = [ 'id' => $n['id'], 'text' => $n['text'], 'parent' => (int) $n['parent'], 'level' => $level ];
			if ( ! empty( $children[ $tid ] ) ) {
				foreach ( $children[ $tid ] as $cid ) $walk( (int) $cid, $level + 1 );
			}
		};

		$roots = $children[0] ?? [];
		foreach ( $roots as $rid ) $walk( (int) $rid, 0 );

		wp_send_json_success( [ 'sections' => $out ] );
	}
}
