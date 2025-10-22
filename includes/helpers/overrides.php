<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Generic resolver:
 * - If a per-section override exists for $layout/$key → return it
 * - else return the global value at $global_ctx_key
 */
function jprm_effective_section_value( array $ctx, int $section_id, string $layout, string $key, string $global_ctx_key ) : string {
    $section_id = (int) $section_id;
    if ( $section_id > 0 && isset( $ctx['section_overrides'][$section_id][$layout][$key] ) ) {
        $val = (string) $ctx['section_overrides'][$section_id][$layout][$key];
        return trim( $val );
    }
    $global = isset( $ctx[$global_ctx_key] ) ? (string) $ctx[$global_ctx_key] : '';
    return trim( $global );
}

/** Convenience wrappers */
function jprm_effective_matrix_placeholder( array $ctx, int $section_id ) : string {
    return jprm_effective_section_value( $ctx, $section_id, 'matrix', 'placeholder', 'labels_matrix_placeholder' );
}
function jprm_effective_inline_below_separator( array $ctx, int $section_id ) : string {
    return jprm_effective_section_value( $ctx, $section_id, 'inline_below', 'separator', 'inline_below_separator' );
}
