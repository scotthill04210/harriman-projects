<?php
/**
 * Importer bootstrap. This is the only file the rest of the plugin loads.
 *
 * Delete this folder and the require in harriman-projects.php to remove the importer.
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-ww-backup.php';
require_once __DIR__ . '/class-ww-slickplan.php';
require_once __DIR__ . '/class-ww-slickplan-admin.php';

add_action(
	'init',
	static function () {
		if ( ! post_type_exists( WW_POST_TYPE ) ) {
			return;
		}
		\WW\HarrimanProjects\Backup::register();
		\WW\HarrimanProjects\SlickplanAdmin::register();
	},
	11
);
