<?php
/**
 * Editor sidebar switch, meta registration, and admin assets.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Switcher {

	/**
	 * Hooks.
	 */
	public static function register() {
		// Called from init priority 11. A new init callback would miss this request.
		self::register_meta();
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor_frame' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * Meta the block editor is allowed to change.
	 */
	public static function register_meta() {
		$auth = array( __CLASS__, 'auth_callback' );
		register_post_meta(
			WW_POST_TYPE,
			'_ww_use_new_template',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_post_meta(
			WW_POST_TYPE,
			'_ww_legacy_deleted',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
		register_post_meta(
			WW_POST_TYPE,
			'_ww_slickplan_page_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			WW_POST_TYPE,
			'_ww_import_prev',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) ? wp_check_invalid_utf8( $value ) : '';
				},
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			WW_POST_TYPE,
			'_ww_import_created',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Tell the editor whether a legacy snapshot exists, without sending the snapshot.
	 */
	public static function register_rest_field() {
		register_rest_field(
			WW_POST_TYPE,
			'ww_legacy_archived',
			array(
				'get_callback' => static function ( $post ) {
					$post_id = isset( $post['id'] ) ? (int) $post['id'] : 0;
					if ( $post_id <= 0 ) {
						return false;
					}
					$snapshot = get_post_meta( $post_id, '_ww_legacy_content', true );
					return is_string( $snapshot ) && '' !== $snapshot;
				},
				'schema'       => array(
					'description' => __( 'Whether this project has an archived legacy layout.', 'ww-projects' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Editors and administrators only.
	 *
	 * @param bool   $allowed   Whether the user can edit this meta.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public static function auth_callback( $allowed = false, $meta_key = '', $object_id = 0 ) {
		unset( $allowed, $meta_key );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return false;
		}
		$object_id = (int) $object_id;
		if ( $object_id > 0 && ! current_user_can( 'edit_post', $object_id ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Canvas-hide rules have to live in the editor iframe, not only the parent document.
	 */
	public static function enqueue_editor_frame() {
		if ( ! is_admin() || ! self::is_project_editor() ) {
			return;
		}
		wp_enqueue_style(
			'ww-admin',
			WW_PLUGIN_URL . 'assets/css/ww-admin.css',
			array(),
			WW_VERSION
		);
	}

	/**
	 * Document sidebar script. Loaded for the block editor only.
	 */
	public static function enqueue_editor() {
		if ( ! self::is_project_editor() ) {
			return;
		}
		wp_enqueue_style(
			'ww-admin',
			WW_PLUGIN_URL . 'assets/css/ww-admin.css',
			array(),
			WW_VERSION
		);
		wp_enqueue_script(
			'ww-switch-panel',
			WW_PLUGIN_URL . 'assets/js/ww-switch-panel.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
			WW_VERSION,
			true
		);
	}

	/**
	 * Meta box script and styles.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin( $hook ) {
		unset( $hook );
		if ( ! self::is_project_editor() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style(
			'ww-admin',
			WW_PLUGIN_URL . 'assets/css/ww-admin.css',
			array(),
			WW_VERSION
		);
		wp_enqueue_script(
			'ww-admin',
			WW_PLUGIN_URL . 'assets/js/ww-admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch', 'wp-i18n', 'media-editor', 'editor' ),
			WW_VERSION,
			true
		);

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$stored  = array();
		if ( $post_id ) {
			foreach ( Fields::all_meta_keys() as $key ) {
				if ( Fields::is_filled( $key, $post_id ) ) {
					$stored[ $key ] = Fields::key_label( $key );
				}
			}
		}
		$templates = array();
		foreach ( Fields::template_ids() as $template ) {
			$templates[ $template ] = Fields::meta_keys( $template );
		}
		wp_localize_script(
			'ww-admin',
			'wwAdmin',
			array(
				'templates'     => $templates,
				'stored'        => $stored,
				'people'        => WW_PEOPLE_POST_TYPE,
				'locationNonce' => wp_create_nonce( 'ww_add_location' ),
			)
		);
	}

	/**
	 * Hide the block canvas on load when this post is already flagged.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( ! self::is_project_editor() ) {
			return $classes;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$flagged = ! $post_id || ww_flag_is_on( get_post_meta( $post_id, '_ww_use_new_template', true ) );
		if ( $flagged ) {
			$classes .= ' ww-flagged ww-hide-legacy-canvas';
		}
		return $classes;
	}

	/**
	 * Project edit screen for a user who may switch templates.
	 *
	 * @return bool
	 */
	private static function is_project_editor() {
		if ( ! is_admin() || ! current_user_can( 'edit_others_posts' ) ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && WW_POST_TYPE === $screen->post_type;
	}
}
