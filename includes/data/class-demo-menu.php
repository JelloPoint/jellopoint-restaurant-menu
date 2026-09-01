<?php
/**
 * Safe, repeatable demo-menu import.
 */

namespace JelloPoint\RestaurantMenu\Data;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class JPRM_Demo_Menu {

	private const OPTION_KEY = 'jprm_demo_menu_v1';
	private const MENU_NAME  = 'JelloPoint Demo Menu';
	private const MENU_SLUG  = 'jellopoint-demo-menu';

	/** Return a dry-run report or import the bundled demo menu. */
	public static function run( bool $dry_run = true ): array {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( is_array( $stored ) && ! empty( $stored['menu_term_id'] ) ) {
			$names = is_array( $stored['names'] ?? null ) ? $stored['names'] : [];
			$items = self::items( $names );
			return self::already_imported_report( $items, $stored, $dry_run );
		}

		$names = self::resolved_names();
		$items = self::items( $names );

		if ( ! class_exists( JPRM_Importer::class ) ) {
			require_once __DIR__ . '/class-importer.php';
		}

		$tmp_file = wp_tempnam( 'jprm-demo-menu.json' );
		if ( ! is_string( $tmp_file ) || '' === $tmp_file ) {
			return self::error_report( 'Could not create a temporary demo import file.', $dry_run );
		}

		$payload = json_encode( [ 'items' => $items ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( false === $payload || false === file_put_contents( $tmp_file, $payload ) ) {
			@unlink( $tmp_file );
			return self::error_report( 'Could not prepare the bundled demo menu.', $dry_run );
		}

		$report = JPRM_Importer::run(
			[
				'name'     => 'jprm-demo-menu.json',
				'tmp_name' => $tmp_file,
			],
			[
				'dry_run'              => $dry_run,
				'create_missing_terms' => true,
				'ignore_ids'           => true,
			]
		);
		@unlink( $tmp_file );

		if ( ! $dry_run && empty( $report['errors'] ) && count( $items ) === (int) ( $report['created'] ?? 0 ) ) {
			self::finalize_import( $report, $names );
		}

		return $report;
	}

	/** Summary used by the admin screen before an import. */
	public static function summary(): array {
		$items = self::items();
		return [
			'menu'    => self::MENU_NAME,
			'sections' => [ 'Starters', 'Mains', 'Desserts', 'Salads', 'Drinks', 'Wine', 'Beer' ],
			'items'   => count( $items ),
		];
	}

	/** Remove only content carrying this demo import's explicit markers. */
	public static function remove(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) || empty( $stored['menu_term_id'] ) ) {
			return [ 'items' => 0, 'sections' => 0, 'menus' => 0 ];
		}

		$removed_items = 0;
		foreach ( array_map( 'absint', (array) ( $stored['post_ids'] ?? [] ) ) as $post_id ) {
			if ( $post_id <= 0 || 1 !== (int) get_post_meta( $post_id, '_jprm_demo_menu', true ) ) { continue; }
			if ( wp_trash_post( $post_id ) ) {
				++$removed_items;
			}
		}

		$removed_sections = 0;
		$names = is_array( $stored['names'] ?? null ) ? $stored['names'] : [];
		$section_names = is_array( $names['sections'] ?? null ) ? $names['sections'] : [];
		foreach ( array_reverse( self::summary()['sections'] ) as $original_name ) {
			$name = (string) ( $section_names[ $original_name ] ?? $original_name );
			$term = get_term_by( 'name', $name, 'jprm_section' );
			if ( ! $term || 1 !== (int) get_term_meta( (int) $term->term_id, '_jprm_demo_menu', true ) ) { continue; }
			$result = wp_delete_term( (int) $term->term_id, 'jprm_section' );
			if ( true === $result ) {
				++$removed_sections;
			}
		}

		$removed_menus = 0;
		$menu_id = absint( $stored['menu_term_id'] );
		if ( $menu_id > 0 && 1 === (int) get_term_meta( $menu_id, '_jprm_demo_menu', true ) ) {
			$result = wp_delete_term( $menu_id, 'jprm_menu' );
			if ( true === $result ) {
				$removed_menus = 1;
			}
		}

		delete_option( self::OPTION_KEY );
		return [ 'items' => $removed_items, 'sections' => $removed_sections, 'menus' => $removed_menus ];
	}

