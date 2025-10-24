<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Collect label columns for Matrix */
function jprm_section_collect_label_columns( array $items, ?array $label_map, array $currency_opts ) : array {
  $cols = [];
  foreach ( $items as $post ) {
    $pid   = (int) $post->ID;
    $rows  = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
    foreach ( $rows as $r ) {
      $lid = (int) ( $r['label_id'] ?? 0 );
      if ( $lid <= 0 && empty( $r['label_text'] ) ) continue;
      if ( $lid <= 0 ) $lid = crc32( 't:' . (string) $r['label_text'] );
      if ( ! isset( $cols[ $lid ] ) ) {
        $cols[ $lid ] = [
          'text'      => (string) ( $r['label_text'] ?? '' ),
          'icon_html' => (string) ( $r['icon_html']  ?? '' ),
        ];
        if ( $label_map && isset( $label_map[$lid] ) && is_array( $label_map[$lid] ) ) {
          if ( isset( $label_map[$lid]['text'] ) && $label_map[$lid]['text'] !== '' )
            $cols[$lid]['text'] = (string) $label_map[$lid]['text'];
          if ( isset( $label_map[$lid]['icon_html'] ) && $label_map[$lid]['icon_html'] !== '' )
            $cols[$lid]['icon_html'] = (string) $label_map[$lid]['icon_html'];
        }
      }
    }
  }
  return $cols;
}

function jprm_label_header_cell( array $l, string $presentation ) : string {
  $text = trim( (string) ( $l['text'] ?? '' ) );
  $ico  = (string) ( $l['icon_html'] ?? '' );
  if ( $presentation === 'icon' && $ico !== '' ) return '<span class="jp-lhdr-ico">'.$ico.'</span>';
  if ( $presentation === 'text' || $ico === '' ) return '<span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
  return '<span class="jp-lhdr-ico">'.$ico.'</span><span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
}

function jprm_item_value_for_label( int $post_id, int $lid, ?array $label_map, array $currency_opts ) : ?string {
  $rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $post_id, $label_map, $currency_opts ) : [];
  if ( empty( $rows ) ) return null;
  foreach ( $rows as $r ) {
    if ( (int)($r['label_id'] ?? 0) === $lid ) {
      $fmt = (string) ( $r['formatted'] ?? '' );
      return $fmt !== '' ? $fmt : null;
    }
  }
  return null;
}


if ( ! function_exists( 'jprm_render_price_token' ) ) {
	/**
	 * Render a price token with separate currency + amount using short classes.
	 *
	 * @param string $amount   Numeric/text amount, e.g. '5' or '10.50'.
	 * @param string $currency Currency symbol, e.g. '€' or '$'.
	 * @param string $pos      'before' | 'after'
	 * @param array  $span_attrib Optional extra attributes for the outer <span class="jp-price">.
	 */
	function jprm_render_price_token( string $amount, string $currency, string $pos = 'before', array $span_attrib = [] ) : void {
		$pos = ( $pos === 'after' ) ? 'after' : 'before';
		$classes = 'jp-price jp-currency-pos-' . $pos;

		// Merge user attributes (optional)
		$attr = array_merge( [ 'class' => $classes ], $span_attrib );
		$attr_str = '';
		foreach ( $attr as $k => $v ) {
			$attr_str .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
		}

		echo '<span' . $attr_str . '>';
		if ( $pos === 'before' ) {
			echo '<span class="jp-currency">' . esc_html( $currency ) . '</span>';
			echo '<span class="jp-amount">'   . esc_html( $amount )   . '</span>';
		} else {
			echo '<span class="jp-amount">'   . esc_html( $amount )   . '</span>';
			echo '<span class="jp-currency">' . esc_html( $currency ) . '</span>';
		}
		echo '</span>';
	}
  // ===== Badges: collect from taxonomy + render =====
if ( ! function_exists( 'jprm_get_badges_for_post' ) ) {
	function jprm_get_badges_for_post( int $post_id ) : array {
		$out = [];
		$terms = get_the_terms( $post_id, 'jprm_badge' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				$meta_icon_html = get_term_meta( $t->term_id, 'icon_html', true );
				$meta_icon_url  = get_term_meta( $t->term_id, 'icon_url',  true );
				$ico = '';
				if ( is_string( $meta_icon_html ) && $meta_icon_html !== '' ) {
					$ico = $meta_icon_html;
				} elseif ( is_string( $meta_icon_url ) && $meta_icon_url !== '' ) {
					$ico = '<img class="jp-menu__icon" src="' . esc_url( $meta_icon_url ) . '" alt="" loading="lazy" decoding="async" />';
				}
				$out[] = [
					'text' => (string) $t->name,
					'ico'  => $ico, // may be empty → text-only badge
				];
			}
		}
		return $out;
	}
}
if ( ! function_exists( 'jprm_render_badges' ) ) {
	function jprm_render_badges( array $badges, string $presentation = 'icon_text' ) : string {
		if ( empty( $badges ) ) return '';
		$html = '<span class="jp-badges">';
		foreach ( $badges as $b ) {
			$text = isset( $b['text'] ) ? trim( (string) $b['text'] ) : '';
			$ico  = isset( $b['ico'] )  ? (string) $b['ico'] : '';
			switch ( $presentation ) {
				case 'icon':      $inner = ( $ico !== '' ) ? $ico : esc_html( $text ); break;
				case 'text':      $inner = esc_html( $text ); break;
				case 'icon_text':
				default:
					$inner = ( $ico !== '' && $text !== '' )
						? '<span class="jp-badge">' . $ico . '<span>' . esc_html( $text ) . '</span></span>'
						: '<span class="jp-badge">' . ( $ico !== '' ? $ico : esc_html( $text ) ) . '</span>';
					$html .= $inner;
					continue 2; // already wrapped in .jp-badge
			}
			$html .= '<span class="jp-badge">' . $inner . '</span>';
		}
		$html .= '</span>';
		return $html;
	}
}
}

