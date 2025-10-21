<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix template (per-section table)
 * Builds a simple table: first column is item title/desc, subsequent columns are label columns.
 * Expects $ctx with:
 *  - sections_order, sections_data
 *  - label_map: ordered map of label keys => label meta (title, icon, etc.)
 *  - currency_opts
 *  - labels_matrix_placeholder (optional)
 */

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
$matrix_placeholder      = (string) ($ctx['labels_matrix_placeholder'] ?? '—');

$ib_map                  = $ctx['ib_map'] ?? [];

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

// Build an ordered list of label keys for header/columns
$label_keys = array_keys( $label_map );

echo '<ul class="jp-menu jp-menu--matrix">';
foreach ( $sections_order as $tid ) {
	if ( ! isset( $sections_data[ $tid ] ) ) continue;
	$blk = $sections_data[ $tid ];

	// ABOVE Info Blocks
	if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
		echo '</li>';
	}

	// Section header
	if ( ! empty( $blk['term'] ) && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $blk['term']->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $blk['term']->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $blk['term']->description ) . '</div>';
		}
		echo '</li>';
	}

	// Section table
	echo '<li class="jp-matrix">';
	echo '<div class="jp-matrix__table" role="table">';

	// Header
	echo '<div class="jp-matrix__row jp-matrix__row--head" role="row">';
	echo '<div class="jp-matrix__cell jp-matrix__cell--head jp-matrix__cell--item" role="columnheader">' . esc_html__( 'Item', 'jellopoint-restaurant-menu' ) . '</div>';
	foreach ( $label_keys as $key ) {
		$label_title = isset( $label_map[$key]['title'] ) ? (string) $label_map[$key]['title'] : (string) $key;
		echo '<div class="jp-matrix__cell jp-matrix__cell--head" role="columnheader">' . esc_html( $label_title ) . '</div>';
	}
	echo '</div>';

	// Rows
	if ( ! empty( $blk['items'] ) && is_array( $blk['items'] ) ) {
		foreach ( $blk['items'] as $post ) {
			$pid   = (int) $post->ID;
			$title = get_the_title( $pid );
			$desc  = get_post_meta( $pid, 'jprm_desc', true );

			echo '<div class="jp-matrix__row" role="row">';

			// Item cell (title + desc)
			echo '<div class="jp-matrix__cell jp-matrix__cell--item" role="cell">';
			if ( $title !== '' ) {
				echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
			}
			if ( is_string( $desc ) && $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}
			echo '</div>';

			// Price cells per label
			foreach ( $label_keys as $key ) {
				$price_html = '';
				if ( function_exists( 'jprm_get_price_by_label_key' ) ) {
					// If your codebase has a helper to fetch one price by label key
					$price_html = (string) jprm_get_price_by_label_key( $pid, $key, $currency_opts );
				}

				// Fallback: try to reuse the inline renderer and extract the matching row
				if ( $price_html === '' && function_exists( 'jprm_render_pricegroup_html' ) ) {
					$tmp = (string) jprm_render_pricegroup_html( $pid, 'icon_text', 'right', $label_map, $currency_opts );
					// crude extraction of the matching label row by a data-key marker if present
					// if no marker exists, we display placeholder
					$price_html = '';
					if ( $tmp !== '' ) {
						// Optional: developers can add data-label-key attributes in their row markup to make this robust.
						if ( preg_match('~(<div[^>]+class="[^"]*jp-menu__row[^"]*"[^>]*data-label-key="' . preg_quote($key, '~') . '"[^>]*>.*?</div>)~is', $tmp, $m) ) {
							$price_html = $m[1];
						}
					}
				}

				if ( $price_html === '' ) {
					$price_html = '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>';
				}

				echo '<div class="jp-matrix__cell" role="cell">' . $price_html . '</div>'; // phpcs:ignore
			}

			echo '</div>'; // .jp-matrix__row
		}
	}

	echo '</div>'; // .jp-matrix__table
	echo '</li>';  // .jp-matrix

	// BELOW Info Blocks
	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
		echo '</li>';
	}
}
echo '</ul>';
