<?php
namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Exporter {

	/**
	 * Stream an export download for all jprm_menu_item posts.
	 *
	 * @param array $args { @type string $format 'json'|'csv' }
	 */
	public static function stream( array $args ): void {
		$format = ( isset( $args['format'] ) && $args['format'] === 'csv' ) ? 'csv' : 'json';

		$items = self::collect_items();

		if ( $format === 'json' ) {
			self::stream_json( $items );
			return;
		}
		self::stream_csv( $items );
	}

	/**
	 * Query and map all items to a canonical array.
	 *
	 * @return array
	 */
	private static function collect_items(): array {
		$q = new \WP_Query( [
			'post_type'      => 'jprm_menu_item',
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );

		$out = [];
		foreach ( $q->posts as $post_id ) {
			$title   = get_the_title( $post_id );
			$status  = get_post_status( $post_id );
			$desc    = get_post_meta( $post_id, 'jprm_desc', true );

			// Featured image
			$thumb_id  = (int) get_post_thumbnail_id( $post_id );
			$thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : null;

			// Terms
			$menus   = self::terms_as_names( $post_id, 'jprm_menu' );
			$sects   = self::terms_as_names( $post_id, 'jprm_section' );

			// Badges (array of slugs; fallback to empty array)
			$badges = get_post_meta( $post_id, 'jprm_item_badges', true );
			if ( ! is_array( $badges ) ) { $badges = []; }

			// Prices — do NOT guess schema; allow a filter to provide normalized rows.
			//  - Return value should be an array of rows, e.g.:
			//    [ [ 'label_id'=>12, 'label_text'=>'25cl', 'numeric'=>3.5, 'price'=>'€3.50' ], ... ]
			$prices = apply_filters( 'jprm/export/prices', null, $post_id );
			if ( $prices === null ) {
				// Attempt a generic fallback: try common meta keys (read-only, best effort).
				$prices = self::best_effort_prices( $post_id );
			}
			if ( ! is_array( $prices ) ) {
				$prices = []; // never break the export
			}

			$out[] = [
				'post_id'        => (int) $post_id,
				'post_title'     => (string) $title,
				'post_status'    => (string) $status,
				'description'    => is_string( $desc ) ? $desc : '',
				'featured_image' => $thumb_id ? [ 'id' => $thumb_id, 'url' => $thumb_url ] : null,
				'tax'            => [
					'jprm_menu'    => $menus,
					'jprm_section' => $sects,
				],
				'badges'         => array_values( array_filter( array_map( 'sanitize_title', $badges ) ) ),
				'prices'         => $prices,
			];
		}

		return $out;
	}

	private static function terms_as_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}
		return array_values( $terms );
	}

	/**
	 * Try a few non-destructive meta keys for prices if the filter is not implemented yet.
	 * If none found, return an empty array.
	 */
	private static function best_effort_prices( int $post_id ): array {
		$candidates = [
			'jprm_prices',
			'jprm_price_rows',
			'jprm_price_config',
		];
		foreach ( $candidates as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( is_array( $val ) && ! empty( $val ) ) {
				return $val;
			}
			if ( is_string( $val ) && $val !== '' ) {
				$maybe = json_decode( $val, true );
				if ( is_array( $maybe ) ) {
					return $maybe;
				}
			}
		}
		return [];
	}

	private static function stream_json( array $items ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export.json"' );

		$payload = [
			'meta'  => [
				'exported_at'   => gmdate( 'c' ),
				'plugin'        => 'jellopoint-restaurant-menu',
				'plugin_version'=> defined( 'JPRM_PLUGIN_VERSION' ) ? JPRM_PLUGIN_VERSION : '',
				'format'        => 'json',
			],
			'items' => $items,
		];

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function stream_csv( array $items ): void {
		// Excel-friendly: UTF-8 BOM + semicolon delimiter
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="jprm-export.csv"' );

		$out = fopen( 'php://output', 'w' );
		// BOM
		fwrite( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );

		$headers = [ 'post_id', 'post_title', 'post_status', 'description', 'menus', 'sections', 'badges', 'prices_json', 'featured_image_id', 'featured_image_url' ];
		fputcsv( $out, $headers, ';', '"' );

		foreach ( $items as $it ) {
			$menus   = implode( '|', (array) ( $it['tax']['jprm_menu'] ?? [] ) );
			$sects   = implode( '|', (array) ( $it['tax']['jprm_section'] ?? [] ) );
			$badges  = implode( '|', (array) ( $it['badges'] ?? [] ) );
			$pricesJ = wp_json_encode( $it['prices'], JSON_UNESCAPED_SLASHES );

			$fid = '';
			$furl= '';
			if ( ! empty( $it['featured_image']['id'] ) ) {
				$fid  = (string) $it['featured_image']['id'];
				$furl = (string) ( $it['featured_image']['url'] ?? '' );
			}

			$row = [
				$it['post_id'],
				self::esc_csv( $it['post_title'] ),
				$it['post_status'],
				self::esc_csv( $it['description'] ),
				$menus,
				$sects,
				$badges,
				$pricesJ,
				$fid,
				$furl,
			];
			fputcsv( $out, $row, ';', '"' );
		}
		fclose( $out );
		exit;
	}

	private static function esc_csv( $val ): string {
		$val = is_scalar( $val ) ? (string) $val : '';
		// Keep it simple; fputcsv will quote. Strip CR/LF to keep rows tidy.
		return str_replace( [ "\r\n", "\n", "\r" ], ' ', $val );
	}
}
