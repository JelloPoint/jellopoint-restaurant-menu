<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels Tool.
 *
 * - Filters by Menu + Section (matches probe: 73 / 44 / 6 / 6).
 * - Section dropdown depends on chosen Menu.
 * - Lists EACH price row as its own line (flattened).
 * - Uses real label registry from option("jprm_price_labels_v2").
 * - Updates BOTH jprm_price + jprm_prices meta.
 * - Dual mode:
 *     - Preview (no writes)
 *     - Apply & Save (writes changes)
 */
final class JPRM_Admin_Bulk_Price_Labels {

	private const PAGE_SLUG  = 'jprm-bulk-price-labels';
	private const CAPABILITY = 'edit_posts';

	/** Cached labels registry: id => label array. */
	private static $labels_cache = null;

	/** Cached label text: id => "Label (id)". */
	private static $label_text_cache = null;

	/**
	 * Bootstrap hooks.
	 */
	public static function bootstrap(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 30 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Add submenu under JelloPoint admin menu.
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
	 * Minimal CSS and small JS (auto-submit on Menu change).
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		$expected = Admin_Menu::PARENT_SLUG . '_page_' . self::PAGE_SLUG;
		if ( $hook_suffix !== $expected ) {
			return;
		}

		$handle = 'jprm-bulk-price-labels';
		$css    = '
			.jprm-bulk-price-labels-wrap .jprm-intro {
				max-width: 900px;
				margin-bottom: 1.5em;
			}
			.jprm-bulk-price-labels-wrap .jprm-intro p {
				margin: 0 0 0.75em;
			}
			.jprm-bulk-price-labels-wrap .jprm-filters {
				margin: 1em 0;
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
			.jprm-bulk-price-labels-wrap table.widefat td,
			.jprm-bulk-price-labels-wrap table.widefat th {
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
				max-width: 260px;
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

		// Auto-submit filter form when Menu changes (so Sections refresh immediately).
		$js = '
			(function(){
				document.addEventListener("DOMContentLoaded", function(){
					var form = document.querySelector(".jprm-bulk-price-labels-wrap form.jprm-filter-form");
					if (!form) return;
					var menuSel = form.querySelector("select[name=\'filter_menu\']");
					if (!menuSel) return;
					menuSel.addEventListener("change", function(){
						form.submit();
					});
				});
			}());
		';
		wp_register_script( $handle . '-js', false, [], '1.0.0', true );
		wp_enqueue_script( $handle . '-js' );
		wp_add_inline_script( $handle . '-js', $js );
	}

	/**
	 * Main page render.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jellopoint-restaurant-menu' ) );
		}

		// Read current filters from GET.
		$current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0; // phpcs:ignore
		$current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0; // phpcs:ignore

		// Handle bulk actions (preview + apply). This also prints notices.
		self::handle_bulk_action();

		// Menus.
		$menus = get_terms( [
			'taxonomy'   => 'jprm_menu',
			'hide_empty' => false,
		] );
		if ( is_wp_error( $menus ) ) {
			$menus = [];
		}

		// Sections pool, depending on Menu (matches earlier working probe).
		$section_pool = self::get_sections_for_menu( $current_menu );

		// Flat price rows for current filters.
		$flat_rows = self::get_flat_price_rows( $current_menu, $current_section );

		// Label registry for dropdown.
		$labels = self::get_labels_registry();
		?>
		<div class="wrap jprm-bulk-price-labels-wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?>
			</h1>
			<hr class="wp-header-end" />

			<div class="jprm-intro">
				<p><?php esc_html_e(
					'This tool lists each price row (including multiple prices per item) so you can assign or clear labels in bulk.',
					'jellopoint-restaurant-menu'
				); ?></p>
				<p><?php esc_html_e(
					'Use the filters to narrow down menus and sections. Then select the price rows you want to update, choose a label and either preview or apply the changes.',
					'jellopoint-restaurant-menu'
				); ?></p>
			</div>

			<?php settings_errors( 'jprm_bulk_price_labels' ); ?>

			<!-- FILTERS (GET) -->
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
							<?php foreach ( $section_pool as $s ) : ?>
								<option value="<?php echo (int) $s->term_id; ?>" <?php selected( $current_section, (int) $s->term_id ); ?>>
									<?php echo esc_html( $s->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Filter', 'jellopoint-restaurant-menu' ); ?>
						</button>
					</div>
				</div>
			</form>

			<!-- FLAT ROWS + BULK FORM (POST) -->
			<form method="post">
				<?php wp_nonce_field( 'jprm_bulk_price_labels', 'jprm_bulk_price_labels_nonce' ); ?>

				<?php self::render_bulk_actions_bar( 'top', $labels ); ?>

				<?php self::render_flat_rows_table( $flat_rows ); ?>

				<?php self::render_bulk_actions_bar( 'bottom', $labels ); ?>
			</form>
		</div>
		<?php
	}

	/* ======================================================================
	 * LABEL REGISTRY HELPERS
	 * ====================================================================== */

	/**
	 * Load labels registry from option("jprm_price_labels_v2").
	 *
	 * Structure from your debug:
	 * [
	 *   [ 'id' => 'pl-1', 'label' => 'Big Glass', 'icon_id' => 575, 'active' => 1, ... ],
	 *   ...
	 * ]
	 *
	 * Returns: [ 'pl-1' => [..], 'pl-0' => [..], ... ] (active first, ordered).
	 */
	private static function get_labels_registry(): array {
		if ( self::$labels_cache !== null ) {
			return self::$labels_cache;
		}

		$raw = get_option( 'jprm_price_labels_v2', [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		// Separate active / inactive, preserve "order".
		$active   = [];
		$inactive = [];

		foreach ( $raw as $entry ) {
			$id    = isset( $entry['id'] )    ? (string) $entry['id']    : '';
			$label = isset( $entry['label'] ) ? (string) $entry['label'] : '';
			$act   = isset( $entry['active'] ) ? (int) $entry['active'] : 1;

			if ( $id === '' ) {
				continue;
			}

			if ( $act ) {
				$active[ $id ] = $entry;
			} else {
				$inactive[ $id ] = $entry;
			}
		}

		// Merge: active first, then inactive (to keep everything available).
		self::$labels_cache = $active + $inactive;

		// Build text cache as well.
		self::$label_text_cache = [];
		foreach ( self::$labels_cache as $id => $entry ) {
			$lbl = isset( $entry['label'] ) ? (string) $entry['label'] : '';
			self::$label_text_cache[ $id ] = $lbl !== '' ? $lbl : $id;
		}

		return self::$labels_cache;
	}

	/**
	 * Get human readable label text for a label_ref (id).
	 */
	private static function get_label_text( string $id ): string {
		if ( self::$label_text_cache === null ) {
			self::get_labels_registry();
		}
		if ( isset( self::$label_text_cache[ $id ] ) ) {
			return self::$label_text_cache[ $id ];
		}
		return $id;
	}

	/* ======================================================================
	 * FILTER HELPERS
	 * ====================================================================== */

	/**
	 * Discover sections for a given menu (matches your probe logic).
	 *
	 * - If $menu_id > 0 → sections actually used by items in that menu.
	 * - Else → all sections.
	 */
	private static function get_sections_for_menu( int $menu_id ): array {
		if ( $menu_id > 0 ) {
			$menu_items = new \WP_Query( [
				'post_type'      => 'jprm_menu_item',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => [
					[
						'taxonomy' => 'jprm_menu',
						'field'    => 'term_id',
						'terms'    => [ $menu_id ],
					],
				],
				'fields' => 'ids',
			] );

			if ( $menu_items->have_posts() ) {
				$ids  = $menu_items->posts;
				$pool = wp_get_object_terms( $ids, 'jprm_section', [
					'fields'     => 'all',
					'hide_empty' => false,
				] );
				wp_reset_postdata();

				if ( ! is_wp_error( $pool ) ) {
					return $pool;
				}
			}
			wp_reset_postdata();

			return [];
		}

		// No menu filter → all sections.
		$sections = get_terms( [
			'taxonomy'   => 'jprm_section',
			'hide_empty' => false,
		] );

		if ( is_wp_error( $sections ) ) {
			return [];
		}
		return $sections;
	}

	/* ======================================================================
	 * PRICE ROWS (READ)
	 * ====================================================================== */

	/**
	 * Build main WP_Query args for items list (matches your 73 / 44 / 6 / 6 behaviour).
	 */
	private static function get_items_query_args( int $menu_id, int $section_id ): array {
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
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * Read price meta and normalize into an array of rows:
	 * [
	 *   ['value' => '6.25', 'label_ref' => 'pl-1'],
	 *   ...
	 * ]
	 *
	 * Supports:
	 * - jprm_price: { "mode":"multi","rows":[{"value":"..","label_ref":"pl-1"},...] }
	 * - jprm_price: { "mode":"single","value":".." , "label_ref":".." }
	 * - Fallback to jprm_prices: [{"amount":"..","label_mode":"ref","label_ref":"pl-1"},...]
	 */
	private static function get_price_rows_for_post( int $post_id ): array {
		$rows = [];

		// --- 1) Try jprm_price (new structure) -------------------------
		$raw = get_post_meta( $post_id, 'jprm_price', true );
		if ( is_string( $raw ) && $raw !== '' ) {
			$data = json_decode( $raw, true );

			if ( is_array( $data ) ) {
				// Multi mode: rows[]
				if ( isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
					foreach ( $data['rows'] as $row ) {
						$value = '';
						if ( isset( $row['value'] ) ) {
							$value = (string) $row['value'];
						} elseif ( isset( $row['amount'] ) ) {
							$value = (string) $row['amount'];
						}

						$label_ref = isset( $row['label_ref'] ) ? (string) $row['label_ref'] : '';

						// We keep rows even if value or label_ref is empty; this is a bulk tool.
						$rows[] = [
							'value'     => $value,
							'label_ref' => $label_ref,
						];
					}
				}
				// Single mode: value at root, optional label_ref at root.
				elseif ( isset( $data['value'] ) || isset( $data['amount'] ) ) {
					$value = isset( $data['value'] ) ? (string) $data['value'] : (string) ( $data['amount'] ?? '' );
					$label_ref = isset( $data['label_ref'] ) ? (string) $data['label_ref'] : '';

					if ( $value !== '' ) {
						$rows[] = [
							'value'     => $value,
							'label_ref' => $label_ref,
						];
					}
				}
			}
		}

		// If we have anything, stop here.
		if ( ! empty( $rows ) ) {
			return $rows;
		}

		// --- 2) Fallback: jprm_prices (editor meta) --------------------
		$raw2 = get_post_meta( $post_id, 'jprm_prices', true );
		if ( is_string( $raw2 ) && $raw2 !== '' ) {
			$data2 = json_decode( $raw2, true );
			if ( is_array( $data2 ) ) {
				foreach ( $data2 as $row ) {
					$value = '';
					if ( isset( $row['amount'] ) ) {
						$value = (string) $row['amount'];
					} elseif ( isset( $row['value'] ) ) {
						$value = (string) $row['value'];
					}

					$label_ref = '';
					if ( isset( $row['label_mode'] ) && $row['label_mode'] === 'ref' && isset( $row['label_ref'] ) ) {
						$label_ref = (string) $row['label_ref'];
					}

					if ( $value === '' && $label_ref === '' ) {
						// Completely empty row, skip.
						continue;
					}

					$rows[] = [
						'value'     => $value,
						'label_ref' => $label_ref,
					];
				}
			}
		}

		return $rows;
	}

	/**
	 * Flatten all price rows for current filters.
	 *
	 * Returns array of:
	 * [
	 *   'row_key'      => 'postid:index',
	 *   'post_id'      => int,
	 *   'row_index'    => int,
	 *   'item_title'   => string,
	 *   'menus'        => 'Menu A, Menu B',
	 *   'sections'     => 'Section X, Section Y',
	 *   'price_value'  => '6.25',
	 *   'label_ref'    => 'pl-1',
	 *   'label_text'   => 'Big Glass',
	 * ]
	 */
	private static function get_flat_price_rows( int $menu_id, int $section_id ): array {
		$rows = [];

		$q = new \WP_Query( self::get_items_query_args( $menu_id, $section_id ) );
		if ( ! $q->have_posts() ) {
			return $rows;
		}

		$labels = self::get_labels_registry();

		while ( $q->have_posts() ) {
			$q->the_post();
			$pid   = (int) get_the_ID();
			$title = get_the_title( $pid );

			$menu_names    = wp_get_post_terms( $pid, 'jprm_menu',    [ 'fields' => 'names' ] );
			$section_names = wp_get_post_terms( $pid, 'jprm_section', [ 'fields' => 'names' ] );

			$menus_str    = ( ! empty( $menu_names ) && ! is_wp_error( $menu_names ) )
				? implode( ', ', $menu_names ) : '';
			$sections_str = ( ! empty( $section_names ) && ! is_wp_error( $section_names ) )
				? implode( ', ', $section_names ) : '';

			$price_rows = self::get_price_rows_for_post( $pid );
			if ( empty( $price_rows ) ) {
				continue; // item truly has no price data at all.
			}

			foreach ( $price_rows as $idx => $pr ) {
				$price_val = (string) ( $pr['value'] ?? '' );
				$label_ref = (string) ( $pr['label_ref'] ?? '' );

				$label_text = '';
				if ( $label_ref !== '' ) {
					$label_text = self::get_label_text( $label_ref );
				}

				$rows[] = [
					'row_key'     => $pid . ':' . $idx,
					'post_id'     => $pid,
					'row_index'   => $idx,
					'item_title'  => $title,
					'menus'       => $menus_str,
					'sections'    => $sections_str,
					'price_value' => $price_val,
					'label_ref'   => $label_ref,
					'label_text'  => $label_text,
				];
			}
		}

		wp_reset_postdata();

		return $rows;
	}

	/* ======================================================================
	 * RENDERING (TABLE + BULK BAR)
	 * ====================================================================== */

	private static function render_bulk_actions_bar( string $position, array $labels ): void {
		?>
		<div class="jprm-bulk-actions jprm-bulk-actions-<?php echo esc_attr( $position ); ?>">
			<strong><?php esc_html_e( 'Bulk action:', 'jellopoint-restaurant-menu' ); ?></strong>

			<select name="jprm_bulk_action">
				<option value=""><?php esc_html_e( '— Select —', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="set_label"><?php esc_html_e( 'Set / change label', 'jellopoint-restaurant-menu' ); ?></option>
				<option value="clear_label"><?php esc_html_e( 'Clear label', 'jellopoint-restaurant-menu' ); ?></option>
			</select>

			<span>
				<?php esc_html_e( 'Target label:', 'jellopoint-restaurant-menu' ); ?>
				<select name="jprm_target_label">
					<option value=""><?php esc_html_e( '— Choose label —', 'jellopoint-restaurant-menu' ); ?></option>
					<?php foreach ( $labels as $id => $entry ) : ?>
						<?php
						$lbl = isset( $entry['label'] ) ? (string) $entry['label'] : '';
						if ( $lbl === '' ) {
							$lbl = $id;
						}
						?>
						<option value="<?php echo esc_attr( $id ); ?>">
							<?php echo esc_html( $lbl . ' (' . $id . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</span>

			<button type="submit" class="button" name="jprm_bulk_mode" value="preview">
				<?php esc_html_e( 'Preview', 'jellopoint-restaurant-menu' ); ?>
			</button>

			<button type="submit" class="button button-primary" name="jprm_bulk_mode" value="apply">
				<?php esc_html_e( 'Apply & Save', 'jellopoint-restaurant-menu' ); ?>
			</button>
		</div>
		<?php
	}

	private static function render_flat_rows_table( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<p><em>' . esc_html__( 'No price rows found for the current filters.', 'jellopoint-restaurant-menu' ) . '</em></p>';
			return;
		}
		?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="jprm-select-all" /></th>
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
						if ( $r['menus'] !== '' ) {
							echo esc_html( $r['menus'] );
						} else {
							echo '<span class="jprm-small-meta">' . esc_html__( '(no menu)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore
						}
						?>
					</td>
					<td>
						<?php
						if ( $r['sections'] !== '' ) {
							echo esc_html( $r['sections'] );
						} else {
							echo '<span class="jprm-small-meta">' . esc_html__( '(no section)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore
						}
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
					<td><?php echo esc_html( (string) $r['row_index'] ); ?></td>
					<td>
						<?php
						if ( $r['price_value'] !== '' ) {
							echo esc_html( $r['price_value'] );
						} else {
							echo '<span class="jprm-small-meta">' . esc_html__( '(no price)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore
						}
						?>
					</td>
					<td>
						<?php
						if ( $r['label_ref'] !== '' ) {
							echo esc_html( $r['label_text'] . ' (' . $r['label_ref'] . ')' );
						} else {
							echo '<span class="jprm-small-meta">' . esc_html__( '(no label)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore
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

	/* ======================================================================
	 * BULK ACTION (PREVIEW + APPLY)
	 * ====================================================================== */

	private static function handle_bulk_action(): void {
		if ( empty( $_POST['jprm_bulk_mode'] ) ) {
			return;
		}

		if ( ! isset( $_POST['jprm_bulk_price_labels_nonce'] )
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

		$mode         = sanitize_text_field( wp_unslash( $_POST['jprm_bulk_mode'] ) );
		$action       = isset( $_POST['jprm_bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['jprm_bulk_action'] ) ) : '';
		$target_label = isset( $_POST['jprm_target_label'] ) ? sanitize_text_field( wp_unslash( $_POST['jprm_target_label'] ) ) : '';
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

		if ( 'set_label' === $action && $target_label === '' ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_target',
				__( 'Please choose a target label.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		$labels = self::get_labels_registry();
		if ( 'set_label' === $action && ! isset( $labels[ $target_label ] ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_invalid_target',
				__( 'The selected target label does not exist.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		// Build changes grouped by post.
		$changes_by_post = [];
		foreach ( $rows as $rk ) {
			$parts = explode( ':', $rk );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$pid = (int) $parts[0];
			$idx = (int) $parts[1];
			if ( $pid <= 0 || $idx < 0 ) {
				continue;
			}
			if ( ! isset( $changes_by_post[ $pid ] ) ) {
				$changes_by_post[ $pid ] = [];
			}
			$changes_by_post[ $pid ][] = $idx;
		}

		if ( empty( $changes_by_post ) ) {
			add_settings_error(
				'jprm_bulk_price_labels',
				'jprm_bulk_price_labels_no_valid_rows',
				__( 'No valid price rows were selected.', 'jellopoint-restaurant-menu' ),
				'error'
			);
			return;
		}

		$total_rows    = 0;
		$preview_lines = [];

		foreach ( $changes_by_post as $pid => $indices ) {
			$pid   = (int) $pid;
			$title = get_the_title( $pid );

			// Current price meta.
			$raw_price   = get_post_meta( $pid, 'jprm_price', true );
			$price_data  = json_decode( (string) $raw_price, true );
			$has_price   = is_array( $price_data ) && isset( $price_data['rows'] ) && is_array( $price_data['rows'] );

			// Editable meta.
			$raw_edit  = get_post_meta( $pid, 'jprm_prices', true );
			$edit_rows = json_decode( (string) $raw_edit, true );
			if ( ! is_array( $edit_rows ) ) {
				$edit_rows = [];
			}

			foreach ( $indices as $idx ) {
				$idx = (int) $idx;
				$total_rows++;

				$old_ref   = '';
				$old_label = '';
				if ( $has_price && isset( $price_data['rows'][ $idx ] ) ) {
					$old_ref   = isset( $price_data['rows'][ $idx ]['label_ref'] ) ? (string) $price_data['rows'][ $idx ]['label_ref'] : '';
					$old_label = $old_ref !== '' ? self::get_label_text( $old_ref ) : '';
				}

				if ( 'set_label' === $action ) {
					$new_ref   = $target_label;
					$new_label = self::get_label_text( $new_ref );
				} else {
					$new_ref   = '';
					$new_label = __( '(no label)', 'jellopoint-restaurant-menu' );
				}

				// Collect preview line.
				if ( count( $preview_lines ) < 15 ) {
					$preview_lines[] = sprintf(
						'#%1$d "%2$s" — row %3$d: %4$s → %5$s',
						$pid,
						$title,
						$idx,
						( $old_label !== '' ? $old_label . ' (' . $old_ref . ')' : __( '(no label)', 'jellopoint-restaurant-menu' ) ),
						( $new_label . ( $new_ref !== '' ? ' (' . $new_ref . ')' : '' ) )
					);
				}

				// If preview only, do not modify arrays.
				if ( 'preview' === $mode ) {
					continue;
				}

				// === APPLY CHANGES (mode = apply) ==========================

				if ( $has_price && isset( $price_data['rows'][ $idx ] ) ) {
					$price_data['rows'][ $idx ]['label_ref'] = $new_ref;
				}

				if ( isset( $edit_rows[ $idx ] ) && is_array( $edit_rows[ $idx ] ) ) {
					$edit_rows[ $idx ]['label_mode']   = 'ref';
					$edit_rows[ $idx ]['label_ref']    = $new_ref;
					$edit_rows[ $idx ]['label_custom'] = '';
				}
			}

			// Write meta if in apply mode and we adjusted something.
			if ( 'apply' === $mode ) {
				if ( $has_price ) {
					update_post_meta( $pid, 'jprm_price', wp_json_encode( $price_data ) );
				}
				update_post_meta( $pid, 'jprm_prices', wp_json_encode( $edit_rows ) );
			}
		}

		// Build notice.
		if ( 'preview' === $mode ) {
			$headline = ( 'set_label' === $action )
				? sprintf(
					/* translators: 1: count, 2: label id */
					__( 'Preview: %1$d price rows would be assigned label "%2$s".', 'jellopoint-restaurant-menu' ),
					$total_rows,
					$target_label
				)
				: sprintf(
					__( 'Preview: label would be cleared from %d price rows.', 'jellopoint-restaurant-menu' ),
					$total_rows
				);
		} else {
			$headline = ( 'set_label' === $action )
				? sprintf(
					__( 'Done: %1$d price rows have been assigned label "%2$s".', 'jellopoint-restaurant-menu' ),
					$total_rows,
					$target_label
				)
				: sprintf(
					__( 'Done: label has been cleared from %d price rows.', 'jellopoint-restaurant-menu' ),
					$total_rows
				);
		}

		$msg  = esc_html( $headline );
		if ( ! empty( $preview_lines ) ) {
			$msg .= '<br><br><strong>' . esc_html__( 'Examples:', 'jellopoint-restaurant-menu' ) . "</strong><br>";
			$msg .= implode( '<br>', array_map( 'esc_html', $preview_lines ) );
			if ( count( $preview_lines ) >= 15 ) {
				$msg .= '<br>…';
			}
		}

		add_settings_error(
			'jprm_bulk_price_labels',
			'jprm_bulk_price_labels_result',
			$msg,
			'updated'
		);
	}
}
