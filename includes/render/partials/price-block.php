<?php
/**
 * Price + Labels rendering partial for JelloPoint Restaurant Menu.
 *
 * Pure presentational: reads 'jprm_price' JSON and renders rows.
 * Now uses JPRM_Labels_Store::resolve() so labels/icons match your store.
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
<?php
// === Structured price data helper for Matrix layout (safe, additive) ===
// Place this at the END of includes/render/partials/price-block.php
// It does NOT replace any existing functions.

if ( ! function_exists( 'jprm_get_pricegroup_data' ) ) {
	/**
	 * Return structured price data for a menu item.
	 * Attempts to read your internal config via jprm_read_price_config().
	 * Falls back to a filter so you can supply exact rows if needed.
	 *
	 * @param int        $post_id
	 * @param array|null $label_map     from jprm_build_label_map() (optional)
	 * @param array      $currency_opts same shape you pass to renderer (optional):
	 *                                  ['show'=>bool,'symbol'=>string,'position'=>'before|after','spacing'=>'none|thin|normal']
	 * @return array[] Each row: [
	 *   'label_id'   => int|null,
	 *   'label_text' => string,
	 *   'icon_html'  => string,
	 *   'amount'     => float|null,
	 *   'formatted'  => string, // ready to print with currency rules
	 * ]
	 */
	function jprm_get_pricegroup_data( int $post_id, ?array $label_map = null, array $currency_opts = [] ) : array {
		$rows = [];

		// Try to read the same config used by your HTML renderer.
		$cfg = function_exists( 'jprm_read_price_config' ) ? jprm_read_price_config( $post_id ) : [];

		// Heuristic extraction to support common shapes:
		// - $cfg['prices'] = [ {label_id|label|label_text, amount|price|value}, ... ]
		// - $cfg['groups'] = [ {prices:[...]}, ... ]
		$maybe_prices = [];

		if ( is_array( $cfg ) ) {
			if ( isset( $cfg['prices'] ) && is_array( $cfg['prices'] ) ) {
				$maybe_prices = $cfg['prices'];
			} elseif ( isset( $cfg['groups'] ) && is_array( $cfg['groups'] ) ) {
				foreach ( $cfg['groups'] as $g ) {
					if ( isset( $g['prices'] ) && is_array( $g['prices'] ) ) {
						$maybe_prices = array_merge( $maybe_prices, $g['prices'] );
					}
				}
			}
		}

		foreach ( $maybe_prices as $p ) {
			$label_id = null;
			if ( isset( $p['label_id'] ) ) {
				$label_id = (int) $p['label_id'];
			} elseif ( isset( $p['label'] ) && is_numeric( $p['label'] ) ) {
				$label_id = (int) $p['label'];
			}

			$amount = null;
			if ( isset( $p['amount'] ) && $p['amount'] !== '' ) {
				$amount = (float) $p['amount'];
			} elseif ( isset( $p['price'] ) && $p['price'] !== '' ) {
				$amount = (float) $p['price'];
			} elseif ( isset( $p['value'] ) && $p['value'] !== '' ) {
				$amount = (float) $p['value'];
			}

			$label_text = '';
			$icon_html  = '';

			// Prefer label_map when we have an id
			if ( $label_id && is_array( $label_map ) && isset( $label_map[ $label_id ] ) ) {
				$lm = $label_map[ $label_id ];
				$label_text = is_array( $lm ) && isset( $lm['text'] ) ? (string) $lm['text'] : (string) $lm;
				if ( is_array( $lm ) && ! empty( $lm['icon_html'] ) ) {
					$icon_html = (string) $lm['icon_html'];
				}
			} else {
				// Fallbacks for when config carries the display text directly
				if ( isset( $p['label_text'] ) ) {
					$label_text = (string) $p['label_text'];
				} elseif ( isset( $p['label_name'] ) ) {
					$label_text = (string) $p['label_name'];
				} elseif ( isset( $p['label'] ) && ! is_numeric( $p['label'] ) ) {
					$label_text = (string) $p['label'];
				}
			}

			// Format number with currency rules used elsewhere
			$formatted = '';
			if ( $amount !== null ) {
				$symbol   = isset( $currency_opts['symbol'] ) ? (string) $currency_opts['symbol'] : '€';
				$position = isset( $currency_opts['position'] ) ? (string) $currency_opts['position'] : 'before';
				$spacing  = isset( $currency_opts['spacing'] ) ? (string) $currency_opts['spacing'] : 'thin';
				$space    = ( $spacing === 'none' ) ? '' : ( $spacing === 'normal' ? '&nbsp;' : '&#8201;' ); // thin space default

				if ( ! empty( $currency_opts['show'] ) ) {
					$formatted = ( $position === 'after' )
						? number_format_i18n( $amount, 0 ) . $space . $symbol
						: $symbol . $space . number_format_i18n( $amount, 0 );
				} else {
					$formatted = number_format_i18n( $amount, 0 );
				}
			}

			$rows[] = [
				'label_id'   => $label_id,
				'label_text' => $label_text,
				'icon_html'  => $icon_html,
				'amount'     => $amount,
				'formatted'  => $formatted,
			];
		}

		/**
		 * Allow exact/alternate sources to provide data or post-process it.
		 * Return the final array of rows with keys described above.
		 */
		$rows = apply_filters( 'jprm_get_pricegroup_data', $rows, $post_id, $label_map, $currency_opts );

		// Deduplicate by (label_id, label_text, formatted)
		if ( ! empty( $rows ) ) {
			$uniq = [];
			$out  = [];
			foreach ( $rows as $r ) {
				$key = md5( json_encode( [
					(int) ( $r['label_id'] ?? 0 ),
					(string) ( $r['label_text'] ?? '' ),
					(string) ( $r['formatted'] ?? '' ),
				] ) );
				if ( isset( $uniq[ $key ] ) ) continue;
				$uniq[ $key ] = true;
				$out[] = $r;
			}
			return $out;
		}

		return [];
	}
}
