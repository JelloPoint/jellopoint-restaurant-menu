<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inline-Below (per section): one horizontal line of label/price pairs
 * placed FULL-WIDTH directly under the title/description.
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_map','currency_opts','inline_separator'
 *   'show_badges','badges_position','badges_presentation'
 * ]
 */

$sctx = isset($_section_ctx) && is_array($_section_ctx) ? $_section_ctx : [];
$items = is_array($sctx['items'] ?? null) ? $sctx['items'] : [];
$label_presentation = (string)($sctx['label_presentation'] ?? 'icon_text');
$label_map          = is_array($sctx['label_map'] ?? null) ? $sctx['label_map'] : [];
$currency_opts      = is_array($sctx['currency_opts'] ?? null) ? $sctx['currency_opts'] : [];
$sep                = (string)($sctx['inline_separator'] ?? '');

// Badges flags
$badges_enabled      = ((string)($sctx['show_badges'] ?? 'yes') === 'yes');
$badges_position     = (string)($sctx['badges_position'] ?? 'after');        // 'before'|'after'
$badges_presentation = (string)($sctx['badges_presentation'] ?? 'icon_text');// 'icon'|'text'|'icon_text'

// Elementor editor preview: show a dot if separator empty
$is_editor = false;
if ( class_exists('\Elementor\Plugin') ) {
    try { $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode(); } catch (\Throwable $e) {}
}
if ( $is_editor && $sep === '' ) { $sep = '·'; }

if (empty($items)) return;

/* shared helpers (guarded) */
if (!function_exists('jprm_sanitize_single_icon')) {
	function jprm_sanitize_single_icon(string $html): string {
		$html = trim($html);
		if ($html === '') return '';
		if (preg_match('~<img\b[^>]*>~is', $html, $m)) return $m[0];
		if (preg_match('~<svg\b[^>]*>.*?</svg>~is', $html, $m)) return $m[0];
		return '';
	}
}

/* label chip builder (uses unified colorizer) */
if (!function_exists('jprm_label_chip_inline_below')) {
	function jprm_label_chip_inline_below(array $meta, string $presentation): string {
		$text = trim((string)($meta['text'] ?? ''));

		// Icon pick + colorize (inline svg/img → cleaned | url .svg → mask | raster → <img>)
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
			case 'icon':
				return $ico !== '' ? $ico : esc_html($text);
			case 'text':
				return esc_html($text);
			case 'icon_text':
			default:
				if ($ico !== '' && $text !== '') {
					return '<span class="jp-menu__label">'.$ico.'<span class="jp-badge__label">'.esc_html($text).'</span></span>';
				}
				return $ico !== '' ? $ico : esc_html($text);
		}
	}
}

echo '<li class="jp-inline-below">';

foreach ($items as $post) {
	$pid   = (int)$post->ID;
	$title = get_the_title($pid);
	$desc  = get_post_meta($pid, 'jprm_desc', true);
	$rows  = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];

	// Pre-render badges for this item
	$badges_html = '';
	if ( $badges_enabled && function_exists('jprm_render_badges_inline_html') ) {
		$badges_html = jprm_render_badges_inline_html($pid, $badges_presentation);
	}

	echo '<div class="jp-menu__item"><div class="jp-menu__inner">';

	// Content (title + desc)
	echo '<div class="jp-menu__content">';
		if ($title !== '') {
			echo '<div class="jp-menu__titlewrap">';
				if ($badges_position === 'before' && $badges_html !== '') echo $badges_html;
				echo '<span class="jp-menu__title">' . esc_html($title) . '</span>';
				if ($badges_position !== 'before' && $badges_html !== '') echo $badges_html;
			echo '</div>';
		}
		if (is_string($desc) && $desc !== '') echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
	echo '</div>';

	// Full-width row below content
	echo '<div class="jp-menu__pricegroup jp-menu__pricegroup--below">';
		echo '<div class="jp-inline-below__line">';

			$pairs = [];

			// Count priced rows
			$__priced_total = 0;
			foreach ($rows as $r) {
				if (isset($r['formatted']) && (string)$r['formatted'] !== '') $__priced_total++;
			}

			$__priced_printed = 0;
			foreach ($rows as $r) {
				$price = (string)($r['formatted'] ?? '');
				$lbl   = [
					'text'      => (string)($r['label_text'] ?? ''),
					'icon_html' => (string)($r['icon_html'] ?? ''),
					'icon_url'  => (string)($r['icon_url'] ?? ''),
				];

				$chip = '<span class="jp-chip">'. jprm_label_chip_inline_below($lbl, $label_presentation) .'</span>';

				if ($price !== '') {
					$__priced_printed++;
					$show_sep_here = ($sep !== '' && $__priced_printed < $__priced_total);
					$pairs[] = '<span class="jp-chipline jp-chipline--priced">'
						. $chip
						. '<span class="jp-price">'. $price .'</span>'
						. ( $show_sep_here ? '<span class="jp-sep">'. esc_html($sep) .'</span>' : '' )
						. '</span>';
				} else {
					$pairs[] = '<span class="jp-chipline jp-chipline--noprice">'. $chip .'</span>';
				}
			}

			echo implode('', $pairs);

		echo '</div>'; // .jp-inline-below__line
	echo '</div>'; // .jp-menu__pricegroup--below

	echo '</div></div>'; // .jp-menu__inner / .jp-menu__item
}

echo '</li>';
