<?php
/** Standalone regression checks for Phase 11A print settings and wiring. */

define( 'ABSPATH', __DIR__ . '/' );

$jprm_print_option = [];
function get_option( $key, $default = false ) { global $jprm_print_option; return $jprm_print_option ?: $default; }
function update_option( $key, $value, $autoload = null ) { global $jprm_print_option; $jprm_print_option = $value; return true; }
function sanitize_hex_color( $color ) { return is_string( $color ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtolower( $color ) : null; }

require_once dirname( __DIR__ ) . '/includes/data/class-print-document-settings.php';

use JelloPoint\RestaurantMenu\Data\Print_Document_Settings;

function jprm_print_assert_same( $expected, $actual, string $message ) : void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$settings = Print_Document_Settings::sanitize( [
	'menu_id' => '18', 'preset' => 'modern', 'paper_size' => 'letter', 'orientation' => 'landscape',
	'margins' => [ 'top' => '-2', 'right' => '12.5', 'bottom' => '99', 'left' => 'invalid' ],
	'columns' => 7, 'column_breaks' => [ '12', 12, -1, 0 ], 'logo_id' => '44', 'logo_position' => 'right',
	'heading_font' => 'modern', 'body_font' => 'classic', 'text_color' => '#123ABC', 'accent_color' => 'invalid',
	'title_size' => 100, 'item_spacing' => -4, 'show_descriptions' => '0', 'show_badges' => '1',
] );
jprm_print_assert_same( 18, $settings['menu_id'], 'The selected existing Menu ID must be normalized.' );
jprm_print_assert_same( 'modern', $settings['preset'], 'A supported dedicated print preset must be stored.' );
jprm_print_assert_same( 'a4', $settings['paper_size'], 'Phase 11A must allow only A4.' );
jprm_print_assert_same( 'landscape', $settings['orientation'], 'Landscape must be supported.' );
jprm_print_assert_same( [ 'top' => 0, 'right' => 12.5, 'bottom' => 50, 'left' => 15.0 ], $settings['margins'], 'Margins must be clamped to 0–50 mm with safe defaults.' );
jprm_print_assert_same( 3, $settings['columns'], 'Print columns must be limited to one through three.' );
jprm_print_assert_same( [ 12 ], $settings['column_breaks'], 'Section column breaks must be normalized and deduplicated.' );
jprm_print_assert_same( 44, $settings['logo_id'], 'The selected Media Library logo must be retained.' );
jprm_print_assert_same( '#123abc', $settings['text_color'], 'Valid custom colors must be normalized.' );
jprm_print_assert_same( '#173f47', $settings['accent_color'], 'Invalid custom colors must use the safe default.' );
jprm_print_assert_same( 60.0, $settings['title_size'], 'Typography sizes must stay inside printable limits.' );
jprm_print_assert_same( 0.0, $settings['item_spacing'], 'Spacing values must not become negative.' );
jprm_print_assert_same( false, $settings['show_descriptions'], 'Visible elements must be individually switchable.' );
jprm_print_assert_same( true, $settings['show_badges'], 'Dietary Badges must remain independently switchable.' );

jprm_print_assert_same( true, Print_Document_Settings::save( $settings ), 'Validated document settings must persist.' );
jprm_print_assert_same( $settings, Print_Document_Settings::get(), 'Saved document settings must round-trip.' );

$main = file_get_contents( dirname( __DIR__ ) . '/jellopoint-restaurant-menu.php' );
$admin = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-print-document.php' );
$builder = file_get_contents( dirname( __DIR__ ) . '/includes/data/class-print-document-builder.php' );
$renderer = file_get_contents( dirname( __DIR__ ) . '/includes/render/class-print-document-renderer.php' );
$template = file_get_contents( dirname( __DIR__ ) . '/includes/render/print/document.php' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/print-document.css' );
jprm_print_assert_same( true, false !== strpos( $main, 'class-print-document-builder.php' ), 'The print document pipeline must load in every context.' );
jprm_print_assert_same( true, false !== strpos( $admin, 'admin_post_jprm_save_print_document' ), 'The settings form must use a protected admin handler.' );
jprm_print_assert_same( true, false !== strpos( $admin, 'wp_enqueue_media' ), 'Logo selection must use the WordPress Media Library.' );
jprm_print_assert_same( true, false !== strpos( $builder, 'Menu_Structure_Store::get' ), 'Print data must reuse the canonical per-Menu structure.' );
jprm_print_assert_same( true, false !== strpos( $builder, 'Price_Repository::get' ), 'Print data must reuse canonical prices.' );
jprm_print_assert_same( true, false !== strpos( $builder, 'JPRM_Badges_Store' ), 'Print data must reuse Dietary Badges and icons.' );
jprm_print_assert_same( true, false !== strpos( $renderer, 'price_html' ), 'The standalone renderer must support canonical prices and Price Labels.' );
jprm_print_assert_same( true, false !== strpos( $template, 'jprm-print--' ), 'The document must apply its selected print preset.' );
foreach ( [ 'classic', 'modern', 'elegant' ] as $preset ) {
	jprm_print_assert_same( true, false !== strpos( $css, '.jprm-print--' . $preset ), ucfirst( $preset ) . ' must have dedicated print styling.' );
}
jprm_print_assert_same( true, false !== strpos( $css, 'column-count: var(--jprm-columns)' ), 'The print layout must support configurable columns.' );
jprm_print_assert_same( true, false !== strpos( $css, 'jprm-print--hide-badges' ), 'Optional print elements must be hideable without changing Menu content.' );
jprm_print_assert_same( true, false !== strpos( $css, 'var(--jprm-section-border)' ), 'Sections must support configurable decorative borders.' );
jprm_print_assert_same( true, false !== strpos( $template, 'render_info_blocks' ), 'Reusable Info Blocks must reach the print template.' );
jprm_print_assert_same( true, false !== strpos( $template, '--jprm-info-background' ), 'Print Info Block styles must reach the document CSS variables.' );
jprm_print_assert_same( true, false !== strpos( $template, 'window.print()' ), 'Print preview must provide the browser PDF/print action.' );
jprm_print_assert_same( true, false !== strpos( $template, 'auto_print' ), 'Direct PDF/print workflow must support opening the browser dialog automatically.' );
jprm_print_assert_same( true, false !== strpos( $template, 'Headers and footers' ), 'Preview must explain how to remove browser-added URLs from PDF output.' );
jprm_print_assert_same( true, false !== strpos( $css, '@media screen and (max-width: 900px)' ), 'Narrow-screen fallback must never override PDF column settings.' );

fwrite( STDOUT, "Print/PDF foundation checks passed.\n" );
