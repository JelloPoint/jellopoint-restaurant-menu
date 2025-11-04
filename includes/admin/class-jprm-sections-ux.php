<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin/editor UX helpers for Sections pickers in Elementor controls.
 *
 * Provides:
 * - AJAX: jprm_sections_for_menu ?menu_id=<id>
 *   Returns a JSON payload with a tree of sections (id, text, level, parent).
 *
 * HOW WE GET SECTIONS:
 * - We DO NOT guess the data model.
 * - We call the filter 'jprm_get_sections_for_menu' and expect back an array of WP_Term (jprm_section)
 *   or array-like objects with at least term_id, name, parent.
 *
 * You implement the filter where you already know the mapping Menu → Sections (e.g., your admin/service layer).
 *
 * Example filter stub (put elsewhere in your plugin):
 *
 *   add_filter('jprm_get_sections_for_menu', function($sections, $menu_id) {
 *       // Return array of WP_Term for taxonomy 'jprm_section' that belong to $menu_id, in your desired order
 *       return $sections;
 *   }, 10, 2);
 */
final class JPRM_Sections_UX {

	public static function bootstrap() : void {
		add_action( 'wp_ajax_jprm_sections_for_menu', [ __CLASS__, 'ajax_sections_for_menu' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_editor_assets' ] );
	}

	public static function enqueue_editor_assets() : void {
		$handle = 'jprm-elementor-sections-ux';
		$src    = plugins_url( '../../assets/admin/elementor-sections-ux.js', __FILE__ );
		wp_enqueue_script( $handle, $src, [ 'jquery' ], JPRM_PLUGIN_VERSION ?? time(), true );
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

		// Ask the host plugin/app for the correct sections list (no guessing here).
		$sections = apply_filters( 'jprm_get_sections_for_menu', [], $menu_id );

		// Normalize to a flat array of nodes [tid,name,parent]
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
				$nodes[$tid] = [ 'id'=>$tid, 'text'=>$name, 'parent'=>$parent ];
			}
		}

		// Build a tree order: roots first, then children (stable)
		$children = [];
		foreach ( $nodes as $n ) {
			$pid = (int)$n['parent'];
			if ( ! isset($children[$pid]) ) $children[$pid] = [];
			$children[$pid][] = $n['id'];
		}

		$out = [];
		$walk = function( int $tid, int $level ) use ( &$walk, &$out, $nodes, $children ) {
			if ( ! isset($nodes[$tid]) ) return;
			$n = $nodes[$tid];
			$out[] = [
				'id'    => $n['id'],
				'text'  => $n['text'],
				'level' => $level,
				'parent'=> (int)$n['parent'],
			];
			if ( ! empty($children[$tid]) ) {
				foreach ( $children[$tid] as $cid ) {
					$walk( (int)$cid, $level+1 );
				}
			}
		};

		$roots = isset($children[0]) ? $children[0] : [];
		foreach ( $roots as $rtid ) {
			$walk( (int)$rtid, 0 );
		}

		wp_send_json_success([
			'sections' => $out, // [{id,text,level,parent},...]
		]);
	}
}

JPRM_Sections_UX::bootstrap();
