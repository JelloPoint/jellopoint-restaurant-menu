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

// Level / ID (kept for possible styling hooks; markup now handled by menu.php)
$section_level = (int) ( $sctx['section_level'] ?? 0 );
$section_id    = (int) ( $sctx['section_id']    ?? 0 );

if ( empty( $items ) ) {
	return;
}

/* helpers (guarded) */
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

		// Icon pick + colorize
		if ( ! empty( $meta['icon_html'] ) ) {
			$ico = jprm_sanitize_single_icon( (string) $meta['icon_html'] );
		}
		$ico = jprm_colorize_icon(
			$ico,
			! empty( $meta['icon_url'] ) ? (string) $meta['icon_url'] : null,
			'label'
		);

		// If truly no label (unlabeled position column), show non-breaking space
		if ( $text === '' && $ico === '' ) {
			return '<span class="jp-menu__label">&nbsp;</span>';
		}

		// Always wrap so style control hits both text & icon
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
 * Collect columns from:
 *  - Known labels in $label_map
 *  - Labels discovered in items (label_id or label_text)
 *  - PLUS position-based columns for unlabeled prices (ONLY when NO labels exist)
 * Returns ['cols'=>map, 'order'=>keys in order, 'has_any_label'=>bool].
 */
if ( ! function_exists( 'jprm_matrix_collect_columns' ) ) {
	function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
		$cols          = [];
		$has_any_label = false; // NEW: track whether any label exists in this section
		$max_unlabeled = 0;

		// 1) Seed from global label registry (in the order from the Labels screen)
		foreach ( $label_map as $meta ) {
			if ( empty( $meta['active'] ) ) {
				continue;
			}

			$txt = isset( $meta['label'] ) ? trim( (string) $meta['label'] ) : '';
			if ( $txt === '' ) {
				continue;
			}

			$key = 't:' . md5( $txt );
			if ( isset( $cols[ $key ] ) ) {
				continue;
			}

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

		// 2) Discover extra label texts from content + count unlabeled rows
		foreach ( $items as $post ) {
			$pid  = (int) $post->ID;
			$rows = function_exists( 'jprm_get_pricegroup_data' )
				? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
				: [];

			$unlabeled_for_item = 0;

			foreach ( $rows as $r ) {
				$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';

				if ( $txt !== '' ) {
					// Labeled row → text-based key
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
					// Truly unlabeled → counts towards positional columns
					$unlabeled_for_item++;
				}
			}

			if ( $unlabeled_for_item > $max_unlabeled ) {
				$max_unlabeled = $unlabeled_for_item;
			}
		}

		/**
		 * 3) Position columns p:0..p:(max-1) with empty header meta
		 *
		 * IMPORTANT:
		 * We ONLY create positional columns if **no labels are used at all**
		 * in this section. This avoids extra columns when you mix
		 * labeled and unlabeled rows.
		 */
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

		$order = array_keys( $cols );

		return [
			'cols'          => $cols,
			'order'         => $order,
			'has_any_label' => $has_any_label,
		];
	}
}


/**
 * Find the formatted value for a given column key.
 *  - labeled columns: match by label_id or label_text
 *  - position columns (p:N): take the Nth unlabeled row's formatted price
 */
if ( ! function_exists( 'jprm_matrix_find_cell' ) ) {
	function jprm_matrix_find_cell( array $rows, string $col_key ) : ?string {
		// Position-based column? (unlabeled prices)
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

		// Labeled columns: we always key by text: "t:md5(label_text)"
		foreach ( $rows as $r ) {
			$txt = isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '';
			if ( $txt === '' ) {
				continue;
			}

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
 * Filter to active columns: keep any column that has at least one value
 * across all items. For position columns, we also test by position index.
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

/* grid build */
$collect        = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
$cols           = $collect['cols'];
$col_keys       = jprm_matrix_filter_active_columns( $items, $collect['order'], $label_map, $currency_opts );
$has_any_label  = ! empty( $collect['has_any_label'] ); // NEW: we use this below

/**
 * REORDER HEADER COLUMNS TO FOLLOW PRICE LABELS ORDER
 * This makes Matrix follow the same order as:
 *   Settings → Price Labels (JPRM_Labels_Store::all())
 */
if ( ! empty( $col_keys ) && class_exists( '\JPRM_Labels_Store' ) && method_exists( '\JPRM_Labels_Store', 'all' ) ) {
	$labels       = \JPRM_Labels_Store::all();
	$labels_order = [];

	// Build map: normalized label text => position in labels UI
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
			// Position columns (p:N) always last, in numeric order
			$is_pos_a = ( strpos( $a, 'p:' ) === 0 );
			$is_pos_b = ( strpos( $b, 'p:' ) === 0 );
			if ( $is_pos_a && $is_pos_b ) {
				return ( (int) substr( $a, 2 ) ) <=> ( (int) substr( $b, 2 ) );
			}
			if ( $is_pos_a !== $is_pos_b ) {
				return $is_pos_a ? 1 : -1; // labeled first, unlabeled (position) last
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

			if ( $oa !== $ob ) {
				return $oa <=> $ob; // primary: price labels order
			}

			// Fallback: alphabetical by label text
			return strcmp( $ta, $tb );
		}
	);
}

$col_count = max( 1, count( $col_keys ) );

/**
 * Structure:
 * menu.php already created:
 *   <li class="jp-menu__section jp-menu__section-box ...">
 *     [header div here]
 *     [we now add this body wrapper:]
 *       <div class="jp-matrix" style="--jp-matrix-cols:N">...</div>
 *   </li>
 */
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

/* rows */
foreach ( $items as $post ) {
	$pid   = (int) $post->ID;
	$title = get_the_title( $pid );
	$desc  = get_post_meta( $pid, 'jprm_desc', true );
	$rows  = function_exists( 'jprm_get_pricegroup_data' )
		? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts )
		: [];

	// Collect unlabeled rows for this item (only relevant if the section uses labels)
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

		// First cell: item title + badges + desc + unlabeled price warnings
		echo '<div class="jp-matrix__cell jp-matrix__cell--item">';

			// BADGES: pre-render per item
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
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}

			// Inline warnings for unlabeled prices (in mixed-label sections)
			if ( $has_any_label && ! empty( $unlabeled_prices ) ) {
				echo '<div class="jp-matrix__unlabeled">';
				foreach ( $unlabeled_prices as $fmt ) {
					echo '<span class="jp-matrix__unlabeled-price" title="' .
						esc_attr__( 'Price without label – configure a Price Label for this column.', 'jellopoint-restaurant-menu' ) .
						'">';
						echo esc_html( $fmt );
						echo ' <span class="jp-matrix__unlabeled-warning">!</span>';
					echo '</span>';
				}
				echo '</div>';
			}

		echo '</div>';

		// Value cells for labeled / positional columns
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