	/** Bundled demo items in the normal lossless importer shape. */
	public static function items( array $names = [] ): array {
		$single = static function ( string $title, string $section, string $description, string $price, array $badges = [] ): array {
			return self::item( $title, $section, $description, $badges, [
				'mode'          => 'single',
				'amount_raw'    => $price,
				'amount_number' => null,
				'label_mode'    => 'ref',
				'label_ref'     => '',
			] );
		};

		$multi = static function ( string $title, string $section, string $description, array $rows, array $badges = [] ): array {
			$prices = [];
			foreach ( $rows as $label => $amount ) {
				$prices[] = [
					'enabled'      => true,
					'label_mode'   => 'custom',
					'label_ref'    => '',
					'label_custom' => $label,
					'icon_id'      => 0,
					'amount'       => $amount,
					'hide_icon'    => true,
				];
			}
			return self::item( $title, $section, $description, $badges, [ 'mode' => 'multi', 'rows' => $prices ] );
		};

		$items = [
			$single( 'Roasted Tomato Soup', 'Starters', 'Slow-roasted tomato, basil oil and sourdough.', '7.50', [ 'vegan' ] ),
			$single( 'Burrata & Heritage Tomato', 'Starters', 'Creamy burrata, heritage tomatoes, pesto and toasted pine nuts.', '12.50', [ 'vegetarian' ] ),
			$single( 'Beef Carpaccio', 'Starters', 'Truffle mayonnaise, capers, Parmesan and rocket.', '14.50', [ 'gluten-free' ] ),
			$single( 'Crispy Calamari', 'Starters', 'Lemon, parsley and roasted garlic aioli.', '13.00' ),

			$single( 'Pan-Roasted Sea Bass', 'Mains', 'Seasonal vegetables, crushed potatoes and lemon beurre blanc.', '25.50', [ 'gluten-free' ] ),
			$single( 'Wild Mushroom Risotto', 'Mains', 'Truffle, Parmesan and fresh herbs.', '21.00', [ 'vegetarian', 'gluten-free' ] ),
			$single( 'Steak Frites', 'Mains', 'Grilled sirloin, hand-cut fries, watercress and peppercorn sauce.', '27.50', [ 'gluten-free' ] ),
			$single( 'Thai Green Vegetable Curry', 'Mains', 'Coconut, jasmine rice, lime and coriander.', '19.50', [ 'vegan', 'gluten-free', 'spicy' ] ),
			$single( 'JelloPoint Burger', 'Mains', 'Beef burger, mature cheddar, tomato relish and fries.', '20.50' ),

			$single( 'Vanilla Panna Cotta', 'Desserts', 'Red berries and almond crumble.', '8.50', [ 'gluten-free' ] ),
			$single( 'Warm Chocolate Fondant', 'Desserts', 'Salted caramel and vanilla ice cream.', '9.50', [ 'vegetarian' ] ),
			$single( 'Mango & Passion Fruit Sorbet', 'Desserts', 'Fresh mint and seasonal fruit.', '7.50', [ 'vegan', 'gluten-free' ] ),
			$single( 'European Cheese Selection', 'Desserts', 'Chutney, grapes and artisan crackers.', '12.00', [ 'vegetarian' ] ),

			$single( 'Classic Caesar Salad', 'Salads', 'Romaine lettuce, Parmesan, croutons and Caesar dressing.', '15.50' ),
			$single( 'Mediterranean Quinoa Salad', 'Salads', 'Roasted vegetables, olives, herbs and lemon dressing.', '16.00', [ 'vegan', 'gluten-free' ] ),
			$single( 'Goat Cheese & Beetroot Salad', 'Salads', 'Walnuts, apple, leaves and balsamic dressing.', '16.50', [ 'vegetarian', 'gluten-free' ] ),

			$multi( 'Mineral Water', 'Drinks', 'Still or sparkling mineral water.', [ '500 ml' => '4.00', '750 ml' => '6.50' ] ),
			$multi( 'Sauvignon Blanc', 'Wine', 'Fresh and aromatic with citrus, gooseberry and a crisp finish.', [ 'Glass' => '6.50', 'Bottle' => '29.50' ], [ 'contains-alcohol' ] ),
			$multi( 'Merlot', 'Wine', 'Soft and rounded with ripe plum, blackberry and gentle spice.', [ 'Glass' => '6.75', 'Bottle' => '31.00' ], [ 'contains-alcohol' ] ),
			$multi( 'Prosecco', 'Wine', 'Light sparkling wine with apple, pear and floral notes.', [ 'Glass' => '7.50', 'Bottle' => '34.00' ], [ 'contains-alcohol' ] ),
			$multi( 'JelloPoint Pilsner', 'Beer', 'Crisp house pilsner with a clean, refreshing finish.', [ '250 ml' => '3.75', '500 ml' => '7.00' ], [ 'contains-alcohol' ] ),
			$multi( 'Belgian Blonde', 'Beer', 'Golden specialty beer with fruit, spice and a soft bitterness.', [ '330 ml' => '5.75', '750 ml' => '12.50' ], [ 'contains-alcohol' ] ),
			$single( 'Alcohol-Free IPA', 'Beer', 'Bright citrus hops with a balanced malt finish.', '4.75' ),
		];

		if ( $names ) {
			$menu_name = (string) ( $names['menu'] ?? self::MENU_NAME );
			$section_names = is_array( $names['sections'] ?? null ) ? $names['sections'] : [];
			foreach ( $items as &$item ) {
				$original_section = (string) ( $item['tax']['jprm_section'][0] ?? '' );
				$item['tax']['jprm_menu'] = [ $menu_name ];
				$item['tax']['jprm_section'] = [ (string) ( $section_names[ $original_section ] ?? $original_section ) ];
			}
			unset( $item );
		}

		return $items;
	}

