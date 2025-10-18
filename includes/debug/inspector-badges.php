<?php
/**
 * JPRM – Dietary Badges Inspector (hidden admin-post endpoint)
 *
 * Usage (admin only):
 *   /wp-admin/admin-post.php?action=jprm_badges_inspect&post_id=123
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_post_jprm_badges_inspect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'jprm' ) );
	}

	$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

	$option = get_option( 'jprm_dietary_badges', [] );
	if ( ! is_array( $option ) ) $option = [];

	$meta = [];
	if ( $post_id > 0 ) {
		$meta = get_post_meta( $post_id, 'jprm_item_badges', true );
		if ( ! is_array( $meta ) ) $meta = [];
	}

	// Simple HTML output
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	echo '<!doctype html><html><head><meta charset="'. esc_attr( get_bloginfo( 'charset' ) ) .'">';
	echo '<title>JPRM – Badges Inspector</title>';
	echo '<style>
	body{font:14px/1.45 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px}
	h1{margin:0 0 12px;font-size:20px}
	h2{margin:24px 0 8px;font-size:16px}
	pre{background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:12px;overflow:auto}
	code{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
	.meta{color:#50575e}
	</style>';
	echo '</head><body>';

	echo '<h1>JPRM – Dietary Badges Inspector</h1>';

	echo '<h2>Option: <code>jprm_dietary_badges</code></h2>';
	echo '<pre>'; echo esc_html( print_r( $option, true ) ); echo '</pre>';

	if ( $post_id > 0 ) {
		echo '<h2>Post Meta for post_id='. intval($post_id) .': <code>jprm_item_badges</code></h2>';
		echo '<pre>'; echo esc_html( print_r( $meta, true ) ); echo '</pre>';
	} else {
		echo '<p class="meta">Add <code>&amp;post_id=123</code> to also inspect a menu item\'s saved badges.</p>';
	}

	echo '</body></html>';
	exit;
});
