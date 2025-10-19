<?php
/**
 * Price + Labels rendering partial for JelloPoint Restaurant Menu.
 *
 * Pure presentational: reads 'jprm_price' JSON and renders rows.
 * Uses JPRM_Labels_Store::resolve() so labels/icons match your store.
 *
 * Defaults for currency (overridable via 'jprm_currency_opts' filter):
 *   show=true, symbol=€, position=before, spacing=thin
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ================= Currency ================= */

if ( ! function_exists( 'jprm_format_amount' ) ) {
	function jprm_format_amount( string $raw, array $opts ) : string {
		$raw      = trim( (string) $raw );
		$show     = (bool) ( $opts['show'] ?? true );
		$symbol   = (string) ( $opts['symbol'] ?? '€' );
		$position = (string) ( $opts['position'] ?? 'before' );
		$spacing  = (string) ( $opts['spacing'] ?? 'thin' );

		if ( $symbol === '' ) { return $raw; }

		// Avoid double symbols (and enable hide by stripping first)
		$pattern_start = '/^\s*' . preg_quote( $symbol, '/' ) . '\s*/u';
		$pattern_end   = '/\s*'  . preg_quote( $symbol, '/' ) . '\s*$/u';
		$base = preg_replace( $pattern_start, '', $raw );
		$base = preg_replace( $pattern_end,   '', $base );
		$base = trim( (string) $base );

		if ( ! $show ) { return $base; }

		$space = '';
		if ( $spacing === 'thin' )   { $space = "\u{2009}"; }   // thin space
		elseif ( $spacing === 'normal' ) { $space = "\u{00A0}"; } // nbsp

		return ( $position === 'after' )
			? $base . $space . $symbol
			: $symbol . $space . $base; // default BEFORE
	}
}

/* ================= Price config ================= */

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

/* ================= Labels ================= */

/**
 * Resolve a label reference using your store (preferred), with optional icon override.
 * Falls back to treating the ref as literal text if store is unavailable.
 */
if ( ! function_exists( 'jprm_resolve_label' ) ) {
	function jprm_resolve_label( string $ref, int $icon_override = 0 ) : array {
		$ref = trim( (string) $ref );

		$label_text = '';
		$icon_id    = 0;

		if ( $ref !== '' && class_exists( '\JPRM_Labels_Store' ) && method_exists( '\JPRM_Labels_Store', 'resolve' ) ) {
			$res        = \JPRM_Labels_Store::resolve( $ref );
			$label_text = (string) ( $res['label_text'] ?? '' );
			$icon_id    = (int)    ( $res['icon_id']    ?? 0 );
		} else {
			// Basic fallback: show the ref as text if no store available
			$label_text = $ref;
			$icon_id    = 0;
		}

		if ( $icon_override > 0 ) {
			$icon_id = $icon_override;
		}

		return [ 'label_text' => $label_text, 'icon_id' => $icon_id ];
	}
}

/** Render label HTML according to your classic modes: text | icon | icon_text */
if ( ! function_exists( 'jprm_label_html' ) ) {
	function jprm_label_html( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
		$label_text = (string) $label_text;
		$icon_id    = (int) $icon_id;

		$icon_html = '';
		if ( ! $hide_icon && $icon_id > 0 ) {
			$img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
			if ( is_string( $img ) ) $icon_html = $img;
		}

		if ( $presentation === 'icon' ) {
			return $icon_html;
		}
		if ( $presentation === 'text' ) {
			return esc_html( $label_text );
		}
		// icon_text (default if unknown)
		if ( $icon_html !== '' ) {
			return $icon_html . ' ' . esc_html( $label_text );
		}
		return esc_html( $label_text );
	}
}

/* ================= Main renderer ================= */

/**
 * @param int         $post_id
 * @param string      $presentation 'text' | 'icon' | 'icon_text'
 * @param string      $label_position 'left' | 'right'
 * @param ?array      $unused_label_map kept for backward compat (ignored)
 * @param array       $currency_opts see jprm_format_amount()
 */
