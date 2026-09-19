<?php
/** Test compiler fail-closed behavior and, when supplied, the actual built ZIPs. */
require_once dirname( __DIR__ ) . '/tools/build-packages.php';
function package_check( $ok, string $message ) : void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
// The repository readme is the canonical product description for both editions.
// Keep these checks before the no-artifact return so CI catches a shortened source.
$source_readme = str_replace( "\r\n", "\n", file_get_contents( dirname( __DIR__ ) . '/readme.txt' ) );
foreach ( [ '= Free features =', '= JelloPoint Pro =', '= Elementor integration =', '== Screenshots ==', 'External service: Freemius' ] as $section ) {
	package_check( false !== strpos( $source_readme, $section ), 'Full product readme section missing: ' . $section );
}
preg_match( '/^ \* Version:\s*(\S+)/m', file_get_contents( dirname( __DIR__ ) . '/jellopoint-restaurant-menu.php' ), $version_match );
package_check( isset( $version_match[1] ), 'Plugin version missing.' );
package_check( false !== strpos( $source_readme, 'Stable tag: ' . $version_match[1] . "\n" ), 'Readme stable tag differs from plugin version.' );
package_check( false !== strpos( $source_readme, '= ' . $version_match[1] . ' =' ), 'Current release changelog missing.' );

