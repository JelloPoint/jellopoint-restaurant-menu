<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Attach Dietary Badges to menu items.
 * Saves to post meta key: jprm_dietary_badges (array of slugs).
 * Works for both CPT slugs: jprm_menu_item (canonical) AND jprm_item (fallback).
 */

add_action( 'current_screen', function( $screen ){
	// Helps when debugging which post type we’re on.
	// error_log('[JPRM] current_screen: ' . ( $screen->id ?? 'n/a' ) );
}, 1 );

add_action( 'add_meta_boxes', function() {
	$post_types = array_filter([
		post_type_exists('jprm_menu_item') ? 'jprm_menu_item' : null,
		post_type_exists('jprm_item')      ? 'jprm_item'      : null,
	]);

	if ( empty( $post_types ) ) return;

	foreach ( $post_types as $pt ) {
		add_meta_box(
			'jprm_dietary_badges_box',
			__('Dietary Badges', 'jprm'),
			'jprm_render_badges_box',
			$pt,
			'side',
			'default'
		);
	}
}, 20 ); // run after CPTs are likely registered

function jprm_render_badges_box( $post ) {
	wp_nonce_field( 'jprm_badges_save', 'jprm_badges_nonce' );

	$selected = get_post_meta( $post->ID, 'jprm_dietary_badges', true );
	if ( ! is_array( $selected ) ) $selected = [];

	// Read rows from the same option you confirmed in DB (…_v1), via Store if present.
	if ( class_exists('\JelloPoint\RestaurantMenu\Badges\Store') ) {
		$rows = \JelloPoint\RestaurantMenu\Badges\Store::instance()->get_rows();
	} else {
		$rows = get_option('jprm_dietary_badges_v1', []);
		if ( ! is_array($rows) ) $rows = [];
	}

	if ( empty( $rows ) ) {
		echo '<p>'.esc_html__('No badges defined yet. Go to: JelloPoint → Dietary Badges.', 'jprm').'</p>';
		return;
	}

	echo '<div class="jprm-badges-checklist" style="margin-top:-4px">';
	foreach ( $rows as $r ) {
		if ( empty($r['active']) || empty($r['name']) ) continue;
		$slug = sanitize_title( $r['name'] );
		$chk  = in_array( $slug, $selected, true ) ? 'checked' : '';
		echo '<label style="display:block;margin:6px 0;">';
		echo '<input type="checkbox" name="jprm_dietary_badges[]" value="'.esc_attr($slug).'" '.$chk.' /> ';
		echo esc_html( $r['name'] );
		echo '</label>';
	}
	echo '</div>';
}

add_action( 'save_post', function( $post_id ) {
	// Autosave / nonce / capability guards
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! isset($_POST['jprm_badges_nonce']) || ! wp_verify_nonce( $_POST['jprm_badges_nonce'], 'jprm_badges_save' ) ) return;

	// Respect capability for whichever CPT is used
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$vals = isset($_POST['jprm_dietary_badges']) ? (array) $_POST['jprm_dietary_badges'] : [];
	$vals = array_map( 'sanitize_title', $vals );
	$vals = array_values( array_unique( array_filter( $vals ) ) );

	if ( empty( $vals ) ) {
		delete_post_meta( $post_id, 'jprm_dietary_badges' );
	} else {
		update_post_meta( $post_id, 'jprm_dietary_badges', $vals );
	}
}, 20 );
