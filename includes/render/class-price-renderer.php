<?php
/**
 * Price Renderer – turns canonical jprm_price (v3) into HTML rows.
 */
namespace JelloPoint\RestaurantMenu\Render;

use JelloPoint\RestaurantMenu\Data\Price_Schema;

if ( ! defined('ABSPATH') ) exit;

class Price_Renderer {

    /**
     * Convenience: render directly from a post's v3 JSON meta (jprm_price).
     * Read-only. Returns '' if invalid/missing.
     *
     * @param int   $post_id
     * @param array $opts Same as render_pricegroup()
     * @return string HTML (identical structure to render_pricegroup output)
     */
    public static function render_from_meta( int $post_id, array $opts = [] ) : string {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) return '';

        // Read + normalize via schema (single source of truth)
        $cfg = Price_Schema::from_post( $post_id );
        if ( ! is_array( $cfg ) || empty( $cfg ) ) return '';

        return self::render_pricegroup( $cfg, $opts );
    }

    /**
     * Render full <div class="jp-menu__pricegroup">…</div> for a v3 cfg.
     *
     * @param array $cfg Canonical schema:
     *  - single: ['mode'=>'single','price'=>string,'label_ref'=>string,'hide_icon'=>bool]
     *  - multi : ['mode'=>'multi','rows'=>[ ['value'=>string,'label_ref'=>string,'hide_icon'=>bool], ... ]]
     * @param array $opts [
     *   'presentation'   => 'text' | 'icon' | 'icon_text',
     *   'order_class'    => 'jp-order--label-left' | 'jp-order--label-right',
     *   'currency'       => ['show'=>bool,'symbol'=>string,'position'=>'before|after','spacing'=>'none|thin|normal']
     * ]
     */
    public static function render_pricegroup( array $cfg, array $opts = [] ) : string {
        // Defaults (strict; no guessing)
        $presentation = (isset($opts['presentation']) && in_array($opts['presentation'], ['text','icon','icon_text'], true))
            ? $opts['presentation']
            : 'icon_text';

        $order_class = (isset($opts['order_class']) && is_string($opts['order_class']))
            ? $opts['order_class']
            : 'jp-order--label-right';

        $currency = is_array($opts['currency'] ?? null) ? $opts['currency'] : [
            'show' => false, 'symbol' => '€', 'position' => 'before', 'spacing' => 'thin',
        ];

        ob_start();
        echo '<div class="jp-menu__pricegroup">';

        // SINGLE
        if ( Price_Schema::is_single( $cfg ) ) {
            $price = self::sanitize_price_string( $cfg['price'] ?? '' );
            if ( $price !== '' ) {
                $ref      = (string) ( $cfg['label_ref'] ?? '' );
                $hide     = ! empty( $cfg['hide_icon'] );

                $res        = \JPRM_Labels_Store::resolve( $ref );
                $label_text = (string) ( $res['label_text'] ?? '' );
                $icon_id    = (int) ( $res['icon_id'] ?? 0 );

                $price_html = self::format_price_display( $price, $currency );

                echo self::row_html( $price_html, $label_text, $icon_id, $presentation, $order_class, $hide );
            }
        }
        // MULTI
        else {
            foreach ( Price_Schema::iter_rows( $cfg ) as $row ) {
                if ( ! is_array( $row ) ) continue;

                $price = self::sanitize_price_string( $row['value'] ?? '' ); // schema: value
                if ( $price === '' ) continue;

                $ref      = (string) ( $row['label_ref'] ?? '' );            // schema: label_ref
                $hide     = ! empty( $row['hide_icon'] );                    // schema: hide_icon

                $res        = \JPRM_Labels_Store::resolve( $ref );
                $label_text = (string) ( $res['label_text'] ?? '' );
                $icon_id    = (int) ( $res['icon_id'] ?? 0 );

                $price_html = self::format_price_display( $price, $currency );

                echo self::row_html( $price_html, $label_text, $icon_id, $presentation, $order_class, $hide );
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render one row: label (text/icon/both) + price markup.
     * Keeps exact classes/structure.
     */
    protected static function row_html( string $price_html, string $label_text, int $icon_id, string $presentation, string $order_class, bool $hide_icon ) : string {
        $label_markup = self::get_label_markup( $label_text, $icon_id, $presentation, $hide_icon );

        // Order: left label then price, or reversed (DOM order controls layout)
        if ( $order_class === 'jp-order--label-left' ) {
            return '<div class="jp-menu__row ' . esc_attr( $order_class ) . '">'
                . '<span class="jp-menu__label">' . $label_markup . '</span>'
                . $price_html
                . '</div>';
        }

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
            if ( is_string( $img ) ) {
                $icon_html = $img;
            }
        }

        if ( $presentation === 'icon' ) {
            return $icon_html;
        }
        if ( $presentation === 'text' ) {
            return esc_html( $label_text );
        }
        if ( $presentation === 'icon_text' ) {
            return $icon_html !== '' ? ( $icon_html . ' ' . esc_html( $label_text ) ) : esc_html( $label_text );
        }

        // Fallback to text if unknown mode is passed in opts
        return esc_html( $label_text );
    }

    /** Defensive cleanup for price strings */
    protected static function sanitize_price_string( $v ) : string {
        $v = is_scalar( $v ) ? (string) $v : '';
        return trim( $v ); // allow "0"
    }

    /**
     * Apply currency formatting to a plain price string.
     * Respects: show, symbol, position (before/after), spacing (none/thin/normal).
     */
    protected static function format_price_display( string $price, array $currency ) : string {
        $price = esc_html( $price );

        if ( empty( $currency['show'] ) ) {
            return '<span class="jp-menu__price">' . $price . '</span>';
        }

        $symbol   = isset( $currency['symbol'] )   ? (string) $currency['symbol']   : '€';
        $position = isset( $currency['position'] ) ? (string) $currency['position'] : 'before';
        $spacing  = isset( $currency['spacing'] )  ? (string) $currency['spacing']  : 'thin';

        $sp = '';
        if ( $spacing === 'normal' )      { $sp = '&nbsp;'; }   // non-breaking
        elseif ( $spacing === 'thin' )    { $sp = '&#8201;'; }  // thin space
        else /* none */                   { $sp = ''; }

        if ( $position === 'before' ) {
            return '<span class="jp-menu__price"><span class="jp-menu__currency">' . esc_html( $symbol ) . '</span>' . $sp . $price . '</span>';
        }
        // after
        return '<span class="jp-menu__price">' . $price . $sp . '<span class="jp-menu__currency">' . esc_html( $symbol ) . '</span></span>';
    }
}
