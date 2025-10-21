<?php
/**
 * Admin Save Normalizer: transforms $_POST into Price Schema v3.
 */
namespace JelloPoint\RestaurantMenu\Admin\Save;

if ( ! defined('ABSPATH') ) { exit; }

class MenuItem_Save_Normalizer {

    /**
     * Build schema v3 from POST data.
     * Expected POST fields:
     *  - jprm_mode: 'single'|'multi'
     *  - SINGLE: jprm_single_price, jprm_single_label_ref, jprm_single_hide_icon
     *  - MULTI : jprm_rows_label_ref[], jprm_rows_value[], jprm_rows_hide_icon[]
     */
    public static function from_post( array $post ) : array {
        $mode = isset($post['jprm_mode']) ? strtolower(trim((string)$post['jprm_mode'])) : '';
        if ( $mode === 'single' ) {
            $price = trim( (string) ( $post['jprm_single_price'] ?? '' ) );
            $label = trim( (string) ( $post['jprm_single_label_ref'] ?? '' ) );
            $hide  = ! empty( $post['jprm_single_hide_icon'] );
            if ( $price === '' ) return [];
            return [
                'mode'      => 'single',
                'price'     => $price,
                'label_ref' => $label,
                'hide_icon' => $hide,
            ];
        }

        if ( $mode === 'multi' ) {
            $labels = $post['jprm_rows_label_ref'] ?? [];
            $values = $post['jprm_rows_value'] ?? [];
            $hides  = $post['jprm_rows_hide_icon'] ?? [];

            // Normalize arrays
            $labels = is_array($labels) ? array_values($labels) : [];
            $values = is_array($values) ? array_values($values) : [];
            $hides  = is_array($hides)  ? array_values($hides)  : [];

            $max = max(count($labels), count($values), count($hides));
            $rows = [];
            for ( $i = 0; $i < $max; $i++ ) {
                $val = isset($values[$i]) ? trim((string)$values[$i]) : '';
                if ( $val === '' ) continue;
                $rows[] = [
                    'label_ref' => isset($labels[$i]) ? trim((string)$labels[$i]) : '',
                    'value'     => $val,
                    'hide_icon' => ! empty($hides[$i]),
                ];
            }
            if ( empty($rows) ) return [];
            return [ 'mode' => 'multi', 'rows' => $rows ];
        }

        return [];
    }
}