<?php
/** Standalone checks for the WPML language configuration. */

$root = dirname( __DIR__ );
$file = $root . '/wpml-config.xml';

if ( ! file_exists( $file ) ) {
	fwrite( STDERR, "Missing wpml-config.xml.\n" );
	exit( 1 );
}

libxml_use_internal_errors( true );
$xml = simplexml_load_file( $file );
if ( false === $xml ) {
	$messages = array_map(
		static fn( LibXMLError $error ) : string => trim( $error->message ),
		libxml_get_errors()
	);
	fwrite( STDERR, 'Invalid wpml-config.xml: ' . implode( '; ', $messages ) . "\n" );
	exit( 1 );
}

/** Fail with a useful message when an XPath requirement is missing. */
function jprm_assert_xpath( SimpleXMLElement $xml, string $xpath, string $message ) : void {
	$matches = $xml->xpath( $xpath );
	if ( false === $matches || [] === $matches ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

foreach ( [ 'jprm_menu_item', 'jprm_info_block' ] as $post_type ) {
	jprm_assert_xpath( $xml, "custom-types/custom-type[@translate='1'][text()='{$post_type}']", "WPML post type is not translatable: {$post_type}" );
}
foreach ( [ 'jprm_menu', 'jprm_section' ] as $taxonomy ) {
	jprm_assert_xpath( $xml, "taxonomies/taxonomy[@translate='1'][text()='{$taxonomy}']", "WPML taxonomy is not translatable: {$taxonomy}" );
}
foreach ( [ 'jprm_desc', 'jprm_info_block_content' ] as $field ) {
	jprm_assert_xpath( $xml, "custom-fields/custom-field[@action='translate'][text()='{$field}']", "WPML text field is not translatable: {$field}" );
}
foreach ( [ 'jprm_price', 'jprm_prices', 'jprm_item_badges', 'jprm_visible', '_jprm_order_in_section' ] as $field ) {
	jprm_assert_xpath( $xml, "custom-fields/custom-field[@action='copy'][text()='{$field}']", "WPML product-data field is not synchronized: {$field}" );
}
foreach ( [ 'jprm_price_labels_v2', 'jprm_dietary_badges', 'jprm_dietary_badges_v1' ] as $option ) {
	jprm_assert_xpath( $xml, "admin-texts/key[@name='{$option}']", "WPML admin text is not registered: {$option}" );
}

jprm_assert_xpath( $xml, "custom-term-fields/custom-term-field[@action='copy'][@type='taxonomy-ids'][@sub-type='jprm_menu'][text()='_jprm_menu_term_id']", 'WPML does not convert the Section owner Menu ID.' );
jprm_assert_xpath( $xml, "custom-term-fields-texts/key[@name='_jprm_menu_structure_v2']//key[@name='id'][@type='post-ids'][@sub-type='jprm_menu_item']", 'WPML does not convert Menu Item IDs in Menu structures.' );
jprm_assert_xpath( $xml, "custom-term-fields-texts/key[@name='_jprm_info_block_placements_v1']//key[@name='id'][@type='post-ids'][@sub-type='jprm_info_block']", 'WPML does not convert Info Block placement IDs.' );

jprm_assert_xpath( $xml, "elementor-widgets/widget[@name='jprm_restaurant_menu']", 'WPML Elementor widget registration is missing.' );
foreach ( [ 'menus', 'sections', 'layout_split_after_section', 'layout_split_after_section2' ] as $field ) {
	jprm_assert_xpath( $xml, "elementor-widgets/widget[@name='jprm_restaurant_menu']/fields/field[text()='{$field}']", "WPML Elementor field is missing: {$field}" );
}
foreach ( [ 'items_order_overrides', 'labels_layout_overrides', 'info_blocks' ] as $repeater ) {
	jprm_assert_xpath( $xml, "elementor-widgets/widget[@name='jprm_restaurant_menu']/fields-in-item[@items_of='{$repeater}']", "WPML Elementor repeater is missing: {$repeater}" );
}

$bootstrap = file_get_contents( $root . '/jellopoint-restaurant-menu.php' );
foreach ( [ 'Version:           2.0.27', "define( 'JPRM_VERSION', '2.0.27' )" ] as $needle ) {
	if ( false === strpos( (string) $bootstrap, $needle ) ) {
		fwrite( STDERR, "Plugin version is not consistently set to 2.0.27.\n" );
		exit( 1 );
	}
}

echo "WPML compatibility checks passed.\n";
