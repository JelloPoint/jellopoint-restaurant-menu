<?php
/** Optional integration test against real WordPress KSES and formatting files. */
$kses = getenv( 'JPRM_TEST_KSES' );
$formatting = getenv( 'JPRM_TEST_FORMATTING' );
if ( ! $kses || ! $formatting ) {
    echo "Icon integration test requires JPRM_TEST_KSES and JPRM_TEST_FORMATTING. Skipped.\n";
    return;
}
define( 'ABSPATH', __DIR__ );
define( 'WPINC', 'wp-includes' );
function apply_filters( $hook, $value, ...$args ) { return $value; }
function wp_allowed_protocols() { return [ 'http', 'https' ]; }
function get_option( $key ) { return 'UTF-8'; }
function wp_load_alloptions() { return [ 'blog_charset' => 'UTF-8' ]; }
require $formatting;
require $kses;
require dirname( __DIR__ ) . '/includes/helpers/icons.php';
foreach ( [ 'badge', 'label' ] as $role ) {
    foreach ( [ 'leaf.svg', 'wine(organic).svg', "wine'quote.svg" ] as $file ) {
        $html = jprm_colorize_icon( '', 'https://example.org/' . $file, $role );
        $clean = wp_kses_post( $html );
        if ( strpos( $clean, '--jprm-icon-image:' ) === false || strpos( $clean, 'url(' ) === false ) {
            throw new RuntimeException( 'WordPress removed the icon URL: ' . $html . ' => ' . $clean );
        }
    }
}
if ( '' !== jprm_svg_mask_span( 'javascript:alert(1)' ) ) {
    throw new RuntimeException( 'Unsafe protocol accepted.' );
}
if ( strpos( wp_kses_post( '<script>alert(1)</script>' ), '<script' ) !== false ) {
    throw new RuntimeException( 'Script filtering was disabled.' );
}
echo "Real WordPress icon filtering passed for badges, labels and unsafe protocols.\n";
