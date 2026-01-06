<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix (per-section)
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_map','currency_opts','matrix_placeholder'
 *   'show_badges','badges_position','badges_presentation'
 *   // Extras from menu.php:
 *   'section_level' => int (0 = main, 1+ = sub),
 *   'section_id'    => int (term_id)
 * ]
 */

$sctx  = ( isset( $_section_ctx ) && is_array( $_section_ctx ) ) ? $_section_ctx : [];
$items = is_array( $sctx['items'] ?? null ) ? $sctx['items'] : [];

$label_presentation = (string) ( $sctx['label_presentation'] ?? 'icon_text' );
$label_map          = is_array( $sctx['label_map'] ?? null ) ? $sctx['label_map'] : [];
$currency_opts      = is_array( $sctx['currency_opts'] ?? null ) ? $sctx['currency_opts'] : [];
$matrix_placeholder = (string) ( $sctx['matrix_placeholder'] ?? '' );

// BADGES
$badges_enabled      = ( (string) ( $sctx['show_badges'] ?? 'yes' ) === 'yes' );
$badges_position     = (string) ( $sctx['badges_position']     ?? 'after' );
$badges_presentation = (string) ( $sctx['badges_presentation'] ?? 'icon_text' );

// Level / ID (for styling hooks; wrapper lives in menu.php)
$section_level = (int) ( $sctx['section_level'] ?? 0 );
$section_id    = (int) ( $sctx['section_id']    ?? 0 );

if ( empty( $items ) ) {
	return;
}

/* ---------- helpers (guarded) ---------- */

if ( ! function_exists( 'jprm_sanitize_single_icon' ) ) {
	function jprm_sanitize_single_icon( string $html ) : string {
		$html = trim( $html );
		if ( $html === '' ) return '';
		if ( preg_match( '~<img\b[^>]*>~is', $html, $m ) ) return $m[0];
		if ( preg_match( '~<svg\b[^>]*>.*?</svg>~is', $html, $m ) ) return $m[0];
		return '';
	}
}

/**
 * Header cell renderer. For unlabeled "position" columns, prints a NBSP.
 */
if ( ! function_exists( 'jprm_matrix_header_cell' ) ) {
	function jprm_matrix_header_cell( array $meta, string $presentation ) : string {
		$text = isset( $meta['text'] ) ? trim( (string) $meta['text'] ) : '';
		$ico  = '';

		if ( ! empty( $meta['icon_html'] ) ) {
			$ico = jprm_sanitize_single_icon( (string) $meta['icon_html'] );
		}
		$ico = jprm_colorize_icon(
			$ico,
			! empty( $meta['icon_url'] ) ? (string) $meta['icon_url'] : null,
			'label'
		);

		if ( $text === '' && $ico === '' ) {
			return '<span class="jp-menu__label">&nbsp;</span>';
		}

		if ( $presentation === 'icon' ) {
			$content = ( $ico !== '' ? $ico : esc_html( $text ) );
			return '<span class="jp-menu__label">' . $content . '</span>';
		}
		if ( $presentation === 'text' ) {
			return '<span class="jp-menu__label"><span class="jp-badge__label">' . esc_html( $text ) . '</span></span>';
		}
		// icon_text
		if ( $ico !== '' && $text !== '' ) {
			return '<span class="jp-menu__label">' . $ico . '<span class="jp-badge__label">' . esc_html( $text ) . '</span></span>';
		}
		return '<span class="jp-menu__label">' . ( $ico !== '' ? $ico : esc_html( $text ) ) . '</span>';
	}
}

/**
 * Collect columns:
 *  - global label registry
 *  - labels discovered in items
 *  - positional columns **only** when NO labels exist at all
 */
