<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// DEBUG: template fingerprint (remove after testing)
echo "\n<!-- JP-MATRIX TEMPLATE LOADED @ " . esc_html( __FILE__ ) . " -->\n";
if ( function_exists('error_log') ) { @error_log('JP MATRIX TEMPLATE LOADED: ' . __FILE__); }

if ( function_exists('jprm_debug_section_hit') ) {
    jprm_debug_section_hit([
        'file'        => __FILE__,
        'line'        => __LINE__,
        'section_id'  => 0,
        'layout'      => 'matrix',
        'placeholder' => '',
        'separator'   => '',
        'note'        => 'matrix template loaded',
    ]);
}

/**
 * Matrix  template (per-section grid)
 * Fixes:
 *  - No "Item" word in the header (first header cell blank).
 *  - Matrix header shows icons/text per label_presentation (sanitized to one icon).
 *  - Placeholder reliably shown for empty cells; updates with control; empty columns auto-hidden.
 */

/* ------------- Local helpers (self-contained) ----------------- */

/** Extract only the first <img> or <svg> from a snippet (avoid dumping sprite blocks). */
if ( ! function_exists( 'jprm_sanitize_single_icon' ) ) {
	function jprm_sanitize_single_icon( string $html ) : string {
		$html = trim( $html );
		if ( $html === '' ) return '';
		if ( preg_match( '~<img\b[^>]*>~is', $html, $m ) ) return $m[0];
		if ( preg_match( '~<svg\b[^>]*>.*?</svg>~is', $html, $m ) ) return $m[0];
		return '';
	}
}

/** Build a header cell (icon/text) according to presentation. */
function jprm_matrix_header_cell( array $meta, string $presentation ) : string {
	$text = trim( (string) ( $meta['text'] ?? '' ) );
	$ico  = '';
	// Try icon_html (already sanitized by dispatcher) and sanitize again locally for safety.
	if ( ! empty( $meta['icon_html'] ) ) $ico = jprm_sanitize_single_icon( (string) $meta['icon_html'] );
	// Fallbacks if templates were passed raw data (rare):
	if ( $ico === '' && ! empty( $meta['icon'] ) )     $ico = jprm_sanitize_single_icon( (string) $meta['icon'] );
	if ( $ico === '' && ! empty( $meta['svg'] ) )      $ico = jprm_sanitize_single_icon( (string) $meta['svg'] );
	if ( $ico === '' && ! empty( $meta['icon_url'] ) ) $ico = '<img class="jp-label__icon" src="' . esc_url( (string)$meta['icon_url'] ) . '" alt="" loading="lazy" decoding="async" />';

	switch ( $presentation ) {
		case 'icon':
			return $ico !== '' ? $ico : esc_html( $text );
		case 'text':
			return esc_html( $text );
		case 'icon_text':
		default:
			if ( $ico !== '' && $text !== '' ) {
				return '<span class="jp-menu__label">' . $ico . '<span>' . esc_html( $text ) . '</span></span>';
			}
			return $ico !== '' ? $ico : esc_html( $text );
	}
}

/** Build ordered columns (seed from label_map; extend if rows use text-only labels). */
function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
	$cols = [];
	// Seed to keep header order stable
	foreach ( $label_map as $lid => $meta ) {
		$cols[(string)$lid] = [
			'text'      => (string) ( $meta['title'] ?? ( $meta['text'] ?? '' ) ),
			'icon_html' => (string) ( $meta['icon_html'] ?? '' ),
			'_seed'     => true,
		];
	}
	// Extend with text-only labels found in data
	foreach ( $items as $post ) {
		$pid  = (int) $post->ID;
		$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
		foreach ( $rows as $r ) {
			$lid = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
			$txt = (string) ( $r['label_text'] ?? '' );
			$key = $lid > 0 ? (string) $lid : ( $txt !== '' ? 't:' . md5( $txt ) : '' );
			if ( $key !== '' && ! isset( $cols[$key] ) ) {
				$cols[$key] = [
					'text'      => $txt,
					'icon_html' => (string) ( $r['icon_html'] ?? '' ),
				];
			}
		}
	}
	return $cols;
}

/** Find formatted price for a given column key from rows. */
function jprm_matrix_find_cell( array $rows, string $col_key ) : ?string {
	foreach ( $rows as $r ) {
		$lid = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
		$txt = (string) ( $r['label_text'] ?? '' );
		$key = $lid > 0 ? (string) $lid : ( $txt !== '' ? 't:' . md5( $txt ) : '' );
		if ( $key === $col_key ) {
			$fmt = (string) ( $r['formatted'] ?? '' );
			return $fmt !== '' ? $fmt : null;
		}
	}
	return null;
}

/** Keep only columns that have at least one price across all items. */
function jprm_matrix_filter_active_columns( array $items, array $col_keys, array $label_map, array $currency_opts ) : array {
	$active = [];
	foreach ( $col_keys as $k ) {
		$has_any = false;
		foreach ( $items as $post ) {
			$pid  = (int) $post->ID;
			$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
			if ( jprm_matrix_find_cell( $rows, $k ) !== null ) { $has_any = true; break; }
		}
		if ( $has_any ) $active[] = $k;
	}
	return $active;
}

/* ------------- Context ----------------- */

