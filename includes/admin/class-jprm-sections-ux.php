<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight UI tweaks for the jprm_section taxonomy:
 * - List page: "Add Section" button text
 * - Edit page: hide Slug, "Edit Section" heading, "Parent Section" label
 */
class Sections_UX {

	const TAX = 'jprm_section';

	public static function init() : void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets() : void {
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only current-screen selector.
		if ( self::TAX !== $taxonomy ) { return; }

		wp_enqueue_style( 'jprm-sections-ux', JPRM_PLUGIN_URL . 'assets/admin/sections-ux.css', [], JPRM_VERSION );
		wp_enqueue_script( 'jprm-sections-ux', JPRM_PLUGIN_URL . 'assets/admin/sections-ux.js', [], JPRM_VERSION, true );
		wp_localize_script( 'jprm-sections-ux', 'jprmSectionsUx', [
			'addSection'    => __( 'Add Section', 'jellopoint-restaurant-menu' ),
			'category'      => __( 'Category', 'jellopoint-restaurant-menu' ),
			'section'       => __( 'Section', 'jellopoint-restaurant-menu' ),
			'parentSection' => __( 'Parent Section', 'jellopoint-restaurant-menu' ),
		] );
	}
}
