<?php
namespace JelloPoint\RestaurantMenu\Debug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only shortcode to inspect plugin data without jumping through code:
 *   [jprm_inspect item="123"]
 *   [jprm_inspect menu="12"]
 *   [jprm_inspect section="34"]
 *
 * Only shows to users who can 'manage_options'.
 */
final class Inspector_Shortcode {

	public static function init(): void {
		add_shortcode( 'jprm_inspect', [ __CLASS__, 'render' ] );
	}

	public static function render( $atts ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		$atts = shortcode_atts( [
			'item'    => '',
			'menu'    => '',
			'section' => '',
		], $atts, 'jprm_inspect' );

		ob_start();
		echo '<div class="wrap"><h2>JPRM Inspector</h2><div style="font-family:monospace;white-space:pre-wrap;background:#fff;border:1px solid #ccc;padding:12px;">';

		// Inspect a Menu Item post (meta + json)
		if ( $atts['item'] !== '' ) {
			$post_id = (int) $atts['item'];
			if ( $post_id > 0 ) {
				echo "== Item #{$post_id} (" . esc_html( get_the_title( $post_id ) ) . ")\n";
				$meta = get_post_meta( $post_id );
				echo "Meta keys:\n";
				foreach ( $meta as $k => $v ) {
					echo "  - {$k}: " . esc_html( var_export( maybe_unserialize( is_array($v) ? $v[0] ?? '' : $v ), true ) ) . "\n";
				}
				$price = get_post_meta( $post_id, 'jprm_price', true );
				echo "\nParsed jprm_price:\n";
				$decoded = json_decode( is_string($price) ? $price : '', true );
				echo esc_html( var_export( $decoded, true ) ) . "\n";
			}
		}

		// Inspect a Menu term (children, related sections by meta)
		if ( $atts['menu'] !== '' ) {
			$menu_id = (int) $atts['menu'];
			$term    = get_term( $menu_id, 'jprm_menu' );
			if ( $term && ! is_wp_error( $term ) ) {
				echo "\n== Menu #{$menu_id} (" . esc_html( $term->name ) . ")\n";
				echo "Slug: " . esc_html( $term->slug ) . "\n";
				echo "Meta:\n";
				$tm = get_term_meta( $menu_id );
				foreach ( $tm as $k => $v ) {
					echo "  - {$k}: " . esc_html( var_export( maybe_unserialize( is_array($v) ? $v[0] ?? '' : $v ), true ) ) . "\n";
				}

				// Sections catalog via meta
				echo "\nSections (linked by term-meta):\n";
				$sections = get_terms( [ 'taxonomy' => 'jprm_section', 'hide_empty' => false ] );
				$keys     = [ 'jprm_menu_id','_jprm_menu_id','jprm_menu','_jprm_menu','menu_id','_menu_id','jprm_menu_ids','_jprm_menu_ids' ];
				foreach ( $sections as $s ) {
					foreach ( $keys as $k ) {
						$val = get_term_meta( $s->term_id, $k, true );
						if ( empty( $val ) ) continue;
						$list = is_array($val) ? $val : ( is_string($val) ? preg_split('/\s*,\s*/', $val) : [ $val ] );
						$list = array_unique( array_map( 'intval', array_filter( $list ) ) );
						if ( in_array( $menu_id, $list, true ) ) {
							echo "  - #{$s->term_id} " . esc_html( $s->name ) . " (key: {$k})\n";
							break;
						}
					}
				}
			}
		}

		// Inspect a Section term (meta)
		if ( $atts['section'] !== '' ) {
			$sec_id = (int) $atts['section'];
			$term   = get_term( $sec_id, 'jprm_section' );
			if ( $term && ! is_wp_error( $term ) ) {
				echo "\n== Section #{$sec_id} (" . esc_html( $term->name ) . ")\n";
				echo "Slug: " . esc_html( $term->slug ) . "\n";
				echo "Parent: " . (int) $term->parent . "\n";
				echo "Meta:\n";
				$tm = get_term_meta( $sec_id );
				foreach ( $tm as $k => $v ) {
					echo "  - {$k}: " . esc_html( var_export( maybe_unserialize( is_array($v) ? $v[0] ?? '' : $v ), true ) ) . "\n";
				}
			}
		}

		// Global price labels option for reference.
		echo "\n== Option: jprm_price_labels_v2\n";
		$opt = get_option( 'jprm_price_labels_v2' );
		$decoded = is_string($opt) ? json_decode($opt, true) : ( is_array($opt) ? $opt : [] );
		echo esc_html( var_export( $decoded, true ) ) . "\n";

		echo "</div></div>";
		return ob_get_clean();
	}
}
