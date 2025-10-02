<?php
/**
 * Passive writer for Price Schema v3.
 * Keeps your existing Admin UI and legacy metas intact.
 * On save, derives a unified JSON under 'jprm_price' without changing your UI.
 */
namespace JelloPoint\RestaurantMenu\Admin\Save;

if ( ! defined('ABSPATH') ) { exit; }

class MenuItem_V3_Writer {

    const CPT     = 'jprm_menu_item';
    const META_V3 = 'jprm_price';

    public static function init() : void {
        // Run late so your admin save completed first
        add_action( 'save_post_' . self::CPT, [ __CLASS__, 'write_v3' ], 50, 2 );
    }

    public static function write_v3( $post_id, $post ) : void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // If existing v3 looks valid, try to sanitize (fix nested JSON) and keep it.
        $existing = self::read_v3( $post_id );
        if ( ! empty( $existing ) ) {
            $fixed = self::sanitize_v3( $existing );
            if ( $fixed !== $existing ) {
                update_post_meta( $post_id, self::META_V3, wp_json_encode( $fixed, JSON_UNESCAPED_UNICODE ) );
            }
            // Do not rebuild if it already exists; exit early.
            return;
        }

        // 1) Try MULTI from legacy parallel arrays first
        $labels_arr  = self::to_array( get_post_meta( $post_id, '_jprm_price_labels', true ) );
        $amounts_arr = self::to_array( get_post_meta( $post_id, '_jprm_price_amounts', true ) );
        $hide_arr    = self::to_array( get_post_meta( $post_id, '_jprm_price_hideicons', true ) );

        $rows = [];
        if ( ! empty( $labels_arr ) || ! empty( $amounts_arr ) ) {
            $max = max( count($labels_arr), count($amounts_arr) );
            for ( $i = 0; $i < $max; $i++ ) {
                $label_ref = isset($labels_arr[$i]) ? trim((string)$labels_arr[$i]) : '';
                $value     = isset($amounts_arr[$i]) ? trim((string)$amounts_arr[$i]) : '';
                $hide      = ! empty( $hide_arr[$i] );
                if ( $value !== '' ) {
                    $rows[] = [
                        'label_ref' => $label_ref,
                        'value'     => $value,
                        'hide_icon' => $hide,
                    ];
                }
            }
        }

