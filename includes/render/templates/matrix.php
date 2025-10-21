<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Matrix template (per-section grid)
 * - Matches CSS selectors in menu.css: .jp-menu__matrix (UL), .jp-matrix (grid), .jp-matrix__row, .jp-matrix__cell
 * - Uses structured price rows via jprm_get_pricegroup_data() if available.
 * - Falls back to placeholder if a cell has no price for that label.
 *
 * Expects $ctx array with keys:
 *   - menu_term, show_menu_title, show_menu_desc, menu_pos
 *   - sections_order (array of term_ids), sections_data (term_id => [ 'term'=>WP_Term, 'items'=>array of WP_Post ])
 *   - show_section_name, show_section_desc
 *   - label_map (array), currency_opts (array)
 *   - labels_matrix_placeholder (string)
 *   - badges_presentation/position only affect headers minimally; item cells show title+desc only
 *   - ib_map (per-section Info Blocks)
 */

/* ---------- Small helpers (kept local to this template) ------------------ */

/**
 * Collect union of label columns used in a section.
 * Returns: [ label_key => [ 'text' => string, 'icon_html' => string ] ]
 * label_key: numeric label_id if available; otherwise a synthetic "t:<hash>" for text-only labels.
 */
function jprm_matrix_collect_columns( array $items, ?array $label_map, array $currency_opts ) : array {
	$cols = [];

	// Seed columns with label_map order (if provided) so headers are stable
	if ( is_array( $label_map ) ) {
		foreach ( $label_map as $lid => $meta ) {
			$lid = (int) $lid;
			$cols[ (string) $lid ] = [
				'text'      => (string) ( $meta['title']     ?? ( $meta['text'] ?? '' ) ),
				'icon_html' => (string) ( $meta['icon_html'] ?? '' ),
				'_seed'     => true, // marks seeded order
			];
		}
	}

	foreach ( $items as $post ) {
		$pid  = (int) $post->ID;
		$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
		if ( empty( $rows ) ) continue;

		foreach ( $rows as $r ) {
			$lid = isset( $r['label_id'] ) ? (int) $r['label_id'] : 0;
			$txt = (string) ( $r['label_text'] ?? '' );
			$key = $lid > 0 ? (string) $lid : ( $txt !== '' ? 't:' . md5( $txt ) : '' );
			if ( $key === '' ) continue;

			if ( ! isset( $cols[ $key ] ) ) {
				// Prefer label_map meta if we can map a numeric id
				if ( $lid > 0 && isset( $label_map[ $lid ] ) ) {
					$cols[ $key ] = [
						'text'      => (string) ( $label_map[ $lid ]['title']     ?? ( $label_map[ $lid ]['text'] ?? $txt ) ),
						'icon_html' => (string) ( $label_map[ $lid ]['icon_html'] ?? '' ),
					];
				} else {
					$cols[ $key ] = [
						'text'      => $txt,
						'icon_html' => (string) ( $r['icon_html'] ?? '' ),
					];
				}
			}
		}
	}

	return $cols;
}

/** Render label header cell according to presentation. */
function jprm_matrix_label_header( array $l, string $presentation ) : string {
	$text = trim( (string ) ( $l['text'] ?? '' ) );
	$ico  = (string) ( $l['icon_html'] ?? '' );

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

/** Find formatted price for a given column key from rows of jprm_get_pricegroup_data(). */
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

/* ---------- Read context ------------------------------------------------- */

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

$matrix_placeholder      = (string) ( $ctx['labels_matrix_placeholder'] ?? '—' );
$ib_map                  = $ctx['ib_map'] ?? [];

/* ---------- Top-level menu meta ----------------------------------------- */

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

/* ---------- Render sections as matrices --------------------------------- */

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

	if ( empty( $items ) ) {
		continue;
	}

	// Build columns and column order
	$cols = jprm_matrix_collect_columns( $items, $label_map, $currency_opts );
	$col_keys = array_keys( $cols );
	$col_count = max( 1, count( $col_keys ) );

	// Matrix grid (li itself is the grid container; CSS expects .jp-matrix)
	echo '<li class="jp-matrix" style="--jp-matrix-cols:' . esc_attr( (string) $col_count ) . '">';

	// Header row: "Item" + each label header according to presentation
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

		$rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];

		echo '<div class="jp-matrix__row">';

		// First column: item title + desc
		echo '<div class="jp-matrix__cell jp-matrix__cell--item">';
		if ( $title !== '' ) {
			echo '<div class="jp-menu__title">' . esc_html( $title ) . '</div>';
		}
		if ( is_string( $desc ) && $desc !== '' ) {
			echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
		}
		echo '</div>';

		// Value cells per label column
		foreach ( $col_keys as $k ) {
			$val = $rows ? jprm_matrix_find_cell( $rows, $k ) : null;
			if ( $val === null || $val === '' ) {
				$val = '<span class="jp-matrix__placeholder">' . esc_html( $matrix_placeholder ) . '</span>';
			}
			echo '<div class="jp-matrix__cell jp-matrix__cell--value">' . $val . '</div>'; // phpcs:ignore
		}

		echo '</div>'; // .jp-matrix__row
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
