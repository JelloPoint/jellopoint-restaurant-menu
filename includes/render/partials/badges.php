<?php
/**
 * Badges partial – robust renderer + map builder
 * - Tolerates multiple option keys for where Admin saves badge rows
 * - Tolerates multiple post meta keys + formats (array or comma-separated)
 * - Renders only "active" badges
 *
 * Exposes:
 *  - jprm_build_badge_map() : array
 *  - jprm_render_badges_html( $post_id, $presentation='icon_text', $position='before', $map=null ) : string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return the option keys we will probe (first one that yields rows wins).
 * You can filter this if your store uses a custom key.
 */
function jprm_badges_option_keys() : array {
	$keys = [
		'jprm_dietary_badges',   // common choice
		'jprm_dietary_badges_rows',
		'jprm_badges',
		'jprm_badges_rows',
	];
	/**
	 * Filter the list of option keys we probe for badge rows.
	 * Return an ordered array of strings (option names).
	 */
	return apply_filters( 'jprm_badges_option_keys', $keys );
}

/**
 * Normalize one "row" (as stored by admin screen) into a consistent shape.
 */
function jprm_normalize_badge_row( $row, int $order_hint = 0 ) : ?array {
	if ( ! is_array( $row ) ) return null;

	$name     = isset( $row['name'] ) ? (string) $row['name'] : '';
	$slug     = sanitize_title( $name );
	if ( $slug === '' ) return null;

	$icon_id  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
	$icon_url = isset( $row['icon_url'] ) ? (string) $row['icon_url'] : '';
	$active   = ! empty( $row['active'] );
	$order    = isset( $row['order'] ) ? intval( $row['order'] ) : $order_hint;

	// If URL empty but we have an ID, try to resolve.
	if ( $icon_url === '' && $icon_id > 0 ) {
		$maybe = wp_get_attachment_image_url( $icon_id, 'thumbnail' );
		if ( is_string( $maybe ) ) $icon_url = $maybe;
	}

	return [
		'slug'     => $slug,
		'name'     => $name !== '' ? $name : $slug,
		'icon_id'  => $icon_id,
		'icon_url' => $icon_url,
		'active'   => $active,
		'order'    => $order,
	];
}

/**
 * Build a map of active badges keyed by slug.
 * Structure: [ slug => ['name'=>..., 'icon_id'=>..., 'icon_url'=>..., 'order'=>int] ]
 */
function jprm_build_badge_map() : array {
	static $cached = null;
	if ( is_array( $cached ) ) return $cached;

	$rows = null; $used_key = null;
	foreach ( jprm_badges_option_keys() as $opt ) {
		$val = get_option( $opt );
		if ( is_array( $val ) && ! empty( $val ) ) {
			$rows = $val;
			$used_key = $opt;
			break;
		}
	}

	$map = [];
	if ( is_array( $rows ) ) {
		$idx = 0;
		foreach ( $rows as $row ) {
			$norm = jprm_normalize_badge_row( $row, $idx++ );
			if ( ! $norm || empty( $norm['active'] ) ) continue;
			$map[ $norm['slug'] ] = [
				'name'     => $norm['name'],
				'icon_id'  => $norm['icon_id'],
				'icon_url' => $norm['icon_url'],
				'order'    => $norm['order'],
			];
		}
	}

	// Stable order by 'order'
	if ( ! empty( $map ) ) {
		uasort( $map, function( $a, $b ) {
			$ao = intval( $a['order'] ?? 0 );
			$bo = intval( $b['order'] ?? 0 );
			return $ao <=> $bo;
		} );
	}

	// (Optional) Help your Inspector see what we used.
	if ( ! has_filter( 'jprm_badges_map_debug' ) ) {
		/**
		 * Filter for debugging in your Inspector.
		 * Return ['key' => <option_key_used_or_null>, 'count' => <int>]
		 */
		add_filter( 'jprm_badges_map_debug', function( $payload ) use ( $used_key, $map ) {
			return [ 'key' => $used_key, 'count' => count( $map ) ];
		} );
	}

	return $cached = $map;
}

/**
 * Read the attached badge slugs for a post.
 * Accepts several meta keys + formats (array | comma-separated string).
 */
function jprm_get_item_badge_slugs( int $post_id ) : array {
	$candidate_meta = apply_filters( 'jprm_badges_meta_keys', [
		'jprm_dietary_badges',
		'jprm_badges',
		'dietary_badges',
	] );

	$slugs = [];
	foreach ( $candidate_meta as $key ) {
		$val = get_post_meta( $post_id, $key, true );
		if ( empty( $val ) ) continue;

		if ( is_array( $val ) ) {
			foreach ( $val as $v ) {
				$v = is_string( $v ) ? trim( $v ) : '';
				if ( $v !== '' ) $slugs[] = sanitize_title( $v );
			}
		} elseif ( is_string( $val ) ) {
			$bits = array_map( 'trim', explode( ',', $val ) );
			foreach ( $bits as $v ) {
				if ( $v !== '' ) $slugs[] = sanitize_title( $v );
			}
		}
	}

	$slugs = array_values( array_unique( array_filter( $slugs ) ) );
	return $slugs;
}

/**
 * Render badges HTML for a post.
 *
 * @param int         $post_id
 * @param string      $presentation 'icon' | 'text' | 'icon_text'
 * @param string      $position     'before' | 'after' (not used here but kept for API symmetry)
 * @param array|null  $map          Optional pre-built map from jprm_build_badge_map()
 * @return string     HTML (or empty string)
 */
function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $map = null ) : string {
	$map = is_array( $map ) ? $map : jprm_build_badge_map();
	if ( empty( $map ) ) return '';

	$slugs = jprm_get_item_badge_slugs( $post_id );
	if ( empty( $slugs ) ) return '';

	$out = '';
	$out .= '<span class="jp-badges jp-badges--' . esc_attr( $presentation ) . ' jp-badges--' . esc_attr( $position ) . '">';

	foreach ( $slugs as $slug ) {
		if ( ! isset( $map[ $slug ] ) ) continue;
		$badge = $map[ $slug ];
		$name  = $badge['name'] ?? $slug;
		$icon  = $badge['icon_url'] ?? '';

		$out .= '<span class="jp-badge jp-badge--' . esc_attr( $slug ) . '">';

		if ( $presentation === 'icon' || $presentation === 'icon_text' ) {
			if ( $icon ) {
				$out .= '<img class="jp-badge__icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
			} else {
				// Graceful fallback if no icon set
				$out .= '<span class="jp-badge__icon jp-badge__icon--empty" aria-hidden="true"></span>';
			}
		}
		if ( $presentation === 'text' || $presentation === 'icon_text' ) {
			$out .= '<span class="jp-badge__text">' . esc_html( $name ) . '</span>';
		}

		$out .= '</span>'; // .jp-badge
	}

	$out .= '</span>'; // .jp-badges
	return $out;
}
