<?php
/** Configuration/package checks; real SDK activation requires a WordPress sandbox. */
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/jellopoint-restaurant-menu.php' );
function fs_check( $ok, $message ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
fs_check( strpos( $main, 'fs_dynamic_init(' ) < strpos( $main, 'class-plugin.php' ), 'SDK must initialize before core.' );
fs_check( ! preg_match( '/[\'"]secret_key[\'"]\s*=>|WP_FS__.*SECRET_KEY|WP_FS__DEV_MODE/', $main ), 'Do not ship secrets or force sandbox mode.' );
preg_match( '/fs_dynamic_init\( (array\(.*?\n\t\t\t\)) \);/s', $main, $match );
fs_check( isset( $match[1] ), 'SDK settings must be a literal array.' );
$config = eval( 'return ' . $match[1] . ';' );
fs_check( $config['id'] === '39068' && $config['slug'] === 'jellopoint-restaurant-menu', 'Incorrect product.' );
fs_check( $config['menu']['slug'] === 'jellopoint', 'Incorrect admin parent.' );
fs_check( $config['is_premium'] && $config['has_paid_plans'] && ! $config['has_addons'], 'Incorrect Pro configuration.' );
fs_check( '' === $config['premium_suffix'], 'Freemius must not append a Pro suffix to the neutral plugin name.' );
fs_check( ! empty( $config['wp_org_gatekeeper'] ), 'Combined Pro build needs submission safeguard.' );
$premium_files = [
	'/includes/admin/class-admin-import-export.php',
	'/includes/admin/class-admin-print-document.php',
	'/includes/admin/views/import-export-page.php',
	'/includes/data/class-importer.php',
	'/includes/data/class-exporter.php',
	'/includes/data/class-demo-menu.php',
	'/includes/data/class-print-document-settings.php',
	'/includes/data/class-print-document-builder.php',
	'/includes/render/class-print-document-renderer.php',
	'/includes/render/print/document.php',
];
foreach ( $premium_files as $premium_file ) {
	fs_check( false !== strpos( $main, $premium_file ), 'Freemius premium-only declaration is missing: ' . $premium_file );
}
foreach ( [ 'start.php', 'require.php', 'config.php', 'LICENSE.txt', 'includes/class-freemius.php' ] as $file ) {
	fs_check( is_file( $root . '/vendor/freemius/' . $file ), 'Missing SDK file: ' . $file );
}
$sdk = json_decode( file_get_contents( $root . '/vendor/freemius/composer.json' ), true );
fs_check( $sdk['license'] === 'GPL-3.0-only', 'Review SDK licensing after upgrade.' );
foreach ( [ 'ci-dev.yml', 'ci-harden.yml' ] as $workflow ) {
	$build = file_get_contents( $root . '/.github/workflows/' . $workflow );
	fs_check( strpos( $build, 'php tools/build-packages.php' ) !== false, 'CI must use the audited edition builder.' );
	fs_check( strpos( $build, 'php tests/package-distribution-test.php' ) !== false, 'CI must verify actual packages.' );
}
$loader = file_get_contents( $root . '/includes/modules/class-module-loader.php' );
$rest = file_get_contents( $root . '/includes/rest/class-jprm-menu-builder-controller.php' );
$builder_js = file_get_contents( $root . '/includes/admin/assets/jprm-menu-builder.js' );
$builder_view = file_get_contents( $root . '/includes/admin/views/jprm-menu-builder.php' );
fs_check( substr_count( $loader, 'is__premium_only()' ) >= 2, 'Freemius cannot strip premium module loader references.' );
fs_check( false !== strpos( $rest, 'get_info_blocks__premium_only' ) && false !== strpos( $rest, 'save_info_blocks__premium_only' ), 'Freemius cannot strip premium REST callbacks.' );
fs_check( substr_count( $builder_js, '<fs_premium_only>' ) >= 2, 'Freemius JavaScript stripping markers are missing.' );
fs_check( false !== strpos( $builder_view, 'is__premium_only()' ), 'Freemius cannot strip the Print/PDF builder panel.' );
$readme = file_get_contents( $root . '/readme.txt' );
fs_check( strpos( $readme, 'External service: Freemius' ) !== false, 'External service disclosure missing.' );
fs_check( strpos( $readme, 'License: GPLv3' ) !== false, 'Combined package license missing.' );
foreach ( [
	'includes/storage/class-price-schema.php',
	'includes/render/class-price-renderer.php',
	'includes/render/partials/price-block.php',
	'includes/admin/class-admin-menuitem-meta.php',
	'includes/admin/class-jprm-menu-item-list.php',
	'includes/admin/save/class-menuitem-v3-writer.php',
	'includes/admin/class-admin-bulk-price-labels.php',
	'includes/widgets/traits/restaurant-menu-style.php',
] as $premium_source ) {
	$content = file_get_contents( $root . '/' . $premium_source );
	fs_check( false !== strpos( $content, 'can_use_premium_code__premium_only' ) || false !== strpos( $content, '__premium_only(' ), 'Freemius cannot identify Multiple Prices premium code in ' . $premium_source );
}
fs_check( false !== strpos( $readme, 'fs_free_only_begin' ) && false !== strpos( $readme, 'fs_premium_only_begin' ), 'Freemius readme edition markers missing.' );
echo "Freemius configuration and package checks passed; WordPress sandbox testing still required.\n";
