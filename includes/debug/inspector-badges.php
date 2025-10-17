<?php
/**
 * JPRM Diagnostics (Badges/Labels/Partials)
 *
 * Drop-in debug screen under Tools → JPRM Inspector.
 * Safe to ship in production; outputs only to wp-admin.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_management_page(
		'JPRM Inspector',
		'JPRM Inspector',
		'manage_options',
		'jprm-inspector',
		'jprm_render_inspector_screen'
	);
} );

function jprm_render_inspector_screen() {
	$plugin_dir  = dirname( dirname( __DIR__ ) );     // points to /includes
	$root_dir    = dirname( $plugin_dir );           // plugin root directory
	$partials    = $plugin_dir . '/render/partials';
	$badges_php  = $partials . '/badges.php';
	$prices_php  = $partials . '/price-block.php';

	$res = [
		'files' => [
			[
				'label' => 'Badges partial (includes/render/partials/badges.php)',
				'path'  => $badges_php,
				'ok'    => is_readable( $badges_php ),
			],
			[
				'label' => 'Price partial (includes/render/partials/price-block.php)',
				'path'  => $prices_php,
				'ok'    => is_readable( $prices_php ),
			],
		],
		'functions' => [
			[
				'name' => 'jprm_render_badges_html',
				'ok'   => function_exists( 'jprm_render_badges_html' ),
			],
			[
				'name' => 'jprm_build_badge_map',
				'ok'   => function_exists( 'jprm_build_badge_map' ),
			],
			[
				'name' => 'jprm_read_price_config',
				'ok'   => function_exists( 'jprm_read_price_config' ),
			],
		],
		'classes' => [
			[
				'name' => '\\JelloPoint\\RestaurantMenu\\Admin\\Dietary_Badges',
				'ok'   => class_exists( '\\JelloPoint\\RestaurantMenu\\Admin\\Dietary_Badges' ),
			],
			[
				'name' => '\\JelloPoint\\RestaurantMenu\\Badges\\Store',
				'ok'   => class_exists( '\\JelloPoint\\RestaurantMenu\\Badges\\Store' ),
			],
		],
	];

	// Try building the badge map (if available)
	$badge_map = null;
	$badge_map_err = null;
	if ( function_exists( 'jprm_build_badge_map' ) ) {
		try {
			$badge_map = jprm_build_badge_map();
		} catch ( \Throwable $e ) {
			$badge_map_err = $e->getMessage();
		}
	}

	// Probe a few items and try to render badges for them.
	$probe = [];
	$items = get_posts( [
		'post_type'      => 'jprm_menu_item',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	] );

	foreach ( $items as $p ) {
		$row = [
			'id'      => $p->ID,
			'title'   => get_the_title( $p->ID ),
			'edited'  => get_edit_post_link( $p->ID, '' ),
			'render'  => '',
			'status'  => '',
			'error'   => '',
		];
		if ( ! function_exists( 'jprm_render_badges_html' ) ) {
			$row['status'] = 'fail';
			$row['error']  = 'Function jprm_render_badges_html() not found';
		} else {
			try {
				$html = jprm_render_badges_html( $p->ID, 'icon_text', 'before', $badge_map );
				$row['render'] = is_string( $html ) ? $html : '';
				$row['status'] = ( $row['render'] !== '' ) ? 'ok' : 'empty';
				if ( $row['status'] === 'empty' ) {
					// Try “after” as well, just in case
					$alt = jprm_render_badges_html( $p->ID, 'icon_text', 'after', $badge_map );
					if ( is_string( $alt ) && $alt !== '' ) {
						$row['render'] = $alt;
						$row['status'] = 'ok (after)';
					} else {
						// Give a hint why it may be empty
						if ( empty( $badge_map ) ) {
							$row['error'] = 'No badge map entries / item has no attached badges.';
						} else {
							$row['error'] = 'Render function returned empty string.';
						}
					}
				}
			} catch ( \Throwable $e ) {
				$row['status'] = 'fail';
				$row['error']  = $e->getMessage();
			}
		}
		$probe[] = $row;
	}

	?>
	<div class="wrap">
		<h1>JPRM Inspector</h1>

		<h2>Environment</h2>
		<table class="widefat striped" style="max-width:980px;">
			<tbody>
				<tr><th>Plugin Root</th><td><?php echo esc_html( $root_dir ); ?></td></tr>
				<tr><th>Includes Dir</th><td><?php echo esc_html( $plugin_dir ); ?></td></tr>
				<tr><th>PHP</th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
				<tr><th>WP_DEBUG</th><td><?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?></td></tr>
			</tbody>
		</table>

		<h2 style="margin-top:20px;">Files</h2>
		<table class="widefat striped" style="max-width:980px;">
			<thead><tr><th>Check</th><th>Path</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $res['files'] as $f ): ?>
				<tr>
					<td><?php echo esc_html( $f['label'] ); ?></td>
					<td><code><?php echo esc_html( $f['path'] ); ?></code></td>
					<td><?php echo $f['ok'] ? '<span style="color:#177245;font-weight:600;">OK</span>' : '<span style="color:#B00020;font-weight:600;">MISSING</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:20px;">Functions</h2>
		<table class="widefat striped" style="max-width:980px;">
			<thead><tr><th>Function</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $res['functions'] as $fn ): ?>
				<tr>
					<td><code><?php echo esc_html( $fn['name'] ); ?></code></td>
					<td><?php echo $fn['ok'] ? '<span style="color:#177245;font-weight:600;">OK</span>' : '<span style="color:#B00020;font-weight:600;">NOT FOUND</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:20px;">Classes</h2>
		<table class="widefat striped" style="max-width:980px;">
			<thead><tr><th>Class</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $res['classes'] as $cl ): ?>
				<tr>
					<td><code><?php echo esc_html( $cl['name'] ); ?></code></td>
					<td><?php echo $cl['ok'] ? '<span style="color:#177245;font-weight:600;">OK</span>' : '<span style="color:#B00020;font-weight:600;">NOT FOUND</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:20px;">Badge Map</h2>
		<table class="widefat striped" style="max-width:980px;">
			<tbody>
				<tr>
					<th>Map Built</th>
					<td>
						<?php
						if ( $badge_map_err ) {
							echo '<span style="color:#B00020;font-weight:600;">ERROR</span> ';
							echo esc_html( $badge_map_err );
						} elseif ( is_array( $badge_map ) ) {
							echo '<span style="color:#177245;font-weight:600;">OK</span> ';
							echo 'entries: ' . count( $badge_map );
						} elseif ( $badge_map === null ) {
							echo ( function_exists( 'jprm_build_badge_map' ) )
								? '<span style="color:#B00020;font-weight:600;">FAILED</span> (returned null)'
								: '<span style="color:#B00020;font-weight:600;">NOT AVAILABLE</span> (function missing)';
						} else {
							echo '<span style="color:#B00020;font-weight:600;">UNKNOWN</span> (type: ' . esc_html( gettype( $badge_map ) ) . ')';
						}
						?>
					</td>
				</tr>
				<?php if ( is_array( $badge_map ) && ! empty( $badge_map ) ): ?>
				<tr>
					<th>First keys</th>
					<td><code>
						<?php
						$keys = array_slice( array_keys( $badge_map ), 0, 8 );
						echo esc_html( implode( ', ', $keys ) );
						?>
					</code></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<h2 style="margin-top:20px;">Probe Items (attempt to render badges)</h2>
		<table class="widefat striped" style="max-width:980px;">
			<thead>
			<tr>
				<th>ID</th>
				<th>Title</th>
				<th>Status</th>
				<th>Render Preview</th>
				<th>Note</th>
				<th>Edit</th>
			</tr>
			</thead>
			<tbody>
			<?php if ( empty( $probe ) ): ?>
				<tr><td colspan="6">No <code>jprm_menu_item</code> posts found.</td></tr>
			<?php else: foreach ( $probe as $row ): ?>
				<tr>
					<td><?php echo (int) $row['id']; ?></td>
					<td><?php echo esc_html( $row['title'] ); ?></td>
					<td><?php
						$color = (strpos($row['status'], 'ok') === 0) ? '#177245' : ( $row['status'] === 'empty' ? '#8A6D3B' : '#B00020' );
						echo '<span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . esc_html( $row['status'] ?: 'n/a' ) . '</span>';
					?></td>
					<td><?php echo $row['render'] !== '' ? wp_kses_post( $row['render'] ) : '<em>(no html)</em>'; ?></td>
					<td><?php echo $row['error'] ? esc_html( $row['error'] ) : ''; ?></td>
					<td><?php echo $row['edited'] ? '<a class="button button-small" href="'. esc_url( $row['edited'] ) .'">Edit</a>' : ''; ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<p style="margin-top:24px;">
			<strong>What to share with me:</strong> copy the whole page or take screenshots of the Files/Functions/Classes/Badge Map/Probe Items sections.
		</p>
	</div>
	<?php
}
