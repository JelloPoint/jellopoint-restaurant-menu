<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Elementor editor UX helpers for section pickers.
 *
 * - AJAX action: jprm_sections_for_menu?menu_id=<id>
 *   Returns a flat, tree-ordered list [{id,text,level,parent}, ...]
 * - Enqueues editor JS that repopulates section selects when Menu changes.
 *
 * The actual “which sections belong to this menu” mapping is provided by the host
 * via filter: `jprm_get_sections_for_menu` and is already wired in Sections_Admin.
 */
final class Sections_UX {

	public static function bootstrap() : void {
		add_action( 'wp_ajax_jprm_sections_for_menu', [ __CLASS__, 'ajax_sections_for_menu' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_editor_assets' ] );
	}

	public static function enqueue_editor_assets() : void {
		$handle = 'jprm-elementor-sections-ux';
		$src    = plugins_url( '../assets/admin/elementor-sections-ux.js', dirname( __FILE__ ) );
		// If you have a constant, use it; otherwise fall back to time() for cache-busting in dev
		$ver    = defined('JPRM_PLUGIN_VERSION') ? JPRM_PLUGIN_VERSION : time();
		wp_enqueue_script( $handle, $src, [ 'jquery' ], $ver, true );
		wp_localize_script( $handle, 'JPRMSectionsUX', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jprm_sections_ux' ),
		] );
	}

	public static function ajax_sections_for_menu() : void {
		check_ajax_referer( 'jprm_sections_ux', 'nonce' );

		$menu_id = isset($_GET['menu_id']) ? (int) $_GET['menu_id'] : 0;
		if ( $menu_id <= 0 ) {
			wp_send_json_success([ 'sections' => [] ]);
		}

		// Ask the plugin for the authoritative list (no guessing here)
		$sections = apply_filters( 'jprm_get_sections_for_menu', [], $menu_id );

		// Normalize to nodes
		$nodes = [];
		foreach ( (array) $sections as $t ) {
			if ( is_object($t) ) {
				$tid    = (int)($t->term_id ?? 0);
				$name   = (string)($t->name ?? '');
				$parent = (int)($t->parent ?? 0);
			} else {
				$tid    = (int)($t['term_id'] ?? 0);
				$name   = (string)($t['name'] ?? '');
				$parent = (int)($t['parent'] ?? 0);
			}
			if ( $tid > 0 && $name !== '' ) {
				$nodes[$tid] = [ 'id' => $tid, 'text' => $name, 'parent' => $parent ];
			}
		}

		// Build tree order
		$children = [];
		foreach ( $nodes as $n ) {
			$pid = (int) $n['parent'];
			if ( ! isset($children[$pid]) ) $children[$pid] = [];
			$children[$pid][] = $n['id'];
		}

		$out  = [];
		$walk = function( int $tid, int $level ) use ( &$walk, &$out, $nodes, $children ) {
			if ( ! isset($nodes[$tid]) ) return;
			$n = $nodes[$tid];
			$out[] = [
				'id'     => $n['id'],
				'text'   => $n['text'],
				'level'  => $level,
				'parent' => (int) $n['parent'],
			];
			if ( ! empty($children[$tid]) ) {
				foreach ( $children[$tid] as $cid ) {
					$walk( (int) $cid, $level + 1 );
				}
			}
		};

		$roots = isset($children[0]) ? $children[0] : [];
		foreach ( $roots as $rid ) {
			$walk( (int) $rid, 0 );
		}

		wp_send_json_success([ 'sections' => $out ]);
	}
}

// Boot
Sections_UX::bootstrap();
