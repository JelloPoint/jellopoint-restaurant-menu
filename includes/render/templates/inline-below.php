<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$columns             = '1'; // Inline Below is clearest in 1 col; expand later if desired
$menu_term           = $ctx['menu_term'] ?? null;
$show_menu_title     = ! empty( $ctx['show_menu_title'] );
$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

$sections_order      = $ctx['sections_order'] ?? [];
$sections_data       = $ctx['sections_data'] ?? [];

$show_section_name   = ! empty( $ctx['show_section_name'] );
$show_section_desc   = ! empty( $ctx['show_section_desc'] );

$show_badges         = ! empty( $ctx['show_badges'] );
$badges_presentation = $ctx['badges_presentation'] ?? 'icon_text';
$badges_position     = $ctx['badges_position'] ?? 'after_title';

$label_presentation  = $ctx['label_presentation'] ?? 'icon_text';
$label_position      = $ctx['label_position'] ?? 'right';
$label_map           = $ctx['label_map'] ?? null;
$currency_opts       = $ctx['currency_opts'] ?? [];

$ib_map              = $ctx['ib_map'] ?? [];
$sep_text            = (string)($ctx['inline_below_sep_content'] ?? ''); // empty => none

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
  echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

echo '<ul class="jp-menu">';
foreach ( $sections_order as $tid ) {
  $blk = $sections_data[$tid];
  if ( isset($ib_map[$tid]['above']) && !empty($ib_map[$tid]['above']) ) {
    echo '<li class="jp-menu__infoblock-li">'.jprm_infoblocks_render_group($ib_map[$tid]['above'],'above').'</li>'; // phpcs:ignore
  }
  if ( $blk['term'] && $show_section_name ) {
    echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">'.esc_html($blk['term']->name).'</h3>';
    if ( $show_section_desc && !empty($blk['term']->description) ) echo '<div class="jp-section__desc">'.esc_html($blk['term']->description).'</div>';
    echo '</li>';
  }

  foreach ( $blk['items'] as $post ) {
    $pid   = (int) $post->ID;
    $title = get_the_title( $pid );
    $desc  = get_post_meta( $pid, 'jprm_desc', true );

    echo '<li class="jp-menu__item"><div class="jp-menu__inner jp--inline-below">';
    echo '<div class="jp-menu__content"><div class="jp-menu__titleline">';
    if ( $show_badges && $badges_position === 'before_title' && function_exists('jprm_render_badges_inline_html') ) echo jprm_render_badges_inline_html($pid,$badges_presentation); // phpcs:ignore
    if ( $title !== '' ) echo '<h4 class="jp-menu__title">'.esc_html($title).'</h4>';
    if ( $show_badges && $badges_position === 'after_title'  && function_exists('jprm_render_badges_inline_html') ) echo jprm_render_badges_inline_html($pid,$badges_presentation); // phpcs:ignore
    echo '</div>';
    if ( is_string($desc) && $desc !== '' ) echo '<div class="jp-menu__desc">'.esc_html($desc).'</div>';
    echo '</div>';

    $html = function_exists('jprm_render_pricegroup_html')
      ? (string) jprm_render_pricegroup_html($pid,$label_presentation,$label_position,$label_map,$currency_opts)
      : '';

    // Convert rows into chips; inject separators literally only if $sep_text non-empty
    if ( $sep_text !== '' ) {
      $sep = '<span class="jp-chip-sep" aria-hidden="true"> '.$sep_text.' </span>';
      $html = preg_replace('~</div>\s*(?=<div[^>]*\bjp-menu__row\b)~i', '</div>'.$sep, $html);
    }

    echo '<div class="jp-menu__pricegroup">';
    echo $html; // phpcs:ignore
    echo '</div>';

    echo '</div></li>';
  }

  if ( isset($ib_map[$tid]['below']) && !empty($ib_map[$tid]['below']) ) {
    echo '<li class="jp-menu__infoblock-li">'.jprm_infoblocks_render_group($ib_map[$tid]['below'],'below').'</li>'; // phpcs:ignore
  }
}
echo '</ul>';
