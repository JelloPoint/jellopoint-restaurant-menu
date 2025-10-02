<?php
/**
 * Labels Store + Admin Menu registration
 */
if ( ! defined('ABSPATH') ) exit;

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

	/* ----------------------------------------------------------------------
	 * Admin Menu: ensure "Price Labels" appears under the JelloPoint menu.
	 * -------------------------------------------------------------------- */
	public static function boot_admin_menu() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_menus' ], 20 );
	}
	public static function register_admin_menus() : void {
		$parent = apply_filters( 'jprm/admin_parent_slug', 'jellopoint' ); // expected top-level slug

		// If parent menu is missing, create it (safely).
		global $admin_page_hooks;
		if ( empty( $admin_page_hooks[ $parent ] ) ) {
			add_menu_page(
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
				__( 'JelloPoint Menu', 'jellopoint-restaurant-menu' ),
				'manage_options',
				$parent,
				'__return_null',
				'dashicons-store',
				56
			);
		}

		// Add (or re-add) our submenu item.
		add_submenu_page(
			$parent,
			__( 'Price Labels', 'jellopoint-restaurant-menu' ),
			__( 'Price Labels', 'jellopoint-restaurant-menu' ),
			'manage_options',
			'jprm-price-labels',
			[ __CLASS__, 'render_labels_admin_page' ],
			10
		);
	}

	/** Simple admin page: shows current labels; you can expand with edit UI as needed. */
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
// ensure admin menu registers
JPRM_Labels_Store::boot_admin_menu();
