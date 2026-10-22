<?php
/**
 * Plugin Name:       Harriman Projects
 * Description:       Per-project templates for the project post type. A project with a saved template uses that layout; every other project keeps the theme layout.
 * Version:           1.2.4
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Warp+Weft
 * Update URI:        https://github.com/scotthill04210/harriman-projects
 * Text Domain:       ww-projects
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;

define( 'WW_VERSION', '1.2.4' );
define( 'WW_PLUGIN_FILE', __FILE__ );
define( 'WW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WW_TEXT_DOMAIN', 'ww-projects' );
define( 'WW_POST_TYPE', 'project' );
define( 'WW_PEOPLE_POST_TYPE', 'leadership' );
define( 'WW_HARRIMAN_PROJECTS_ACTIVE', true );

require_once WW_PLUGIN_DIR . 'includes/class-ww-fields.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-post-type.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-plugin.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-updater.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-meta-boxes.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-save.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-switch.php';
require_once WW_PLUGIN_DIR . 'includes/class-ww-render.php';
require_once WW_PLUGIN_DIR . 'includes/functions-ww.php';
if ( ! defined( 'WW_ENABLE_IMPORTER' ) || WW_ENABLE_IMPORTER ) {
	require_once WW_PLUGIN_DIR . 'includes/importer/load.php';
}

add_action( 'init', array( 'WW\\HarrimanProjects\\Plugin', 'boot' ), 11 );
\WW\HarrimanProjects\PostType::register_hooks();
register_activation_hook(
	WW_PLUGIN_FILE,
	static function () {
		delete_option( 'ww_projects_version' );
	}
);
