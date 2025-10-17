<?php
/**
 * Always-available admin bootstrap for Dietary Badges.
 * - Ensures the admin-post handler for the Badges list page
 * - Ensures the Menu Item meta box is registered AND its save_post handler is active during save
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
		new \JPRM_Admin_Dietary_Badges( $store ); // registers admin_post_jprm_save_dietary_badges
	}
}, 1);


/**
 * 2) Instantiate the Menu Item meta box class early on ANY admin request that
 *    is saving/editing a jprm_menu_item, so its save_post handler is registered.
 */
add_action('admin_init', function () {
	// Detect the post type on save requests and normal loads.
	$post_type = null;

	// a) Direct hint from POST when saving
	if ( isset($_POST['post_type']) && is_string($_POST['post_type']) ) {
		$post_type = $_POST['post_type'];
	}

	// b) From ?post=ID when loading/editing
	if ( ! $post_type && isset($_GET['post']) ) {
		$maybe = get_post( (int) $_GET['post'] );
		if ( $maybe instanceof WP_Post ) {
			$post_type = $maybe->post_type;
		}
	}

	// c) From ?post_type=... when creating new
	if ( ! $post_type && isset($_GET['post_type']) && is_string($_GET['post_type']) ) {
		$post_type = $_GET['post_type'];
	}

	if ( $post_type !== 'jprm_menu_item' ) {
		return;
	}

	$base = dirname(__DIR__, 1); // /includes
	require_once $base . '/data/class-badges-store.php';
	require_once __DIR__ . '/class-admin-menuitem-badges-meta.php';

	$store = new \JPRM_Badges_Store();
	new \JPRM_MenuItem_Badges_Meta( $store ); // registers add_meta_boxes AND save_post_jprm_menu_item
}, 1);


/**
 * 3) (Nice-to-have) still load the meta box on edit screens via load-* hooks
 *    so CSS/markup are available when viewing the editor.
 */
function jprm_bootstrap_menuitem_badges_metabox_loader() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->base !== 'post' ) {
		return;
	}

	$post_type = $screen->post_type;
	if ( $post_type !== 'jprm_menu_item' ) {
		return;
	}

	$base = dirname(__DIR__, 1); // /includes
	require_once $base . '/data/class-badges-store.php';
	require_once __DIR__ . '/class-admin-menuitem-badges-meta.php';

	$store = new \JPRM_Badges_Store();
	new \JPRM_MenuItem_Badges_Meta( $store );
}
add_action( 'load-post.php',     'jprm_bootstrap_menuitem_badges_metabox_loader' );
add_action( 'load-post-new.php', 'jprm_bootstrap_menuitem_badges_metabox_loader' );
