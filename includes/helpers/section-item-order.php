<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Per-section item ordering helpers
 * Meta:
 *  - _jprm_items_orderby: 'menu_order' | 'title' | 'date'
 *  - _jprm_items_orderdir: 'ASC' | 'DESC'
 */

if ( ! function_exists('jprm_get_section_item_ordering') ) {
	function jprm_get_section_item_ordering( int $section_id ) : array {
		$by  = get_term_meta( $section_id, '_jprm_items_orderby', true );
		$dir = get_term_meta( $section_id, '_jprm_items_orderdir', true );

		$by  = in_array( $by, ['menu_order','title','date'], true ) ? $by : 'menu_order';
		$dir = ( strtoupper( (string)$dir ) === 'DESC' ) ? 'DESC' : 'ASC';

		return [ 'by' => $by, 'dir' => $dir ];
	}
}

if ( ! function_exists('jprm_sort_items_array_in_place') ) {
	/**
	 * Sorts an array of WP_Post objects in place.
	 */
	function jprm_sort_items_array_in_place( array &$items, string $by, string $dir = 'ASC' ) : void {
		$desc = ( strtoupper($dir) === 'DESC' );
		usort( $items, static function( $a, $b ) use ( $by, $desc ) {
			$av = 0; $bv = 0;

			switch ( $by ) {
				case 'title':
					$av = (string) get_the_title( $a->ID );
					$bv = (string) get_the_title( $b->ID );
					$cmp = strcasecmp( $av, $bv );
					break;

				case 'date':
					$av = (string) ( $a->post_date ?? '' );
					$bv = (string) ( $b->post_date ?? '' );
					$cmp = strcmp( $av, $bv );
					break;

				case 'menu_order':
				default:
					$av = (int) ( $a->menu_order ?? 0 );
					$bv = (int) ( $b->menu_order ?? 0 );
					$cmp = $av <=> $bv;
					break;
			}

			return $desc ? -$cmp : $cmp;
		});
	}
}