$regions = ['example' => ['file' => 'sample.php', 'free' => "free();\n"]];
$seen = [];
$input = "before();\n// JPRM_PRO_BEGIN:example\npremium();\n// JPRM_PRO_END:example\nafter();\n";
package_check( "before();\nfree();\nafter();\n" === JPRM_Package_Builder::transform( $input, 'sample.php', false, $regions, $seen ), 'Free region replacement failed.' );
$seen = [];
package_check( "before();\npremium();\nafter();\n" === JPRM_Package_Builder::transform( $input, 'sample.php', true, $regions, $seen ), 'Pro region preservation failed.' );
foreach ( [str_replace( 'END:example', 'END:other', $input ), str_replace( '// JPRM_PRO_END:example', '', $input ), $input . $input, str_replace( 'premium();', '// JPRM_PRO_BEGIN:example', $input )] as $bad ) {
	$seen = [];
	try { JPRM_Package_Builder::transform( $bad, 'sample.php', false, $regions, $seen ); throw new LogicException( 'Malformed regions accepted.' ); }
	catch ( RuntimeException $expected ) { /* Must reject invalid input. */ }
}
if ( empty( $argv[1] ) ) { echo "Build compiler checks passed. CI also tests both actual artifacts.\n"; return; }
$base = realpath( $argv[1] );
package_check( false !== $base, 'Missing build directory.' );
$report = json_decode( file_get_contents( $base . '/build-report.json' ), true, 512, JSON_THROW_ON_ERROR );
$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/tools/package-files.json' ), true );
foreach ( ['free' => false, 'pro' => true] as $edition => $premium ) {
	$slug = 'jellopoint-restaurant-menu' . ( $premium ? '-premium' : '' );
	$dir = "$base/$slug";
	$expected = $premium ? $manifest : array_values( array_diff( $manifest, JPRM_Package_Builder::PRO_FILES ) );
	$actual = [];
	$zip = new PharData( "$base/$slug.zip" );
	package_check( count( $zip ) === count( $expected ), "$edition ZIP file count differs." );
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$path = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
		$actual[] = $path;
		$content = file_get_contents( $file->getPathname() );
		package_check( isset( $zip["$slug/$path"] ) && $content === $zip["$slug/$path"]->getContent(), "ZIP differs: $path" );
		package_check( hash( 'sha256', $content ) === ( $report[$edition]['files'][$path] ?? '' ), "Checksum differs: $path" );
		if ( 'php' === $file->getExtension() ) { token_get_all( $content, TOKEN_PARSE ); }
		if ( 0 !== strpos( $path, 'vendor/' ) ) {
			package_check( ! preg_match( '/JPRM_PRO_(BEGIN|END):/', $content ), "Unprocessed marker: $path" );
			package_check( ! preg_match( '/[\'"]secret_key[\'"]\s*=>|define\s*\(\s*[\'"]WP_FS__/', $content ), "Secret/dev setting in $path" );
			if ( ! $premium && 'php' === $file->getExtension() ) {
				package_check( ! preg_match( '/function\s+(jprm_daily_menu_is_active|jprm_daily_menu_display_data|get_info_blocks|save_info_blocks|daily_menu_toggle_script|item_separator_dependency_script)\s*\(/', $content ), "Pro implementation in Free: $path" );
			}
		}
	}
	sort( $expected ); sort( $actual );
	package_check( $actual === $expected, "$edition manifest differs." );
	$main = file_get_contents( "$dir/jellopoint-restaurant-menu.php" );
	package_check( false !== strpos( $main, 'Plugin Name:       JelloPoint – Restaurant Menu' ), "$edition plugin name is wrong." );
	package_check( false === strpos( $main, 'Plugin Name:       JelloPoint – Restaurant Menu Pro' ), "$edition plugin name is hard-coded as Pro." );
	package_check( false !== strpos( $main, "'is_premium' => " . ( $premium ? 'true' : 'false' ) ), "$edition SDK identity is wrong." );
	package_check( false !== strpos( $main, "'premium_suffix' => ''" ), "$edition can receive an automatic Premium name suffix." );
	package_check( false !== strpos( $main, "Version:           2.0.51" ), "$edition plugin version is wrong." );
	package_check( false !== strpos( $main, "define( 'JPRM_VERSION', '2.0.51' )" ), "$edition runtime version is wrong." );
	package_check( $premium === ( false !== strpos( $main, "'wp_org_gatekeeper'" ) ), "$edition gatekeeper is wrong." );
	package_check( false !== strpos( $main, 'set_basename( ' . ( $premium ? 'true' : 'false' ) ), 'Double activation wrapper missing.' );
	foreach ( ['includes/storage/class-price-schema.php', 'includes/render/class-price-renderer.php', 'includes/admin/class-admin-info-blocks.php', 'includes/data/class-default-data.php', 'includes/class-uninstaller.php', 'vendor/freemius/LICENSE.txt', 'wpml-config.xml'] as $shared ) {
		package_check( is_file( "$dir/$shared" ), "Shared file missing: $shared" );
	}
	package_check( ! is_file( "$dir/uninstall.php" ), 'Freemius packages must not contain root uninstall.php.' );
	foreach ( [ 'tests', 'tools', 'stubs', 'docs', '.github', '.vscode', 'package', 'composer.json', 'composer.lock', 'phpcs.xml', 'phpstan.neon', 'phpstan-baseline.neon', 'CONTRIBUTING.md' ] as $development_path ) {
		package_check( ! file_exists( "$dir/$development_path" ), "Development-only path leaked into $edition: $development_path" );
	}
	package_check( $source_readme === str_replace( "\r\n", "\n", file_get_contents( "$dir/readme.txt" ) ), "$edition readme differs from the full canonical product description." );
	foreach ( JPRM_Package_Builder::PRO_FILES as $pro_file ) { package_check( $premium === is_file( "$dir/$pro_file" ), "Wrong edition: $pro_file" ); }
	$builder = file_get_contents( "$dir/includes/admin/assets/jprm-menu-builder.js" );
	package_check( $premium === ( false !== strpos( $builder, 'menu-builder/info-blocks' ) ), 'Print placement JS has wrong edition.' );
	$price_schema = file_get_contents( "$dir/includes/storage/class-price-schema.php" );
	$price_editor = file_get_contents( "$dir/includes/admin/class-admin-menuitem-meta.php" );
	$price_style  = file_get_contents( "$dir/includes/widgets/traits/restaurant-menu-style.php" );
	package_check( $premium === ( false !== strpos( $price_schema, 'normalize_multi__premium_only' ) ), 'Multiple Prices schema has wrong edition.' );
	package_check( $premium === ( false !== strpos( $price_editor, 'function render_multiple_pricing__premium_only' ) ), 'Multiple Prices editor has wrong edition.' );
	package_check( $premium === ( false !== strpos( $price_style, 'jprm_price_rows_gap' ) ), 'Multiple Prices Elementor controls have wrong edition.' );
	if ( ! $premium ) {
		package_check( false === strpos( $price_editor, 'id="jprm2-prices-table"' ), 'Multiple Prices UI leaked into Free.' );
		package_check( false !== strpos( $price_editor, 'stored prices are preserved' ), 'Free downgrade data-retention notice missing.' );
	}
	echo "$edition: manifest, archive, checksums, PHP syntax, SDK identity and physical module separation passed.\n";
}
