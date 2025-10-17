<?php
/**
 * Price + Labels rendering partial for JelloPoint Restaurant Menu.
 *
 * Responsibilities:
 * - Read jprm_price JSON from post meta
 * - Resolve labels/icons from the global labels option (jprm_price_labels_v2)
 * - Output the exact markup the widget expects (jp-menu__pricegroup, rows, etc.)
 *
 * Functions are wrapped with function_exists guards so multiple includes are safe.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read price config JSON (meta: jprm_price). Supports:
 *  Single: {"mode":"single","price":"3","label_ref":"","hide_icon":false,"icon_id":0}
 *  Multi:  {"mode":"multi","rows":[{"label_ref":"pl-2","value":"5,50","hide_icon":false,"icon_id":0}, ...]}
 */
if ( ! function_exists( 'jprm_read_price_config' ) ) {
	function jprm_read_price_config( int $post_id ) : array {
		$json = get_post_meta( $post_id, 'jprm_price', true );
		if ( ! is_string( $json ) || $json === '' ) return [];

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) return [];

		$mode = ( isset( $data['mode'] ) && in_array( $data['mode'], [ 'single', 'multi' ], true ) ) ? $data['mode'] : 'single';

		$out = [ 'mode' => $mode, 'price' => '', 'rows' => [], 'label_ref' => '', 'hide_icon' => false, 'icon_id' => 0 ];

		if ( $mode === 'single' ) {
			$out['price']     = (string) ( $data['price'] ?? '' );
			$out['label_ref'] = (string) ( $data['label_ref'] ?? '' );
			$out['hide_icon'] = (bool)   ( $data['hide_icon'] ?? false );
			$out['icon_id']   = (int)    ( $data['icon_id'] ?? 0 );
		} else {
			$rows = is_array( $data['rows'] ?? null ) ? $data['rows'] : [];
			foreach ( $rows as $row ) {
				$out['rows'][] = [
					'value'     => (string) ( $row['value'] ?? '' ),
					'label_ref' => (string) ( $row['label_ref'] ?? '' ),
					'hide_icon' => (bool)   ( $row['hide_icon'] ?? false ),
					'icon_id'   => (int)    ( $row['icon_id'] ?? 0 ),
				];
			}
		}
		return $out;
	}
}

/**
 * Build label map from option `jprm_price_labels_v2`.
 * Returns map for both id & slug keys:
 *   [ 'id-or-slug' => ['text' => 'Glass', 'icon_id' => 123], ... ]
 */
if ( ! function_exists( 'jprm_build_label_map' ) ) {
	function jprm_build_label_map() : array {
		$opt  = get_option( 'jprm_price_labels_v2' );
		$list = is_string( $opt ) ? json_decode( $opt, true ) : ( is_array( $opt ) ? $opt : [] );
		$map  = [];
		if ( is_array( $list ) ) {
			foreach ( $list as $row ) {
				$id   = isset( $row['id'] )     ? (string) $row['id']     : '';
				$slug = isset( $row['slug'] )   ? (string) $row['slug']   : '';
				$lab  = isset( $row['label'] )  ? (string) $row['label']  : '';
				$ico  = isset( $row['icon_id'] )? (int)    $row['icon_id']: 0;
				if ( $id   !== '' ) $map[ $id ]   = [ 'text' => $lab, 'icon_id' => $ico ];
				if ( $slug !== '' ) $map[ $slug ] = [ 'text' => $lab, 'icon_id' => $ico ];
			}
		}
		return $map;
	}
}

/** Resolve a label reference using the map; fallback to custom text + optional icon override. */
if ( ! function_exists( 'jprm_resolve_label_ref' ) ) {
	function jprm_resolve_label_ref( string $ref, array $map, int $icon_override = 0 ) : array {
		$ref = trim( $ref );
		if ( $ref === '' ) {
			return [ 'text' => '', 'icon_id' => $icon_override ];
		}
		if ( isset( $map[ $ref ] ) ) {
			return [ 'text' => (string) $map[ $ref ]['text'], 'icon_id' => (int) $map[ $ref ]['icon_id'] ];
		}
		return [ 'text' => $ref, 'icon_id' => $icon_override ];
	}
}

/** Create a single label HTML fragment based on presentation + icon state. */
if ( ! function_exists( 'jprm_label_html' ) ) {
	function jprm_label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
		$label_text = (string) $label_text;
		$icon_html  = '';
		if ( ! $hide_icon && $icon_id > 0 ) {
			$img = wp_get_attachment_image( $icon_id, [24, 24], false, [ 'class' => 'jp-menu__icon' ] );
			if ( is_string( $img ) ) {
				$icon_html = $img;
			}
		}
		if ( $presentation === 'icon' )      return $icon_html;
		if ( $presentation === 'text' )      return esc_html( $label_text );
		if ( $presentation === 'icon_text' ) return $icon_html ? ( $icon_html . ' ' . esc_html( $label_text ) ) : esc_html( $label_text );
		return esc_html( $label_text );
	}
}

/**
 * Render the entire price group for one item (returns HTML string).
 * $position: 'left'|'right' (label position)
 * $presentation: 'text'|'icon'|'icon_text'
 */
if ( ! function_exists( 'jprm_render_pricegroup_html' ) ) {
	function jprm_render_pricegroup_html( int $post_id, string $presentation, string $position, ?array $label_map = null ) : string {
		$cfg = jprm_read_price_config( $post_id );
		if ( empty( $cfg ) ) {
			return '<div class="jp-menu__pricegroup"></div>';
		}
		$map = is_array( $label_map ) ? $label_map : jprm_build_label_map();

		ob_start();
		echo '<div class="jp-menu__pricegroup">';

		// Single price
		if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
			$resolved   = jprm_resolve_label_ref( (string) $cfg['label_ref'], $map, (int) ( $cfg['icon_id'] ?? 0 ) );
			$label_html = jprm_label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) $cfg['hide_icon'] );

			if ( $position === 'left' ) {
				echo '<div class="jp-menu__price">';
				echo '  <div class="jp-col-label">' . $label_html . '</div>';
				echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
				echo '</div>';
			} else {
				echo '<div class="jp-menu__price">';
				echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $cfg['price'] ) . '</span>';
				echo '  <div class="jp-col-label">' . $label_html . '</div>';
				echo '</div>';
			}
		}

		// Multi prices
		if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
			foreach ( $cfg['rows'] as $row ) {
				$val = (string) ( $row['value'] ?? '' );
				if ( $val === '' ) { continue; }
				$resolved   = jprm_resolve_label_ref( (string) ( $row['label_ref'] ?? '' ), $map, (int) ( $row['icon_id'] ?? 0 ) );
				$label_html = jprm_label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) ( $row['hide_icon'] ?? false ) );

				if ( $position === 'left' ) {
					echo '<div class="jp-menu__price">';
					echo '  <div class="jp-col-label">' . $label_html . '</div>';
					echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $val ) . '</span>';
					echo '</div>';
				} else {
					echo '<div class="jp-menu__price">';
					echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $val ) . '</span>';
					echo '  <div class="jp-col-label">' . $label_html . '</div>';
					echo '</div>';
				}
			}
		}

		echo '</div>'; // .jp-menu__pricegroup
		return (string) ob_get_clean();
	}
}
