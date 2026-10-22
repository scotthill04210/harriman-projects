<?php
/**
 * Registers the project post type while this plugin is active.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class PostType {

	/**
	 * Hooks. Called at plugin load, before init.
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ), 9 );
		add_action( 'init', array( __CLASS__, 'attach_taxonomies' ), 11 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush' ) );
	}

	/**
	 * Same arguments as the theme, without the pre-filled block template.
	 */
	public static function register() {
		if ( post_type_exists( WW_POST_TYPE ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'already_registered_notice' ) );
			return;
		}

		register_post_type(
			WW_POST_TYPE,
			array(
				'labels'                => array(
					'name'                  => __( 'Projects', 'harriman' ),
					'singular_name'         => __( 'Project', 'harriman' ),
					'all_items'             => __( 'All Projects', 'harriman' ),
					'archives'              => __( 'Project Archives', 'harriman' ),
					'attributes'            => __( 'Project Attributes', 'harriman' ),
					'insert_into_item'      => __( 'Insert into Project', 'harriman' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Project', 'harriman' ),
					'featured_image'        => _x( 'Featured Image', 'project', 'harriman' ),
					'set_featured_image'    => _x( 'Set featured image', 'project', 'harriman' ),
					'remove_featured_image' => _x( 'Remove featured image', 'project', 'harriman' ),
					'use_featured_image'    => _x( 'Use as featured image', 'project', 'harriman' ),
					'filter_items_list'     => __( 'Filter Projects list', 'harriman' ),
					'items_list_navigation' => __( 'Projects list navigation', 'harriman' ),
					'items_list'            => __( 'Projects list', 'harriman' ),
					'new_item'              => __( 'New Project', 'harriman' ),
					'add_new'               => __( 'Add New', 'harriman' ),
					'add_new_item'          => __( 'Add New Project', 'harriman' ),
					'edit_item'             => __( 'Edit Project', 'harriman' ),
					'view_item'             => __( 'View Project', 'harriman' ),
					'view_items'            => __( 'View Projects', 'harriman' ),
					'search_items'          => __( 'Search Projects', 'harriman' ),
					'not_found'             => __( 'No Projects found', 'harriman' ),
					'not_found_in_trash'    => __( 'No Projects found in trash', 'harriman' ),
					'parent_item_colon'     => __( 'Parent Project:', 'harriman' ),
					'menu_name'             => __( 'Projects', 'harriman' ),
				),
				'public'                => true,
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_in_nav_menus'     => true,
				'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'has_archive'           => true,
				'rewrite'               => array(
					'slug'       => 'projects',
					'with_front' => false,
				),
				'query_var'             => true,
				'menu_position'         => null,
				'menu_icon'             => 'dashicons-portfolio',
				'show_in_rest'          => true,
				'rest_base'             => 'project',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
			)
		);
	}

	/**
	 * Keep market, service, and project-location on project after they register.
	 */
	public static function attach_taxonomies() {
		if ( ! post_type_exists( WW_POST_TYPE ) ) {
			return;
		}
		foreach ( array( 'market', 'service', 'project-location' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) && ! in_array( $taxonomy, get_object_taxonomies( WW_POST_TYPE ), true ) ) {
				register_taxonomy_for_object_type( $taxonomy, WW_POST_TYPE );
			}
		}
	}

	/**
	 * Flush once when the plugin version changes. Not on every request.
	 */
	public static function maybe_flush() {
		if ( ! post_type_exists( WW_POST_TYPE ) ) {
			return;
		}
		if ( get_option( 'ww_projects_version' ) === WW_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'ww_projects_version', WW_VERSION, false );
	}

	/**
	 * Another plugin or the theme already registered project.
	 */
	public static function already_registered_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Harriman Projects did not replace the project post type because something else already registered it.', 'ww-projects' );
		echo '</p></div>';
	}
}
