<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Sections_Admin_Order {
	const TAX_SECTION        = 'jprm_section';
	const TAX_MENU           = 'jprm_menu';
	const META_MENU_OWNER    = '_jprm_menu_term_id';
	const META_SECTION_ORDER = '_jprm_section_order';

	public static function init() : void {
		// Ensure the top "Filter by Menu" stays visible & sticky in URL (you already had this working)
		add_action( 'restrict_manage_terms', [ __CLASS__, 'toolbar_filter' ], 9, 2 );

		// Hard-enforce filter + order for *any* sections query in admin (list table + child lookups)
		add_action( 'pre_get_terms', [ __CLASS__, 'force_filter_and_order' ], 99 );

		// When WP decides to ignore our order (hierarchical child fetches), fix at SQL level
		add_filter( 'terms_clauses', [ __CLASS__, 'force_order_sql' ], 99, 3 );
	}

	/** Renders the menu filter above the list (simple, no JS). */
	public static function toolbar_filter( string $taxonomy, $which ) : void {
		if ( $taxonomy !== self::TAX_SECTION ) return;

		$selected = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		$menus    = get_terms( [ 'taxonomy' => self::TAX_MENU, 'hide_empty' => false ] );
		if ( is_wp_error( $menus ) ) return;

		echo '<label class="screen-reader-text" for="jprm_filter_menu">' . esc_html__( 'Filter by Menu', 'jprm' ) . '</label>';
		echo '<select name="jprm_filter_menu" id="jprm_filter_menu" class="postform">';
		echo '<option value="0">' . esc_html__( 'All Menus', 'jprm' ) . '</option>';
		foreach ( $menus as $m ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $m->term_id,
				selected( $selected, (int) $m->term_id, false ),
				esc_html( $m->name )
			);
		}
		echo '</select>';

		// keep URL stable on submit
		submit_button( __( 'Filter' , 'jprm' ), 'secondary', 'filter_action', false );
	}

	/** Make *all* admin list/child queries respect owner filter + section order. */
	public static function force_filter_and_order( \WP_Term_Query $q ) : void {
		if ( ! is_admin() ) return;

		$tax = (array) ( $q->query_vars['taxonomy'] ?? [] );
		if ( ! in_array( self::TAX_SECTION, $tax, true ) ) return;

		// Owner filter (toolbar)
		$menu_id = isset( $_GET['jprm_filter_menu'] ) ? (int) $_GET['jprm_filter_menu'] : 0; // phpcs:ignore
		if ( $menu_id > 0 ) {
			$mq   = (array) ( $q->query_vars['meta_query'] ?? [] );
			$mq[] = [ 'key' => self::META_MENU_OWNER, 'value' => (string) $menu_id ];
			$q->query_vars['meta_query'] = $mq;
		}

		// Force order by numeric section order (fallback to name only if order meta missing)
		$q->query_vars['meta_key'] = self::META_SECTION_ORDER;
		$q->query_vars['orderby']  = 'meta_value_num';
		$q->query_vars['order']    = 'ASC';

		// WP sometimes flips order for hierarchical child fetches; we fix again in terms_clauses.
	}

	/** When WP overrides order for hierarchical children, re-assert our ORDER BY at SQL level. */
	public static function force_order_sql( array $clauses, array $taxonomies, array $args ) : array {
		if ( ! is_admin() ) return $clauses;
		if ( empty( $taxonomies ) || ! in_array( self::TAX_SECTION, $taxonomies, true ) ) return $clauses;

		global $wpdb;

		// Ensure join to termmeta for our order meta if WP didn't add it
		if ( strpos( $clauses['join'], 'jprm_sord' ) === false ) {
			$clauses['join'] .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->termmeta} AS jprm_sord
				  ON ( jprm_sord.term_id = t.term_id AND jprm_sord.meta_key = %s ) ",
				self::META_SECTION_ORDER
			);
		}

		// Our ORDER BY (numeric) then name as tiebreaker
		$clauses['orderby'] = " ORDER BY CAST(jprm_sord.meta_value AS SIGNED) ASC, t.name ASC ";

		return $clauses;
	}
}

Sections_Admin_Order::init();
