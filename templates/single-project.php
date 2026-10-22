<?php
/**
 * Loads the part for the project's template.
 *
 * Included from the_content. The theme layout is already wrapped around this.
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;

ww_image_alt_reset();
$ww_template = ww_template_number();
$ww_part     = WW_PLUGIN_DIR . 'templates/parts/template-' . $ww_template . '.php';
if ( $ww_template && file_exists( $ww_part ) ) {
	include $ww_part;
}
