<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk Price Labels Tool — Clean, Correct, Fully Working Version.
 *
 * Features:
 * - Filters work exactly like probe: All = 73, Menu = 44, Menu+Section = 6, Section = 6
 * - Section dropdown repopulates based on selected Menu BEFORE filtering
 * - Items list uses correct AND rules
 * - Ready for upcoming bulk edit actions
 */
final class JPRM_Admin_Bulk_Price_Labels {

    private const PAGE_SLUG  = 'jprm-bulk-price-labels';
    private const CAPABILITY = 'edit_posts';

    public static function bootstrap(): void {
        if ( ! is_admin() ) return;

        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 30 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function register_menu(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) return;

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
        if ( $hook_suffix !== $expected ) return;

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
            .jprm-bulk-price-labels-wrap table.widefat td,
            .jprm-bulk-price-labels-wrap table.widefat th {
                vertical-align: top;
            }
        ';

        wp_register_style( 'jprm-bulk-price-labels', false, [], '1.0' );
        wp_enqueue_style( 'jprm-bulk-price-labels' );
        wp_add_inline_style( 'jprm-bulk-price-labels', $css );
    }

    /**
     * MAIN RENDER (clean + fully rewritten — no duplicates).
     */
    public static function render_page(): void {

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Not allowed.' );
        }

        // Current filters
        $current_menu    = isset( $_GET['filter_menu'] )    ? (int) $_GET['filter_menu']    : 0;
        $current_section = isset( $_GET['filter_section'] ) ? (int) $_GET['filter_section'] : 0;

        // Get all menus
        $menus = get_terms( [
            'taxonomy'   => 'jprm_menu',
            'hide_empty' => false,
        ] );
        if ( is_wp_error( $menus ) ) $menus = [];

        // ============================================================
        // 1) DISCOVER SECTIONS DEPENDING ON SELECTED MENU
        // ============================================================

        $section_pool = [];

        if ( $current_menu > 0 ) {
            // First query: find items in that menu
            $menu_items = new \WP_Query( [
                'post_type'      => 'jprm_menu_item',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => 'jprm_menu',
                        'field'    => 'term_id',
                        'terms'    => [ $current_menu ],
                    ]
                ],
                'fields' => 'ids'
            ] );

            if ( $menu_items->have_posts() ) {
                $ids = $menu_items->posts;
                $section_pool = wp_get_object_terms( $ids, 'jprm_section', [
                    'fields'     => 'all',
                    'hide_empty' => false,
                ] );
                if ( is_wp_error( $section_pool ) ) $section_pool = [];
            }
            wp_reset_postdata();
        } else {
            // No menu selected → all sections
            $section_pool = get_terms( [
                'taxonomy'   => 'jprm_section',
                'hide_empty' => false,
            ] );
            if ( is_wp_error( $section_pool ) ) $section_pool = [];
        }

        // ============================================================
        // 2) MAIN QUERY (matches probe 73/44/6/6)
        // ============================================================

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

        // ============================================================
        // 3) RENDER
        // ============================================================

        ?>
        <div class="wrap jprm-bulk-price-labels-wrap">

            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Bulk Price Labels', 'jellopoint-restaurant-menu' ); ?>
            </h1>
            <hr class="wp-header-end">

            <!-- FILTER BAR -->
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">

                <div class="jprm-filters">

                    <!-- MENU -->
                    <div class="field">
                        <label><?php esc_html_e( 'Menu', 'jellopoint-restaurant-menu' ); ?></label>
                        <select name="filter_menu">
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
                        <label><?php esc_html_e( 'Section', 'jellopoint-restaurant-menu' ); ?></label>
                        <select name="filter_section">
                            <option value="0">
                                <?php echo $current_menu ? 'All Sections for this Menu' : 'All Sections'; ?>
                            </option>
                            <?php foreach ( $section_pool as $s ) : ?>
                                <option value="<?php echo (int) $s->term_id; ?>" <?php selected( $current_section, (int) $s->term_id ); ?>>
                                    <?php echo esc_html( $s->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="button button-primary"><?php esc_html_e( 'Filter', 'jellopoint-restaurant-menu' ); ?></button>
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

                        // Use the storage layer directly – no currency or label map needed here.
                        $rows = \JelloPoint\RestaurantMenu\Storage\Price_Repository::load_for_post( $pid );

                ?>

                    <tr>
                        <td>
                            <strong><?php echo esc_html( $title ); ?></strong>
                            <div class="row-actions">
                                <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">Edit</a>
                            </div>
                        </td>
                        <td><?php echo esc_html( implode( ', ', (array) $menu_terms ) ); ?></td>
                        <td><?php echo esc_html( implode( ', ', (array) $section_terms ) ); ?></td>
                        <td>
                        <?php if ( ! empty( $rows ) ) : ?>
                            <ul>
                                <?php foreach ( $rows as $pr ) : ?>
                                    <li>
                                        <?php
                                        $label = isset( $pr['label_text'] ) ? (string) $pr['label_text'] : '';
                                        $price = isset( $pr['price_raw'] )  ? (string) $pr['price_raw']  : '';

                                        // If for some reason price_raw is empty but another field exists:
                                        if ( $price === '' && isset( $pr['price'] ) ) {
                                            $price = (string) $pr['price'];
                                        }

                                        echo esc_html( $label !== '' ? "{$label}: {$price}" : $price );
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

                    <tr><td colspan="4">No items match these filters.</td></tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

        <?php
        wp_reset_postdata();
    }
}
