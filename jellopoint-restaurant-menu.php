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
 * Core includes (explicit paths)
 * ------------------------------------------------- */

// Storage layer
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-schema.php';
require_once JPRM_PLUGIN_PATH . 'includes/storage/class-price-repository.php';

// Data / Admin
require_once JPRM_PLUGIN_PATH . 'includes/data/class-labels-store.php';
require_once JPRM_PLUGIN_PATH . 'includes/class-plugin.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menuitem-meta.php';
require_once JPRM_PLUGIN_PATH . 'includes/admin/save/class-menuitem-v3-writer.php';

// Admin menu container (if present)
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menu.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-menu.php';
}

// Renderer
require_once JPRM_PLUGIN_PATH . 'includes/render/class-price-renderer.php';

// Debug (admin-only)
require_once JPRM_PLUGIN_PATH . 'includes/debug/class-inspector.php';

// Badges saving bridge (if present)
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/admin/badges-post-bootstrap.php';
}

// Your badges files
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/data/class-badges-store.php';
}
if ( file_exists( JPRM_PLUGIN_PATH . 'includes/admin/class-admin-dietary-badges.php' ) ) {
	require_once JPRM_PLUGIN_PATH . 'includes/admin/class-admin-dietary-badges.php';
}

/* -------------------------------------------------
 * Partials (load immediately so helpers exist now)
 * ------------------------------------------------- */
foreach ( [
	JPRM_PLUGIN_PATH . 'includes/render/partials/badges.php',
	JPRM_PLUGIN_PATH . 'includes/render/partials/price-block.php',
] as $file ) {
	if ( file_exists( $file ) ) require_once $file;
}

/* -------------------------------------------------
 * Utility: resolve/alias class names
 * ------------------------------------------------- */
if ( ! function_exists( 'jprm_resolve_class' ) ) {
	function jprm_resolve_class( array $candidates ) : ?string {
		foreach ( $candidates as $fqcn ) {
			if ( class_exists( $fqcn ) ) return $fqcn;
		}
		return null;
	}
}

/* If your classes are global, alias them to the namespaced
 * identifiers the Inspector expects, so “Classes: FOUND”. */
if ( class_exists( 'JPRM_Admin_Dietary_Badges' ) && ! class_exists( '\JelloPoint\RestaurantMenu\Admin\Dietary_Badges' ) ) {
	class_alias( 'JPRM_Admin_Dietary_Badges', '\JelloPoint\RestaurantMenu\Admin\Dietary_Badges' );
}
if ( class_exists( 'JPRM_Badges_Store' ) && ! class_exists( '\JelloPoint\RestaurantMenu\Badges\Store' ) ) {
	class_alias( 'JPRM_Badges_Store', '\JelloPoint\RestaurantMenu\Badges\Store' );
}

/* -------------------------------------------------
 * BADGES: map + render helpers (at load time)
 * ------------------------------------------------- */
