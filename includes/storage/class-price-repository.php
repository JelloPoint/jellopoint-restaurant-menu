<?php
/**
 * Price Repository – single point of read/write for 'jprm_price'
 */
namespace JelloPoint\RestaurantMenu\Storage;

if ( ! defined('ABSPATH') ) exit;

class Price_Repository {

    const META_KEY = 'jprm_price';

    /** Read normalized cfg or null if missing/invalid. */
    public static function get( int $post_id ) : ?array {
        $raw = get_post_meta( $post_id, self::META_KEY, true );
        $cfg = null;

        if ( is_array($raw) ) {
            $cfg = $raw;
        } elseif ( is_string($raw) ) {
            $trim = trim($raw);
            if ( $trim !== '' && ($trim[0] === '{' || $trim[0] === '[') ) {
                $tmp = json_decode( $trim, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array($tmp) ) $cfg = $tmp;
            }
        }

        if ( ! is_array($cfg) ) return null;

        $cfg = Price_Schema::sanitize_cfg( $cfg );
        if ( ! Price_Schema::is_valid( $cfg ) ) return null;
        return $cfg;
    }

    /** Write cfg (validated) to meta. Returns true on success. */
    public static function set( int $post_id, array $cfg ) : bool {
        $cfg = Price_Schema::sanitize_cfg( $cfg );
        if ( ! Price_Schema::is_valid( $cfg ) ) return false;
        return (bool) update_post_meta( $post_id, self::META_KEY, wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) );
    }

    /** Delete meta. */
    public static function delete( int $post_id ) : void {
        delete_post_meta( $post_id, self::META_KEY );
    }
}
