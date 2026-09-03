<?php
use JelloPoint\RestaurantMenu\Render\Print_Document_Renderer;

if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var array $document Template data supplied by Print_Document_Renderer. */
$settings = $document['settings'];
$menu = $document['menu'];
$preset = (string) $settings['preset'];
$margins = $settings['margins'];
$daily = $menu['daily'];
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
	<style>:root{--jprm-page-top:<?php echo esc_attr( (string) $margins['top'] ); ?>mm;--jprm-page-right:<?php echo esc_attr( (string) $margins['right'] ); ?>mm;--jprm-page-bottom:<?php echo esc_attr( (string) $margins['bottom'] ); ?>mm;--jprm-page-left:<?php echo esc_attr( (string) $margins['left'] ); ?>mm}@page{size:A4 <?php echo esc_html( (string) $settings['orientation'] ); ?>;margin:<?php echo esc_html( (string) $margins['top'] ); ?>mm <?php echo esc_html( (string) $margins['right'] ); ?>mm <?php echo esc_html( (string) $margins['bottom'] ); ?>mm <?php echo esc_html( (string) $margins['left'] ); ?>mm}</style>
</head>
<body class="jprm-print jprm-print--<?php echo esc_attr( $preset ); ?> jprm-print--<?php echo esc_attr( (string) $settings['orientation'] ); ?>">
	<main class="jprm-print-document">
		<header class="jprm-print-header"><p class="jprm-print-kicker"><?php esc_html_e( 'Restaurant Menu', 'jellopoint-restaurant-menu' ); ?></p><h1><?php echo esc_html( (string) $menu['name'] ); ?></h1><?php if ( '' !== (string) $menu['description'] ) : ?><div class="jprm-print-intro"><?php echo wp_kses_post( wpautop( (string) $menu['description'] ) ); ?></div><?php endif; ?><?php if ( $date_text || '' !== (string) $daily['fixed_price'] ) : ?><div class="jprm-print-daily"><?php if ( $date_text ) : ?><span><?php echo esc_html( $date_text ); ?></span><?php endif; ?><?php if ( '' !== (string) $daily['fixed_price'] ) : ?><strong>€<?php echo esc_html( (string) $daily['fixed_price'] ); ?></strong><?php endif; ?></div><?php endif; ?></header>
		<div class="jprm-print-sections">
		<?php foreach ( $document['sections'] as $section ) : if ( empty( $section['items'] ) ) { continue; } ?>
			<section class="jprm-print-section <?php echo (int) $section['parent_id'] > 0 ? 'jprm-print-section--child' : ''; ?>"><h2><?php echo esc_html( (string) $section['name'] ); ?></h2>
			<?php foreach ( $section['items'] as $index => $item ) : ?>
				<article class="jprm-print-item"><div class="jprm-print-item-copy"><h3><?php echo esc_html( (string) $item['title'] ); ?></h3><?php if ( '' !== trim( (string) $item['description'] ) ) : ?><div class="jprm-print-description"><?php echo wp_kses_post( wpautop( (string) $item['description'] ) ); ?></div><?php endif; ?><?php $badges = Print_Document_Renderer::badges_html( (array) $item['badges'] ); if ( $badges ) : ?><div class="jprm-print-badges"><?php echo $badges; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?></div><div class="jprm-print-prices"><?php echo Print_Document_Renderer::price_html( (array) $item['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></article>
				<?php if ( ! empty( $daily['enabled'] ) && empty( $section['disable_item_separator'] ) && $index < count( $section['items'] ) - 1 ) : $separator = (string) $section['item_separator'] ?: (string) $daily['item_separator']; if ( $separator ) : ?><div class="jprm-print-separator"><?php echo esc_html( $separator ); ?></div><?php endif; endif; ?>
			<?php endforeach; ?></section>
		<?php endforeach; ?>
		</div>
	</main>
</body></html>
