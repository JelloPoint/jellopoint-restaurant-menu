<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Sections_UX {

	public static function init() : void {
		// AJAX
		add_action( 'wp_ajax_jprm_sections_for_menu', [ __CLASS__, 'ajax_sections_for_menu' ] );

		// Try both Elementor hooks (some builds only fire one)
		add_action( 'elementor/editor/before_enqueue_scripts', [ __CLASS__, 'enqueue_editor_assets' ] );
		add_action( 'elementor/editor/after_enqueue_scripts',  [ __CLASS__, 'enqueue_editor_assets' ] );

		// Final fallback if Elementor hook is missed for some reason
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_on_elementor_screen' ] );
	}

	private static function plugin_file_path() : string {
		// Most JelloPoint plugins define this; fallback to guessing.
		if ( defined('JPRM_PLUGIN_FILE') && JPRM_PLUGIN_FILE ) return JPRM_PLUGIN_FILE;
		// Go up two levels from this file to reach the main plugin dir.
		$root = dirname( dirname( __FILE__ ) );
		// Try to find the first main file in that dir
		$candidates = glob( $root . '/*.php' );
		return $candidates && is_array($candidates) ? $candidates[0] : __FILE__;
	}

	private static function script_url( string $rel ) : string {
		// Prefer a constant if available (most of your plugins have it)
		if ( defined('JPRM_PLUGIN_URL') && JPRM_PLUGIN_URL ) {
			return rtrim( JPRM_PLUGIN_URL, '/\\' ) . '/' . ltrim( $rel, '/\\' );
		}
		// Safe fallback: plugins_url relative to the main plugin file
		return plugins_url( $rel, self::plugin_file_path() );
	}

	public static function enqueue_editor_assets() : void {
		self::enqueue_once();
	}

	public static function maybe_enqueue_on_elementor_screen( $hook ) : void {
		// Only load in the Elementor editor
		if ( isset($_GET['action']) && $_GET['action'] === 'elementor' ) { // phpcs:ignore
			self::enqueue_once();
		}
	}

	private static $enqueued = false;
	private static function enqueue_once() : void {
		if ( self::$enqueued ) return;
		self::$enqueued = true;

		$handle = 'jprm-elementor-sections-dep';
		$src    = self::script_url( 'assets/admin/elementor-sections-dep.js' );
		$ver    = defined('JPRM_PLUGIN_VERSION') ? JPRM_PLUGIN_VERSION : time();

		wp_enqueue_script( $handle, $src, [ 'jquery' ], $ver, true );
		wp_localize_script( $handle, 'JPRMSectionsUX', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jprm_sections_ux' ),
		] );

		// (Optional but helpful) add a console ping so we *know* it loaded.
		wp_add_inline_script( $handle, 'console.log("[JPRM] sections dep script enqueued:", ' . json_encode( $src ) . ');' );
	}

	public static function ajax_sections_for_menu() : void {
		check_ajax_referer( 'jprm_sections_ux', 'nonce' );
		$menu_id = isset($_GET['menu_id']) ? (int) $_GET['menu_id'] : 0;

		if ( $menu_id <= 0 ) {
			wp_send_json_success( [ 'sections' => [] ] );
		}

		// Ask the host for authoritative list (your Sections_Admin wires the filter)
		$terms = apply_filters( 'jprm_get_sections_for_menu', [], $menu_id );
		$nodes = [];

		foreach ( (array) $terms as $t ) {
			if ( is_object( $t ) ) {
				$tid = (int) ($t->term_id ?? 0);
				$txt = (string) ($t->name ?? '');
				$par = (int) ($t->parent ?? 0);
			} else {
				$tid = (int) ($t['term_id'] ?? 0);
				$txt = (string) ($t['name'] ?? '');
				$par = (int) ($t['parent'] ?? 0);
			}
			if ( $tid > 0 && $txt !== '' ) {
				$nodes[ $tid ] = [ 'id' => $tid, 'text' => $txt, 'parent' => $par ];
			}
		}

		// Tree order with level
		$children = [];
		foreach ( $nodes as $n ) {
			$pid = (int) $n['parent'];
			if ( ! isset( $children[ $pid ] ) ) $children[ $pid ] = [];
			$children[ $pid ][] = $n['id'];
		}

		$out  = [];
		$walk = function( int $tid, int $level ) use ( &$walk, &$out, $nodes, $children ) {
			if ( ! isset( $nodes[ $tid ] ) ) return;
			$n = $nodes[ $tid ];
			$out[] = [
				'id'     => $n['id'],
				'text'   => $n['text'],
				'level'  => $level,
				'parent' => (int) $n['parent'],
			];
			if ( ! empty( $children[ $tid ] ) ) {
				foreach ( $children[ $tid ] as $cid ) $walk( (int) $cid, $level + 1 );
			}
		};

		$roots = isset( $children[0] ) ? $children[0] : [];
		foreach ( $roots as $rid ) $walk( (int) $rid, 0 );

		wp_send_json_success( [ 'sections' => $out ] );
	}
}
