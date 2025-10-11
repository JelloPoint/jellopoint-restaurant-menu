<?php
/**
 * JelloPoint Restaurant Menu – Admin: Menu Item > Dietary Badges Meta Box
 *
 * Safe, isolated meta box:
 * - Adds a "Dietary Badges" panel to jprm_menu_item edit screen
 * - No global JS; only simple HTML checkboxes (no drag/drop)
 * - Uses store to list active badges in the same order
 * - Saves selected badge slugs to a namespaced meta key
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_MenuItem_Badges_Meta' ) ) :

class JPRM_MenuItem_Badges_Meta {

	/** Post type */
	const POST_TYPE = 'jprm_menu_item';

	/** Meta key on the menu item (array of slugs) */
	const META_KEY  = 'jprm_item_badges_v1';

	/** Nonce */
	const NONCE_NAME   = 'jprm_item_badges_nonce';
	const NONCE_ACTION = 'jprm_item_badges_save';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;

		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ], 20 );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_action( 'load-post.php', [ $this, 'maybe_enqueue_css' ] );
		add_action( 'load-post-new.php', [ $this, 'maybe_enqueue_css' ] );
	}

	public function register_metabox() {
		add_meta_box(
			'jprm_menu_item_badges',
			__( 'Dietary Badges', 'jprm' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}

		$rows = $this->store->get_rows(); // already ordered
		// Build a list of active badges only
		$badges = [];
		foreach ( $rows as $r ) {
			$active = ! empty( $r['active'] );
			$name   = isset( $r['name'] ) ? (string) $r['name'] : '';
			if ( $active && $name !== '' ) {
				$slug = sanitize_title( $name ); // derive stable slug from name
				$badges[] = [
					'slug'     => $slug,
					'name'     => $name,
					'icon_url' => isset( $r['icon_url'] ) ? (string) $r['icon_url'] : '',
				];
			}
		}

		echo '<div class="jprm-badges-meta">';

		if ( empty( $badges ) ) {
			echo '<p>' . esc_html__( 'No active badges found. Configure them under “Dietary Badges”.', 'jprm' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<ul class="jprm-badges-list">';
		foreach ( $badges as $b ) {
			$slug     = $b['slug'];
			$name     = $b['name'];
			$icon_url = $b['icon_url'];
			$checked  = in_array( $slug, $selected, true );

			echo '<li class="jprm-badge-li">';
			echo '<label class="jprm-badge-label">';
			if ( $icon_url ) {
				echo '<img src="' . esc_url( $icon_url ) . '" alt="" class="jprm-badge-icon" />';
			} else {
				// Black placeholder to match Labels look
				echo '<span class="dashicons dashicons-format-image jprm-badge-placeholder" aria-hidden="true"></span>';
			}
			echo '<span class="jprm-badge-name">' . esc_html( $name ) . '</span>';
			echo '<span class="jprm-badge-check">';
			echo '<input type="checkbox" name="jprm_item_badges[]" value="' . esc_attr( $slug ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '</span>';
			echo '</label>';
			echo '</li>';
		}
		echo '</ul>';

		echo '</div>';
	}

	/**
	 * Save handler for our meta.
	 */
	public function save_post( $post_id, $post ) {
		// Basic checks
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['jprm_item_badges'] ) ? (array) $_POST['jprm_item_badges'] : [];
		$clean = [];

		// Only allow slugs that exist in the current active set
		$valid_slugs = [];
		foreach ( $this->store->get_rows() as $r ) {
			if ( ! empty( $r['active'] ) && ! empty( $r['name'] ) ) {
				$valid_slugs[] = sanitize_title( (string) $r['name'] );
			}
		}

		foreach ( $input as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( in_array( $slug, $valid_slugs, true ) ) {
				$clean[] = $slug;
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, array_values( array_unique( $clean ) ) );
		}
	}

	/**
	 * Tiny CSS scoped to our metabox; no global styles.
	 */
	public function maybe_enqueue_css() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== self::POST_TYPE ) {
			return;
		}
		$h = 'jprm-badges-meta-inline';
		wp_register_style( $h, false );
		wp_enqueue_style( $h );
		wp_add_inline_style( $h, $this->inline_css() );
	}

	protected function inline_css() : string {
		return '
		#jprm_menu_item_badges .inside { margin:0; padding:0; }
		.jprm-badges-list { list-style:none; margin:0; padding:8px; display:flex; flex-direction:column; gap:6px; }
		.jprm-badge-li { margin:0; }
		.jprm-badge-label { display:flex; align-items:center; gap:8px; }
		.jprm-badge-icon { width:20px; height:20px; object-fit:contain; border:1px solid #ccd0d4; border-radius:3px; background:#fff; }
		.jprm-badge-placeholder { color:#000; opacity:1; font-size:16px; }
		.jprm-badge-name { flex:1; }
		.jprm-badge-check input { vertical-align:middle; }
		';
	}
}

endif;
