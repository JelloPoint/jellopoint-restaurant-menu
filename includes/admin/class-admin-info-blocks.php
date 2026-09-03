<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Plain Menu Item-style editing screen for reusable Info Blocks. */
final class Info_Blocks_Admin {
	private const POST_TYPE = 'jprm_info_block';
	private const META_KEY = 'jprm_info_block_content';
	private const NONCE_ACTION = 'jprm_save_info_block';
	private const NONCE_FIELD = '_jprm_info_block_nonce';

	public static function init() : void {
		add_action( 'add_meta_boxes_' . self::POST_TYPE, [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save' ], 10, 2 );
	}

	public static function add_meta_boxes() : void {
		add_meta_box( 'jprm_info_block_content', __( 'Info Block Content', 'jellopoint-restaurant-menu' ), [ __CLASS__, 'render_content' ], self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_content( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$content = get_post_meta( $post->ID, self::META_KEY, true );
		if ( '' === (string) $content ) { $content = $post->post_content; }
		wp_editor( (string) $content, 'jprm_info_block_editor', [
			'textarea_name' => 'jprm_info_block_content',
			'media_buttons' => true,
			'tinymce' => true,
			'quicktags' => true,
			'textarea_rows' => 10,
		] );
		 echo '<p class="description">' . esc_html__( 'This reusable content can be placed above or below a Section in Menu Builder.', 'jellopoint-restaurant-menu' ) . '</p>';
	}

	public static function save( int $post_id, \WP_Post $post ) : void {
		if ( self::POST_TYPE !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) { return; }
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$content = isset( $_POST['jprm_info_block_content'] ) ? wp_kses_post( wp_unslash( $_POST['jprm_info_block_content'] ) ) : '';
		if ( '' === trim( $content ) ) { delete_post_meta( $post_id, self::META_KEY ); }
		else { update_post_meta( $post_id, self::META_KEY, $content ); }
	}
}
