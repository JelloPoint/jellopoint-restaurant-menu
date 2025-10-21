<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix template (per-section grid)
 * - Header is TEXT ONLY (no icons)
 * - Columns with no prices for ANY item are auto-hidden
 * - Placeholder uses ONLY $ctx['labels_matrix_placeholder'] (no fallback)
 */

/** Build ordered columns (seed from label_map; extend if rows use text-only labels). */
function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
	$cols = [];
	// Seed to keep header order stable
	foreach ( $label_map as $lid => $meta ) {
		$cols[(string)$lid] = [
			'text'  => (string) ($meta['title'] ?? ($meta['text'] ?? '')),
			'_seed' => true,
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
				$cols[$key] = [ 'text' => $txt ];
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

/** Column visibility: keep only columns that have at least one price among all items. */
function jprm_matrix_filter_active_columns( array $items, array $col_keys, array $label_map, array $currency_opts ) : array {
	$active = [];
	foreach ( $col_keys as $k ) {
		$has_any = false;
		foreach ( $items as $post ) {
			$pid  = (int) $post->ID;
			$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
			if ( jprm_matrix_find_cell( $rows, $k ) !== null ) { $has_any = true; break; }
		}
		if ( $has_any ) { $active[] = $k; }
	}
	return $active;
}

/* ---------- Context ---------- */

$menu_term               = $ctx['menu_term'] ?? null;
$show_menu_title         = ! empty( $ctx['show_menu_title'] );
$show_menu_desc          = ! empty( $ctx['show_menu_desc'] );
$menu_pos                = $ctx['menu_pos'] ?? 'above_menu';

$sections_order          = $ctx['sections_order'] ?? [];
$sections_data           = $ctx['sections_data'] ?? [];

$show_section_name       = ! empty( $ctx['show_section_name'] );
$show_section_desc       = ! empty( $ctx['show_section_desc'] );

$label_map               = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts           = $ctx['currency_opts'] ?? [];

// Use ONLY the control value; decode entities; no fallback.
$matrix_placeholder      = '';
if ( array_key_exists( 'labels_matrix_placeholder', $ctx ) ) {
	$raw = (string) $ctx['labels_matrix_placeholder'];
	$matrix_placeholder = trim( html_entity_decode( $raw, ENT_QUOTES ) );
}

$ib_map                  = $ctx['ib_map'] ?? [];

/* ---------- Top meta ---------- */

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* ---------- Sections ---------- */

echo '<ul class="jp-menu__matrix">';

foreach ( $sections_order as $tid ) {
	if ( ! isset( $sections_data[ $tid ] ) ) continue;
	$blk = $sections_data[ $tid ];
	$term  = $blk['term']  ?? null;
	$items = $blk['items'] ?? [];

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

	// Columns (build → then drop columns with no prices at all)
	$cols      = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
	$col_keys  = array_keys( $cols );
	$col_keys  = jprm_matrix_filter_active_columns( $items, $col_keys, $label_map, $currency_opts );
	$col_count = max( 1, count( $col_keys ) );

	// Grid container
	echo '<li class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

	// HEADER (TEXT ONLY)
	echo '<div class="jp-matrix__row">';
	echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item">' . esc_html__( 'Item', 'jellopoint-restaurant-menu' ) . '</div>';
	foreach ( $col_keys as $k ) {
		$label_text = isset( $cols[$k]['text'] ) ? (string) $cols[$k]['text'] : (string) $k;
		echo '<div class="jp-matrix__cell jp-matrix__cell--head" data-label-key="' . esc_attr($k) . '">' . esc_html( $label_text ) . '</div>';
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
				$val = $matrix_placeholder !== '' ? '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>' : '';
			}
			echo '<div class="jp-matrix__cell jp-matrix__cell--value" data-label-key="' . esc_attr($k) . '">' . $val . '</div>'; // phpcs:ignore
		}

		echo '</div>'; // row
	}

	echo '</li>'; // grid

	// BELOW Info Blocks
	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">'. jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ) .'</li>'; // phpcs:ignore
	}
}
echo '</ul>';
