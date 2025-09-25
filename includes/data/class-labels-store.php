<?php
/**
 * Data: Labels Store
 * Canonical source for reading Price Labels from the option `jprm_price_labels` (JSON array).
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) {

class JPRM_Labels_Store {

    const OPTION_KEY = 'jprm_price_labels';

    /**
     * Return array of labels: [ ['id'=>'pl-uuid', 'label'=>'Small', 'slug'=>'small', 'icon_id'=>503, 'active'=>true, 'order'=>0], ... ]
     */
    public static function all() : array {
        $raw = get_option( self::OPTION_KEY, '' );
        if ( is_array($raw) ) {
            $arr = $raw;
        } else {
            $arr = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        }
        if ( ! is_array($arr) ) $arr = [];
        $out = [];
        foreach ( $arr as $row ) {
            if ( ! is_array($row) ) continue;
            $id    = isset($row['id']) ? (string)$row['id'] : '';
            $label = isset($row['label']) ? (string)$row['label'] : '';
            if ( $label === '' ) continue;
            $out[] = [
                'id'      => $id !== '' ? $id : sanitize_title($label),
                'label'   => $label,
                'slug'    => isset($row['slug']) ? sanitize_title($row['slug']) : sanitize_title($label),
                'icon_id' => isset($row['icon_id']) ? intval($row['icon_id']) : 0,
                'active'  => isset($row['active']) ? (bool)$row['active'] : true,
                'order'   => isset($row['order']) ? intval($row['order']) : 0,
            ];
        }
        $out = array_values(array_filter($out, function($r){ return !isset($r['active']) || $r['active']; }));
        usort($out, function($a,$b){
            $ao = $a['order'] ?? 0; $bo = $b['order'] ?? 0;
            if ($ao === $bo) { return strcasecmp($a['label'],$b['label']); }
            return ($ao < $bo) ? -1 : 1;
        });
        return $out;
    }

    public static function map_by_id() : array {
        $out = [];
        foreach ( self::all() as $r ) { $out[(string)$r['id']] = $r; }
        return $out;
    }
}

}