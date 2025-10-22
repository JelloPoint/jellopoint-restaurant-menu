<?php
/**
 * JelloPoint Restaurant Menu — Info Blocks partial (Step 1)
 *
 * Provides helper functions for rendering simple Info Blocks
 * (HTML + Image only), positioned ABOVE/BELOW a target Section.
 *
 * Styling is intentionally omitted per Step 1.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Partition rows by ['section_id']['above'|'below'].
 *
 * @param array $rows
 * @return array
 */
function jprm_infoblocks_partition_by_position( array $rows ) : array {
	$map = [];
	foreach ( $rows as $row ) {
		$section_id = isset( $row['section_id'] ) ? (int) $row['section_id'] : 0;
		$pos = isset( $row['position'] ) && in_array( $row['position'], [ 'above', 'below' ], true )
			? $row['position']
			: 'above';

		if ( $section_id <= 0 ) {
			continue;
		}
		if ( ! isset( $map[ $section_id ] ) ) {
			$map[ $section_id ] = [ 'above' => [], 'below' => [] ];
		}
		$map[ $section_id ][ $pos ][] = $row;
	}
	return $map;
}

/**
 * Render a list of rows (for a given position) as raw HTML (no styling).
 *
 * @param array  $rows
 * @param string $position  'above'|'below' (informational only)
 * @return string
 */
function jprm_infoblocks_render_rows( array $rows, string $position ) : string {
	ob_start();

	foreach ( $rows as $row ) {
		$html  = isset( $row['content_html'] ) ? (string) $row['content_html'] : '';
		$image = ( isset( $row['image'] ) && is_array( $row['image'] ) ) ? $row['image'] : [];
		$img_id  = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$img_url = ! empty( $image['url'] )
			? $image['url']
			: ( $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '' );

		echo '<div class="jprm-infoblock" data-position="' . esc_attr( $position ) . '">';

		if ( $img_url ) {
			$alt = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
			echo '<div class="jprm-infoblock__image"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $alt ) . '"></div>';
		}

		if ( $html !== '' ) {
			// Intentionally raw: this field is a deliberate HTML field in the widget.
			echo '<div class="jprm-infoblock__content">' . $html . '</div>';
		}

		echo '</div>';
	}

	return ob_get_clean();
}

/**
 * Convenience wrapper to render a group for a given position.
 *
 * @param array  $rows
 * @param string $position
 * @return string
 */
function jprm_infoblocks_render_group( array $rows, string $position ) : string {
	return jprm_infoblocks_render_rows( $rows, $position );
}

/**
 * Return Sections belonging to a specific Menu (for control options, etc.).
 * Adjust this to your actual data model if different.
 *
 * @param int $menu_id
 * @return array [ term_id => "Section Name" ]
 */
function jprm_infoblocks_sections_for_menu( int $menu_id ) : array {
	if ( $menu_id <= 0 ) {
		return [];
	}

	// Option A: Sections carry a term meta 'jprm_menu_id' linking them to the Menu.
	$args = [
		'taxonomy'   => 'jprm_menu_section',
		'hide_empty' => false,
		'meta_query' => [
			[
				'key'   => 'jprm_menu_id',
				'value' => $menu_id,
			],
		],
	];

	// Option B (uncomment if your model uses parent terms to represent the menu):
	// $args = [
	// 	'taxonomy'   => 'jprm_menu_section',
	// 	'hide_empty' => false,
	// 	'parent'     => $menu_id,
	// ];

	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	$out = [];
	foreach ( $terms as $t ) {
		$out[ (int) $t->term_id ] = $t->name;
	}
	return $out;
}
