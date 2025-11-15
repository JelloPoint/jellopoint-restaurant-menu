<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inline (per-section)
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_position','label_map','currency_opts'
 *   // BADGES:
 *   'show_badges' => 'yes'|'no',
 *   'badges_position' => 'before'|'after',
 *   'badges_presentation' => 'icon'|'text'|'icon_text',
 *   // From menu.php (non-breaking extras):
 *   'section_level' => int (0 = main, 1+ = sub),
 *   'section_id'    => int (term_id),
 *   // Leader:
 *   'inline_leader_enable' => 'yes'|'no',
 *   'inline_leader_char'   => string (unused here, but passed through),
 *   'inline_leader_style'  => 'dotted'|'dashed'|'solid'
 * ]
 */

$sctx = ( isset($_section_ctx) && is_array($_section_ctx) ) ? $_section_ctx : [];

$items = is_array($sctx['items'] ?? null) ? $sctx['items'] : [];
$label_presentation = (string)($sctx['label_presentation'] ?? 'icon_text');
$label_position     = (string)($sctx['label_position'] ?? 'right');
$label_map          = is_array($sctx['label_map'] ?? null) ? $sctx['label_map'] : [];
$currency_opts      = is_array($sctx['currency_opts'] ?? null) ? $sctx['currency_opts'] : [];

$leader_enabled = (string)($sctx['inline_leader_enable'] ?? 'no') === 'yes';
$leader_style   = isset($sctx['inline_leader_style']) ? (string)$sctx['inline_leader_style'] : 'dotted';

// BADGES
$badges_enabled      = (string)($sctx['show_badges'] ?? 'yes') === 'yes';
$badges_position     = (string)($sctx['badges_position'] ?? 'after');
$badges_presentation = (string)($sctx['badges_presentation'] ?? 'icon_text');

// Level / ID
$section_level = (int)($sctx['section_level'] ?? 0);
$section_id    = (int)($sctx['section_id'] ?? 0);

if (empty($items)) return;

/* helpers (guarded, because this file is included per section) */
if (!function_exists('jprm_sanitize_single_icon')) {
	function jprm_sanitize_single_icon(string $html): string {
		$html = trim($html);
		if ($html === '') return '';
		if (preg_match('~<img\b[^>]*>~is', $html, $m)) return $m[0];
		if (preg_match('~<svg\b[^>]*>.*?</svg>~is', $html, $m)) return $m[0];
		return '';
	}
}
if (!function_exists('jprm_label_chip_inline')) {
	function jprm_label_chip_inline(array $meta, string $presentation): string {
		$text = trim((string)($meta['text'] ?? ''));

		$ico = '';
		if (!empty($meta['icon_html'])) {
			$ico = jprm_sanitize_single_icon((string)$meta['icon_html']);
		}
		$ico = jprm_colorize_icon(
			$ico,
			!empty($meta['icon_url']) ? (string)$meta['icon_url'] : null,
			'label'
		);

		switch ($presentation) {
			case 'icon':  return $ico !== '' ? $ico : esc_html($text);
			case 'text':  return esc_html($text);
			default:
			case 'icon_text':
				if ($ico !== '' && $text !== '') {
					return '<span class="jp-menu__label">'.$ico.'<span class="jp-badge__label">'.esc_html($text).'</span></span>';
				}
				return $ico !== '' ? $ico : esc_html($text);
		}
	}
}

// === Wrapper with level-aware classes for per-level styling
echo '<li class="jp-inline jp-layout-inline jp-menu__section jp-menu__section--level-' . (int)$section_level . '" data-section-id="' . (int)$section_id . '">';

foreach ($items as $post) {
	$pid   = (int)$post->ID;
	$title = get_the_title($pid);
	$desc  = get_post_meta($pid, 'jprm_desc', true);
	$rows  = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];

	// Determine if any price is present (so we don’t render a leader to nowhere)
	$has_price = false;
	foreach ($rows as $r) {
		if ( isset($r['formatted']) && (string)$r['formatted'] !== '' ) { $has_price = true; break; }
	}

	// BADGES: pre-render HTML once per item
	$badges_html = '';
	if ( $badges_enabled && function_exists('jprm_render_badges_inline_html') ) {
		$badges_html = jprm_render_badges_inline_html($pid, $badges_presentation);
	}

	echo '<div class="jp-menu__item"><div class="jp-menu__inner">';

	// --- ONE ROW: Title + (optional) leader + Pricegroup (baseline aligned) ---
	echo '<div class="jp-row jp-row--titleline">';

		echo '<div class="jp-menu__content">';

			if ($title !== '') {
				echo '<div class="jp-menu__titlewrap">';
					if ($badges_position === 'before' && $badges_html !== '') {
						echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '<span class="jp-menu__title">' . esc_html($title) . '</span>';
					if ($badges_position !== 'before' && $badges_html !== '') {
						echo $badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				echo '</div>';
			}

		echo '</div>'; // .jp-menu__content

		// Leader sits BETWEEN title and pricegroup, same baseline
		if ( $leader_enabled && $has_price ) {
			echo '<span class="jp-leader" aria-hidden="true" data-style="'.esc_attr($leader_style).'"></span>';
		}

		// Full pricegroup on the same row (can be multiple rows visually)
		echo '<div class="jp-menu__pricegroup">';
			foreach ($rows as $r) {
				$price = (string)($r['formatted'] ?? '');
				$lbl   = [
					'text'      => (string)($r['label_text'] ?? ''),
					'icon_html' => (string)($r['icon_html'] ?? ''),
					'icon'      => (string)($r['icon'] ?? ''),
					'svg'       => (string)($r['svg'] ?? ''),
					'icon_url'  => (string)($r['icon_url'] ?? ''),
				];
				echo '<div class="jp-price-row">';
					echo '<span class="jp-chip">'. jprm_label_chip_inline($lbl, $label_presentation) .'</span>';
					if ($price !== '') echo '<span class="jp-price">'. $price .'</span>';
				echo '</div>';
			}
		echo '</div>'; // .jp-menu__pricegroup

	echo '</div>'; // .jp-row--titleline

	// --- Description BELOW the row so it never pushes the leader down ---
	if (is_string($desc) && $desc !== '') {
		echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
	}

	echo '</div></div>'; // .jp-menu__inner / .jp-menu__item
}

echo '</li>';
