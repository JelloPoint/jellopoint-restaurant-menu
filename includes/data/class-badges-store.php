<?php
/**
 * JelloPoint Restaurant Menu – Data Store: Dietary Badges
 *
 * Mirrors the "Price Labels" data shape/UX:
 * - name (string)
 * - slug (string) — stable identifier used by menu items
 * - icon_id (int)
 * - icon_url (string)
 * - active (bool)
 * - order (int) — for stable sorting
 *
 * Stored as: option('jprm_dietary_badges') => array of rows in order.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_Badges_Store' ) ) :

class JPRM_Badges_Store {

	const OPTION_KEY = 'jprm_dietary_badges';

	/**
	 * Return rows in display order; if missing, seed defaults.
	 *
	 * @return array
	 */
	public function get_rows() : array {
		$rows = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $rows ) ) {
			$rows = $this->defaults();
			update_option( self::OPTION_KEY, $rows, false );
		}

		// Normalize & sort by 'order'
		$san = array_map( [ $this, 'sanitize_row' ], $rows );
		usort( $san, function( $a, $b ) {
			return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
		});

		// Reindex for neatness
		return array_values( $san );
	}

	/**
	 * Save rows from POST (expects ordered rows).
	 *
	 * @param array $input
	 * @return void
	 */
	public function save_rows( $input ) : void {
		$out = [];
		$seen_slugs = [];

		if ( is_array( $input ) ) {
			foreach ( $input as $row ) {
				$r = $this->sanitize_row( $row );
				// Keep non-empty lines only (at least a name or an icon)
				if ( $r['name'] !== '' || $r['icon_id'] || $r['icon_url'] !== '' ) {
					$base = $r['slug'] !== '' ? $r['slug'] : sanitize_title( $r['name'] );
					$slug = $base;
					$suffix = 2;
					while ( $slug !== '' && isset( $seen_slugs[ $slug ] ) ) {
						$slug = $base . '-' . $suffix++;
					}
					$r['slug'] = $slug;
					if ( $slug !== '' ) $seen_slugs[ $slug ] = true;
					$out[] = $r;
				}
			}
		}

		// Fix order sequence
		foreach ( $out as $i => &$r ) {
			$r['order'] = $i;
		}

		update_option( self::OPTION_KEY, $out, false );
	}

	/**
	 * Blank row template.
	 */
	public function blank_row() : array {
		return [
			'name'     => '',
			'slug'     => '',
			'icon_id'  => 0,
			'icon_url' => '',
			'active'   => true,
			'order'    => 0,
		];
	}

	/**
	 * Sanitize a single row.
	 */
	protected function sanitize_row( $row ) : array {
		$name     = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
		$slug     = isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : sanitize_title( $name );
		$icon_id  = isset( $row['icon_id'] ) ? absint( $row['icon_id'] ) : 0;
		$icon_url = isset( $row['icon_url'] ) ? esc_url_raw( (string) $row['icon_url'] ) : '';
		$active   = isset( $row['active'] ) ? (bool) $row['active'] : false;
		$order    = isset( $row['order'] ) ? (int) $row['order'] : 0;

		// If ID but no URL, resolve a thumbnail URL for robustness.
		if ( $icon_id && ! $icon_url ) {
			$maybe = wp_get_attachment_image_src( $icon_id, 'thumbnail' );
			if ( is_array( $maybe ) && ! empty( $maybe[0] ) ) {
				$icon_url = $maybe[0];
			}
		}

		return [
			'name'     => $name,
			'slug'     => $slug,
			'icon_id'  => $icon_id,
			'icon_url' => $icon_url,
			'active'   => $active,
			'order'    => $order,
		];
	}

	/**
	 * Opinionated defaults (icons empty so you can pick site-matching art).
	 */
	protected function defaults() : array {
		$names = [
			'Vegan',
			'Vegetarian',
			'Gluten-Free',
			'Halal',
			'Kosher',
			'Organic',
			'Spicy',
			'Nut-Free',
			'Dairy-Free',
			'Low Sugar',
			'Low Sodium',
			'Contains Alcohol',
		];

		$rows = [];
		foreach ( $names as $i => $n ) {
			$rows[] = [
				'name'     => $n,
				'slug'     => sanitize_title( $n ),
				'icon_id'  => 0,
				'icon_url' => '',
				'active'   => true,
				'order'    => $i,
			];
		}
		return $rows;
	}
}

endif;