if ( ! function_exists( 'jprm_matrix_collect_columns' ) ) {
	function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
		$cols          = [];
		$has_any_label = false;
		$max_unlabeled = 0;

		// 1) seed from Labels screen
		foreach ( $label_map as $meta ) {
			if ( empty( $meta['active'] ) ) continue;
			$txt = isset( $meta['label'] ) ? trim( (string) $meta['label'] ) : '';
			if ( $txt === '' ) continue;

			$key = 't:' . md5( $txt );
			if ( isset( $cols[ $key ] ) ) continue;

			$icon_html = '';
			if ( ! empty( $meta['icon_id'] ) ) {
				$img = wp_get_attachment_image(
					(int) $meta['icon_id'],
					[ 24, 24 ],
					false,
					[ 'class' => 'jp-menu__icon' ]
				);
				if ( is_string( $img ) ) {
					$icon_html = $img;
				}
			}

			$cols[ $key ] = [
				'text'      => $txt,
				'icon_html' => $icon_html,
				'icon_url'  => '',
				'_seed'     => true,
			];
			$has_any_label = true;
		}

		// 2) discover labels + count unlabeled rows
		foreach ( $items as $post ) {
			$pid  = (int) $post->ID;
			$rows = function_exists( 'jprm_get_pricegroup_data' )
				? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
				: [];

			$unlabeled_for_item = 0;

			foreach ( $rows as $r ) {
				$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';

				if ( $txt !== '' ) {
					$key = 't:' . md5( $txt );
					if ( ! isset( $cols[ $key ] ) ) {
						$cols[ $key ] = [
							'text'      => $txt,
							'icon_html' => (string) ( $r['icon_html'] ?? '' ),
							'icon_url'  => (string) ( $r['icon_url']  ?? '' ),
						];
					}
					$has_any_label = true;
				} else {
					$unlabeled_for_item++;
				}
			}

			if ( $unlabeled_for_item > $max_unlabeled ) {
				$max_unlabeled = $unlabeled_for_item;
			}
		}

		// 3) positional columns ONLY when **no labels at all**
		if ( ! $has_any_label && $max_unlabeled > 0 ) {
			for ( $i = 0; $i < $max_unlabeled; $i++ ) {
				$key = 'p:' . $i;
				if ( ! isset( $cols[ $key ] ) ) {
					$cols[ $key ] = [
						'text'      => '',
						'icon_html' => '',
						'icon_url'  => '',
						'_pos'      => $i,
					];
				}
			}
		}

		return [
			'cols'          => $cols,
			'order'         => array_keys( $cols ),
			'has_any_label' => $has_any_label,
		];
	}
}

/**
 * Find cell value.
 */
if ( ! function_exists( 'jprm_matrix_find_cell' ) ) {
	function jprm_matrix_find_cell( array $rows, string $col_key ) : ?string {
		if ( strpos( $col_key, 'p:' ) === 0 ) {
			$idx  = (int) substr( $col_key, 2 );
			$seen = 0;
			foreach ( $rows as $r ) {
				$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';
				if ( $txt === '' ) {
					if ( $seen === $idx ) {
						$fmt = (string) ( $r['formatted'] ?? '' );
						return $fmt !== '' ? $fmt : null;
					}
					$seen++;
				}
			}
			return null;
		}

		foreach ( $rows as $r ) {
			$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';
			if ( $txt === '' ) continue;

			$key = 't:' . md5( $txt );
			if ( $key === $col_key ) {
				$fmt = (string) ( $r['formatted'] ?? '' );
				return $fmt !== '' ? $fmt : null;
			}
		}
		return null;
	}
}

/**
 * Reduce to active columns.
 */
if ( ! function_exists( 'jprm_matrix_filter_active_columns' ) ) {
	function jprm_matrix_filter_active_columns( array $items, array $col_keys, array $label_map, array $currency_opts ) : array {
		$active = [];
		foreach ( $col_keys as $k ) {
			foreach ( $items as $post ) {
				$pid  = (int) $post->ID;
				$rows = function_exists( 'jprm_get_pricegroup_data' )
					? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
					: [];
				if ( jprm_matrix_find_cell( $rows, $k ) !== null ) {
					$active[] = $k;
					break;
				}
			}
		}
		return $active;
	}
}

/* ---------- grid build ---------- */

$collect        = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
$cols           = $collect['cols'];
$col_keys       = jprm_matrix_filter_active_columns( $items, $collect['order'], $label_map, $currency_opts );
$has_any_label  = ! empty( $collect['has_any_label'] );

if ( ! empty( $col_keys ) && class_exists( '\JPRM_Labels_Store' ) && method_exists( '\JPRM_Labels_Store', 'all' ) ) {
	$labels       = \JPRM_Labels_Store::all();
	$labels_order = [];

	foreach ( $labels as $idx => $lbl ) {
		$name = '';
		if ( isset( $lbl['label'] ) && $lbl['label'] !== '' ) {
			$name = (string) $lbl['label'];
		} elseif ( isset( $lbl['label_text'] ) ) {
			$name = (string) $lbl['label_text'];
		}
		$name_norm = trim( strtolower( wp_strip_all_tags( $name ) ) );
		if ( $name_norm !== '' && ! isset( $labels_order[ $name_norm ] ) ) {
			$labels_order[ $name_norm ] = (int) $idx;
		}
	}

	usort(
		$col_keys,
		function ( string $a, string $b ) use ( $cols, $labels_order ) {
			$is_pos_a = ( strpos( $a, 'p:' ) === 0 );
			$is_pos_b = ( strpos( $b, 'p:' ) === 0 );
			if ( $is_pos_a && $is_pos_b ) {
				return ( (int) substr( $a, 2 ) ) <=> ( (int) substr( $b, 2 ) );
			}
			if ( $is_pos_a !== $is_pos_b ) {
				return $is_pos_a ? 1 : -1;
			}

			$ta = '';
			if ( isset( $cols[ $a ]['text'] ) ) {
				$ta = trim( strtolower( wp_strip_all_tags( (string) $cols[ $a ]['text'] ) ) );
			}
			$tb = '';
			if ( isset( $cols[ $b ]['text'] ) ) {
				$tb = trim( strtolower( wp_strip_all_tags( (string) $cols[ $b ]['text'] ) ) );
			}

			$oa = $labels_order[ $ta ] ?? PHP_INT_MAX;
			$ob = $labels_order[ $tb ] ?? PHP_INT_MAX;

			if ( $oa !== $ob ) return $oa <=> $ob;
			return strcmp( $ta, $tb );
		}
	);
}

