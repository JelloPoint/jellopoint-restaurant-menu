<?php
namespace JelloPoint\RestaurantMenu\Render;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Outputs the standalone print-preview document shared by future PDF generation. */
final class Print_Document_Renderer {
	public static function render( array $document ) : void {
		$template = JPRM_PLUGIN_PATH . 'includes/render/print/document.php';
		if ( ! is_file( $template ) ) { wp_die( esc_html__( 'Print template not found.', 'jellopoint-restaurant-menu' ) ); }
		include $template;
	}

	public static function price_html( array $price ) : string {
		if ( ! $price ) { return ''; }
		$rows = [];
		if ( 'multi' === (string) ( $price['mode'] ?? '' ) ) {
			$rows = isset( $price['rows'] ) && is_array( $price['rows'] ) ? $price['rows'] : [];
		} elseif ( '' !== (string) ( $price['price'] ?? '' ) ) {
			$rows[] = [ 'value' => (string) $price['price'], 'label' => $price['label'] ?? [], 'hide_icon' => $price['hide_icon'] ?? false, 'icon_id' => $price['icon_id'] ?? 0 ];
		}
		$out = '';
		foreach ( $rows as $row ) {
			$value = (string) ( $row['value'] ?? '' );
			if ( '' === $value ) { continue; }
			$label = isset( $row['label'] ) && is_array( $row['label'] ) ? $row['label'] : [];
			$icon_url = (string) ( $label['icon_url'] ?? '' );
			$icon_id = (int) ( $row['icon_id'] ?? ( $label['icon_id'] ?? 0 ) );
			if ( $icon_id > 0 ) { $resolved = wp_get_attachment_image_url( $icon_id, 'thumbnail' ); if ( is_string( $resolved ) ) { $icon_url = $resolved; } }
			$out .= '<span class="jprm-print-price">';
			if ( empty( $row['hide_icon'] ) && '' !== $icon_url ) { $out .= '<img src="' . esc_url( $icon_url ) . '" alt="" />'; }
			if ( '' !== (string) ( $label['label_text'] ?? '' ) ) { $out .= '<span class="jprm-print-price-label">' . esc_html( (string) $label['label_text'] ) . '</span>'; }
			$out .= '<strong>€' . esc_html( $value ) . '</strong></span>';
		}
		return $out;
	}

	public static function badges_html( array $badges ) : string {
		$out = '';
		foreach ( $badges as $badge ) {
			$out .= '<span class="jprm-print-badge">';
			if ( '' !== (string) ( $badge['icon_url'] ?? '' ) ) { $out .= '<img src="' . esc_url( (string) $badge['icon_url'] ) . '" alt="" />'; }
			$out .= '<span>' . esc_html( (string) ( $badge['name'] ?? '' ) ) . '</span></span>';
		}
		return $out;
	}

	public static function render_info_blocks( array $blocks ) : void {
		foreach ( $blocks as $block ) {
			echo '<aside class="jprm-print-info-block">';
			$image = isset( $block['image'] ) && is_array( $block['image'] ) ? (string) ( $block['image']['url'] ?? '' ) : '';
			if ( '' !== $image ) { echo '<img src="' . esc_url( $image ) . '" alt="" />'; }
			echo '<div>' . wp_kses_post( (string) ( $block['content_html'] ?? '' ) ) . '</div></aside>';
		}
	}
}
