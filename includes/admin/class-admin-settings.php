<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plugin settings that apply to both Free and Pro. */
final class Settings {
	public const DELETE_DATA_OPTION = 'jprm_delete_data_on_uninstall';
	private const SETTINGS_GROUP = 'jprm_settings';

	public static function init() : void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 80 );
		add_action( 'admin_post_jprm_restore_defaults', [ __CLASS__, 'restore_defaults' ] );
	}

	/** Free onboarding; never depends on the Pro import engine or uploads. */
	public static function restore_defaults() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'jprm_restore_defaults' );
		\JPRM_Default_Data::install_missing();
		wp_safe_redirect( admin_url( 'admin.php?page=jprm-settings&defaults-restored=1' ) );
		exit;
	}

	public static function register_settings() : void {
		register_setting(
			self::SETTINGS_GROUP,
			self::DELETE_DATA_OPTION,
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
			]
		);
	}

	public static function sanitize_checkbox( $value ) : int {
		return empty( $value ) ? 0 : 1;
	}

	public static function register_menu() : void {
		add_submenu_page(
			Admin_Menu::PARENT_SLUG,
			__( 'JelloPoint Settings', 'jellopoint-restaurant-menu' ),
			__( 'Settings', 'jellopoint-restaurant-menu' ),
			'manage_options',
			Admin_Menu::SLUG_SETTINGS,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		$delete_data = '1' === (string) get_option( self::DELETE_DATA_OPTION, '0' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'JelloPoint Settings', 'jellopoint-restaurant-menu' ); ?></h1>
			<?php $defaults_restored = isset( $_GET['defaults-restored'] ) ? absint( wp_unslash( $_GET['defaults-restored'] ) ) : 0; ?>
			<?php if ( 1 === $defaults_restored ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Missing default badges, price labels and icons have been restored. Existing customizations are retained.', 'jellopoint-restaurant-menu' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Data removal', 'jellopoint-restaurant-menu' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::DELETE_DATA_OPTION ); ?>" value="0" />
							<label for="jprm-delete-data-on-uninstall">
								<input id="jprm-delete-data-on-uninstall" type="checkbox" name="<?php echo esc_attr( self::DELETE_DATA_OPTION ); ?>" value="1" <?php checked( $delete_data ); ?> />
								<?php esc_html_e( 'Delete all JelloPoint Restaurant Menu data when uninstalling the plugin', 'jellopoint-restaurant-menu' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disabled by default. Deactivating the plugin never removes data. When enabled, deleting the plugin permanently removes all restaurant menus, sections, items, Info Blocks, settings, and related plugin data.', 'jellopoint-restaurant-menu' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<h2><?php esc_html_e( 'Default badges and price labels', 'jellopoint-restaurant-menu' ); ?></h2>
			<p><?php esc_html_e( 'Add missing bundled defaults without replacing your existing badges or price labels.', 'jellopoint-restaurant-menu' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jprm_restore_defaults" />
				<?php wp_nonce_field( 'jprm_restore_defaults' ); ?>
				<?php submit_button( __( 'Restore missing defaults', 'jellopoint-restaurant-menu' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
