<?php
// (Optional safety): if helper not loaded, we can still call the global value
$__has_helper = function_exists('jprm_effective_inline_below_separator');
if ( function_exists('jprm_debug_section_hit') ) {
    jprm_debug_section_hit([
        'file'        => __FILE__,
        'line'        => __LINE__,
        'section_id'  => 0,
        'layout'      => 'inline_below',
        'placeholder' => '',
        'separator'   => '',
        'note'        => 'inline-below template loaded',
    ]);
}


if ( ! defined( 'ABSPATH' ) ) { exit; }

$menu_term           = $ctx['menu_term'] ?? null;
$show_menu_title     = ! empty( $ctx['show_menu_title'] );
$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

$sections_order      = $ctx['sections_order'] ?? [];
$sections_data       = $ctx['sections_data'] ?? [];

$show_section_name   = ! empty( $ctx['show_section_name'] );
$show_section_desc   = ! empty( $ctx['show_section_desc'] );

$show_badges         = ! empty( $ctx['show_badges'] );
$badges_presentation = (string) ($ctx['badges_presentation'] ?? 'icon_text');
$badges_position     = (string) ($ctx['badges_position'] ?? 'after_title');

$label_presentation  = (string) ($ctx['label_presentation'] ?? 'icon_text');
$label_position      = (string) ($ctx['label_position'] ?? 'right');
$label_map           = is_array( $ctx['label_map'] ?? null ) ? $ctx['label_map'] : [];
$currency_opts       = $ctx['currency_opts'] ?? [];

$ib_map              = $ctx['ib_map'] ?? [];

// Step 1: no user-configured separator yet.
$sep_text = '';

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
	echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

echo '<ul class="jp-menu">';
foreach ( $sections_order as $tid ) {
	if ( ! isset( $sections_data[ $tid ] ) ) continue;
	$blk = $sections_data[ $tid ];
    // Effective separator for this section (override → global)
    $inline_below_separator = $__has_helper
        ? jprm_effective_inline_below_separator( $ctx, (int)$tid )
        : ( isset($ctx['inline_below_separator']) ? (string)$ctx['inline_below_separator'] : '' );

    // DEBUG: record effective values for this section
    if ( function_exists('jprm_debug_section_hit') ) {
        $eff_layout = isset($ctx['section_layouts'][$tid]) ? (string)$ctx['section_layouts'][$tid] : (string)($ctx['global_labels_layout'] ?? 'inline');
        jprm_debug_section_hit([
            'file'        => __FILE__,
            'line'        => __LINE__,
            'section_id'  => (int)$tid,
            'layout'      => $eff_layout,
            'placeholder' => '',
            'separator'   => $inline_below_separator,
            'note'        => 'inline-below will use this separator between label and price',
        ]);
    }

	if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
		echo '</li>';
	}

	if ( ! empty( $blk['term'] ) && $show_section_name ) {
		echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $blk['term']->name ) . '</h3>';
		if ( $show_section_desc && ! empty( $blk['term']->description ) ) {
			echo '<div class="jp-section__desc">' . esc_html( $blk['term']->description ) . '</div>';
		}
		echo '</li>';
	}

	if ( ! empty( $blk['items'] ) && is_array( $blk['items'] ) ) {
		foreach ( $blk['items'] as $post ) {
			$pid   = (int) $post->ID;
			$title = get_the_title( $pid );
			$desc  = get_post_meta( $pid, 'jprm_desc', true );

			echo '<li class="jp-menu__item"><div class="jp-menu__inner jp--inline-below">';

			echo '<div class="jp-menu__content">';
			echo '<div class="jp-menu__titleline">';
			if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
				echo jprm_render_badges_inline_html( $pid, $badges_presentation ); // phpcs:ignore
			}
			if ( $title !== '' ) echo '<h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
			if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
				echo jprm_render_badges_inline_html( $pid, $badges_presentation ); // phpcs:ignore
			}
			echo '</div>';
			if ( is_string( $desc ) && $desc !== '' ) {
				echo '<div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
			}
			echo '</div>';

			$html = jprm_render_pricegroup_inline_ctx( $pid, $label_presentation, $label_position, $label_map, $currency_opts );

			// (Separator injection reserved for Step 2)
			echo '<div class="jp-menu__pricegroup jp--presentation-' . esc_attr( $label_presentation ) . '">';
			echo $html; // phpcs:ignore
			echo '</div>';

			echo '</div></li>';
		}
	}

	if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
		echo '<li class="jp-menu__infoblock-li">';
		echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
		echo '</li>';
	}
}
echo '</ul>';
