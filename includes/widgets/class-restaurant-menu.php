<?php
namespace JelloPoint\RestaurantMenu\Widgets;

use Elementor\Widget_Base;

use function jprm_build_label_map;
use function jprm_read_price_config;

require_once __DIR__ . '/traits/restaurant-menu-controls.php';
use JelloPoint\RestaurantMenu\Widgets\Traits\Restaurant_Menu_Controls;

require_once __DIR__ . '/traits/restaurant-menu-style.php';
use JelloPoint\RestaurantMenu\Widgets\Traits\Restaurant_Menu_Style;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Restaurant_Menu extends Widget_Base {
    use Restaurant_Menu_Controls, Restaurant_Menu_Style;

    public function get_name() { return 'jprm_restaurant_menu'; }
    public function get_title() { return __( 'Restaurant Menu (JelloPoint)', 'jellopoint-restaurant-menu' ); }
    public function get_icon() { return 'eicon-table'; }
    public function get_categories() { return [ 'jellopoint-widgets' ]; }
    public function get_keywords() { return [ 'menu','restaurant','prices','jellopoint','labels' ]; }
    public function get_style_depends() { return [ 'jprm-menu' ]; }
    public function get_script_depends() { return []; }

    /* ===== Partials / helpers ===== */
    private static function require_price_partial_once() : void {
        static $loaded = false; if ( $loaded ) return;
        $path = dirname( __DIR__ ) . '/render/partials/price-block.php';
        if ( is_readable( $path ) ) require_once $path;
        $loaded = true;
    }
    private static function require_badges_partial_once() : void {
        static $loaded = false; if ( $loaded ) return;
        $path = dirname( __DIR__ ) . '/render/partials/badges-block.php';
        if ( is_readable( $path ) ) { require_once $path; }
        $loaded = true;
    }
    private static function require_infoblocks_partial_once() : void {
        static $loaded = false; if ( $loaded ) return;
        $path = dirname( __DIR__ ) . '/render/partials/info-blocks.php';
        if ( is_readable( $path ) ) require_once $path;
        $loaded = true;
    }
    private static function ensure_menu_meta_helper() : void {
        if ( function_exists( 'jprm_render_menu_meta' ) ) return;
        function jprm_render_menu_meta( $term, bool $show_title, bool $show_desc, string $scope ) : string {
            if ( ! $term || ( ! $show_title && ! $show_desc ) ) return '';
            $title = $show_title ? trim( (string) $term->name ) : '';
            $desc  = $show_desc  ? trim( (string) $term->description ) : '';
            if ( $title === '' && $desc === '' ) return '';
            $cls = 'jp-menu__meta ' . ( $scope === 'global' ? 'jp-menu__meta--global' : 'jp-menu__meta--col' );
            $out  = '<div class="' . esc_attr( $cls ) . '">';
            if ( $title !== '' ) $out .= '<h2 class="jp-menu__meta-title">' . esc_html( $title ) . '</h2>';
            if ( $desc  !== '' ) $out .= '<div class="jp-menu__meta-desc">' . esc_html( $desc ) . '</div>';
            $out .= '</div>';
            return $out;
        }
    }

    /** Helper for controls: SELECT2 of all sections (id => label), with owner menu hint when available. */
    public function jprm_get_sections_options() : array {
        $terms = get_terms([
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
        ]);
        if ( is_wp_error( $terms ) || empty( $terms ) ) return [];

        $out = [];
        foreach ( $terms as $t ) {
            $owner_id = (int) get_term_meta( $t->term_id, '_jprm_menu_term_id', true );
            $suffix   = '';
            if ( $owner_id > 0 ) {
                $owner = get_term( $owner_id, 'jprm_menu' );
                if ( $owner && ! is_wp_error( $owner ) ) {
                    $suffix = ' — ' . (string) $owner->name;
                }
            }
            $out[ (int) $t->term_id ] = (string) $t->name . $suffix;
        }
        return $out;
    }

    /** Fetch sections that belong to a Menu, ordered by _jprm_section_order (ASC), then name. */
    private function jprm_get_ordered_sections_for_menu( int $menu_id ) : array {
        if ( $menu_id <= 0 ) return [];

        $terms = get_terms( [
            'taxonomy'   => 'jprm_section',
            'hide_empty' => false,
            'meta_query' => [
                [ 'key' => '_jprm_menu_term_id', 'value' => (string) $menu_id ],
            ],
            'meta_key'   => '_jprm_section_order',
            'orderby'    => 'meta_value_num',
            'order'      => 'ASC',
        ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return [];

        // Stable fallback by name if some terms miss the meta.
        usort( $terms, static function( $a, $b ){
            $ao = (int) get_term_meta( $a->term_id, '_jprm_section_order', true );
            $bo = (int) get_term_meta( $b->term_id, '_jprm_section_order', true );
            if ( $ao !== $bo ) return $ao <=> $bo;
            return strcasecmp( (string) $a->name, (string) $b->name );
        } );

        return $terms;
    }

    /** Return a numeric price used only for sorting (single or first multi row). */
    private static function jprm_effective_price_number( int $post_id ) : float {
        $mode   = (string) get_post_meta( $post_id, 'jprm_price_mode', true );
        $amount = '';

        if ( $mode === 'single' ) {
            $amount = (string) get_post_meta( $post_id, 'jprm_price_amount', true );
        } else {
            $rows = get_post_meta( $post_id, 'jprm_prices', true );
            if ( ! is_array( $rows ) ) {
                $p = get_post_meta( $post_id, 'jprm_price', true );
                $rows = ( is_array($p) && !empty($p['rows']) && is_array($p['rows']) ) ? $p['rows'] : [];
            }
            if ( ! empty( $rows ) ) {
                $r = (array) $rows[0];
                $amount = (string) ( $r['amount'] ?? ( $r['value'] ?? '' ) );
            }
        }

        $s = trim( (string) $amount );
        if ( $s === '' ) return INF; // empty pushes to end if ASC

        // normalize strings like "€ 2,50" -> "2.50"
        $s = preg_replace('~[^0-9,.\-]~', '', $s);
        if ( strpos($s, ',') !== false && strpos($s, '.') === false ) {
            $s = str_replace(',', '.', $s);
        }
        $n = floatval( $s );
        return is_finite($n) ? $n : INF;
    }

    /* =========================
     * Render
     * ========================= */
    public function render() {
        self::require_price_partial_once();
        self::require_badges_partial_once();
        self::require_infoblocks_partial_once();
        self::ensure_menu_meta_helper();

        // $s = with computed dynamic values; $raw = raw settings (for optional future use)
        $s   = $this->get_settings_for_display();
        $raw = $this->get_settings();

        // Static mode (unchanged)
        $mode = isset( $s['data_mode'] ) ? (string) $s['data_mode'] : null;
        if ( 'static' === $mode || ( null === $mode && ! empty( $s['items'] ) ) ) {
            $this->render_static_list( is_array( $s['items'] ) ? $s['items'] : [] );
            return;
        }

        $show_all           = ( isset( $s['show_all_when_empty'] ) && 'yes' === $s['show_all_when_empty'] );
        $menu_sel           = $s['menus'] ?? '';
        $sections_sel       = $s['sections'] ?? [];
        $orderby            = 'menu_order'; // query-level (we sort buckets later as needed)
        $order              = 'ASC';
        $limit              = ( isset( $s['query_limit'] ) && is_numeric( $s['query_limit'] ) ) ? (int) $s['query_limit'] : 0;

        $label_presentation = isset( $s['label_presentation'] ) ? (string) $s['label_presentation'] : 'icon_text';
        $label_position     = isset( $s['label_position'] ) ? (string) $s['label_position'] : 'right';

        $show_badges         = ( isset( $s['show_badges'] ) && $s['show_badges'] === 'yes' );
        $badges_presentation = isset( $s['badges_presentation'] ) ? (string) $s['badges_presentation'] : 'icon_text';
        $badges_position     = isset( $s['badges_position'] ) ? (string) $s['badges_position'] : 'after';

        $inline_leader_enable = ( !empty($s['inline_leader_enable']) && $s['inline_leader_enable'] === 'yes' ) ? 'yes' : 'no';
        $inline_leader_style  = (string) ( $s['inline_leader_style'] ?? 'dotted' );

        $currency_opts = [
            'show'     => ( isset( $s['jprm_curr_show'] ) && $s['jprm_curr_show'] === 'yes' ),
            'symbol'   => (string) ( $s['jprm_curr_symbol']   ?? '€' ),
            'position' => (string) ( $s['jprm_curr_position'] ?? 'before' ),
            'spacing'  => (string) ( $s['jprm_curr_spacing']  ?? 'thin' ),
        ];

        // Multi-column layout (content tab)
        // IMPORTANT: responsive controls may not populate layout_columns; use Elementor helper.
        $columns = (string) $this->get_settings_for_display( 'layout_columns' );
        if ( $columns === '' || $columns === '0' ) {
            $columns = '2'; // match your control default
        }
        $split_mode    = isset( $s['layout_split_mode'] ) ? (string) $s['layout_split_mode'] : 'auto';
        $split_after_1 = isset( $s['layout_split_after_section'] )  ? (int) $s['layout_split_after_section']  : 0;
        $split_after_2 = isset( $s['layout_split_after_section2'] ) ? (int) $s['layout_split_after_section2'] : 0;

        $menu_ids    = $this->normalize_to_ids( $menu_sel );
        $section_ids = $this->normalize_to_ids( $sections_sel );

        $menu_term = null;
        if ( count( $menu_ids ) === 1 ) {
            $menu_term = get_term( (int) $menu_ids[0], 'jprm_menu' );
            if ( ! $menu_term || is_wp_error( $menu_term ) ) $menu_term = null;
        }

        $show_menu_title = ( isset( $s['show_menu_title'] ) && $s['show_menu_title'] === 'yes' );
        $show_menu_desc  = ( isset( $s['show_menu_description'] ) && $s['show_menu_description'] === 'yes' );
        $menu_pos        = isset( $s['menu_title_position'] ) ? (string) $s['menu_title_position'] : 'above_menu';

        if ( empty( $menu_ids ) && empty( $section_ids ) && ! $show_all ) {
            echo '<div class="jp-menu--empty">' . esc_html__( 'Select a Menu or Section to display items.', 'jellopoint-restaurant-menu' ) . '</div>';
            return;
        }

        $items = $this->query_items( $menu_ids, $section_ids, $orderby, $order, $limit, $show_all );
        if ( empty( $items ) ) {
            echo '<div class="jp-menu--empty">' . esc_html__( 'No items found.', 'jellopoint-restaurant-menu' ) . '</div>';
            return;
        }

        $label_map = function_exists( 'jprm_build_label_map' ) ? jprm_build_label_map() : null;

        // Group posts under primary section
        $sections_order = [];
        $sections_data  = [];
        foreach ( $items as $post ) {
            $post_id = (int) $post->ID;

            $cfg = function_exists( 'jprm_read_price_config' ) ? jprm_read_price_config( $post_id ) : [];
            if ( empty( $cfg ) ) continue;

            $terms = wp_get_post_terms( $post_id, 'jprm_section', [ 'orderby' => 'name', 'order' => 'ASC' ] );
            $primary_tid  = 0;
            $primary_term = null;
            if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $primary_term = $terms[0];
                $primary_tid  = (int) $primary_term->term_id;
            }
            if ( ! isset( $sections_data[ $primary_tid ] ) ) {
                $sections_data[ $primary_tid ] = [ 'term' => $primary_term, 'items' => [] ];
                $sections_order[] = $primary_tid;
            }
            $sections_data[ $primary_tid ]['items'][] = $post;
        }

        // ---- Sort items per section based on global controls + optional overrides
        $global_ob = isset( $s['items_orderby'] ) ? (string)$s['items_orderby'] : 'menu_order';
        $global_od = isset( $s['items_order'] )   ? strtoupper((string)$s['items_order']) : 'ASC';

        // Read per-section rows from whichever key the trait uses.
        $per_section_rows = [];
        foreach ( [ 'items_order_overrides', 'items_order_sections', 'items_order_section_overrides' ] as $key ) {
            if ( ! empty( $s[ $key ] ) && is_array( $s[ $key ] ) ) {
                $per_section_rows = $s[ $key ];
                break;
            }
        }

        // Build quick override map: [ section_id => ['orderby'=>'...','order'=>'ASC|DESC'] ]
        $ov_map = [];
        if ( ! empty( $per_section_rows ) && is_array( $per_section_rows ) ) {
            foreach ( $per_section_rows as $ov ) {
                if ( ! is_array( $ov ) ) continue;

                // Section id field may be named a bit differently, be tolerant.
                $sid = 0;
                foreach ( [ 'section_id', 'section', 'section_term' ] as $k ) {
                    if ( isset( $ov[ $k ] ) && $ov[ $k ] !== '' ) {
                        $sid = (int) $ov[ $k ];
                        break;
                    }
                }
                if ( $sid <= 0 ) continue;

                // Orderby could be 'orderby' or 'order_by'
                $ob = $global_ob;
                if ( isset( $ov['orderby'] ) && $ov['orderby'] !== '' ) {
                    $ob = (string) $ov['orderby'];
                } elseif ( isset( $ov['order_by'] ) && $ov['order_by'] !== '' ) {
                    $ob = (string) $ov['order_by'];
                }

                // Direction could be 'order' or 'direction'
                $od = $global_od;
                if ( isset( $ov['order'] ) && $ov['order'] !== '' ) {
                    $od = strtoupper( (string) $ov['order'] );
                } elseif ( isset( $ov['direction'] ) && $ov['direction'] !== '' ) {
                    $od = strtoupper( (string) $ov['direction'] );
                }

                $ov_map[ $sid ] = [
                    'orderby' => $ob,
                    'order'   => $od,
                ];
            }
        }

        // Tiny debug hook: visible in view-source so you can confirm overrides are seen.
        if ( ! empty( $ov_map ) ) {
            echo "\n<!-- jprm items_order_overrides: " . esc_html( wp_json_encode( $ov_map ) ) . " -->\n";
        }

        foreach ( $sections_data as $tid => &$bucket ) {
            if ( empty( $bucket['items'] ) || ! is_array( $bucket['items'] ) ) continue;

            $use_ob = $ov_map[$tid]['orderby'] ?? $global_ob;
            $use_od = $ov_map[$tid]['order']   ?? $global_od;
            $dir    = ($use_od === 'DESC') ? -1 : 1;

            usort( $bucket['items'], function( $a, $b ) use ( $use_ob, $dir ) {
                $aid = (int)$a->ID; $bid = (int)$b->ID;

                switch ( $use_ob ) {
                    case 'title':
                        $av = mb_strtolower( get_the_title($aid) ?: '' );
                        $bv = mb_strtolower( get_the_title($bid) ?: '' );
                        $cmp = $av <=> $bv;
                        break;

                    case 'price':
                        $ap = self::jprm_effective_price_number( $aid );
                        $bp = self::jprm_effective_price_number( $bid );
                        $cmp = ($ap <=> $bp);
                        break;

                    case 'menu_order':
                    default:
                        // Prefer Menu Builder ordering: per-section order meta.
                        $meta_key = '_jprm_order_in_section';

                        $ameta = get_post_meta( $aid, $meta_key, true );
                        $bmeta = get_post_meta( $bid, $meta_key, true );

                        // If builder meta exists, use it; otherwise fall back to core menu_order.
                        $am = ( $ameta !== '' ) ? (int) $ameta : (int) get_post_field( 'menu_order', $aid );
                        $bm = ( $bmeta !== '' ) ? (int) $bmeta : (int) get_post_field( 'menu_order', $bid );

                        $cmp = $am <=> $bm;
                        break;
                }
                return $dir * $cmp;
            });
        }
        unset($bucket);

        // --- Reorder sections by builder order if a single Menu is chosen ---
        if ( $menu_term && empty( $section_ids ) ) {
            $ordered_terms   = $this->jprm_get_ordered_sections_for_menu( (int) $menu_term->term_id );
            $ordered_ids_all = array_map( static fn( $t ) => (int) $t->term_id, $ordered_terms );

            $reordered = [];

            $show_main_sections      = ( !empty( $s['show_main_sections'] ) && $s['show_main_sections'] === 'yes' );
            $show_main_even_if_empty = ( !empty( $s['show_main_even_if_empty'] ) && $s['show_main_even_if_empty'] === 'yes' );

            if ( $show_main_sections && $show_main_even_if_empty ) {
                // include empty sections too, in builder order
                foreach ( $ordered_ids_all as $tid ) {
                    if ( ! isset( $sections_data[ $tid ] ) ) {
                        $term = get_term( $tid, 'jprm_section' );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $sections_data[ $tid ] = [ 'term' => $term, 'items' => [] ];
                        }
                    }
                }
                $reordered = $ordered_ids_all;
            } else {
                // only those that actually have items, but still in builder order
                $have_items = array_keys( array_filter( $sections_data, static fn($row) => ! empty( $row['items'] ) ) );
                $reordered  = array_values( array_intersect( $ordered_ids_all, $have_items ) );
            }

            if ( ! empty( $reordered ) ) {
                $sections_order = $reordered;
            }
        }

        $show_section_name = ( isset( $s['show_section_name'] ) && $s['show_section_name'] === 'yes' );
        $show_section_desc = ( isset( $s['show_section_description'] ) && $s['show_section_description'] === 'yes' );

        // Info Blocks map
        $ib_rows = ( isset( $s['info_blocks'] ) && is_array( $s['info_blocks'] ) ) ? $s['info_blocks'] : [];
        $ib_map  = function_exists('jprm_infoblocks_partition_by_position') ? jprm_infoblocks_partition_by_position( $ib_rows ) : [];

        // ====== GLOBAL LABEL LAYOUTS PER DEVICE =========================

        // Desktop base (always from main control)
        $layout_desktop = isset( $s['labels_layout'] ) ? (string) $s['labels_layout'] : 'inline';
        if ( ! in_array( $layout_desktop, [ 'inline', 'inline_below', 'matrix' ], true ) ) {
            $layout_desktop = 'inline';
        }

        // Behaviour for tablet & mobile:
        //  - inline       → always Inline on tablet+mobile
        //  - inline_below → always Inline Below on tablet+mobile
        //  - per_section  → follow the per-section desktop layout (Matrix / Inline / Inline Below)
        $behaviour = isset( $s['labels_mobile_behaviour'] )
            ? (string) $s['labels_mobile_behaviour']
            : 'inline_below';

        if ( ! in_array( $behaviour, [ 'inline', 'inline_below', 'per_section' ], true ) ) {
            $behaviour = 'inline_below';
        }

        switch ( $behaviour ) {
            case 'inline':
                $layout_tablet   = 'inline';
                $layout_mobile   = 'inline';
                $layout_strategy = 'force_global';
                break;

            case 'inline_below':
                $layout_tablet   = 'inline_below';
                $layout_mobile   = 'inline_below';
                $layout_strategy = 'force_global';
                break;

            case 'per_section':
            default:
                // Tablet & mobile follow each section's effective desktop layout.
                $layout_tablet   = $layout_desktop;
                $layout_mobile   = $layout_desktop;
                $layout_strategy = 'respect_overrides';
                break;
        }

        // Global placeholder defaults (desktop base)
        $global_matrix_placeholder = isset( $s['labels_matrix_placeholder'] )
            ? (string) $s['labels_matrix_placeholder']
            : '—';

        // Per-section layout extras (Matrix placeholder / Inline-below separator)
        $section_layouts = [];
        $overrides = ( isset( $s['labels_layout_overrides'] ) && is_array( $s['labels_layout_overrides'] ) ) ? $s['labels_layout_overrides'] : [];
        foreach ( $overrides as $ov ) {
            $sid = isset( $ov['section_id'] ) ? (int) $ov['section_id'] : 0;
            if ( $sid <= 0 ) continue;

            $layout      = isset( $ov['layout'] ) ? (string) $ov['layout'] : '';
            $placeholder = isset( $ov['placeholder'] ) ? (string) $ov['placeholder'] : '';
            $separator   = isset( $ov['separator'] )   ? (string) $ov['separator']   : '';

            if ( ! isset( $section_layouts[ $sid ] ) ) {
                $section_layouts[ $sid ] = [ 'layout' => '', 'placeholder' => '', 'separator' => '' ];
            }
            if ( $layout !== '' ) {
                $section_layouts[ $sid ]['layout'] = $layout;
            }
            if ( $layout === 'matrix' && $placeholder !== '' ) {
                $section_layouts[ $sid ]['placeholder'] = html_entity_decode( $placeholder, ENT_QUOTES );
            }
            if ( $layout === 'inline_below' && $separator !== '' ) {
                $section_layouts[ $sid ]['separator'] = $separator;
            }
        }

        // Inline-below separator from Content tab (global)
        $inline_below_separator = (
            ! empty( $s['inline_below_sep_enable'] ) && $s['inline_below_sep_enable'] === 'on'
        )
            ? (string) ( $s['inline_below_sep_content'] ?? '' )
            : '';

        // Build ctx for template
        $ctx = [
            // Multi-column
           'layout_columns' => $columns,
            'layout_split_mode'           => $split_mode,
            'layout_split_after_section'  => $split_after_1,
            'layout_split_after_section2' => $split_after_2,

            // Menu meta
            'menu_term'           => $menu_term,
            'show_menu_title'     => $show_menu_title,
            'show_menu_desc'      => $show_menu_desc,
            'menu_pos'            => $menu_pos,

            // Sections + items
            'sections_order'      => $sections_order,
            'sections_data'       => $sections_data,
            'show_section_name'   => $show_section_name,
            'show_section_desc'   => $show_section_desc,

            // Main section header rules
            'show_main_sections'      => ( !empty($s['show_main_sections']) && $s['show_main_sections'] === 'yes' ) ? 'yes' : 'no',
            'show_main_even_if_empty' => ( !empty($s['show_main_even_if_empty']) && $s['show_main_even_if_empty'] === 'yes' ) ? 'yes' : 'no',

            // Badges
            'show_badges'         => $show_badges,
            'badges_presentation' => $badges_presentation,
            'badges_position'     => $badges_position,

            // Labels + currency
            'label_presentation'  => $label_presentation,
            'label_position'      => $label_position,
            'label_map'           => $label_map,
            'currency_opts'       => $currency_opts,

            // Leader
            'inline_leader_enable' => $inline_leader_enable,
            'inline_leader_style'  => $inline_leader_style,

            // Info Blocks
            'ib_map'              => $ib_map,

            // Layout overrides
            'section_layouts'     => $section_layouts,

            // Global layout per device
            'layout_desktop'      => $layout_desktop,
            'layout_tablet'       => $layout_tablet,
            'layout_mobile'       => $layout_mobile,
            'layout_strategy'     => $layout_strategy,

            // Global default (desktop) used for inheritance base
            'global_labels_layout'=> $layout_desktop,

            // Matrix placeholder (global)
            'labels_matrix_placeholder' => html_entity_decode( $global_matrix_placeholder, ENT_QUOTES ),

            // Inline-below separator from Content tab
            'inline_below_separator' => $inline_below_separator,

            // Legacy placeholder (still read by template inheritance)
            'global_placeholder'    => $global_matrix_placeholder,
        ];

        // Optional helpers
        $overrides_helper = dirname( __DIR__ ) . '/helpers/overrides.php';
        if ( file_exists( $overrides_helper ) ) require_once $overrides_helper;
        $icons_helper = dirname( __DIR__ ) . '/helpers/icons.php';
        if ( file_exists( $icons_helper ) ) require_once $icons_helper;

        // Dispatch into template
        $template = dirname( __DIR__ ) . '/render/templates/menu.php';
        if ( is_readable( $template ) ) {
            $ctx = $ctx; // local scope for template
            require $template;
        } else {
            echo '<div class="jp-menu--empty">Template missing.</div>';
        }
    }

    /* =========================
     * Data helpers
     * ========================= */
    protected function normalize_to_ids( $input ) : array {
        if ( $input === '' || $input === null ) return [];
        $vals = is_array( $input ) ? $input : [ $input ];
        $out  = [];
        foreach ( $vals as $v ) {
            if ( $v === '' || $v === null ) continue;
            $out[] = (int) $v;
        }
        return array_values( array_unique( array_filter( $out, fn( $n ) => $n > 0 ) ) );
    }

    protected function query_items( array $menu_ids, array $section_ids, string $orderby, string $order, int $limit, bool $fallback_all ) : array {
        $args = [
            'post_type'        => 'jprm_menu_item',
            'post_status'      => 'publish',
            'orderby'          => in_array( $orderby, [ 'menu_order','title','date' ], true ) ? $orderby : 'menu_order',
            'order'            => ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC',
            'posts_per_page'   => ( $limit > 0 ) ? $limit : -1,
            'suppress_filters' => false,
        ];

        $tax_query = [];
        if ( ! empty( $menu_ids ) )    $tax_query[] = [ 'taxonomy' => 'jprm_menu',    'field' => 'term_id', 'terms' => $menu_ids ];
        if ( ! empty( $section_ids ) ) $tax_query[] = [ 'taxonomy' => 'jprm_section', 'field' => 'term_id', 'terms' => $section_ids ];
        if ( ! empty( $tax_query ) )   $args['tax_query'] = $tax_query;
        elseif ( ! $fallback_all )     return [];

        $q = new \WP_Query( $args );
        return is_array( $q->posts ?? null ) ? $q->posts : [];
    }

    /* =========================
     * Static renderer
     * ========================= */
    protected function render_static_list( array $items ) : void {
        echo '<ul class="jp-menu">';
        foreach ( $items as $it ) {
            $title = $it['item_title'] ?? '';
            $desc  = $it['item_description'] ?? '';
            $price = $it['item_price'] ?? '';
            echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
            echo '  <div class="jp-menu__content">';
            echo '    <div class="jp-menu__titleline">';
            if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
            echo '    </div>';
            if ( $desc  !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
            echo '  </div>';
            echo '  <div class="jp-menu__pricegroup">';
            if ( $price !== '' ) {
                echo '    <div class="jp-menu__price">';
                echo '      <span class="jp-menu__value jp-col-price">' . esc_html( $price ) . '</span>';
                echo '    </div>';
            }
            echo '  </div>';
            echo '</div></li>';
        }
        echo '</ul>';
    }
}
