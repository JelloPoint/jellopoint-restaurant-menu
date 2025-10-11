<?php
/**
 * JelloPoint Restaurant Menu – Data Store: Dietary Badges
 *
 * Mirrors the Labels Store shape: simple rows with slug, name, icon (ID+URL).
 * Stored in option: jprm_dietary_badges_v1
 *
 * @package JPRM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_Badges_Store' ) ) :

class JPRM_Badges_Store {

	const OPTION_KEY = 'jprm_dietary_badges_v1';

	/**
	 * Get all rows (ensuring defaults on first run).
	 *
	 * @return array
	 */
	public function get_rows() {
		$rows = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $rows ) ) {
			$rows = $this->defaults();
			update_option( self::OPTION_KEY, $rows, false );
		}
		return array_values( array_map( [ $this, 'sanitize_row' ], $rows ) );
	}

	/**
	 * Save rows from admin POST.
	 *
	 * @param array $input
	 * @return void
	 */
	public function save_rows( $input ) {
		$san = [];

		if ( is_array( $input ) ) {
			foreach ( $input as $row ) {
				$r = $this->sanitize_row( $row );
				if ( $r['slug'] !== '' || $r['name'] !== '' || $r['icon_id'] || $r['icon_url'] !== '' ) {
					$san[] = $r;
				}
			}
		}

		update_option( self::OPTION_KEY, $san, false );
	}

	/**
	 * Empty/blank row for template.
	 *
	 * @return array
	 */
	public function blank_row() {
		return [
			'slug'     => '',
			'name'     => '',
			'icon_id'  => 0,
			'icon_url' => '',
		];
	}

	/**
	 * Sanitize a single row.
	 *
	 * @param array $row
	 * @return array
	 */
	protected function sanitize_row( $row ) {
		$slug = '';
		if ( isset( $row['slug'] ) ) {
			$slug = sanitize_title( wp_strip_all_tags( (string) $row['slug'] ) );
		}

		$name = '';
		if ( isset( $row['name'] ) ) {
			$name = sanitize_text_field( (string) $row['name'] );
		}

		$icon_id  = isset( $row['icon_id'] )  ? absint( $row['icon_id'] ) : 0;
		$icon_url = isset( $row['icon_url'] ) ? esc_url_raw( $row['icon_url'] ) : '';

		// If we have ID but no URL, try to resolve to current file URL (robust across moves).
		if ( $icon_id && empty( $icon_url ) ) {
			$maybe = wp_get_attachment_image_src( $icon_id, 'thumbnail' );
			if ( is_array( $maybe ) && ! empty( $maybe[0] ) ) {
				$icon_url = $maybe[0];
			}
		}

		return [
			'slug'     => $slug,
			'name'     => $name,
			'icon_id'  => $icon_id,
			'icon_url' => $icon_url,
		];
	}

	/**
	 * Opinionated defaults (icons intentionally empty so you can pick site-matching art).
	 *
	 * @return array[]
	 */
	protected function defaults() {
		return [
			[ 'slug' => 'vegan',        'name' => 'Vegan',        'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'vegetarian',   'name' => 'Vegetarian',   'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'gluten-free',  'name' => 'Gluten-Free',  'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'halal',        'name' => 'Halal',        'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'kosher',       'name' => 'Kosher',       'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'organic',      'name' => 'Organic',      'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'spicy',        'name' => 'Spicy',        'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'nut-free',     'name' => 'Nut-Free',     'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'dairy-free',   'name' => 'Dairy-Free',   'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'low-sugar',    'name' => 'Low Sugar',    'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'low-sodium',   'name' => 'Low Sodium',   'icon_id' => 0, 'icon_url' => '' ],
			[ 'slug' => 'contains-alc', 'name' => 'Contains Alcohol', 'icon_id' => 0, 'icon_url' => '' ],
		];
	}
}

endif;
