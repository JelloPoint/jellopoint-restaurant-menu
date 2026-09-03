<?php
use JelloPoint\RestaurantMenu\Render\Print_Document_Renderer;

if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var array $document Template data supplied by Print_Document_Renderer. */
$settings = $document['settings'];
$menu = $document['menu'];
$preset = (string) $settings['preset'];
$margins = $settings['margins'];
$daily = $menu['daily'];
$logo_url = (int) $settings['logo_id'] > 0 ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'large' ) : '';
$visibility_classes = [];
foreach ( [ 'show_descriptions' => 'descriptions', 'show_price_labels' => 'price-labels', 'show_price_icons' => 'price-icons', 'show_badges' => 'badges', 'show_info_blocks' => 'info-blocks' ] as $setting => $class_name ) {
	if ( empty( $settings[ $setting ] ) ) { $visibility_classes[] = 'jprm-print--hide-' . $class_name; }
}
$date_text = '';
if ( ! empty( $daily['enabled'] ) && 'none' !== (string) $daily['date_type'] ) {
	$date_text = (string) $daily['start_date'];
	if ( '' !== (string) $daily['end_date'] ) { $date_text .= ' – ' . (string) $daily['end_date']; }
}
?><!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( (string) $menu['name'] ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( JPRM_PLUGIN_URL . 'assets/css/print-document.css?ver=' . JPRM_VERSION ); ?>" />
	<style>:root{--jprm-page-top:<?php echo esc_attr( (string) $margins['top'] ); ?>mm;--jprm-page-right:<?php echo esc_attr( (string) $margins['right'] ); ?>mm;--jprm-page-bottom:<?php echo esc_attr( (string) $margins['bottom'] ); ?>mm;--jprm-page-left:<?php echo esc_attr( (string) $margins['left'] ); ?>mm;--jprm-columns:<?php echo (int) $settings['columns']; ?>;--jprm-text:<?php echo esc_attr( (string) $settings['text_color'] ); ?>;--jprm-accent:<?php echo esc_attr( (string) $settings['accent_color'] ); ?>;--jprm-background:<?php echo esc_attr( (string) $settings['background_color'] ); ?>;--jprm-title-size:<?php echo esc_attr( (string) $settings['title_size'] ); ?>pt;--jprm-section-size:<?php echo esc_attr( (string) $settings['section_size'] ); ?>pt;--jprm-item-size:<?php echo esc_attr( (string) $settings['item_size'] ); ?>pt;--jprm-description-size:<?php echo esc_attr( (string) $settings['description_size'] ); ?>pt;--jprm-section-spacing:<?php echo esc_attr( (string) $settings['section_spacing'] ); ?>mm;--jprm-item-spacing:<?php echo esc_attr( (string) $settings['item_spacing'] ); ?>mm;--jprm-header-align:<?php echo esc_attr( (string) $settings['header_alignment'] ); ?>;--jprm-section-align:<?php echo esc_attr( (string) $settings['section_alignment'] ); ?>}@page{size:A4 <?php echo esc_html( (string) $settings['orientation'] ); ?>;margin:<?php echo esc_html( (string) $margins['top'] ); ?>mm <?php echo esc_html( (string) $margins['right'] ); ?>mm <?php echo esc_html( (string) $margins['bottom'] ); ?>mm <?php echo esc_html( (string) $margins['left'] ); ?>mm}</style>
	<style>:root{--jprm-menu-border:<?php echo esc_attr( (string) $settings['menu_border_width'] ); ?>px <?php echo esc_attr( (string) $settings['menu_border_style'] ); ?> <?php echo esc_attr( (string) $settings['menu_border_color'] ); ?>;--jprm-menu-radius:<?php echo esc_attr( (string) $settings['menu_border_radius'] ); ?>mm;--jprm-section-border:<?php echo esc_attr( (string) $settings['section_border_width'] ); ?>px <?php echo esc_attr( (string) $settings['section_border_style'] ); ?> <?php echo esc_attr( (string) $settings['section_border_color'] ); ?>;--jprm-section-radius:<?php echo esc_attr( (string) $settings['section_border_radius'] ); ?>mm;--jprm-section-padding:<?php echo esc_attr( (string) $settings['section_border_padding'] ); ?>mm;--jprm-info-text:<?php echo esc_attr( (string) $settings['info_block_text_color'] ); ?>;--jprm-info-background:<?php echo esc_attr( (string) $settings['info_block_background_color'] ); ?>;--jprm-info-align:<?php echo esc_attr( (string) $settings['info_block_alignment'] ); ?>;--jprm-info-size:<?php echo esc_attr( (string) $settings['info_block_font_size'] ); ?>pt;--jprm-info-image-width:<?php echo esc_attr( (string) $settings['info_block_image_width'] ); ?>mm;--jprm-info-padding:<?php echo esc_attr( (string) $settings['info_block_padding'] ); ?>mm;--jprm-info-spacing:<?php echo esc_attr( (string) $settings['info_block_spacing'] ); ?>mm;--jprm-info-border:<?php echo esc_attr( (string) $settings['info_block_border_width'] ); ?>px <?php echo esc_attr( (string) $settings['info_block_border_style'] ); ?> <?php echo esc_attr( (string) $settings['info_block_border_color'] ); ?>;--jprm-info-radius:<?php echo esc_attr( (string) $settings['info_block_border_radius'] ); ?>mm}</style>
</head>
<body class="jprm-print jprm-print--<?php echo esc_attr( $preset ); ?> jprm-print--<?php echo esc_attr( (string) $settings['orientation'] ); ?> jprm-print--columns-<?php echo (int) $settings['columns']; ?> jprm-print--heading-<?php echo esc_attr( (string) $settings['heading_font'] ); ?> jprm-print--body-<?php echo esc_attr( (string) $settings['body_font'] ); ?> jprm-print--info-<?php echo esc_attr( (string) $settings['info_block_layout'] ); ?> jprm-print--info-align-<?php echo esc_attr( (string) $settings['info_block_alignment'] ); ?> <?php echo esc_attr( implode( ' ', $visibility_classes ) ); ?>">
	<main class="jprm-print-document">
		<header class="jprm-print-header jprm-print-header--logo-<?php echo esc_attr( (string) $settings['logo_position'] ); ?>"><?php if ( is_string( $logo_url ) && '' !== $logo_url ) : ?><img class="jprm-print-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?><p class="jprm-print-kicker"><?php esc_html_e( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); ?></p><h1><?php echo esc_html( (string) $menu['name'] ); ?></h1><?php if ( '' !== (string) $menu['description'] ) : ?><div class="jprm-print-intro"><?php echo wp_kses_post( wpautop( (string) $menu['description'] ) ); ?></div><?php endif; ?><?php if ( $date_text || '' !== (string) $daily['fixed_price'] ) : ?><div class="jprm-print-daily"><?php if ( $date_text ) : ?><span><?php echo esc_html( $date_text ); ?></span><?php endif; ?><?php if ( '' !== (string) $daily['fixed_price'] ) : ?><strong>€<?php echo esc_html( (string) $daily['fixed_price'] ); ?></strong><?php endif; ?></div><?php endif; ?></header>
		<div class="jprm-print-sections">
		<?php foreach ( $document['sections'] as $section ) : if ( empty( $section['items'] ) ) { continue; } ?>
			<section class="jprm-print-section <?php echo (int) $section['parent_id'] > 0 ? 'jprm-print-section--child' : ''; ?> <?php echo in_array( (int) $section['id'], $settings['column_breaks'], true ) ? 'jprm-print-section--column-break' : ''; ?>"><?php Print_Document_Renderer::render_info_blocks( (array) ( $section['info_blocks']['above'] ?? [] ) ); ?><h2><?php echo esc_html( (string) $section['name'] ); ?></h2>
			<?php foreach ( $section['items'] as $index => $item ) : ?>
				<article class="jprm-print-item"><div class="jprm-print-item-copy"><h3><?php echo esc_html( (string) $item['title'] ); ?></h3><?php if ( '' !== trim( (string) $item['description'] ) ) : ?><div class="jprm-print-description"><?php echo wp_kses_post( wpautop( (string) $item['description'] ) ); ?></div><?php endif; ?><?php $badges = Print_Document_Renderer::badges_html( (array) $item['badges'] ); if ( $badges ) : ?><div class="jprm-print-badges"><?php echo $badges; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?></div><div class="jprm-print-prices"><?php echo Print_Document_Renderer::price_html( (array) $item['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></article>
				<?php if ( ! empty( $daily['enabled'] ) && empty( $section['disable_item_separator'] ) && $index < count( $section['items'] ) - 1 ) : $separator = (string) $section['item_separator'] ?: (string) $daily['item_separator']; if ( $separator ) : ?><div class="jprm-print-separator"><?php echo esc_html( $separator ); ?></div><?php endif; endif; ?>
			<?php endforeach; ?><?php Print_Document_Renderer::render_info_blocks( (array) ( $section['info_blocks']['below'] ?? [] ) ); ?></section>
		<?php endforeach; ?>
		</div>
	</main>
</body></html>
