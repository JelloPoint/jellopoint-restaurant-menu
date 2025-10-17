<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Attach Dietary Badges to menu items.
 * Saves to post meta key: jprm_dietary_badges (array of slugs).
 */
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'jprm_dietary_badges_box',
		__('Dietary Badges', 'jprm'),
		'jprm_render_badges_box',
		'jprm_menu_item',   // <-- adjust if your CPT differs; earlier you had jprm_menu_item
		'side',
		'default'
	);
}, 20 );

function jprm_render_badges_box( $post ) {
	wp_nonce_field( 'jprm_badges_save', 'jprm_badges_nonce' );

	$selected = get_post_meta( $post->ID, 'jprm_dietary_badges', true );
	if ( ! is_array( $selected ) ) $selected = [];

	$rows = [];
	if ( class_exists('\JelloPoint\RestaurantMenu\Badges\Store') ) {
		$rows = \JelloPoint\RestaurantMenu\Badges\Store::instance()->get_rows();
	} else {
		$rows = get_option('jprm_dietary_badges_v1', []);
		if ( ! is_array($rows) ) $rows = [];
	}

	echo '<div class="jprm-badges-checklist">';
	if ( empty($rows) ) {
		echo '<p>'.esc_html__('No badges defined yet.', 'jprm').'</p>';
	} else {
		foreach ( $rows as $r ) {
			if ( empty($r['active']) || empty($r['name']) ) continue;
			$slug = sanitize_title( $r['name'] );
			$chk  = in_array( $slug, $selected, true ) ? 'checked' : '';
			echo '<label style="display:block;margin-bottom:6px;">';
			echo '<input type="checkbox" name="jprm_dietary_badges[]" value="'.esc_attr($slug).'" '.$chk.' /> ';
			echo esc_html( $r['name'] );
			echo '</label>';
		}
	}
	echo '</div>';
}

add_action( 'save_post', function( $post_id ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! isset($_POST['jprm_badges_nonce']) || ! wp_verify_nonce( $_POST['jprm_badges_nonce'], 'jprm_badges_save' ) ) return;

	// Capability check
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