	private static function item( string $title, string $section, string $description, array $badges, array $prices ): array {
		return [
			'post_id'     => 0,
			'post_title'  => $title,
			'post_status' => 'publish',
			'description' => $description,
			'tax'         => [
				'jprm_menu'    => [ self::MENU_NAME ],
				'jprm_section' => [ $section ],
			],
			'badges'      => $badges,
			'prices'      => $prices,
		];
	}

	private static function resolved_names(): array {
		$menu_name = self::MENU_NAME;
		if ( get_term_by( 'name', $menu_name, 'jprm_menu' ) || get_term_by( 'slug', self::MENU_SLUG, 'jprm_menu' ) ) {
			$menu_name = self::unique_term_name( self::MENU_NAME, 'jprm_menu' );
		}

		$sections = [];
		foreach ( self::summary()['sections'] as $name ) {
			$sections[ $name ] = get_term_by( 'name', $name, 'jprm_section' )
				? self::unique_term_name( $name, 'jprm_section' )
				: $name;
		}

		return [ 'menu' => $menu_name, 'sections' => $sections ];
	}

	private static function unique_term_name( string $preferred, string $taxonomy ): string {
		$candidate = $preferred . ' (Demo)';
		$suffix = 2;
		while ( get_term_by( 'name', $candidate, $taxonomy ) ) {
			$candidate = $preferred . ' (Demo ' . $suffix++ . ')';
		}
		return $candidate;
	}

