<?php
/**
 * JelloPoint Restaurant Menu – Admin bootstrap for Dietary Badges screen + metabox loader
 */
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Badges_Post_Bootstrap {

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
 * Load the metabox class on jprm_menu_item screens
 * and nudge its position to be between Pricing and Visibility in the left column.
 */
function jprm_bootstrap_menuitem_badges_metabox_loader() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'jprm_menu_item' ) {
		return;
	}

	$includes_dir = dirname( __DIR__, 1 ); // /includes
	require_once $includes_dir . '/data/class-badges-store.php';
	require_once __DIR__ . '/class-admin-menuitem-badges-meta.php';

	// Instantiate metabox.
	if ( class_exists( '\JPRM_Badges_Store' ) && class_exists( '\JPRM_MenuItem_Badges_Meta' ) ) {
		$store = new \JPRM_Badges_Store();
		new \JPRM_MenuItem_Badges_Meta( $store );
	}

	/**
	 * Default the metabox order so our box sits between Pricing and Visibility.
	 * Your IDs:
	 *  - Pricing:    jprm_price_meta
	 *  - Visibility: jprm_item_vis
	 *  - Badges:     jprm_item_badges
	 *
	 * This only affects users without a saved personal metabox order.
	 */
	add_filter( 'get_user_option_meta-box-order_' . $screen->id, function( $order ) {
		$pricing_id    = 'jprm_price_meta';
		$badges_id     = 'jprm_item_badges';
		$visibility_id = 'jprm_item_vis';

		if ( ! is_array( $order ) ) {
			$order = [ 'normal' => '', 'advanced' => '', 'side' => '' ];
		}
		foreach ( [ 'normal', 'advanced', 'side' ] as $zone ) {
			if ( ! isset( $order[ $zone ] ) ) $order[ $zone ] = '';
		}

		$normal   = array_values( array_filter( array_map( 'trim', explode( ',', (string) $order['normal'] ) ) ) );
		$advanced = array_values( array_filter( array_map( 'trim', explode( ',', (string) $order['advanced'] ) ) ) );
		$side     = array_values( array_filter( array_map( 'trim', explode( ',', (string) $order['side'] ) ) ) );

		// Remove badges from any zone first.
		$normal   = array_values( array_diff( $normal,   [ $badges_id ] ) );
		$advanced = array_values( array_diff( $advanced, [ $badges_id ] ) );
		$side     = array_values( array_diff( $side,     [ $badges_id ] ) );

		$pi = array_search( $pricing_id,    $normal, true );
		$vi = array_search( $visibility_id, $normal, true );

		// Insert right after Pricing, but before Visibility if present.
		if ( $pi !== false ) {
			$insert_at = $pi + 1;
		} elseif ( $vi !== false ) {
			$insert_at = max( 0, (int) $vi ); // before visibility
		} else {
			$insert_at = 0; // best effort near top
		}

		// If Visibility exists and our insert position would land after it, move before it.
		if ( $vi !== false && $insert_at > $vi ) {
			$insert_at = $vi;
		}

		array_splice( $normal, min( $insert_at, count( $normal ) ), 0, [ $badges_id ] );

		$order['normal']   = implode( ',', array_unique( $normal ) );
		$order['advanced'] = implode( ',', array_unique( $advanced ) );
		$order['side']     = implode( ',', array_unique( $side ) );

		return $order;
	}, 10 );
}
add_action( 'load-post.php',     __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
add_action( 'load-post-new.php', __NAMESPACE__ . '\\jprm_bootstrap_menuitem_badges_metabox_loader' );
