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
 * - Single: {"mode":"single","price":"3","label_ref":"","hide_icon":false}
 * - Multi:  {"mode":"multi","rows":[{"label_ref":"pl-2","value":"5,50","hide_icon":false}, ...]}
 * Optional keys allowed: icon_id on root/row to override stored label icon.
 */
if ( ! function_exists( 'jprm_read_price_config' ) ) {
	function jprm_read_price_config( int $post_id ) : array {
		$json = get_post_meta( $post_id, 'jprm_price', true );
		if ( ! is_string( $json ) || $json === '' ) return [];
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) return [];

		$mode = (string) ( $data['mode'] ?? '' );
		if ( $mode !== 'single' && $mode !== 'multi' ) return [];

		if ( $mode === 'single' ) {
			return [
				'mode'      => 'single',
				'price'     => (string) ( $data['price'] ?? '' ),
				'label_ref' => (string) ( $data['label_ref'] ?? '' ),
				'hide_icon' => (bool)   ( $data['hide_icon'] ?? false ),
				'icon_id'   => (int)    ( $data['icon_id'] ?? 0 ),
			];
		}

		// multi
		$out = [ 'mode' => 'multi', 'rows' => [] ];
		if ( is_array( $data['rows'] ?? null ) ) {
			foreach ( $data['rows'] as $row ) {
				if ( ! is_array( $row ) ) continue;
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
 * Returns map for both text and icon_id for quick lookup by slug.
 */
if ( ! function_exists( 'jprm_build_label_map' ) ) {
	function jprm_build_label_map() : array {
		$opt  = get_option( 'jprm_price_labels_v2' );
		$list = is_string( $opt ) ? json_decode( $opt, true ) : ( is_array( $opt ) ? $opt : [] );
		$map  = [];
		if ( is_array( $list ) ) {
			foreach ( $list as $row ) {
				$slug = (string) ( $row['slug'] ?? '' );
				if ( $slug === '' ) continue;
				$map[ $slug ] = [
					'text'    => (string) ( $row['text'] ?? '' ),
					'icon_id' => (int)    ( $row['icon_id'] ?? 0 ),
				];
			}
		}
		return $map;
	}
}

/** Resolve a label ref + optional icon override to [text, icon_id] */
if ( ! function_exists( 'jprm_resolve_label_ref' ) ) {
	function jprm_resolve_label_ref( string $ref, array $map, int $icon_override = 0 ) : array {
		$ref = trim( $ref );
		if ( $ref === '' ) return [ 'text' => '', 'icon_id' => 0 ];
		$hit = $map[ $ref ] ?? [ 'text' => '', 'icon_id' => 0 ];
		if ( $icon_override > 0 ) $hit['icon_id'] = $icon_override;
		return $hit;
	}
}

/** Render label HTML based on presentation + hide_icon */
if ( ! function_exists( 'jprm_label_html' ) ) {
	function jprm_label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
		$label_text = (string) $label_text;
		$icon_id    = (int) $icon_id;
		$has_icon   = ! $hide_icon && $icon_id > 0;

		if ( $label_text === '' && ! $has_icon ) {
			return '';
		}

		$icon = '';
		if ( $has_icon ) {
			$src = wp_get_attachment_image_src( $icon_id, 'thumbnail' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$icon = '<img class="jp-menu__icon" src="' . esc_url( $src[0] ) . '" alt="" loading="lazy" />';
			}
		}

		if ( $presentation === 'plain' ) {
			if ( $icon ) {
				return '<span class="jp-menu__label">' . $icon . '<span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
			}
			return '<span class="jp-menu__label"><span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
		}

		// default: chip
		if ( $icon ) {
			return '<span class="jp-menu__label jp-chip">' . $icon . '<span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
		}
		return '<span class="jp-menu__label jp-chip"><span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
	}
}

if ( ! function_exists( 'jprm_format_amount' ) ) {
	/**
	 * Apply currency formatting based on widget opts.
	 * $opts = [show(bool), symbol(string), position('before'|'after'), spacing('none'|'thin'|'normal')]
	 */
	function jprm_format_amount( string $raw, array $opts ) : string {
		$raw      = trim( (string) $raw );
		$show     = (bool) ( $opts['show'] ?? true );
		$symbol   = (string) ( $opts['symbol'] ?? '' );
		$position = (string) ( $opts['position'] ?? 'before' );
		$spacing  = (string) ( $opts['spacing'] ?? 'thin' );
		if ( $symbol === '' ) { return $raw; }

		// Strip same symbol at boundaries to avoid double symbols or to hide when show=false
		$pattern_start = '/^\s*' . preg_quote( $symbol, '/' ) . '\s*/u';
		$pattern_end   = '/\s*'  . preg_quote( $symbol, '/' ) . '\s*$/u';
		$base = preg_replace( $pattern_start, '', $raw );
		$base = preg_replace( $pattern_end,   '', $base );
		$base = trim( $base );
		if ( ! $show ) { return $base; }

		$space = '';
		if ( $spacing === 'thin' ) {
			$space = "\u{2009}"; // thin space
		} elseif ( $spacing === 'normal' ) {
			$space = "\u{00A0}"; // no-break space
		}

		if ( $position === 'after' ) {
			return $base . $space . $symbol;
		}
		// default: before
		return $symbol . $space . $base;
	}
}

/**
 * Main renderer for price group (single/multi) with labels.
 * $presentation: 'chip'|'plain'
 * $position: 'left'|'right' (label position relative to price)
 * $label_map: optional prebuilt map to save calls
 * $currency_opts: array as described in jprm_format_amount()
 */
if ( ! function_exists( 'jprm_render_pricegroup_html' ) ) {
	function jprm_render_pricegroup_html( int $post_id, string $presentation, string $position, ?array $label_map = null, array $currency_opts = [] ) : string {
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
				echo '  <span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span>';
				echo '</div>';
			} else {
				echo '<div class="jp-menu__price">';
				echo '  <span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span>';
				echo '  <div class="jp-col-label">' . $label_html . '</div>';
				echo '</div>';
			}
		}

		// Multi price
		if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
			foreach ( $cfg['rows'] as $row ) {
				$val = (string) ( $row['value'] ?? '' );
				if ( $val === '' ) { continue; }
				$resolved   = jprm_resolve_label_ref( (string) ( $row['label_ref'] ?? '' ), $map, (int) ( $row['icon_id'] ?? 0 ) );
				$label_html = jprm_label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) ( $row['hide_icon'] ?? false ) );

				if ( $position === 'left' ) {
					echo '<div class="jp-menu__price">';
					echo '  <div class="jp-col-label">' . $label_html . '</div>';
					echo '  <span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span>';
					echo '</div>';
				} else {
					echo '<div class="jp-menu__price">';
					echo '  <span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span>';
					echo '  <div class="jp-col-label">' . $label_html . '</div>';
					echo '</div>';
				}
			}
		}

		echo '</div>'; // .jp-menu__pricegroup
		return (string) ob_get_clean();
	}
}