$col_count = max( 1, count( $col_keys ) );

/* ---------- markup ---------- */

echo '<div class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

/* header row: first cell blank (item title column) */
echo '<div class="jp-matrix__row jp-matrix__row--header">';
	echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item"></div>';
	foreach ( $col_keys as $k ) {
		$meta = [
			'text'      => isset( $cols[ $k ]['text'] ) ? (string) $cols[ $k ]['text'] : '',
			'icon_html' => (string) ( $cols[ $k ]['icon_html'] ?? '' ),
			'icon_url'  => (string) ( $cols[ $k ]['icon_url']  ?? '' ),
		];
		echo '<div class="jp-matrix__cell jp-matrix__cell--head" data-label-key="' . esc_attr( $k ) . '">'
			. jprm_matrix_header_cell( $meta, $label_presentation )
			. '</div>';
	}
echo '</div>';

/* body rows */
foreach ( $items as $post ) {
	$pid   = (int) $post->ID;
	$title = get_the_title( $pid );
	$desc  = get_post_meta( $pid, 'jprm_desc', true );
	$rows  = function_exists( 'jprm_get_pricegroup_data' )
		? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
		: [];

	// Collect unlabeled prices for this item (only relevant when section uses labels)
	$unlabeled_prices = [];
	if ( $has_any_label && ! empty( $rows ) ) {
		foreach ( $rows as $r ) {
			$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';
			if ( $txt === '' ) {
				$fmt = (string) ( $r['formatted'] ?? '' );
				if ( $fmt !== '' ) {
					$unlabeled_prices[] = $fmt;
				}
			}
		}
	}

	echo '<div class="jp-matrix__row" data-post-id="' . esc_attr( (string) $pid ) . '">';

		// First cell: title + badges + desc + unlabeled warnings
		echo '<div class="jp-matrix__cell jp-matrix__cell--item">';

			$badges_html = '';
			if ( $badges_enabled && function_exists( 'jprm_render_badges_inline_html' ) ) {
				$badges_html = jprm_render_badges_inline_html( $pid, $badges_presentation );
			}

			if ( $title !== '' ) {
				echo '<div class="jp-menu__titlewrap">';
					if ( $badges_position === 'before' && $badges_html !== '' ) {
						echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '<span class="jp-menu__title">' . esc_html( $title ) . '</span>';
					if ( $badges_position !== 'before' && $badges_html !== '' ) {
						echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				echo '</div>';
			}

			if ( is_string( $desc ) && $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . wpautop( wp_kses_post( $desc ) ) . '</div>';
			}

			// Unlabeled prices warning block
			if ( $has_any_label && ! empty( $unlabeled_prices ) ) {
				echo "\n<!-- jprm-matrix-unlabeled #{$pid}: " . esc_html( implode( ', ', $unlabeled_prices ) ) . " -->\n";
				echo '<div class="jp-matrix__unlabeled">';
				foreach ( $unlabeled_prices as $fmt ) {
					echo '<div class="jp-matrix__unlabeled-price" title="' .
						esc_attr__( 'Price without label – configure a Price Label for this column.', 'jellopoint-restaurant-menu' ) .
						'">';
						echo '⚠ ' . esc_html( $fmt );
					echo '</div>';
				}
				echo '</div>';
			}

		echo '</div>';

		// Matrix value cells
		foreach ( $col_keys as $k ) {
			$val = $rows ? jprm_matrix_find_cell( $rows, $k ) : null;
			if ( $val === null || $val === '' ) {
				$val = $matrix_placeholder !== ''
					? '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>'
					: '';
			}
			echo '<div class="jp-matrix__cell jp-matrix__cell--value" data-label-key="' . esc_attr( $k ) . '">'
				. $val
				. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

	echo '</div>';
}

echo '</div>'; // .jp-matrix
