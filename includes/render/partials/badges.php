<?php
// includes/render/partials/badges.php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend badges renderer for menu items.
 * - Looks for \JelloPoint\RestaurantMenu\Badges_Store and tries common getters.
 * - Emits compact markup ready to be styled in includes/render/css/menu.css
 */

if ( ! function_exists( 'jprm_get_badges_store' ) ) {
	function jprm_get_badges_store() {
		if ( class_exists( '\JelloPoint\RestaurantMenu\Badges_Store' ) ) {
			// Singleton or plain constructor – be defensive:
			if ( method_exists( '\JelloPoint\RestaurantMenu\Badges_Store', 'instance' ) ) {
				return \JelloPoint\RestaurantMenu\Badges_Store::instance();
			}
			if ( method_exists( '\JelloPoint\RestaurantMenu\Badges_Store', 'get_instance' ) ) {
				return \JelloPoint\RestaurantMenu\Badges_Store::get_instance();
			}
			try { return new \JelloPoint\RestaurantMenu\Badges_Store(); } catch ( \Throwable $e ) {}
		}
		return null;
	}
}

if ( ! function_exists( 'jprm_fetch_item_badges' ) ) {
	/**
	 * Return a normalized list of badges for a menu item.
	 * Each badge: [ 'text' => string, 'icon_id' => int|null ] – icon_id optional.
	 */
	function jprm_fetch_item_badges( int $post_id ) : array {
		$store = jprm_get_badges_store();
		if ( ! $store ) return [];

		$raw = [];
		// Try a few likely method names without guessing meta keys:
		foreach ( [ 'get_item_badges', 'get_assigned_for_item', 'assigned_for_post', 'get_assigned' ] as $m ) {
			if ( method_exists( $store, $m ) ) {
				try {
					$raw = $store->{$m}( $post_id );
					break;
				} catch ( \Throwable $e ) {}
			}
		}
		if ( empty( $raw ) ) return [];

		$out = [];
		foreach ( (array) $raw as $b ) {
			$text = '';
			$icon = null;

			// Normalize a few common shapes
			if ( is_array( $b ) ) {
				$text = (string) ( $b['text'] ?? $b['label'] ?? $b['name'] ?? '' );
				$icon = isset( $b['icon'] ) ? $b['icon'] : ( $b['icon_id'] ?? null );
			} elseif ( is_object( $b ) ) {
				$text = (string) ( $b->text ?? $b->label ?? $b->name ?? '' );
				$icon = $b->icon_id ?? ( $b->icon ?? null );
			} elseif ( is_string( $b ) ) {
				$text = $b;
			}

			$text = trim( $text );
			if ( $text === '' ) continue;

			$icon_id = is_numeric( $icon ) ? (int) $icon : null;
			$out[] = [ 'text' => $text, 'icon_id' => $icon_id ];
		}
		return $out;
	}
}

if ( ! function_exists( 'jprm_render_item_badges_html' ) ) {
	/**
	 * Render badges list.
	 * $position: 'before'|'after' – relative to the item title.
	 * $presentation: 'text'|'icon'|'icon_text' (reuse same control values as Labels).
	 */
	function jprm_render_item_badges_html( int $post_id, string $position, string $presentation ) : string {
		$badges = jprm_fetch_item_badges( $post_id );
		if ( empty( $badges ) ) return '';

		$pos_cls = ( $position === 'before' ) ? 'before-title' : 'after-title';
		$mode    = in_array( $presentation, [ 'text', 'icon', 'icon_text' ], true ) ? $presentation : 'icon_text';

		$html = '<span class="jp-badges jp-badges--' . esc_attr( $pos_cls ) . ' jp-badges--mode-' . esc_attr( $mode ) . '">';

		foreach ( $badges as $b ) {
			$parts = [];
			if ( $mode !== 'text' && ! empty( $b['icon_id'] ) ) {
				$img = wp_get_attachment_image( (int) $b['icon_id'], 'thumbnail', false, [ 'class' => 'jp-badge__icon' ] );
				if ( $img ) $parts[] = '<span class="jp-badge__iconwrap">'.$img.'</span>';
			}
			if ( $mode !== 'icon' ) {
				$parts[] = '<span class="jp-badge__text">' . esc_html( $b['text'] ) . '</span>';
			}
			if ( ! empty( $parts ) ) {
				$html .= '<span class="jp-badge">' . implode( '', $parts ) . '</span>';
			}
		}

		$html .= '</span>';
		return $html;
	}
}
