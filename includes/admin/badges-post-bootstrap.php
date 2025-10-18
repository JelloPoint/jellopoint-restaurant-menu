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
		$includes_dir = dirname( __DIR__, 1 ); // points to /includes

		// Load dependencies for this screen.
		require_once $includes_dir . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		// Hard fail if required classes aren't present.
		if ( ! class_exists( '\JPRM_Badges_Store' ) || ! class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			\wp_die( \esc_html__( 'Dietary Badges screen could not be loaded. Missing classes (store/ui).', 'jprm' ) );
		}

		$store = new \JPRM_Badges_Store();
		$ui    = new \JPRM_Admin_Dietary_Badges( $store );
		$ui->render();
	}
}
