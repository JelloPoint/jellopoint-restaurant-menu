<?php
namespace JelloPoint\RestaurantMenu;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Remove plugin-owned data only after the administrator explicitly opts in. */
final class Uninstaller {
	/** Register cleanup behind the uninstall lifecycle managed by Freemius. */
	public static function register( $freemius ) : void {
		if ( is_object( $freemius ) && method_exists( $freemius, 'add_action' ) ) {
			$freemius->add_action( 'after_uninstall', [ self::class, 'after_uninstall' ] );
		}
	}

	/** Apply the opt-in cleanup to every site affected by the uninstall. */
	public static function after_uninstall() : void {
		if ( is_multisite() ) {
			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::site_data();
				restore_current_blog();
			}
			return;
		}

		self::site_data();
	}

	/** Delete all plugin-owned data for the current site. */
	public static function site_data() : void {
		if ( '1' !== (string) get_option( 'jprm_delete_data_on_uninstall', '0' ) ) {
			return;
		}

		$post_ids = get_posts(
			[
				'post_type'      => [ 'jprm_menu_item', 'jprm_info_block' ],
				'post_status'    => get_post_stati( [], 'names' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		foreach ( [ 'jprm_menu', 'jprm_section' ] as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, [ 'jprm_menu_item' ] );
			}

			$term_ids = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				]
			);

			if ( is_wp_error( $term_ids ) ) {
				continue;
			}

			foreach ( $term_ids as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}

		$options = [
			'jprm_delete_data_on_uninstall',
			'jprm_dietary_badges',
			'jprm_dietary_badges_v1',
			'jprm_price_labels_v2',
			'jprm_print_document_settings',
			'jprm_demo_menu_v1',
			'jprm_site_uid',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		global $wpdb;
		$report_like = $wpdb->esc_like( '_transient_jprm_ie_report_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_jprm_ie_report_' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$report_like,
				$timeout_like
			)
		);

		flush_rewrite_rules();
	}
}