        if ( ! empty( $rows ) ) {
            $data = [ 'mode' => 'multi', 'rows' => $rows ];
            update_post_meta( $post_id, self::META_V3, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
            return;
        }

        // 2) Otherwise SINGLE from legacy single keys (IMPORTANT: DO NOT READ 'jprm_price' HERE)
        $single = self::first_scalar( $post_id, [
            '_jprm_price','price','_price','item_price','price_single','single_price',
            'jprm_item_price','_jprm_price_value','_jprm_single_price','_jp_price'
        ] );

        // Single label reference (common legacy keys; DO NOT READ 'jprm_price')
        $single_label_ref = self::first_scalar( $post_id, [
            '_jprm_single_label','single_label','price_label','jprm_single_label','jprm_price_label',
            '_jprm_label_single','label_single','_jprm_label_id','_jprm_label_key',
            'label_id','label_key','label_ref','label','preset','slug'
        ] );

        $single_hide_icon = self::truthy( get_post_meta( $post_id, '_jprm_single_hide_icon', true ) )
                             || self::truthy( get_post_meta( $post_id, 'single_hide_icon', true ) )
                             || self::truthy( get_post_meta( $post_id, 'price_hide_icon', true ) );

        if ( $single !== '' ) {
            $data = [
                'mode'      => 'single',
                'price'     => (string) $single,
                'label_ref' => (string) $single_label_ref,
                'hide_icon' => (bool) $single_hide_icon,
            ];
            $data = self::sanitize_v3( $data );
            update_post_meta( $post_id, self::META_V3, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
            return;
        }

        // 3) Nothing to write → remove v3 to stay clean
        delete_post_meta( $post_id, self::META_V3 );
    }

    /* ---------- v3 helpers ---------- */

    protected static function read_v3( $post_id ) : array {
        $raw = get_post_meta( $post_id, self::META_V3, true );
        if ( is_array($raw) ) return $raw; // in rare cases
        if ( ! is_string($raw) || $raw === '' ) return [];
        $trim = trim($raw);
        if ( $trim === '' || ($trim[0] !== '{' && $trim[0] !== '[') ) return [];
        $cfg = json_decode( $trim, true );
        return (json_last_error() === JSON_ERROR_NONE && is_array($cfg)) ? $cfg : [];
    }

    protected static function sanitize_v3( array $cfg ) : array {
        // Fix nested JSON in single price
        if ( isset($cfg['mode']) && $cfg['mode'] === 'single' && isset($cfg['price']) ) {
            $cfg['price'] = self::sanitize_price_string( $cfg['price'] );
            if ( $cfg['price'] === '' ) unset($cfg['price']);
        }
        // Fix nested JSON in multi rows values (unlikely, but safe)
        if ( isset($cfg['mode']) && $cfg['mode'] === 'multi' && !empty($cfg['rows']) && is_array($cfg['rows']) ) {
            foreach ( $cfg['rows'] as &$r ) {
                if ( isset($r['value']) ) {
                    $r['value'] = self::sanitize_price_string( $r['value'] );
                }
            }
            unset($r);
        }
        return $cfg;
    }

    protected static function sanitize_price_string( $s ) : string {
        if ( is_string($s) ) {
            $t = trim($s);
            // if it's a JSON-looking string, try to decode and pull an inner 'price' or 'value'
            if ( $t !== '' && ($t[0] === '{' || $t[0] === '[') ) {
                $inner = json_decode( $t, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array($inner) ) {
                    if ( isset($inner['price']) && is_scalar($inner['price']) ) return (string)$inner['price'];
                    if ( isset($inner['value']) && is_scalar($inner['value']) ) return (string)$inner['value'];
                }
                // if we can’t parse, drop it (better no price than raw JSON)
                return '';
            }
            return $t;
        }
        if ( is_numeric($s) ) return (string)$s;
        return '';
    }

    /* ---------- legacy helpers ---------- */

    protected static function to_array( $v ) : array {
        if ( is_array( $v ) ) return $v;
        if ( is_string( $v ) ) {
            $j = json_decode( $v, true );
            if ( is_array( $j ) ) return $j;
            $m = maybe_unserialize( $v );
            if ( is_array( $m ) ) return $m;
            if ( strpos($v, ',') !== false ) {
                return array_map( 'trim', explode( ',', $v ) );
            }
        }
        return [];
    }

    protected static function first_scalar( $post_id, array $keys ) : string {
        foreach ( $keys as $k ) {
            $v = get_post_meta( $post_id, $k, true );
            if ( is_string($v) || is_numeric($v) ) {
                $sv = trim((string)$v);
                if ( $sv !== '' ) {
                    // if someone stored JSON by mistake in the legacy key, try to unwrap
                    $sv = self::sanitize_price_string( $sv );
                    if ( $sv !== '' ) return $sv;
                }
            } elseif ( is_array($v) ) {
                if ( isset($v['formatted']) && $v['formatted'] !== '' ) return (string)$v['formatted'];
                if ( isset($v['value'])     && $v['value'] !== '' )     return (string)$v['value'];
                if ( isset($v['amount'])    && $v['amount'] !== '' )    return (string)$v['amount'];
                if ( isset($v['price'])     && $v['price'] !== '' )     return (string)$v['price'];
                if ( isset($v[0])           && $v[0] !== '' )           return (string)$v[0];
            }
        }
        return '';
    }

    protected static function truthy( $v ) : bool {
        return ($v === '1' || $v === 1 || $v === true || $v === 'yes' || $v === 'on');
    }
}
MenuItem_V3_Writer::init();
