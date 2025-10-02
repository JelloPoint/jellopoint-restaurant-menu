<?php
/**
 * Labels store + full Admin UI for Price Labels (compact fields, safe redirect).
 * - Reads/writes option 'jprm_price_labels_v2' (array or JSON string)
 * - Resolve helpers for frontend
 * - Admin page with media icon picker, add/remove, active, order
 *
 * NOTE: Global class (no namespace) for maximal compatibility.
 */
if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('JPRM_Labels_Store') ) :

class JPRM_Labels_Store {

	/* =====================================================================
	 * PUBLIC READ API (used by frontend/widget)
	 * ===================================================================*/

	/** Return the raw list as array of rows. */
	public static function all() : array {
		$opt = get_option( 'jprm_price_labels_v2', [] );

		if ( is_string( $opt ) ) {
			$tmp = json_decode( $opt, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $tmp ) ) {
				$opt = $tmp;
			}
		}

		return is_array( $opt ) ? $opt : [];
	}

	public static function map_by_id() : array {
		$out = [];
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) ) continue;
			$id = isset($row['id']) ? (string)$row['id'] : '';
			if ( $id === '' ) continue;
			$out[ $id ] = $row;
		}
		return $out;
	}

	public static function map_by_slug() : array {
		$out = [];
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) ) continue;
			$slug = isset($row['slug']) ? (string)$row['slug'] : '';
			if ( $slug === '' ) continue;
			$out[ $slug ] = $row;
		}
		return $out;
	}

	public static function get_by_ref( string $ref ) : ?array {
		$ref = trim( $ref );
		if ( $ref === '' ) return null;

		$by_id   = self::map_by_id();
		$by_slug = self::map_by_slug();

		if ( isset( $by_id[ $ref ] ) )   return $by_id[ $ref ];
		if ( isset( $by_slug[ $ref ] ) ) return $by_slug[ $ref ];
		return null;
	}

	/**
	 * Resolve a label reference (id/slug) OR literal text into:
	 *   ['label_text' => string, 'icon_id' => int]
	 */
	public static function resolve( string $ref_or_text ) : array {
		$ref_or_text = trim($ref_or_text);
		if ( $ref_or_text === '' ) return [ 'label_text' => '', 'icon_id' => 0 ];

		$row = self::get_by_ref( $ref_or_text );
		if ( $row ) {
			return [
				'label_text' => (string) ( $row['label'] ?? $ref_or_text ),
				'icon_id'    => isset($row['icon_id']) ? (int)$row['icon_id'] : 0,
			];
		}

		// treat as literal text
		return [ 'label_text' => $ref_or_text, 'icon_id' => 0 ];
	}

	/* =====================================================================
	 * ADMIN UI (submenu + page + save + assets)
	 * ===================================================================*/

	public static function boot_admin_ui() : void {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ __CLASS__, 'attach_submenu' ], 99 ); // late: parent likely exists
			add_action( 'admin_post_jprm_save_labels', [ __CLASS__, 'handle_save' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		}
	}

	/**
	 * Attach "Price Labels" under the existing Jellopoint parent.
	 * We DO NOT create a new top-level. Fallback to Settings if not found.
	 */
	public static function attach_submenu() : void {
		$parent = self::detect_parent_slug();
		if ( ! $parent ) $parent = 'options-general.php';

		// Prevent duplicates
		if ( self::submenu_exists( $parent, 'jprm-price-labels' ) ) return;

		add_submenu_page(
			$parent,
			__( 'Price Labels', 'jellopoint-restaurant-menu' ),
			__( 'Price Labels', 'jellopoint-restaurant-menu' ),
			'manage_options',
			'jprm-price-labels',
			[ __CLASS__, 'render_admin_page' ],
			10
		);
	}

	protected static function detect_parent_slug() {
		global $admin_page_hooks, $menu;

		$candidates = apply_filters( 'jprm/admin_parent_slug_candidates', [] );
		if ( ! is_array( $candidates ) ) $candidates = [];

		$common = [ 'jellopoint-menu', 'jellopoint_root', 'jellopoint', 'jprm_root' ];
		$candidates = array_values( array_unique( array_merge( $candidates, $common ) ) );

		foreach ( $candidates as $slug ) {
			if ( isset( $admin_page_hooks[ $slug ] ) ) return $slug;
		}

		// scan for a title containing "JelloPoint"
		if ( is_array( $menu ) ) {
			foreach ( $menu as $m ) {
				$title = isset( $m[0] ) ? wp_strip_all_tags( (string)$m[0] ) : '';
				$slug  = isset( $m[2] ) ? (string)$m[2] : '';
				if ( $title && stripos( $title, 'jellopoint' ) !== false && $slug ) return $slug;
			}
		}
		return false;
	}

	protected static function submenu_exists( string $parent, string $slug ) : bool {
		global $submenu;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) return false;
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) return true;
		}
		return false;
	}

	/** Enqueue media + JS for our admin page only (safe handles + nowdoc). */
	public static function enqueue_admin_assets( $hook ) : void {
		// Load only on our page
		$is_our_screen = ( isset( $_GET['page'] ) && $_GET['page'] === 'jprm-price-labels' );
		if ( ! $is_our_screen ) return;

		wp_enqueue_media();

		// Register lightweight handles so we can attach inline scripts/styles.
		if ( ! wp_script_is( 'jprm-labels-admin-js', 'registered' ) ) {
			wp_register_script( 'jprm-labels-admin-js', false, [ 'jquery' ], '1.1', true );
		}
		if ( ! wp_style_is( 'jprm-labels-admin-css', 'registered' ) ) {
			wp_register_style( 'jprm-labels-admin-css', false, [], '1.1' );
		}

		wp_enqueue_script( 'jprm-labels-admin-js' );
		wp_enqueue_style( 'jprm-labels-admin-css' );

		// JS (NOWDOC to avoid PHP ${} interpolation)
		$js = <<<'JS'
jQuery(function($){
	function newRow(data){
		data = data || {};
		var idx = $('.jprm-label-row').length;
		var id  = data.id || ('pl-' + idx);
		var iconHTML = data.icon_html || '';
		var active = data.active ? 'checked' : '';
		var order  = (data.order != null ? data.order : idx);

		var html = ''
		+ '<tr class="jprm-label-row">'
		+   '<td><input type="text" class="regular-text jprm-id" name="labels['+idx+'][id]" value="'+id+'" /></td>'
		+   '<td><input type="text" class="regular-text jprm-label" name="labels['+idx+'][label]" value="'+(data.label||'')+'" /></td>'
		+   '<td><input type="text" class="regular-text jprm-slug" name="labels['+idx+'][slug]" value="'+(data.slug||'')+'" /></td>'
		+   '<td class="jprm-icon-cell">'
		+     '<input type="hidden" class="jprm-icon-id" name="labels['+idx+'][icon_id]" value="'+(data.icon_id||0)+'">'
		+     '<span class="jprm-icon-preview">'+iconHTML+'</span>'
		+     '<button type="button" class="button jprm-pick-icon">Select</button> '
		+     '<button type="button" class="button jprm-clear-icon">Clear</button>'
		+   '</td>'
		+   '<td style="text-align:center">'
		+     '<input type="checkbox" name="labels['+idx+'][active]" value="1" '+active+'>'
		+   '</td>'
		+   '<td><input type="number" class="small-text" name="labels['+idx+'][order]" value="'+order+'" /></td>'
		+   '<td><button type="button" class="button button-link-delete jprm-remove-row">Remove</button></td>'
		+ '</tr>';

		return $(html);
	}

	// Add row
	$('#jprm-add-row').on('click', function(e){
		e.preventDefault();
		$('#jprm-labels-table tbody').append(newRow());
	});

	// Remove row
	$('#jprm-labels-table').on('click','.jprm-remove-row', function(){
		$(this).closest('tr').remove();
	});

	// Media picker
	var frame;
	$('#jprm-labels-table').on('click','.jprm-pick-icon', function(e){
		e.preventDefault();
		var $cell = $(this).closest('.jprm-icon-cell');
		if ( frame ) { frame.open(); return; }
		frame = wp.media({
			title: 'Select Icon',
			button: { text: 'Use this icon' },
			multiple: false
		});
		frame.on('select', function(){
			var att = frame.state().get('selection').first().toJSON();
			var url = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
			$cell.find('.jprm-icon-id').val(att.id);
			$cell.find('.jprm-icon-preview').html('<img src="'+url+'" style="width:24px;height:24px;border-radius:3px;vertical-align:middle">');
		});
		frame.open();
	});
	$('#jprm-labels-table').on('click','.jprm-clear-icon', function(e){
		e.preventDefault();
		var $cell = $(this).closest('.jprm-icon-cell');
		$cell.find('.jprm-icon-id').val('0');
		$cell.find('.jprm-icon-preview').empty();
	});
});
JS;

		$css = <<<'CSS'
/* compact widths */
#jprm-labels-table input.jprm-id { width: 110px; }
#jprm-labels-table input.jprm-label { width: 280px; }
#jprm-labels-table input.jprm-slug { width: 220px; }

#jprm-labels-table .jprm-icon-cell { white-space:nowrap; }
#jprm-labels-table .jprm-icon-preview { display:inline-block; width:28px; height:28px; margin-right:6px; vertical-align:middle; }
#jprm-labels-table .small-text { width:70px; }
CSS;

		wp_add_inline_script( 'jprm-labels-admin-js', $js );
		wp_add_inline_style( 'jprm-labels-admin-css', $css );
	}

	/** Render the admin page (with full form). */
	public static function render_admin_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jellopoint-restaurant-menu' ) );
		}
		$labels = self::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Price Labels', 'jellopoint-restaurant-menu' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
				<?php wp_nonce_field( 'jprm_labels_save', 'jprm_labels_nonce' ); ?>
				<input type="hidden" name="action" value="jprm_save_labels" />

				<table class="widefat striped" id="jprm-labels-table">
					<thead>
						<tr>
							<th style="width:12%"><?php esc_html_e('ID', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:26%"><?php esc_html_e('Label', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:20%"><?php esc_html_e('Slug', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:20%"><?php esc_html_e('Icon', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:8%; text-align:center"><?php esc_html_e('Active', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:8%"><?php esc_html_e('Order', 'jellopoint-restaurant-menu'); ?></th>
							<th style="width:6%">&nbsp;</th>
						</tr>
					</thead>
					<tbody>
					<?php
					if ( ! empty( $labels ) ) :
						foreach ( $labels as $i => $row ) :
							$id      = isset($row['id']) ? (string)$row['id'] : 'pl-' . intval($i);
							$label   = isset($row['label']) ? (string)$row['label'] : '';
							$slug    = isset($row['slug']) ? (string)$row['slug'] : '';
							$icon_id = isset($row['icon_id']) ? (int)$row['icon_id'] : 0;
							$active  = ! empty($row['active']);
							$order   = isset($row['order']) ? (int)$row['order'] : intval($i);

							$icon_html = $icon_id ? wp_get_attachment_image( $icon_id, [24,24], false, [ 'style'=>'width:24px;height:24px;border-radius:3px;vertical-align:middle' ] ) : '';
							?>
							<tr class="jprm-label-row">
								<td><input type="text" class="regular-text jprm-id" name="labels[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($id); ?>" /></td>
								<td><input type="text" class="regular-text jprm-label" name="labels[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($label); ?>" /></td>
								<td><input type="text" class="regular-text jprm-slug" name="labels[<?php echo esc_attr($i); ?>][slug]" value="<?php echo esc_attr($slug); ?>" /></td>
								<td class="jprm-icon-cell">
									<input type="hidden" class="jprm-icon-id" name="labels[<?php echo esc_attr($i); ?>][icon_id]" value="<?php echo esc_attr($icon_id); ?>">
									<span class="jprm-icon-preview"><?php echo $icon_html; ?></span>
									<button type="button" class="button jprm-pick-icon"><?php esc_html_e('Select', 'jellopoint-restaurant-menu'); ?></button>
									<button type="button" class="button jprm-clear-icon"><?php esc_html_e('Clear', 'jellopoint-restaurant-menu'); ?></button>
								</td>
								<td style="text-align:center"><input type="checkbox" name="labels[<?php echo esc_attr($i); ?>][active]" value="1" <?php checked( $active ); ?>></td>
								<td><input type="number" class="small-text" name="labels[<?php echo esc_attr($i); ?>][order]" value="<?php echo esc_attr($order); ?>" /></td>
								<td><button type="button" class="button button-link-delete jprm-remove-row"><?php esc_html_e('Remove', 'jellopoint-restaurant-menu'); ?></button></td>
							</tr>
							<?php
						endforeach;
					endif;
					?>
					</tbody>
				</table>

				<p><button type="button" class="button button-secondary" id="jprm-add-row"><?php esc_html_e('Add Label', 'jellopoint-restaurant-menu'); ?></button></p>

				<?php submit_button( __( 'Save Labels', 'jellopoint-restaurant-menu' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Handle POST save for labels. */
	public static function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jellopoint-restaurant-menu' ) );
		}
		check_admin_referer( 'jprm_labels_save', 'jprm_labels_nonce' );

		$labels = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : [];
		$out = [];

		foreach ( $labels as $row ) {
			if ( ! is_array( $row ) ) continue;

			$id      = isset($row['id']) ? sanitize_text_field( $row['id'] ) : '';
			$label   = isset($row['label']) ? sanitize_text_field( $row['label'] ) : '';
			$slug    = isset($row['slug']) ? sanitize_title( $row['slug'] ) : '';
			$icon_id = isset($row['icon_id']) ? intval( $row['icon_id'] ) : 0;
			$active  = ! empty( $row['active'] ) ? 1 : 0;
			$order   = isset($row['order']) ? intval( $row['order'] ) : 0;

			if ( $id === '' && $label === '' && $slug === '' ) continue;

			$out[] = [
				'id'      => $id !== '' ? $id : ('pl-' . count($out)),
				'label'   => $label,
				'slug'    => $slug,
				'icon_id' => max(0, $icon_id),
				'active'  => $active,
				'order'   => $order,
			];
		}

		// Reindex by order
		usort( $out, function($a,$b){ return intval($a['order']) <=> intval($b['order']); } );

		update_option( 'jprm_price_labels_v2', wp_json_encode( $out, JSON_UNESCAPED_UNICODE ), false );

		// ✅ Robust redirect back to our page (works regardless of parent slug)
		$url = function_exists('menu_page_url') ? menu_page_url( 'jprm-price-labels', false ) : '';
		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=jprm-price-labels' );
		}
		$url = add_query_arg( 'updated', 1, $url );

		wp_safe_redirect( $url );
		exit;
	}
}

// Boot the admin UI from this file.
JPRM_Labels_Store::boot_admin_ui();

endif;
