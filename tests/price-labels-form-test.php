<?php
/** Integration test: real WordPress filtering must preserve the labels form. */
$kses = getenv( 'JPRM_TEST_KSES' );
$formatting = getenv( 'JPRM_TEST_FORMATTING' );
if ( ! $kses || ! $formatting ) {
    echo "Price labels integration requires JPRM_TEST_KSES and JPRM_TEST_FORMATTING. Skipped.\n";
    return;
}
define( 'ABSPATH', __DIR__ );
define( 'WPINC', 'wp-includes' );
function apply_filters( $hook, $value, ...$args ) { return $value; }
function wp_allowed_protocols() { return [ 'http', 'https' ]; }
function wp_load_alloptions() { return [ 'blog_charset' => 'UTF-8' ]; }
function get_option( $key, $default = false ) { return 'blog_charset' === $key ? 'UTF-8' : ( $GLOBALS['labels'] ?? $default ); }
function update_option( $key, $value ) { $GLOBALS['labels'] = $value; }
function add_action( ...$args ) {}
function current_user_can( $cap ) { return true; }
function is_admin() { return true; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
function wp_nonce_field( ...$args ) {}
function wp_verify_nonce( ...$args ) { return true; }
function checked( $value, $expected ) { if ( $value === $expected ) { echo 'checked="checked"'; } }
function wp_get_attachment_image( ...$args ) { return '<img src="https://example.org/icon.svg" alt="" />'; }
function admin_url( $path ) { return $path; }
function add_query_arg( ...$args ) { return 'saved'; }
class LabelsSaved extends RuntimeException {}
function wp_safe_redirect( $url ) { throw new LabelsSaved(); }
require $formatting;
require $kses;
require getenv( 'JPRM_LABELS_TEST_FILE' ) ?: dirname( __DIR__ ) . '/includes/data/class-labels-store.php';
function labels_check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function labels_form_post() {
    ob_start();
    JPRM_Labels_Store::render_admin_page();
    $html = ob_get_clean();
    $doc = new DOMDocument();
    @$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
    $pairs = [];
    foreach ( $doc->getElementsByTagName( 'input' ) as $input ) {
        labels_check( ! $input->hasAttribute( 'onfocus' ), 'Unsafe attribute escaped its value.' );
        if ( 'checkbox' === $input->getAttribute( 'type' ) && ! $input->hasAttribute( 'checked' ) ) { continue; }
        $pairs[] = urlencode( $input->getAttribute( 'name' ) ) . '=' . urlencode( $input->getAttribute( 'value' ) );
    }
    parse_str( implode( '&', $pairs ), $post );
    return $post;
}
function labels_save( $post ) {
    // WordPress adds slashes to request data before the save handler runs.
    $_POST = wp_slash( $post + [ 'jprm_labels_nonce' => 'test' ] );
    try { JPRM_Labels_Store::handle_save(); } catch ( LabelsSaved $saved ) {}
}
$GLOBALS['labels'] = [];
labels_check( isset( labels_form_post()['labels'][0]['label'] ), 'Empty form lost its input.' );
$GLOBALS['labels'] = [];
for ( $i = 0; $i < 10; ++$i ) {
    $GLOBALS['labels'][] = [ 'id' => 'original_' . $i, 'slug' => 'glass-' . $i,
        'label' => 'Glass " onfocus="alert(1)', 'icon_id' => 12, 'icon_url' => '',
        'active' => 0 !== $i, 'order' => $i ];
}
$expected = $GLOBALS['labels'];
$post = labels_form_post();
labels_check( count( $post['labels'] ?? [] ) === 10, 'Existing label inputs were removed.' );
$post['labels'][] = [ 'id' => 'new_label', 'slug' => 'bottle', 'label' => 'Bottle', 'active' => 1, 'order' => 10 ];
labels_save( $post );
labels_check( count( $GLOBALS['labels'] ) === 11, 'Adding a label deleted existing labels.' );
foreach ( $expected as $i => $row ) {
    labels_check( $GLOBALS['labels'][$i] === $row, 'Existing label data changed.' );
}
labels_save( labels_form_post() );
labels_check( count( $GLOBALS['labels'] ) === 11, 'Repeat save lost labels.' );
labels_check( 'Bottle' === JPRM_Labels_Store::resolve( 'new_label' )['label_text'], 'Frontend lookup failed.' );
$post = labels_form_post();
unset( $post['labels'][10] );
labels_save( $post );
labels_check( count( $GLOBALS['labels'] ) === 10, 'Intentional deletion failed.' );
labels_check( false === strpos( wp_kses_post( '<input name="unsafe"><script>x</script>' ), '<input' ), 'Global post filtering was weakened.' );
echo "Price labels: empty form, add, repeat save, IDs, icons, active flags, deletion and escaping passed.\n";
