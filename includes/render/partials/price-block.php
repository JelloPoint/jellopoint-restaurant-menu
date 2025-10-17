<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SAFE currency formatter:
 * - Defaults: show=true, symbol=€, position=before, spacing=thin
 * - No widget dependency; can be overridden later via filter:
 *   apply_filters( 'jprm_currency_opts', $defaults, $post_id )
 */
if ( ! function_exists( 'jprm_format_amount' ) ) {
	function jprm_format_amount( string $raw, array $opts ) : string {
		$raw      = trim( (string) $raw );
		$show     = (bool) ( $opts['show'] ?? true );
		$symbol   = (string) ( $opts['symbol'] ?? '€' );
		$position = (string) ( $opts['position'] ?? 'before' );
		$spacing  = (string) ( $opts['spacing'] ?? 'thin' );

		if ( $symbol === '' ) { return $raw; }

		// Strip same symbol at start/end to avoid duplicates (or to hide when show=false)
		$pattern_start = '/^\s*' . preg_quote( $symbol, '/' ) . '\s*/u';
		$pattern_end   = '/\s*'  . preg_quote( $symbol, '/' ) . '\s*$/u';
		$base = preg_replace( $pattern_start, '', $raw );
		$base = preg_replace( $pattern_end,   '', $base );
		$base = trim( $base );

		if ( ! $show ) { return $base; }

		$space = '';
		if ( $spacing === 'thin' )   { $space = "\u{2009}"; }   // thin space
		elseif ( $spacing === 'normal' ) { $space = "\u{00A0}"; } // no-break space

		return ( $position === 'after' )
			? $base . $space . $symbol
			: $symbol . $space . $base; // default BEFORE
	}
}

/**
 * Read price config JSON from post meta 'jprm_price'.
 * (Same shapes you specified: single/multi)
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

/** Build label map from option `jprm_price_labels_v2` */
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

/** Render label HTML; keeps your existing semantics (icon can be hidden by per-row flag) */
if ( ! function_exists( 'jprm_label_html' ) ) {
	function jprm_label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
		$label_text = (string) $label_text;
		$icon_id    = (int) $icon_id;
		$has_icon   = ! $hide_icon && $icon_id > 0;

		if ( $label_text === '' && ! $has_icon ) return '';

		$icon = '';
		if ( $has_icon ) {
			$src = wp_get_attachment_image_src( $icon_id, 'thumbnail' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$icon = '<img class="jp-menu__icon" src="' . esc_url( $src[0] ) . '" alt="" loading="lazy" />';
			}
		}

		// Presentation values passed in from your existing widget (unchanged)
		if ( $presentation === 'plain' ) {
			return $icon
				? '<span class="jp-menu__label">' . $icon . '<span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>'
				: '<span class="jp-menu__label"><span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
		}
		// default "chip" if that’s what your current widget uses; otherwise this is harmless.
		return $icon
			? '<span class="jp-menu__label jp-chip">' . $icon . '<span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>'
			: '<span class="jp-menu__label jp-chip"><span class="jp-menu__labeltext">' . esc_html( $label_text ) . '</span></span>';
	}
}

/**
 * Main renderer for a post’s price group.
 * NOTE: Currency options default here, but can be overridden by a filter later.
 */
if ( ! function_exists( 'jprm_render_pricegroup_html' ) ) {
	function jprm_render_pricegroup_html( int $post_id, string $presentation = 'chip', string $label_position = 'right', ?array $label_map = null, array $currency_opts = [] ) : string {
		$cfg = jprm_read_price_config( $post_id );
		if ( empty( $cfg ) ) return '<div class="jp-menu__pricegroup"></div>';

		$map = is_array( $label_map ) ? $label_map : jprm_build_label_map();

		// Defaults: show, symbol €, BEFORE, THIN space (your request)
		$defaults = [
			'show'     => true,
			'symbol'   => '€',
			'position' => 'before',
			'spacing'  => 'thin',
		];
		$currency_opts = array_merge( $defaults, $currency_opts );
		$currency_opts = apply_filters( 'jprm_currency_opts', $currency_opts, $post_id );

		ob_start();
		echo '<div class="jp-menu__pricegroup">';

		if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
			$resolved   = jprm_resolve_label_ref( (string) $cfg['label_ref'], $map, (int) ( $cfg['icon_id'] ?? 0 ) );
			$label_html = jprm_label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) $cfg['hide_icon'] );

			if ( $label_position === 'left' ) {
				echo '<div class="jp-menu__price"><div class="jp-col-label">' . $label_html . '</div>';
				echo '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span></div>';
			} else {
				echo '<div class="jp-menu__price"><span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span>';
				echo '<div class="jp-col-label">' . $label_html . '</div></div>';
			}
		}

		if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
			foreach ( $cfg['rows'] as $row ) {
				$val = (string) ( $row['value'] ?? '' );
				if ( $val === '' ) continue;
				$resolved   = jprm_resolve_label_ref( (string) ( $row['label_ref'] ?? '' ), $map, (int) ( $row['icon_id'] ?? 0 ) );
				$label_html = jprm_label_html( $resolved['text'], (int) $resolved['icon_id'], $presentation, (bool) ( $row['hide_icon'] ?? false ) );

				if ( $label_position === 'left' ) {
					echo '<div class="jp-menu__price"><div class="jp-col-label">' . $label_html . '</div>';
					echo '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span></div>';
				} else {
					echo '<div class="jp-menu__price"><span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span>';
					echo '<div class="jp-col-label">' . $label_html . '</div></div>';
				}
			}
		}

		echo '</div>';
		return (string) ob_get_clean();
	}
}
