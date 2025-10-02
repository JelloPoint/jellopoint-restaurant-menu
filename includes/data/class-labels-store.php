<?php
/**
 * Labels Store + submenu registration via central Admin_Menu registrar
 */
if ( ! defined('ABSPATH') ) exit;

use JelloPoint\RestaurantMenu\Admin\Admin_Menu;

class JPRM_Labels_Store {

	protected static $cache = null;

	public static function all() : array {
		if ( is_array(self::$cache) ) return self::$cache;

		$opt = get_option('jprm_price_labels_v2', []);
		if ( is_string($opt) ) {
			$tmp = json_decode($opt, true);
			if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $opt = $tmp;
		}
		self::$cache = is_array($opt) ? $opt : [];
		return self::$cache;
	}

	public static function map_by_id() : array {
		$out = [];
		foreach ( self::all() as $row ) {
			if ( ! is_array($row) ) continue;
			$id = isset($row['id']) ? (string)$row['id'] : '';
			if ( $id === '' ) continue;
			$out[$id] = $row;
		}
		return $out;
	}

	public static function resolve( $ref ) : array {
		$ref = is_scalar($ref) ? (string)$ref : '';
		if ( $ref === '' ) return [ 'label_text' => '', 'icon_id' => 0 ];

		$map = self::map_by_id();
		if ( isset($map[$ref]) ) {
			$row = $map[$ref];
			return [
				'label_text' => isset($row['label']) ? (string)$row['label'] : '',
				'icon_id'    => isset($row['icon_id']) ? (int)$row['icon_id'] : 0,
			];
		}
		return [ 'label_text' => $ref, 'icon_id' => 0 ];
	}

	/* ---------------- Admin Menu (via central registrar) ---------------- */

	public static function boot_admin_menu() : void {
		// Enqueue our submenu; Admin_Menu will attach it under the parent when ready.
		add_action( 'admin_menu', [ __CLASS__, 'queue_submenu' ], 5 );
	}

	public static function queue_submenu() : void {
		if ( ! class_exists( Admin_Menu::class ) ) return;

		Admin_Menu::register_submenu( [
			'page_title' => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
			'menu_title' => __( 'Price Labels', 'jellopoint-restaurant-menu' ),
			'capability' => 'manage_options',
			'menu_slug'  => 'jprm-price-labels',
			'callback'   => [ __CLASS__, 'render_labels_admin_page' ],
			'position'   => 10,
		] );
	}

	public static function render_labels_admin_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Not allowed.', 'jellopoint-restaurant-menu' ) ); }
		$labels = self::all();

		echo '<div class="wrap"><h1>' . esc_html__( 'Price Labels', 'jellopoint-restaurant-menu' ) . '</h1>';
		if ( empty( $labels ) ) {
			echo '<p>' . esc_html__( 'No labels found. Add labels via your Labels settings UI.', 'jellopoint-restaurant-menu' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'ID', 'jellopoint-restaurant-menu' ) . '</th>';
			echo '<th>' . esc_html__( 'Label', 'jellopoint-restaurant-menu' ) . '</th>';
			echo '<th>' . esc_html__( 'Icon', 'jellopoint-restaurant-menu' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $labels as $row ) {
				$id   = isset($row['id']) ? (string)$row['id'] : '';
				$lab  = isset($row['label']) ? (string)$row['label'] : '';
				$icon = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;

				echo '<tr>';
				echo '<td>' . esc_html( $id ) . '</td>';
				echo '<td>' . esc_html( $lab ) . '</td>';
				echo '<td>';
				if ( $icon ) {
					echo wp_get_attachment_image( $icon, [24,24], false, [ 'style'=>'width:24px;height:24px;border-radius:3px;vertical-align:middle' ] );
				} else {
					echo '&mdash;';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}

// Boot submenu registration.
JPRM_Labels_Store::boot_admin_menu();
