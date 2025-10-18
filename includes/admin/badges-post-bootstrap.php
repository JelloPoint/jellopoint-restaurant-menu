<?php
/**
 * JelloPoint Restaurant Menu – Admin bootstrap for Dietary Badges screen + metabox loader
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

		if ( method_exists( $ui, 'handle_post' ) ) {
			$ui->handle_post(); // should redirect on success
		}

		\wp_die( \esc_html__( 'Dietary Badges save handler not found.', 'jprm' ) );
	}
}

/**
 * Ensure the "Dietary Badges" metabox is available on the Menu Item editor.
 * We only load the class and instantiate it on jprm_menu_item screens.
 */
function jprm_bootstrap_menuitem_badges_metabox_loader() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) {
		return;
	}

	$includes_dir = dirname( __DIR__, 1 ); // /includes
	require_once $includes_dir . '/data/class-badges-store.php';
	require_once __DIR__ . '/class-admin-menuitem-badges-meta.php';

	if ( class_exists( '\JPRM_Badges_Store' ) && class_exists( '\JPRM_MenuItem_Badges_Meta' ) ) {
		$store = new \JPRM_Badges_Store();
		new \JPRM_MenuItem_Badges_Meta( $store );
	}
}
add_action( 'load-post.php',     __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
add_action( 'load-post-new.php', __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
