<?php
/**
 * Plugin Name: JelloPoint – Restaurant Menu
 * Description: Restaurant Menu items, labels and Elementor widget.
 * Version: 2.0.6
 * Author: JelloPoint
 * Text Domain: jellopoint-restaurant-menu
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------
 * Constants
 * ------------------------------------------------- */
if ( ! defined( 'JPRM_VERSION' ) )       define( 'JPRM_VERSION', '2.0.6' );
if ( ! defined( 'JPRM_PLUGIN_FILE' ) )   define( 'JPRM_PLUGIN_FILE', __FILE__ );
if ( ! defined( 'JPRM_PLUGIN_PATH' ) )   define( 'JPRM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'JPRM_PLUGIN_URL' ) )    define( 'JPRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------
 * Includes (explicit, fixed paths)
 * ------------------------------------------------- */

// Storage layer
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

// Data / Admin
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';

/** Admin menu bootstrap */
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menu.php';

// Renderer
require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

// Debug (admin-only shortcode)
require_once JPRM_PLUGIN_PATH . 'includes/debug/class-inspector.php';

// (Optional) Post save bridge for badges, if present
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php';
}

// Badges storage + admin screen (YOUR files)
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php';
}
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/admin/class-admin-dietary-badges.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-dietary-badges.php';
}

/* -------------------------------------------------
 * Class aliases (uploaded classes are global)
 * ------------------------------------------------- */
if ( class_exists( 'JPRM_Badges_Store' ) && ! class_exists( '\JelloPoint\RestaurantMenu\Badges\Store' ) ) {
	class_alias( 'JPRM_Badges_Store', '\JelloPoint\RestaurantMenu\Badges\Store' );
}
if ( class_exists( 'JPRM_Admin_Dietary_Badges' ) && ! class_exists( '\JelloPoint\RestaurantMenu\Admin\Dietary_Badges' ) ) {
	class_alias( 'JPRM_Admin_Dietary_Badges', '\JelloPoint\RestaurantMenu\Admin\Dietary_Badges' );
}

/* -------------------------------------------------
 * Partials & robust badge helpers (loaded early)
 * ------------------------------------------------- */
