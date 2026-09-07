<?php
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$can_edit = false;
	function current_user_can( $capability ) { return $GLOBALS['can_edit']; }
	function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function get_term_meta( $id, $key, $single ) { return 10 === $id ? '1' : ''; }
	function notice_check( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	$root = dirname( __DIR__ );
	require_once $root . '/includes/modules/class-module-catalog.php';
	require_once $root . '/includes/modules/class-module-access.php';
	require_once $root . '/includes/admin/class-jprm-menus-admin.php';
	ob_start();
	\JelloPoint\RestaurantMenu\Admin\Menus_Admin::edit_daily_menu_fields( (object) [ 'term_id' => 10 ] );
	$notice = ob_get_clean();
	notice_check( false !== strpos( $notice, 'settings and content are preserved' ), 'Existing daily menu needs an explanation.' );
	notice_check( false === strpos( $notice, '<input' ), 'Notice must not submit or overwrite Daily fields.' );
	ob_start();
	\JelloPoint\RestaurantMenu\Admin\Menus_Admin::edit_daily_menu_fields( (object) [ 'term_id' => 11 ] );
	notice_check( '' === ob_get_clean(), 'Regular menu should not show a license warning.' );
	require_once $root . '/stubs/elementor.php';
	require_once $root . '/includes/widgets/class-restaurant-menu.php';
	$method = new \ReflectionMethod( \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu::class, 'jprm_is_editor_preview' );
	$method->setAccessible( true );
	$mode = new class {
		public $edit = false;
		public $preview = false;
		public function is_edit_mode() { return $this->edit; }
		public function is_preview_mode() { return $this->preview; }
	};
	\Elementor\Plugin::$instance = (object) [ 'editor' => $mode, 'preview' => $mode ];
	$mode->preview = true;
	notice_check( ! $method->invoke( null ), 'Visitors must not see preview warnings.' );
	$can_edit = true;
	notice_check( $method->invoke( null ), 'Authorized Elementor preview should show explanation.' );
	$mode->preview = false;
	notice_check( ! $method->invoke( null ), 'Ordinary frontend must not show editor warnings.' );
	$mode->edit = true;
	notice_check( $method->invoke( null ), 'Elementor editor should show explanation.' );
	echo "Daily license notice and preview visibility checks passed.\n";
}
