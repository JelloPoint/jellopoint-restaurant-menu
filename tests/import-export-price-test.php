<?php
/** Standalone lossless price export checks. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_transfer_meta = [
	101 => [
		'jprm_price' => json_encode( [
			'mode' => 'single', 'price' => '14,50', 'label_ref' => 'Chef', 'icon_id' => 289, 'hide_icon' => true,
		] ),
		'jprm_price_label_mode' => 'custom',
		'jprm_desc' => 'Single item',
		'jprm_item_badges' => [ 'vegan' ],
	],
	102 => [
		'jprm_price' => json_encode( [
			'mode' => 'multi',
			'rows' => [
				[ 'value' => '9', 'label_ref' => 'Small', 'icon_id' => 301, 'hide_icon' => false ],
				[ 'value' => '12', 'label_ref' => 'Large', 'icon_id' => 302, 'hide_icon' => true ],
			],
		] ),
		'jprm_prices' => json_encode( [
			[ 'label_mode' => 'ref', 'label_ref' => 'Small', 'amount' => '9', 'icon_id' => 301 ],
			[ 'label_mode' => 'custom', 'label_custom' => 'Large', 'amount' => '12', 'icon_id' => 302, 'hide_icon' => true ],
		] ),
		'jprm_desc' => 'Multi item',
	],
];

$jprm_transfer_options = [];

class WP_Query {
	public array $posts = [ 101, 102 ];
	public function __construct( array $args ) {}
}

function get_post_meta( $post_id, $key, $single = false ) {
	global $jprm_transfer_meta;
	return $jprm_transfer_meta[ $post_id ][ $key ] ?? '';
}
function update_post_meta( $post_id, $key, $value ) {
	global $jprm_transfer_meta;
	$jprm_transfer_meta[ $post_id ][ $key ] = $value;
	return true;
}
function get_the_title( $post_id ) { return 'Item ' . $post_id; }
function get_post_status( $post_id ) { return 'publish'; }
function wp_get_object_terms( $post_id, $taxonomy, $args ) { return []; }
function is_wp_error( $value ) { return false; }
function get_option( $key, $default = '' ) {
	global $jprm_transfer_options;
	return $jprm_transfer_options[ $key ] ?? $default;
}
function update_option( $key, $value ) {
	global $jprm_transfer_options;
	$jprm_transfer_options[ $key ] = $value;
	return true;
}
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; }
function sanitize_text_field( $value ) { return preg_replace( '/\s+/', ' ', trim( (string) $value ) ); }
function wp_kses_post( $value ) { return str_replace( "\r\n", "\n", (string) $value ); }

require_once dirname( __DIR__ ) . '/includes/storage/class-price-schema.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-price-repository.php';
require_once dirname( __DIR__ ) . '/includes/data/class-exporter.php';
require_once dirname( __DIR__ ) . '/includes/data/class-importer.php';

use JelloPoint\RestaurantMenu\Data\JPRM_Exporter;
use JelloPoint\RestaurantMenu\Data\JPRM_Importer;

function jprm_transfer_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$method = new ReflectionMethod( JPRM_Exporter::class, 'collect_items' );
$items = $method->invoke( null );

jprm_transfer_assert_same( 'custom', $items[0]['prices']['label_mode'], 'Single custom label mode must survive export.' );
jprm_transfer_assert_same( 'Chef', $items[0]['prices']['label_custom'], 'Single custom label text must survive export.' );
jprm_transfer_assert_same( 289, $items[0]['prices']['icon_id'], 'Single custom icon must survive export.' );
jprm_transfer_assert_same( true, $items[0]['prices']['hide_icon'], 'Single icon visibility must survive export.' );
jprm_transfer_assert_same( 301, $items[1]['prices']['rows'][0]['icon_id'], 'Multi-price icon must survive export.' );
jprm_transfer_assert_same( 'custom', $items[1]['prices']['rows'][1]['label_mode'], 'Multi custom label mode must survive export.' );
jprm_transfer_assert_same( 'Large', $items[1]['prices']['rows'][1]['label_custom'], 'Multi custom label must survive export.' );
jprm_transfer_assert_same( true, $items[1]['prices']['rows'][1]['hide_icon'], 'Multi icon visibility must survive export.' );

$result_method = new ReflectionMethod( JPRM_Importer::class, 'result_row' );
$reported = $result_method->invoke( null, 101, 101, 'Item 101', 'single', '14,50', [], [], '', 'unchanged' );
jprm_transfer_assert_same( 'unchanged', $reported['action'], 'Dry-run reports must preserve the calculated unchanged action.' );

fwrite( STDOUT, "Import/export price compatibility checks passed.\n" );
