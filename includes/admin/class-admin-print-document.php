<?php
namespace JelloPoint\RestaurantMenu\Admin;

use JelloPoint\RestaurantMenu\Data\Print_Document_Builder;
use JelloPoint\RestaurantMenu\Data\Print_Document_Settings;
use JelloPoint\RestaurantMenu\Render\Print_Document_Renderer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Print document settings and dedicated preview. */
final class Print_Document_Admin {
	private const PAGE_SLUG = 'jprm-print-pdf';
	private const NONCE_ACTION = 'jprm_save_print_document';
	private const NONCE_FIELD = '_jprm_print_nonce';

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_post_jprm_save_print_document', [ __CLASS__, 'save' ] );
		add_action( 'admin_post_jprm_preview_print_document', [ __CLASS__, 'preview' ] );
	}

	public static function register_menu() : void {
		add_submenu_page(
			Admin_Menu::PARENT_SLUG,
			__( 'Print / PDF', 'jellopoint-restaurant-menu' ),
			__( 'Print / PDF', 'jellopoint-restaurant-menu' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	public static function save() : void {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'jellopoint-restaurant-menu' ) ); }
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );
		$raw = isset( $_POST['jprm_print'] ) && is_array( $_POST['jprm_print'] ) ? wp_unslash( $_POST['jprm_print'] ) : [];
		Print_Document_Settings::save( $raw );
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() : void {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) ); }
		$settings = Print_Document_Settings::get();
		$menus = get_terms( [ 'taxonomy' => 'jprm_menu', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
		$document = $settings['menu_id'] > 0 ? Print_Document_Builder::build( (int) $settings['menu_id'], $settings ) : [];
		$item_count = 0;
		foreach ( (array) ( $document['sections'] ?? [] ) as $section ) { $item_count += count( (array) ( $section['items'] ?? [] ) ); }
		?>
		<div class="wrap jprm-print-settings">
			<h1><?php esc_html_e( 'Print / PDF', 'jellopoint-restaurant-menu' ); ?></h1>
			<p><?php esc_html_e( 'Choose an existing Menu, a dedicated print design and the paper settings.', 'jellopoint-restaurant-menu' ); ?></p>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Print settings saved.', 'jellopoint-restaurant-menu' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jprm_save_print_document" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="jprm-print-menu"><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label></th><td><select id="jprm-print-menu" name="jprm_print[menu_id]" required><option value=""><?php esc_html_e( '— Select a Menu —', 'jellopoint-restaurant-menu' ); ?></option><?php if ( ! is_wp_error( $menus ) ) : foreach ( $menus as $menu ) : ?><option value="<?php echo (int) $menu->term_id; ?>" <?php selected( (int) $settings['menu_id'], (int) $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option><?php endforeach; endif; ?></select><p class="description"><?php esc_html_e( 'Content comes directly from this existing Menu and remains managed in Menu Builder.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
					<tr><th><label for="jprm-print-preset"><?php esc_html_e( 'Print Preset', 'jellopoint-restaurant-menu' ); ?></label></th><td><select id="jprm-print-preset" name="jprm_print[preset]"><option value="classic" <?php selected( $settings['preset'], 'classic' ); ?>><?php esc_html_e( 'Classic', 'jellopoint-restaurant-menu' ); ?></option><option value="modern" <?php selected( $settings['preset'], 'modern' ); ?>><?php esc_html_e( 'Modern', 'jellopoint-restaurant-menu' ); ?></option><option value="elegant" <?php selected( $settings['preset'], 'elegant' ); ?>><?php esc_html_e( 'Elegant', 'jellopoint-restaurant-menu' ); ?></option></select><p class="description"><?php esc_html_e( 'Each preset is optimized independently for paper and does not change Elementor styling.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Paper Size', 'jellopoint-restaurant-menu' ); ?></th><td><select name="jprm_print[paper_size]"><option value="a4" selected>A4</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Orientation', 'jellopoint-restaurant-menu' ); ?></th><td><label><input type="radio" name="jprm_print[orientation]" value="portrait" <?php checked( $settings['orientation'], 'portrait' ); ?> /> <?php esc_html_e( 'Portrait', 'jellopoint-restaurant-menu' ); ?></label>&nbsp;&nbsp;<label><input type="radio" name="jprm_print[orientation]" value="landscape" <?php checked( $settings['orientation'], 'landscape' ); ?> /> <?php esc_html_e( 'Landscape', 'jellopoint-restaurant-menu' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Margins', 'jellopoint-restaurant-menu' ); ?></th><td><fieldset class="jprm-margin-grid"><?php foreach ( [ 'top' => __( 'Top', 'jellopoint-restaurant-menu' ), 'right' => __( 'Right', 'jellopoint-restaurant-menu' ), 'bottom' => __( 'Bottom', 'jellopoint-restaurant-menu' ), 'left' => __( 'Left', 'jellopoint-restaurant-menu' ) ] as $side => $label ) : ?><label><?php echo esc_html( $label ); ?><input type="number" min="0" max="50" step="0.5" name="jprm_print[margins][<?php echo esc_attr( $side ); ?>]" value="<?php echo esc_attr( (string) $settings['margins'][ $side ] ); ?>" /> mm</label><?php endforeach; ?></fieldset><p class="description"><?php esc_html_e( 'Allowed range: 0–50 mm.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save Print Settings', 'jellopoint-restaurant-menu' ), 'primary', 'submit', false ); ?>
				<?php if ( $document ) : ?> <a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jprm_preview_print_document' ), 'jprm_preview_print_document' ) ); ?>"><?php esc_html_e( 'Open Print Preview', 'jellopoint-restaurant-menu' ); ?></a><?php endif; ?>
			</form>
			<?php if ( $document ) : ?><div class="card"><h2><?php esc_html_e( 'Document Source Check', 'jellopoint-restaurant-menu' ); ?></h2><p><strong><?php echo esc_html( (string) $document['menu']['name'] ); ?></strong></p><p><?php printf( esc_html__( '%1$d Sections and %2$d published Menu Items are ready for the printable templates.', 'jellopoint-restaurant-menu' ), count( $document['sections'] ), $item_count ); ?></p><p><?php esc_html_e( 'Prices, Price Labels, Dietary Badges and their icons are included in the document data.', 'jellopoint-restaurant-menu' ); ?></p></div><?php endif; ?>
		</div>
		<style>.jprm-print-settings .form-table{max-width:900px}.jprm-margin-grid{display:grid;grid-template-columns:repeat(4,minmax(100px,150px));gap:12px}.jprm-margin-grid label{display:grid;gap:4px}.jprm-print-settings .card{max-width:850px;margin-top:24px}@media(max-width:782px){.jprm-margin-grid{grid-template-columns:repeat(2,minmax(100px,1fr))}}</style>
		<?php
	}

	public static function preview() : void {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to access this preview.', 'jellopoint-restaurant-menu' ) ); }
		check_admin_referer( 'jprm_preview_print_document' );
		$settings = Print_Document_Settings::get();
		$document = Print_Document_Builder::build( (int) $settings['menu_id'], $settings );
		if ( ! $document ) { wp_die( esc_html__( 'Select and save a valid Menu first.', 'jellopoint-restaurant-menu' ) ); }
		nocache_headers();
		Print_Document_Renderer::render( $document );
		exit;
	}
}
