<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels Tool — flat view (one row per price).
 *
 * - Filters: Menu + Section, with Section depending on Menu.
 * - Uses real label registry from jprm_price_labels_v2.
 * - Parses all known price meta formats (single / multi, JSON / serialized).
 * - Renders ONE table row per price:
 *   Menu | Section | Item | Price Index | Amount | Label
 *
 * No bulk write actions yet — this is a safe, read-only inspector.
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
		';

		wp_register_style( $handle, false, [], '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );

		// Small JS: auto-submit on Menu change so sections refresh immediately.
		$js = '
			(function(){
				const form  = document.querySelector(".jprm-bulk-price-labels-wrap form.jprm-filter-form");
				if (!form) return;
				const menuSel = form.querySelector("select[name=\'filter_menu\']");
				if (!menuSel) return;
				menuSel.addEventListener("change", function() {
					// Reset section to "all" when menu changes.
					const sectSel = form.querySelector("select[name=\'filter_section\']");
					if (sectSel) { sectSel.value = "0"; }
					form.submit();
				});
			}());
		';
		wp_add_inline_script( 'jquery-core', $js );
	}

	/**
	 * Main page renderer: builds filters, loads rows, prints flat table.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jellopoint-restaurant-menu' ) );
		}

		$current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0; // phpcs:ignore
		$current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0; // phpcs:ignore

		// --- Menus -------------------------------------------------------
		$menus = get_terms( [
			'taxonomy'   => 'jprm_menu',
			'hide_empty' => false,
		] );
		if ( is_wp_error( $menus ) ) {
			$menus = [];
		}

		// --- Sections pool (depends on menu) ----------------------------
		$sections_pool = self::load_sections_for_menu( $current_menu );

		// --- Main query: items filtered by menu + section ---------------
		$items_query = self::build_items_query( $current_menu, $current_section );

		// --- Label registry (id => label data) --------------------------
		$labels_index = self::load_price_labels_index();

		// --- Flatten all price rows across items ------------------------
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

				// If an item truly has no price rows, we simply skip it in this flat view.
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

			<div class="jprm-intro">
				<p>
					<?php esc_html_e(
						'This tool flattens all prices so each price row appears separately. You can use this as a safe inspector before we wire up real bulk label updates.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
				<p>
					<?php esc_html_e(
						'Use the filters to narrow down menus and sections. Multiple prices on one item appear as separate rows with a price index.',
						'jellopoint-restaurant-menu'
					); ?>
				</p>
			</div>

			<!-- FILTER BAR -->
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

			<!-- FLAT PRICE ROWS TABLE -->
			<table class="widefat fixed striped">
				<thead>
					<tr>
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
						<td colspan="6">
							<?php esc_html_e( 'No price rows match the current filters.', 'jellopoint-restaurant-menu' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $flat_rows as $r ) : ?>
						<tr>
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
		</div>
		<?php
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
	 *
	 * This covers:
	 * - jprm_price JSON (single + multi)
	 * - jprm_price serialized array
	 * - jprm_prices JSON / serialized array
	 * - legacy single mode meta: jprm_price_mode + jprm_price_amount + jprm_price_label_ref
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

			if ( $mode === 'single' ) {
				$amount    = (string) ( $struct['price'] ?? $struct['value'] ?? '' );
				$label_ref = isset( $struct['label_ref'] ) ? (string) $struct['label_ref'] : '';
				self::add_price_row( $rows, $amount, $label_ref, $labels_index, $struct );
			} elseif ( $mode === 'multi' && ! empty( $struct['rows'] ) && is_array( $struct['rows'] ) ) {
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
			if ( $mode === 'single' ) {
				$amount    = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
				$label_ref = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );
				self::add_price_row( $rows, $amount, $label_ref, $labels_index, [] );
			}
		}

		// Remove completely empty rows, just in case.
		$rows = array_values( array_filter(
			$rows,
			static function ( $row ) {
				return isset( $row['amount'] ) && trim( (string) $row['amount'] ) !== '';
			}
		) );

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
	private static function add_price_row( array &$rows, string $amount, string $label_ref, array $labels_index, array $raw_row ): void {
		$amount = trim( $amount );
		if ( $amount === '' ) {
			return;
		}

		$label_text = '';

		// If we have a ref and it exists in registry, prefer that.
		if ( $label_ref !== '' && isset( $labels_index[ $label_ref ] ) ) {
			$label_text = isset( $labels_index[ $label_ref ]['label'] )
				? (string) $labels_index[ $label_ref ]['label']
				: '';
		}

		// Fallback: custom label present in row.
		if ( $label_text === '' && ! empty( $raw_row['label_custom'] ) ) {
			$label_text = (string) $raw_row['label_custom'];
		}

		$rows[] = [
			'amount'     => $amount,
			'label_ref'  => $label_ref,
			'label_text' => $label_text,
		];
	}
}
