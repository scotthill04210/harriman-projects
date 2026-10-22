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
	 * The importer sets this around apply. Field saves still require the meta box nonce.
	 *
	 * @var bool
	 */
	public static $importing = false;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'save_post_' . WW_POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
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
