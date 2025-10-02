<?php
/**
 * Passive writer for Price Schema v3.
 * Reads the CURRENT admin meta keys and writes unified JSON to 'jprm_price'.
 * Does NOT change your UI or delete any meta.
 */
namespace JelloPoint\RestaurantMenu\Admin\Save;

if ( ! defined('ABSPATH') ) { exit; }

class MenuItem_V3_Writer {

    const CPT     = 'jprm_menu_item';
    const META_V3 = 'jprm_price';

    public static function init() : void {
        // run after your admin save
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'write_v3' ], 50, 2 );
    }

    public static function write_v3( $post_id, $post ) : void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // 0) If there is already a valid v3, sanitize & keep (avoid churn)
        $existing = self::read_v3( $post_id );
        if ( ! empty( $existing ) ) {
            $fixed = self::sanitize_v3( $existing );
            if ( $fixed !== $existing ) {
                update_post_meta( $post_id, self::META_V3, wp_json_encode( $fixed, JSON_UNESCAPED_UNICODE ) );
            }
            return;
        }

        // 1) Identify mode as saved by admin
        $mode = get_post_meta( $post_id, 'jprm_price_mode', true );
        $mode = ($mode === 'multi') ? 'multi' : 'single';

        if ( $mode === 'single' ) {
            // Single amount
            $amount = get_post_meta( $post_id, 'jprm_price_amount', true );
            $amount = self::sanitize_price_string( $amount );

            // Label mode & value
            $lm   = get_post_meta( $post_id, 'jprm_price_label_mode', true );
            $lm   = ($lm === 'custom') ? 'custom' : 'ref';
            $ref  = get_post_meta( $post_id, 'jprm_price_label_ref', true );
            $cust = get_post_meta( $post_id, 'jprm_price_label_custom', true );
            $icon = (int) get_post_meta( $post_id, 'jprm_price_label_icon_id', true );

            // Per-row icon visibility for single? Stored as 'hide_icon' false by default unless you add a checkbox later.
            $hide_icon = false;

            if ( $amount !== '' ) {
                $data = [
                    'mode'      => 'single',
                    'price'     => $amount,
                    'label_ref' => ($lm === 'custom') ? (string)$cust : (string)$ref, // custom text or predefined ref
                    'icon_id'   => $icon,     // NEW: carry icon explicitly (overrides label store)
                    'hide_icon' => $hide_icon
                ];
                $data = self::sanitize_v3( $data );
                update_post_meta( $post_id, self::META_V3, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
            } else {
                delete_post_meta( $post_id, self::META_V3 );
            }
            return;
        }

        // MULTI mode: read rows from 'jprm_prices' JSON array
        $rows_raw = get_post_meta( $post_id, 'jprm_prices', true );
        if ( is_string( $rows_raw ) && $rows_raw !== '' ) {
            $rows = json_decode( $rows_raw, true );
        } elseif ( is_array( $rows_raw ) ) {
            $rows = $rows_raw;
        } else {
            $rows = [];
        }

        $out = [];
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) {
                if ( ! is_array( $r ) ) continue;
                // respect "enabled" flag
                $enabled = ! empty( $r['enabled'] );
                if ( ! $enabled ) continue;

                $label_mode = ( ($r['label_mode'] ?? 'ref') === 'custom' ) ? 'custom' : 'ref';
                $label_ref  = (string) ( $label_mode === 'custom' ? ( $r['label_custom'] ?? '' ) : ( $r['label_ref'] ?? '' ) );
                $icon_id    = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;
                $amount     = isset($r['amount']) ? self::sanitize_price_string( $r['amount'] ) : '';
                $hide_icon  = ! empty( $r['hide_icon'] );

                if ( $amount === '' ) continue;

                $out[] = [
                    'label_ref' => $label_ref,   // custom text or predefined id/slug
                    'value'     => $amount,
                    'icon_id'   => $icon_id,     // NEW: carry icon explicitly
                    'hide_icon' => $hide_icon,
                ];
            }
        }

        if ( ! empty( $out ) ) {
            $data = [ 'mode' => 'multi', 'rows' => $out ];
            $data = self::sanitize_v3( $data );
            update_post_meta( $post_id, self::META_V3, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
        } else {
            delete_post_meta( $post_id, self::META_V3 );
        }
    }

    /* ---------- v3 helpers ---------- */

    protected static function read_v3( $post_id ) : array {
        $raw = get_post_meta( $post_id, self::META_V3, true );
        if ( is_array($raw) ) return $raw;
        if ( ! is_string($raw) || $raw === '' ) return [];
        $trim = trim($raw);
        if ( $trim === '' || ($trim[0] !== '{' && $trim[0] !== '[') ) return [];
        $cfg = json_decode( $trim, true );
        return (json_last_error() === JSON_ERROR_NONE && is_array($cfg)) ? $cfg : [];
    }

    protected static function sanitize_v3( array $cfg ) : array {
        // unwrap nested JSON in price/value fields if any
        if ( isset($cfg['mode']) && $cfg['mode'] === 'single' && isset($cfg['price']) ) {
            $cfg['price'] = self::sanitize_price_string( $cfg['price'] );
            if ( $cfg['price'] === '' ) unset($cfg['price']);
        }
        if ( isset($cfg['mode']) && $cfg['mode'] === 'multi' && !empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as &$r ) {
                if ( isset($r['value']) ) $r['value'] = self::sanitize_price_string( $r['value'] );
            }
            unset($r);
        }
        return $cfg;
    }

    protected static function sanitize_price_string( $s ) : string {
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
}
MenuItem_V3_Writer::init();