add_action( 'init', function () {
	// Make sure partials are available (and known to the Inspector)
	$partials = [
		JPRM_PLUGIN_PATH . 'includes/render/partials/badges.php',
		JPRM_PLUGIN_PATH . 'includes/render/partials/price-block.php',
	];
	foreach ( $partials as $file ) {
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	// Build a robust badge map that can read from multiple sources
	if ( ! function_exists( 'jprm_build_badge_map' ) ) {
		function jprm_build_badge_map() : array {
			$map = [ 'by_id' => [], 'by_slug' => [] ];

			// 1) Preferred: your storage class
			if ( class_exists( '\JelloPoint\RestaurantMenu\Badges\Store' ) ) {
				try {
					$store = new \JelloPoint\RestaurantMenu\Badges\Store();
					$rows  = [];
					if ( method_exists( $store, 'get_rows' ) ) {
						$rows = $store->get_rows();
					} elseif ( method_exists( $store, 'all' ) ) {
						$rows = $store->all();
					}
					if ( is_array( $rows ) ) {
						foreach ( $rows as $r ) {
							$id   = isset( $r['id'] )   ? (string) $r['id']   : '';
							$slug = isset( $r['slug'] ) ? (string) $r['slug'] : '';
							if ( $id !== '' )   $map['by_id'][ $id ]   = $r;
							if ( $slug !== '' ) $map['by_slug'][ $slug ] = $r;
						}
					}
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( '[JPRM] badge store read failed: '.$e->getMessage() );
				}
			}

			// 2) Fallback: option (common registry storage)
			if ( empty( $map['by_id'] ) && empty( $map['by_slug'] ) ) {
				$opt = get_option( 'jprm_badges_registry' ); // array of badge rows?
				if ( is_array( $opt ) ) {
					foreach ( $opt as $r ) {
						$id   = isset( $r['id'] )   ? (string) $r['id']   : '';
						$slug = isset( $r['slug'] ) ? (string) $r['slug'] : '';
						if ( $id !== '' )   $map['by_id'][ $id ]   = $r;
						if ( $slug !== '' ) $map['by_slug'][ $slug ] = $r;
					}
				}
			}

			return $map;
		}
	}

	// Render badges for a post, reading meta/tax and using the map above
	if ( ! function_exists( 'jprm_render_badges_html' ) ) {
		function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $map = null ) : string {
			if ( $post_id <= 0 ) return '';
			if ( ! $map ) $map = jprm_build_badge_map();

			// Collect attached badges (meta)
			$tokens = [];
			foreach ( [ 'jprm_badges', 'jprm_dietary_badges', 'dietary_badges' ] as $key ) {
				$raw = get_post_meta( $post_id, $key, true );
				if ( empty( $raw ) ) continue;
				if ( is_array( $raw ) ) {
					$tokens = array_merge( $tokens, array_map( 'strval', $raw ) );
				} elseif ( is_string( $raw ) ) {
					$tokens = array_merge( $tokens, array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
				}
			}

			// Collect attached badges (taxonomy), if exists
			if ( taxonomy_exists( 'jprm_badge' ) ) {
				$terms = wp_get_post_terms( $post_id, 'jprm_badge', [ 'fields' => 'all' ] );
				if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $t ) {
						if ( isset( $t->slug ) ) $tokens[] = (string) $t->slug;
					}
				}
			}

			$tokens = array_values( array_unique( array_filter( $tokens, fn( $t ) => $t !== '' ) ) );
			if ( empty( $tokens ) ) return '';

			$out = [];
			foreach ( $tokens as $token ) {
				$row = null;
				if ( is_numeric( $token ) && isset( $map['by_id'][ (string) (int) $token ] ) ) {
					$row = $map['by_id'][ (string) (int) $token ];
				}
				if ( ! $row && isset( $map['by_slug'][ (string) $token ] ) ) {
					$row = $map['by_slug'][ (string) $token ];
				}
				if ( ! $row ) continue;

				$label   = isset( $row['label'] ) ? (string) $row['label'] : '';
				$icon_id = isset( $row['icon'] )  ? (int) $row['icon']  : 0;

				$icon_html = '';
				if ( $icon_id > 0 ) {
					$img = wp_get_attachment_image( $icon_id, 'thumbnail', false, [ 'class' => 'jp-badge__icon', 'loading' => 'lazy' ] );
					if ( is_string( $img ) ) $icon_html = $img;
				}

				if ( $presentation === 'icon' ) {
					if ( $icon_html !== '' ) $out[] = '<span class="jp-badge">'.$icon_html.'</span>';
				} elseif ( $presentation === 'text' ) {
					if ( $label !== '' ) $out[] = '<span class="jp-badge"><span class="jp-badge__text">'.esc_html( $label ).'</span></span>';
				} else {
					$inner = $icon_html . ( $label !== '' ? '<span class="jp-badge__text">'.esc_html( $label ).'</span>' : '' );
					if ( $inner !== '' ) $out[] = '<span class="jp-badge">'.$inner.'</span>';
				}
			}

			if ( empty( $out ) ) return '';
			return '<span class="jp-badges jp-badges--' . esc_attr( $presentation ) . '">'. implode( '', $out ) .'</span>';
		}
	}
}, 1 );

/* -------------------------------------------------
 * JPRM Inspector – badges panel (if present)
 * ------------------------------------------------- */
if ( file_exists( __DIR__ . '/includes/debug/inspector-badges.php' ) ) {
	require_once __DIR__ . '/includes/debug/inspector-badges.php';
}

/* -------------------------------------------------
 * REST routes (front + admin)
 * ------------------------------------------------- */
add_action( 'rest_api_init', function () {
	if ( ! class_exists( '\JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller' ) ) return;
	$ctl = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
	$ctl->register_routes();
}, 10 );

/* -------------------------------------------------
 * Assets
 * ------------------------------------------------- */
function jprm_register_assets() {
	wp_register_style( 'jprm-menu', JPRM_PLUGIN_URL . 'includes/render/css/menu.css', [], JPRM_VERSION );
}
add_action( 'init', 'jprm_register_assets', 5 );

// Elementor editor preview styles
add_action( 'elementor/editor/after_enqueue_styles', function () {
	if ( ! wp_style_is( 'jprm-menu', 'registered' ) ) {
		wp_register_style( 'jprm-menu', JPRM_PLUGIN_URL . 'includes/render/css/menu.css', [], JPRM_VERSION );
	}
	wp_enqueue_style( 'jprm-menu' );
}, 10 );

