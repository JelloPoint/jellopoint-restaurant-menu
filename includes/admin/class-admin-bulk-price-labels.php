<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels Tool — list items + all price rows, with label lookup.
 *
 * - Filters: All = 73, Menu = 44, Menu+Section = 6, Section = 6 (matches your probe)
 * - Section dropdown depends on selected Menu (server-side)
 * - Prices column shows ALL rows (single + multi, old + new formats)
 * - Labels resolved via jprm_price_labels_v2 (dual mode: ref + text)
 *
 * This version is read-only (no bulk write yet).
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

        $css = '
            .jprm-bulk-price-labels-wrap .jprm-intro {
                max-width: 900px;
                margin-bottom: 1.5em;
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
            .jprm-bulk-price-labels-wrap table.widefat td,
            .jprm-bulk-price-labels-wrap table.widefat th {
                vertical-align: top;
            }
            .jprm-bulk-price-labels-wrap .jprm-price-list {
                margin: 0;
                padding-left: 1.1em;
            }
            .jprm-bulk-price-labels-wrap .jprm-price-list li {
                margin: 0;
            }
            .jprm-bulk-price-labels-wrap .jprm-price-no-label {
                color: #666;
            }
        ';

        wp_register_style( 'jprm-bulk-price-labels', false, [], '1.0' );
        wp_enqueue_style( 'jprm-bulk-price-labels' );
        wp_add_inline_style( 'jprm-bulk-price-labels', $css );
    }

    /**
     * MAIN RENDER (query + filters + table).
     */
    public static function render_page(): void {

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Not allowed.' );
        }

        // Current filters from URL.
        $current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0; // phpcs:ignore
        $current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0; // phpcs:ignore

        // Menus for dropdown.
        $menus = get_terms( [
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ] );
        if ( is_wp_error( $menus ) ) {
            $menus = [];
        }

        // -----------------------------------------------------------------
        // 1) DISCOVER SECTION POOL FOR CURRENT MENU
        // -----------------------------------------------------------------
        $section_pool = [];

        if ( $current_menu > 0 ) {
            $menu_items = new \WP_Query( [
                'post_type'      => 'jprm_menu_item',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [
                    [
                        'taxonomy' => 'jprm_menu',
                        'field'    => 'term_id',
                        'terms'    => [ $current_menu ],
                    ],
                ],
            ] );

            if ( $menu_items->have_posts() ) {
                $ids          = $menu_items->posts;
                $section_pool = wp_get_object_terms( $ids, 'jprm_section', [
                    'fields'     => 'all',
                    'hide_empty' => false,
                ] );
                if ( is_wp_error( $section_pool ) ) {
                    $section_pool = [];
                }
            }
            wp_reset_postdata();
        } else {
            // No menu filter → all sections.
            $section_pool = get_terms( [
                'taxonomy'   => 'jprm_section',
                'hide_empty' => false,
            ] );
            if ( is_wp_error( $section_pool ) ) {
                $section_pool = [];
            }
        }

        // -----------------------------------------------------------------
        // 2) MAIN QUERY (matches your 73 / 44 / 6 / 6 probe)
        // -----------------------------------------------------------------
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

        $main_args = [
            'post_type'      => 'jprm_menu_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( ! empty( $tax_query ) ) {
            $main_args['tax_query'] = $tax_query;
        }

        $items_query = new \WP_Query( $main_args );

        // -----------------------------------------------------------------
        // 3) LABEL REGISTRY (jprm_price_labels_v2 → index by id)
        // -----------------------------------------------------------------
        $label_registry = get_option( 'jprm_price_labels_v2', [] );
        $label_index    = [];

        if ( is_array( $label_registry ) ) {
            foreach ( $label_registry as $entry ) {
                if ( empty( $entry['id'] ) ) {
                    continue;
                }
                $label_index[ $entry['id'] ] = $entry;
            }
        }

        ?>
        <div class="wrap jprm-bulk-price-labels-wrap">

            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?>
            </h1>
            <hr class="wp-header-end">

            <div class="jprm-intro">
                <p>
                    <?php esc_html_e(
                        'This screen lists each menu item and all its price rows (single and multiple).',
                        'jellopoint-restaurant-menu'
                    ); ?>
                </p>
                <p>
                    <?php esc_html_e(
                        'Next step will be adding real bulk actions to change the price labels based on your selection.',
                        'jellopoint-restaurant-menu'
                    ); ?>
                </p>
            </div>

            <!-- FILTER BAR -->
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">

                <div class="jprm-filters">

                    <!-- MENU -->
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

                    <!-- SECTION (depends on menu) -->
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

                    <button class="button button-primary">
                        <?php esc_html_e( 'Filter', 'jellopoint-restaurant-menu' ); ?>
                    </button>
                </div>
            </form>

            <!-- ITEMS TABLE -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Item', 'jellopoint-restaurant-menu' ); ?></th>
                        <th><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></th>
                        <th><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></th>
                        <th><?php esc_html_e( 'Prices', 'jellopoint-restaurant-menu' ); ?></th>
                    </tr>
                </thead>
                <tbody>

                <?php
                if ( $items_query->have_posts() ) :
                    while ( $items_query->have_posts() ) :
                        $items_query->the_post();

                        $pid   = get_the_ID();
                        $title = get_the_title();

                        $menu_terms    = wp_get_object_terms( $pid, 'jprm_menu', [ 'fields' => 'names' ] );
                        $section_terms = wp_get_object_terms( $pid, 'jprm_section', [ 'fields' => 'names' ] );

                        // NEW: unified reader for all legacy + current formats.
                        $price_rows = self::get_price_rows_for_post( $pid, $label_index );
                        ?>

                        <tr>
                            <td>
                                <strong><?php echo esc_html( $title ); ?></strong>
                                <div class="row-actions">
                                    <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'jellopoint-restaurant-menu' ); ?>
                                    </a>
                                </div>
                            </td>
                            <td><?php echo esc_html( implode( ', ', (array) $menu_terms ) ); ?></td>
                            <td><?php echo esc_html( implode( ', ', (array) $section_terms ) ); ?></td>
                            <td>
                                <?php if ( ! empty( $price_rows ) ) : ?>
                                    <ul class="jprm-price-list">
                                        <?php foreach ( $price_rows as $row ) : ?>
                                            <li>
                                                <?php
                                                $amount = (string) ( $row['amount'] ?? '' );
                                                $lt     = (string) ( $row['label_text'] ?? '' );

                                                if ( $lt !== '' ) {
                                                    echo esc_html( $lt . ': ' . $amount );
                                                } elseif ( $amount !== '' ) {
                                                    echo esc_html( $amount );
                                                } else {
                                                    echo '<span class="jprm-price-no-label">' . esc_html__( '(empty row)', 'jellopoint-restaurant-menu' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                }

                                                // If label_ref exists but not in registry, show it for debugging.
                                                if ( ! empty( $row['label_ref'] ) && $lt === '' ) {
                                                    echo ' ';
                                                    echo '<span class="jprm-price-no-label">[' . esc_html( $row['label_ref'] ) . ']</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                }
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'No prices', 'jellopoint-restaurant-menu' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php
                    endwhile;
                else :
                    ?>
                    <tr><td colspan="4"><?php esc_html_e( 'No items match these filters.', 'jellopoint-restaurant-menu' ); ?></td></tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

        <script>
        // Optional UX: when changing menu, auto-submit to refresh sections for that menu.
        document.addEventListener('DOMContentLoaded', function () {
            const menuSel = document.getElementById('jprm-filter-menu');
            if (!menuSel) return;
            menuSel.addEventListener('change', function () {
                this.form.submit();
            });
        });
        </script>

        <?php
        wp_reset_postdata();
    }

    /**
     * Unified reader for ALL observed price storage variants.
     *
     * Returns array of rows, each:
     *   [
     *     'amount'     => string,
     *     'label_ref'  => string|null,
     *     'label_text' => string (resolved via registry or ''),
     *   ]
     */
    private static function get_price_rows_for_post( int $post_id, array $label_index ): array {
        $rows = [];

        // 1) Primary source: jprm_price
        $meta_price = get_post_meta( $post_id, 'jprm_price', true );
        $data       = null;

        if ( is_array( $meta_price ) ) {
            // Already unserialized array (your multi/single arrays).
            $data = $meta_price;
        } elseif ( is_string( $meta_price ) && $meta_price !== '' ) {
            // Could be JSON (e.g. {"mode":"multi",...}) as in item 800 / 812.
            $decoded = json_decode( $meta_price, true );
            if ( is_array( $decoded ) ) {
                $data = $decoded;
            }
        }

        // 2) Parse if we have a structured array with 'mode'.
        if ( is_array( $data ) && ! empty( $data['mode'] ) ) {
            $mode = (string) $data['mode'];

            if ( $mode === 'single' ) {
                $amount    = '';
                $label_ref = $data['label_ref'] ?? '';

                // single: price or amount (for safety)
                if ( isset( $data['price'] ) ) {
                    $amount = (string) $data['price'];
                } elseif ( isset( $data['amount'] ) ) {
                    $amount = (string) $data['amount'];
                }

                // fallback: meta split keys for label_ref (e.g. item 822)
                if ( $label_ref === '' ) {
                    $label_ref = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );
                }

                if ( $amount !== '' ) {
                    $rows[] = self::build_row( $amount, $label_ref, $label_index );
                }
            } elseif ( $mode === 'multi' && ! empty( $data['rows'] ) && is_array( $data['rows'] ) ) {
                foreach ( $data['rows'] as $r ) {
                    if ( ! is_array( $r ) ) {
                        continue;
                    }

                    $label_ref = $r['label_ref'] ?? '';
                    $amount    = '';

                    if ( isset( $r['value'] ) ) {
                        $amount = (string) $r['value'];
                    } elseif ( isset( $r['amount'] ) ) {
                        $amount = (string) $r['amount'];
                    }

                    if ( $amount === '' ) {
                        continue;
                    }

                    $rows[] = self::build_row( $amount, $label_ref, $label_index );
                }
            }
        }

        // 3) If we still have no rows, fall back to legacy split-meta scheme.
        if ( empty( $rows ) ) {
            $mode = (string) get_post_meta( $post_id, 'jprm_price_mode', true );

            if ( $mode === 'single' ) {
                $amount    = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
                $label_ref = (string) get_post_meta( $post_id, 'jprm_price_label_ref', true );

                if ( $amount !== '' ) {
                    $rows[] = self::build_row( $amount, $label_ref, $label_index );
                }
            } elseif ( $mode === 'multi' ) {
                $meta_prices = get_post_meta( $post_id, 'jprm_prices', true );

                // Can be array or JSON string (see item 802/861 vs 812).
                if ( is_string( $meta_prices ) && $meta_prices !== '' ) {
                    $decoded = json_decode( $meta_prices, true );
                    if ( is_array( $decoded ) ) {
                        $meta_prices = $decoded;
                    }
                }

                if ( is_array( $meta_prices ) ) {
                    foreach ( $meta_prices as $r ) {
                        if ( ! is_array( $r ) ) {
                            continue;
                        }

                        $amount    = '';
                        $label_ref = $r['label_ref'] ?? '';

                        if ( isset( $r['amount'] ) ) {
                            $amount = (string) $r['amount'];
                        } elseif ( isset( $r['value'] ) ) {
                            $amount = (string) $r['value'];
                        }

                        if ( $amount === '' ) {
                            continue;
                        }

                        $rows[] = self::build_row( $amount, $label_ref, $label_index );
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Helper to build a single normalized row with resolved label text.
     */
    private static function build_row( string $amount, string $label_ref, array $label_index ): array {
        $label_ref  = trim( $label_ref );
        $label_text = '';

        if ( $label_ref !== '' && isset( $label_index[ $label_ref ] ) ) {
            $entry = $label_index[ $label_ref ];
            if ( ! empty( $entry['label'] ) ) {
                $label_text = (string) $entry['label'];
            }
        }

        return [
            'amount'     => $amount,
            'label_ref'  => $label_ref,
            'label_text' => $label_text,
        ];
    }
}
