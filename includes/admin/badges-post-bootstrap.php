<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Badges_Post_Bootstrap {

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_screen' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_jprm_badges_save', [ __CLASS__, 'handle_save' ] );
	}

	public static function register_screen() : void {
		add_submenu_page(
			'jprm_main_menu',
			__( 'Dietary Badges', 'jprm' ),
			__( 'Dietary Badges', 'jprm' ),
			'manage_options',
			'jprm_dietary_badges',
			[ __CLASS__, 'render_screen' ]
		);
	}

	/** Only load our admin JS on the badges page */
	public static function enqueue_admin_assets( $hook ) : void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'jprm_dietary_badges' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$base = plugin_dir_url( dirname( __DIR__, 1 ) ); // points to /includes/
		wp_enqueue_media();
		wp_enqueue_script(
			'jprm-badges-admin',
			$base . 'admin/assets/badges-admin.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	public static function render_screen() : void {
		if ( ! class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			wp_die( esc_html__( 'Dietary Badges screen could not be loaded. Missing classes.', 'jprm' ) );
		}
		\JPRM_Admin_Dietary_Badges::render();
	}

	public static function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed', 'jprm' ) );
		}
		check_admin_referer( 'jprm_badges_save', 'jprm_nonce' );

		if ( class_exists( '\JPRM_Admin_Dietary_Badges' ) ) {
			\JPRM_Admin_Dietary_Badges::save();
		}

		wp_redirect( add_query_arg( [ 'page' => 'jprm_dietary_badges', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}

// Kick it
Badges_Post_Bootstrap::init();
