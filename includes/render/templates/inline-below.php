<?php
/**
 * Inline-Below layout for JelloPoint Restaurant Menu
 *
 * Expected inputs from the section context ($_section_ctx → $sctx):
 * - items:            array of items; each $item can be WP_Post or array with keys ['ID','title','desc','labels','prices']
 * - inline_separator: string (e.g., "·" or "—")  // used visually between chipline elements
 * - show_inline_separator: truthy/falsey toggle
 *
 * This layout prints:
 *  - Left block: Title + Description
 *  - Right block: Price group (multiple price rows possible)
 *  - Below row: chip line (labels), with an optional visible separator span.jp-sep
 *
 * Notes:
 *  - Editor-safe: if Elementor editor and separator char is empty, we show a harmless default "·" for preview only.
 *  - All settings reads are guarded with isset()/!empty() to avoid sanitize_settings(null) fatal during live editing.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Resolve per-section context safely */
$sctx = isset($_section_ctx) && is_array($_section_ctx) ? $_section_ctx : [];

/** Elementor editor mode (safe if Elementor not present) */
$is_editor = false;
if ( class_exists( '\Elementor\Plugin' ) ) {
	try {
		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
	} catch ( \Throwable $e ) {
		$is_editor = false;
	}
}

/** Helper: get scalar with default */
$__get = function(array $arr, string $key, $default = null) {
	return array_key_exists($key, $arr) ? $arr[$key] : $default;
};

/** Pull separator settings with safe defaults */
$sep_char = (string) $__get($sctx, 'inline_separator', '');
$show_sep = !empty($__get($sctx, 'show_inline_separator', false));

/** In the editor, ensure you SEE something even if char not set yet */
if ( $is_editor && $sep_char === '' ) {
	$sep_char = '·';
}

/** Items to render */
$items = $__get($sctx, 'items', []);
if ( ! is_array($items) ) {
	$items = [];
}

/** Begin layout root wrapper (if your outer template already prints a wrapper, you can remove this) */
echo '<div class="jp-inline-below">';

/** Render each item row */
foreach ( $items as $item ) {

	// Normalize to simple fields
	$pid   = 0;
	$title = '';
	$desc  = '';
	$labels = [];   // array of label arrays or strings; we render chips if present
	$prices = [];   // array of rows; each row: ['label' => '...', 'price' => '$ 5']

	if ( is_object($item) && isset($item->ID) ) {
		$pid   = (int) $item->ID;
		$title = isset($item->post_title) ? (string) $item->post_title : '';
		$desc  = isset($item->post_excerpt) ? (string) $item->post_excerpt : '';
	} elseif ( is_array($item) ) {
		$pid   = isset($item['ID']) ? (int) $item['ID'] : 0;
		$title = isset($item['title']) ? (string) $item['title'] : ( isset($item['post_title']) ? (string) $item['post_title'] : '' );
		$desc  = isset($item['desc'])  ? (string) $item['desc']  : ( isset($item['post_excerpt']) ? (string) $item['post_excerpt'] : '' );
		$labels = isset($item['labels']) && is_array($item['labels']) ? $item['labels'] : [];
		$prices = isset($item['prices']) && is_array($item['prices']) ? $item['prices'] : [];
	}

	// Row wrapper; expose data-post-id if available (helps debugging/styling)
	echo '<div class="jp-menu__row"'. ( $pid ? ' data-post-id="'. esc_attr($pid) .'"' : '' ) .'>';

		// LEFT: content (titleline + description)
		echo '<div class="jp-menu__content">';

			// Title line (badges intentionally omitted per your pause)
			echo '<div class="jp-menu__titleline">';
				if ( $title !== '' ) {
					echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
				}
			echo '</div>';

			// Description (optional)
			if ( is_string($desc) && $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}

		echo '</div>'; // .jp-menu__content

		// RIGHT: price group
		echo '<div class="jp-menu__pricegroup">';

			// If structured prices were provided in $items, print them; otherwise, leave for server-side template hooks
			if ( $prices ) {
				foreach ( $prices as $row ) {
					$lbl   = '';
					$price = '';
					if ( is_array($row) ) {
						$lbl   = isset($row['label']) ? (string) $row['label'] : '';
						$price = isset($row['price']) ? (string) $row['price'] : '';
					} elseif ( is_string($row) ) {
						$price = $row;
					}
					echo '<div class="jp-price-row">';

						// Chip with label (optional)
						if ( $lbl !== '' ) {
							echo '<span class="jp-chip"><span class="jp-menu__label"><span>' . esc_html($lbl) . '</span></span></span>';
						}

						// Price token (currency + amount currently as one text node by design)
						if ( $price !== '' ) {
							echo '<span class="jp-price">' . wp_kses_post( $price ) . '</span>';
						}

					echo '</div>'; // .jp-price-row
				}
			}

		echo '</div>'; // .jp-menu__pricegroup

		// BELOW: chip line (labels row) with an optional visible separator
		// If your $labels array is empty, we still print the separator in the editor so designers can style it.
		echo '<div class="jp-chipline">';

			// Visible separator logic:
			// - Front-end: shown only if toggle is on AND char non-empty
			// - Editor: shown if toggle on OR char is empty (so it's still visible for styling)
			$should_print_sep = false;
			if ( $is_editor ) {
				$should_print_sep = ( $show_sep || $sep_char !== '' );
			} else {
				$should_print_sep = ( $show_sep && $sep_char !== '' );
			}
			if ( $should_print_sep ) {
				echo '<span class="jp-sep">' . esc_html( $sep_char ) . '</span>';
			}

			// Render chips if labels present (icon/image handling intentionally omitted in this pass)
			if ( $labels ) {
				foreach ( $labels as $lab ) {
					if ( is_string($lab) ) {
						$lab_text = $lab;
						echo '<span class="jp-chip"><span class="jp-menu__label"><span>' . esc_html($lab_text) . '</span></span></span>';
					} elseif ( is_array($lab) ) {
						$lab_text = isset($lab['text']) ? (string) $lab['text'] : '';
						$icon_html = isset($lab['icon_html']) ? (string) $lab['icon_html'] : '';
						echo '<span class="jp-chip"><span class="jp-menu__label">';
						if ( $icon_html !== '' ) {
							// allow safe inline SVG or <img> (assuming upstream sanitization); else strip
							echo wp_kses_post( $icon_html );
						}
						if ( $lab_text !== '' ) {
							echo '<span>' . esc_html( $lab_text ) . '</span>';
						}
						echo '</span></span>';
					}
				}
			}

		echo '</div>'; // .jp-chipline

	echo '</div>'; // .jp-menu__row
}

/** Close wrapper */
echo '</div>'; // .jp-inline-below
