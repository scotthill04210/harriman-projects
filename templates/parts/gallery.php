<?php
/**
 * Image slider or image grid.
 *
 * One slider image is a plain figure. Two or more use the theme carousel.
 *
 * @package WW\HarrimanProjects
 *
 * @var array  $ww_images        Image rows.
 * @var string $ww_gallery_kind  slider|grid.
 * @var int    $ww_gallery_count Image count.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $ww_images ) ) {
	return;
}

$count    = isset( $ww_gallery_count ) ? (int) $ww_gallery_count : count( $ww_images );
$kind     = ( isset( $ww_gallery_kind ) && 'grid' === $ww_gallery_kind ) ? 'grid' : 'slider';
$template = ww_template_number();

if ( 'slider' === $kind && $count < 2 ) {
	echo '<section class="ww-project__slider ww-project__slider--single">';
	echo '<div class="ww-project__gallery ww-project__gallery--slider ww-project__gallery--n-1">';
	foreach ( $ww_images as $ww_image ) {
		$ww_image_role   = 'photo';
		$ww_figure_class = 'ww-project__gallery-item';
		include WW_PLUGIN_DIR . 'templates/parts/image.php';
	}
	echo '</div></section>';
	return;
}

if ( 'slider' === $kind ) {
	$slides = '';
	foreach ( $ww_images as $ww_image ) {
		if ( empty( $ww_image['id'] ) ) {
			continue;
		}
		$alt = ww_image_alt( $ww_image, 'photo' );
		$img = wp_get_attachment_image(
			(int) $ww_image['id'],
			'full',
			false,
			array(
				'class' => 'ww-project__img wp-block-anchour-slide__image',
				'alt'   => $alt,
			)
		);
		if ( ! $img ) {
			continue;
		}
		$caption = trim( (string) $ww_image['caption'] );
		$slides .= '<li class="wp-block-anchour-slide glide__slide">';
		$slides .= '<div class="wp-block-anchour-slide__image-wrapper"><div class="wp-block-anchour-slide__image-scaler"></div>';
		$slides .= $img;
		$slides .= '</div>';
		if ( '' !== $caption ) {
			$slides .= '<p class="ww-project__caption">' . esc_html( $caption ) . '</p>';
		}
		$slides .= '</li>';
	}
	if ( '' === $slides ) {
		return;
	}
	echo '<section class="ww-project__slider">';
	echo '<div class="ww-project__gallery ww-project__gallery--slider wp-block-anchour-carousel wp-block carousel-landscape">';
	echo '<div class="glide__track" data-glide-el="track"><ul class="glide__slides">' . $slides . '</ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image HTML is escaped by WordPress and the caption is escaped above.
	echo '<div class="controls-wrap"><div class="glide__bullets" data-glide-el="controls[nav]"></div>';
	echo '<div class="glide__arrows" data-glide-el="controls">';
	echo '<button type="button" class="glide__arrow glide__arrow--left" data-glide-dir="' . esc_attr( '<' ) . '" aria-label="' . esc_attr__( 'Previous slide', 'ww-projects' ) . '">prev</button>';
	echo '<button type="button" class="glide__arrow glide__arrow--right" data-glide-dir="' . esc_attr( '>' ) . '" aria-label="' . esc_attr__( 'Next slide', 'ww-projects' ) . '">next</button>';
	echo '</div></div></div></section>';
	return;
}

$figma   = ( 2 === $template && 3 === $count ) || ( 3 === $template && 4 === $count );
$classes = array(
	'ww-project__gallery',
	'ww-project__gallery--grid',
	'ww-project__gallery--n-' . $count,
);
if ( $figma ) {
	$classes[] = 'ww-project__gallery--figma';
}
ob_start();
foreach ( $ww_images as $ww_image ) {
	$ww_image_role   = 'photo';
	$ww_figure_class = 'ww-project__gallery-item';
	include WW_PLUGIN_DIR . 'templates/parts/image.php';
}
$items = ob_get_clean();
if ( ! is_string( $items ) || '' === trim( $items ) ) {
	return;
}
echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $items . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image.php escapes its output.
