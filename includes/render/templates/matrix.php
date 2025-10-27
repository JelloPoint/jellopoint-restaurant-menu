<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix (per-section)
 * Expects $_section_ctx = [
 *   'term','items','label_presentation','label_map','currency_opts','matrix_placeholder'
 *   // BADGES
 *   'show_badges','badges_position','badges_presentation'
 * ]
 */

$sctx = isset($_section_ctx) && is_array($_section_ctx) ? $_section_ctx : [];
$items = is_array($sctx['items'] ?? null) ? $sctx['items'] : [];
$label_presentation = (string)($sctx['label_presentation'] ?? 'icon_text');
$label_map  = is_array($sctx['label_map'] ?? null) ? $sctx['label_map'] : [];
$currency_opts = is_array($sctx['currency_opts'] ?? null) ? $sctx['currency_opts'] : [];
$matrix_placeholder = (string)($sctx['matrix_placeholder'] ?? '');

// === BADGES: read from section ctx (with safe defaults)
$badges_enabled      = ((string)($sctx['show_badges'] ?? 'yes') === 'yes');
$badges_position     = (string)($sctx['badges_position'] ?? 'after');        // 'before'|'after'
$badges_presentation = (string)($sctx['badges_presentation'] ?? 'icon_text');// 'icon'|'text'|'icon_text'

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
if (!function_exists('jprm_matrix_header_cell')) {
	function jprm_matrix_header_cell(array $meta, string $presentation): string {
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
}
if (!function_exists('jprm_matrix_collect_columns')) {
	function jprm_matrix_collect_columns(array $items, array $label_map, array $currency_opts): array {
		$cols = [];
		foreach ($label_map as $lid => $meta) {
			$cols[(string)$lid] = ['text'=>(string)($meta['title'] ?? ($meta['text'] ?? '')), 'icon_html'=>(string)($meta['icon_html'] ?? ''), '_seed'=>true];
		}
		foreach ($items as $post) {
			$pid = (int)$post->ID;
			$rows = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];
			foreach ($rows as $r) {
				$lid = isset($r['label_id']) ? (int)$r['label_id'] : 0;
				$txt = (string)($r['label_text'] ?? '');
				$key = $lid > 0 ? (string)$lid : ($txt !== '' ? 't:'.md5($txt) : '');
				if ($key !== '' && !isset($cols[$key])) {
					$cols[$key] = ['text'=>$txt, 'icon_html'=>(string)($r['icon_html'] ?? '')];
				}
			}
		}
		return $cols;
	}
}
if (!function_exists('jprm_matrix_find_cell')) {
	function jprm_matrix_find_cell(array $rows, string $col_key): ?string {
		foreach ($rows as $r) {
			$lid = isset($r['label_id']) ? (int)$r['label_id'] : 0;
			$txt = (string)($r['label_text'] ?? '');
			$key = $lid > 0 ? (string)$lid : ($txt !== '' ? 't:'.md5($txt) : '');
			if ($key === $col_key) {
				$fmt = (string)($r['formatted'] ?? '');
				return $fmt !== '' ? $fmt : null;
			}
		}
		return null;
	}
}
if (!function_exists('jprm_matrix_filter_active_columns')) {
	function jprm_matrix_filter_active_columns(array $items, array $col_keys, array $label_map, array $currency_opts): array {
		$active = [];
		foreach ($col_keys as $k) {
			foreach ($items as $post) {
				$pid  = (int)$post->ID;
				$rows = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];
				if (jprm_matrix_find_cell($rows, $k) !== null) { $active[] = $k; break; }
			}
		}
		return $active;
	}
}

/* grid build */
$cols      = jprm_matrix_collect_columns($items, $label_map, $currency_opts);
$col_keys  = jprm_matrix_filter_active_columns($items, array_keys($cols), $label_map, $currency_opts);
$col_count = max(1, count($col_keys));

echo '<li class="jp-matrix" style="--jp-matrix-cols:' . esc_attr((string)$col_count) . '">';

/* header row: first cell blank */
echo '<div class="jp-matrix__row">';
echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item"></div>';
foreach ($col_keys as $k) {
	$meta = ['text'=> isset($cols[$k]['text']) ? (string)$cols[$k]['text'] : (string)$k, 'icon_html'=> (string)($cols[$k]['icon_html'] ?? '')];
	echo '<div class="jp-matrix__cell jp-matrix__cell--head" data-label-key="' . esc_attr($k) . '">'
		. jprm_matrix_header_cell($meta, $label_presentation)
		. '</div>';
}
echo '</div>';

/* rows */
foreach ($items as $post) {
	$pid   = (int)$post->ID;
	$title = get_the_title($pid);
	$desc  = get_post_meta($pid, 'jprm_desc', true);
	$rows  = function_exists('jprm_get_pricegroup_data') ? jprm_get_pricegroup_data($pid, $label_map, $currency_opts) : [];

	echo '<div class="jp-matrix__row" data-post-id="' . esc_attr((string)$pid) . '">';

	echo '<div class="jp-matrix__cell jp-matrix__cell--item">';

		// === BADGES: pre-render per item
		$badges_html = '';
		if ( $badges_enabled && function_exists('jprm_render_badges_inline_html') ) {
			$badges_html = jprm_render_badges_inline_html($pid, $badges_presentation);
		}

		// === BADGES: title + badges inline in the item cell
		if ($title !== '') {
			echo '<div class="jp-menu__titlewrap">';
				if ($badges_position === 'before' && $badges_html !== '') {
					echo $badges_html;
				}
				echo '<span class="jp-menu__title">' . esc_html($title) . '</span>';
				if ($badges_position !== 'before' && $badges_html !== '') {
					echo $badges_html;
				}
			echo '</div>';
		}

		if (is_string($desc) && $desc !== '') echo '<div class="jp-menu__desc">' . esc_html($desc) . '</div>';
	echo '</div>';

	foreach ($col_keys as $k) {
		$val = $rows ? jprm_matrix_find_cell($rows, $k) : null;
		if ($val === null || $val === '') {
			$val = $matrix_placeholder !== '' ? '<span class="jp-matrix__placeholder">' . esc_html($matrix_placeholder) . '</span>' : '';
		}
		echo '<div class="jp-matrix__cell jp-matrix__cell--value" data-label-key="' . esc_attr($k) . '">' . $val . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '</div>';
}

echo '</li>';
