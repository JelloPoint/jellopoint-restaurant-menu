<?php
/**
 * JelloPoint Restaurant Menu – Admin: Menu Item > Dietary Badges Metabox
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_MenuItem_Badges_Meta' ) ) :

class JPRM_MenuItem_Badges_Meta {

	const POST_TYPE    = 'jprm_menu_item';
	const META_KEY     = 'jprm_item_badges';    // array of badge slugs
	const NONCE_NAME   = 'jprm_item_badges_nonce';
	const NONCE_ACTION = 'jprm_item_badges_save';

	/** @var JPRM_Badges_Store */
	private $store;

	public function __construct( $store ) {
		$this->store = $store;
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
	}

	public function add_metabox() : void {
		// Put it in the LEFT column.
		add_meta_box(
			'jprm_item_badges',
			__( 'Dietary Badges', 'jprm' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'normal',    // <- main/left column
			'high'       // try to float near the top (we’ll refine via order filter in bootstrap)
		);
	}

	public function render_metabox( $post ) : void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		// Show ALL badges (not only active), so you always see what was saved.
		$rows    = method_exists( $this->store, 'all' ) ? $this->store->all() : [];
		$map_all = [];
		foreach ( $rows as $r ) { $map_all[ $r['slug'] ] = $r; }

		$checked = $this->get_selected_slugs( (int) $post->ID );

		// Minimal inline CSS for compact chip layout.
		echo '<style>
		#jprm_item_badges ul{margin:0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px}
		#jprm_item_badges li{margin:0;padding:0}
		#jprm_item_badges label{display:flex;align-items:center;gap:6px;border:1px solid #dcdcde;border-radius:6px;padding:6px 8px;background:#fff;cursor:pointer}
		#jprm_item_badges img{width:18px;height:18px;border-radius:3px;object-fit:cover}
		#jprm_item_badges .ph{width:18px;height:18px;display:inline-block;background:#f2f2f2;border-radius:3px}
		#jprm_item_badges input[type=checkbox]{margin:0}
		#jprm_item_badges .inactive{opacity:.55}
		#jprm_item_badges .help{margin-top:6px;color:#646970}
		</style>';

		if ( empty( $map_all ) ) {
			echo '<p class="description">'.esc_html__( 'No dietary badges defined yet. Add them via JelloPoint → Dietary Badges.', 'jprm' ).'</p>';
			return;
		}

		echo '<div id="jprm_item_badges"><ul>';
		foreach ( $map_all as $slug => $row ) {
			$is_checked = in_array( $slug, $checked, true );
			$is_active  = ! empty( $row['active'] );

			echo '<li><label class="'. ( $is_active ? '' : 'inactive' ) .'">';
			if ( ! empty( $row['icon_url'] ) ) {
				echo '<img src="' . esc_url( $row['icon_url'] ) . '" alt="" />';
			} else {
				echo '<span class="ph"></span>';
			}
			echo '<span>' . esc_html( $row['name'] ) . '</span>';
			echo '<input type="checkbox" name="jprm_item_badges[]" value="' . esc_attr( $slug ) . '"' . checked( $is_checked, true, false ) . ' />';
			echo '</label></li>';
		}
		echo '</ul>';

		echo '<p class="help">'.esc_html__( 'Inactive badges are dimmed but remain selectable to preserve existing data.', 'jprm' ).'</p>';
		echo '</div>';
	}

	private function get_selected_slugs( int $post_id ) : array {
		$val = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $val ) ) $val = [];
		$val = array_values( array_unique( array_map( 'sanitize_title', $val ) ) );
		return $val;
	}

	public function save_post( $post_id, $post ) : void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) return;
		if ( ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) return;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$incoming = isset($_POST['jprm_item_badges']) && is_array($_POST['jprm_item_badges'])
			? array_values( array_unique( array_map( 'sanitize_title', $_POST['jprm_item_badges'] ) ) )
			: [];

		update_post_meta( $post_id, self::META_KEY, $incoming );
	}
}

endif;
