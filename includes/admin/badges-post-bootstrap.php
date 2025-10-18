<?php
/**
 * JelloPoint Restaurant Menu – Admin bootstrap for Dietary Badges screen
 */
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Badges_Post_Bootstrap {

	/**
	 * Render the Dietary Badges admin screen.
	 * Loads only the two required classes and hands off to the UI.
	 */
	public static function render_screen() : void {
		$includes_dir = dirname( __DIR__, 1 ); // /includes

		// Load dependencies for this screen.
		require_once $includes_dir . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		if ( ! class_exists( '\JPRM_Badges_Store' ) || ! class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			\wp_die( \esc_html__( 'Dietary Badges screen could not be loaded. Missing classes (store/ui).', 'jprm' ) );
		}

		$store = new \JPRM_Badges_Store();
		$ui    = new \JPRM_Admin_Dietary_Badges( $store );

		// Your UI class exposes render_page().
		$ui->render_page();
	}

	/**
	 * Handle POST from admin-post.php?action=jprm_save_dietary_badges
	 * Must be available during any admin-post request, not only on screen render.
	 */
	public static function handle_post() : void {
		$includes_dir = dirname( __DIR__, 1 ); // /includes

		require_once $includes_dir . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		if ( ! class_exists( '\JPRM_Badges_Store' ) || ! class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			\wp_die( \esc_html__( 'Dietary Badges save failed. Missing classes (store/ui).', 'jprm' ) );
		}

		$store = new \JPRM_Badges_Store();
		$ui    = new \JPRM_Admin_Dietary_Badges( $store );

		// Delegate to the UI’s own handler (it has the exact nonce names and capabilities).
		if ( method_exists( $ui, 'handle_post' ) ) {
			$ui->handle_post(); // will wp_safe_redirect() on success
		}

		// If the class unexpectedly lacks the method:
		\wp_die( \esc_html__( 'Dietary Badges save handler not found.', 'jprm' ) );
	}
}
