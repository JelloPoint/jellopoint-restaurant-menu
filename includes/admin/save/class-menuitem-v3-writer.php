<?php
namespace JelloPoint\RestaurantMenu\Admin\Save;

if ( ! defined('ABSPATH') ) { exit; }

class MenuItem_V3_Writer {

    /**
     * Write V3 config to a post (schema 3.0 only).
     * $data = [
     *   'desc' => 'text',
     *   'mode' => 'single'|'multi',
     *   'single' => ['price'=>'','label_ref'=>'','hide_icon'=>bool,'icon_id'=>int?],
     *   'rows'   => [ ['label_ref'=>'','value'=>'','hide_icon'=>bool,'icon_id'=>int?], ... ]
     * ];
     */
    public static function write( int $post_id, array $data ) : bool {
        if ( ! current_user_can('edit_post', $post_id ) ) return false;

        $desc = isset($data['desc']) ? wp_kses_post( $data['desc'] ) : '';
        update_post_meta( $post_id, 'jprm_desc', $desc );

        $mode = ($data['mode'] ?? 'single') === 'multi' ? 'multi' : 'single';
        $cfg  = [ '_schema' => '3.0', 'mode' => $mode ];

        if ( $mode === 'single' ) {
            $s = $data['single'] ?? [];
            $cfg['price']     = isset($s['price']) ? (string)$s['price'] : '';
            $cfg['label_ref'] = isset($s['label_ref']) ? (string)$s['label_ref'] : '';
            $cfg['hide_icon'] = ! empty($s['hide_icon']);
            if ( ! empty($s['icon_id']) ) $cfg['icon_id'] = (int) $s['icon_id'];
        } else {
            $rows = [];
            foreach ( (array)($data['rows'] ?? []) as $r ) {
                $val = isset($r['value']) ? (string)$r['value'] : '';
                if ( $val === '' ) continue;
                $row = [
                    'label_ref' => isset($r['label_ref']) ? (string)$r['label_ref'] : '',
                    'value'     => $val,
                    'hide_icon' => ! empty($r['hide_icon']),
                ];
                if ( ! empty($r['icon_id']) ) $row['icon_id'] = (int)$r['icon_id'];
                $rows[] = $row;
            }
            $cfg['rows'] = array_values($rows);
        }

        update_post_meta( $post_id, 'jprm_price', wp_json_encode( $cfg ) );
        return true;
    }
}
