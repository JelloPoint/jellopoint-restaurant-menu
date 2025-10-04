<?php
namespace JelloPoint\RestaurantMenu\Render;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Optional renderer helpers (currently not used by the widget).
 * Keep as a stable place for future shared rendering utilities.
 */
class Price_Renderer {

    public static function label_html( string $text, int $icon_id, string $presentation = 'icon_text', bool $hide_icon = false ) : string {
        $icon_html = '';
        if ( ! $hide_icon && $icon_id > 0 ) {
            $img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
            if ( is_string($img) ) $icon_html = $img;
        }
        if ( $presentation === 'icon' )      return $icon_html;
        if ( $presentation === 'text' )      return esc_html( $text );
        if ( $presentation === 'icon_text' ) return $icon_html ? ($icon_html.' '.esc_html($text)) : esc_html($text);
        return esc_html( $text );
    }
}