if ( ! function_exists( 'jprm__get_store_rows' ) ) {
	function jprm__get_store_rows() : array {
		// Try namespaced store first, then global
		$store_fqcn = jprm_resolve_class( [
			'\JelloPoint\RestaurantMenu\Badges\Store',
			'JPRM_Badges_Store',
		] );
		if ( $store_fqcn ) {
			try {
				$store = new $store_fqcn();
				foreach ( [ 'get_rows', 'all', 'get_all', 'list' ] as $m ) {
					if ( method_exists( $store, $m ) ) {
						$rows = $store->{$m}();
						return is_array( $rows ) ? $rows : [];
					}
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( '[JPRM] badge store read failed: '.$e->getMessage() );
			}
		}
		// Fallback: option registry
		$opt = get_option( 'jprm_badges_registry' );
		return is_array( $opt ) ? $opt : [];
	}
}

if ( ! function_exists( 'jprm_build_badge_map' ) ) {
	function jprm_build_badge_map() : array {
		$rows = jprm__get_store_rows();
		$map  = [ 'by_id' => [], 'by_slug' => [], 'by_name' => [] ];

		$i = 0;
		foreach ( $rows as $row ) {
			$i++;
			// Normalize field names from your admin table:
			$id      = isset( $row['id'] ) ? (string) $row['id'] : (string) $i;
			$name    = isset( $row['name'] ) ? (string) $row['name'] : '';
			$slug    = isset( $row['slug'] ) ? (string) $row['slug'] : sanitize_title( $name );
			$icon_id = isset( $row['icon_id'] ) ? (int) $row['icon_id'] : ( isset( $row['icon'] ) ? (int) $row['icon'] : 0 );
			$icon    = $icon_id; // keep as int id
			$active  = ! empty( $row['active'] );

			$rec = compact( 'id','name','slug','icon','active' );
			if ( $id !== '' )             $map['by_id'][ $id ]     = $rec;
			if ( $slug !== '' )           $map['by_slug'][ $slug ] = $rec;
			if ( $name !== '' )           $map['by_name'][ $name ] = $rec;
		}
		return $map;
	}
}

if ( ! function_exists( 'jprm_render_badges_html' ) ) {
	function jprm_render_badges_html( int $post_id, string $presentation = 'icon_text', string $position = 'before', ?array $map = null ) : string {
		if ( $post_id <= 0 ) return '';
		if ( ! $map ) $map = jprm_build_badge_map();

		// 1) read post meta: support common keys, array or comma string
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

		// 2) optional taxonomy
		if ( taxonomy_exists( 'jprm_badge' ) ) {
			$terms = wp_get_post_terms( $post_id, 'jprm_badge', [ 'fields' => 'all' ] );
			if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					if ( isset( $t->slug ) ) $tokens[] = (string) $t->slug;
				}
			}
		}

		$tokens = array_values( array_unique( array_filter( $tokens, 'strlen' ) ) );
		if ( empty( $tokens ) ) return '';

		$out = [];
		foreach ( $tokens as $token ) {
			$row = null;

			// Try id → slug → name
			if ( is_numeric( $token ) && isset( $map['by_id'][ (string) (int) $token ] ) ) {
				$row = $map['by_id'][ (string) (int) $token ];
			}
			if ( ! $row ) {
				$slug = sanitize_title( (string) $token );
				if ( isset( $map['by_slug'][ $slug ] ) ) $row = $map['by_slug'][ $slug ];
			}
			if ( ! $row && isset( $map['by_name'][ (string) $token ] ) ) {
				$row = $map['by_name'][ (string) $token ];
			}

			// Render a visible fallback for troubleshooting
			if ( ! $row ) {
				$out[] = '<span class="jp-badge"><span class="jp-badge__text">'. esc_html( (string) $token ) .'</span></span>';
				continue;
			}
			if ( empty( $row['active'] ) ) continue; // skip inactive

			$label   = (string) ( $row['name'] ?? '' );
			$icon_id = (int) ( $row['icon'] ?? 0 );

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

/* -------------------------------------------------
 * Admin menu wiring for Dietary Badges (no duplicates)
 * ------------------------------------------------- */
add_action( 'plugins_loaded', function () {
	// Initialize store (if it has static init())
	$store_fqcn = jprm_resolve_class( [
		'\JelloPoint\RestaurantMenu\Badges\Store',
		'JPRM_Badges_Store',
	] );
	if ( $store_fqcn && method_exists( $store_fqcn, 'init' ) ) {
		call_user_func( [ $store_fqcn, 'init' ] );
	}

	if ( is_admin() ) {
		// Initialize parent admin menu if present
		if ( class_exists( '\JelloPoint\RestaurantMenu\Admin\Admin_Menu' ) && method_exists( '\JelloPoint\RestaurantMenu\Admin\Admin_Menu', 'init' ) ) {
			\JelloPoint\RestaurantMenu\Admin\Admin_Menu::init();
		}

		// Build and register the Dietary Badges submenu
		$admin_fqcn = jprm_resolve_class( [
			'\JelloPoint\RestaurantMenu\Admin\Dietary_Badges', // alias provided above if needed
			'JPRM_Admin_Dietary_Badges',
		] );

		if ( $admin_fqcn ) {
			// Prepare a store instance to pass into the admin screen
			$store_obj = null;
			if ( $store_fqcn ) {
				try { $store_obj = new $store_fqcn(); } catch ( \Throwable $e ) {}
			}
			// Instantiate admin screen
			try {
				$GLOBALS['jprm_dietary_badges_admin'] = new $admin_fqcn( $store_obj );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( '[JPRM] Failed to instantiate Dietary Badges admin: '.$e->getMessage() );
			}

			// Add submenu under parent "jellopoint" only if not already present
			add_action( 'admin_menu', function() {
				$parent_slug = 'jellopoint'; // parent menu root
				$page_title  = __( 'Dietary Badges', 'jprm' );
				$menu_title  = __( 'Dietary Badges', 'jprm' );
				$capability  = 'manage_options';

				$slug = defined( 'JPRM_Admin_Dietary_Badges::PAGE_SLUG' )
					? JPRM_Admin_Dietary_Badges::PAGE_SLUG
					: ( defined( '\JelloPoint\RestaurantMenu\Admin\Dietary_Badges::PAGE_SLUG' )
						? \JelloPoint\RestaurantMenu\Admin\Dietary_Badges::PAGE_SLUG
						: 'jprm-dietary-badges' );

				global $submenu;
				if ( isset( $submenu[ $parent_slug ] ) ) {
					foreach ( $submenu[ $parent_slug ] as $item ) {
						if ( isset( $item[2] ) && $item[2] === $slug ) {
							return; // already added somewhere else
						}
					}
				}

				//add_submenu_page(
					$parent_slug,
					$page_title,
					$menu_title,
					$capability,
					$slug,
					function() {
						if ( isset( $GLOBALS['jprm_dietary_badges_admin'] ) && method_exists( $GLOBALS['jprm_dietary_badges_admin'], 'render_page' ) ) {
							$GLOBALS['jprm_dietary_badges_admin']->render_page();
						} else {
							echo '<div class="wrap"><h1>'.esc_html__( 'Dietary Badges', 'jprm' ).'</h1><p>'.esc_html__( 'Screen could not be loaded. Missing classes.', 'jprm' ).'</p></div>';
						}
					},
					22
				);
			}, 60 );
		}
	}
}, 20);

/* -------------------------------------------------
 * Inspector add-on (if present)
 * ------------------------------------------------- */
if ( file_exists( __DIR__ . '/includes/debug/inspector-badges.php' ) ) {
	require_once __DIR__ . '/includes/debug/inspector-badges.php';
}

/* -------------------------------------------------
 * REST routes
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
