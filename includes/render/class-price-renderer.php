<?php
/**
 * Price Renderer – turns canonical jprm_price (v3) into HTML rows.
 */
namespace JelloPoint\RestaurantMenu\Render;

use JelloPoint\RestaurantMenu\Storage\Price_Schema;

if ( ! defined('ABSPATH') ) exit;

class Price_Renderer {

    /**
     * Convenience: render directly from a post's v3 JSON meta (jprm_price).
     * Read-only. No fallbacks/migrations. Returns '' if invalid/missing.
     *
     * @param int   $post_id
     * @param array $opts Same as render_pricegroup()
     * @return string HTML (identical structure to render_pricegroup output)
     */
    public static function render_from_meta( int $post_id, array $opts = [] ) : string {
        $post_id = (int)$post_id;
        if ( $post_id <= 0 ) return '';

        $raw = get_post_meta( $post_id, 'jprm_price', true );
        if ( ! is_string( $raw ) || $raw === '' ) return '';

        $cfg = json_decode( $raw, true );
        if ( ! is_array( $cfg ) ) return '';

        // sanitize in render_pricegroup via Price_Schema::sanitize_cfg
        return self::render_pricegroup( $cfg, $opts );
    }

    /**
     * Render full <div class="jp-menu__pricegroup">…</div> for a v3 cfg.
     *
     * @param array $cfg Canonical schema (mode: single|multi)
     * @param array $opts [
     *   'presentation'   => 'text' | 'icon' | 'icon_text',
     *   'order_class'    => 'jp-order--label-left' | 'jp-order--label-right'
     * ]
     */
    public static function render_pricegroup( array $cfg, array $opts = [] ) : string {
        $cfg = Price_Schema::sanitize_cfg( $cfg ); // defensive

        $presentation = isset($opts['presentation']) && in_array($opts['presentation'], ['text','icon','icon_text'], true)
            ? $opts['presentation']
            : 'text';

        $order_class = isset($opts['order_class']) && is_string($opts['order_class'])
       ? $opts['order_class'] : 'jp-order--label-right';

        ob_start();
        echo '<div class="jp-menu__pricegroup">';

        if ( ($cfg['mode'] ?? '') === 'single' && ! empty($cfg['price']) ) {
            $price   = self::sanitize_price_string( $cfg['price'] );
            $ref     = (string)($cfg['label_ref'] ?? '');
            $icon_id = isset($cfg['icon_id']) ? (int)$cfg['icon_id'] : 0;
            $hide    = ! empty( $cfg['hide_icon'] );

            $res        = \JPRM_Labels_Store::resolve( $ref );
            $label_text = (string)($res['label_text'] ?? '');
            $icon_id    = $icon_id ?: (int)($res['icon_id'] ?? 0);

            echo self::row_html( $price, $label_text, $icon_id, $presentation, $order_class, $hide );
        }

        if ( ($cfg['mode'] ?? '') === 'multiple' && ! empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as $row ) {
                $price = self::sanitize_price_string( $row['price'] ?? '' );
           if ( $price === '' ) continue;

                $ref     = (string)($row['label_ref'] ?? '');
                $hide    = ! empty( $row['hide_icon'] );
                $icon_id = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;

                $res        = \JPRM_Labels_Store::resolve( $ref );
                $label_text = (string)($res['label_text'] ?? '');
                $icon_id    = $icon_id ?: (int)($res['icon_id'] ?? 0);

                echo self::row_html( $price, $label_text, $icon_id, $presentation, $order_class, $hide );
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render one row: label (text/icon/both) + price span.
     * Keeps exact classes/structure.
     */
    protected static function row_html( string $price, string $label_text, int $icon_id, string $presentation, string $order_class, bool $hide_icon ) : string {
        $label_markup = self::get_label_markup( $label_text, $icon_id, $presentation, $hide_icon );

        $price_html = '<span class="jp-menu__price">' . esc_html( $price ) . '</span>';

        // Order: left label then price, or reversed, but keep classes identical
        if ( $order_class === 'jp-order--label-left' ) {
            return '<div class="jp-menu__row ' . esc_attr( $order_class ) . '">'
                . '<span class="jp-menu__label">' . $label_markup . '</span>'
                . $price_html
                . '</div>';
        }

        // default/right
        return '<div class="jp-menu__row ' . esc_attr( $order_class ) . '">'
            . $price_html
            . '<span class="jp-menu__label">' . $label_markup . '</span>'
            . '</div>';
    }

    /**
     * Returns label markup according to presentation mode.
     * - 'text'      => plain escaped text
     * - 'icon'      => icon only (if available)
     * - 'icon_text' => icon + text (space-separated)
     */
    protected static function get_label_markup( string $label_text, int $icon_id, string $presentation, bool $hide_icon ) : string {
        $icon_html = '';

        if ( ! $hide_icon && $icon_id > 0 ) {
            $img = wp_get_attachment_image( $icon_id, [24,24], false, [ 'class' => 'jp-menu__icon' ] );
            if ( is_string($img) ) $icon_html = $img;
        }

        if ( $presentation === 'icon' ) {
            return $icon_html;
        }

        if ( $presentation === 'text' ) {
            return esc_html( $label_text );
        }

        if ( $presentation === 'icon_text' ) {
            if ( $icon_html !== '' ) {
                return $icon_html + ' ' . esc_html( $label_text );
            }
            return esc_html( $label_text );
        }

        return esc_html( $label_text );
    }

    /** Defensive cleanup for price strings */
    protected static function sanitize_price_string( $v ) : string {
        $v = is_scalar($v) ? (string)$v : '';
        // allow "0", trim spaces
        return trim($v);
    }
}
