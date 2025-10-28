<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var string $export_url */
/** @var string $import_url */
/** @var string $nonce_field */
/** @var array  $messages */
?>
<div class="wrap jprm-ie-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'JPRM Import/Export', 'jellopoint-restaurant-menu' ); ?></h1>

	<?php if ( ! empty( $messages ) ) : ?>
		<div class="notice notice-info is-dismissible">
			<p><?php echo esc_html( implode( ' — ', $messages ) ); ?></p>
		</div>
	<?php endif; ?>

	<div class="jprm-ie-grid">
		<section class="jprm-card">
			<h2><?php esc_html_e( 'Export', 'jellopoint-restaurant-menu' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $export_url ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p>
					<label>
						<?php esc_html_e( 'Format', 'jellopoint-restaurant-menu' ); ?><br/>
						<select name="format">
							<option value="json">JSON (Backup – lossless)</option>
							<option value="csv">CSV (For Excel/Sheets)</option>
						</select>
					</label>
				</p>
				<p>
					<?php submit_button( __( 'Export', 'jellopoint-restaurant-menu' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</section>

		<section class="jprm-card">
			<h2><?php esc_html_e( 'Import', 'jellopoint-restaurant-menu' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $import_url ); ?>" enctype="multipart/form-data">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p>
					<input type="file" name="jprm_import_file" accept=".json,.csv" required />
				</p>
				<p>
					<label><input type="checkbox" name="dry_run" value="1" checked /> <?php esc_html_e( 'Dry run (no changes)', 'jellopoint-restaurant-menu' ); ?></label><br/>
					<label><input type="checkbox" name="create_missing_terms" value="1" /> <?php esc_html_e( 'Create missing Menus/Sections', 'jellopoint-restaurant-menu' ); ?></label><br/>
					<label><input type="checkbox" name="attach_images" value="1" /> <?php esc_html_e( 'Re-attach images (if present)', 'jellopoint-restaurant-menu' ); ?></label>
				</p>
				<p>
					<?php submit_button( __( 'Validate (Dry Run)', 'jellopoint-restaurant-menu' ), 'secondary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'No data will be changed in dry-run.', 'jellopoint-restaurant-menu' ); ?></span>
				</p>
			</form>
		</section>
	</div>
</div>
