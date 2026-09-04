<?php
/** Standalone checks for strict Import/Export upload validation. */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = '' ) { return $text; }

require_once dirname( __DIR__ ) . '/includes/admin/class-admin-import-export.php';
require_once dirname( __DIR__ ) . '/includes/data/class-importer.php';

use JelloPoint\RestaurantMenu\Admin\JPRM_Admin_Import_Export;
use JelloPoint\RestaurantMenu\Data\JPRM_Importer;

function jprm_upload_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$temporary_files = [];
$make_file = static function( string $content ) use ( &$temporary_files ) : string {
	$path = tempnam( sys_get_temp_dir(), 'jprm-upload-' );
	file_put_contents( $path, $content );
	$temporary_files[] = $path;
	return $path;
};

$json = $make_file( '{"items":[{"post_title":"Soup"}]}' );
$csv = $make_file( "post_id;post_title;post_status;description;menus;sections;Price_Single;Price_Multiple\n;Soup;publish;;Lunch;Starters;8.50;\n" );
$invalid_json = $make_file( '{"items":' );
$binary = $make_file( "post_id\0post_title" );

jprm_upload_assert_same( '', JPRM_Admin_Import_Export::validate_import_file_content( $json, 'json' ), 'A valid JSON export must be accepted.' );
jprm_upload_assert_same( '', JPRM_Admin_Import_Export::validate_import_file_content( $csv, 'csv' ), 'A valid strict CSV must be accepted.' );
jprm_upload_assert_same( true, '' !== JPRM_Admin_Import_Export::validate_import_file_content( $invalid_json, 'json' ), 'Malformed JSON must be rejected.' );
jprm_upload_assert_same( true, '' !== JPRM_Admin_Import_Export::validate_import_file_content( $json, 'csv' ), 'An extension/content mismatch must be rejected.' );
jprm_upload_assert_same( true, '' !== JPRM_Admin_Import_Export::validate_import_file_content( $binary, 'csv' ), 'Binary content must be rejected.' );

$oversized_items = $make_file( json_encode( [ 'items' => array_fill( 0, 5001, [] ) ] ) );
$report = JPRM_Importer::run( [ 'tmp_name' => $oversized_items, 'name' => 'too-many.json' ], [ 'dry_run' => true ] );
jprm_upload_assert_same( 'Import files may contain no more than 5000 items.', $report['errors'][0] ?? '', 'Oversized item collections must be rejected before processing.' );

$admin_source = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-import-export.php' );
jprm_upload_assert_same( true, false !== strpos( $admin_source, 'is_uploaded_file( $tmp_name )' ), 'The HTTP handler must require a genuine PHP upload.' );
jprm_upload_assert_same( true, false !== strpos( $admin_source, 'filesize( $tmp_name )' ), 'The HTTP handler must use the actual temporary file size.' );

foreach ( $temporary_files as $path ) {
	unlink( $path );
}

echo "Import upload security checks passed.\n";
