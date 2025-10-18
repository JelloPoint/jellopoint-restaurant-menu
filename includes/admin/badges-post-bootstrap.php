<?php
/**
 * JelloPoint Restaurant Menu – Admin bootstrap for Dietary Badges screen + metabox loader
 */
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Badges_Post_Bootstrap {

	/**
	 * Render the Dietary Badges admin screen.
	 */
	public static function render_screen() : void {
		$includes_dir = dirname( __DIR__, 1 ); // /includes
		require_once $includes_dir . '/data/class-badges-store.php';
		require_once __DIR__ . '/class-admin-dietary-badges.php';

		if ( ! class_exists( '\JPRM_Badges_Store' ) || ! class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			\wp_die( \esc_html__( 'Dietary Badges screen could not be loaded. Missing classes (store/ui).', 'jprm' ) );
		}

		$store = new \JPRM_Badges_Store();
		$ui    = new \JPRM_Admin_Dietary_Badges( $store );
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
 * Ensure the "Dietary Badges" metabox is available on the Menu Item editor,
 * and default it into the LEFT column (normal) just after the Pricing box if possible.
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

	/**
	 * Try to influence the default order so our box sits after Pricing.
	 * This only affects users who don't have a saved personal order yet.
	 * We assume the Pricing metabox id is 'jprm_item_prices' (common in your plugin).
	 * If your pricing box has another id, change $pricing_id below.
	 */
	add_filter( 'get_user_option_meta-box-order_' . $screen->id, function( $order ) {
		$pricing_id = 'jprm_item_prices';
		$badges_id  = 'jprm_item_badges';

		// Build a sane default if none.
		if ( ! is_array( $order ) ) {
			$order = [ 'normal' => '', 'advanced' => '', 'side' => '' ];
		}
		foreach ( [ 'normal', 'advanced', 'side' ] as $zone ) {
			if ( ! isset( $order[ $zone ] ) ) $order[ $zone ] = '';
		}

		// Ensure our badges box is in the 'normal' column list.
		$normal = array_filter( array_map( 'trim', explode( ',', (string) $order['normal'] ) ) );
		// Remove from anywhere else
		$advanced = array_filter( array_map( 'trim', explode( ',', (string) $order['advanced'] ) ) );
		$side     = array_filter( array_map( 'trim', explode( ',', (string) $order['side'] ) ) );

		$advanced = array_values( array_diff( $advanced, [ $badges_id ] ) );
		$side     = array_values( array_diff( $side,     [ $badges_id ] ) );
		$normal   = array_values( array_diff( $normal,   [ $badges_id ] ) );

		// Insert after pricing if pricing is known; otherwise append near the top.
		$insert_at = array_search( $pricing_id, $normal, true );
		if ( $insert_at !== false ) {
			array_splice( $normal, $insert_at + 1, 0, [ $badges_id ] );
		} else {
			array_unshift( $normal, $badges_id );
		}

		$order['normal']   = implode( ',', array_unique( $normal ) );
		$order['advanced'] = implode( ',', array_unique( $advanced ) );
		$order['side']     = implode( ',', array_unique( $side ) );

		return $order;
	}, 10 );
}
add_action( 'load-post.php',     __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
add_action( 'load-post-new.php', __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
