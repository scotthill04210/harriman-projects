<?php
/**
 * Template picker and the three forms.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class MetaBoxes {

	/**
	 * While a checklist is rendering, list every term in name order.
	 *
	 * @var bool
	 */
	private static $listing_terms = false;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'add_meta_boxes_' . WW_POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'hide_native_taxonomies' ), 100, 2 );
		add_action( 'wp_ajax_ww_add_location', array( __CLASS__, 'add_location' ) );
	}

	/**
	 * Meta box at the top of the project editor. Editors and administrators only.
	 */
	public static function add() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		add_meta_box(
			'ww-project-template',
			__( 'Project Template', 'ww-projects' ),
			array( __CLASS__, 'render' ),
			WW_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Picker plus one panel per template.
	 *
	 * @param \WP_Post $post Current project.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'ww_save_fields', 'ww_fields_nonce' );
		$current = (int) get_post_meta( $post->ID, '_ww_template', true );
		$images  = ww_template_card_images();
		?>
		<div class="ww-editor" id="ww-project-editor">
			<div class="ww-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Project Template', 'ww-projects' ); ?>">
				<?php foreach ( Fields::template_ids() as $template ) : ?>
					<label class="ww-picker__card">
						<input type="radio" name="ww_template" value="<?php echo esc_attr( (string) $template ); ?>" <?php checked( $current, $template ); ?> />
						<img src="<?php echo esc_url( $images[ $template ] ); ?>" alt="" width="320" height="180" />
						<span><?php echo esc_html( sprintf( /* translators: %d: template number. */ __( 'Template %d', 'ww-projects' ), $template ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="ww-unused" <?php echo $current ? '' : 'hidden'; ?>></p>
			<?php foreach ( Fields::template_ids() as $template ) : ?>
				<div class="ww-template-panel" data-template="<?php echo esc_attr( (string) $template ); ?>" <?php echo $current === $template ? '' : 'hidden'; ?>>
					<input type="hidden" name="ww[taxonomies_present]" value="1" <?php disabled( $current !== $template ); ?> />
					<?php
					foreach ( Fields::blocks( $template ) as $block ) {
						self::render_block( $block, $template, $post->ID, $current === $template );
					}
					?>
				</div>
			<?php endforeach; ?>
			<template id="ww-row-image">
				<?php self::image_row( 'slider', '__i__', array( 'id' => 0, 'alt' => '', 'caption' => '' ), true ); ?>
			</template>
			<template id="ww-row-contact">
				<?php self::contact_row( '__i__', array( 'person_id' => 0, 'text' => '' ) ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * One collapsible block.
	 *
	 * @param string $block  Block name.
	 * @param int    $template Template number.
	 * @param int    $post_id  Post ID.
	 * @param bool   $active   Whether this panel is the saved choice.
	 */
	private static function render_block( $block, $template, $post_id, $active ) {
		$schema = Fields::block( $block, $template );
		?>
		<details class="ww-block" open>
			<summary>
				<?php echo self::icon( $schema['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span class="ww-block__label"><?php echo esc_html( $schema['label'] ); ?></span>
			</summary>
			<div class="ww-block__body">
				<?php
				if ( 'title' === $block ) {
					echo '<p>' . esc_html__( 'This is the project title at the top of the editor.', 'ww-projects' ) . '</p>';
				} elseif ( 'hero' === $block || 'single_image' === $block ) {
					$image = get_post_meta( $post_id, '_ww_' . $block, true );
					self::image_fields( 'ww[' . $block . ']', is_array( $image ) ? $image : array(), false, ! $active );
				} elseif ( 'location' === $block ) {
					self::location_fields( $template, $post_id, ! $active );
				} elseif ( 'subhead' === $block || 'stat' === $block || 'type' === $block || 'program' === $block ) {
					self::text_field( $block, (string) get_post_meta( $post_id, '_ww_' . $block, true ), ! $active );
				} elseif ( 'description' === $block || 'content_block' === $block ) {
					self::rich_text_field( $block, $template, (string) get_post_meta( $post_id, '_ww_' . $block, true ), ! $active );
				} elseif ( 'markets_services' === $block ) {
					self::term_fields( $template, $post_id, ! $active );
				} elseif ( 'slider' === $block || 'grid' === $block ) {
					self::image_repeater( $block, $template, $post_id, ! $active );
				} elseif ( 'overview' === $block ) {
					self::overview_fields( $template, $post_id, ! $active );
				} elseif ( 'quote' === $block ) {
					self::textarea_field( 'quote', (string) get_post_meta( $post_id, '_ww_quote', true ), 4, ! $active );
				}
				if ( $schema['help'] ) {
					echo '<p class="ww-block__help">' . esc_html( $schema['help'] ) . '</p>';
				}
				?>
			</div>
		</details>
		<?php
	}

	/**
	 * Plain textarea. TinyMCE is started in the browser once this section is visible.
	 *
	 * @param string $block    Field key.
	 * @param int    $template Template number.
	 * @param string $value    Stored HTML.
	 * @param bool   $disabled Disabled when this template is not the active one.
	 */
	private static function rich_text_field( $block, $template, $value, $disabled ) {
		$editor_id = 'ww_t' . (int) $template . '_' . $block;
		?>
		<textarea id="<?php echo esc_attr( $editor_id ); ?>" class="ww-richtext widefat" rows="8" name="ww[<?php echo esc_attr( $block ); ?>]" <?php disabled( $disabled ); ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Single-line input.
	 *
	 * @param string $key      Field key.
	 * @param string $value    Stored value.
	 * @param bool   $disabled Disabled when the panel is hidden.
	 */
	private static function text_field( $key, $value, $disabled ) {
		?>
		<input type="text" class="widefat" name="ww[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php disabled( $disabled ); ?> />
		<?php
	}

	/**
	 * Multiline input.
	 *
	 * @param string $key      Field key.
	 * @param string $value    Stored value.
	 * @param int    $rows     Rows.
	 * @param bool   $disabled Disabled when the panel is hidden.
	 */
	private static function textarea_field( $key, $value, $rows, $disabled ) {
		?>
		<textarea class="widefat" rows="<?php echo esc_attr( (string) $rows ); ?>" name="ww[<?php echo esc_attr( $key ); ?>]" <?php disabled( $disabled ); ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Location checklist. Checked terms stay in alphabetical order.
	 *
	 * @param int  $template Template number.
	 * @param int  $post_id  Post ID.
	 * @param bool $disabled Disabled when the panel is hidden.
	 */
	private static function location_fields( $template, $post_id, $disabled ) {
		echo '<div class="ww-terms ww-terms--location">';
		self::term_checklist( 'project-location', 'ww[locations][]', 't' . (int) $template . '-', $post_id, $disabled );
		if ( self::can_manage_terms( 'project-location' ) ) {
			echo '<p class="ww-location-add">';
			echo '<label class="screen-reader-text" for="ww-new-location-' . esc_attr( (string) $template ) . '">' . esc_html__( 'Add New Location', 'ww-projects' ) . '</label>';
			echo '<input type="text" class="ww-location-add__name" id="ww-new-location-' . esc_attr( (string) $template ) . '" placeholder="' . esc_attr__( 'City, ST (e.g. Portland, ME)', 'ww-projects' ) . '" ' . disabled( $disabled, true, false ) . ' />';
			echo '<button type="button" class="button ww-location-add__button">' . esc_html__( 'Add New Location', 'ww-projects' ) . '</button>';
			echo '</p>';
			echo '<p class="ww-location-add__error" hidden></p>';
		}
		echo '</div>';
	}

	/**
	 * Markets and services. Every existing term, with no add-new control.
	 *
	 * @param int  $template Template number.
	 * @param int  $post_id  Post ID.
	 * @param bool $disabled Disabled when the panel is hidden.
	 */
	private static function term_fields( $template, $post_id, $disabled ) {
		$groups = array(
			'markets'  => array(
				'taxonomy' => 'market',
				'label'    => __( 'Markets', 'ww-projects' ),
			),
			'services' => array(
				'taxonomy' => 'service',
				'label'    => __( 'Services', 'ww-projects' ),
			),
		);
		foreach ( $groups as $name => $group ) {
			echo '<fieldset class="ww-terms"><legend>' . esc_html( $group['label'] ) . '</legend>';
			self::term_checklist( $group['taxonomy'], 'ww[' . $name . '][]', 't' . (int) $template . '-' . $name . '-', $post_id, $disabled );
			echo '</fieldset>';
		}
	}

	/**
	 * Category-style list of every term. Checked terms are not moved to the top.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param string $input     Checkbox name.
	 * @param string $prefix    Unique id prefix for this copy of the list.
	 * @param int    $post_id   Post ID.
	 * @param bool   $disabled  Disabled when the panel is hidden.
	 */
	private static function term_checklist( $taxonomy, $input, $prefix, $post_id, $disabled ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			echo '<p>' . esc_html__( 'No terms found.', 'ww-projects' ) . '</p>';
			return;
		}
		$selected = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $selected ) ) {
			$selected = array();
		}
		if ( ! function_exists( 'wp_terms_checklist' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		if ( ! class_exists( __NAMESPACE__ . '\\Term_Checklist_Walker' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php';
			require_once WW_PLUGIN_DIR . 'includes/class-ww-term-checklist.php';
		}
		$list_class = 'ww-terms__list categorychecklist';
		if ( 'project-location' === $taxonomy ) {
			$list_class .= ' ww-location-list';
		}
		echo '<ul class="' . esc_attr( $list_class ) . '">';
		self::$listing_terms = true;
		add_filter( 'get_terms_args', array( __CLASS__, 'checklist_term_args' ), 10, 2 );
		wp_terms_checklist(
			$post_id,
			array(
				'taxonomy'      => $taxonomy,
				'selected_cats' => array_map( 'intval', $selected ),
				'checked_ontop' => false,
				'echo'          => true,
				'walker'        => new Term_Checklist_Walker( $input, $prefix, $disabled ),
			)
		);
		remove_filter( 'get_terms_args', array( __CLASS__, 'checklist_term_args' ), 10 );
		self::$listing_terms = false;
		echo '</ul>';
	}

	/**
	 * Keep empty terms in the checklist and sort the full list by name.
	 *
	 * @param array<string,mixed> $args       Term query args.
	 * @param string[]|string     $taxonomies Taxonomies being queried.
	 * @return array<string,mixed>
	 */
	public static function checklist_term_args( $args, $taxonomies ) {
		if ( ! self::$listing_terms ) {
			return $args;
		}
		$names = array();
		foreach ( (array) $taxonomies as $taxonomy ) {
			$names[] = is_object( $taxonomy ) ? $taxonomy->name : (string) $taxonomy;
		}
		$wanted = array( 'market', 'service', 'project-location' );
		if ( ! array_intersect( $names, $wanted ) ) {
			return $args;
		}
		$args['hide_empty'] = false;
		if ( empty( $args['number'] ) ) {
			$args['orderby'] = 'name';
			$args['order']   = 'ASC';
		}
		return $args;
	}

	/**
	 * Whether the current user may create terms in this taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private static function can_manage_terms( $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		return $tax && current_user_can( $tax->cap->manage_terms );
	}

	/**
	 * Hide the native taxonomy boxes on a flagged project.
	 *
	 * Other post types, and projects that are not flagged, keep their panels.
	 *
	 * @param string           $post_type Post type.
	 * @param \WP_Post|null    $post      Current post.
	 */
	public static function hide_native_taxonomies( $post_type, $post = null ) {
		if ( WW_POST_TYPE !== $post_type || ! $post instanceof \WP_Post ) {
			return;
		}
		if ( ! ww_uses_new_template( $post->ID ) ) {
			return;
		}
		foreach ( array( 'market', 'service', 'project-location' ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax ) {
				continue;
			}
			$box_id = $tax->hierarchical ? $taxonomy . 'div' : 'tagsdiv-' . $taxonomy;
			remove_meta_box( $box_id, WW_POST_TYPE, 'side' );
			remove_meta_box( $box_id, WW_POST_TYPE, 'normal' );
		}
	}

	/**
	 * Add a top-level location, or check the term that already has this name.
	 */
	public static function add_location() {
		$result = self::create_location();
		if ( is_wp_error( $result ) ) {
			$status = (int) $result->get_error_data();
			if ( $status < 400 ) {
				$status = 400;
			}
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status
			);
		}
		wp_send_json_success( $result );
	}

	/**
	 * Create or reuse a location from the current request.
	 *
	 * @return array{id:int,name:string,existing:bool}|\\WP_Error
	 */
	public static function create_location() {
		if ( ! check_ajax_referer( 'ww_add_location', 'nonce', false ) ) {
			return new \WP_Error( 'ww_location_nonce', __( 'The location could not be added.', 'ww-projects' ), 403 );
		}
		$taxonomy = 'project-location';
		$tax      = get_taxonomy( $taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->manage_terms ) ) {
			return new \WP_Error( 'ww_location_cap', __( 'You are not allowed to add a location.', 'ww-projects' ), 403 );
		}
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$name = self::normalize_term_name( $name );
		if ( '' === $name ) {
			return new \WP_Error( 'ww_location_name', __( 'Enter a location name.', 'ww-projects' ), 400 );
		}
		$existing = self::matching_term( $taxonomy, $name );
		if ( $existing instanceof \WP_Term ) {
			return array(
				'id'       => (int) $existing->term_id,
				'name'     => $existing->name,
				'existing' => true,
			);
		}
		$created = wp_insert_term( $name, $taxonomy, array( 'parent' => 0 ) );
		if ( is_wp_error( $created ) ) {
			$term_id = (int) $created->get_error_data( 'term_exists' );
			$term    = $term_id ? get_term( $term_id, $taxonomy ) : null;
			if ( $term instanceof \WP_Term && self::term_name_key( $term->name ) === self::term_name_key( $name ) ) {
				return array(
					'id'       => (int) $term->term_id,
					'name'     => $term->name,
					'existing' => true,
				);
			}
			return new \WP_Error( 'ww_location_create', $created->get_error_message(), 400 );
		}
		$term = get_term( (int) $created['term_id'], $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error( 'ww_location_create', __( 'The location could not be added.', 'ww-projects' ), 500 );
		}
		return array(
			'id'       => (int) $term->term_id,
			'name'     => $term->name,
			'existing' => false,
		);
	}

	/**
	 * Term whose name matches after trimming, spacing, and case are ignored.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name     Sanitized name.
	 * @return \WP_Term|null
	 */
	private static function matching_term( $taxonomy, $name ) {
		$key   = self::term_name_key( $name );
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) ) {
			return null;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && self::term_name_key( $term->name ) === $key ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * Collapse whitespace. Case is preserved for a new term.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private static function normalize_term_name( $name ) {
		$name = html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
		$name = str_replace( "\xc2\xa0", ' ', $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		return trim( (string) $name );
	}

	/**
	 * Comparison key for a term name.
	 *
	 * @param string $name Term name.
	 * @return string
	 */
	private static function term_name_key( $name ) {
		$name = self::normalize_term_name( $name );
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $name, 'UTF-8' );
		}
		return strtolower( $name );
	}

	/**
	 * Overview rows for this template.
	 *
	 * @param int  $template Template number.
	 * @param int  $post_id  Post ID.
	 * @param bool $disabled Disabled when the panel is hidden.
	 */
	private static function overview_fields( $template, $post_id, $disabled ) {
		foreach ( Fields::overview_keys( $template ) as $key ) {
			echo '<div class="ww-overview__row">';
			echo '<label><span class="ww-overview__label">' . esc_html( Fields::key_label( $key ) ) . '</span>';
			if ( 'key_contacts' === $key ) {
				echo '</label>';
				self::contact_repeater( $post_id, $disabled );
			} elseif ( in_array( $key, array( 'client', 'size', 'components', 'recognition' ), true ) ) {
				self::textarea_field( $key, (string) get_post_meta( $post_id, '_ww_' . $key, true ), 3, $disabled );
				echo '</label>';
			} else {
				self::text_field( $key, (string) get_post_meta( $post_id, '_ww_' . $key, true ), $disabled );
				echo '</label>';
			}
			echo '</div>';
		}
	}

	/**
	 * Image repeater.
	 *
	 * @param string $key      slider|grid.
	 * @param int    $template Template number.
	 * @param int    $post_id  Post ID.
	 * @param bool   $disabled Disabled when the panel is hidden.
	 */
	private static function image_repeater( $key, $template, $post_id, $disabled ) {
		$images = get_post_meta( $post_id, '_ww_' . $key, true );
		if ( ! is_array( $images ) ) {
			$images = array();
		}
		$exact = '';
		$min   = '';
		$max   = '';
		if ( 'slider' === $key ) {
			$min = '3';
			$max = '5';
		} else {
			$exact = ( 3 === (int) $template ) ? '4' : '3';
		}
		echo '<div class="ww-repeater" data-kind="image" data-key="' . esc_attr( $key ) . '">';
		echo '<div class="ww-repeater__list" data-repeater="' . esc_attr( $key ) . '" data-min="' . esc_attr( $min ) . '" data-max="' . esc_attr( $max ) . '" data-exact="' . esc_attr( $exact ) . '">';
		$index = 0;
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			self::image_row( $key, $index, $image, $disabled );
			++$index;
		}
		echo '</div>';
		echo '<p class="ww-soft-warning" hidden></p>';
		echo '<button type="button" class="button ww-repeater__add">' . esc_html__( 'Add another', 'ww-projects' ) . '</button>';
		echo '</div>';
	}

	/**
	 * One image row, including caption and alt tabs.
	 *
	 * @param string               $key      slider|grid or a single field name.
	 * @param int|string           $index    Row index.
	 * @param array<string,mixed>  $image    Image data.
	 * @param bool                 $disabled Disabled when the panel is hidden.
	 */
	private static function image_row( $key, $index, $image, $disabled ) {
		$id      = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$alt     = isset( $image['alt'] ) ? (string) $image['alt'] : '';
		$caption = isset( $image['caption'] ) ? (string) $image['caption'] : '';
		$preview = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		$name    = 'ww[' . $key . '][' . $index . ']';
		?>
		<div class="ww-repeater__row ww-image">
			<button type="button" class="ww-repeater__handle" aria-label="<?php esc_attr_e( 'Reorder', 'ww-projects' ); ?>">&#8597;</button>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( (string) $id ); ?>" <?php disabled( $disabled ); ?> />
			<div class="ww-image__preview">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<p>
				<button type="button" class="button ww-image__select"><?php esc_html_e( 'Select image', 'ww-projects' ); ?></button>
				<button type="button" class="button ww-repeater__remove"><?php esc_html_e( 'Remove', 'ww-projects' ); ?></button>
			</p>
			<div class="ww-image__tabs" role="tablist">
				<button type="button" class="ww-image__tab" role="tab" aria-selected="true" data-tab="caption"><?php esc_html_e( 'Caption', 'ww-projects' ); ?></button>
				<button type="button" class="ww-image__tab" role="tab" aria-selected="false" data-tab="alt"><?php esc_html_e( 'Alt text', 'ww-projects' ); ?></button>
			</div>
			<div class="ww-image__panel" data-panel="caption">
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Caption', 'ww-projects' ); ?></span>
					<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[caption]" value="<?php echo esc_attr( $caption ); ?>" <?php disabled( $disabled ); ?> />
				</label>
			</div>
			<div class="ww-image__panel" data-panel="alt" hidden>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Alt text', 'ww-projects' ); ?></span>
					<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[alt]" value="<?php echo esc_attr( $alt ); ?>" <?php disabled( $disabled ); ?> />
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * A single image block uses the same tabs without a repeater index.
	 *
	 * @param string              $name     Input name prefix, including ww[].
	 * @param array<string,mixed> $image    Image data.
	 * @param bool                $handle   Unused. Single images are not reordered.
	 * @param bool                $disabled Disabled when the panel is hidden.
	 */
	private static function image_fields( $name, $image, $handle, $disabled ) {
		unset( $handle );
		$id      = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$alt     = isset( $image['alt'] ) ? (string) $image['alt'] : '';
		$caption = isset( $image['caption'] ) ? (string) $image['caption'] : '';
		$preview = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		?>
		<div class="ww-image">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( (string) $id ); ?>" <?php disabled( $disabled ); ?> />
			<div class="ww-image__preview">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<p>
				<button type="button" class="button ww-image__select"><?php esc_html_e( 'Select image', 'ww-projects' ); ?></button>
				<button type="button" class="button ww-image__clear"><?php esc_html_e( 'Remove', 'ww-projects' ); ?></button>
			</p>
			<div class="ww-image__tabs" role="tablist">
				<button type="button" class="ww-image__tab" role="tab" aria-selected="true" data-tab="caption"><?php esc_html_e( 'Caption', 'ww-projects' ); ?></button>
				<button type="button" class="ww-image__tab" role="tab" aria-selected="false" data-tab="alt"><?php esc_html_e( 'Alt text', 'ww-projects' ); ?></button>
			</div>
			<div class="ww-image__panel" data-panel="caption">
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Caption', 'ww-projects' ); ?></span>
					<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[caption]" value="<?php echo esc_attr( $caption ); ?>" <?php disabled( $disabled ); ?> />
				</label>
			</div>
			<div class="ww-image__panel" data-panel="alt" hidden>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Alt text', 'ww-projects' ); ?></span>
					<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[alt]" value="<?php echo esc_attr( $alt ); ?>" <?php disabled( $disabled ); ?> />
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Key contacts repeater.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $disabled Disabled when the panel is hidden.
	 */
	private static function contact_repeater( $post_id, $disabled ) {
		$rows = get_post_meta( $post_id, '_ww_key_contacts', true );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		echo '<div class="ww-repeater" data-kind="contact" data-key="key_contacts">';
		echo '<div class="ww-repeater__list" data-repeater="key_contacts">';
		$index = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			self::contact_row( $index, $row, $disabled );
			++$index;
		}
		echo '</div>';
		echo '<button type="button" class="button ww-repeater__add">' . esc_html__( 'Add contact', 'ww-projects' ) . '</button>';
		echo '</div>';
	}

	/**
	 * One contact row.
	 *
	 * @param int|string          $index    Row index.
	 * @param array<string,mixed> $row      Stored row.
	 * @param bool                $disabled Disabled when the panel is hidden.
	 */
	private static function contact_row( $index, $row, $disabled = false ) {
		$person_id = isset( $row['person_id'] ) ? (int) $row['person_id'] : 0;
		$text      = isset( $row['text'] ) ? (string) $row['text'] : '';
		$mode      = $person_id ? 'person' : 'text';
		if ( ! $person_id && '' === $text ) {
			$mode = 'person';
		}
		$name    = 'ww[key_contacts][' . $index . ']';
		$person  = ( $person_id && WW_PEOPLE_POST_TYPE === get_post_type( $person_id ) ) ? get_the_title( $person_id ) : '';
		?>
		<div class="ww-repeater__row ww-contact" data-mode="<?php echo esc_attr( $mode ); ?>">
			<button type="button" class="ww-repeater__handle" aria-label="<?php esc_attr_e( 'Reorder', 'ww-projects' ); ?>">&#8597;</button>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Contact type', 'ww-projects' ); ?></span>
				<select name="<?php echo esc_attr( $name ); ?>[mode]" <?php disabled( $disabled ); ?>>
					<option value="person" <?php selected( $mode, 'person' ); ?>><?php esc_html_e( 'Person', 'ww-projects' ); ?></option>
					<option value="text" <?php selected( $mode, 'text' ); ?>><?php esc_html_e( 'Plain text', 'ww-projects' ); ?></option>
				</select>
			</label>
			<div class="ww-contact__person">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[person_id]" value="<?php echo esc_attr( (string) $person_id ); ?>" <?php disabled( $disabled ); ?> />
				<input type="search" class="ww-contact__search" placeholder="<?php esc_attr_e( 'Search people', 'ww-projects' ); ?>" autocomplete="off" <?php disabled( $disabled ); ?> />
				<ul class="ww-contact__results" hidden></ul>
				<p class="ww-contact__chosen"><?php echo esc_html( $person ); ?></p>
			</div>
			<label class="ww-contact__text">
				<span class="screen-reader-text"><?php esc_html_e( 'Plain text', 'ww-projects' ); ?></span>
				<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[text]" value="<?php echo esc_attr( $text ); ?>" <?php disabled( $disabled ); ?> />
			</label>
			<button type="button" class="button ww-repeater__remove"><?php esc_html_e( 'Remove', 'ww-projects' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Static icon. Not copied from the sample export.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	private static function icon( $name ) {
		$paths = array(
			'heading' => '<path d="M3 4h2v5h6V4h2v12h-2V11H5v5H3z"/>',
			'image'   => '<path d="M2 4h12v10H2zM4 6v6h8V6zm1 4l1.5-2 1.2 1.4L9.2 7 11 10z"/>',
			'text'    => '<path d="M2 3h12v2H9v8H7V5H2z"/>',
			'rich'    => '<path d="M2 3h12v2H2zm0 4h8v2H2zm0 4h12v2H2z"/>',
			'list'    => '<path d="M2 4h2v2H2zm3 0h9v2H5zM2 8h2v2H2zm3 0h9v2H5zM2 12h2v2H2zm3 0h9v2H5z"/>',
			'images'  => '<path d="M1 3h9v7H1zm2 2v3h5V5zm3 1h8v7H5v-2h1v1h7V7H6z"/>',
			'quote'   => '<path d="M3 4h4v4H5c0 2 1 3 2 3v2c-3 0-4-2-4-5zm7 0h4v4h-2c0 2 1 3 2 3v2c-3 0-4-2-4-5z"/>',
		);
		$path = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['text'];
		return '<svg class="ww-block__icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}
}