/* -------------------------------------------------
 * CPT fallback
 * ------------------------------------------------- */
function jprm_register_cpt_fallback() {
	if ( post_type_exists( 'jprm_item' ) ) return;

	$parent_menu_slug = 'jellopoint';

	register_post_type( 'jprm_item', [
		'label'        => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
		'labels'       => [
			'name'          => __( 'Menu Items', 'jellopoint-restaurant-menu' ),
			'singular_name' => __( 'Menu Item', 'jellopoint-restaurant-menu' ),
			'add_new_item'  => __( 'Add Menu Item', 'jellopoint-restaurant-menu' ),
			'edit_item'     => __( 'Edit Menu Item', 'jellopoint-restaurant-menu' ),
		],
		'public'       => true,
		'show_ui'      => true,
		'show_in_menu' => $parent_menu_slug,
		'show_in_rest' => true,
		'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
		'has_archive'  => false,
		'rewrite'      => [ 'slug' => 'menu-item' ],
	] );
}
add_action( 'init', 'jprm_register_cpt_fallback', 3 );

function jprm_activate() { jprm_register_cpt_fallback(); flush_rewrite_rules(); }
register_activation_hook( __FILE__, 'jprm_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* -------------------------------------------------
 * Elementor integration
 * ------------------------------------------------- */
add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
	if ( method_exists( $elements_manager, 'add_category' ) ) {
		$elements_manager->add_category(
			'jellopoint-widgets',
			[ 'title' => __( 'JelloPoint', 'jellopoint-restaurant-menu' ), 'icon' => 'fa fa-plug' ]
		);
	}
}, 10 );

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	$widget_file = JPRM_PLUGIN_PATH . 'includes/widgets/class-restaurant-menu.php';
	if ( ! file_exists( $widget_file ) ) {
		error_log( '[JPRM] Widget file missing: ' . $widget_file );
		return;
	}
	require_once $widget_file;

	if ( class_exists( '\JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu' ) ) {
		$widgets_manager->register( new \JelloPoint\RestaurantMenu\Widgets\Restaurant_Menu() );
	} else {
		error_log( '[JPRM] Widget class not found after require_once.' );
	}
}, 10 );

/* -------------------------------------------------
 * Admin: Menu Builder + Sections + Menus etc.
 * ------------------------------------------------- */
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menu-builder.php';
require_once JPRM_PLUGIN_PATH . 'includes/rest/class-jprm-menu-builder-controller.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menu-item-list.php';
\JelloPoint\RestaurantMenu\Admin\Menu_Item_List::init();

require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-sections-admin.php';
\JelloPoint\RestaurantMenu\Admin\Sections_Admin::init();

require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-menus-admin.php';
\JelloPoint\RestaurantMenu\Admin\Menus_Admin::init();

require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-sections-ux.php';
\JelloPoint\RestaurantMenu\Admin\Sections_UX::init();

require_once JPRM_PLUGIN_PATH . 'includes/admin/class-jprm-items-list-filters.php';
\JelloPoint\RestaurantMenu\Admin\Items_List_Filters::init();

/* Register Menu Builder hooks on admin only */
add_action( 'plugins_loaded', function () {
	if ( ! is_admin() ) return;

	$builder = new \JelloPoint\RestaurantMenu\Admin\Menu_Builder();
	$builder->hooks();

	add_action( 'rest_api_init', function() {
		$ctl = new \JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
		$ctl->register_routes();
	} );
}, 30 );

/* -------------------------------------------------
 * Optional: core plugin bootstrap
 * ------------------------------------------------- */
if ( class_exists( '\JelloPoint\RestaurantMenu\Plugin' ) ) {
	if ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'instance' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::instance();
	} elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'get_instance' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::get_instance();
	} elseif ( is_callable( [ '\JelloPoint\RestaurantMenu\Plugin', 'init' ] ) ) {
		\JelloPoint\RestaurantMenu\Plugin::init();
	}
}

/**
 * IMPORTANT: We intentionally DO NOT add the "Dietary Badges" submenu here anymore,
 * to avoid duplicates. Your existing Admin class should handle creating that screen.
 * If you still don't see the menu, the Admin class can call add_submenu_page itself.
 */
