<?php
/**
 * Data: Labels Store
 * Reads from jprm_price_labels (JSON or array), with fallbacks:
 * - jprm_price_labels_v2 (JSON or array)
 * - legacy newline string (e.g., "Small\nMedium\nLarge")
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) {

class JPRM_Labels_Store {
    const OPTION_KEY_PRIMARY = 'jprm_price_labels';
    const OPTION_KEY_FALLBACK = 'jprm_price_labels_v2';

    public static function all() : array {
        // 1) Try primary
        $arr = self::read_option(self::OPTION_KEY_PRIMARY);

        // 2) Fallback to v2 if empty
        if (empty($arr)) {
            $arr = self::read_option(self::OPTION_KEY_FALLBACK);
        }

        // 3) If still empty, try to parse legacy newline list from either key
        if (empty($arr)) {
            $legacy = get_option(self::OPTION_KEY_PRIMARY, '');
            if (!is_string($legacy) || $legacy === '') {
                $legacy = get_option(self::OPTION_KEY_FALLBACK, '');
            }
            if (is_string($legacy) && $legacy !== '') {
                $lines = preg_split("/\r\n|\r|\n/", $legacy);
                $order = 0;
                $tmp = [];
                foreach ($lines as $line) {
                    $t = trim(wp_strip_all_tags((string)$line));
                    if ($t === '') continue;
                    $tmp[] = [
                        'id'      => 'pl-'.sanitize_title($t),
                        'label'   => $t,
                        'slug'    => sanitize_title($t),
                        'icon_id' => 0,
                        'active'  => true,
                        'order'   => $order++,
                    ];
                }
                $arr = $tmp;
            }
        }

        // Normalize / sanitize
        if (!is_array($arr)) $arr = [];
        $out = [];
        foreach ($arr as $row) {
            if (!is_array($row)) continue;
            $label = isset($row['label']) ? (string)$row['label'] : '';
            if ($label === '') continue;

            $id = isset($row['id']) ? (string)$row['id'] : '';
            if ($id === '') $id = sanitize_title($label);

            $out[] = [
                'id'      => $id,
                'label'   => $label,
                'slug'    => isset($row['slug']) ? sanitize_title($row['slug']) : sanitize_title($label),
                'icon_id' => isset($row['icon_id']) ? intval($row['icon_id']) : 0,
                'active'  => array_key_exists('active',$row) ? (bool)$row['active'] : true,
                'order'   => isset($row['order']) ? intval($row['order']) : 0,
            ];
        }

        // Only active + sorted by order, then label
        $out = array_values(array_filter($out, function($r){ return !isset($r['active']) || $r['active']; }));
        usort($out, function($a,$b){
            $ao = $a['order'] ?? 0; $bo = $b['order'] ?? 0;
            if ($ao === $bo) return strcasecmp($a['label'] ?? '', $b['label'] ?? '');
            return ($ao < $bo) ? -1 : 1;
        });

        return $out;
    }

    private static function read_option(string $key) : array {
        $raw = get_option($key, '');
        if (is_array($raw)) return $raw;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }
        return [];
    }

    public static function map_by_id() : array {
        $out = [];
        foreach ( self::all() as $r ) { $out[(string)$r['id']] = $r; }
        return $out;
    }
}
}