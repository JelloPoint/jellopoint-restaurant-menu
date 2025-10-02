<?php
/**
 * Price Renderer – turns jprm_price (v3) into stable HTML.
 */
namespace JelloPoint\RestaurantMenu\Render;

if ( ! defined('ABSPATH') ) exit;

class Price_Renderer {

    /**
     * Render the price block (single or multi) from a normalized cfg.
     * $cfg: ['mode'=>'single','price'=>...,'label_ref'=>...,'icon_id'=>?,'hide_icon'=>?]
     *   or  ['mode'=>'multi','rows'=>[ ['label_ref'=>...,'value'=>...,'icon_id'=>?,'hide_icon'=>?], ... ]]
     *
     * $opts:
     *  - presentation: 'text' | 'icon' | 'icon_text' (default 'text')
     *  - label_position: 'left' | 'right' (default 'right')
     *  - resolve_label: callable(string $ref): array{label_text:string, icon_id:int}
     *
     * Returns safe HTML string.
     */
    public static function render_price_group( array $cfg, array $opts ) : string {
        $presentation  = in_array( $opts['presentation'] ?? 'text', ['text','icon','icon_text'], true )
            ? $opts['presentation'] : 'text';
        $position      = ($opts['label_position'] ?? 'right') === 'left' ? 'left' : 'right';
        $resolve_label = is_callable( $opts['resolve_label'] ?? null ) ? $opts['resolve_label'] : function( $ref ){ return ['label_text'=>'','icon_id'=>0]; };

        $label_order_cls = $position === 'left' ? 'jp-order--label-left' : 'jp-order--label-right';

        ob_start();
        echo '<div class="jp-menu__pricegroup">';
        if ( ($cfg['mode'] ?? '') === 'single' && ! empty($cfg['price']) ) {
            $price   = (string) $cfg['price'];
            $ref     = (string) ($cfg['label_ref'] ?? '');
            $icon_id = isset($cfg['icon_id']) ? (int)$cfg['icon_id'] : 0;
            $hide    = ! empty($cfg['hide_icon']);

            $res = $resolve_label( $ref );
            $label_text = $res['label_text'] ?? '';
            if ( $icon_id <= 0 ) $icon_id = (int) ($res['icon_id'] ?? 0);

            if ( $label_text === '' && ! $icon_id ) {
                echo '<div class="jp-menu__price"><span class="jp-menu__value">' . esc_html( $price ) . '</span></div>';
            } else {
                echo '<div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                echo '  <span class="jp-menu__label jp-col-label">' . self::label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                echo '</div>';
            }
        } elseif ( ($cfg['mode'] ?? '') === 'multi' && ! empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as $row ) {
                $value   = isset($row['value']) ? (string)$row['value'] : '';
                if ( $value === '' ) continue;
                $ref     = (string) ($row['label_ref'] ?? '');
                $icon_id = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
                $hide    = ! empty($row['hide_icon']);

                $res = $resolve_label( $ref );
                $label_text = $res['label_text'] ?? '';
                if ( $icon_id <= 0 ) $icon_id = (int) ($res['icon_id'] ?? 0);

                echo '<div class="jp-menu__price jp-price-row ' . esc_attr( $label_order_cls ) . '">';
                echo '  <span class="jp-menu__label jp-col-label">' . self::label_html( $label_text, $presentation, $icon_id, $hide ) . '</span>';
                echo '  <span class="jp-menu__value jp-col-price">' . esc_html( $value ) . '</span>';
                echo '</div>';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render a label based on presentation rules.
     */
    public static function label_html( string $label_text, string $presentation, int $icon_id = 0, bool $hide_icon = false ) : string {
        if ( ! in_array( $presentation, ['text','icon','icon_text'], true ) ) {
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
