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

	/** @var JPRM_Badges_Store|null */
	private $store;

	public function __construct( $store = null ) {
		$this->store = $store;
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
	}

	public function add_metabox() : void {
		add_meta_box(
			'jprm_item_badges',
			__( 'Dietary Badges', 'jprm' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'normal',   // left/main column
			'default'   // default priority so it can sit between Pricing and Visibility
		);
	}

	public function render_metabox( $post ) : void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$rows = get_option( 'jprm_dietary_badges', [] );
		if ( ! is_array( $rows ) ) $rows = [];

		// Normalize + index by slug.
		$map_all = [];
		foreach ( $rows as $i => $r ) {
			$name     = isset( $r['name'] ) ? (string) $r['name'] : '';
			if ( $name === '' ) { continue; }
			$slug     = ! empty( $r['slug'] ) ? sanitize_title( $r['slug'] ) : sanitize_title( $name );
			$icon_id  = isset( $r['icon_id'] ) ? (int) $r['icon_id'] : 0;
			$icon_url = isset( $r['icon_url'] ) ? (string) $r['icon_url'] : '';
			$active   = array_key_exists( 'active', $r ) ? (bool) $r['active'] : true;
			$order    = isset( $r['order'] ) ? (int) $r['order'] : $i;

			$map_all[ $slug ] = [
				'slug'     => $slug,
				'name'     => $name,
				'icon_id'  => $icon_id,
				'icon_url' => $icon_url,
				'active'   => $active,
				'order'    => $order,
			];
		}

		uasort( $map_all, function( $a, $b ) {
			return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
		});

		$checked = $this->get_selected_slugs( (int) $post->ID );

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
		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) return;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$incoming = isset( $_POST['jprm_item_badges'] ) && is_array( $_POST['jprm_item_badges'] )
			? array_values( array_unique( array_map( 'sanitize_title', wp_unslash( $_POST['jprm_item_badges'] ) ) ) )
			: [];

		update_post_meta( $post_id, self::META_KEY, $incoming );
	}
}

endif;
