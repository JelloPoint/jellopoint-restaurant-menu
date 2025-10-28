<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var string $export_url */
/** @var string $import_url */
/** @var string $nonce_field */
/** @var array  $messages */
/** @var array|null $import_report */ // <- comes from render_page()
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

		<?php if ( isset( $import_report ) && is_array( $import_report ) ) : ?>
		<!-- >>> ADDED: Import Report card -->
		<section class="jprm-card">
			<h2><?php esc_html_e( 'Import Report', 'jellopoint-restaurant-menu' ); ?></h2>

			<p>
				<strong>
					<?php echo ! empty( $import_report['dry_run'] )
						? esc_html__( 'Dry Run', 'jellopoint-restaurant-menu' )
						: esc_html__( 'Committed', 'jellopoint-restaurant-menu' ); ?>
				</strong>
				— <?php
				printf(
					/* translators: 1: created, 2: updated, 3: skipped */
					esc_html__( 'Created: %1$d, Updated: %2$d, Skipped: %3$d', 'jellopoint-restaurant-menu' ),
					(int) ( $import_report['created'] ?? 0 ),
					(int) ( $import_report['updated'] ?? 0 ),
					(int) ( $import_report['skipped'] ?? 0 )
				);
				?>
			</p>

			<?php if ( ! empty( $import_report['errors'] ) ) : ?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Errors', 'jellopoint-restaurant-menu' ); ?>:</strong></p>
					<ul>
						<?php foreach ( (array) $import_report['errors'] as $e ) : ?>
							<li><?php echo esc_html( (string) $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $import_report['items'] ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Action', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Old ID', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'New ID', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Title', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Menus', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Sections', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Badges', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Error', 'jellopoint-restaurant-menu' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( (array) $import_report['items'], 0, 50 ) as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $row['action'] ?? '' ) ); ?></td>
								<td><?php echo (int) ( $row['post_id_old'] ?? 0 ); ?></td>
								<td><?php echo (int) ( $row['post_id_new'] ?? 0 ); ?></td>
								<td><?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $row['mode'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', (array) ( $row['menus'] ?? [] ) ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', (array) ( $row['sections'] ?? [] ) ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', (array) ( $row['badges'] ?? [] ) ) ); ?></td>
								<td><?php echo esc_html( (string) ( $row['error'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( (array) $import_report['items'] ) > 50 ) : ?>
					<p class="description"><?php echo esc_html__( 'Showing first 50 rows.', 'jellopoint-restaurant-menu' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<!-- <<< END ADDED -->
		<?php endif; ?>

	</div>
</div>
