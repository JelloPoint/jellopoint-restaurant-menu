<?php
/**
 * Price Schema (v3) – validation & normalization
 */
namespace JelloPoint\RestaurantMenu\Storage;

if ( ! defined('ABSPATH') ) exit;

class Price_Schema {

    /**
     * Normalize a single-price config.
     * Input keys (any subset):
     *  - price (string|number)
     *  - label_ref (string)   // preset id/slug OR custom text
     *  - icon_id (int)        // explicit icon overrides label store
     *  - hide_icon (bool)
     */
    public static function normalize_single( array $in ) : array {
        $price     = self::sanitize_price_string( $in['price'] ?? '' );
        $label_ref = is_scalar($in['label_ref'] ?? '') ? (string)$in['label_ref'] : '';
        $icon_id   = isset($in['icon_id']) ? max(0, (int)$in['icon_id']) : 0;
        $hide_icon = ! empty( $in['hide_icon'] );

        $out = [
            'mode'      => 'single',
            'price'     => $price,
            'label_ref' => $label_ref,
            'hide_icon' => (bool)$hide_icon,
        ];
        if ( $icon_id > 0 ) $out['icon_id'] = $icon_id;
        return $out;
    }

    /**
     * Normalize multi rows.
     * Each row (any subset):
     *  - value (string|number)
     *  - label_ref (string)   // preset id/slug OR custom text
     *  - icon_id (int)
     *  - hide_icon (bool)
     */
    public static function normalize_multi( array $rows ) : array {
        $out_rows = [];
        foreach ( $rows as $r ) {
            if ( ! is_array($r) ) continue;
            $value     = self::sanitize_price_string( $r['value'] ?? '' );
            if ( $value === '' ) continue;

            $label_ref = is_scalar($r['label_ref'] ?? '') ? (string)$r['label_ref'] : '';
            $icon_id   = isset($r['icon_id']) ? max(0, (int)$r['icon_id']) : 0;
            $hide_icon = ! empty( $r['hide_icon'] );

            $row = [
                'label_ref' => $label_ref,
                'value'     => $value,
                'hide_icon' => (bool)$hide_icon,
            ];
            if ( $icon_id > 0 ) $row['icon_id'] = $icon_id;
            $out_rows[] = $row;
        }
        return [
            'mode' => 'multi',
            'rows' => $out_rows,
        ];
    }

    /** Validate minimal structure. */
    public static function is_valid( array $cfg ) : bool {
        if ( empty($cfg['mode']) ) return false;
        if ( $cfg['mode'] === 'single' ) {
            return isset($cfg['price']) && $cfg['price'] !== '';
        }
        if ( $cfg['mode'] === 'multi' ) {
            if ( empty($cfg['rows']) || ! is_array($cfg['rows']) ) return false;
            foreach ( $cfg['rows'] as $r ) {
                if ( ! is_array($r) || ($r['value'] ?? '') === '' ) return false;
            }
            return true;
        }
        return false;
    }

    /** Unwrap nested JSON and sanitize a price-like string. */
    public static function sanitize_price_string( $s ) : string {
        if ( is_string($s) ) {
            $t = trim($s);
            if ( $t !== '' && ($t[0] === '{' || $t[0] === '[') ) {
                $inner = json_decode( $t, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array($inner) ) {
                    if ( isset($inner['price']) && is_scalar($inner['price']) ) return (string)$inner['price'];
                    if ( isset($inner['value']) && is_scalar($inner['value']) ) return (string)$inner['value'];
                }
                return '';
            }
            return $t;
        }
        if ( is_numeric($s) ) return (string)$s;
        return '';
    }

    /** Sanitize a full cfg (unwrap nested JSON in price/value). */
    public static function sanitize_cfg( array $cfg ) : array {
        if ( ($cfg['mode'] ?? '') === 'single' && isset($cfg['price']) ) {
            $cfg['price'] = self::sanitize_price_string( $cfg['price'] );
            if ( $cfg['price'] === '' ) unset($cfg['price']);
        }
        if ( ($cfg['mode'] ?? '') === 'multi' && !empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as &$r ) {
                if ( isset($r['value']) ) $r['value'] = self::sanitize_price_string( $r['value'] );
            }
            unset($r);
        }
        return $cfg;
    }
}