	private static function finalize_import( array $report, array $names ): void {
		$menu_name = (string) ( $names['menu'] ?? self::MENU_NAME );
		$section_names = is_array( $names['sections'] ?? null ) ? $names['sections'] : [];
		$menu = get_term_by( 'name', $menu_name, 'jprm_menu' );
		if ( ! $menu ) { return; }

		$menu_id = (int) $menu->term_id;
		wp_update_term( $menu_id, 'jprm_menu', [
			'slug'        => sanitize_title( $menu_name ),
			'description' => 'A complete example restaurant menu created by JelloPoint.',
		] );
		update_term_meta( $menu_id, '_jprm_demo_menu', 1 );

		$section_ids = [];
		foreach ( self::summary()['sections'] as $order => $original_name ) {
			$name = (string) ( $section_names[ $original_name ] ?? $original_name );
			$term = get_term_by( 'name', $name, 'jprm_section' );
			if ( ! $term ) { continue; }
			$section_ids[ $original_name ] = (int) $term->term_id;
			update_term_meta( (int) $term->term_id, '_jprm_menu_term_id', $menu_id );
			update_term_meta( (int) $term->term_id, '_jprm_section_order', $order );
			update_term_meta( (int) $term->term_id, '_jprm_demo_menu', 1 );
		}

		if ( isset( $section_ids['Drinks'], $section_ids['Wine'], $section_ids['Beer'] ) ) {
			wp_update_term( $section_ids['Wine'], 'jprm_section', [ 'parent' => $section_ids['Drinks'] ] );
			wp_update_term( $section_ids['Beer'], 'jprm_section', [ 'parent' => $section_ids['Drinks'] ] );
		}

		$post_ids = [];
		foreach ( (array) ( $report['items'] ?? [] ) as $row ) {
			$post_id = (int) ( $row['post_id_new'] ?? 0 );
			if ( $post_id > 0 ) {
				update_post_meta( $post_id, '_jprm_demo_menu', 1 );
				$post_ids[] = $post_id;
			}
		}

		update_option( self::OPTION_KEY, [
			'version'      => 1,
			'menu_term_id' => $menu_id,
			'post_ids'     => $post_ids,
			'names'        => $names,
		] );
	}

	private static function already_imported_report( array $items, array $stored, bool $dry_run ): array {
		$rows = [];
		$post_ids = array_values( array_map( 'absint', (array) ( $stored['post_ids'] ?? [] ) ) );
		foreach ( $items as $index => $item ) {
			$rows[] = [
				'post_id_old'   => $post_ids[ $index ] ?? 0,
				'post_id_new'   => $post_ids[ $index ] ?? 0,
				'title'         => (string) $item['post_title'],
				'action'        => 'unchanged',
				'price_summary' => self::price_summary( $item ),
				'menus'         => (array) $item['tax']['jprm_menu'],
				'sections'      => (array) $item['tax']['jprm_section'],
				'badges'        => (array) $item['badges'],
				'changes'       => [],
				'error'         => '',
			];
		}
		return self::base_report( $dry_run, 0, 0, count( $rows ), 0, [], $rows );
	}

	private static function error_report( string $message, bool $dry_run ): array {
		return self::base_report( $dry_run, 0, 0, 0, 0, [ $message ], [] );
	}

	private static function base_report( bool $dry_run, int $created, int $updated, int $unchanged, int $skipped, array $errors, array $items ): array {
		return [
			'dry_run'   => $dry_run,
			'created'   => $created,
			'updated'   => $updated,
			'unchanged' => $unchanged,
			'skipped'   => $skipped,
			'errors'    => $errors,
			'new_terms' => [ 'menus' => 0, 'sections' => 0, 'menus_list' => [], 'sections_list' => [] ],
			'items'     => $items,
		];
	}

	private static function price_summary( array $item ): string {
		$prices = (array) ( $item['prices'] ?? [] );
		return 'multi' === ( $prices['mode'] ?? '' )
			? count( (array) ( $prices['rows'] ?? [] ) ) . ' rows'
			: (string) ( $prices['amount_raw'] ?? '' );
	}
}
