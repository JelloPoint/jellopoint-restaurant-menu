<?php
/**
 * JPRM – Dietary Badges Inspector
 *
 * Adds a Tools page:
 *   Tools → JPRM – Dietary Badges Inspector
 *
 * Also exposes a direct endpoint:
 *   /wp-admin/admin-post.php?action=jprm_badges_inspect&post_id=123
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render inspector HTML for both the Tools page and admin-post endpoint.
 */
function jprm_render_badges_inspector( $post_id = 0 ) {
	$option = get_option( 'jprm_dietary_badges', [] );
	if ( ! is_array( $option ) ) $option = [];

	$meta = [];
	if ( $post_id ) {
		$meta = get_post_meta( $post_id, 'jprm_item_badges', true );
		if ( ! is_array( $meta ) ) $meta = [];
	}

	echo '<div class="wrap">';
	echo '<h1>JPRM – Dietary Badges Inspector</h1>';

	// Small selector to pick a Menu Item post.
	$items = get_posts([
		'post_type'      => 'jprm_menu_item',
		'posts_per_page' => 50,
		'post_status'    => 'any',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'suppress_filters' => true,
	]);

	echo '<form method="get" action="">';
	echo '<input type="hidden" name="page" value="jprm-badges-inspector" />';
	echo '<label for="jprm_post_select">Select Menu Item:</label> ';
	echo '<select id="jprm_post_select" name="post_id">';
	echo '<option value="0">— None —</option>';
	foreach ( $items as $p ) {
		printf(
			'<option value="%d"%s>%s (ID %d)</option>',
			(int) $p->ID,
			selected( $post_id, (int) $p->ID, false ),
			esc_html( get_the_title( $p ) ?: '(no title)' ),
			(int) $p->ID
		);
	}
	echo '</select> ';
	submit_button( 'Inspect', 'secondary', '', false );
	echo '</form>';

	echo '<h2>Option: <code>jprm_dietary_badges</code></h2>';
	echo '<pre style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:12px;overflow:auto">';
	echo esc_html( print_r( $option, true ) );
	echo '</pre>';

	echo '<h2>Post Meta: <code>jprm_item_badges</code></h2>';
	if ( $post_id ) {
		echo '<p>Post ID: <code>'. (int) $post_id .'</code></p>';
		echo '<pre style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:12px;overflow:auto">';
		echo esc_html( print_r( $meta, true ) );
		echo '</pre>';
	} else {
		echo '<p style="color:#50575e">Select a Menu Item above to inspect its saved badges.</p>';
	}

	// Quick linkage to the admin-post endpoint as well.
	$admin_post_url = add_query_arg([
		'action'  => 'jprm_badges_inspect',
		'post_id' => $post_id ?: 0,
	], admin_url( 'admin-post.php' ) );
	echo '<p style="margin-top:12px;"><a class="button" href="'. esc_url( $admin_post_url ) .'">Open admin-post view</a></p>';

	echo '</div>';
}

/**
 * Tools → JPRM – Dietary Badges Inspector
 */
add_action( 'admin_menu', function () {
	add_management_page(
		'JPRM – Dietary Badges Inspector',
		'JPRM – Dietary Badges Inspector',
		'manage_options',
		'jprm-badges-inspector',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'jprm' ) );
			}
			$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
			jprm_render_badges_inspector( $post_id );
		}
	);
}, 50 );

/**
 * Admin-post endpoint:
 *   /wp-admin/admin-post.php?action=jprm_badges_inspect&post_id=123
 */
add_action( 'admin_post_jprm_badges_inspect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'jprm' ) );
	}

	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;

	// Render a minimal page outside the Tools menu chrome.
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	echo '<!doctype html><html><head><meta charset="'. esc_attr( get_bloginfo( 'charset' ) ) .'"><title>JPRM – Badges Inspector</title>';
	echo '<style>body{font:14px/1.45 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px} h1{margin:0 0 12px;font-size:20px} h2{margin:24px 0 8px;font-size:16px} pre{background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:12px;overflow:auto}</style>';
	echo '</head><body>';
	jprm_render_badges_inspector( $post_id );
	echo '</body></html>';
	exit;
});
