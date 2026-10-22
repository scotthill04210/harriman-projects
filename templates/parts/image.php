<?php
/**
 * One image: figure, img, and figcaption when a caption was entered.
 *
 * @package WW\HarrimanProjects
 *
 * @var array  $ww_image        id, alt, caption.
 * @var string $ww_image_role   hero or photo. Photo when omitted.
 * @var string $ww_figure_class Figure class.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $ww_image['id'] ) ) {
	return;
}

$ww_id      = (int) $ww_image['id'];
$ww_caption = trim( (string) $ww_image['caption'] );
$ww_role    = ( isset( $ww_image_role ) && 'hero' === $ww_image_role ) ? 'hero' : 'photo';
$ww_alt     = ww_image_alt( $ww_image, $ww_role );
$ww_img     = wp_get_attachment_image(
	$ww_id,
	'full',
	false,
	array(
		'class' => 'ww-project__img',
		'alt'   => $ww_alt,
	)
);
if ( ! $ww_img ) {
	return;
}
?>
<figure class="<?php echo esc_attr( $ww_figure_class ); ?>">
	<?php echo $ww_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes. ?>
	<?php if ( '' !== $ww_caption ) : ?>
		<figcaption><?php echo esc_html( $ww_caption ); ?></figcaption>
	<?php endif; ?>
</figure>
