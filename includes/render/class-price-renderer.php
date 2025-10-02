<?php
/**
 * Price Renderer – turns canonical jprm_price (v3) into HTML rows.
 */
namespace JelloPoint\RestaurantMenu\Render;

use JelloPoint\RestaurantMenu\Storage\Price_Schema;

if ( ! defined('ABSPATH') ) exit;

class Price_Renderer {

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
            ? $opts['presentation'] : 'text';

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
            $label_text = $res['label_text'];
            if ( $icon_id <= 0 ) $icon_id = $res['icon_id'];

            if ( $label_text === '' && ! $icon_id ) {
                echo '<div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $price ) . '</span></div>';
            } else {
                echo '<div class="jp-menu__price jp-price-row ' . esc_attr( $order_class ) . '">';
                echo '  <span class="jp-menu__label jp-col-label">' . self::label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                echo '</div>';
            }
        }

        if ( ($cfg['mode'] ?? '') === 'multi' && ! empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as $row ) {
                $value = self::sanitize_price_string( $row['value'] ?? '' );
                if ( $value === '' ) continue;

                $ref     = (string)($row['label_ref'] ?? '');
                $icon_id = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
                $hide    = ! empty( $row['hide_icon'] );

                $res        = \JPRM_Labels_Store::resolve( $ref );
                $label_text = $res['label_text'];
                if ( $icon_id <= 0 ) $icon_id = $res['icon_id'];

                echo '<div class="jp-menu__price jp-price-row ' . esc_attr( $order_class ) . '">';
                echo '  <span class="jp-menu__label jp-col-label">' . self::label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                echo '</div>';
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /* -------------------- internals -------------------- */

    /** Same sanitizer used elsewhere; keep renderer hermetic. */
    protected static function sanitize_price_string( $s ) : string {
        return Price_Schema::sanitize_price_string( $s );
    }

    /** Build label HTML based on presentation + optional icon override. */
    protected static function label_html( string $label_text, string $presentation, int $icon_id = 0, bool $hide_icon = false ) : string {
        if ( $presentation !== 'text' && $presentation !== 'icon' && $presentation !== 'icon_text' ) {
            $presentation = 'text';
        }
        if ( $hide_icon ) $presentation = 'text';

        $icon_html = '';
        if ( $icon_id > 0 ) {
            $img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [
                'class' => 'jp-menu__icon',
                'alt'   => $label_text !== '' ? $label_text : '',
            ] );
            if ( is_string( $img ) && $img !== '' ) $icon_html = $img;
        }

        if ( $presentation === 'icon' ) {
            if ( $icon_html !== '' ) {
                return $icon_html . '<span class="screen-reader-text">' . esc_html( $label_text ) . '</span>';
            }
            return esc_html( $label_text );
        }

        if ( $presentation === 'icon_text' ) {
            if ( $icon_html !== '' ) {
                return $icon_html . ' ' . esc_html( $label_text );
            }
            return esc_html( $label_text );
        }

        return esc_html( $label_text );
    }
}
