<?php
// Always-available POST hook for Dietary Badges.
// Loads only when the POST action is our badges save.

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_init', function () {
	if ( isset($_POST['action']) && $_POST['action'] === 'jprm_save_dietary_badges' ) {
		$base = dirname(__DIR__, 1); // /includes
		require_once $base . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		$store = new \JPRM_Badges_Store();
		new \JPRM_Admin_Dietary_Badges( $store ); // constructor registers admin_post handler
	}
}, 1);
