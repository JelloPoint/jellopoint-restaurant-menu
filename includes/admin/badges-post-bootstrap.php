<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Attach “Dietary Badges” metabox to menu items and save selections.
 * Works for both CPT slugs: jprm_menu_item (canonical) and jprm_item (fallback).
 * Meta key: jprm_dietary_badges (array of slugs).
 */

//
// 0) Helpers
//
function jprm_badges_cpt_targets() : array {
	$targets = [];
	if ( post_type_exists('jprm_menu_item') ) $targets[] = 'jprm_menu_item';
	if ( post_type_exists('jprm_item') )      $targets[] = 'jprm_item';
	return array_values(array_unique($targets));
}

function jprm_badges_get_rows() : array {
	// Prefer the Store class if available (keeps one source of truth)
	if ( class_exists('\JelloPoint\RestaurantMenu\Badges\Store') ) {
		try {
			return \JelloPoint\RestaurantMenu\Badges\Store::instance()->get_rows();
		} catch (\Throwable $e) {
			// Fall through to option
		}
	}
	$rows = get_option('jprm_dietary_badges_v1', []);
	return is_array($rows) ? $rows : [];
}

//
// 1) Register metabox (generic hook) – catches most cases
//
add_action( 'add_meta_boxes', function() {
	$post_types = jprm_badges_cpt_targets();
	if ( empty($post_types) ) return;

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
}, 30 ); // run late to avoid being overwritten by other code

//
// 2) Also register on post-type–specific hooks – fixes odd load-order issues
//
add_action( 'add_meta_boxes_jprm_menu_item', function() {
	add_meta_box(
		'jprm_dietary_badges_box',
		__('Dietary Badges', 'jprm'),
		'jprm_render_badges_box',
		'jprm_menu_item',
		'side',
		'default'
	);
}, 30 );

add_action( 'add_meta_boxes_jprm_item', function() {
	add_meta_box(
		'jprm_dietary_badges_box',
		__('Dietary Badges', 'jprm'),
		'jprm_render_badges_box',
		'jprm_item',
		'side',
		'default'
	);
}, 30 );

//
// 3) Metabox renderer
//
function jprm_render_badges_box( $post ) {
	wp_nonce_field( 'jprm_badges_save', 'jprm_badges_nonce' );

	$selected = get_post_meta( $post->ID, 'jprm_dietary_badges', true );
	if ( ! is_array( $selected ) ) $selected = [];

	$rows = jprm_badges_get_rows();
	$active_rows = [];
	foreach ( $rows as $r ) {
		if ( ! empty($r['active']) && ! empty($r['name']) ) {
			$active_rows[] = $r;
		}
	}

	if ( empty( $active_rows ) ) {
		echo '<p>'.esc_html__('No active badges defined. Go to: JelloPoint → Dietary Badges.', 'jprm').'</p>';
		return;
	}

	echo '<div class="jprm-badges-checklist" style="margin-top:-4px; max-height: 260px; overflow:auto;">';
	foreach ( $active_rows as $r ) {
		$slug = sanitize_title( $r['name'] );
		$chk  = in_array( $slug, $selected, true ) ? 'checked' : '';
		echo '<label style="display:block;margin:6px 0;">';
		echo '<input type="checkbox" name="jprm_dietary_badges[]" value="'.esc_attr($slug).'" '.$chk.' /> ';
		// Optional icon preview (tiny)
		if ( ! empty($r['icon_url']) ) {
			echo '<img src="'.esc_url($r['icon_url']).'" alt="" style="width:14px;height:14px;object-fit:contain;margin-right:6px;vertical-align:-2px;border:1px solid #ccd0d4;background:#fff;border-radius:2px;">';
		}
		echo esc_html( $r['name'] );
		echo '</label>';
	}
	echo '</div>';
}

//
// 4) Save handler
//
add_action( 'save_post', function( $post_id ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;

	if ( ! isset($_POST['jprm_badges_nonce']) || ! wp_verify_nonce( $_POST['jprm_badges_nonce'], 'jprm_badges_save' ) ) return;
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

//
// 5) Editor-screen diagnostics (show a small notice ONLY on the post edit screens)
//
add_action( 'current_screen', function( $screen ) {
	if ( empty($screen) ) return;
	if ( $screen->base !== 'post' ) return; // only on post editor

	$post_type = $screen->post_type ?: '';
	if ( ! in_array( $post_type, ['jprm_menu_item','jprm_item'], true ) ) return;

	add_action( 'admin_notices', function() use ( $screen, $post_type ) {
		global $wp_meta_boxes;

		$has_box = false;
		if ( isset( $wp_meta_boxes[ $post_type ]['side']['default']['jprm_dietary_badges_box'] ) ) {
			$has_box = true;
		} elseif ( isset( $wp_meta_boxes[ $post_type ]['side']['core']['jprm_dietary_badges_box'] ) ) {
			$has_box = true;
		}

		$msg  = '<strong>JPRM Badges Debug</strong> — screen: <code>'.esc_html($screen->id).'</code>';
		$msg .= ' | post_type: <code>'.esc_html($post_type).'</code>';
		$msg .= ' | metabox: '. ( $has_box ? '<span style="color:#0a0;">present</span>' : '<span style="color:#a00;">missing</span>' );

		// If missing, suggest Screen Options check
		if ( ! $has_box ) {
			$msg .= ' — If hidden, open <em>Screen Options</em> (top right) and tick “Dietary Badges”.';
		}

		echo '<div class="notice notice-info"><p>'.$msg.'</p></div>';
	});
}, 9 );
