<?php
/**
 * Price Schema v3 helper (read/validate/normalize).
 * Single canonical meta key: 'jprm_price' (JSON).
 */
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined('ABSPATH') ) { exit; }

class Price_Schema {

    /**
     * Load and validate schema for a post (returns array or empty array).
     */
    public static function from_post( $post_id ) : array {
        $raw = get_post_meta( $post_id, 'jprm_price', true );
        $cfg = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if ( ! is_array($cfg) || empty($cfg['mode']) ) return [];

        $mode = $cfg['mode'];
        if ( $mode === 'single' ) {
            $price = isset($cfg['price']) ? (string) $cfg['price'] : '';
            if ( $price === '' ) return [];
            return [
                'mode'        => 'single',
                'price'       => $price,
                'label_ref'   => isset($cfg['label_ref']) ? (string)$cfg['label_ref'] : '',
                'hide_icon'   => ! empty($cfg['hide_icon']),
            ];
        }

        if ( $mode === 'multi' ) {
            $rows = isset($cfg['rows']) && is_array($cfg['rows']) ? $cfg['rows'] : [];
            $out  = [];
            foreach ( $rows as $r ) {
                if ( ! is_array($r) ) continue;
                $val = isset($r['value']) ? (string) $r['value'] : '';
                if ( $val === '' ) continue;
                $out[] = [
                    'label_ref' => isset($r['label_ref']) ? (string)$r['label_ref'] : '',
                    'value'     => $val,
                    'hide_icon' => ! empty($r['hide_icon']),
                ];
            }
            if ( empty($out) ) return [];
            return [ 'mode' => 'multi', 'rows' => $out ];
        }

        return [];
    }

    /** Convenience: true if single mode with a value. */
    public static function is_single( array $cfg ) : bool {
        return isset($cfg['mode']) && $cfg['mode'] === 'single' && !empty($cfg['price']);
    }

    /** Convenience: iterate multi rows (label_ref, value, hide_icon). */
    public static function iter_rows( array $cfg ) : array {
        if ( isset($cfg['mode']) && $cfg['mode'] === 'multi' && !empty($cfg['rows']) && is_array($cfg['rows']) ) {
            return $cfg['rows'];
        }
        return [];
    }
}