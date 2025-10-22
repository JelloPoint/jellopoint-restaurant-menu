<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inline (per-section)
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_position','label_map','currency_opts'
 * ]
 * (No separator/placeholder logic needed here.)
 */

$sctx = isset($_section_ctx) && is_array($_section_ctx) ? $_section_ctx : [];
$items = is_array($sctx['items'] ?? null) ? $sctx['items'] : [];
$label_presentation = (string)($sctx['label_presentation'] ?? 'icon_text');
$label_position     = (string)($sctx['label_position'] ?? 'right');
$label_map          = is_array($sctx['label_map'] ?? null) ? $sctx['label_map'] : [];
$currency_opts      = is_array($sctx['currency_opts'] ?? null) ? $sctx['currency_opts'] : [];

if (empty($items)) return;

if (!function_exists('jprm_sanitize_single_icon')) {
	function jprm_sanitize_single_icon(string $html): string {
		$html = trim($html);
		if ($html === '') return '';
		if (preg_match('~<img\b[^>]*>~is', $html, $m)) return $m[0];
		if (preg_match('~<svg\b[^>]*>.*?</svg>~is', $html, $m)) return $m[0];
		return '';
	}
}
function jprm_label_chip_inline(array $meta, string $presentation): string {
	$text = trim((string)($meta['text'] ?? ''));
	$ico  = '';
	if (!empty($meta['icon_html'])) $ico = jprm_sanitize_single_icon((string)$meta['icon_html']);
	if ($ico === '' && !empty($meta['icon']))     $ico = jprm_sanitize_single_icon((string)$meta['icon']);
	if ($ico === '' && !empty($meta['svg']))      $ico = jprm_sanitize_single_icon((string)$meta['svg']);
	if ($ico === '' && !empty($meta['icon_url'])) $ico = '<img class="jp-label__icon" src="' . esc_url((string)$meta['icon_url']) . '" alt="" loading="lazy" decoding="async" />';
	switch ($presentation) {
		case 'icon':      return $ico !== '' ? $ico : esc_html($text);
		case 'text':      return esc_html($text);
		case 'icon_text':
		default:
			if ($ico !== '' && $text !== '') return '<span class="jp-menu__label">'.$ico.'<span>'.esc_html($text).'</span></span>';
			return $ico !== '' ? $ico : esc_html($text);
	}
}

echo '<li class="jp-inline">';

foreach ($items as $post) {
	$pid   = (int)$post->ID;
	$title = get_the_title($pid);
	$desc  = get_post_meta($pid, 'jprm_desc', true);
	$rows  = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];

	echo '<div class="jp-menu__item"><div class="jp-menu__inner">';

	echo '<div class="jp-menu__content">';
		if ($title !== '') echo '<div class="jp-menu__title">' . esc_html($title) . '</div>';
		if (is_string($desc) && $desc !== '') echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
	echo '</div>';

	echo '<div class="jp-menu__pricegroup">';

	/* inline: label chip + price, same line; if no price, just chip */
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
			if ($price !== '') echo '<span class="jp-price">'. $price .'</span>'; // formatted already
		echo '</div>';
	}

	echo '</div>'; // .jp-menu__pricegroup
	echo '</div></div>'; // .jp-menu__item
}

echo '</li>';
