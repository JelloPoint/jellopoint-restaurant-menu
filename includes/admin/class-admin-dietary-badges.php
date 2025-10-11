<?php
/**
 * JelloPoint Restaurant Menu – Admin: Dietary Badges
 *
 * Admin screen that mirrors the Price Labels management UI,
 * but stores rows under jprm_dietary_badges_v1.
 *
 * @package JPRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_Admin_Dietary_Badges' ) ) :

class JPRM_Admin_Dietary_Badges {

	/** Slug for the submenu page */
	const PAGE_SLUG = 'jprm-dietary-badges';

	/** Capability (mirror Price Labels capability) */
	protected $capability = 'manage_options';

	/** Nonce action/key */
	protected $nonce_action = 'jprm_dietary_badges_save';
	protected $nonce_name   = 'jprm_dietary_badges_nonce';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;
		add_action( 'admin_post_jprm_save_dietary_badges', [ $this, 'handle_post' ] );
	}

	/**
	 * Render page
	 */
	public function render_page() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jprm' ) );
		}

		$rows = $this->store->get_rows(); // Always an array of associative rows.

		// Enqueue WP media for icon selection and a tiny bit of inline JS.
		wp_enqueue_media();
		wp_enqueue_style( 'jprm-admin-badges-inline', false );
		wp_add_inline_style( 'jprm-admin-badges-inline', $this->inline_css() );
		wp_add_inline_script( 'jquery', $this->inline_js(), 'after' );

		?>
		<div class="wrap jprm-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Dietary Badges', 'jprm' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage common dietary/attribute badges (e.g., Vegan, Gluten-Free). These work like Price Labels and can be referenced by your items/templates.', 'jprm' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jprm-form">
				<?php wp_nonce_field( $this->nonce_action, $this->nonce_name ); ?>
				<input type="hidden" name="action" value="jprm_save_dietary_badges" />

				<table class="widefat fixed striped jprm-table" id="jprm-badges-table">
					<thead>
					<tr>
						<th style="width:48px;"><?php esc_html_e( 'Icon', 'jprm' ); ?></th>
						<th style="width:14ch;"><?php esc_html_e( 'Slug', 'jprm' ); ?></th>
						<th><?php esc_html_e( 'Name', 'jprm' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Actions', 'jprm' ); ?></th>
					</tr>
					</thead>
					<tbody class="jprm-rows" data-prototype="1">
					<?php if ( ! empty( $rows ) ) : ?>
						<?php foreach ( $rows as $idx => $row ) : ?>
							<?php echo $this->row_html( $idx, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
					<tfoot>
					<tr>
						<td colspan="4">
							<button type="button" class="button button-secondary jprm-add-row"><?php esc_html_e( 'Add Badge', 'jprm' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'jprm' ); ?></button>
						</td>
					</tr>
					</tfoot>
				</table>
			</form>

			<!-- Template row -->
			<script type="text/html" id="tmpl-jprm-badge-row">
				<?php echo $this->row_html( '__INDEX__', $this->store->blank_row() ); // phpcs:ignore ?>
			</script>
		</div>
		<?php
	}

	/**
	 * POST handler
	 */
	public function handle_post() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jprm' ) );
		}

		check_admin_referer( $this->nonce_action, $this->nonce_name );

		$input = isset( $_POST['jprm_badges'] ) ? wp_unslash( $_POST['jprm_badges'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->store->save_rows( $input );

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render a single table row (kept very close to Labels’ look & feel).
	 *
	 * @param int|string $index
	 * @param array      $row
	 * @return string
	 */
	protected function row_html( $index, $row ) {
		$slug     = isset( $row['slug'] ) ? $row['slug'] : '';
		$name     = isset( $row['name'] ) ? $row['name'] : '';
		$icon_id  = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : 0;
		$icon_url = isset( $row['icon_url'] ) ? $row['icon_url'] : '';

		$icon_preview = $icon_url ? '<img src="' . esc_url( $icon_url ) . '" alt="" style="width:28px;height:28px;object-fit:contain;border-radius:3px;" />' : '<span class="jprm-icon-placeholder">—</span>';

		ob_start();
		?>
		<tr class="jprm-row">
			<td class="jprm-cell-icon">
				<div class="jprm-icon-wrap">
					<span class="jprm-icon-preview"><?php echo $icon_preview; // phpcs:ignore ?></span>
					<input type="hidden" class="jprm-icon-id"   name="jprm_badges[<?php echo esc_attr( $index ); ?>][icon_id]"  value="<?php echo esc_attr( $icon_id ); ?>" />
					<input type="hidden" class="jprm-icon-url"  name="jprm_badges[<?php echo esc_attr( $index ); ?>][icon_url]" value="<?php echo esc_url( $icon_url ); ?>" />
					<div class="jprm-icon-actions">
						<button type="button" class="button button-small jprm-choose-icon"><?php esc_html_e( 'Choose', 'jprm' ); ?></button>
						<button type="button" class="button button-small jprm-clear-icon"><?php esc_html_e( 'Clear', 'jprm' ); ?></button>
					</div>
				</div>
			</td>
			<td>
				<input type="text" class="regular-text" name="jprm_badges[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" placeholder="vegan" />
			</td>
			<td>
				<input type="text" class="regular-text" name="jprm_badges[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Vegan', 'jprm' ); ?>" />
			</td>
			<td class="jprm-actions">
				<button type="button" class="button button-small jprm-delete-row"><?php esc_html_e( 'Delete', 'jprm' ); ?></button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Minimal CSS to match the existing admin table styling you use for Labels.
	 */
	protected function inline_css() {
		return '
		.jprm-wrap .jprm-table .jprm-icon-wrap{display:flex;gap:.5rem;align-items:center}
		.jprm-wrap .jprm-icon-placeholder{display:inline-block;min-width:28px;text-align:center;opacity:.6}
		.jprm-wrap .jprm-actions{white-space:nowrap}
		.jprm-wrap .jprm-icon-actions .button{margin-right:4px}
		';
	}

	/**
	 * Inline JS to mirror the Labels interactions (add row, delete row, pick/clear icon).
	 * Kept self-contained to avoid touching existing scripts.
	 */
	protected function inline_js() {
		$media_title  = esc_js( __( 'Select Badge Icon', 'jprm' ) );
        $media_button = esc_js( __( 'Use this icon', 'jprm' ) );


		return <<<JS
		(function($){
			var \$tbody = \$('#jprm-badges-table .jprm-rows');
			var tmpl = \$('#tmpl-jprm-badge-row').html();

			\$('.jprm-add-row').on('click', function(){
				var idx = \$tbody.find('tr.jprm-row').length;
				var html = tmpl.replace(/__INDEX__/g, idx);
				\$tbody.append(html);
			});

			\$tbody.on('click', '.jprm-delete-row', function(){
				$(this).closest('tr.jprm-row').remove();
			});

			function openFrame(cb){
				var frame = wp.media({
					title: '{$media_title}',
					button: { text: '{$media_button}' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					cb(att);
				});
				frame.open();
			}

			\$tbody.on('click', '.jprm-choose-icon', function(){
				var \$row = $(this).closest('tr.jprm-row');
				openFrame(function(att){
					\$row.find('.jprm-icon-id').val(att.id);
					\$row.find('.jprm-icon-url').val(att.url);
					\$row.find('.jprm-icon-preview').html('<img src=\"'+att.url+'\" style=\"width:28px;height:28px;object-fit:contain;border-radius:3px;\" />');
				});
			});

			\$tbody.on('click', '.jprm-clear-icon', function(){
				var \$row = $(this).closest('tr.jprm-row');
				\$row.find('.jprm-icon-id').val('0');
				\$row.find('.jprm-icon-url').val('');
				\$row.find('.jprm-icon-preview').html('<span class="jprm-icon-placeholder">—</span>');
			});
		})(jQuery);
		JS;
	}
}

endif;
