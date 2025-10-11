<?php
/**
 * Always-available admin bootstrap for Dietary Badges.
 * - Registers the admin-post handler on submit
 * - Registers the "Dietary Badges" meta box on Edit Menu Item
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1) Ensure the admin-post handler is registered exactly when saving badges
 *    (admin-post.php calls admin_init before doing the action).
 */
add_action('admin_init', function () {
	if ( isset($_POST['action']) && $_POST['action'] === 'jprm_save_dietary_badges' ) {
		$base = dirname(__DIR__, 1); // /includes
		require_once $base . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		$store = new \JPRM_Badges_Store();
		new \JPRM_Admin_Dietary_Badges( $store ); // constructor registers admin_post handler
	}
}, 1);


/**
 * 2) Add the "Dietary Badges" meta box to the Edit Menu Item screen.
 *    We load only on post.php / post-new.php and only for post_type=jprm_menu_item.
 */
function jprm_bootstrap_menuitem_badges_metabox_loader() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->base !== 'post' ) {
		return;
	}

	// Only for our CPT
	$post_type = isset($_GET['post_type']) ? (string) $_GET['post_type'] : '';
	if ( empty($post_type) && isset($_GET['post']) ) {
		$post = get_post( (int) $_GET['post'] );
		if ( $post && $post instanceof WP_Post ) {
			$post_type = $post->post_type;
		}
	}
	if ( $post_type !== 'jprm_menu_item' ) {
		return;
	}

	// Load store + meta class and instantiate
	$base = dirname(__DIR__, 1); // /includes
	require_once $base . '/data/class-badges-store.php';
	require_once __DIR__ . '/class-admin-menuitem-badges-meta.php';

	$store = new \JPRM_Badges_Store();
	new \JPRM_MenuItem_Badges_Meta( $store );
}
add_action( 'load-post.php',     'jprm_bootstrap_menuitem_badges_metabox_loader' );
add_action( 'load-post-new.php', 'jprm_bootstrap_menuitem_badges_metabox_loader' );
