<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels tool.
 *
 * Admin page under the JelloPoint parent menu.
 * - Lists menu items and flattens all price rows (incl. multiple prices).
 * - Adds filters (menu, section, search).
 * - Lets you select price rows and choose a target label.
 * - On submit, shows a summary of what WOULD be updated (no DB writes yet).
 */
final class JPRM_Admin_Bulk_Price_Labels {

	/** Submenu slug for this page. */
	private const PAGE_SLUG  = 'jprm-bulk-price-labels';

	/** Capability — keep it in line with the rest of the plugin tools. */
	private const CAPABILITY = 'edit_posts';

	/**
	 * Bootstrap hooks — call once from the plugin loader (admin only).
	 */
	public static function bootstrap(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Register under the JelloPoint parent menu.
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 30 );

		// Assets only on our own screen.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Register the JelloPoint → Bulk Price Labels screen.
	 */
	public static function register_menu(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$parent_slug = Admin_Menu::PARENT_SLUG; // "jellopoint"

		add_submenu_page(
			$parent_slug,
			__( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ),
			__( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue minimal styling for our screen only.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		$expected = Admin_Menu::PARENT_SLUG . '_page_' . self::PAGE_SLUG;
		if ( $hook_suffix !== $expected ) {
			return;
		}

		$handle = 'jprm-bulk-price-labels-admin';
		$css    = '
			.jprm-bulk-price-labels-wrap .jprm-intro {
				max-width: 900px;
				margin-bottom: 1.5em;
			}
			.jprm-bulk-price-labels-wrap .jprm-intro p {
				margin: 0 0 0.75em;
			}
			.jprm-bulk-price-labels-wrap .jprm-filters {
				margin-bottom: 1em;
				padding: 0.75em 1em;
				background: #f6f7f7;
				border-radius: 4px;
				border: 1px solid #dcdcde;
				display: flex;
				flex-wrap: wrap;
				gap: 0.75em 1.5em;
				align-items: flex-end;
			}
			.jprm-bulk-price-labels-wrap .jprm-filters .field {
				display: flex;
				flex-direction: column;
				gap: 0.25em;
			}
			.jprm-bulk-price-labels-wrap .jprm-filters label {
				font-weight: 600;
			}
			.jprm-bulk-price-labels-wrap table.widefat th.column-select {
				width: 40px;
			}
			.jprm-bulk-price-labels-wrap table.widefat th,
			.jprm-bulk-price-labels-wrap table.widefat td {
				vertical-align: top;
			}
			.jprm-bulk-price-labels-wrap .jprm-bulk-actions {
				margin: 1em 0;
				padding: 0.75em 1em;
				background: #f6f7f7;
				border-radius: 4px;
				border: 1px solid #dcdcde;
				display: flex;
				flex-wrap: wrap;
				gap: 0.75em 1.5em;
				align-items: center;
			}
			.jprm-bulk-price-labels-wrap .jprm-bulk-actions select {
				max-width: 240px;
			}
			.jprm-bulk-price-labels-wrap .jprm-small-meta {
				font-size: 11px;
				color: #666;
				margin-top: 2px;
			}
		';

		wp_register_style( $handle, false, [], '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Render the admin page.
	 *
	 * Uses the proven query logic:
	 * - Menu filter (filter_menu)
	 * - Section filter (filter_section) dependent on menu
	 * - Search (s)
	 * Then flattens all price rows inline into the table.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		// Handle bulk submission (preview only).
		self::handle_bulk_action();

		// ---- Read filters from URL ------------------------------------------
		$current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search          = isset( $_GET['s'] )
			? sanitize_text_field( wp_unslash( $_GET['s'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		// ---- Fetch all menus for the dropdown -------------------------------
		$menus = get_terms( [
			'taxonomy'   => 'jprm_menu',
			'hide_empty' => false,
		] );
		if ( is_wp_error( $menus ) ) {
			$menus = [];
		}

		// ======================================================================
		// 1) Build a "base" query to discover sections for the selected menu
		// ======================================================================

		$base_tax_query = [];

		if ( $current_menu > 0 ) {
			$base_tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'term_id',
				'terms'    => [ $current_menu ],
			];
		}

		$base_args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		];

		if ( ! empty( $base_tax_query ) ) {
			$base_args['tax_query'] = $base_tax_query;
		}

		$base_query = new \WP_Query( $base_args );
		$post_ids   = $base_query->posts;
		wp_reset_postdata();

		// Discover the section terms actually used by these items.
		if ( ! empty( $post_ids ) ) {
			$sections_for_menu = wp_get_object_terms( $post_ids, 'jprm_section', [
				'fields'     => 'all',
				'hide_empty' => false,
			] );
			if ( is_wp_error( $sections_for_menu ) ) {
				$sections_for_menu = [];
			}
		} else {
			$sections_for_menu = [];
		}

		// ======================================================================
		// 2) Build the Section dropdown options
		// ======================================================================

		if ( $current_menu > 0 ) {
			// Only sections that actually occur in items for the selected menu.
			$sections = $sections_for_menu;
		} else {
			// No menu filter yet → show all sections.
			$sections = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
			if ( is_wp_error( $sections ) ) {
				$sections = [];
			}
		}

		// ======================================================================
		// 3) Build the MAIN query for the table (menu + optional section + search)
		// ======================================================================

		$tax_query = [];

		if ( $current_menu > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'term_id',
				'terms'    => [ $current_menu ],
			];
		}

		if ( $current_section > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'term_id',
				'terms'    => [ $current_section ],
			];
		}

		$items_args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $search !== '' ) {
			$items_args['s'] = $search;
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$items_args['tax_query'] = $tax_query;
		}

		$items_query = new \WP_Query( $items_args );

		?>
		<div class="wrap jprm-bulk-price-labels-wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?>
			</h1>

			<hr class="wp-header-end" />

			<div class="jprm-intro">
				<p>
					<?php esc_html_e(
						'This tool flattens all price rows (including multiple prices per item) so you can select them and assign labels in bulk.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
				<p>
					<?php esc_html_e(
						'Use the filters to narrow down menus, sections or items. Then select the price rows you want to adjust and choose a label in the bulk actions area.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
			</div>

			<?php
			// ================================
			// Filter form (GET)
			// ================================
			$action = remove_query_arg( [ 'paged' ] );
			?>
			<form method="get" action="<?php echo esc_url( $action ); ?>" id="jprm-bulk-filter-form">
				<?php
				// Preserve page + post_type.
				foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( in_array( $key, [ 'page', 'post_type' ], true ) ) {
						printf(
							'<input type="hidden" name="%s" value="%s" />',
							esc_attr( $key ),
							esc_attr( wp_unslash( $value ) )
						);
					}
				}
				?>
				<div class="jprm-filters">
					<div class="field">
						<label for="jprm-filter-menu"><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label>
						<select name="filter_menu" id="jprm-filter-menu">
							<option value="0"><?php esc_html_e( 'All menus', 'jellopoint-restaurant-menu' ); ?></option>
							<?php foreach ( $menus as $menu_term ) : ?>
								<option value="<?php echo (int) $menu_term->term_id; ?>" <?php selected( $current_menu, (int) $menu_term->term_id ); ?>>
									<?php echo esc_html( $menu_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<label for="jprm-filter-section"><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></label>
						<select name="filter_section" id="jprm-filter-section">
							<option value="0">
								<?php
								echo $current_menu > 0
									? esc_html__( 'All sections for this menu', 'jellopoint-restaurant-menu' )
									: esc_html__( 'All sections', 'jellopoint-restaurant-menu' );
								?>
							</option>
							<?php foreach ( $sections as $sect_term ) : ?>
								<option value="<?php echo (int) $sect_term->term_id; ?>" <?php selected( $current_section, (int) $sect_term->term_id ); ?>>
									<?php echo esc_html( $sect_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<label for="jprm-search"><?php esc_html_e( 'Search items', 'jellopoint-restaurant-menu' ); ?></label>
						<input type="search"
						       id="jprm-search"
						       name="s"
						       value="<?php echo esc_attr( $search ); ?>"
						       placeholder="<?php esc_attr_e( 'Item title contains…', 'jellopoint-restaurant-menu' ); ?>" />
					</div>

					<div class="field">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Filter', 'jellopoint-restaurant-menu' ); ?>
						</button>
					</div>
				</div>
			</form>

			<script>
				// Auto-submit filter form when Menu changes so Sections refresh immediately.
				(function(){
					const form  = document.getElementById('jprm-bulk-filter-form');
					const menu  = document.getElementById('jprm-filter-menu');
					if (!form || !menu) return;
					menu.addEventListener('change', function () {
						form.submit();
					});
				}());
			</script>

			<form method="post">
				<?php wp_nonce_field( 'jprm_bulk_price_labels', 'jprm_bulk_price_labels_nonce' ); ?>

				<?php self::render_bulk_actions( 'top' ); ?>

				<?php
				// ================================
				// MAIN TABLE – one row per price row
				// ================================
				if ( $items_query->have_posts() && function_exists( 'jprm_get_pricegroup_data' ) ) :
					?>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th class="column-select check-column">
									<input type="checkbox" id="jprm-select-all" />
								</th>
								<th><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></th>
								<th><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></th>
								<th><?php esc_html_e( 'Item', 'jellopoint-restaurant-menu' ); ?></th>
								<th><?php esc_html_e( 'Price index', 'jellopoint-restaurant-menu' ); ?></th>
								<th><?php esc_html_e( 'Price', 'jellopoint-restaurant-menu' ); ?></th>
								<th><?php esc_html_e( 'Current label', 'jellopoint-restaurant-menu' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$any_rows = false;

						while ( $items_query->have_posts() ) :
							$items_query->the_post();
							$pid   = get_the_ID();
							$title = get_the_title( $pid );

							$item_menus    = wp_get_object_terms( $pid, 'jprm_menu', [ 'fields' => 'names' ] );
							$item_sections = wp_get_object_terms( $pid, 'jprm_section', [ 'fields' => 'names' ] );

							$menus_str    = ! empty( $item_menus ) && ! is_wp_error( $item_menus )
								? implode( ', ', $item_menus )
								: '';
							$sections_str = ! empty( $item_sections ) && ! is_wp_error( $item_sections )
								? implode( ', ', $item_sections )
								: '';

							$price_rows = jprm_get_pricegroup_data( $pid, [], [] );
							if ( ! is_array( $price_rows ) || empty( $price_rows ) ) {
								continue;
							}

							$index = 0;
							foreach ( $price_rows as $row ) {
								$any_rows = true;

								$formatted  = isset( $row['formatted'] ) ? (string) $row['formatted'] : '';
								$label_text = isset( $row['label_text'] ) ? (string) $row['label_text'] : '';
								$label_id   = isset( $row['label_id'] ) ? (int) $row['label_id'] : 0;

								$row_key = $pid . ':' . $index;
								?>
								<tr>
									<th class="check-column">
										<input type="checkbox"
										       name="jprm_rows[]"
										       value="<?php echo esc_attr( $row_key ); ?>" />
									</th>
									<td>
										<?php
										echo $menus_str !== ''
											? esc_html( $menus_str )
											: '<span class="jprm-small-meta">' . esc_html__( '(no menu)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</td>
									<td>
										<?php
										echo $sections_str !== ''
											? esc_html( $sections_str )
											: '<span class="jprm-small-meta">' . esc_html__( '(no section)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</td>
									<td>
										<strong>
											<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
												<?php echo esc_html( $title ); ?>
											</a>
										</strong>
										<div class="jprm-small-meta">
											<?php
											printf(
												/* translators: 1: post ID */
												esc_html__( 'Item ID: %d', 'jellopoint-restaurant-menu' ),
												(int) $pid
											);
											?>
										</div>
									</td>
									<td><?php echo esc_html( (string) $index ); ?></td>
									<td>
										<?php
										echo $formatted !== ''
											? esc_html( $formatted )
											: '<span class="jprm-small-meta">' . esc_html__( '(no price)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</td>
									<td>
										<?php
										if ( $label_text !== '' ) {
											echo esc_html( $label_text );
										} elseif ( $label_id ) {
											printf(
												/* translators: 1: label ID */
												esc_html__( '(label ID %d, no text)', 'jellopoint-restaurant-menu' ),
												(int) $label_id
											);
										} else {
											echo '<span class="jprm-small-meta">' . esc_html__( '(no label)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										?>
									</td>
								</tr>
								<?php
								$index++;
							}
						endwhile;
						wp_reset_postdata();

						if ( ! $any_rows ) :
							?>
							<tr>
								<td colspan="7">
									<em><?php esc_html_e( 'No price rows found for the current filters.', 'jellopoint-restaurant-menu' ); ?></em>
								</td>
							</tr>
							<?php
						endif;
						?>
						</tbody>
					</table>

					<script>
						(function(){
							const master = document.getElementById('jprm-select-all');
							if (!master) return;
							master.addEventListener('change', function(){
								const checks = document.querySelectorAll('input[name="jprm_rows[]"]');
								for (const c of checks) {
									c.checked = master.checked;
								}
							});
						}());
					</script>
					<?php
				else :
					?>
					<p><em><?php esc_html_e( 'No items match the current filters.', 'jellopoint-restaurant-menu' ); ?></em></p>
					<?php
				endif;
				?>

				<?php self::render_bulk_actions( 'bottom' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Bulk actions UI fragment (top + bottom).
	 */
	private static function render_bulk_actions( string $position ): void {
		?>
		<div class="jprm-bulk-actions jprm-bulk-actions-<?php echo esc_attr( $position ); ?>">
			<strong><?php esc_html_e( 'Bulk action:', 'jellopoint-restaurant-menu' ); ?></strong>

			<select name="jprm_bulk_action">
				<option value=""><?php esc_html_e( '— Select —', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="set_label"><?php esc_html_e( 'Set / change label', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="clear_label"><?php esc_html_e( 'Clear label', 'jellopoint-restaurant-menu' ); ?></option>
			</select>

			<span>
				<?php esc_html_e( 'Target label (preview only):', 'jellopoint-restaurant-menu' ); ?>
				<input type="text"
				       name="jprm_target_label"
				       value=""
				       placeholder="<?php esc_attr_e( 'e.g. Glass / Bottle / Large…', 'jellopoint-restaurant-menu' ); ?>" />
			</span>

			<button type="submit" class="button button-primary" name="jprm_bulk_apply" value="1">
				<?php esc_html_e( 'Apply (preview only)', 'jellopoint-restaurant-menu' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Handle bulk action submit.
	 *
	 * For now this only reports what WOULD be updated (preview mode).
	 */
	private static function handle_bulk_action(): void {
		if ( empty( $_POST['jprm_bulk_apply'] ) ) {
			return;
		}

		if (
			! isset( $_POST['jprm_bulk_price_labels_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['jprm_bulk_price_labels_nonce'] ) ),
				'jprm_bulk_price_labels'
			)
		) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_nonce_error',
				__( 'Security check failed. Please try again.', 'jellopoint-restaurant-menu' ),
				'error'
			 );
			settings_errors( 'jprm_bulk_price_labels' );
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_cap_error',
				__( 'You are not allowed to perform this action.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			settings_errors( 'jprm_bulk_price_labels' );
			return;
		}

		$action       = isset( $_POST['jprm_bulk_action'] )
			? sanitize_text_field( wp_unslash( $_POST['jprm_bulk_action'] ) )
			: '';
		$target_label = isset( $_POST['jprm_target_label'] )
			? sanitize_text_field( wp_unslash( $_POST['jprm_target_label'] ) )
			: '';
		$rows         = isset( $_POST['jprm_rows'] ) && is_array( $_POST['jprm_rows'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['jprm_rows'] ) )
			: [];

		if ( $action === '' ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_action',
				__( 'Please choose a bulk action.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			settings_errors( 'jprm_bulk_price_labels' );
			return;
		}

		if ( empty( $rows ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_rows',
				__( 'Please select at least one price row.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			settings_errors( 'jprm_bulk_price_labels' );
			return;
		}

		if ( $action === 'set_label' && $target_label === '' ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_target',
				__( 'Please enter a target label name (for preview).', 'jellopoint-restaurant-menu' ),
				'error'
			);
			settings_errors( 'jprm_bulk_price_labels' );
			return;
		}

		$count = count( $rows );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				'[JPRM Bulk Price Labels] action=' . $action .
				' target_label="' . $target_label .
				'" rows=' . print_r( $rows, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			);
		}

		if ( $action === 'set_label' ) {
			/* translators: 1: count, 2: label */
			$message = sprintf(
				_n(
					'Preview: %1$d price row would be assigned the label "%2$s".',
					'Preview: %1$d price rows would be assigned the label "%2$s".',
					$count,
					'jellopoint-restaurant-menu'
				),
				$count,
				$target_label
			);
		} else {
			/* translators: %d: count */
			$message = sprintf(
				_n(
					'Preview: label would be cleared from %d price row.',
					'Preview: label would be cleared from %d price rows.',
					$count,
					'jellopoint-restaurant-menu'
				),
				$count
			);
		}

		add_settings_error(
			'jprm_bulk_price_labels',
			'jprm_bulk_price_labels_preview',
			$message,
			'updated'
		);

		settings_errors( 'jprm_bulk_price_labels' );
	}
}
