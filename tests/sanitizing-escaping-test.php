<?php
/** Standalone source checks for request sanitizing and output escaping boundaries. */

$root = dirname( __DIR__ );

$checks = [
	'includes/data/class-labels-store.php' => [
		"sanitize_text_field( wp_unslash( \$_POST['jprm_labels_nonce'] ) )",
		"wp_unslash( \$_POST['labels'] )",
		"sanitize_key( wp_unslash( \$_GET['page'] ) )",
		"sanitize_key( (string)\$row['id'] )",
	],
	'includes/admin/class-admin-menuitem-badges-meta.php' => [
		"sanitize_text_field( wp_unslash( \$_POST[ self::NONCE_NAME ] ) )",
		"array_map( 'sanitize_title', wp_unslash( \$_POST['jprm_item_badges'] ) )",
	],
	'includes/admin/class-admin-menuitem-meta.php' => [
		"sanitize_text_field( wp_unslash( \$_POST['jprm_meta_nonce'] ) )",
		"wp_kses_post(\$raw_desc)",
		"sanitize_key( wp_unslash( \$_POST['jprm_price_mode'] ) )",
		"sanitize_text_field( wp_unslash( \$_POST['jprm_price_amount'] ) )",
	],
	'includes/admin/class-jprm-sections-admin.php' => [
		"sanitize_key( wp_unslash( \$_GET['orderby'] ) )",
		"absint( wp_unslash( \$_GET['jprm_filter_menu'] ) )",
		"wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD )",
		"wp_verify_nonce( \$nonce, self::NONCE_ACTION )",
		"current_user_can( 'edit_term', \$term_id )",
	],
	'includes/admin/class-jprm-menus-admin.php' => [
		"wp_verify_nonce( \$nonce, self::NONCE_ACTION )",
		"current_user_can( 'edit_term', \$term_id )",
	],
];

foreach ( $checks as $relative_path => $needles ) {
	$source = file_get_contents( $root . '/' . $relative_path );
	if ( false === $source ) {
		fwrite( STDERR, "Unable to read {$relative_path}.\n" );
		exit( 1 );
	}

	foreach ( $needles as $needle ) {
		if ( false === strpos( $source, $needle ) ) {
			fwrite( STDERR, "Missing sanitizing boundary in {$relative_path}: {$needle}\n" );
			exit( 1 );
		}
	}
}

$render_sources = [
	'includes/render/templates/inline.php' => 'esc_html( $title )',
	'includes/render/templates/matrix.php' => 'esc_html( $title )',
	'includes/render/print/document.php'   => "esc_html( (string) \$item['title'] )",
];

foreach ( $render_sources as $relative_path => $needle ) {
	$source = file_get_contents( $root . '/' . $relative_path );
	if ( false === $source || false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Missing escaped item title in {$relative_path}.\n" );
		exit( 1 );
	}
}

$inline_asset_sources = glob( $root . '/includes/*.php' );
$inline_asset_sources = array_merge( $inline_asset_sources ?: [], glob( $root . '/includes/admin/*.php' ) ?: [], glob( $root . '/includes/data/*.php' ) ?: [] );
foreach ( $inline_asset_sources as $source_path ) {
	$source = file_get_contents( $source_path );
	if ( false !== $source && preg_match( '/<(?:script|style)(?:\s|>)/i', $source ) ) {
		fwrite( STDERR, 'Literal script/style tag remains in ' . basename( $source_path ) . ".\n" );
		exit( 1 );
	}
}

echo "Sanitizing and escaping checks passed.\n";