$menu_term               = $ctx['menu_term'] ?? null;
$show_menu_title         = ! empty( $ctx['show_menu_title'] );
$show_menu_desc          = ! empty( $ctx['show_menu_desc'] );
$menu_pos                = $ctx['menu_pos'] ?? 'above_menu';

$sections_order          = $ctx['sections_order'] ?? [];
$sections_data           = $ctx['sections_data'] ?? [];

$show_section_name       = ! empty( $ctx['show_section_name'] );
$show_section_desc       = ! empty( $ctx['show_section_desc'] );

$label_presentation      = (string) ( $ctx['label_presentation'] ?? 'icon_text' ); // used for header icon/text mode
$label_map               = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts           = $ctx['currency_opts'] ?? [];

$matrix_placeholder = isset($ctx['labels_matrix_placeholder'])
    ? trim( html_entity_decode( (string) $ctx['labels_matrix_placeholder'], ENT_QUOTES ) )
    : '';
$ib_map                  = $ctx['ib_map'] ?? [];

/* ------------- Top meta ----------------- */

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* ------------- Sections ----------------- */

echo '<ul class="jp-menu__matrix">';

foreach ( $sections_order as $tid ) {
	if ( ! isset( $sections_data[ $tid ] ) ) continue;
	$blk = $sections_data[ $tid ];
	$term  = $blk['term']  ?? null;
	$items = $blk['items'] ?? [];
    // Effective placeholder for this section: override → global
    $matrix_placeholder = function_exists('jprm_effective_matrix_placeholder')
    ? jprm_effective_matrix_placeholder( $ctx, (int) $tid )
    : ( isset($ctx['labels_matrix_placeholder']) ? trim( (string) $ctx['labels_matrix_placeholder'] ) : '' );
    // DEBUG: show effective section values in HTML comments and error_log
echo "\n<!-- JP-MATRIX SEC " . (int)$tid . " PLACEHOLDER: " . esc_html( $matrix_placeholder ) . " -->\n";
if ( function_exists('error_log') ) {
    @error_log( sprintf('JP MATRIX SEC %d PLACEHOLDER="%s"', (int)$tid, $matrix_placeholder ) );
}

    // DEBUG: record effective values for this section
    if ( function_exists('jprm_debug_section_hit') ) {
        $eff_layout = isset($ctx['section_layouts'][$tid]) ? (string)$ctx['section_layouts'][$tid] : (string)($ctx['global_labels_layout'] ?? 'inline');
        jprm_debug_section_hit([
            'file'        => __FILE__,
            'line'        => __LINE__,
            'section_id'  => (int)$tid,
            'layout'      => $eff_layout,
            'placeholder' => $matrix_placeholder,
            'separator'   => '',
            'note'        => 'matrix header/rows will use this placeholder for empty cells',
        ]);
    }

	// ABOVE Info Blocks
	if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ) .'</li>'; // phpcs:ignore
	}

	// Section header
	if ( $term && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $term->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
		}
		echo '</li>';
	}

	if ( empty( $items ) ) continue;

	// Columns → then keep only columns that actually have at least one price
	$cols      = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
	$col_keys  = jprm_matrix_filter_active_columns( $items, array_keys( $cols ), $label_map, $currency_opts );
	$col_count = max( 1, count( $col_keys ) );

	// Grid container
	echo '<li class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

	// HEADER (first cell blank; no "Item" word)
	echo '<div class="jp-matrix__row">';
	echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item"></div>';
	foreach ( $col_keys as $k ) {
		$meta = [
			'text'      => isset( $cols[$k]['text'] ) ? (string) $cols[$k]['text'] : (string) $k,
			'icon_html' => isset( $cols[$k]['icon_html'] ) ? (string) $cols[$k]['icon_html'] : '',
		];
		echo '<div class="jp-matrix__cell jp-matrix__cell--head" data-label-key="' . esc_attr($k) . '">'
			. jprm_matrix_header_cell( $meta, $label_presentation )
			. '</div>';
	}
	echo '</div>';

	// ROWS
	foreach ( $items as $post ) {
		$pid   = (int) $post->ID;
		$title = get_the_title( $pid );
		$desc  = get_post_meta( $pid, 'jprm_desc', true );
		$rows  = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];

		echo '<div class="jp-matrix__row" data-post-id="' . esc_attr((string)$pid) . '">';

		echo '<div class="jp-matrix__cell jp-matrix__cell--item">';
		if ( $title !== '' ) echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
		if ( is_string( $desc ) && $desc !== '' ) echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
		echo '</div>';

		foreach ( $col_keys as $k ) {
			$val = $rows ? jprm_matrix_find_cell( $rows, $k ) : null;
			if ( $val === null || $val === '' ) {
                /*PLACEHOLDER DEBUG*/
                if ( function_exists( 'jprm_debug_placeholder_hit' ) ) { jprm_debug_placeholder_hit( __FILE__, __LINE__, $matrix_placeholder, $k, $pid ); }

				$val = $matrix_placeholder !== '' ? '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>' : '';
			}
			echo '<div class="jp-matrix__cell jp-matrix__cell--value" data-label-key="' . esc_attr($k) . '">' . $val . '</div>'; // phpcs:ignore
		}

		echo '</div>'; // row
	}

	echo '</li>'; // .jp-matrix

	// BELOW Info Blocks
	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ) .'</li>'; // phpcs:ignore
	}
}
echo '</ul>';
