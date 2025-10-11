<?php
/**
 * JelloPoint Restaurant Menu – Admin: Dietary Badges
 *
 * Matches the Price Labels UI:
 * 1. Drag handle (sortable)
 * 2. Name
 * 3. Icon choose/clear with preview
 * 4. Active
 * 5. Delete (trash)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_Admin_Dietary_Badges' ) ) :

class JPRM_Admin_Dietary_Badges {

	const PAGE_SLUG = 'jprm-dietary-badges';

	protected $capability  = 'manage_options';
	protected $nonce_name  = 'jprm_dietary_badges_nonce';
	protected $nonce_action= 'jprm_dietary_badges_save';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;
		add_action( 'admin_post_jprm_save_dietary_badges', [ $this, 'handle_post' ] );
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jprm' ) );
		}

		$rows = $this->store->get_rows();

		// Assets (self-contained, like Labels page behavior)
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		$style_handle = 'jprm-admin-badges-inline';
		wp_register_style( $style_handle, false );
		wp_enqueue_style( $style_handle );
		wp_add_inline_style( $style_handle, $this->inline_css() );

		wp_add_inline_script( 'jquery', $this->inline_js(), 'after' );

		?>
		<div class="wrap jprm-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Dietary Badges', 'jprm' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Drag rows to reorder. Click the icon to choose or clear. Use the trash to delete a row.', 'jprm' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jprm-badges-form">
				<?php wp_nonce_field( $this->nonce_action, $this->nonce_name ); ?>
				<input type="hidden" name="action" value="jprm_save_dietary_badges" />

				<table class="widefat fixed striped jprm-table" id="jprm-badges-table">
					<thead>
						<tr>
							<th style="width:36px;"></th>
							<th><?php esc_html_e( 'Name', 'jprm' ); ?></th>
							<th style="width:140px;"><?php esc_html_e( 'Icon', 'jprm' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( 'Active', 'jprm' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Actions', 'jprm' ); ?></th>
						</tr>
					</thead>
					<tbody class="jprm-rows">
					<?php
					if ( ! empty( $rows ) ) :
						foreach ( $rows as $i => $row ) :
							echo $this->row_html( $i, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						endforeach;
					endif;
					?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="5">
								<button type="button" class="button button-secondary jprm-add-row">
									<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle"></span>
									<?php esc_html_e( 'Add Row', 'jprm' ); ?>
								</button>
								<button type="submit" class="button button-primary jprm-save">
									<span class="dashicons dashicons-yes-alt" style="vertical-align:middle"></span>
									<?php esc_html_e( 'Save Badges', 'jprm' ); ?>
								</button>
							</td>
						</tr>
					</tfoot>
				</table>
			</form>

			<script type="text/html" id="tmpl-jprm-badge-row">
				<?php echo $this->row_html( '__INDEX__', $this->store->blank_row() ); // phpcs:ignore ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Render a single row.
	 */
	protected function row_html( $index, $row ) : string {
		$name     = isset( $row['name'] ) ? $row['name'] : '';
		$icon_id  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
		$icon_url = isset( $row['icon_url'] ) ? $row['icon_url'] : '';
		$active   = ! empty( $row['active'] );
		$order    = isset( $row['order'] ) ? (int) $row['order'] : (int) $index;

		$preview = $icon_url
			? '<img src="' . esc_url( $icon_url ) . '" alt="" class="jprm-icon-img" />'
			: '<span class="dashicons dashicons-format-image jprm-icon-placeholder" aria-hidden="true"></span>';

		ob_start();
		?>
		<tr class="jprm-row" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<td class="jprm-cell-drag">
				<span class="dashicons dashicons-move jprm-sort" title="<?php esc_attr_e( 'Drag to reorder', 'jprm' ); ?>"></span>
				<input type="hidden" class="jprm-order" name="jprm_badges[<?php echo esc_attr( (string) $index ); ?>][order]" value="<?php echo esc_attr( (string) $order ); ?>" />
			</td>

			<td class="jprm-cell-name">
				<input type="text" class="regular-text" name="jprm_badges[<?php echo esc_attr( (string) $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" />
			</td>

			<td class="jprm-cell-icon">
				<div class="jprm-icon-wrap">
					<a href="#" class="jprm-choose-icon" title="<?php esc_attr_e( 'Choose icon', 'jprm' ); ?>">
						<span class="jprm-icon-preview"><?php echo $preview; // phpcs:ignore ?></span>
					</a>
					<a href="#" class="jprm-clear-icon dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Clear icon', 'jprm' ); ?>"></a>
				</div>
				<input type="hidden" class="jprm-icon-id"  name="jprm_badges[<?php echo esc_attr( (string) $index ); ?>][icon_id]"  value="<?php echo esc_attr( (string) $icon_id ); ?>" />
				<input type="hidden" class="jprm-icon-url" name="jprm_badges[<?php echo esc_attr( (string) $index ); ?>][icon_url]" value="<?php echo esc_url( $icon_url ); ?>" />
			</td>

			<td class="jprm-cell-active">
				<label>
					<input type="checkbox" name="jprm_badges[<?php echo esc_attr( (string) $index ); ?>][active]" value="1" <?php checked( $active ); ?> />
					<?php esc_html_e( 'Active', 'jprm' ); ?>
				</label>
			</td>

			<td class="jprm-cell-actions">
				<a href="#" class="button button-link-delete jprm-delete" title="<?php esc_attr_e( 'Delete row', 'jprm' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</a>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle save.
	 */
	public function handle_post() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jprm' ) );
		}

		check_admin_referer( $this->nonce_action, $this->nonce_name );

		$rows = isset( $_POST['jprm_badges'] ) ? wp_unslash( $_POST['jprm_badges'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->store->save_rows( $rows );

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Minimal CSS to match the Labels look-and-feel.
	 */
	protected function inline_css() : string {
		return '
		.jprm-table .jprm-cell-drag { width:36px; text-align:center; }
		.jprm-table .jprm-sort { cursor:move; opacity:0.7; }
		.jprm-icon-wrap { display:flex; align-items:center; gap:.5rem; }
		.jprm-icon-img { width:28px; height:28px; object-fit:contain; border-radius:3px; background:#fff; border:1px solid #ccd0d4; }
		.jprm-icon-placeholder { font-size:20px; opacity:.6; }
		.jprm-clear-icon { text-decoration:none; line-height:1; }
		.jprm-cell-actions .button-link-delete .dashicons { color:#b32d2e; }
		.jprm-row.ui-sortable-helper { background:#fffbe5; }
		';
	}

	/**
	 * Inline JS for sorting, add/delete, and media selection.
	 */
	protected function inline_js() : string {
		$t_select = esc_js( __( 'Select Badge Icon', 'jprm' ) );
		$t_use    = esc_js( __( 'Use this icon', 'jprm' ) );

		return <<<JS
		(function($){
			var \$tbody = \$('#jprm-badges-table .jprm-rows');
			var tmpl = \$('#tmpl-jprm-badge-row').html();
			var idxCounter = (function(){ // find max existing index to avoid collisions
				var max = -1;
				\$tbody.find('tr.jprm-row').each(function(){
					var i = parseInt($(this).attr('data-index'), 10);
					if (!isNaN(i) && i > max) max = i;
				});
				return max + 1;
			})();

			function renumberOrders(){
				\$tbody.find('tr.jprm-row').each(function(i){
					$(this).find('input.jprm-order').val(i);
				});
			}

			\$tbody.sortable({
				handle: '.jprm-sort',
				axis: 'y',
				helper: function(e, ui){
					ui.children().each(function(){ $(this).width($(this).width()); });
					return ui;
				},
				update: renumberOrders
			});

			$('.jprm-add-row').on('click', function(){
				var html = tmpl.replace(/__INDEX__/g, idxCounter);
				\$tbody.append(html);
				idxCounter++;
				renumberOrders();
			});

			\$tbody.on('click', '.jprm-delete', function(e){
				e.preventDefault();
				$(this).closest('tr.jprm-row').remove();
				renumberOrders();
			});

			function openFrame(cb){
				var frame = wp.media({
					title: '{$t_select}',
					button: { text: '{$t_use}' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					cb(att);
				});
				frame.open();
			}

			// Choose icon by clicking preview or the choose button
			\$tbody.on('click', '.jprm-choose-icon, .jprm-icon-preview', function(e){
				e.preventDefault();
				var \$row = $(this).closest('tr.jprm-row');
				openFrame(function(att){
					\$row.find('.jprm-icon-id').val(att.id);
					\$row.find('.jprm-icon-url').val(att.url);
					\$row.find('.jprm-icon-preview').html('<img src=\"'+att.url+'\" alt=\"\" class=\"jprm-icon-img\"/>');
				});
			});

			\$tbody.on('click', '.jprm-clear-icon', function(e){
				e.preventDefault();
				var \$row = $(this).closest('tr.jprm-row');
				\$row.find('.jprm-icon-id').val('0');
				\$row.find('.jprm-icon-url').val('');
				\$row.find('.jprm-icon-preview').html('<span class="dashicons dashicons-format-image jprm-icon-placeholder" aria-hidden="true"></span>');
			});
		})(jQuery);
		JS;
	}
}

endif;
