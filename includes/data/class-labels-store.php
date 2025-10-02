<?php
/**
 * Labels Store + submenu registration via central Admin_Menu registrar.
 * IMPORTANT: We do NOT replace your existing Labels admin UI.
 * We attempt to load/call it; fallback listing is only used if nothing is found.
 */
if ( ! defined('ABSPATH') ) exit;

use JelloPoint\RestaurantMenu\Admin\Admin_Menu;

class JPRM_Labels_Store {

	/** Cache of labels */
	protected static $cache = null;

	/** Read labels from options (jprm_price_labels_v2) */
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

	/** Map by id for quick resolution */
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

	/**
	 * Resolve a label reference to text & icon.
	 * - If $ref matches an ID in the store, use its label/icon_id.
	 * - Otherwise treat $ref as custom text.
	 */
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
		// custom text fallback
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
			'callback'   => [ __CLASS__, 'route_to_labels_admin_ui' ],
			'position'   => 10,
		] );
	}

	/**
	 * Route to the existing Labels admin UI if available.
	 * We try multiple common file paths and callbacks before falling back.
	 */
	public static function route_to_labels_admin_ui() : void {
		// 1) Try to include any known admin page class/files you already have.
		self::maybe_require_admin_ui_file();

		// 2) Known callbacks to try (adjust/add as needed).
		//    We support both functions and class methods.
		$callbacks = [
			'jprm_render_price_labels_admin',                       // function
			[ '\JelloPoint\RestaurantMenu\Admin\Labels_Page', 'render' ],  // class::method
			[ 'JPRM_Price_Labels_Admin', 'render' ],                // legacy class::method
		];

		foreach ( $callbacks as $cb ) {
			if ( is_callable( $cb ) ) {
				call_user_func( $cb );
				return;
			}
		}

		// 3) If no known UI exists, use a gentle fallback: read-only listing.
		self::render_fallback_labels_list();
	}

	/**
	 * Attempt to load your original admin UI file if it exists.
	 * We deliberately do NOT fatal if it’s missing.
	 */
	protected static function maybe_require_admin_ui_file() : void {
		$base = defined('JPRM_PLUGIN_PATH') ? JPRM_PLUGIN_PATH : plugin_dir_path( dirname( __FILE__, 2 ) );

		$candidates = [
			'includes/admin/pages/class-price-labels-admin.php',
			'includes/admin/pages/class-labels-admin.php',
			'includes/admin/class-price-labels-admin.php',
			'includes/admin/labels/class-price-labels-admin.php',
		];

		foreach ( $candidates as $rel ) {
			$file = trailingslashit( $base ) . $rel;
			if ( file_exists( $file ) ) {
				require_once $file;
				// Do not break; multiple files may be fine—callbacks will decide.
			}
		}
	}

	/** Read-only fallback so the page isn’t blank if your UI isn’t found. */
	protected static function render_fallback_labels_list() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jellopoint-restaurant-menu' ) );
		}
		$labels = self::all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Price Labels', 'jellopoint-restaurant-menu' ) . '</h1>';
		echo '<p style="margin-top:8px;">' .
		     esc_html__( 'Your full Price Labels UI was not detected. Showing a read-only list below. If you have a custom admin page, we will automatically use it when available.', 'jellopoint-restaurant-menu' ) .
		     '</p>';

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
					echo wp_get_attachment_image( $icon, [24,24], false, [
						'style'=>'width:24px;height:24px;border-radius:3px;vertical-align:middle'
					] );
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

// Boot submenu registration (hooks only; UI is resolved dynamically).
JPRM_Labels_Store::boot_admin_menu();
