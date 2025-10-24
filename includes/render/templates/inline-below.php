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
$show_badges         = ! empty( $sctx['show_badges'] );
$badges_presentation = (string) ( $sctx['badges_presentation'] ?? 'icon_text' );
$badges_position     = (string) ( $sctx['badges_position'] ?? 'after_title' );
$sep                = (string)($sctx['inline_separator'] ?? '');

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

	echo '<div class="jp-menu__item"><div class="jp-menu__inner">';

	// LEFT / TOP: content
	echo '<div class="jp-menu__content">';
	echo '<div class="jp-menu__titleline">';
		$badges = $show_badges ? jprm_get_badges_for_post( $pid ) : [];
		if ( $show_badges && $badges && $badges_position === 'before_title' ) {
			echo jprm_render_badges( $badges, $badges_presentation ); // before title
		}
		if ($title !== '') echo '<div class="jp-menu__title">' . esc_html($title) . '</div>';
		if ( $show_badges && $badges && $badges_position === 'after_title' ) {
			echo jprm_render_badges( $badges, $badges_presentation );  // after title
		}
	echo '</div>'; // .jp-menu__titleline
	if ( is_string($desc) && $desc !== '' ) echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
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
						. ( $sep !== '' ? '<span class="jp-sep">'. esc_html($sep) .'</span>' : '' )
						. '<span class="jp-price">'. $price .'</span>'
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
