<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * JPRM icon helpers – make SVGs colorable without clutter.
 *
 * - Inline <svg>  → strip fill/stroke/style overrides, add .jp-svg
 * - <img src="…svg"> or URL .svg → return a mask <span> that uses currentColor
 * - Raster images → keep <img>
 */

/** Create a mask <span> for an SVG URL (color via currentColor). */
if ( ! function_exists('jprm_svg_mask_span') ) {
    function jprm_svg_mask_span( string $url, string $extra_class = '' ) : string {
        $url = esc_url( $url );
        $cls = 'jp-icon-mask' . ( $extra_class ? ' ' . $extra_class : '' );
        return '<span class="' . esc_attr($cls) . '" style="-webkit-mask-image:url(\''.$url.'\');mask-image:url(\''.$url.'\');" aria-hidden="true"></span>';
    }
}

/**
 * Normalize arbitrary icon markup/URL to a colorable element.
 *
 * @param string      $html   Raw HTML for icon (can be <svg> or <img>)
 * @param string|null $url    Optional URL (e.g., icon_url) if $html is empty
 * @param string      $role   Either 'badge' or 'label' → applies role classes
 * @return string             Safe HTML – mask <span>, cleaned <svg>, or <img>
 */
if ( ! function_exists('jprm_colorize_icon') ) {
    function jprm_colorize_icon( string $html, ?string $url = null, string $role = 'label' ) : string {
        $html = trim($html);
        $role = ($role === 'badge') ? 'badge' : 'label';

        // A) Inline <svg>: strip fill/stroke/inline styles, tag with role class
        if ( $html !== '' && preg_match('~<svg\b~i', $html) ) {
            $html = preg_replace('~\s+(fill|stroke)=("|\')(.*?)\2~i', '', $html);
            $html = preg_replace('~\s+style=("|\')(?:[^"\']*?\b(fill|stroke)\s*:\s*[^;"]*;?[^"\']*)+\1~i', '', $html);
            $class_to_add = 'jp-'.$role.'__svg';
            if ( strpos($html, 'class=') === false ) {
                $html = preg_replace('~<svg\b~i', '<svg class="'.$class_to_add.'"', $html, 1);
            } else {
                $html = preg_replace('~<svg\b([^>]*)class=("|\')~i', '<svg$1class=$2'.$class_to_add.' ', $html, 1);
            }
            return $html;
        }

        // B) <img src="...svg"> → mask
        if ( $html !== '' && preg_match('~<img\b[^>]*src=("|\')(.*?\.svg(?:\?.*?)?)\1~i', $html, $m) ) {
            return jprm_svg_mask_span( $m[2], 'jp-'.$role.'__icon jp-'.$role.'__icon--mask' );
        }

        // C) URL provided (icon_url)
        if ( $html === '' && is_string($url) && $url !== '' ) {
            if ( preg_match('~\.svg(\?.*)?$~i', $url) || strpos($url, 'data:image/svg+xml') === 0 ) {
                return jprm_svg_mask_span( $url, 'jp-'.$role.'__icon jp-'.$role.'__icon--mask' );
            }
            return '<img class="jp-'.$role.'__icon" src="'. esc_url($url) .'" alt="" loading="lazy" decoding="async" />';
        }

        // D) Nothing to do or raster <img> already in $html
        return $html;
    }
}
