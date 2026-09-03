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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets() : void {
		if ( isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { wp_enqueue_media(); }
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
		$logo_url = $settings['logo_id'] > 0 ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'medium' ) : '';
		$item_count = 0;
		foreach ( (array) ( $document['sections'] ?? [] ) as $section ) { $item_count += count( (array) ( $section['items'] ?? [] ) ); }
		?>
		<div class="wrap jprm-print-settings">
			<h1><?php esc_html_e( 'Print / PDF', 'jellopoint-restaurant-menu' ); ?></h1>
			<p><?php esc_html_e( 'Choose an existing Menu, a dedicated print design and the paper settings.', 'jellopoint-restaurant-menu' ); ?></p>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Print settings saved.', 'jellopoint-restaurant-menu' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="jprm-print-menu"><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label></th><td><select id="jprm-print-menu" name="jprm_print[menu_id]" required><option value=""><?php esc_html_e( '— Select a Menu —', 'jellopoint-restaurant-menu' ); ?></option><?php if ( ! is_wp_error( $menus ) ) : foreach ( $menus as $menu ) : ?><option value="<?php echo (int) $menu->term_id; ?>" <?php selected( (int) $settings['menu_id'], (int) $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option><?php endforeach; endif; ?></select><p class="description"><?php esc_html_e( 'Content comes directly from this existing Menu and remains managed in Menu Builder.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
					<tr><th><label for="jprm-print-preset"><?php esc_html_e( 'Print Preset', 'jellopoint-restaurant-menu' ); ?></label></th><td><select id="jprm-print-preset" name="jprm_print[preset]"><option value="classic" <?php selected( $settings['preset'], 'classic' ); ?>><?php esc_html_e( 'Classic', 'jellopoint-restaurant-menu' ); ?></option><option value="modern" <?php selected( $settings['preset'], 'modern' ); ?>><?php esc_html_e( 'Modern', 'jellopoint-restaurant-menu' ); ?></option><option value="elegant" <?php selected( $settings['preset'], 'elegant' ); ?>><?php esc_html_e( 'Elegant', 'jellopoint-restaurant-menu' ); ?></option></select><p class="description"><?php esc_html_e( 'Each preset is optimized independently for paper and does not change Elementor styling.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Columns', 'jellopoint-restaurant-menu' ); ?></th><td><select name="jprm_print[columns]"><?php foreach ( [ 1, 2, 3 ] as $columns ) : ?><option value="<?php echo (int) $columns; ?>" <?php selected( (int) $settings['columns'], $columns ); ?>><?php echo (int) $columns; ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Logo', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-logo-control"><input type="hidden" id="jprm-print-logo-id" name="jprm_print[logo_id]" value="<?php echo (int) $settings['logo_id']; ?>" /><span id="jprm-print-logo-preview"><?php if ( is_string( $logo_url ) && '' !== $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?></span><button type="button" class="button" id="jprm-select-logo"><?php esc_html_e( 'Choose Logo', 'jellopoint-restaurant-menu' ); ?></button> <button type="button" class="button" id="jprm-remove-logo"><?php esc_html_e( 'Remove', 'jellopoint-restaurant-menu' ); ?></button></div><p><select name="jprm_print[logo_position]"><option value="left" <?php selected( $settings['logo_position'], 'left' ); ?>><?php esc_html_e( 'Left', 'jellopoint-restaurant-menu' ); ?></option><option value="center" <?php selected( $settings['logo_position'], 'center' ); ?>><?php esc_html_e( 'Center', 'jellopoint-restaurant-menu' ); ?></option><option value="right" <?php selected( $settings['logo_position'], 'right' ); ?>><?php esc_html_e( 'Right', 'jellopoint-restaurant-menu' ); ?></option></select></p></td></tr>
					<tr><th><?php esc_html_e( 'Typography', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-control-grid"><label><?php esc_html_e( 'Headings', 'jellopoint-restaurant-menu' ); ?><select name="jprm_print[heading_font]"><?php self::font_options( (string) $settings['heading_font'] ); ?></select></label><label><?php esc_html_e( 'Body Text', 'jellopoint-restaurant-menu' ); ?><select name="jprm_print[body_font]"><?php self::font_options( (string) $settings['body_font'] ); ?></select></label><?php foreach ( [ 'title_size' => __( 'Menu Title', 'jellopoint-restaurant-menu' ), 'section_size' => __( 'Section Titles', 'jellopoint-restaurant-menu' ), 'item_size' => __( 'Item Titles', 'jellopoint-restaurant-menu' ), 'description_size' => __( 'Descriptions', 'jellopoint-restaurant-menu' ) ] as $key => $label ) : ?><label><?php echo esc_html( $label ); ?><span><input type="number" min="6" max="60" step="0.5" name="jprm_print[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" /> pt</span></label><?php endforeach; ?></div></td></tr>
					<tr><th><?php esc_html_e( 'Colors', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-control-grid"><?php foreach ( [ 'text_color' => __( 'Text', 'jellopoint-restaurant-menu' ), 'accent_color' => __( 'Accent', 'jellopoint-restaurant-menu' ), 'background_color' => __( 'Background', 'jellopoint-restaurant-menu' ) ] as $key => $label ) : ?><label><?php echo esc_html( $label ); ?><input type="color" name="jprm_print[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" /></label><?php endforeach; ?></div></td></tr>
					<tr><th><?php esc_html_e( 'Alignment', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-control-grid"><label><?php esc_html_e( 'Header', 'jellopoint-restaurant-menu' ); ?><?php self::alignment_select( 'header_alignment', (string) $settings['header_alignment'] ); ?></label><label><?php esc_html_e( 'Section Titles', 'jellopoint-restaurant-menu' ); ?><?php self::alignment_select( 'section_alignment', (string) $settings['section_alignment'] ); ?></label></div></td></tr>
					<tr><th><?php esc_html_e( 'Spacing', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-control-grid"><label><?php esc_html_e( 'Between Sections', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="0" max="30" step="0.5" name="jprm_print[section_spacing]" value="<?php echo esc_attr( (string) $settings['section_spacing'] ); ?>" /> mm</span></label><label><?php esc_html_e( 'Between Items', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="0" max="20" step="0.5" name="jprm_print[item_spacing]" value="<?php echo esc_attr( (string) $settings['item_spacing'] ); ?>" /> mm</span></label></div></td></tr>
					<tr><th><?php esc_html_e( 'Visible Elements', 'jellopoint-restaurant-menu' ); ?></th><td><?php foreach ( [ 'show_descriptions' => __( 'Descriptions', 'jellopoint-restaurant-menu' ), 'show_price_labels' => __( 'Price Labels', 'jellopoint-restaurant-menu' ), 'show_price_icons' => __( 'Price Label Icons', 'jellopoint-restaurant-menu' ), 'show_badges' => __( 'Dietary Badges', 'jellopoint-restaurant-menu' ), 'show_info_blocks' => __( 'Info Blocks', 'jellopoint-restaurant-menu' ) ] as $key => $label ) : ?><input type="hidden" name="jprm_print[<?php echo esc_attr( $key ); ?>]" value="0" /><label class="jprm-check"><input type="checkbox" name="jprm_print[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></td></tr>
					<tr><th><?php esc_html_e( 'Print/PDF Info Block Style', 'jellopoint-restaurant-menu' ); ?></th><td>
						<p class="description"><?php esc_html_e( 'These settings apply to all Info Blocks in this printed document. Website styling remains controlled in Elementor.', 'jellopoint-restaurant-menu' ); ?></p>
						<div class="jprm-control-grid">
							<label><?php esc_html_e( 'Layout', 'jellopoint-restaurant-menu' ); ?><select name="jprm_print[info_block_layout]"><option value="beside" <?php selected( $settings['info_block_layout'], 'beside' ); ?>><?php esc_html_e( 'Image Beside Text', 'jellopoint-restaurant-menu' ); ?></option><option value="stacked" <?php selected( $settings['info_block_layout'], 'stacked' ); ?>><?php esc_html_e( 'Image Above Text', 'jellopoint-restaurant-menu' ); ?></option></select></label>
							<label><?php esc_html_e( 'Alignment', 'jellopoint-restaurant-menu' ); ?><?php self::alignment_select( 'info_block_alignment', (string) $settings['info_block_alignment'] ); ?></label>
							<label><?php esc_html_e( 'Text Color', 'jellopoint-restaurant-menu' ); ?><input type="color" name="jprm_print[info_block_text_color]" value="<?php echo esc_attr( (string) $settings['info_block_text_color'] ); ?>" /></label>
							<label><?php esc_html_e( 'Background Color', 'jellopoint-restaurant-menu' ); ?><input type="color" name="jprm_print[info_block_background_color]" value="<?php echo esc_attr( (string) $settings['info_block_background_color'] ); ?>" /></label>
							<label><?php esc_html_e( 'Text Size', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="6" max="24" step="0.5" name="jprm_print[info_block_font_size]" value="<?php echo esc_attr( (string) $settings['info_block_font_size'] ); ?>" /> pt</span></label>
							<label><?php esc_html_e( 'Image Width', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="5" max="80" step="0.5" name="jprm_print[info_block_image_width]" value="<?php echo esc_attr( (string) $settings['info_block_image_width'] ); ?>" /> mm</span></label>
							<label><?php esc_html_e( 'Inner Spacing', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="0" max="20" step="0.5" name="jprm_print[info_block_padding]" value="<?php echo esc_attr( (string) $settings['info_block_padding'] ); ?>" /> mm</span></label>
							<label><?php esc_html_e( 'Space After Block', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="0" max="30" step="0.5" name="jprm_print[info_block_spacing]" value="<?php echo esc_attr( (string) $settings['info_block_spacing'] ); ?>" /> mm</span></label>
							<label><?php esc_html_e( 'Border Style', 'jellopoint-restaurant-menu' ); ?><select name="jprm_print[info_block_border_style]"><?php foreach ( [ 'none', 'solid', 'double', 'dashed' ] as $style ) : ?><option value="<?php echo esc_attr( $style ); ?>" <?php selected( $settings['info_block_border_style'], $style ); ?>><?php echo esc_html( ucfirst( $style ) ); ?></option><?php endforeach; ?></select></label>
							<label><?php esc_html_e( 'Border Width', 'jellopoint-restaurant-menu' ); ?><input type="number" min="0" max="10" step="0.5" name="jprm_print[info_block_border_width]" value="<?php echo esc_attr( (string) $settings['info_block_border_width'] ); ?>" /></label>
							<label><?php esc_html_e( 'Border Color', 'jellopoint-restaurant-menu' ); ?><input type="color" name="jprm_print[info_block_border_color]" value="<?php echo esc_attr( (string) $settings['info_block_border_color'] ); ?>" /></label>
							<label><?php esc_html_e( 'Rounded Corners', 'jellopoint-restaurant-menu' ); ?><span><input type="number" min="0" max="20" step="0.5" name="jprm_print[info_block_border_radius]" value="<?php echo esc_attr( (string) $settings['info_block_border_radius'] ); ?>" /> mm</span></label>
						</div>
					</td></tr>
					<tr><th><?php esc_html_e( 'Borders', 'jellopoint-restaurant-menu' ); ?></th><td><div class="jprm-control-grid"><?php foreach ( [ 'menu' => __( 'Complete Menu', 'jellopoint-restaurant-menu' ), 'section' => __( 'Sections', 'jellopoint-restaurant-menu' ) ] as $scope => $label ) : ?><fieldset><strong><?php echo esc_html( $label ); ?></strong><label><?php esc_html_e( 'Style', 'jellopoint-restaurant-menu' ); ?><select name="jprm_print[<?php echo esc_attr( $scope ); ?>_border_style]"><?php foreach ( [ 'none', 'solid', 'double', 'dashed' ] as $style ) : ?><option value="<?php echo esc_attr( $style ); ?>" <?php selected( $settings[ $scope . '_border_style' ], $style ); ?>><?php echo esc_html( ucfirst( $style ) ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Width', 'jellopoint-restaurant-menu' ); ?><input type="number" min="0" max="10" step="0.5" name="jprm_print[<?php echo esc_attr( $scope ); ?>_border_width]" value="<?php echo esc_attr( (string) $settings[ $scope . '_border_width' ] ); ?>" /></label><label><?php esc_html_e( 'Color', 'jellopoint-restaurant-menu' ); ?><input type="color" name="jprm_print[<?php echo esc_attr( $scope ); ?>_border_color]" value="<?php echo esc_attr( (string) $settings[ $scope . '_border_color' ] ); ?>" /></label><label><?php esc_html_e( 'Rounded Corners', 'jellopoint-restaurant-menu' ); ?><input type="number" min="0" max="20" step="0.5" name="jprm_print[<?php echo esc_attr( $scope ); ?>_border_radius]" value="<?php echo esc_attr( (string) $settings[ $scope . '_border_radius' ] ); ?>" /></label><?php if ( 'section' === $scope ) : ?><label><?php esc_html_e( 'Inner Spacing', 'jellopoint-restaurant-menu' ); ?><input type="number" min="0" max="20" step="0.5" name="jprm_print[section_border_padding]" value="<?php echo esc_attr( (string) $settings['section_border_padding'] ); ?>" /></label><?php endif; ?></fieldset><?php endforeach; ?></div></td></tr>
					<tr><th><?php esc_html_e( 'Paper Size', 'jellopoint-restaurant-menu' ); ?></th><td><select name="jprm_print[paper_size]"><option value="a4" selected>A4</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Orientation', 'jellopoint-restaurant-menu' ); ?></th><td><label><input type="radio" name="jprm_print[orientation]" value="portrait" <?php checked( $settings['orientation'], 'portrait' ); ?> /> <?php esc_html_e( 'Portrait', 'jellopoint-restaurant-menu' ); ?></label>&nbsp;&nbsp;<label><input type="radio" name="jprm_print[orientation]" value="landscape" <?php checked( $settings['orientation'], 'landscape' ); ?> /> <?php esc_html_e( 'Landscape', 'jellopoint-restaurant-menu' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Margins', 'jellopoint-restaurant-menu' ); ?></th><td><fieldset class="jprm-margin-grid"><?php foreach ( [ 'top' => __( 'Top', 'jellopoint-restaurant-menu' ), 'right' => __( 'Right', 'jellopoint-restaurant-menu' ), 'bottom' => __( 'Bottom', 'jellopoint-restaurant-menu' ), 'left' => __( 'Left', 'jellopoint-restaurant-menu' ) ] as $side => $label ) : ?><label><?php echo esc_html( $label ); ?><input type="number" min="0" max="50" step="0.5" name="jprm_print[margins][<?php echo esc_attr( $side ); ?>]" value="<?php echo esc_attr( (string) $settings['margins'][ $side ] ); ?>" /> mm</label><?php endforeach; ?></fieldset><p class="description"><?php esc_html_e( 'Allowed range: 0–50 mm.', 'jellopoint-restaurant-menu' ); ?></p></td></tr>
					<?php if ( $document && (int) $settings['columns'] > 1 ) : ?><tr><th><?php esc_html_e( 'Start New Column', 'jellopoint-restaurant-menu' ); ?></th><td><p class="description"><?php esc_html_e( 'Optionally force a selected Section to begin at the top of a new column.', 'jellopoint-restaurant-menu' ); ?></p><?php foreach ( $document['sections'] as $section ) : ?><label class="jprm-check"><input type="checkbox" name="jprm_print[column_breaks][]" value="<?php echo (int) $section['id']; ?>" <?php checked( in_array( (int) $section['id'], $settings['column_breaks'], true ) ); ?> /> <?php echo esc_html( (string) $section['name'] ); ?></label><?php endforeach; ?></td></tr><?php endif; ?>
				</table>
				<button type="submit" class="button button-primary" name="action" value="jprm_save_print_document"><?php esc_html_e( 'Save Print Settings', 'jellopoint-restaurant-menu' ); ?></button>
				<?php if ( $document ) : ?>
					<button type="submit" class="button button-secondary" name="action" value="jprm_preview_print_document" formtarget="_blank"><?php esc_html_e( 'Open Print Preview', 'jellopoint-restaurant-menu' ); ?></button>
					<button type="submit" class="button button-secondary" name="action" value="jprm_preview_print_document" formtarget="_blank" formaction="<?php echo esc_url( add_query_arg( 'auto_print', '1', admin_url( 'admin-post.php' ) ) ); ?>"><?php esc_html_e( 'Print / Save as PDF', 'jellopoint-restaurant-menu' ); ?></button>
					<span class="description"><?php esc_html_e( 'Choose “Save as PDF” in the browser print window to download the menu.', 'jellopoint-restaurant-menu' ); ?></span>
				<?php endif; ?>
			</form>
			<?php if ( $document ) : ?><div class="card"><h2><?php esc_html_e( 'Document Source Check', 'jellopoint-restaurant-menu' ); ?></h2><p><strong><?php echo esc_html( (string) $document['menu']['name'] ); ?></strong></p><p><?php printf( esc_html__( '%1$d Sections and %2$d published Menu Items are ready for the printable templates.', 'jellopoint-restaurant-menu' ), count( $document['sections'] ), $item_count ); ?></p><p><?php esc_html_e( 'Prices, Price Labels, Dietary Badges and their icons are included in the document data.', 'jellopoint-restaurant-menu' ); ?></p></div><?php endif; ?>
		</div>
		<style>.jprm-print-settings .form-table{max-width:1000px}.jprm-margin-grid,.jprm-control-grid{display:grid;grid-template-columns:repeat(4,minmax(110px,160px));gap:12px}.jprm-margin-grid label,.jprm-control-grid label{display:grid;gap:4px}.jprm-logo-control{display:flex;align-items:center;gap:8px}.jprm-logo-control img{display:block;max-width:160px;max-height:70px}.jprm-check{display:inline-flex;align-items:center;margin:0 20px 8px 0}.jprm-print-settings .card{max-width:850px;margin-top:24px}@media(max-width:782px){.jprm-margin-grid,.jprm-control-grid{grid-template-columns:repeat(2,minmax(100px,1fr))}}</style>
		<script>jQuery(function($){var frame;$('#jprm-select-logo').on('click',function(){if(frame){frame.open();return}frame=wp.media({title:'<?php echo esc_js( __( 'Choose Logo', 'jellopoint-restaurant-menu' ) ); ?>',button:{text:'<?php echo esc_js( __( 'Use Logo', 'jellopoint-restaurant-menu' ) ); ?>'},multiple:false});frame.on('select',function(){var image=frame.state().get('selection').first().toJSON();$('#jprm-print-logo-id').val(image.id);$('#jprm-print-logo-preview').html('<img src="'+(image.sizes&&image.sizes.medium?image.sizes.medium.url:image.url)+'" alt="" />')});frame.open()});$('#jprm-remove-logo').on('click',function(){$('#jprm-print-logo-id').val('0');$('#jprm-print-logo-preview').empty()})});</script>
		<?php
	}

	public static function preview() : void {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to access this preview.', 'jellopoint-restaurant-menu' ) ); }
		if ( 'POST' === (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );
			$raw = isset( $_POST['jprm_print'] ) && is_array( $_POST['jprm_print'] ) ? wp_unslash( $_POST['jprm_print'] ) : [];
			$settings = Print_Document_Settings::sanitize( $raw );
		} else {
			check_admin_referer( 'jprm_preview_print_document' );
			$settings = Print_Document_Settings::get();
		}
		$document = Print_Document_Builder::build( (int) $settings['menu_id'], $settings );
		if ( ! $document ) { wp_die( esc_html__( 'Select and save a valid Menu first.', 'jellopoint-restaurant-menu' ) ); }
		$document['auto_print'] = isset( $_GET['auto_print'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['auto_print'] ) );
		nocache_headers();
		Print_Document_Renderer::render( $document );
		exit;
	}

	private static function font_options( string $current ) : void {
		foreach ( [ 'serif' => __( 'Serif', 'jellopoint-restaurant-menu' ), 'sans' => __( 'Sans Serif', 'jellopoint-restaurant-menu' ), 'modern' => __( 'Modern', 'jellopoint-restaurant-menu' ), 'classic' => __( 'Classic', 'jellopoint-restaurant-menu' ) ] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
	}

	private static function alignment_select( string $name, string $current ) : void {
		echo '<select name="jprm_print[' . esc_attr( $name ) . ']">';
		foreach ( [ 'left' => __( 'Left', 'jellopoint-restaurant-menu' ), 'center' => __( 'Center', 'jellopoint-restaurant-menu' ), 'right' => __( 'Right', 'jellopoint-restaurant-menu' ) ] as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select>';
	}
}
