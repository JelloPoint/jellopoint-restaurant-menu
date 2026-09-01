<?php
/**
 * Price Schema v3 helper (read/validate/normalize).
 * Single canonical meta key: 'jprm_price' (JSON).
 */
namespace JelloPoint\RestaurantMenu\Data;

use JelloPoint\RestaurantMenu\Storage\Price_Schema as Storage_Price_Schema;

if ( ! defined('ABSPATH') ) { exit; }

class Price_Schema {

    /**
     * Load and validate schema for a post (returns array or empty array).
     */
    public static function from_post( $post_id ) : array {
		return Storage_Price_Schema::from_post( (int) $post_id );
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