if ( ! function_exists( 'jprm_render_pricegroup_html' ) ) {
	function jprm_render_pricegroup_html( int $post_id, string $presentation = 'text', string $label_position = 'right', ?array $unused_label_map = null, array $currency_opts = [] ) : string {
		$cfg = jprm_read_price_config( $post_id );
		if ( empty( $cfg ) ) return '<div class="jp-menu__pricegroup"></div>';

		// Defaults as requested
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

		// Single
		if ( $cfg['mode'] === 'single' && $cfg['price'] !== '' ) {
			$ref     = (string) ( $cfg['label_ref'] ?? '' );
			$hide    = (bool)   ( $cfg['hide_icon'] ?? false );
			$icon_id = (int)    ( $cfg['icon_id']   ?? 0 );

			$resolved   = jprm_resolve_label( $ref, $icon_id );
			$label_html = jprm_label_html( (string) $resolved['label_text'], (int) $resolved['icon_id'], $presentation, $hide );

			if ( $label_position === 'left' ) {
				echo '<div class="jp-menu__price">';
				echo   '<div class="jp-col-label">' . $label_html . '</div>';
				echo   '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span>';
				echo '</div>';
			} else {
				echo '<div class="jp-menu__price">';
				echo   '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( (string) $cfg['price'], $currency_opts ) ) . '</span>';
				echo   '<div class="jp-col-label">' . $label_html . '</div>';
				echo '</div>';
			}
		}

		// Multi
		if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
			foreach ( $cfg['rows'] as $row ) {
				$val = (string) ( $row['value'] ?? '' );
				if ( $val === '' ) continue;

				$ref     = (string) ( $row['label_ref'] ?? '' );
				$hide    = (bool)   ( $row['hide_icon'] ?? false );
				$icon_id = (int)    ( $row['icon_id']   ?? 0 );

				$resolved   = jprm_resolve_label( $ref, $icon_id );
				$label_html = jprm_label_html( (string) $resolved['label_text'], (int) $resolved['icon_id'], $presentation, $hide );

				if ( $label_position === 'left' ) {
					echo '<div class="jp-menu__price">';
					echo   '<div class="jp-col-label">' . $label_html . '</div>';
					echo   '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span>';
					echo '</div>';
				} else {
					echo '<div class="jp-menu__price">';
					echo   '<span class="jp-menu__value jp-col-price">' . esc_html( jprm_format_amount( $val, $currency_opts ) ) . '</span>';
					echo   '<div class="jp-col-label">' . $label_html . '</div>';
					echo '</div>';
				}
			}
		}

		echo '</div>'; // .jp-menu__pricegroup
		return (string) ob_get_clean();
	}
}

/* ================= Structured data for Matrix layout ================= */

/**
 * Provide structured label/price rows so templates can render a matrix per section.
 * Each row has:
 * - label_id   : int|null   (0/null when unknown; templates can synthesize a key from label_text)
 * - label_text : string
 * - icon_html  : string     (24x24 img if available)
 * - amount     : float|null (best-effort numeric extraction)
 * - formatted  : string     (currency-formatted output, same rules as jprm_format_amount)
 */
