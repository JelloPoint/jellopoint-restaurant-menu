<?php
/** Standalone checks for the bundled demo menu. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_demo_options = [];

function get_option( $key, $default = false ) {
	global $jprm_demo_options;
	return $jprm_demo_options[ $key ] ?? $default;
}
function absint( $value ) { return abs( (int) $value ); }

require_once dirname( __DIR__ ) . '/includes/data/class-demo-menu.php';

use JelloPoint\RestaurantMenu\Data\JPRM_Demo_Menu;

function jprm_demo_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$items   = JPRM_Demo_Menu::items();
$summary = JPRM_Demo_Menu::summary();

jprm_demo_assert_same( 23, count( $items ), 'The demo must contain the complete 23-item catalogue.' );
jprm_demo_assert_same( 23, $summary['items'], 'The admin summary must match the bundled item count.' );
jprm_demo_assert_same(
	[ 'Starters', 'Mains', 'Desserts', 'Salads', 'Drinks', 'Wine', 'Beer' ],
	$summary['sections'],
	'The requested sections and drink subsections must be present.'
);

$by_title = [];
foreach ( $items as $item ) {
	$by_title[ $item['post_title'] ] = $item;
	jprm_demo_assert_same( [ 'JelloPoint Demo Menu' ], $item['tax']['jprm_menu'], 'Every item must belong to the demo menu.' );
}

$wine_rows = $by_title['Sauvignon Blanc']['prices']['rows'];
jprm_demo_assert_same( 'custom', $wine_rows[0]['label_mode'], 'Wine prices must use visible custom labels.' );
jprm_demo_assert_same( 'Glass', $wine_rows[0]['label_custom'], 'Wine must have a glass price.' );
jprm_demo_assert_same( 'Bottle', $wine_rows[1]['label_custom'], 'Wine must have a bottle price.' );
jprm_demo_assert_same( [ 'contains-alcohol' ], $by_title['Sauvignon Blanc']['badges'], 'Alcoholic drinks must carry the alcohol badge.' );

$beer_rows = $by_title['JelloPoint Pilsner']['prices']['rows'];
jprm_demo_assert_same( '250 ml', $beer_rows[0]['label_custom'], 'Beer must demonstrate a small serving price.' );
jprm_demo_assert_same( '500 ml', $beer_rows[1]['label_custom'], 'Beer must demonstrate a large serving price.' );
jprm_demo_assert_same( [ 'vegan', 'gluten-free', 'spicy' ], $by_title['Thai Green Vegetable Curry']['badges'], 'Demo dishes must demonstrate multiple dietary badges.' );

$resolved_names = [
	'menu'     => 'JelloPoint Demo Menu',
	'sections' => [ 'Starters' => 'Starters (Demo)', 'Mains' => 'Mains' ],
];
$resolved_items = JPRM_Demo_Menu::items( $resolved_names );
jprm_demo_assert_same( [ 'Starters (Demo)' ], $resolved_items[0]['tax']['jprm_section'], 'Items must use the safely resolved section name.' );

$jprm_demo_options['jprm_demo_menu_v1'] = [
	'menu_term_id' => 77,
	'post_ids'     => range( 101, 123 ),
];
$repeat_report = JPRM_Demo_Menu::run( false );
jprm_demo_assert_same( 0, $repeat_report['created'], 'A repeated import must create no items.' );
jprm_demo_assert_same( 23, $repeat_report['unchanged'], 'A repeated import must report all demo items unchanged.' );
jprm_demo_assert_same( 101, $repeat_report['items'][0]['post_id_new'], 'A repeated import must retain the recorded item IDs.' );

fwrite( STDOUT, "Demo menu checks passed.\n" );
