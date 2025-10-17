<?php
/**
 * Badges partial for JPRM
 *
 * Provides:
 *  - jprm_build_badge_map(): array<string, array{ text:string, icon_id:int }>
 *  - jprm_render_badges_html( int $post_id, string $presentation='icon_text', string $position='before', ?array $badge_map=null ): string
 *
 * Data sources (in order of preference):
 *  1) \JelloPoint\RestaurantMenu\Badges\Store (if present)
 *  2) Option "jprm_badges_registry" (array or JSON string)
 *  3) Taxonomy "jprm_badge" with term metas: text: (jprm_badge_text|jprm_text), icon_id: (jprm_badge_icon_id|jprm_icon_id)
 *  4) Empty array
 *
 * Per-item selections (first non-empty wins):
 *  - post meta: jprm_badges (array | CSV string)
 *  - post meta: jprm_dietary_badges (array | CSV string)
 *  - post meta: dietary_badges (array | CSV string)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'jprm_normalize_badge_keys' ) ) {
	function jprm_normalize_badge_keys( $val ) : array {
		if ( empty( $val ) ) return [];
		if ( is_string( $val ) ) {
			// CSV -> array
			$val = array_filter( array_map( 'trim', explode( ',', $val ) ), static fn($s) => $s !== '' );
		}
		if ( is_array( $val ) ) {
			$keys = [];
			foreach ( $val as $k => $v ) {
				// allow associative array: ['pl-1' => true] or ['pl-1']
				$key = is_string( $k ) ? $k : ( is_string( $v ) ? $v : '' );
				if ( $key === '' ) continue;
				$keys[] = sanitize_key( $key );
			}
			return array_values( array_unique( $keys ) );
		}
		return [];
	}
}

if ( ! function_exists( 'jprm_get_item_badge_keys' ) ) {
	function jprm_get_item_badge_keys( int $post_id ) : array {
		$candidates = [
			get_post_meta( $post_id, 'jprm_badges', true ),
			get_post_meta( $post_id, 'jprm_dietary_badges', true ),
			get_post_meta( $post_id, 'dietary_badges', true ),
		];
		foreach ( $candidates as $raw ) {
			$keys = jprm_normalize_badge_keys( $raw );
			if ( ! empty( $keys ) ) return $keys;
		}
		return [];
	}
}

if ( ! function_exists( 'jprm_build_badge_map' ) ) {
	function jprm_build_badge_map() : array {
		// 1) Store class
		if ( class_exists( '\\JelloPoint\\RestaurantMenu\\Badges\\Store' ) ) {
			try {
				$cls = '\\JelloPoint\\RestaurantMenu\\Badges\\Store';
				// Probe common styles: ::get_registry(), ::get_map(), instance()->get_registry()
				if ( method_exists( $cls, 'get_registry' ) ) {
					$map = $cls::get_registry();
				} elseif ( method_exists( $cls, 'get_map' ) ) {
					$map = $cls::get_map();
				} else {
					$inst = method_exists( $cls, 'instance' ) ? $cls::instance() : ( method_exists( $cls, 'get_instance' ) ? $cls::get_instance() : null );
					if ( $inst && method_exists( $inst, 'get_registry' ) ) {
						$map = $inst->get_registry();
					} elseif ( $inst && method_exists( $inst, 'get_map' ) ) {
						$map = $inst->get_map();
					} else {
						$map = null;
					}
				}
				if ( is_array( $map ) ) {
					// Normalize
					$out = [];
					foreach ( $map as $key => $row ) {
						$k = sanitize_key( is_string( $key ) ? $key : ( $row['key'] ?? '' ) );
						if ( $k === '' ) continue;
						$text = isset( $row['text'] ) ? (string) $row['text'] : (string) ( $row['label'] ?? '' );
						$icon = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : (int) ( $row['icon'] ?? 0 );
						$out[ $k ] = [ 'text' => $text, 'icon_id' => $icon ];
					}
					return $out;
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[JPRM] Badges\\Store failed: ' . $e->getMessage() );
				}
			}
		}

		// 2) Option
		$opt = get_option( 'jprm_badges_registry' );
		if ( is_string( $opt ) ) {
			$maybe = json_decode( $opt, true );
			if ( is_array( $maybe ) ) $opt = $maybe;
		}
		if ( is_array( $opt ) ) {
			$out = [];
			foreach ( $opt as $key => $row ) {
				$k = sanitize_key( is_string( $key ) ? $key : ( $row['key'] ?? '' ) );
				if ( $k === '' ) continue;
				$text = isset( $row['text'] ) ? (string) $row['text'] : (string) ( $row['label'] ?? '' );
				$icon = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : (int) ( $row['icon'] ?? 0 );
				$out[ $k ] = [ 'text' => $text, 'icon_id' => $icon ];
			}
			return $out;
		}

		// 3) Taxonomy
		if ( taxonomy_exists( 'jprm_badge' ) ) {
			$terms = get_terms( [ 'taxonomy' => 'jprm_badge', 'hide_empty' => false ] );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				$out = [];
				foreach ( $terms as $t ) {
					$key  = sanitize_key( $t->slug ?: $t->term_id );
					$text = get_term_meta( $t->term_id, 'jprm_badge_text', true );
					if ( $text === '' ) $text = get_term_meta( $t->term_id, 'jprm_text', true );
					if ( $text === '' ) $text = $t->name;

					$icon = get_term_meta( $t->term_id, 'jprm_badge_icon_id', true );
					if ( $icon === '' ) $icon = get_term_meta( $t->term_id, 'jprm_icon_id', true );
					$icon = (int) $icon;

					$out[ $key ] = [ 'text' => (string) $text, 'icon_id' => $icon ];
				}
				return $out;
			}
		}

		// 4) Fallback
		return [];
	}
}

if ( ! function_exists( 'jprm_render_badges_html' ) ) {
	/**
	 * @param int         $post_id
	 * @param string      $presentation 'text'|'icon'|'icon_text'
	 * @param string      $position     'before'|'after'  (used for class only)
	 * @param array|null  $badge_map    prebuilt map (optional)
	 * @return string HTML
	 */
	function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $badge_map = null ) : string {
		$keys = jprm_get_item_badge_keys( $post_id );
		if ( empty( $keys ) ) return '';

		if ( $badge_map === null ) {
			$badge_map = jprm_build_badge_map();
		}
		if ( empty( $badge_map ) ) return '';

		$pieces = [];
		foreach ( $keys as $k ) {
			if ( empty( $badge_map[ $k ] ) ) continue;
			$row     = $badge_map[ $k ];
			$text    = isset( $row['text'] ) ? (string) $row['text'] : '';
			$icon_id = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;

			$parts = [];
			if ( $presentation === 'icon' || $presentation === 'icon_text' ) {
				if ( $icon_id > 0 ) {
					$img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [
						'class' => 'jp-badge__icon',
						'loading' => 'lazy',
						'decoding' => 'async',
						'alt' => $text !== '' ? $text : $k,
					] );
					if ( $img ) $parts[] = $img;
				}
			}
			if ( $presentation === 'text' || $presentation === 'icon_text' ) {
				if ( $text !== '' ) {
					$parts[] = '<span class="jp-badge__text">' . esc_html( $text ) . '</span>';
				}
			}
			if ( empty( $parts ) ) continue;

			$pieces[] = '<span class="jp-badge jp-badge--' . esc_attr( $k ) . '">' . implode( '', $parts ) . '</span>';
		}

		if ( empty( $pieces ) ) return '';

		return '<span class="jp-badges jp-badges--' . esc_attr( $position ) . '">' . implode( '', $pieces ) . '</span>';
	}
}