if ( ! function_exists( 'jprm_get_pricegroup_data' ) ) {
	function jprm_get_pricegroup_data( int $post_id, ?array $label_map = null, array $currency_opts = [] ) : array {
		$cfg = jprm_read_price_config( $post_id );
		if ( empty( $cfg ) ) return [];

		// Defaults aligned with renderer
		$defaults = [
			'show'     => true,
			'symbol'   => '€',
			'position' => 'before',
			'spacing'  => 'thin',
		];
		$currency_opts = array_merge( $defaults, $currency_opts );
		$currency_opts = apply_filters( 'jprm_currency_opts', $currency_opts, $post_id );

		$rows = [];

		$make_icon_html = function( int $icon_id ) : string {
			if ( $icon_id <= 0 ) return '';
			$img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
			return is_string( $img ) ? $img : '';
		};

		$to_amount = function( string $raw ) : ?float {
			$raw = trim( $raw );
			if ( $raw === '' ) return null;
			$norm = str_replace([ "\u{00A0}", ' ' ], '', $raw); // remove nbsp/spaces
			$norm = str_replace( ',', '.', $norm );
			$norm = preg_replace( '/[^0-9\.\-]/', '', $norm );
			if ( $norm === '' || $norm === '.' || $norm === '-' ) return null;
			return is_numeric( $norm ) ? (float) $norm : null;
		};

		// SINGLE
		if ( $cfg['mode'] === 'single' && (string) $cfg['price'] !== '' ) {
			$ref     = (string) ( $cfg['label_ref'] ?? '' );
			$hide    = (bool)   ( $cfg['hide_icon'] ?? false );
			$icon_id = (int)    ( $cfg['icon_id']   ?? 0 );

			$res      = jprm_resolve_label( $ref, $icon_id );
			$text     = (string) ( $res['label_text'] ?? '' );
			$icon_out = $hide ? '' : $make_icon_html( (int) ( $res['icon_id'] ?? 0 ) );

			$raw       = (string) $cfg['price'];
			$amount    = $to_amount( $raw );
			$formatted = jprm_format_amount( $raw, $currency_opts );

			$rows[] = [
				'label_id'   => 0,
				'label_text' => $text,
				'icon_html'  => $icon_out,
				'amount'     => $amount,
				'formatted'  => $formatted,
			];
		}

		// MULTI
		if ( $cfg['mode'] === 'multi' && ! empty( $cfg['rows'] ) ) {
			foreach ( $cfg['rows'] as $row ) {
				$raw = (string) ( $row['value'] ?? '' );
				if ( $raw === '' ) continue;

				$ref     = (string) ( $row['label_ref'] ?? '' );
				$hide    = (bool)   ( $row['hide_icon'] ?? false );
				$icon_id = (int)    ( $row['icon_id']   ?? 0 );

				$res      = jprm_resolve_label( $ref, $icon_id );
				$text     = (string) ( $res['label_text'] ?? '' );
				$icon_out = $hide ? '' : $make_icon_html( (int) ( $res['icon_id'] ?? 0 ) );

				$amount    = $to_amount( $raw );
				$formatted = jprm_format_amount( $raw, $currency_opts );

				$rows[] = [
					'label_id'   => 0,
					'label_text' => $text,
					'icon_html'  => $icon_out,
					'amount'     => $amount,
					'formatted'  => $formatted,
				];
			}
		}
// Normalize label_id for text-only labels so Matrix can align columns.
// We mirror the header's behavior in menu.php, which uses:
// $lid = crc32('t:' . (string) $r['label_text'])
if ( ! empty( $rows ) ) {
	foreach ( $rows as &$r ) {
		$lid = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
		if ( $lid <= 0 ) {
			$txt = (string) ( $r['label_text'] ?? '' );
			if ( $txt !== '' ) {
				// Use unsigned CRC32, same as header synthesis
				$r['label_id'] = (int) sprintf( '%u', crc32( 't:' . $txt ) );
			}
		}
	}
	unset( $r );
}

		$rows = apply_filters( 'jprm_get_pricegroup_data', $rows, $post_id, $label_map, $currency_opts );

		// Deduplicate (label_text + formatted)
		if ( ! empty( $rows ) ) {
			$seen = [];
			$out  = [];
			foreach ( $rows as $r ) {
				$key = md5( json_encode( [
					(string) ( $r['label_text'] ?? '' ),
					(string) ( $r['formatted']  ?? '' ),
				] ) );
				if ( isset( $seen[ $key ] ) ) continue;
				$seen[ $key ] = true;
				$out[] = [
					'label_id'   => (int) ( $r['label_id']   ?? 0 ),
					'label_text' => (string) ( $r['label_text'] ?? '' ),
					'icon_html'  => (string) ( $r['icon_html']  ?? '' ),
					'amount'     => isset( $r['amount'] ) ? ( ( $r['amount'] === null ) ? null : (float) $r['amount'] ) : null,
					'formatted'  => (string) ( $r['formatted'] ?? '' ),
				];
			}
			return $out;
		}

		return [];
	}
}

/* ================= Matrix helper: stable keys ================= */

if ( ! function_exists( 'jprm_price_rows_with_keys' ) ) {
	/**
	 * Get structured rows and add a stable 'key' used by the Matrix template.
	 * - If label_id > 0, key is "id:{id}"
	 * - Else key is "txt:{normalized label_text}"
	 */
	function jprm_price_rows_with_keys( int $post_id, ?array $label_map = null, array $currency_opts = [] ) : array {
		$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $post_id, $label_map, $currency_opts ) : [];
		if ( empty( $rows ) ) return [];

		$norm = static function( string $s ) : string {
			$s = wp_strip_all_tags( $s );
			$s = strtolower( $s );
			$s = preg_replace( '/[^a-z0-9]+/u', '-', $s );
			$s = trim( $s, '-' );
			return $s !== '' ? $s : 'label';
		};

		$out = [];
		foreach ( $rows as $r ) {
			$label_id   = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
			$label_text = isset( $r['label_text'] ) ? (string) $r['label_text'] : '';
			$key = $label_id > 0 ? ( 'id:' . $label_id ) : ( 'txt:' . $norm( $label_text ) );

			$r['key'] = $key;
			$out[] = $r;
		}
		return $out;
	}
}
