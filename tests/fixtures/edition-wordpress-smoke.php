<?php
/** WP-CLI eval-file fixture. Only run against a disposable local WordPress database. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) { exit( 1 ); }
use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store;
use JelloPoint\RestaurantMenu\Data\Info_Block_Store;
use JelloPoint\RestaurantMenu\Modules\Module_Access;
function jprm_qa_check( $condition, string $message ) : void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$mode = $args[0] ?? 'free';
wp_set_current_user( get_user_by( 'login', 'jprm_qa' )->ID );

if ( 'seed' === $mode ) {
	jprm_qa_check( ! get_option( 'jprm_qa_fixture' ), 'Fixture already exists; do not overwrite.' );
	$menu = wp_insert_term( 'QA Menu', 'jprm_menu' )['term_id'];
	$daily = wp_insert_term( 'QA Daily', 'jprm_menu' )['term_id'];
	$section = wp_insert_term( 'QA Section', 'jprm_section' )['term_id'];
	$item = wp_insert_post( ['post_type' => 'jprm_menu_item', 'post_status' => 'publish', 'post_title' => 'QA Wine'] );
	$info = wp_insert_post( ['post_type' => 'jprm_info_block', 'post_status' => 'publish', 'post_title' => 'QA Info', 'post_content' => 'QA reusable content'] );
	update_post_meta( $item, 'jprm_price', wp_json_encode( ['mode' => 'multi', 'rows' => [['value' => '8.50', 'label_ref' => 'glass'], ['value' => '29.00', 'label_ref' => 'bottle']]] ) );
	update_post_meta( $item, 'jprm_item_badges', ['vegan'] );
	update_post_meta( $item, 'jprm_desc', 'QA description' );
	Menu_Structure_Store::attach_section( $menu, $section );
	Menu_Structure_Store::assign_items( $menu, $section, [$item] );
	Menu_Structure_Store::attach_section( $daily, $section );
	Menu_Structure_Store::assign_items( $daily, $section, [$item] );
	Info_Block_Store::save( $menu, [['id' => $info, 'section_id' => $section, 'position' => 'above', 'order' => 0]] );
	update_term_meta( $daily, '_jprm_is_daily_menu', '1' );
	update_term_meta( $daily, '_jprm_daily_menu_date_type', 'range' );
	update_term_meta( $daily, '_jprm_daily_menu_date', '2026-09-01' );
	update_term_meta( $daily, '_jprm_daily_menu_end_date', '2026-09-30' );
	update_term_meta( $daily, '_jprm_daily_menu_fixed_price', '39.00' );
	update_term_meta( $daily, '_jprm_daily_menu_item_separator', 'or' );
	update_option( 'jprm_qa_fixture', compact( 'menu', 'daily', 'section', 'item', 'info' ) );
}
$ids = get_option( 'jprm_qa_fixture' );
jprm_qa_check( is_array( $ids ), 'Seed the QA fixture first.' );
$snapshot = [];
foreach ( ['menu', 'daily', 'section'] as $key ) { $snapshot[$key] = get_term_meta( $ids[$key] ); }
foreach ( ['item', 'info'] as $key ) { $snapshot[$key] = [get_post( $ids[$key] )->post_content, get_post_meta( $ids[$key] ), wp_get_object_terms( $ids[$key], ['jprm_menu', 'jprm_section'], ['fields' => 'ids'] )]; }
$hash = hash( 'sha256', serialize( $snapshot ) );
if ( 'seed' === $mode ) { update_option( 'jprm_qa_snapshot', $hash ); echo "QA content seeded.\n"; return; }
jprm_qa_check( get_option( 'jprm_qa_snapshot' ) === $hash, 'Content changed across edition switch.' );
$premium = 'pro' === $mode;
jprm_qa_check( jprm_fs()->is_premium() === $premium, 'Wrong running SDK edition.' );
jprm_qa_check( Module_Access::allows( 'multiple_prices' ), 'Multiple Prices unavailable.' );
jprm_qa_check( Module_Access::allows( 'info_blocks' ), 'Info Blocks unavailable.' );

$routes = rest_get_server()->get_routes();
jprm_qa_check( isset( $routes['/jprm/v1/menu-builder/menus'] ), 'Core Builder routes missing.' );
jprm_qa_check( $premium === isset( $routes['/jprm/v1/menu-builder/info-blocks'] ), 'Print endpoints in wrong edition.' );
$print_request = new WP_REST_Request( 'GET', '/jprm/v1/menu-builder/info-blocks' );
$print_request->set_param( 'menu_id', $ids['menu'] );
if ( ! Module_Access::allows( 'print_pdf' ) ) {
	jprm_qa_check( ( $premium ? 403 : 404 ) === rest_do_request( $print_request )->get_status(), 'Unlicensed print route must deny Pro and be absent in Free.' );
}
$response = rest_do_request( '/jprm/v1/menu-builder/menus' );
jprm_qa_check( 200 === $response->get_status(), 'Builder menu request failed.' );
wp_set_current_user( 0 );
jprm_qa_check( 401 === rest_do_request( '/jprm/v1/menu-builder/menus' )->get_status(), 'Anonymous Builder read allowed.' );
wp_set_current_user( get_user_by( 'login', 'jprm_qa' )->ID );

// Runtime loader must not require missing files in Free, even with admin context.
\JelloPoint\RestaurantMenu\Modules\Module_Loader::load_runtime();
\JelloPoint\RestaurantMenu\Modules\Module_Loader::boot_admin();
jprm_qa_check( $premium === class_exists( '\JelloPoint\RestaurantMenu\Data\Print_Document_Builder' ), 'Print runtime has wrong edition.' );
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menus-admin.php';
ob_start();
\JelloPoint\RestaurantMenu\Admin\Menus_Admin::edit_daily_menu_fields( get_term( $ids['daily'], 'jprm_menu' ) );
$notice = ob_get_clean();
if ( ! Module_Access::allows( 'daily_weekly_menus' ) ) { jprm_qa_check( false !== strpos( $notice, 'settings and content are preserved' ), 'Saved Daily warning missing.' ); }
if ( ! Module_Access::allows( 'daily_weekly_menus' ) ) {
	$daily_before = get_term_meta( $ids['daily'] );
	$original_post = $_POST;
	$_POST = ['_jprm_daily_menu_nonce' => wp_create_nonce( 'jprm_save_daily_menu_fields' ), 'jprm_daily_menu_date' => '2030-01-01'];
	\JelloPoint\RestaurantMenu\Admin\Menus_Admin::save_daily_menu_fields( $ids['daily'] );
	$_POST = $original_post;
	jprm_qa_check( $daily_before === get_term_meta( $ids['daily'] ), 'Unlicensed save changed Daily metadata.' );
}
$blocks = Info_Block_Store::data_for_widget( [['info_block_id' => $ids['info']]] );
jprm_qa_check( false !== strpos( $blocks[0]['content_html'], 'QA reusable content' ), 'Free reusable Info Blocks failed.' );
require_once JPRM_PLUGIN_PATH . 'includes/render/partials/price-block.php';
$prices = jprm_get_pricegroup_data( $ids['item'] );
jprm_qa_check( count( $prices ) === 2, 'Multiple Prices lost rows.' );

// Render all actual Free templates with database-backed content.
require_once JPRM_PLUGIN_PATH . 'includes/render/partials/info-blocks.php';
require_once JPRM_PLUGIN_PATH . 'includes/render/partials/badges-block.php';
require_once JPRM_PLUGIN_PATH . 'includes/helpers/icons.php';
foreach ( ['inline', 'inline_below', 'matrix'] as $layout ) {
	$ctx = [
		'menu_term' => get_term( $ids['menu'], 'jprm_menu' ), 'show_menu_title' => true,
		'sections_order' => [$ids['section']],
		'sections_data' => [$ids['section'] => ['term' => get_term( $ids['section'], 'jprm_section' ), 'items' => [get_post( $ids['item'] )]]],
		'show_main_sections' => 'yes', 'show_main_even_if_empty' => 'yes',
		'show_section_name' => true, 'show_badges' => true, 'label_map' => jprm_build_label_map(),
		'layout_desktop' => $layout, 'layout_tablet' => $layout, 'layout_mobile' => $layout,
	];
	ob_start(); include JPRM_PLUGIN_PATH . 'includes/render/templates/menu.php'; $html = ob_get_clean();
	jprm_qa_check( false !== strpos( $html, 'QA Wine' ), "Item missing from $layout output." );
	jprm_qa_check( false !== strpos( $html, '8.50' ) || false !== strpos( $html, '8,50' ), "Price missing from $layout output." );
}
if ( class_exists( '\Elementor\Plugin' ) ) {
	$elementor = \Elementor\Plugin::$instance;
	$elementor->widgets_manager->get_widget_types();
	$widget = $elementor->elements_manager->create_element_instance( [
		'id' => 'jprmqa', 'elType' => 'widget', 'widgetType' => 'jprm_restaurant_menu',
		'settings' => ['menus' => (string) $ids['menu'], 'show_main_sections' => 'yes', 'show_main_even_if_empty' => 'yes', 'show_badges' => 'yes'],
	] );
	jprm_qa_check( $widget instanceof \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu, 'Actual Elementor widget not registered.' );
	$controls = $widget->get_controls();
	if ( ! Module_Access::allows( 'daily_weekly_menus' ) ) {
		jprm_qa_check( ! isset( $controls['show_daily_menu_date'] ) && ! isset( $controls['jprm_daily_menu_heading'] ), 'Daily controls remain without entitlement.' );
	}
	ob_start(); $widget->render(); $widget_html = ob_get_clean();
	jprm_qa_check( false !== strpos( $widget_html, 'QA Wine' ), 'Actual Elementor widget failed to render.' );
	jprm_qa_check( false !== strpos( $widget_html, 'Vegan' ), 'Dietary badge missing from actual widget.' );
	jprm_qa_check( false !== strpos( $widget_html, 'Glass' ) && false !== strpos( $widget_html, 'Bottle' ), 'Price labels missing from actual widget.' );
	$daily_widget = $elementor->elements_manager->create_element_instance( [
		'id' => 'jprmdailyqa', 'elType' => 'widget', 'widgetType' => 'jprm_restaurant_menu', 'settings' => ['menus' => (string) $ids['daily']],
	] );
	if ( ! Module_Access::allows( 'daily_weekly_menus' ) ) {
		$elementor->editor->set_edit_mode( false );
		ob_start(); $daily_widget->render(); $hidden = ob_get_clean();
		jprm_qa_check( '' === $hidden, 'Daily menu or admin warning exposed to visitors.' );
		$elementor->editor->set_edit_mode( true );
		ob_start(); $daily_widget->render(); $warning = ob_get_clean();
		jprm_qa_check( false !== strpos( $warning, 'settings and content are preserved' ), 'Elementor Daily warning missing.' );
		$elementor->editor->set_edit_mode( false );
	}
	echo "Actual Elementor widget registration, controls, frontend rendering and Daily editor-only warning passed.\n";
}
echo "$mode: SDK bootstrap, Builder/permissions, three frontend layouts, Multiple Prices, Info Blocks, Daily warning and edition-switch data retention passed.\n";
