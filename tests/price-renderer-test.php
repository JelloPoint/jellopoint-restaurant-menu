<?php
/** Standalone regression checks for price HTML rendering. */

define( 'ABSPATH', __DIR__ . '/' );

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
	return '<img data-id="' . (int) $id . '" class="' . esc_attr( $attrs['class'] ?? '' ) . '">';
}

class JPRM_Labels_Store {
	public static function resolve( $ref ): array {
		if ( 'Bundled' === $ref ) {
			return [ 'label_text' => 'Bundled', 'icon_id' => 0, 'icon_url' => 'https://example.test/bottle.svg' ];
		}
		return [ 'label_text' => (string) $ref, 'icon_id' => 12 ];
	}
}

require_once dirname( __DIR__ ) . '/includes/data/class-price-schema.php';
require_once dirname( __DIR__ ) . '/includes/render/class-price-renderer.php';

use JelloPoint\RestaurantMenu\Render\Price_Renderer;

function jprm_render_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$single_html = Price_Renderer::render_pricegroup(
	[
		'mode'      => 'single',
		'price'     => '12,50',
		'label_ref' => 'Lunch',
		'icon_id'   => 289,
		'hide_icon' => false,
	],
	[
		'presentation' => 'icon_text',
		'order_class'  => 'jp-order--label-left',
		'currency'     => [ 'show' => true, 'symbol' => '€', 'position' => 'before', 'spacing' => 'thin' ],
	]
);

jprm_render_assert( str_contains( $single_html, 'data-id="289"' ), 'An explicit item icon must override the label-store icon.' );
jprm_render_assert( str_contains( $single_html, 'jp-order--label-left' ), 'The configured label order must remain in the HTML.' );
jprm_render_assert( str_contains( $single_html, 'jp-menu__currency">€' ), 'Currency options must remain in the HTML.' );

$multi_html = Price_Renderer::render_pricegroup(
	[
		'mode' => 'multi',
		'rows' => [
			[ 'value' => '8', 'label_ref' => 'Small', 'icon_id' => 301, 'hide_icon' => true ],
			[ 'value' => '10', 'label_ref' => 'Large', 'icon_id' => 302, 'hide_icon' => false ],
		],
	]
);

jprm_render_assert( ! str_contains( $multi_html, 'data-id="301"' ), 'A hidden icon must not be rendered.' );
jprm_render_assert( str_contains( $multi_html, 'data-id="302"' ), 'A visible explicit multi-price icon must be rendered.' );
jprm_render_assert( 2 === substr_count( $multi_html, 'class="jp-menu__row ' ), 'Every valid multi-price row must render exactly once.' );

$bundled_html = Price_Renderer::render_pricegroup(
	[ 'mode' => 'single', 'price' => '5', 'label_ref' => 'Bundled', 'hide_icon' => false ],
	[ 'presentation' => 'icon_text' ]
);
jprm_render_assert( str_contains( $bundled_html, 'bottle.svg' ), 'A bundled label icon URL must render when no attachment icon is selected.' );

fwrite( STDOUT, "Price renderer regression checks passed.\n" );
