<?php
/**
 * Template 3. Statistic card, then the image grid. No slider and no content block.
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
	ww_render_overview();
	ww_render_stat_card();
	ww_render_gallery( 'grid' );
	ww_render_quote();
	ww_render_related();
	ww_render_cta();
	?>
</article>
