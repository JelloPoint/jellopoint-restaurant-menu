<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Build a badge map from the option you actually use (jprm_dietary_badges_v1).
 * Map format: slug => ['name'=>..., 'icon_id'=>..., 'icon_url'=>..., 'active'=>true]
 */
if ( ! function_exists('jprm_build_badge_map') ) {
	function jprm_build_badge_map() : array {
		// Hard-depend on the Store class if present, but also guard if not loaded.
		$rows = [];
		if ( class_exists('\JelloPoint\RestaurantMenu\Badges\Store') ) {
			$rows = \JelloPoint\RestaurantMenu\Badges\Store::instance()->get_rows();
		} else {
			// Fallback read (just in case): same key as DB
			$rows = get_option( 'jprm_dietary_badges_v1', [] );
			if ( ! is_array( $rows ) ) $rows = [];
		}

		$map = [];
		foreach ( $rows as $r ) {
			$name = isset($r['name']) ? trim((string)$r['name']) : '';
			$active = ! empty($r['active']);
			if ( $name === '' || ! $active ) continue;

			$slug = sanitize_title( $name ); // e.g. "Gluten-Free" -> "gluten-free"
			$map[$slug] = [
				'name'     => $name,
				'icon_id'  => (int)($r['icon_id'] ?? 0),
				'icon_url' => (string)($r['icon_url'] ?? ''),
				'active'   => true,
			];
		}
		return $map;
	}
}

/**
 * Read badges attached to a post.
 * Supports multiple meta keys + formats:
 *  - 'jprm_dietary_badges' (preferred) | 'jprm_badges' | 'dietary_badges'
 *  - array of slugs OR comma/space separated string.
 */
if ( ! function_exists('jprm_get_post_badge_slugs') ) {
	function jprm_get_post_badge_slugs( int $post_id ) : array {
		$keys = [ 'jprm_dietary_badges', 'jprm_badges', 'dietary_badges' ];
		$slugs = [];
		foreach ( $keys as $k ) {
			$v = get_post_meta( $post_id, $k, true );
			if ( empty($v) ) continue;

			if ( is_array( $v ) ) {
				$slugs = array_merge( $slugs, $v );
			} else {
				// accept CSV or space-separated
				$parts = preg_split( '/[\s,]+/', (string)$v, -1, PREG_SPLIT_NO_EMPTY );
				$slugs = array_merge( $slugs, $parts );
			}
		}
		// normalize → unique, sanitized
		$slugs = array_map( fn($s) => sanitize_title( $s ), $slugs );
		$slugs = array_values( array_unique( array_filter( $slugs ) ) );
		return $slugs;
	}
}

/**
 * Render badges HTML next to a title.
 * $presentation: 'text' | 'icon' | 'icon_text'
 * $position: 'before' | 'after' (class only, your widget decides where to echo)
 */
if ( ! function_exists('jprm_render_badges_html') ) {
	function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $map = null ) : string {
		if ( $map === null ) $map = jprm_build_badge_map();
		if ( empty( $map ) ) return '';

		$slugs = jprm_get_post_badge_slugs( $post_id );
		if ( empty( $slugs ) ) return '';

		$out = [];
		foreach ( $slugs as $slug ) {
			if ( ! isset( $map[$slug] ) ) continue;
			$meta = $map[$slug];
			$name = esc_html( $meta['name'] );
			$icon = '';
			// Prefer icon_url if present, else try attachment ID
			if ( ! empty( $meta['icon_url'] ) ) {
				$icon = '<img class="jp-badge__icon" src="' . esc_url( $meta['icon_url'] ) . '" alt="" loading="lazy" />';
			} elseif ( ! empty( $meta['icon_id'] ) ) {
				$url = wp_get_attachment_image_url( (int)$meta['icon_id'], 'thumbnail' );
				if ( $url ) {
					$icon = '<img class="jp-badge__icon" src="' . esc_url( $url ) . '" alt="" loading="lazy" />';
				}
			}

			if ( $presentation === 'icon' ) {
				$label = $icon !== '' ? $icon : '<span class="jp-badge__txt">' . $name . '</span>';
			} elseif ( $presentation === 'text' ) {
				$label = '<span class="jp-badge__txt">' . $name . '</span>';
			} else { // icon_text
				if ( $icon !== '' ) {
					$label = '<span class="jp-badge__inner">'.$icon.'<span class="jp-badge__txt">'.$name.'</span></span>';
				} else {
					$label = '<span class="jp-badge__txt">'.$name.'</span>';
				}
			}

			$out[] = '<span class="jp-badge jp-badge--' . esc_attr( $slug ) . '">' . $label . '</span>';
		}

		if ( empty( $out ) ) return '';
		return '<span class="jp-badges jp-badges--' . esc_attr($position) . '">' . implode( '', $out ) . '</span>';
	}
}
