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
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		// Handle bulk submission (preview only – no DB writes).
		self::handle_bulk_action();

		// Collect filters from request.
		$filters = self::get_filters_from_request();

		// Fetch flat price rows according to filters.
		$rows = self::get_flat_price_rows( $filters );
		?>
		<div class="wrap jprm-bulk-price-labels-wrap">
			<h1><?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?></h1>

			<div class="jprm-intro">
				<p>
					<?php esc_html_e(
						'This tool lists each price row (including multiple prices per item) so you can select them and assign labels in bulk.',
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

			<?php self::render_filters( $filters ); ?>

			<form method="post">
				<?php wp_nonce_field( 'jprm_bulk_price_labels', 'jprm_bulk_price_labels_nonce' ); ?>

				<?php self::render_bulk_actions_top(); ?>

				<?php self::render_rows_table( $rows ); ?>

				<?php self::render_bulk_actions_bottom(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Read filter values from $_GET.
	 *
	 * Uses:
	 *  - jprm_menu    (int) Menu term ID
	 *  - jprm_section (int) Section term ID
	 *  - s            (string) Search by item title
	 */
	private static function get_filters_from_request(): array {
		$menu    = isset( $_GET['jprm_menu'] )    ? (int) $_GET['jprm_menu']    : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['jprm_section'] ) ? (int) $_GET['jprm_section'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] )
			? sanitize_text_field( wp_unslash( $_GET['s'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		return [
			'menu'    => $menu,
			'section' => $section,
			'search'  => $search,
		];
	}

	/**
	 * Render the filter bar.
	 *
	 * Section dropdown is dependent on the chosen menu:
	 *  - If a menu is selected → only sections that actually occur in items for that menu.
	 *  - If no menu is selected → all sections.
	 */
	private static function render_filters( array $filters ): void {
		$menu    = $filters['menu'];
		$section = $filters['section'];
		$search  = $filters['search'];

		// Menus for dropdown.
		$menus = get_terms( [
			'taxonomy'   => 'jprm_menu',
			'hide_empty' => false,
		] );
		if ( is_wp_error( $menus ) ) {
			$menus = [];
		}

		// Sections depend on selected menu.
		if ( $menu > 0 ) {
			// Base query: all items for this menu (any section).
			$base_args = [
				'post_type'      => 'jprm_menu_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [
					[
						'taxonomy' => 'jprm_menu',
						'field'    => 'term_id',
						'terms'    => [ $menu ],
					],
				],
			];

			$base_q = new \WP_Query( $base_args );
			$post_ids = $base_q->posts;
			wp_reset_postdata();

			if ( ! empty( $post_ids ) ) {
				$sections = wp_get_object_terms( $post_ids, 'jprm_section', [
					'fields'     => 'all',
					'hide_empty' => false,
				] );
				if ( is_wp_error( $sections ) ) {
					$sections = [];
				}
			} else {
				$sections = [];
			}
		} else {
			// No menu filter → all sections.
			$sections = get_terms( [
				'taxonomy'   => 'jprm_section',
				'hide_empty' => false,
			] );
			if ( is_wp_error( $sections ) ) {
				$sections = [];
			}
		}

		// Base URL for form (keep page/post_type).
		$action = remove_query_arg( [ 'paged' ] );
		?>
		<form method="get" action="<?php echo esc_url( $action ); ?>">
			<?php
			// Preserve existing query args for page / post_type.
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
					<label for="jprm_menu"><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label>
					<select id="jprm_menu" name="jprm_menu">
						<option value="0"><?php esc_html_e( 'All menus', 'jellopoint-restaurant-menu' ); ?></option>
						<?php
						if ( ! empty( $menus ) ) {
							foreach ( $menus as $term ) {
								printf(
									'<option value="%d"%s>%s</option>',
									(int) $term->term_id,
									selected( $menu, (int) $term->term_id, false ),
									esc_html( $term->name )
								);
							}
						}
						?>
					</select>
				</div>

				<div class="field">
					<label for="jprm_section"><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></label>
					<select id="jprm_section" name="jprm_section">
						<option value="0">
							<?php
							echo $menu > 0
								? esc_html__( 'All sections for this menu', 'jellopoint-restaurant-menu' )
								: esc_html__( 'All sections', 'jellopoint-restaurant-menu' );
							?>
						</option>
						<?php
						if ( ! empty( $sections ) ) {
							foreach ( $sections as $term ) {
								printf(
									'<option value="%d"%s>%s</option>',
									(int) $term->term_id,
									selected( $section, (int) $term->term_id, false ),
									esc_html( $term->name )
								);
							}
						}
						?>
					</select>
				</div>

				<div class="field">
					<label for="jprm_search"><?php esc_html_e( 'Search items', 'jellopoint-restaurant-menu' ); ?></label>
					<input type="search"
					       id="jprm_search"
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
		<?php
	}

	/**
	 * Flatten price rows for the current filters.
	 *
	 * Each element in the returned array:
	 * [
	 *   'row_key'     => 'postid:index',
	 *   'post_id'     => int,
	 *   'row_index'   => int,
	 *   'item_title'  => string,
	 *   'menus'       => string (comma list),
	 *   'sections'    => string (comma list),
	 *   'price'       => string (formatted),
	 *   'label_text'  => string,
	 *   'label_id'    => int|null,
	 * ]
	 *
	 * Query logic mirrors the probe snippet you tested:
	 *  - No filters      → all items
	 *  - Menu only       → items in that menu
	 *  - Section only    → items in that section
	 *  - Menu + Section  → items that have BOTH (tax_query relation AND)
	 */
	private static function get_flat_price_rows( array $filters ): array {
		$rows = [];

		// If the helper is not available, we can't show rows.
		if ( ! function_exists( 'jprm_get_pricegroup_data' ) ) {
			return $rows;
		}

		$query_args = [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $filters['search'] !== '' ) {
			$query_args['s'] = $filters['search'];
		}

		$tax_query = [];

		if ( $filters['menu'] ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_menu',
				'field'    => 'term_id',
				'terms'    => [ $filters['menu'] ],
			];
		}

		if ( $filters['section'] ) {
			$tax_query[] = [
				'taxonomy' => 'jprm_section',
				'field'    => 'term_id',
				'terms'    => [ $filters['section'] ],
			];
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_query;
		}

		$q = new \WP_Query( $query_args );

		if ( ! $q->have_posts() ) {
			return $rows;
		}

		while ( $q->have_posts() ) {
			$q->the_post();
			$pid   = get_the_ID();
			$title = get_the_title( $pid );

			// Menu and section term names for context.
			$menu_terms    = wp_get_post_terms( $pid, 'jprm_menu', [ 'fields' => 'names' ] );
			$section_terms = wp_get_post_terms( $pid, 'jprm_section', [ 'fields' => 'names' ] );

			$menus_str    = ! empty( $menu_terms ) && ! is_wp_error( $menu_terms )
				? implode( ', ', $menu_terms )
				: '';
			$sections_str = ! empty( $section_terms ) && ! is_wp_error( $section_terms )
				? implode( ', ', $section_terms )
				: '';

			// Reuse the same helper used on the frontend; we pass empty maps so we don't rely
			// on any specific label/currency structures.
			$price_rows = jprm_get_pricegroup_data( $pid, [], [] );
			if ( ! is_array( $price_rows ) || empty( $price_rows ) ) {
				continue;
			}

			$index = 0;
			foreach ( $price_rows as $pr ) {
				$formatted  = isset( $pr['formatted'] ) ? (string) $pr['formatted'] : '';
				$label_text = isset( $pr['label_text'] ) ? (string) $pr['label_text'] : '';
				$label_id   = isset( $pr['label_id'] ) ? (int) $pr['label_id'] : 0;

				$rows[] = [
					'row_key'    => $pid . ':' . $index,
					'post_id'    => $pid,
					'row_index'  => $index,
					'item_title' => $title,
					'menus'      => $menus_str,
					'sections'   => $sections_str,
					'price'      => $formatted,
					'label_text' => $label_text,
					'label_id'   => $label_id,
				];

				$index++;
			}
		}
		wp_reset_postdata();

		return $rows;
	}

	/**
	 * Render the bulk actions bar (top).
	 */
	private static function render_bulk_actions_top(): void {
		self::render_bulk_actions( 'top' );
	}

	/**
	 * Render the bulk actions bar (bottom).
	 */
	private static function render_bulk_actions_bottom(): void {
		self::render_bulk_actions( 'bottom' );
	}

	/**
	 * Bulk actions UI fragment.
	 *
	 * NOTE: At this stage, we do NOT yet load the real label registry.
	 * The "target label" field is a plain text input so we can safely
	 * see the selection + flow without changing meta structures.
	 *
	 * Later we can replace this with a dropdown fed by your real labels.
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
	 * Render rows table.
	 */
	private static function render_rows_table( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<p><em>' . esc_html__( 'No price rows found for the current filters.', 'jellopoint-restaurant-menu' ) . '</em></p>';
			return;
		}
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
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<th class="check-column">
							<input type="checkbox"
							       name="jprm_rows[]"
							       value="<?php echo esc_attr( $r['row_key'] ); ?>" />
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
									/* translators: 1: post ID */
									esc_html__( 'Item ID: %d', 'jellopoint-restaurant-menu' ),
									(int) $r['post_id']
								);
								?>
							</div>
						</td>
						<td>
							<?php echo esc_html( (string) $r['row_index'] ); ?>
						</td>
						<td>
							<?php
							echo $r['price'] !== ''
								? esc_html( $r['price'] )
								: '<span class="jprm-small-meta">' . esc_html__( '(no price)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</td>
						<td>
							<?php
							if ( $r['label_text'] !== '' ) {
								echo esc_html( $r['label_text'] );
							} elseif ( $r['label_id'] ) {
								printf(
									/* translators: 1: label ID */
									esc_html__( '(label ID %d, no text)', 'jellopoint-restaurant-menu' ),
									(int) $r['label_id']
								);
							} else {
								echo '<span class="jprm-small-meta">' . esc_html__( '(no label)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
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

		// For now, only PREVIEW what would be changed.
		$count = count( $rows );

		// Log the selection for debugging – safe, as we do not write anything.
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
