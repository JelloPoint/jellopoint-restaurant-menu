<?php
/**
 * JelloPoint Restaurant Menu – Admin: Menu Item > Dietary Badges Meta Box
 *
 * Displays active badges from the store as horizontal chips.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_MenuItem_Badges_Meta' ) ) :

class JPRM_MenuItem_Badges_Meta {

	const POST_TYPE     = 'jprm_menu_item';
	const META_KEY      = 'jprm_item_badges_v1';
	const NONCE_NAME    = 'jprm_item_badges_nonce';
	const NONCE_ACTION  = 'jprm_item_badges_save';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ], 20 );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );

		// Load scoped CSS for this screen only
		add_action( 'load-post.php',     [ $this, 'enqueue_css' ] );
		add_action( 'load-post-new.php', [ $this, 'enqueue_css' ] );
	}

	public function register_metabox() {
		add_meta_box(
			'jprm_menu_item_badges',
			__( 'Dietary Badges', 'jprm' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'normal',
			'low'
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}

		$badges = [];
		foreach ( $this->store->get_rows() as $r ) {
			if ( empty( $r['active'] ) ) continue;
			$name = isset( $r['name'] ) ? trim( $r['name'] ) : '';
			if ( $name === '' ) continue;
			$badges[] = [
				'slug'     => sanitize_title( $name ),
				'name'     => $name,
				'icon_url' => isset( $r['icon_url'] ) ? (string) $r['icon_url'] : '',
			];
		}

		echo '<div class="jprm-badges-meta jprm-badges-horizontal">';
		if ( empty( $badges ) ) {
			echo '<p>' . esc_html__( 'No active badges found. Configure them under “Dietary Badges”.', 'jprm' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<ul class="jprm-badges-list">';
		foreach ( $badges as $b ) {
			$checked = in_array( $b['slug'], $selected, true );
			echo '<li class="jprm-badge-li">';
			echo '  <label class="jprm-badge-label">';
			if ( $b['icon_url'] ) {
				echo '    <img src="' . esc_url( $b['icon_url'] ) . '" alt="" class="jprm-badge-icon" />';
			} else {
				echo '    <span class="dashicons dashicons-format-image jprm-badge-placeholder" aria-hidden="true"></span>';
			}
			echo '    <span class="jprm-badge-name">' . esc_html( $b['name'] ) . '</span>';
			echo '    <input type="checkbox" name="jprm_item_badges[]" value="' . esc_attr( $b['slug'] ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '  </label>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	public function save_post( $post_id, $post ) {
		if (
			! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION )
		) return;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( self::POST_TYPE !== $post->post_type ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$input = isset( $_POST['jprm_item_badges'] ) ? (array) $_POST['jprm_item_badges'] : [];
		$clean = [];

		$valid = [];
		foreach ( $this->store->get_rows() as $r ) {
			if ( ! empty( $r['active'] ) && ! empty( $r['name'] ) ) {
				$valid[] = sanitize_title( (string) $r['name'] );
			}
		}

		foreach ( $input as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( in_array( $slug, $valid, true ) ) {
				$clean[] = $slug;
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, array_values( array_unique( $clean ) ) );
		}
	}

	/** Load compact CSS for badges layout */
	public function enqueue_css() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::POST_TYPE ) return;

		$h = 'jprm-badges-horizontal-style';
		wp_register_style( $h, false );
		wp_enqueue_style( $h );
		wp_add_inline_style( $h, $this->inline_css() );
	}

	protected function inline_css() : string {
		return '
		#jprm_menu_item_badges .inside {
			margin:0 !important;
			padding:8px 12px !important;
		}
		#jprm_menu_item_badges .jprm-badges-list {
			display:flex !important;
			flex-wrap:wrap !important;
			gap:8px !important;
			list-style:none !important;
			margin:0 !important;
			padding:0 !important;
		}
		#jprm_menu_item_badges .jprm-badge-li {
			background:#fff !important;
			border:1px solid #dcdcde !important;
			border-radius:6px !important;
			padding:6px 10px !important;
			display:flex !important;
			align-items:center !important;
		}
		#jprm_menu_item_badges .jprm-badge-label {
			display:flex !important;
			align-items:center !important;
			gap:8px !important;
			margin:0 !important;
			font-size:13px !important;
			line-height:1.4 !important;
		}
		#jprm_menu_item_badges .jprm-badge-icon {
			width:18px !important;
			height:18px !important;
			object-fit:contain !important;
			border:1px solid #ccd0d4 !important;
			border-radius:3px !important;
			background:#fff !important;
		}
		#jprm_menu_item_badges .jprm-badge-placeholder {
			color:#000 !important;
			opacity:1 !important;
			font-size:16px !important;
		}
		#jprm_menu_item_badges .jprm-badge-name {
			font-weight:500 !important;
		}
		#jprm_menu_item_badges input[type=checkbox] {
			margin-left:6px !important;
		}
		';
	}
}

endif;
