<?php
namespace JelloPoint\RestaurantMenu\Debug;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only inspector to visualize labels/options and a selected menu item.
 * Appears under Tools → JPRM Inspector.
 */
class Inspector {

	public static function init() : void {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ __CLASS__, 'register_tools_page' ] );
		}
	}

	public static function register_tools_page() : void {
		add_management_page(
			__( 'JPRM Inspector', 'jellopoint-restaurant-menu' ),
			__( 'JPRM Inspector', 'jellopoint-restaurant-menu' ),
			'manage_options',
			'jprm-inspector',
			[ __CLASS__, 'render_page' ]
		);
	}

	protected static function get_label_map() : array {
		$opt = get_option( 'jprm_price_labels_v2' );
		$list = is_string( $opt ) ? json_decode( $opt, true ) : ( is_array( $opt ) ? $opt : [] );
		$map = [];
		if ( is_array( $list ) ) {
			foreach ( $list as $row ) {
				$id   = isset( $row['id'] ) ? (string) $row['id'] : '';
				$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
				$lab  = isset( $row['label'] ) ? (string) $row['label'] : '';
				$ico  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
				if ( $id !== '' )   { $map[ $id ]   = [ 'text' => $lab, 'icon_id' => $ico ]; }
				if ( $slug !== '' ) { $map[ $slug ] = [ 'text' => $lab, 'icon_id' => $ico ]; }
			}
		}
		return $map;
	}

	protected static function resolve_label( string $ref, array $map, int $icon_override = 0 ) : array {
		$ref = trim( $ref );
		if ( $ref === '' ) {
			return [ 'text' => '', 'icon_id' => 0, 'source' => 'empty' ];
		}
		if ( isset( $map[ $ref ] ) ) {
			return [ 'text' => (string) $map[ $ref ]['text'], 'icon_id' => (int) $map[ $ref ]['icon_id'], 'source' => 'registry' ];
		}
		return [ 'text' => $ref, 'icon_id' => (int) $icon_override, 'source' => 'custom' ];
	}

	public static function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$selected = isset( $_GET['jprm_item'] ) ? (int) $_GET['jprm_item'] : 0;
		$label_map = self::get_label_map();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'JPRM Inspector', 'jellopoint-restaurant-menu' ) . '</h1>';

		// Label option block
		echo '<h2>' . esc_html__( 'Labels Option (jprm_price_labels_v2)', 'jellopoint-restaurant-menu' ) . '</h2>';
		echo '<p><em>' . esc_html__( 'Normalized key → { text, icon_id }', 'jellopoint-restaurant-menu' ) . '</em></p>';
		if ( empty( $label_map ) ) {
			echo '<p>' . esc_html__( 'No labels configured.', 'jellopoint-restaurant-menu' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Text</th><th>Icon ID</th></tr></thead><tbody>';
			foreach ( $label_map as $k => $row ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%d</td></tr>',
					esc_html( (string) $k ),
					esc_html( (string) $row['text'] ),
					(int) $row['icon_id']
				);
			}
			echo '</tbody></table>';
		}

		// Item picker
		echo '<h2 style="margin-top:2em;">' . esc_html__( 'Inspect a Menu Item', 'jellopoint-restaurant-menu' ) . '</h2>';

		$items = get_posts( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="jprm-inspector" />';
		echo '<select name="jprm_item">';
		echo '<option value="0">' . esc_html__( '— Select an item —', 'jellopoint-restaurant-menu' ) . '</option>';
		foreach ( $items as $pid ) {
			$title = get_the_title( $pid );
			printf( '<option value="%d"%s>%s (#%d)</option>',
				(int) $pid,
				selected( $selected, $pid, false ),
				esc_html( $title ),
				(int) $pid
			);
		}
		echo '</select> ';
		submit_button( __( 'Inspect', 'jellopoint-restaurant-menu' ), 'secondary', '', false );
		echo '</form>';

		if ( $selected ) {
			self::render_item_block( $selected, $label_map );
		}

		echo '</div>';
	}

	protected static function render_item_block( int $post_id, array $label_map ) : void {
		$title = get_the_title( $post_id );
		$desc  = get_post_meta( $post_id, 'jprm_desc', true );
		$json  = get_post_meta( $post_id, 'jprm_price', true );
		$data  = is_string( $json ) ? json_decode( $json, true ) : null;

		echo '<hr/>';
		printf( '<h3>%s</h3>', esc_html( sprintf( __( 'Item: %s (#%d)', 'jellopoint-restaurant-menu' ), $title, $post_id ) ) );

		// Taxonomies
		$menus    = wp_get_post_terms( $post_id, 'jprm_menu',    [ 'fields' => 'names' ] );
		$sections = wp_get_post_terms( $post_id, 'jprm_section', [ 'fields' => 'names' ] );

		echo '<p><strong>' . esc_html__( 'Menus:', 'jellopoint-restaurant-menu' ) . '</strong> ' . esc_html( implode( ', ', (array) $menus ) ?: '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Sections:', 'jellopoint-restaurant-menu' ) . '</strong> ' . esc_html( implode( ', ', (array) $sections ) ?: '—' ) . '</p>';

		// Meta
		echo '<h4>' . esc_html__( 'Meta', 'jellopoint-restaurant-menu' ) . '</h4>';
		echo '<table class="widefat striped"><tbody>';
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'jprm_desc', 'jellopoint-restaurant-menu' ), esc_html( (string) $desc ) );
		printf( '<tr><th>%s</th><td><code>%s</code></td></tr>', esc_html__( 'jprm_price (raw JSON)', 'jellopoint-restaurant-menu' ), esc_html( (string) $json ) );
		echo '</tbody></table>';

		// Resolved rows
		echo '<h4 style="margin-top:1em;">' . esc_html__( 'Resolved Prices & Labels', 'jellopoint-restaurant-menu' ) . '</h4>';

		if ( ! is_array( $data ) ) {
			echo '<p>' . esc_html__( 'Invalid or empty JSON.', 'jellopoint-restaurant-menu' ) . '</p>';
			return;
		}

		$mode = isset( $data['mode'] ) ? (string) $data['mode'] : 'single';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Type', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '<th>' . esc_html__( 'Label Ref', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '<th>' . esc_html__( 'Resolved Text', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '<th>' . esc_html__( 'Icon ID', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'jellopoint-restaurant-menu' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( $mode === 'single' ) {
			$ref = (string) ( $data['label_ref'] ?? '' );
			$val = (string) ( $data['price'] ?? '' );
			$ico = (int)    ( $data['icon_id'] ?? 0 );
			$r   = self::resolve_label( $ref, $label_map, $ico );

			printf( '<tr><td>single</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
				esc_html( $val ),
				esc_html( $ref ),
				esc_html( $r['text'] ),
				(int) $r['icon_id'],
				esc_html( $r['source'] )
			);
		} else {
			$rows = is_array( $data['rows'] ?? null ) ? $data['rows'] : [];
			foreach ( $rows as $row ) {
				$ref = (string) ( $row['label_ref'] ?? '' );
				$val = (string) ( $row['value'] ?? '' );
				$ico = (int)    ( $row['icon_id'] ?? 0 );
				$r   = self::resolve_label( $ref, $label_map, $ico );

				printf( '<tr><td>row</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
					esc_html( $val ),
					esc_html( $ref ),
					esc_html( $r['text'] ),
					(int) $r['icon_id'],
					esc_html( $r['source'] )
				);
			}
		}

		echo '</tbody></table>';
	}
}

\JelloPoint\RestaurantMenu\Debug\Inspector::init();
