<?php
/** Standalone regression checks for Elementor widget price sorting. */

define( 'ABSPATH', __DIR__ . '/' );

set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ): bool {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

$jprm_sort_meta = [
	201 => [ 'jprm_price' => json_encode( [ 'mode' => 'single', 'price' => '1.234,50' ] ) ],
	202 => [ 'jprm_price' => json_encode( [ 'mode' => 'multi', 'rows' => [ [ 'value' => '9,75' ], [ 'value' => '12,00' ] ] ] ) ],
	203 => [ 'jprm_price' => json_encode( [ 'mode' => 'single', 'price' => 'Dagprijs' ] ) ],
	204 => [ 'jprm_price' => json_encode( [ 'mode' => 'single', 'price' => '0' ] ) ],
];

class WP_Query {
	public static int $calls = 0;
	public static array $last_args = [];
	public array $posts = [ 501 ];
	public function __construct( array $args ) {
		self::$calls++;
		self::$last_args = $args;
	}
}

function get_post_meta( $post_id, $key, $single = false ) {
	global $jprm_sort_meta;
	return $jprm_sort_meta[ $post_id ][ $key ] ?? '';
}

require_once dirname( __DIR__ ) . '/stubs/elementor.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-price-schema.php';
require_once dirname( __DIR__ ) . '/includes/storage/class-price-repository.php';
require_once dirname( __DIR__ ) . '/includes/widgets/class-restaurant-menu.php';

use JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu;

function jprm_sort_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$method = new ReflectionMethod( Restaurant_Menu::class, 'jprm_effective_price_number' );
jprm_sort_assert_same( 1234.5, $method->invoke( null, 201 ), 'EU thousands and decimal separators must sort numerically.' );
jprm_sort_assert_same( 9.75, $method->invoke( null, 202 ), 'Canonical multi-price JSON must sort by its first row.' );
jprm_sort_assert_same( INF, $method->invoke( null, 203 ), 'Text prices must sort after numeric prices.' );
jprm_sort_assert_same( 0.0, $method->invoke( null, 204 ), 'A zero price must remain sortable.' );

$widget = new Restaurant_Menu();
$query_method = new ReflectionMethod( Restaurant_Menu::class, 'query_items' );
$without_fallback = $query_method->invoke( $widget, [], [], 'menu_order', 'ASC', 0, false );
jprm_sort_assert_same( [], $without_fallback, 'No filters with fallback disabled must return no items.' );
jprm_sort_assert_same( 0, WP_Query::$calls, 'No filters with fallback disabled must avoid a database query.' );

$filtered = $query_method->invoke( $widget, [ 8 ], [ 12 ], 'title', 'DESC', 25, false );
jprm_sort_assert_same( [ 501 ], $filtered, 'A filtered query must return the WordPress query results.' );
jprm_sort_assert_same( 'AND', WP_Query::$last_args['tax_query']['relation'] ?? '', 'Menu and section filters must be explicitly combined with AND.' );
jprm_sort_assert_same( 25, WP_Query::$last_args['posts_per_page'], 'A positive query limit must be preserved.' );
jprm_sort_assert_same( 'DESC', WP_Query::$last_args['order'], 'Descending order must be preserved.' );

fwrite( STDOUT, "Widget price sorting checks passed.\n" );
