<?php
/**
 * JelloPoint Restaurant Menu – Admin: Dietary Badges
 *
 * UI mirrors Price Labels:
 * 1) Drag handle
 * 2) Name
 * 3) Icon (choose/clear via media frame)
 * 4) Active checkbox
 * 5) Delete (trash)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_Admin_Dietary_Badges' ) ) :

class JPRM_Admin_Dietary_Badges {

	const PAGE_SLUG = 'jprm-dietary-badges';

	// If your Labels screen uses custom icons, replace these HTML snippets once:
	const ICON_DRAG   = '<span class="dashicons dashicons-move jprm-sort" title="%s"></span>';
	const ICON_PLACEH = '<span class="dashicons dashicons-format-image jprm-icon-placeholder" aria-hidden="true"></span>';
	const ICON_CLEAR  = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
	const ICON_TRASH  = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';

	protected $capability   = 'manage_options';
	protected $nonce_name   = 'jprm_dietary_badges_nonce';
	protected $nonce_action = 'jprm_dietary_badges_save';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;
		add_action( 'admin_post_jprm_save_dietary_badges', [ $this, 'handle_post' ] );
	}

	public function render_page() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jprm' ) );
		}

		$rows = $this->store->get_rows();

		// Assets.
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		$style_handle = 'jprm-admin-badges-inline';
		wp_register_style( $style_handle, false );
		wp_enqueue_style( $style_handle );
		wp_add_inline_style( $style_handle, $this->inline_css() );

		// Put script after DOM is available by printing it right here.
		?>
		<div class="wrap jprm-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Dietary Badges', 'jprm' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Drag rows to reorder. Click the icon to choose or clear. Use the trash to delete a row.', 'jprm' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="jprm-badges-form">
				<?php wp_nonce_field( $this->nonce_action, $this->nonce_name ); ?>
				<input type="hidden" name="action" value="jprm_save_dietary_badges" />

				<table class="widefat fixed striped jprm-table" id="jprm-badges-table">
					<thead>
						<tr>
							<th style="width:36px;"></th>
							<th><?php esc_html_e( 'Name', 'jprm' ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'Icon', 'jprm' ); ?></th>
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

		<script>
		jQuery(function($){
			var $tbody = $('#jprm-badges-table .jprm-rows');
			var tmpl = $('#tmpl-jprm-badge-row').html();

			function renumberOrders(){
				$tbody.find('tr.jprm-row').each(function(i){
					$(this).find('input.jprm-order').val(i);
				});
			}

			// Find max existing index to avoid name collisions when adding rows
			var nextIndex = (function(){
				var max = -1;
				$tbody.find('tr.jprm-row').each(function(){
					var i = parseInt($(this).attr('data-index'),10);
					if (!isNaN(i) && i > max) max = i;
				});
				return max + 1;
			})();

			$tbody.sortable({
				handle: '.jprm-sort',
				axis: 'y',
				helper: function(e, ui){
					ui.children().each(function(){ $(this).width($(this).width()); });
					return ui;
				},
				update: renumberOrders
			});

			$('.jprm-add-row').on('click', function(){
				var html = tmpl.replace(/__INDEX__/g, nextIndex);
				$tbody.append(html);
				nextIndex++;
				renumberOrders();
			});

			$tbody.on('click', '.jprm-delete', function(e){
				e.preventDefault();
				$(this).closest('tr.jprm-row').remove();
				renumberOrders();
			});

			function openMedia(cb){
				var frame = wp.media({
					title: <?php echo wp_json_encode( __( 'Select Badge Icon', 'jprm' ) ); ?>,
					button: { text: <?php echo wp_json_encode( __( 'Use this icon', 'jprm' ) ); ?> },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					cb(att);
				});
				frame.open();
			}

			// Choose icon: clicking the preview OR the choose button
			$tbody.on('click', '.jprm-choose-icon, .jprm-icon-preview', function(e){
				e.preventDefault();
				var $row = $(this).closest('tr.jprm-row');
				openMedia(function(att){
					$row.find('.jprm-icon-id').val(att.id);
					$row.find('.jprm-icon-url').val(att.url);
					$row.find('.jprm-icon-preview').html('<img src="'+att.url+'" alt="" class="jprm-icon-img">');
				});
			});

			$tbody.on('click', '.jprm-clear-icon', function(e){
				e.preventDefault();
				var $row = $(this).closest('tr.jprm-row');
				$row.find('.jprm-icon-id').val('0');
				$row.find('.jprm-icon-url').val('');
				$row.find('.jprm-icon-preview').html('<?php echo self::ICON_PLACEH; // phpcs:ignore ?>');
			});
		});
		</script>
		<?php
	}

	protected function row_html( $index, $row ) : string {
		$name     = isset( $row['name'] ) ? $row['name'] : '';
		$icon_id  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
		$icon_url = isset( $row['icon_url'] ) ? $row['icon_url'] : '';
		$active   = ! empty( $row['active'] );
		$order    = isset( $row['order'] ) ? (int) $row['order'] : (int) $index;

		$drag_title = esc_attr__( 'Drag to reorder', 'jprm' );
		$drag_html  = sprintf( self::ICON_DRAG, $drag_title );

		$preview = $icon_url
			? '<img src="' . esc_url( $icon_url ) . '" alt="" class="jprm-icon-img" />'
			: self::ICON_PLACEH;

		ob_start();
		?>
		<tr class="jprm-row" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<td class="jprm-cell-drag">
				<?php echo $drag_html; // phpcs:ignore ?>
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
					<a href="#" class="jprm-clear-icon button button-small" title="<?php esc_attr_e( 'Clear icon', 'jprm' ); ?>">
						<?php echo self::ICON_CLEAR; // phpcs:ignore ?>
					</a>
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
					<?php echo self::ICON_TRASH; // phpcs:ignore ?>
				</a>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

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

	protected function inline_css() : string {
		return '
		.jprm-table .jprm-cell-drag { width:36px; text-align:center; }
		.jprm-sort { cursor:move; opacity:.75; }
		.jprm-icon-wrap { display:flex; align-items:center; gap:.5rem; }
		.jprm-icon-img { width:28px; height:28px; object-fit:contain; border-radius:3px; background:#fff; border:1px solid #ccd0d4; }
		.jprm-icon-placeholder { font-size:20px; opacity:.6; }
		.jprm-cell-actions .button-link-delete .dashicons { color:#b32d2e; }
		.jprm-row.ui-sortable-helper { background:#fffbe5; }
		';
	}
}

endif;
