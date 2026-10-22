<?php
/**
 * Bootstrap. Stops completely when the project post type is missing.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Wire the plugin after the theme has registered post types.
	 */
	public static function boot() {
		load_plugin_textdomain( WW_TEXT_DOMAIN, false, dirname( plugin_basename( WW_PLUGIN_FILE ) ) . '/languages' );
		Updater::register();
		add_filter( 'plugin_action_links_' . plugin_basename( WW_PLUGIN_FILE ), array( 'WW\\HarrimanProjects\\Updater', 'action_links' ) );

		if ( ! post_type_exists( WW_POST_TYPE ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_post_type_notice' ) );
			return;
		}

		Switcher::register();
		MetaBoxes::register();
		Save::register();
		Rest::register();
		Render::register();
	}

	/**
	 * Admin notice when this site has no project post type.
	 */
	public static function missing_post_type_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Harriman Projects is idle because the project post type is not registered.', 'ww-projects' );
		echo '</p></div>';
	}
}
