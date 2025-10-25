<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Inline-Below (per section): one horizontal line of label/price pairs
 * placed FULL-WIDTH directly under the title/description.
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_map','currency_opts','inline_separator'
 * ]
 */

$sctx = isset($_section_ctx) && is_array($_section_ctx) ? $_section_ctx : [];
$items = is_array($sctx['items'] ?? null) ? $sctx['items'] : [];
$label_presentation = (string)($sctx['label_presentation'] ?? 'icon_text');
$label_map          = is_array($sctx['label_map'] ?? null) ? $sctx['label_map'] : [];
$currency_opts      = is_array($sctx['currency_opts'] ?? null) ? $sctx['currency_opts'] : [];
$sep                = (string)($sctx['inline_separator'] ?? '');

// Ensure separator is visible in the Elementor editor even if not saved yet
$is_editor = false;
if ( class_exists('\Elementor\Plugin') ) {
    try {
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
    } catch (\Throwable $e) {}
}
if ( $is_editor && $sep === '' ) {
    $sep = '·'; // harmless default just for live preview
}

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
if (!function_exists('jprm_label_chip_inline_below')) {
	function jprm_label_chip_inline_below(array $meta, string $presentation): string {
		$text = trim((string)($meta['text'] ?? ''));
		$ico  = '';
		if (!empty($meta['icon_html'])) $ico = jprm_sanitize_single_icon((string)$meta['icon_html']);
		if ($ico === '' && !empty($meta['icon']))     $ico = jprm_sanitize_single_icon((string)$meta['icon']);
		if ($ico === '' && !empty($meta['svg']))      $ico = jprm_sanitize_single_icon((string)$meta['svg']);
		if ($ico === '' && !empty($meta['icon_url'])) $ico = '<img class="jp-label__icon" src="' . esc_url((string)$meta['icon_url']) . '" alt="" loading="lazy" decoding="async" />';
		switch ($presentation) {
			case 'icon':     return $ico !== '' ? $ico : esc_html($text);
			case 'text':     return esc_html($text);
			case 'icon_text':
			default:
				if ($ico !== '' && $text !== '') {
					return '<span class="jp-menu__label">'.$ico.'<span>'.esc_html($text).'</span></span>';
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

/*DEBUG  START*/	
if ( isset($_GET['jprm_dbg']) ) { echo "\n<!-- inline-below pid=$pid rows=" . (is_array($rows)?count($rows):0) . " sep='". esc_html($sep) ."' -->\n"; }
/*DEBUG STOP*/
	
	echo '<div class="jp-menu__item"><div class="jp-menu__inner">';

	// LEFT / TOP: content
	echo '<div class="jp-menu__content">';
		if ($title !== '') echo '<div class="jp-menu__title">' . esc_html($title) . '</div>';
		if (is_string($desc) && $desc !== '') echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
	echo '</div>';

	// FULL-WIDTH row BELOW content (spans both columns when grid is used)
	echo '<div class="jp-menu__pricegroup jp-menu__pricegroup--below">';
		echo '<div class="jp-inline-below__line">';

			$pairs = [];
			foreach ($rows as $r) {
				$price = (string)($r['formatted'] ?? '');
				$lbl   = [
					'text'      => (string)($r['label_text'] ?? ''),
					'icon_html' => (string)($r['icon_html'] ?? ''),
					'icon'      => (string)($r['icon'] ?? ''),
					'svg'       => (string)($r['svg'] ?? ''),
					'icon_url'  => (string)($r['icon_url'] ?? ''),
				];
				
				$chip = '<span class="jp-chip">'. jprm_label_chip_inline_below($lbl, $label_presentation) .'</span>';

				if ($price !== '') {
    $pairs[] = '<span class="jp-chipline jp-chipline--priced">'
        . $chip
        . '<span class="jp-price">'. $price .'</span>'
        . ( $sep !== '' ? '<span class="jp-sep">'. esc_html($sep) .'</span>' : '' )
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
