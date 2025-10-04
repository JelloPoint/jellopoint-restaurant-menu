<?php
// File: includes/debug/class-inspector.php
// Minimal inspector to see the exact data your items store.
// Usage (Admins only):
//   [jprm_inspect id="POST_ID"]
// Place it on a private page and view while logged in as admin.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'jprm_arr' ) ) {
    function jprm_arr( $x ) {
        if ( is_string($x) ) {
            $try = json_decode($x, true);
            if ( json_last_error() === JSON_ERROR_NONE ) return $try;
            $maybe = @maybe_unserialize($x);
            if ( is_array($maybe) ) return $maybe;
        }
        return is_array($x) ? $x : (is_object($x) ? (array)$x : $x);
    }
}

if ( ! function_exists( 'jprm_pre' ) ) {
    function jprm_pre( $title, $data ) {
        echo '<h3 style="margin:1em 0 .2em 0;font-family:system-ui;">' . esc_html($title) . '</h3>';
        echo '<pre style="padding:10px;background:#f6f7f7;border:1px solid #ddd;overflow:auto;">' .
             esc_html( print_r( $data, true ) ) . '</pre>';
    }
}

if ( ! function_exists( 'jprm_collect_all_meta' ) ) {
    function jprm_collect_all_meta( $pid ) : array {
        $all = get_post_meta( $pid );
        $flat = [];
        foreach ( $all as $k => $vals ) {
            $flat[$k] = count($vals) === 1 ? $vals[0] : $vals;
        }
        return $flat;
    }
}

if ( ! function_exists( 'jprm_inspector_shortcode' ) ) {
    function jprm_inspector_shortcode( $atts ) {
        if ( ! current_user_can('manage_options') ) {
            return '<div style="color:#a00;">Inspector: admin only.</div>';
        }
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'jprm_inspect' );
        $pid  = (int) $atts['id'];
        if ( $pid <= 0 ) return '<div>No post ID.</div>';

        $post = get_post( $pid );
        if ( ! $post ) return '<div>Post not found.</div>';

        ob_start();

        echo '<div style="border:2px solid #2271b1;padding:14px;border-radius:6px;background:#fff;">';
        echo '<h2 style="margin-top:0;font-family:system-ui;">JPRM Inspector — Post #' . (int)$pid .
             ' <small style="color:#666;">(' . esc_html($post->post_type) . ')</small></h2>';

        // Raw meta (flattened)
        $raw = jprm_collect_all_meta( $pid );
        jprm_pre( 'RAW META (flattened)', $raw );

        // Description candidates
        $desc_candidates = [
            'jprm_description', '_jprm_description', 'description', '_description',
            // fallbacks
            'post_excerpt (from post table)', 'post_content (from post table)',
        ];
        $desc_found = '';
        foreach ( [ 'jprm_description','_jprm_description','description','_description' ] as $k ) {
            $v = get_post_meta( $pid, $k, true );
            if ( is_string($v) && $v !== '' ) { $desc_found = "meta:{$k} = {$v}"; break; }
        }
        if ( $desc_found === '' ) {
            $ex = get_post_field( 'post_excerpt', $pid );
            if ( is_string($ex) && $ex !== '' ) {
                $desc_found = 'post_excerpt = ' . $ex;
            } else {
                $ct = get_post_field( 'post_content', $pid );
                $clean = is_string($ct) ? trim( wp_strip_all_tags( strip_shortcodes( $ct ) ) ) : '';
                $desc_found = 'post_content(cleaned) = ' . $clean;
            }
        }
        jprm_pre( 'DESCRIPTION (source → value)', $desc_found );

        // New v3 JSON
        $v3 = get_post_meta( $pid, 'jprm_price', true );
        jprm_pre( 'jprm_price (v3 JSON, decoded)', jprm_arr( $v3 ) );

        // Legacy rows JSON
        $rows_json = get_post_meta( $pid, 'jprm_price_rows', true );
        jprm_pre( 'jprm_price_rows (legacy JSON, decoded)', jprm_arr( $rows_json ) );

        // Broad prices keys
        $broad = [
            'jprm_prices' => jprm_arr( get_post_meta( $pid, 'jprm_prices', true ) ),
            'prices'      => jprm_arr( get_post_meta( $pid, 'prices', true ) ),
        ];
        jprm_pre( 'BROAD prices (decoded)', $broad );

        // Legacy split arrays
        $split = [
            '_jprm_price_amounts'   => jprm_arr( get_post_meta( $pid, '_jprm_price_amounts', true ) ),
            '_jprm_price_labels'    => jprm_arr( get_post_meta( $pid, '_jprm_price_labels', true ) ),
            '_jprm_price_hideicons' => jprm_arr( get_post_meta( $pid, '_jprm_price_hideicons', true ) ),
        ];
        jprm_pre( 'Legacy split arrays', $split );

        // Single price + single label/meta
        $single = [
            'single_price'           => get_post_meta( $pid, 'single_price', true ),
            '_jprm_single_label_ref' => get_post_meta( $pid, '_jprm_single_label_ref', true ),
            '_jprm_single_hide_icon' => (bool) get_post_meta( $pid, '_jprm_single_hide_icon', true ),
            '_jprm_labels'           => jprm_arr( get_post_meta( $pid, '_jprm_labels', true ) ),
            '_jprm_hide_icon'        => (bool) get_post_meta( $pid, '_jprm_hide_icon', true ),
        ];
        jprm_pre( 'SINGLE price + label meta', $single );

        // Labels registry
        $labels_opt = get_option( 'jprm_price_labels_v2' );
        $labels     = is_string($labels_opt) ? json_decode($labels_opt, true) : ( is_array($labels_opt) ? $labels_opt : [] );
        jprm_pre( 'LABEL REGISTRY (jprm_price_labels_v2)', $labels );

        echo '</div>';

        $html = ob_get_clean();
        return $html;
    }
    add_shortcode( 'jprm_inspect', 'jprm_inspector_shortcode' );
}
