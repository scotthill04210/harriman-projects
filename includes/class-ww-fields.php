<?php
/**
 * Field registry. Templates choose keys; they do not own separate copies of the data.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Fields {

	/**
	 * Template ids in picker order.
	 *
	 * @return int[]
	 */
	public static function template_ids() {
		return array( 1, 2, 3 );
	}

	/**
	 * Spec block order for one template. Twelve, thirteen, and eleven blocks.
	 *
	 * @param int $template Template number.
	 * @return string[]
	 */
	public static function blocks( $template ) {
		$map = array(
			1 => array( 'title', 'hero', 'subhead', 'location', 'description', 'markets_services', 'slider', 'overview', 'stat', 'content_block', 'single_image', 'quote' ),
			2 => array( 'title', 'hero', 'subhead', 'location', 'description', 'markets_services', 'grid', 'overview', 'slider', 'stat', 'content_block', 'single_image', 'quote' ),
			3 => array( 'title', 'hero', 'subhead', 'location', 'description', 'markets_services', 'overview', 'stat', 'single_image', 'grid', 'quote' ),
		);
		$template = (int) $template;
		return isset( $map[ $template ] ) ? $map[ $template ] : array();
	}

	/**
	 * Meta keys written when this template is saved.
	 *
	 * Markets and services are taxonomies, and the title is the post title.
	 *
	 * @param int $template Template number.
	 * @return string[]
	 */
	public static function meta_keys( $template ) {
		$keys = array();
		foreach ( self::blocks( $template ) as $block ) {
			foreach ( self::block_keys( $block, $template ) as $key ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Overview row keys for a template.
	 *
	 * @param int $template Template number.
	 * @return string[]
	 */
	public static function overview_keys( $template ) {
		if ( 2 === (int) $template ) {
			return array( 'client', 'type', 'program', 'size', 'components', 'key_contacts', 'recognition' );
		}
		return array( 'client', 'type', 'size', 'components', 'key_contacts', 'recognition' );
	}

	/**
	 * Human label for a meta key.
	 *
	 * @param string $key Meta key without the _ww_ prefix.
	 * @return string
	 */
	public static function key_label( $key ) {
		$labels = array(
			'hero'          => __( 'Hero Image', 'ww-projects' ),
			'subhead'       => __( 'Subhead', 'ww-projects' ),
			'location'      => __( 'Location', 'ww-projects' ),
			'description'   => __( 'Project Description', 'ww-projects' ),
			'client'        => __( 'Client', 'ww-projects' ),
			'type'          => __( 'Type', 'ww-projects' ),
			'size'          => __( 'Size', 'ww-projects' ),
			'program'       => __( 'Program', 'ww-projects' ),
			'components'    => __( 'Program Components', 'ww-projects' ),
			'key_contacts'  => __( 'Key Contacts', 'ww-projects' ),
			'recognition'   => __( 'Recognition', 'ww-projects' ),
			'stat'          => __( 'Subhead or Statistic', 'ww-projects' ),
			'content_block' => __( 'Content Block', 'ww-projects' ),
			'single_image'  => __( 'Single Image', 'ww-projects' ),
			'slider'        => __( 'Image Slider', 'ww-projects' ),
			'grid'          => __( 'Image Grid', 'ww-projects' ),
			'quote'         => __( 'Quote/Testimonial', 'ww-projects' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * Block chrome: label, icon, and helper text.
	 *
	 * @param string $block    Block name.
	 * @param int    $template Template number.
	 * @return array{label:string,icon:string,help:string}
	 */
	public static function block( $block, $template ) {
		$help = array(
			'hero'             => __( 'Image should be 16:9 proportions and less than 1 MB. Ideal 2000 x 1125 px at 72dpi, JPG.', 'ww-projects' ),
			'description'      => __( 'Approximately 100 words in 2-3 paragraphs. We recommend not including bullets in this description.', 'ww-projects' ),
			'stat'             => __( 'For example: "Hunter\'s Shop \'n Save owners credit the integrated mechanical system with reducing their electric bills by 66%." Recommend no more than 20 words.', 'ww-projects' ),
			'content_block'    => __( 'Approximately 100 words in 2-3 paragraphs with a short headline. We recommend not including bullets in this description.', 'ww-projects' ),
			'quote'            => __( 'Include quote/testimonial here. All quotes should have quotation marks included. If left blank, a quote will not appear on the website.', 'ww-projects' ),
			'overview'         => __( 'Categories left blank will not show on the website.', 'ww-projects' ),
			'slider'           => __( 'Upload 3-5 images. Image should be 3:2 proportions and less than 1 MB. Ideal 1000 x 667 px at 72dpi, JPG.', 'ww-projects' ),
			'single_image_3x2' => __( 'Upload one image. 3:2 proportions, under 1 MB. Ideal 1000 x 667 px at 72dpi, JPG.', 'ww-projects' ),
			'single_image_1x1' => __( 'Upload one image. 1:1 proportions, under 1 MB. Ideal 1000 x 1000 px at 72dpi, JPG.', 'ww-projects' ),
			'grid_2'           => __( 'Upload 3 images. Image should be 1:1 (square) proportions and less than 1 MB. Ideal 1000 x 1000 px at 72dpi, JPG.', 'ww-projects' ),
			'grid_3'           => __( 'Upload 4 images. Two images 3:2 proportions, the other two 2:3 proportions. All under 1 MB, 72dpi, JPG.', 'ww-projects' ),
		);

		$labels = array(
			'title'            => __( 'Headline / Project Name', 'ww-projects' ),
			'hero'             => __( 'Hero Image', 'ww-projects' ),
			'subhead'          => __( 'Subhead', 'ww-projects' ),
			'location'         => __( 'Location', 'ww-projects' ),
			'description'      => __( 'Project Description', 'ww-projects' ),
			'markets_services' => __( 'Markets + Services', 'ww-projects' ),
			'slider'           => __( 'Image Slider', 'ww-projects' ),
			'grid'             => __( 'Image Grid', 'ww-projects' ),
			'overview'         => __( 'Project Overview', 'ww-projects' ),
			'stat'             => __( 'Subhead or Statistic', 'ww-projects' ),
			'content_block'    => __( 'Content Block', 'ww-projects' ),
			'single_image'     => __( 'Single Image', 'ww-projects' ),
			'quote'            => __( 'Quote/Testimonial', 'ww-projects' ),
		);

		$icons = array(
			'title'            => 'heading',
			'hero'             => 'image',
			'subhead'          => 'heading',
			'location'         => 'text',
			'description'      => 'rich',
			'markets_services' => 'list',
			'slider'           => 'images',
			'grid'             => 'images',
			'overview'         => 'list',
			'stat'             => 'heading',
			'content_block'    => 'rich',
			'single_image'     => 'image',
			'quote'            => 'quote',
		);

		$help_key = $block;
		if ( 'single_image' === $block ) {
			$help_key = ( 3 === (int) $template ) ? 'single_image_1x1' : 'single_image_3x2';
		} elseif ( 'grid' === $block ) {
			$help_key = ( 3 === (int) $template ) ? 'grid_3' : 'grid_2';
		}

		return array(
			'label' => isset( $labels[ $block ] ) ? $labels[ $block ] : $block,
			'icon'  => isset( $icons[ $block ] ) ? $icons[ $block ] : 'text',
			'help'  => isset( $help[ $help_key ] ) ? $help[ $help_key ] : '',
		);
	}

	/**
	 * Labels of stored fields this template does not display.
	 *
	 * @param int $template Template number.
	 * @param int $post_id  Post ID.
	 * @return string[]
	 */
	public static function unused_labels( $template, $post_id ) {
		$used   = self::meta_keys( $template );
		$labels = array();
		foreach ( self::all_meta_keys() as $key ) {
			if ( in_array( $key, $used, true ) || ! self::is_filled( $key, $post_id ) ) {
				continue;
			}
			$labels[] = self::key_label( $key );
		}
		return $labels;
	}

	/**
	 * Whether a saved value should count as "kept but unused".
	 *
	 * @param string $key     Meta key without prefix.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function is_filled( $key, $post_id ) {
		$value = get_post_meta( $post_id, '_ww_' . $key, true );
		if ( in_array( $key, array( 'hero', 'single_image' ), true ) ) {
			return is_array( $value ) && ! empty( $value['id'] );
		}
		if ( in_array( $key, array( 'slider', 'grid', 'key_contacts' ), true ) ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( ! empty( $row['id'] ) || ! empty( $row['person_id'] ) || ( isset( $row['text'] ) && '' !== trim( (string) $row['text'] ) ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return ! empty( $value );
		}
		$text = trim( wp_strip_all_tags( str_replace( '&nbsp;', ' ', $value ) ) );
		return '' !== $text;
	}

	/**
	 * Every meta key the registry knows about.
	 *
	 * @return string[]
	 */
	public static function all_meta_keys() {
		return array(
			'hero',
			'subhead',
			'location',
			'description',
			'client',
			'type',
			'size',
			'program',
			'components',
			'key_contacts',
			'recognition',
			'stat',
			'content_block',
			'single_image',
			'slider',
			'grid',
			'quote',
		);
	}

	/**
	 * Meta keys owned by one block.
	 *
	 * @param string $block    Block name.
	 * @param int    $template Template number.
	 * @return string[]
	 */
	private static function block_keys( $block, $template ) {
		if ( 'overview' === $block ) {
			return self::overview_keys( $template );
		}
		if ( in_array( $block, array( 'title', 'markets_services' ), true ) ) {
			return array();
		}
		return array( $block );
	}

	/**
	 * Slickplan block label to field key, for one template.
	 *
	 * Overview rows are included under their own labels. Title is the post title.
	 * Markets and services are term assignments, not meta.
	 *
	 * @param int $template Template number.
	 * @return array<string,string>
	 */
	public static function slickplan_labels( $template ) {
		$template = (int) $template;
		$blocks   = self::blocks( $template );
		if ( ! $blocks ) {
			return array();
		}
		$map = array(
			'Headline / Project Name' => 'title',
			'Hero Image'              => 'hero',
			'Subhead'                 => 'subhead',
			'Location'                => 'location',
			'Project Description'     => 'description',
			'Markets + Services'      => 'markets_services',
			'Image Slider'            => 'slider',
			'Image Grid'              => 'grid',
			'Project Overview'        => 'overview',
			'Subhead or Statistic'    => 'stat',
			'Content Block'           => 'content_block',
			'Single Image'            => 'single_image',
			'Quote/Testimonial'       => 'quote',
		);
		$out = array();
		foreach ( $map as $label => $key ) {
			if ( in_array( $key, $blocks, true ) ) {
				$out[ $label ] = $key;
			}
		}
		if ( in_array( 'overview', $blocks, true ) ) {
			$overview = array(
				'Client'       => 'client',
				'Type'         => 'type',
				'Size'         => 'size',
				'Program'             => 'program',
				'Program Components'  => 'components',
				'Project Components'  => 'components',
				'Key Contact'         => 'key_contacts',
				'Key Contacts' => 'key_contacts',
				'Recognition'  => 'recognition',
			);
			$used = self::overview_keys( $template );
			foreach ( $overview as $label => $key ) {
				if ( in_array( $key, $used, true ) ) {
					$out[ $label ] = $key;
				}
			}
		}
		return $out;
	}
}
