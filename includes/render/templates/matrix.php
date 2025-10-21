<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix template (per-section grid)
 * Relies on normalized $ctx from dispatcher and jprm_get_pricegroup_data().
 */

function jprm_matrix_collect_columns( array $items, array $label_map, array $currency_opts ) : array {
	$cols = [];

	// Seed columns from label_map to keep order stable
	foreach ( $label_map as $lid => $meta ) {
		$lid = (string) $lid;
		$cols[ $lid ] = [
			'text'      => (string) ( $meta['title'] ?? '' ),
			'icon_html' => (string) ( $meta['icon_html'] ?? '' ),
			'_seed'     => true,
		];
	}

	// Grow with text-only labels possibly used by items
	foreach ( $items as $post ) {
		$pid  = (int) $post->ID;
		$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
		foreach ( $rows as $r ) {
			$lid = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
			$txt = (string) ( $r['label_text'] ?? '' );
			$key = $lid > 0 ? (string) $lid : ( $txt !== '' ? 't:' . md5( $txt ) : '' );
			if ( $key === '' || isset( $cols[ $key ] ) ) continue;

			$cols[ $key ] = [
				'text'      => $txt,
				'icon_html' => (string) ( $r['icon_html'] ?? '' ),
			];
		}
	}

	return $cols;
}

function jprm_matrix_label_header( array $l, string $presentation ) : string {
	$text = trim( (string ) ( $l['text'] ?? '' ) );
	$ico  = (string) ( $l['icon_html'] ?? '' );
	switch ( $presentation ) {
		case 'icon':      return $ico !== '' ? $ico : esc_html( $text );
		case 'text':      return esc_html( $text );
		case 'icon_text':
		default:
			if ( $ico !== '' && $text !== '' ) {
				return '<span class="jp-menu__label">' . $ico . '<span>' . esc_html( $text ) . '</span></span>';
			}
			return $ico !== '' ? $ico : esc_html( $text );
	}
}

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

/* ---------- Context ---------- */

$menu_term               = $ctx['menu_term'] ?? null;
$show_menu_title         = ! empty( $ctx['show_menu_title'] );
$show_menu_desc          = ! empty( $ctx['show_menu_desc'] );
$menu_pos                = $ctx['menu_pos'] ?? 'above_menu';

$sections_order          = $ctx['sections_order'] ?? [];
$sections_data           = $ctx['sections_data'] ?? [];

$show_section_name       = ! empty( $ctx['show_section_name'] );
$show_section_desc       = ! empty( $ctx['show_section_desc'] );

$label_presentation      = (string) ( $ctx['label_presentation'] ?? 'icon_text' );
$label_map               = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts           = $ctx['currency_opts'] ?? [];

$matrix_placeholder      = (string) ( $ctx['labels_matrix_placeholder'] ?? '' );
$ib_map                  = $ctx['ib_map'] ?? [];

/* ---------- Global meta ---------- */

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
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
		echo '</li>';
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

	// Columns
	$cols      = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
	$col_keys  = array_keys( $cols );
	$col_count = max( 1, count( $col_keys ) );

	// Grid container
	echo '<li class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

	// Header row
	echo '<div class="jp-matrix__row">';
	echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item">' . esc_html__( 'Item', 'jellopoint-restaurant-menu' ) . '</div>';
	foreach ( $col_keys as $k ) {
		echo '<div class="jp-matrix__cell jp-matrix__cell--head">' . jprm_matrix_label_header( $cols[$k], $label_presentation ) . '</div>';
	}
	echo '</div>';

	// Data rows
	foreach ( $items as $post ) {
		$pid   = (int) $post->ID;
		$title = get_the_title( $pid );
		$desc  = get_post_meta( $pid, 'jprm_desc', true );
		$rows  = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];

		echo '<div class="jp-matrix__row">';

		echo '<div class="jp-matrix__cell jp-matrix__cell--item">';
		if ( $title !== '' ) echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
		if ( is_string( $desc ) && $desc !== '' ) echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
		echo '</div>';

		foreach ( $col_keys as $k ) {
			$val = $rows ? jprm_matrix_find_cell( $rows, $k ) : null;
			if ( $val === null || $val === '' ) {
				$val = $matrix_placeholder !== '' ? '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>' : '';
			}
			echo '<div class="jp-matrix__cell jp-matrix__cell--value">' . $val . '</div>'; // phpcs:ignore
		}

		echo '</div>'; // row
	}

	echo '</li>'; // .jp-matrix

	// BELOW Info Blocks
	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
		echo '</li>';
	}
}

echo '</ul>';
