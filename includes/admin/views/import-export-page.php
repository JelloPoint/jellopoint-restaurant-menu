<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var string      $export_url */
/** @var string      $import_url */
/** @var string      $demo_import_url */
/** @var string      $demo_remove_url */
/** @var string      $install_defaults_url */
/** @var string      $nonce_field */
/** @var array       $messages */
/** @var array|null  $import_report */
/** @var array       $demo_summary */
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
				<?php echo $nonce_field; // phpcs:ignore ?>
				<p>
					<label>
						<?php esc_html_e( 'Format', 'jellopoint-restaurant-menu' ); ?><br/>
						<select name="format">
							<option value="json">JSON (Backup – lossless)</option>
							<option value="csv">CSV (For Excel/Sheets)</option>
						</select>
					</label>
				</p>
				<p><?php submit_button( __( 'Export', 'jellopoint-restaurant-menu' ), 'primary', 'submit', false ); ?></p>
			</form>
		</section>

		<section class="jprm-card">
			<h2><?php esc_html_e( 'Import', 'jellopoint-restaurant-menu' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $import_url ); ?>" enctype="multipart/form-data">
				<?php echo $nonce_field; // phpcs:ignore ?>
				<p><input type="file" name="jprm_import_file" accept=".json,.csv" required /></p>

				<p>
					<label><input type="checkbox" name="create_missing_terms" value="1" /> <?php esc_html_e( 'Create missing Menus/Sections', 'jellopoint-restaurant-menu' ); ?></label><br/>
					<label><input type="checkbox" name="attach_images" value="1" /> <?php esc_html_e( 'Re-attach images (if present)', 'jellopoint-restaurant-menu' ); ?></label><br/>
					<label><input type="checkbox" name="ignore_ids" value="1" checked /> <?php esc_html_e( 'Ignore incoming IDs (always create new items)', 'jellopoint-restaurant-menu' ); ?></label>
				</p>

				<input type="hidden" name="action_type" id="jprm_action_type" value="dry_run" />
				<p style="display:flex; gap:8px; align-items:center;">
					<button type="submit" class="button button-primary" onclick="document.getElementById('jprm_action_type').value='import';">
						<?php esc_html_e( 'Import (Commit Changes)', 'jellopoint-restaurant-menu' ); ?>
					</button>
					<button type="submit" class="button" onclick="document.getElementById('jprm_action_type').value='dry_run';">
						<?php esc_html_e( 'Validate (Dry Run)', 'jellopoint-restaurant-menu' ); ?>
					</button>
					<span class="description"><?php esc_html_e( 'Dry-run simulates changes only — use Import to commit.', 'jellopoint-restaurant-menu' ); ?></span>
				</p>
			</form>
		</section>

		<section class="jprm-card">
			<h2><?php esc_html_e( 'Import Demo Menu', 'jellopoint-restaurant-menu' ); ?></h2>
			<p><?php esc_html_e( 'Create a complete example restaurant menu without changing existing menu items.', 'jellopoint-restaurant-menu' ); ?></p>
			<p>
				<strong><?php echo esc_html( (string) ( $demo_summary['menu'] ?? '' ) ); ?></strong><br />
				<?php echo esc_html( implode( ', ', (array) ( $demo_summary['sections'] ?? [] ) ) ); ?><br />
				<?php
				printf(
					esc_html__( '%d example items, including multi-priced wine and beer.', 'jellopoint-restaurant-menu' ),
					(int) ( $demo_summary['items'] ?? 0 )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( $demo_import_url ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="action_type" id="jprm_demo_action_type" value="dry_run" />
				<p style="display:flex; gap:8px; align-items:center;">
					<button type="submit" class="button button-primary" onclick="document.getElementById('jprm_demo_action_type').value='import';">
						<?php esc_html_e( 'Import Demo Menu', 'jellopoint-restaurant-menu' ); ?>
					</button>
					<button type="submit" class="button" onclick="document.getElementById('jprm_demo_action_type').value='dry_run';">
						<?php esc_html_e( 'Preview Demo Import', 'jellopoint-restaurant-menu' ); ?>
					</button>
				</p>
				<p class="description"><?php esc_html_e( 'Importing again will not create duplicates. Conflicting names stop the import before data is changed.', 'jellopoint-restaurant-menu' ); ?></p>
			</form>
			<hr />
			<form method="post" action="<?php echo esc_url( $demo_remove_url ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Remove all imported demo menu content? Demo items will be moved to the Trash.', 'jellopoint-restaurant-menu' ) ); ?>');">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button type="submit" class="button button-link-delete">
					<?php esc_html_e( 'Remove Demo Content', 'jellopoint-restaurant-menu' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'Only content marked by the demo importer is removed. Menu items are moved to the WordPress Trash.', 'jellopoint-restaurant-menu' ); ?></p>
			</form>
		</section>

		<section class="jprm-card">
			<h2><?php esc_html_e( 'Default Badges & Price Labels', 'jellopoint-restaurant-menu' ); ?></h2>
			<p><?php esc_html_e( 'Install the standard dietary badges and price labels with bundled icons.', 'jellopoint-restaurant-menu' ); ?></p>
			<form method="post" action="<?php echo esc_url( $install_defaults_url ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Install Default Badges & Labels', 'jellopoint-restaurant-menu' ); ?></button>
				<p class="description"><?php esc_html_e( 'Safe to run again. Missing entries and icons are added; your existing names, settings, and selected icons are preserved.', 'jellopoint-restaurant-menu' ); ?></p>
			</form>
		</section>
	</div>

	<?php if ( is_array( $import_report ?? null ) ) : ?>
		<hr/>
		<h2><?php esc_html_e( 'Import Report', 'jellopoint-restaurant-menu' ); ?></h2>

		<?php $report_errors = array_filter( array_map( 'strval', (array) ( $import_report['errors'] ?? [] ) ) ); ?>
		<?php if ( $report_errors ) : ?>
			<div class="notice notice-error inline">
				<?php foreach ( $report_errors as $report_error ) : ?>
					<p><?php echo esc_html( $report_error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p>
			<strong><?php esc_html_e( 'Mode:', 'jellopoint-restaurant-menu' ); ?></strong>
			<?php echo ! empty( $import_report['dry_run'] ) ? esc_html__( 'Dry Run', 'jellopoint-restaurant-menu' ) : esc_html__( 'Committed', 'jellopoint-restaurant-menu' ); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Created', 'jellopoint-restaurant-menu' ); ?>:</strong> <?php echo (int) ($import_report['created'] ?? 0); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Updated', 'jellopoint-restaurant-menu' ); ?>:</strong> <?php echo (int) ($import_report['updated'] ?? 0); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Unchanged', 'jellopoint-restaurant-menu' ); ?>:</strong> <?php echo (int) ($import_report['unchanged'] ?? 0); ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Skipped', 'jellopoint-restaurant-menu' ); ?>:</strong> <?php echo (int) ($import_report['skipped'] ?? 0); ?>
		</p>

		<?php
		$items = is_array( $import_report['items'] ?? null ) ? $import_report['items'] : [];
		if ( $items ) :
		?>
			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Old ID', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'New ID', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Title', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Action', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Price', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Menus', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Sections', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Badges', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Changes', 'jellopoint-restaurant-menu' ); ?></th>
					<th><?php esc_html_e( 'Error', 'jellopoint-restaurant-menu' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $it ) : ?>
					<tr>
						<td><?php echo isset($it['post_id_old']) ? (int) $it['post_id_old'] : 0; ?></td>
						<td><?php echo isset($it['post_id_new']) ? (int) $it['post_id_new'] : 0; ?></td>
						<td><?php echo esc_html( (string) ($it['title'] ?? '') ); ?></td>
						<td><?php echo esc_html( (string) ($it['action'] ?? '') ); ?></td>
						<td><?php echo esc_html( (string) ($it['price_summary'] ?? '') ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) ($it['menus'] ?? []) ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) ($it['sections'] ?? []) ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) ($it['badges'] ?? []) ) ); ?></td>
						<td>
							<?php $changes = is_array( $it['changes'] ?? null ) ? $it['changes'] : []; ?>
							<?php if ( $changes ) : ?>
								<details>
									<summary><?php echo esc_html( implode( ', ', array_keys( $changes ) ) ); ?></summary>
									<pre><?php echo esc_html( (string) wp_json_encode( $changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ($it['error'] ?? '') ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$new_m = array_filter( array_map( 'strval', (array) ( $import_report['new_terms']['menus_list'] ?? [] ) ) );
			$new_s = array_filter( array_map( 'strval', (array) ( $import_report['new_terms']['sections_list'] ?? [] ) ) );
			if ( $new_m || $new_s ) :
			?>
				<h3 style="margin-top:1.25rem;"><?php esc_html_e( 'New Menus/Sections', 'jellopoint-restaurant-menu' ); ?></h3>
				<div class="jprm-two-col">
					<div>
						<strong><?php esc_html_e( 'Menus', 'jellopoint-restaurant-menu' ); ?></strong>
						<ul><?php foreach ( $new_m as $nm ) echo '<li>' . esc_html( $nm ) . '</li>'; ?></ul>
					</div>
					<div>
						<strong><?php esc_html_e( 'Sections', 'jellopoint-restaurant-menu' ); ?></strong>
						<ul><?php foreach ( $new_s as $ns ) echo '<li>' . esc_html( $ns ) . '</li>'; ?></ul>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No items in report.', 'jellopoint-restaurant-menu' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
