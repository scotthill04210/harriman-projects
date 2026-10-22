<?php
/**
 * Sanitize and save the chosen template. Other templates' keys are left stored.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Save {

	/**
	 * Skip the legacy snapshot while a delete is clearing content.
	 *
	 * @var bool
	 */
	public static $skip_snapshot = false;

	/**
	 * Set by a future importer so a post save does not archive legacy content.
	 *
	 * @var bool
	 */
	public static $importing = false;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'save_post_' . WW_POST_TYPE, array( __CLASS__, 'snapshot_on_save' ), 5, 2 );
		add_action( 'save_post_' . WW_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_snapshot' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_snapshot' ), 10, 4 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'keep_flag_after_delete' ), 10, 4 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'keep_flag_after_delete' ), 10, 3 );
	}

	/**
	 * Archive post_content the first time the flag is stored.
	 *
	 * Switching never rewrites post_content. This is a copy.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 */
	public static function maybe_snapshot( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( '_ww_use_new_template' !== $meta_key || self::$skip_snapshot || self::$importing ) {
			return;
		}
		if ( ! ww_flag_is_on( $meta_value ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$object_id = (int) $object_id;
		if ( wp_is_post_revision( $object_id ) || wp_is_post_autosave( $object_id ) ) {
			return;
		}
		if ( WW_POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}
		if ( get_post_meta( $object_id, '_ww_legacy_deleted', true ) ) {
			return;
		}
		// First successful switch only. Revert leaves the archive in place.
		if ( metadata_exists( 'post', $object_id, '_ww_legacy_content' ) ) {
			return;
		}
		$post = get_post( $object_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		update_post_meta( $object_id, '_ww_legacy_content', $post->post_content );
	}

	/**
	 * Copy post_content the first time a project is saved in new-template mode.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function snapshot_on_save( $post_id, $post ) {
		if ( self::$skip_snapshot || self::$importing ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || WW_POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! ww_uses_new_template( $post_id ) ) {
			return;
		}
		if ( get_post_meta( $post_id, '_ww_legacy_deleted', true ) ) {
			return;
		}
		if ( metadata_exists( 'post', $post_id, '_ww_legacy_content' ) ) {
			return;
		}
		update_post_meta( $post_id, '_ww_legacy_content', $post->post_content );
	}

	/**
	 * After old data is deleted, the flag cannot be turned off.
	 *
	 * @param null|bool $check      Short-circuit value.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value New value. Absent on delete.
	 * @return null|bool
	 */
	public static function keep_flag_after_delete( $check, $object_id, $meta_key, $meta_value = null ) {
		if ( null !== $check || '_ww_use_new_template' !== $meta_key ) {
			return $check;
		}
		if ( ! get_post_meta( $object_id, '_ww_legacy_deleted', true ) ) {
			return $check;
		}
		$turning_off = ( 4 > func_num_args() ) || ! ww_flag_is_on( $meta_value );
		if ( $turning_off ) {
			return true;
		}
		return $check;
	}

	/**
	 * Save fields posted by the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		unset( $post );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['ww_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ww_fields_nonce'] ) ), 'ww_save_fields' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! ww_uses_new_template( $post_id ) ) {
			return;
		}

		$template = isset( $_POST['ww_template'] ) ? (int) $_POST['ww_template'] : 0;
		$raw      = array();
		if ( isset( $_POST['ww'] ) && is_array( $_POST['ww'] ) ) {
			$raw = wp_unslash( $_POST['ww'] );
		}
		// Hidden panels are disabled and do not post. Skip field writes until a panel is enabled.
		if ( empty( $raw['taxonomies_present'] ) ) {
			return;
		}
		if ( ! array_key_exists( 'locations', $raw ) ) {
			$raw['locations'] = array();
		}
		if ( ! array_key_exists( 'markets', $raw ) ) {
			$raw['markets'] = array();
		}
		if ( ! array_key_exists( 'services', $raw ) ) {
			$raw['services'] = array();
		}

		self::save_template_fields( $post_id, $template, $raw );
	}

	/**
	 * Save one template's fields from an array. Does not read $_POST.
	 *
	 * Does not write _ww_legacy_content. Location text is left stored when the
	 * form does not send it. Market, service, and project-location terms are
	 * replaced from the checked boxes when those keys are present.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param int                  $template Template number.
	 * @param array<string,mixed> $fields   Field values keyed without the _ww_ prefix.
	 */
	public static function save_template_fields( $post_id, $template, array $fields ) {
		$post_id  = (int) $post_id;
		$template = (int) $template;
		if ( ! ww_uses_new_template( $post_id ) ) {
			return;
		}
		if ( ! in_array( $template, Fields::template_ids(), true ) ) {
			return;
		}

		update_post_meta( $post_id, '_ww_template', (string) $template );

		foreach ( Fields::meta_keys( $template ) as $key ) {
			// The location input is gone. A form save must not erase _ww_location.
			if ( 'location' === $key && ! array_key_exists( $key, $fields ) ) {
				continue;
			}
			$value = isset( $fields[ $key ] ) ? $fields[ $key ] : null;
			update_post_meta( $post_id, '_ww_' . $key, self::sanitize( $key, $value ) );
		}

		if ( array_key_exists( 'locations', $fields ) ) {
			self::save_terms( $post_id, 'project-location', $fields['locations'] );
		}
		if ( array_key_exists( 'markets', $fields ) ) {
			self::save_terms( $post_id, 'market', $fields['markets'] );
		}
		if ( array_key_exists( 'services', $fields ) ) {
			self::save_terms( $post_id, 'service', $fields['services'] );
		}
	}

	/**
	 * Replace terms for one taxonomy with the checked ids.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param mixed  $incoming Posted ids.
	 */
	private static function save_terms( $post_id, $taxonomy, $incoming ) {
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->assign_terms, $post_id ) ) {
			return;
		}
		$ids = array();
		foreach ( (array) $incoming as $term_id ) {
			$term_id = (int) $term_id;
			$term    = get_term( $term_id, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$ids[] = $term_id;
			}
		}
		wp_set_object_terms( $post_id, $ids, $taxonomy, false );
	}

	/**
	 * Sanitize one registry value.
	 *
	 * @param string $key   Meta key without prefix.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( $key, $value ) {
		if ( in_array( $key, array( 'hero', 'single_image' ), true ) ) {
			return self::sanitize_image( $value );
		}
		if ( in_array( $key, array( 'slider', 'grid' ), true ) ) {
			$rows = array();
			foreach ( (array) $value as $row ) {
				$image = self::sanitize_image( $row );
				if ( $image['id'] ) {
					$rows[] = $image;
				}
			}
			return $rows;
		}
		if ( 'key_contacts' === $key ) {
			return self::sanitize_contacts( $value );
		}
		if ( in_array( $key, array( 'description', 'content_block' ), true ) ) {
			return wp_kses_post( is_string( $value ) ? $value : '' );
		}
		if ( in_array( $key, array( 'subhead', 'stat' ), true ) ) {
			return ww_clean_plain( is_string( $value ) ? $value : '' );
		}
		if ( in_array( $key, array( 'client', 'size', 'components', 'recognition', 'quote' ), true ) ) {
			return sanitize_textarea_field( is_string( $value ) ? $value : '' );
		}
		return sanitize_text_field( is_string( $value ) ? $value : '' );
	}

	/**
	 * Image id plus caption and alt override.
	 *
	 * @param mixed $value Raw image.
	 * @return array{id:int,alt:string,caption:string}
	 */
	private static function sanitize_image( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'id'      => 0,
				'alt'     => '',
				'caption' => '',
			);
		}
		return array(
			'id'      => absint( isset( $value['id'] ) ? $value['id'] : 0 ),
			'alt'     => sanitize_text_field( isset( $value['alt'] ) ? (string) $value['alt'] : '' ),
			'caption' => sanitize_text_field( isset( $value['caption'] ) ? (string) $value['caption'] : '' ),
		);
	}

	/**
	 * Contact rows. A person link or a line of text.
	 *
	 * @param mixed $value Raw rows.
	 * @return array<int,array{person_id:int,text:string}>
	 */
	private static function sanitize_contacts( $value ) {
		$rows = array();
		foreach ( (array) $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$mode      = ( isset( $row['mode'] ) && 'text' === $row['mode'] ) ? 'text' : 'person';
			$person_id = ( 'person' === $mode ) ? absint( isset( $row['person_id'] ) ? $row['person_id'] : 0 ) : 0;
			if ( $person_id && WW_PEOPLE_POST_TYPE !== get_post_type( $person_id ) ) {
				$person_id = 0;
			}
			$text = sanitize_text_field( isset( $row['text'] ) ? (string) $row['text'] : '' );
			if ( ! $person_id && '' === $text ) {
				continue;
			}
			$rows[] = array(
				'person_id' => $person_id,
				'text'      => $text,
			);
		}
		return $rows;
	}
}
