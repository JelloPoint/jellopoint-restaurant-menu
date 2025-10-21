<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Menu meta (shared) */
if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
  function jprm_render_menu_meta( $term, bool $show_title, bool $show_desc, string $scope ) : string {
    if ( ! $term || ( ! $show_title && ! $show_desc ) ) return '';
    $title = $show_title ? trim( (string) $term->name ) : '';
    $desc  = $show_desc  ? trim( (string) $term->description ) : '';
    if ( $title === '' && $desc === '' ) return '';
    $cls = 'jp-menu__meta ' . ( $scope === 'global' ? 'jp-menu__meta--global' : 'jp-menu__meta--col' );
    $out  = '<div class="' . esc_attr( $cls ) . '">';
    if ( $title !== '' ) $out .= '<h2 class="jp-menu__meta-title">' . esc_html( $title ) . '</h2>';
    if ( $desc  !== '' ) $out .= '<div class="jp-menu__meta-desc">' . esc_html( $desc ) . '</div>';
    $out .= '</div>';
    return $out;
  }
}

/** Collect label columns for Matrix */
function jprm_section_collect_label_columns( array $items, ?array $label_map, array $currency_opts ) : array {
  $cols = [];
  foreach ( $items as $post ) {
    $pid   = (int) $post->ID;
    $rows  = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $pid, $label_map, $currency_opts ) : [];
    foreach ( $rows as $r ) {
      $lid = (int) ( $r['label_id'] ?? 0 );
      if ( $lid <= 0 && empty( $r['label_text'] ) ) continue;
      if ( $lid <= 0 ) $lid = crc32( 't:' . (string) $r['label_text'] );
      if ( ! isset( $cols[ $lid ] ) ) {
        $cols[ $lid ] = [
          'text'      => (string) ( $r['label_text'] ?? '' ),
          'icon_html' => (string) ( $r['icon_html']  ?? '' ),
        ];
        if ( $label_map && isset( $label_map[$lid] ) && is_array( $label_map[$lid] ) ) {
          if ( isset( $label_map[$lid]['text'] ) && $label_map[$lid]['text'] !== '' )
            $cols[$lid]['text'] = (string) $label_map[$lid]['text'];
          if ( isset( $label_map[$lid]['icon_html'] ) && $label_map[$lid]['icon_html'] !== '' )
            $cols[$lid]['icon_html'] = (string) $label_map[$lid]['icon_html'];
        }
      }
    }
  }
  return $cols;
}

function jprm_label_header_cell( array $l, string $presentation ) : string {
  $text = trim( (string) ( $l['text'] ?? '' ) );
  $ico  = (string) ( $l['icon_html'] ?? '' );
  if ( $presentation === 'icon' && $ico !== '' ) return '<span class="jp-lhdr-ico">'.$ico.'</span>';
  if ( $presentation === 'text' || $ico === '' ) return '<span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
  return '<span class="jp-lhdr-ico">'.$ico.'</span><span class="jp-lhdr-text">'.esc_html( $text ).'</span>';
}

function jprm_item_value_for_label( int $post_id, int $lid, ?array $label_map, array $currency_opts ) : ?string {
  $rows = function_exists( 'jprm_get_pricegroup_data' ) ? jprm_get_pricegroup_data( $post_id, $label_map, $currency_opts ) : [];
  if ( empty( $rows ) ) return null;
  foreach ( $rows as $r ) {
    if ( (int)($r['label_id'] ?? 0) === $lid ) {
      $fmt = (string) ( $r['formatted'] ?? '' );
      return $fmt !== '' ? $fmt : null;
    }
  }
  return null;
}
