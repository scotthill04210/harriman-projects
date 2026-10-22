<?php
/**
 * Template 1. Header, intro, slider, overview, statistic, feature, quote.
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;
?>
<?php ww_render_header(); ?>
<article class="ww-project alignfull">
	<?php
	ww_render_hero_slot();
	ww_render_intro();
	ww_render_gallery( 'slider' );
	ww_render_overview();
	ww_render_stat();
	ww_render_feature();
	ww_render_quote();
	ww_render_related();
	ww_render_cta();
	?>
</article>
