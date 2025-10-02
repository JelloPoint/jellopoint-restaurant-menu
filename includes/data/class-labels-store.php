<?php
/**
 * Labels store facade.
 * Reads option 'jprm_price_labels_v2' (array or JSON string).
 * Provides lookups by id/slug/name + resolve() helper.
 *
 * Note: Kept as global class (no namespace) for maximum compatibility.
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :

class JPRM_Labels_Store {

    /** Return the raw list as array of rows. */
    public static function all() : array {
        $opt = get_option( 'jprm_price_labels_v2', [] );
        if ( is_string( $opt ) ) {
            $arr = json_decode( $opt, true );
            return is_array($arr) ? $arr : [];
        }
        return is_array($opt) ? $opt : [];
    }

    /** Map by id. */
    public static function map_by_id() : array {
        $out = [];
        foreach ( self::all() as $row ) {
            if ( ! is_array($row) ) continue;
            $id = isset($row['id']) ? (string)$row['id'] : '';
            if ( $id === '' ) continue;
            $out[$id] = $row;
        }
        return $out;
    }

    /** Map by slug. */
    public static function map_by_slug() : array {
        $out = [];
        foreach ( self::all() as $row ) {
            if ( ! is_array($row) ) continue;
            $slug = isset($row['slug']) ? (string)$row['slug'] : '';
            if ( $slug === '' ) continue;
            $out[$slug] = $row;
        }
        return $out;
    }

    /**
     * Get a label row by id OR slug OR case-insensitive name.
     * Returns array|null
     */
    public static function get_by_ref( string $ref ) : ?array {
        $ref = trim($ref);
        if ( $ref === '' ) return null;

        // Try id
        $by_id = self::map_by_id();
        if ( isset( $by_id[$ref] ) ) return $by_id[$ref];

        // Try slug
        $by_slug = self::map_by_slug();
        if ( isset( $by_slug[$ref] ) ) return $by_slug[$ref];

        // Case-insensitive match against id/slug/label
        $needle = strtolower($ref);
        foreach ( self::all() as $r ) {
            if ( ! is_array($r) ) continue;
            $id   = strtolower( (string) ($r['id']    ?? '') );
            $slug = strtolower( (string) ($r['slug']  ?? '') );
            $name = strtolower( (string) ($r['label'] ?? '') );
            if ( $needle === $id || $needle === $slug || $needle === $name ) return $r;
        }

        return null;
    }

    /**
     * Resolve any ref or free text into display text + icon id.
     * Returns ['label_text' => string, 'icon_id' => int]
     */
    public static function resolve( string $ref_or_text ) : array {
        $ref_or_text = trim($ref_or_text);
        if ( $ref_or_text === '' ) return [ 'label_text' => '', 'icon_id' => 0 ];

        $row = self::get_by_ref( $ref_or_text );
        if ( $row ) {
            return [
                'label_text' => (string) ( $row['label'] ?? $ref_or_text ),
                'icon_id'    => isset($row['icon_id']) ? (int)$row['icon_id'] : 0,
            ];
        }

        // treat as literal text
        return [ 'label_text' => $ref_or_text, 'icon_id' => 0 ];
    }
}

endif;
