<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels Tool — flat view + bulk edit + dry run.
 *
 * - Filters: Menu + Section, with Section depending on Menu.
 * - Uses real label registry from jprm_price_labels_v2.
 * - Parses all known price meta formats (single / multi, JSON / serialized).
 * - Renders ONE table row per price:
 *      [x] | Menu | Section | Item | Price Index | Amount | Label
 * - Bulk actions:
 *      - Set label (pick existing pl-*lbl_* from registry)
 *      - Clear label
 * - Dry run checkbox (default ON): preview only, no DB writes.
 *
 * Writes back into:
 * - jprm_price (array)
 * - jprm_prices (for multi)
 * - single-mode helpers: jprm_price_mode, jprm_price_amount, jprm_price_label_ref
 */
final class JPRM_Admin_Bulk_Price_Labels {

	private const PAGE_SLUG  = 'jprm-bulk-price-labels';
	private const CAPABILITY = 'edit_posts';

	public static function bootstrap(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 30 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

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

	public static function enqueue_assets( string $hook_suffix ): void {
		$expected = Admin_Menu::PARENT_SLUG . '_page_' . self::PAGE_SLUG;
		if ( $hook_suffix !== $expected ) {
			return;
		}

		$handle = 'jprm-bulk-price-labels';

		$css = '
			.jprm-bulk-price-labels-wrap .jprm-intro {
				max-width: 900px;
				margin-bottom: 1.5em;
			}
			.jprm-bulk-price-labels-wrap .jprm-intro p {
				margin: 0 0 0.5em;
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
			.jprm-bulk-price-labels-wrap table.widefat th,
			.jprm-bulk-price-labels-wrap table.widefat td {
				vertical-align: top;
			}
			.jprm-bulk-price-labels-wrap .jprm-small-meta {
				font-size: 11px;
				color: #666;
				margin-top: 2px;
			}
			.jprm-bulk-price-labels-wrap .column-select {
				width: 40px;
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
				max-width: 260px;
			}
			.jprm-bulk-price-labels-wrap .jprm-bulk-actions .jprm-dryrun-toggle {
				margin-left: auto;
				display: flex;
				align-items: center;
				gap: 0.35em;
				font-size: 12px;
				color: #444;
			}
		';

		wp_register_style( $handle, false, [], '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );

		// Small JS: auto-submit on Menu / Section change so you don't need the button.
		$js = '
			(function(){
				document.addEventListener("DOMContentLoaded", function(){
					var form = document.querySelector(".jprm-bulk-price-labels-wrap form.jprm-filter-form");
					if (!form) return;

					var menuSel = form.querySelector("select[name=\'filter_menu\']");
					var sectSel = form.querySelector("select[name=\'filter_section\']");

					if (menuSel) {
						menuSel.addEventListener("change", function(){
							if (sectSel) { sectSel.value = "0"; }
							form.submit();
						});
					}

					if (sectSel) {
						sectSel.addEventListener("change", function(){
							form.submit();
						});
					}
				});
			}());
		';
		wp_add_inline_script( 'jquery-core', $js );
	}

	/**
	 * Main page renderer: filters, bulk handler, flat table.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		$current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Label registry (id => row).
		$labels_index = self::load_price_labels_index();

		// Handle bulk POST (writes to meta, adds admin notices).
		self::handle_bulk_action( $labels_index );

		// Menus for dropdown.
		$menus = get_terms( [
			'taxonomy'   => 'jprm_menu',
			'hide_empty' => false,
		] );
		if ( is_wp_error( $menus ) ) {
			$menus = [];
		}

		// Sections pool depending on selected menu.
		$sections_pool = self::load_sections_for_menu( $current_menu );

		// Items query (menu + section).
		$items_query = self::build_items_query( $current_menu, $current_section );

		// Flatten all price rows across items.
		$flat_rows = [];

		if ( $items_query->have_posts() ) {
			while ( $items_query->have_posts() ) {
				$items_query->the_post();

				$post_id = get_the_ID();
				$title   = get_the_title( $post_id );

				$menu_terms    = wp_get_object_terms( $post_id, 'jprm_menu', [ 'fields' => 'names' ] );
				$section_terms = wp_get_object_terms( $post_id, 'jprm_section', [ 'fields' => 'names' ] );

				$menus_str    = ( ! empty( $menu_terms ) && ! is_wp_error( $menu_terms ) )
					? implode( ', ', $menu_terms )
					: '';
				$sections_str = ( ! empty( $section_terms ) && ! is_wp_error( $section_terms ) )
					? implode( ', ', $section_terms )
					: '';

				$price_rows = self::parse_price_rows_for_post( $post_id, $labels_index );

				if ( empty( $price_rows ) ) {
					continue;
				}

				foreach ( $price_rows as $idx => $row ) {
					$flat_rows[] = [
						'post_id'      => $post_id,
						'item_title'   => $title,
						'menus'        => $menus_str,
						'sections'     => $sections_str,
						'price_index'  => $idx,
						'amount'       => $row['amount'],
						'label_ref'    => $row['label_ref'],
						'label_text'   => $row['label_text'],
					];
				}
			}
			wp_reset_postdata();
		}

		?>
		<div class="wrap jprm-bulk-price-labels-wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?>
			</h1>
			<hr class="wp-header-end" />

			<?php settings_errors( 'jprm_bulk_price_labels' ); ?>

			<div class="jprm-intro">
				<p>
					<?php esc_html_e(
						'This tool flattens all prices so each price row appears separately. Use the checkboxes + bulk actions to set or clear labels.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
				<p>
					<?php esc_html_e(
						'Filters are live: changing the menu or section immediately refreshes the list. Multiple prices on one item appear as separate rows with a price index.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
			</div>

			<!-- FILTER BAR (GET) -->
			<form method="get" class="jprm-filter-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />

				<div class="jprm-filters">

					<div class="field">
						<label for="jprm-filter-menu"><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label>
						<select name="filter_menu" id="jprm-filter-menu">
							<option value="0"><?php esc_html_e( 'All Menus', 'jellopoint-restaurant-menu' ); ?></option>
							<?php foreach ( $menus as $m ) : ?>
								<option value="<?php echo (int) $m->term_id; ?>" <?php selected( $current_menu, (int) $m->term_id ); ?>>
									<?php echo esc_html( $m->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<label for="jprm-filter-section"><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></label>
						<select name="filter_section" id="jprm-filter-section">
							<option value="0">
								<?php
								echo $current_menu
									? esc_html__( 'All Sections for this Menu', 'jellopoint-restaurant-menu' )
									: esc_html__( 'All Sections', 'jellopoint-restaurant-menu' );
								?>
							</option>
							<?php foreach ( $sections_pool as $sect ) : ?>
								<option value="<?php echo (int) $sect->term_id; ?>" <?php selected( $current_section, (int) $sect->term_id ); ?>>
									<?php echo esc_html( $sect->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<button class="button button-primary">
						<?php esc_html_e( 'Filter', 'jellopoint-restaurant-menu' ); ?>
					</button>
				</div>
			</form>

			<!-- BULK EDIT FORM (POST) -->
			<form method="post">
				<?php wp_nonce_field( 'jprm_bulk_labels', 'jprm_bulk_labels_nonce' ); ?>

				<!-- preserve filters on POST -->
				<input type="hidden" name="filter_menu" value="<?php echo (int) $current_menu; ?>" />
				<input type="hidden" name="filter_section" value="<?php echo (int) $current_section; ?>" />

				<?php self::render_bulk_actions_bar( 'top', $labels_index ); ?>

				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th class="column-select check-column">
								<input type="checkbox" id="jprm-select-all" />
							</th>
							<th><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Item', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Price Index', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'jellopoint-restaurant-menu' ); ?></th>
							<th><?php esc_html_e( 'Label', 'jellopoint-restaurant-menu' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $flat_rows ) ) : ?>
						<tr>
							<td colspan="7">
								<?php esc_html_e( 'No price rows match the current filters.', 'jellopoint-restaurant-menu' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $flat_rows as $r ) : ?>
							<tr>
								<th class="check-column">
									<input type="checkbox"
										name="jprm_rows[]"
										value="<?php echo esc_attr( $r['post_id'] . ':' . $r['price_index'] ); ?>" />
								</th>
								<td>
									<?php
									echo $r['menus'] !== ''
										? esc_html( $r['menus'] )
										: '<span class="jprm-small-meta">' . esc_html__( '(no menu)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</td>
								<td>
									<?php
									echo $r['sections'] !== ''
										? esc_html( $r['sections'] )
										: '<span class="jprm-small-meta">' . esc_html__( '(no section)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</td>
								<td>
									<strong>
										<a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>">
											<?php echo esc_html( $r['item_title'] ); ?>
										</a>
									</strong>
									<div class="jprm-small-meta">
										<?php
										printf(
											/* translators: %d: post ID */
											esc_html__( 'Item ID: %d', 'jellopoint-restaurant-menu' ),
											(int) $r['post_id']
										);
										?>
									</div>
								</td>
								<td><?php echo esc_html( (string) $r['price_index'] ); ?></td>
								<td><?php echo esc_html( (string) $r['amount'] ); ?></td>
								<td>
									<?php
									if ( $r['label_text'] !== '' ) {
										echo esc_html( $r['label_text'] );
										if ( $r['label_ref'] !== '' ) {
											echo ' ';
											echo '<span class="jprm-small-meta">(' . esc_html( $r['label_ref'] ) . ')</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
									} elseif ( $r['label_ref'] !== '' ) {
										echo '<span class="jprm-small-meta">' . esc_html( $r['label_ref'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									} else {
										echo '<span class="jprm-small-meta">' . esc_html__( '(no label)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>

				<?php self::render_bulk_actions_bar( 'bottom', $labels_index ); ?>

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
			</form>
		</div>
		<?php
	}

	/**
	 * Bulk actions bar (top + bottom).
	 * Top and bottom use different field names, like WP list tables (action/action2).
	 */
	private static function render_bulk_actions_bar( string $position, array $labels_index ): void {
		$is_top          = ( 'top' === $position );
		$action_name     = $is_top ? 'jprm_bulk_action' : 'jprm_bulk_action2';
		$target_label_name = $is_top ? 'jprm_target_label_ref' : 'jprm_target_label_ref2';
		?>
		<div class="jprm-bulk-actions jprm-bulk-actions-<?php echo esc_attr( $position ); ?>">
			<strong><?php esc_html_e( 'Bulk action:', 'jellopoint-restaurant-menu' ); ?></strong>

			<select name="<?php echo esc_attr( $action_name ); ?>">
				<option value=""><?php esc_html_e( '— Select —', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="set_label"><?php esc_html_e( 'Set label (ref)', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="clear_label"><?php esc_html_e( 'Clear label', 'jellopoint-restaurant-menu' ); ?></option>
			</select>

			<span>
				<?php esc_html_e( 'Target label:', 'jellopoint-restaurant-menu' ); ?>
				<select name="<?php echo esc_attr( $target_label_name ); ?>">
					<option value=""><?php esc_html_e( '— Select label —', 'jellopoint-restaurant-menu' ); ?></option>
					<?php foreach ( $labels_index as $id => $data ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>">
							<?php
							$label = isset( $data['label'] ) ? (string) $data['label'] : $id;
							echo esc_html( $label . ' (' . $id . ')' );
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</span>

			<?php if ( $is_top ) : ?>
				<span class="jprm-dryrun-toggle">
					<label>
						<input type="checkbox" name="jprm_dry_run" value="1" checked="checked" />
						<?php esc_html_e( 'Dry run (preview only, no changes saved)', 'jellopoint-restaurant-menu' ); ?>
					</label>
				</span>
			<?php endif; ?>

			<button type="submit" class="button button-primary" name="jprm_bulk_apply" value="1">
				<?php esc_html_e( 'Apply', 'jellopoint-restaurant-menu' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Handle bulk action POST and write back to meta (or preview only).
	 */
	private static function handle_bulk_action( array $labels_index ): void {
		if ( empty( $_POST['jprm_bulk_apply'] ) ) {
			return;
		}

		if ( ! isset( $_POST['jprm_bulk_labels_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['jprm_bulk_labels_nonce'] ) ),
				'jprm_bulk_labels'
			)
		) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_nonce_error',
				__( 'Security check failed. Please try again.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_cap_error',
				__( 'You are not allowed to perform this action.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		// Like WP: action (top) and action2 (bottom).
		$action_primary   = isset( $_POST['jprm_bulk_action'] )
			? sanitize_text_field( wp_unslash( $_POST['jprm_bulk_action'] ) )
			: '';
		$action_secondary = isset( $_POST['jprm_bulk_action2'] )
			? sanitize_text_field( wp_unslash( $_POST['jprm_bulk_action2'] ) )
			: '';

		$use_secondary = false;
		if ( '' === $action_primary && '' !== $action_secondary ) {
			$action        = $action_secondary;
			$use_secondary = true;
		} else {
			$action = $action_primary;
		}

		$rows = isset( $_POST['jprm_rows'] ) && is_array( $_POST['jprm_rows'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['jprm_rows'] ) )
			: [];

		if ( '' === $action ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_action',
				__( 'Please choose a bulk action.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		if ( empty( $rows ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_rows',
				__( 'Please select at least one price row.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		$dry_run = ! empty( $_POST['jprm_dry_run'] );

		$target_label_ref = null;

		if ( 'set_label' === $action ) {
			$raw_target_primary   = isset( $_POST['jprm_target_label_ref'] )
				? sanitize_text_field( wp_unslash( $_POST['jprm_target_label_ref'] ) )
				: '';
			$raw_target_secondary = isset( $_POST['jprm_target_label_ref2'] )
				? sanitize_text_field( wp_unslash( $_POST['jprm_target_label_ref2'] ) )
				: '';

			if ( $use_secondary ) {
				$raw_target = $raw_target_secondary;
			} else {
				$raw_target = $raw_target_primary;
			}

			if ( '' === $raw_target ) {
				add_settings_error(
					'jprm_bulk_price_labels',
					'jprm_bulk_price_labels_no_target',
					__( 'Please select a target label.', 'jellopoint-restaurant-menu' ),
					'error'
				);
				return;
			}

			if ( ! isset( $labels_index[ $raw_target ] ) ) {
				add_settings_error(
					'jprm_bulk_price_labels',
					'jprm_bulk_price_labels_invalid_target',
					__( 'The selected label no longer exists.', 'jellopoint-restaurant-menu' ),
					'error'
				);
				return;
			}

			$target_label_ref = $raw_target;
		}

		// Group selected rows by post_id => [indices...].
		$targets = [];
		foreach ( $rows as $key ) {
			$parts = explode( ':', $key, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$post_id = (int) $parts[0];
			$idx     = (int) $parts[1];

			if ( $post_id <= 0 || $idx < 0 ) {
				continue;
			}

			if ( ! isset( $targets[ $post_id ] ) ) {
				$targets[ $post_id ] = [];
			}
			$targets[ $post_id ][] = $idx;
		}

		if ( empty( $targets ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_valid_rows',
				__( 'No valid price rows were selected.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		$total_changed = 0;

		foreach ( $targets as $post_id => $indices ) {
			$indices = array_values( array_unique( array_map( 'intval', $indices ) ) );
			$total_changed += self::apply_bulk_to_post(
				$post_id,
				$indices,
				$action,
				$target_label_ref,
				$labels_index,
				$dry_run
			);
		}

		if ( $total_changed <= 0 ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_changes',
				__( 'No price rows were changed (meta might be missing or incompatible).', 'jellopoint-restaurant-menu' ),
				'error'
			);
		} else {
			if ( $dry_run ) {
				/* translators: %d: number of price rows that would change */
				$message = sprintf(
					_n(
						'Preview: %d price row would be updated (no changes saved).',
						'Preview: %d price rows would be updated (no changes saved).',
						$total_changed,
						'jellopoint-restaurant-menu'
					),
					$total_changed
				);
			} else {
				/* translators: %d: number of price rows changed */
				$message = sprintf(
					_n(
						'Updated labels on %d price row.',
						'Updated labels on %d price rows.',
						$total_changed,
						'jellopoint-restaurant-menu'
					),
					$total_changed
				);
			}

			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_success',
				$message,
				'updated'
			);
		}
	}

	/**
	 * Apply bulk action to one post (one or more indices).
	 *
	 * If $dry_run is true, only counts changes; DB is untouched.
	 *
	 * Returns the number of rows that were actually changed.
	 */
	private static function apply_bulk_to_post(
		int $post_id,
		array $indices,
		string $action,
		?string $target_label_ref,
		array $labels_index,
		bool $dry_run
	): int {
		$changed = 0;

		// Build canonical struct from existing meta.
		$price_meta = get_post_meta( $post_id, 'jprm_price', true );
		$struct     = null;

		if ( is_array( $price_meta ) ) {
			$struct = $price_meta;
		} elseif ( is_string( $price_meta ) && $price_meta !== '' ) {
			$maybe = maybe_unserialize( $price_meta );
			if ( is_array( $maybe ) ) {
				$struct = $maybe;
			} else {
				$json = json_decode( $price_meta, true );
				if ( is_array( $json ) ) {
					$struct = $json;
				}
			}
		}

		// Fallback: build from jprm_prices (multi rows).
		if ( ! is_array( $struct ) ) {
			$prices_meta = get_post_meta( $post_id, 'jprm_prices', true );
			$prices_arr  = null;

			if ( is_array( $prices_meta ) ) {
				$prices_arr = $prices_meta;
			} elseif ( is_string( $prices_meta ) && $prices_meta !== '' ) {
				$maybe = maybe_unserialize( $prices_meta );
				if ( is_array( $maybe ) ) {
					$prices_arr = $maybe;
				} else {
					$json = json_decode( $prices_meta, true );
					if ( is_array( $json ) ) {
						$prices_arr = $json;
					}
				}
			}

			if ( is_array( $prices_arr ) ) {
				$struct = [
					'mode' => 'multi',
					'rows' => [],
				];

				foreach ( $prices_arr as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$amount = (string) ( $row['amount'] ?? $row['value'] ?? '' );
					$amount = trim( $amount );
					if ( '' === $amount ) {
						continue;
					}

					$struct['rows'][] = [
						'label_ref' => isset( $row['label_ref'] ) ? (string) $row['label_ref'] : '',
						'value'     => $amount,
						'hide_icon' => ! empty( $row['hide_icon'] ),
					];
				}
			}
		}

		// Final fallback: legacy single mode only.
		if ( ! is_array( $struct ) ) {
			$mode   = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
			$amount = (string) get_post_meta( $post_id, 'jprm_price_amount', true );

			if ( 'single' === $mode && '' !== trim( $amount ) ) {
				$struct = [
					'mode'      => 'single',
					'price'     => $amount,
					'label_ref' => (string) get_post_meta( $post_id, 'jprm_price_label_ref', true ),
					'hide_icon' => false,
				];
			} else {
				return 0; // nothing we can safely touch.
			}
		}

		$mode = isset( $struct['mode'] ) ? (string) $struct['mode'] : '';

		if ( 'single' === $mode ) {
			// Only index 0 is valid for single mode.
			if ( ! in_array( 0, $indices, true ) ) {
				return 0;
			}

			if ( 'set_label' === $action && null !== $target_label_ref ) {
				$struct['label_ref'] = $target_label_ref;
			} elseif ( 'clear_label' === $action ) {
				$struct['label_ref'] = '';
			}

			$changed = 1;

			if ( ! $dry_run ) {
				// Persist struct as array (WordPress will serialize).
				update_post_meta( $post_id, 'jprm_price', $struct );

				// Sync helper meta.
				$amount_str = (string) ( $struct['price'] ?? '' );
				update_post_meta( $post_id, 'jprm_price_mode', 'single' );
				update_post_meta( $post_id, 'jprm_price_amount', $amount_str );

				if ( 'set_label' === $action && null !== $target_label_ref ) {
					update_post_meta( $post_id, 'jprm_price_label_mode', 'ref' );
					update_post_meta( $post_id, 'jprm_price_label_ref', $target_label_ref );
				} else {
					delete_post_meta( $post_id, 'jprm_price_label_ref' );
				}
			}

			return $changed;
		}

		if ( 'multi' === $mode ) {
			if ( empty( $struct['rows'] ) || ! is_array( $struct['rows'] ) ) {
				return 0;
			}

			foreach ( $indices as $idx ) {
				if ( ! isset( $struct['rows'][ $idx ] ) ) {
					continue;
				}

				if ( 'set_label' === $action && null !== $target_label_ref ) {
					$struct['rows'][ $idx ]['label_ref'] = $target_label_ref;
					if ( isset( $struct['rows'][ $idx ]['label_mode'] ) ) {
						$struct['rows'][ $idx ]['label_mode'] = 'ref';
					}
				} elseif ( 'clear_label' === $action ) {
					$struct['rows'][ $idx ]['label_ref'] = '';
				}

				$changed++;
			}

			if ( $changed <= 0 ) {
				return 0;
			}

			if ( ! $dry_run ) {
				update_post_meta( $post_id, 'jprm_price', $struct );

				// Rebuild jprm_prices from rows.
				$out_prices = [];
				foreach ( $struct['rows'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$amount = (string) ( $row['value'] ?? $row['amount'] ?? '' );
					$amount = trim( $amount );
					if ( '' === $amount ) {
						continue;
					}
					$out_prices[] = [
						'amount'    => $amount,
						'label_ref' => isset( $row['label_ref'] ) ? (string) $row['label_ref'] : '',
					];
				}

				update_post_meta( $post_id, 'jprm_price_mode', 'multi' );
				update_post_meta( $post_id, 'jprm_prices', $out_prices );
				delete_post_meta( $post_id, 'jprm_price_amount' );
				delete_post_meta( $post_id, 'jprm_price_label_ref' );
			}

			return $changed;
		}

		return 0;
	}

	/**
	 * Return sections that are relevant for a given menu.
	 *
	 * - If $menu_id > 0: only sections actually used by items in that menu.
	 * - If $menu_id = 0: all sections.
	 *
	 * @return \WP_Term[]
	 */
	private static function load_sections_for_menu( int $menu_id ): array {
		if ( $menu_id <= 0 ) {
			$sections = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
			return is_wp_error( $sections ) ? [] : $sections;
		}

		$query = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => 'jprm_menu',
					'field'    => 'term_id',
					'terms'    => [ $menu_id ],
				],
			],
		] );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return [];
		}

		$post_ids = $query->posts;
		wp_reset_postdata();

		$sections = wp_get_object_terms( $post_ids, 'jprm_section', [
			'fields'     => 'all',
			'hide_empty' => false,
		] );

		return is_wp_error( $sections ) ? [] : $sections;
	}

	/**
	 * Build the main items query based on menu + section filters.
	 */
	private static function build_items_query( int $menu_id, int $section_id ): \WP_Query {
		$tax_query = [];

		if ( $menu_id > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'term_id',
				'terms'    => [ $menu_id ],
			];
		}

		if ( $section_id > 0 ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'term_id',
				'terms'    => [ $section_id ],
			];
		}

		$args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		return new \WP_Query( $args );
	}

	/**
	 * Load label registry and index by label ID (pl-1, pl-2, lbl_..., etc.)
	 *
	 * @return array<string,array> id => label data
	 */
	private static function load_price_labels_index(): array {
		$opt = get_option( 'jprm_price_labels_v2' );
		if ( ! is_array( $opt ) ) {
			return [];
		}

		$index = [];

		foreach ( $opt as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$id = (string) $row['id'];
			$index[ $id ] = $row;
		}

		return $index;
	}

	/**
	 * Parse all price rows for a given item into a uniform array.
	 *
	 * Result: each element is:
	 * [
	 *   'amount'     => string,
	 *   'label_ref'  => string,
	 *   'label_text' => string,
	 * ]
	 */
	private static function parse_price_rows_for_post( int $post_id, array $labels_index ): array {
		$rows = [];

		// 1) jprm_price (primary, may be JSON or serialized).
		$price_meta = get_post_meta( $post_id, 'jprm_price', true );
		$struct     = null;

		if ( is_array( $price_meta ) ) {
			$struct = $price_meta;
		} elseif ( is_string( $price_meta ) && $price_meta !== '' ) {
			$maybe = maybe_unserialize( $price_meta );
			if ( is_array( $maybe ) ) {
				$struct = $maybe;
			} else {
				$json = json_decode( $price_meta, true );
				if ( is_array( $json ) ) {
					$struct = $json;
				}
			}
		}

		if ( is_array( $struct ) ) {
			$mode = isset( $struct['mode'] ) ? (string) $struct['mode'] : '';

			if ( 'single' === $mode ) {
				$amount = (string) ( $struct['price'] ?? $struct['value'] ?? '' );
				if ( '' === trim( $amount ) ) {
					$amount = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
				}

				$label_ref = isset( $struct['label_ref'] ) ? (string) $struct['label_ref'] : '';
				if ( '' === $label_ref ) {
					$label_ref = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );
				}

				self::add_price_row( $rows, $amount, $label_ref, $labels_index, $struct );
			} elseif ( 'multi' === $mode && ! empty( $struct['rows'] ) && is_array( $struct['rows'] ) ) {
				foreach ( $struct['rows'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$amount    = (string) ( $row['value'] ?? $row['amount'] ?? '' );
					$label_ref = isset( $row['label_ref'] ) ? (string) $row['label_ref'] : '';
					self::add_price_row( $rows, $amount, $label_ref, $labels_index, $row );
				}
			}
		}

		// 2) If no result yet, try jprm_prices (array of rows).
		if ( empty( $rows ) ) {
			$prices_meta = get_post_meta( $post_id, 'jprm_prices', true );
			$prices_arr  = null;

			if ( is_array( $prices_meta ) ) {
				$prices_arr = $prices_meta;
			} elseif ( is_string( $prices_meta ) && $prices_meta !== '' ) {
				$maybe = maybe_unserialize( $prices_meta );
				if ( is_array( $maybe ) ) {
					$prices_arr = $maybe;
				} else {
					$json = json_decode( $prices_meta, true );
					if ( is_array( $json ) ) {
						$prices_arr = $json;
					}
				}
			}

			if ( is_array( $prices_arr ) ) {
				foreach ( $prices_arr as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$amount    = (string) ( $row['amount'] ?? $row['value'] ?? '' );
					$label_ref = isset( $row['label_ref'] ) ? (string) $row['label_ref'] : '';
					self::add_price_row( $rows, $amount, $label_ref, $labels_index, $row );
				}
			}
		}

		// 3) Legacy single mode fields as last fallback.
		if ( empty( $rows ) ) {
			$mode = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
			if ( 'single' === $mode ) {
				$amount    = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
				$label_ref = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );
				self::add_price_row( $rows, $amount, $label_ref, $labels_index, [] );
			}
		}

		// Remove completely empty rows, just in case.
		$rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return isset( $row['amount'] ) && '' !== trim( (string) $row['amount'] );
				}
			)
		);

		return $rows;
	}

	/**
	 * Helper: normalize and push a single price row into $rows.
	 *
	 * @param array  $rows         Reference to output rows.
	 * @param string $amount       Price string.
	 * @param string $label_ref    Reference ID (pl-1, lbl_...).
	 * @param array  $labels_index id => label data.
	 * @param array  $raw_row      full raw row (may contain label_custom).
	 */
	private static function add_price_row(
		array &$rows,
		string $amount,
		string $label_ref,
		array $labels_index,
		array $raw_row
	): void {
		$amount = trim( $amount );
		if ( '' === $amount ) {
			return;
		}

		$label_text = '';

		// If we have a ref and it exists in registry, prefer that.
		if ( '' !== $label_ref && isset( $labels_index[ $label_ref ] ) ) {
			$label_text = isset( $labels_index[ $label_ref ]['label'] )
				? (string) $labels_index[ $label_ref ]['label']
				: '';
		}

		// Fallback: custom label present in row.
		if ( '' === $label_text && ! empty( $raw_row['label_custom'] ) ) {
			$label_text = (string) $raw_row['label_custom'];
		}

		$rows[] = [
			'amount'     => $amount,
			'label_ref'  => $label_ref,
			'label_text' => $label_text,
		];
	}
}
