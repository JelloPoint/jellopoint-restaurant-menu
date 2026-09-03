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

		$repeater_class = ! empty( $row['_id'] ) ? ' elementor-repeater-item-' . sanitize_html_class( (string) $row['_id'] ) : '';
		$block_styles = [];
		$content_styles = [];
		$image_styles = [];
		if ( ! empty( $row['block_text_color'] ) && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)|[a-z]+)$/i', (string) $row['block_text_color'] ) ) {
			$content_styles[] = 'color:' . (string) $row['block_text_color'];
		}
		if ( ! empty( $row['block_bg_color'] ) && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)|[a-z]+)$/i', (string) $row['block_bg_color'] ) ) {
			$block_styles[] = 'background-color:' . (string) $row['block_bg_color'];
		}
		if ( in_array( (string) ( $row['block_alignment'] ?? '' ), [ 'left', 'center', 'right' ], true ) ) {
			$block_styles[] = 'text-align:' . (string) $row['block_alignment'];
		}
		foreach ( [ 'block_font_size' => [ &$content_styles, 'font-size' ], 'block_image_size' => [ &$image_styles, 'width' ] ] as $setting => &$target ) {
			$value = is_array( $row[ $setting ] ?? null ) ? $row[ $setting ] : [];
			$size  = isset( $value['size'] ) && is_numeric( $value['size'] ) ? (float) $value['size'] : null;
			$unit  = in_array( (string) ( $value['unit'] ?? '' ), [ 'px', 'em', 'rem', '%' ], true ) ? (string) $value['unit'] : '';
			if ( null !== $size && '' !== $unit ) { $target[0][] = $target[1] . ':' . $size . $unit; }
		}
		unset( $target );
		$style_attr = $block_styles ? ' style="' . esc_attr( implode( ';', $block_styles ) ) . '"' : '';
		$content_style_attr = $content_styles ? ' style="' . esc_attr( implode( ';', $content_styles ) ) . '"' : '';
		$image_style_attr = $image_styles ? ' style="' . esc_attr( implode( ';', $image_styles ) ) . '"' : '';
		echo '<div class="jprm-infoblock' . esc_attr( $repeater_class ) . '" data-position="' . esc_attr( $position ) . '"' . $style_attr . '>';

		if ( $img_url ) {
			$alt = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';
			echo '<div class="jprm-infoblock__image"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $alt ) . '"' . $image_style_attr . '></div>';
		}

		if ( $html !== '' ) {
			// Intentionally raw: this field is a deliberate HTML field in the widget.
			echo '<div class="jprm-infoblock__content"' . $content_style_attr . '>' . $html . '</div>';
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
