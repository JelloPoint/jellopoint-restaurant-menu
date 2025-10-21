<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$columns             = (string) ( $ctx['columns'] ?? '1' );
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
$section_layouts     = $ctx['section_layouts'] ?? [];
$global_placeholder  = $ctx['global_placeholder'] ?? '—';

if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
  echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
}

$open_list = function() { echo '<ul class="jp-menu">'; };
$close_list = function() { echo '</ul>'; };

$render_item = function( int $pid ) use ( $show_badges,$badges_position,$badges_presentation,$label_presentation,$label_position,$label_map,$currency_opts ) {
  $title = get_the_title( $pid );
  $desc  = get_post_meta( $pid, 'jprm_desc', true );

  echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
  echo '<div class="jp-menu__content"><div class="jp-menu__titleline">';
  if ( $show_badges && $badges_position === 'before_title' && function_exists('jprm_render_badges_inline_html') ) echo jprm_render_badges_inline_html($pid,$badges_presentation); // phpcs:ignore
  if ( $title !== '' ) echo '<h4 class="jp-menu__title">'.esc_html($title).'</h4>';
  if ( $show_badges && $badges_position === 'after_title'  && function_exists('jprm_render_badges_inline_html') ) echo jprm_render_badges_inline_html($pid,$badges_presentation); // phpcs:ignore
  echo '</div>';
  if ( is_string($desc) && $desc !== '' ) echo '<div class="jp-menu__desc">'.esc_html($desc).'</div>';
  echo '</div>';

  echo '<div class="jp-menu__pricegroup">';
  if ( function_exists('jprm_render_pricegroup_html') ) {
    echo jprm_render_pricegroup_html($pid, $label_presentation,$label_position,$label_map,$currency_opts); // phpcs:ignore
  }
  echo '</div>';

  echo '</div></li>';
};

if ( $columns === '1' ) {
  $open_list();
  if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
    echo '<li class="jp-menu__meta-li">'.jprm_render_menu_meta($menu_term,$show_menu_title,$show_menu_desc,'col').'</li>'; // phpcs:ignore
  }
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
    foreach ($blk['items'] as $post) { $render_item( (int)$post->ID ); }
    if ( isset($ib_map[$tid]['below']) && !empty($ib_map[$tid]['below']) ) {
      echo '<li class="jp-menu__infoblock-li">'.jprm_infoblocks_render_group($ib_map[$tid]['below'],'below').'</li>'; // phpcs:ignore
    }
  }
  $close_list();
  return;
}

/* If you need 2/3 columns in Inline, copy your previous, known-good splitter here.
   Keep it *only* for Inline. */
