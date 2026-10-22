<?php
/**
 * Delete the archived layout for one flagged project.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Rest {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * POST ww/v1/projects/<id>/delete-legacy.
	 */
	public static function routes() {
		register_rest_route(
			'ww/v1',
			'/projects/(?P<id>\d+)/delete-legacy',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'delete_legacy' ),
				'permission_callback' => array( __CLASS__, 'can_delete' ),
				'args'                => array(
					'id' => array(
						'type' => 'integer',
					),
				),
			)
		);
	}

	/**
	 * Nonce, capability, project type, and the flag.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function can_delete( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'ww_forbidden', __( 'Invalid nonce.', 'ww-projects' ), array( 'status' => 403 ) );
		}
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'ww_forbidden', __( 'You cannot edit this project.', 'ww-projects' ), array( 'status' => 403 ) );
		}
		if ( WW_POST_TYPE !== get_post_type( $post_id ) ) {
			return new \WP_Error( 'ww_not_found', __( 'Project not found.', 'ww-projects' ), array( 'status' => 404 ) );
		}
		if ( ! ww_flag_is_on( get_post_meta( $post_id, '_ww_use_new_template', true ) ) ) {
			return new \WP_Error( 'ww_not_flagged', __( 'Save the switch before deleting the old layout.', 'ww-projects' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Remove the snapshot and blank post_content.
	 *
	 * Terms, the featured image, testimonial posts, and reusable blocks stay.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_legacy( $request ) {
		$post_id = (int) $request['id'];
		$confirm = $request->get_param( 'confirm' );
		if ( ! is_string( $confirm ) || 'DELETE' !== $confirm ) {
			return new \WP_Error(
				'ww_confirm',
				__( 'Type DELETE to confirm.', 'ww-projects' ),
				array( 'status' => 400 )
			);
		}
		$snapshot = get_post_meta( $post_id, '_ww_legacy_content', true );
		if ( ! is_string( $snapshot ) || '' === $snapshot ) {
			return new \WP_Error(
				'ww_no_snapshot',
				__( 'No archived copy exists. Save the project once first.', 'ww-projects' ),
				array( 'status' => 400 )
			);
		}
		$post = get_post( $post_id );
		$content_hash = $post instanceof \WP_Post ? hash( 'sha256', $post->post_content ) : '';
		if ( ! hash_equals( hash( 'sha256', $snapshot ), $content_hash ) ) {
			return new \WP_Error(
				'ww_snapshot_stale',
				__( 'Archived copy is out of date. Save the project once, then try again.', 'ww-projects' ),
				array( 'status' => 400 )
			);
		}
		update_post_meta( $post_id, '_ww_legacy_deleted', '1' );
		delete_post_meta( $post_id, '_ww_legacy_content' );

		Save::$skip_snapshot = true;
		$result              = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '',
			),
			true
		);
		Save::$skip_snapshot = false;

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'deleted' => true,
				'revert'  => false,
			)
		);
	}
}
