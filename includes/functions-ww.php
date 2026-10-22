<?php
/**
 * Template helpers. Names stay in the ww_ prefix.
 *
 * @package WW\HarrimanProjects
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a stored flag value is on.
 *
 * @param mixed $value Meta value.
 * @return bool
 */
function ww_flag_is_on( $value ) {
	return true === $value || 1 === $value || '1' === $value;
}

/**
 * Whether this project uses the new templates.
 *
 * A missing flag counts as on. An explicit false is a revert.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return bool
 */
function ww_uses_new_template( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( WW_POST_TYPE !== get_post_type( $post_id ) ) {
		return false;
	}
	return ww_flag_is_on( get_post_meta( $post_id, '_ww_use_new_template', true ) );
}

/**
 * Active template number, or 0 when this project still uses the current layout
 * or no card has been chosen.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return int
 */
function ww_active_template( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! ww_uses_new_template( $post_id ) ) {
		return 0;
	}
	$template = (int) get_post_meta( $post_id, '_ww_template', true );
	return in_array( $template, array( 1, 2, 3 ), true ) ? $template : 0;
}

/**
 * Template number while rendering. Zero outside a flagged project.
 *
 * @return int
 */
function ww_template_number() {
	return ww_active_template( get_the_ID() );
}

/**
 * Slickplan term name to an existing term name.
 *
 * @return array<string,string>
 */
function ww_term_aliases() {
	$defaults = array(
		'Commercial + Mixed Use' => 'Commercial / Mixed-Use',
		'Community + Cultural'   => 'Community Civic + Cultural',
		'Pre-K – 12 Education'   => 'Pre-K and K-12 Education',
		'Science + Technology'   => 'Science + Research',
		'Community + Civic'      => 'Community Civic + Cultural',
		'PreK-12 Education'      => 'Pre-K and K-12 Education',
	);
	$aliases  = apply_filters( 'ww_term_aliases', $defaults );
	return is_array( $aliases ) ? $aliases : $defaults;
}

/**
 * Abbreviations expanded before project titles are compared.
 *
 * @return array<string,string>
 */
function ww_title_abbreviations() {
	$defaults = array(
		'UNH'  => 'University of New Hampshire',
		'USM'  => 'University of Southern Maine',
		'NMCC' => 'Northern Maine Community College',
		'CMCC' => 'Central Maine Community College',
		'MMA'  => 'Maine Maritime Academy',
		'EMCC' => 'Eastern Maine Community College',
	);
	$map = apply_filters( 'ww_title_abbreviations', $defaults );
	return is_array( $map ) ? $map : $defaults;
}

/**
 * Raw meta for a registry key.
 *
 * @param string $key     Key without the _ww_ prefix.
 * @param int    $post_id Post ID.
 * @return mixed
 */
function ww_meta( $key, $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$all     = ww_meta_cache( $post_id );
	$meta_key = '_ww_' . $key;
	if ( ! isset( $all[ $meta_key ][0] ) ) {
		return '';
	}
	return maybe_unserialize( $all[ $meta_key ][0] );
}

/**
 * All meta for one post, loaded once per request.
 *
 * @param int $post_id Post ID.
 * @return array<string,array<int,mixed>>
 */
function ww_meta_cache( $post_id ) {
	static $cache = array();
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return array();
	}
	if ( ! isset( $cache[ $post_id ] ) ) {
		$all = get_post_meta( $post_id );
		$cache[ $post_id ] = is_array( $all ) ? $all : array();
	}
	return $cache[ $post_id ];
}

/**
 * Load every project image attachment before the template loops over them.
 *
 * @param int $post_id Project ID.
 */
function ww_prime_project_media( $post_id ) {
	$ids = array();
	foreach ( array( 'hero', 'single_image' ) as $key ) {
		$image = ww_meta( $key, $post_id );
		if ( is_array( $image ) && ! empty( $image['id'] ) ) {
			$ids[] = (int) $image['id'];
		}
	}
	foreach ( array( 'slider', 'grid' ) as $key ) {
		$rows = ww_meta( $key, $post_id );
		if ( ! is_array( $rows ) ) {
			continue;
		}
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$ids[] = (int) $row['id'];
			}
		}
	}
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
	}
}

/**
 * Front-end img tag with srcset. The hero is eager. Every other image is lazy.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $alt           Alt text.
 * @param string $role          hero or photo.
 * @param string $class         Extra class names.
 * @return string
 */
function ww_project_image_html( $attachment_id, $alt, $role, $class = 'ww-project__img' ) {
	$hero  = ( 'hero' === $role );
	$attrs = array(
		'class'    => $class,
		'alt'      => $alt,
		'decoding' => 'async',
		'sizes'    => $hero ? '(min-width: 1512px) 1512px, 100vw' : '(max-width: 899px) 100vw, 720px',
	);
	if ( $hero ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'high';
	} else {
		$attrs['loading'] = 'lazy';
	}
	return wp_get_attachment_image( (int) $attachment_id, 'large', false, $attrs );
}

/**
 * Strip tags and Word markup from a single-line field.
 *
 * @param string $value Raw text.
 * @return string
 */
function ww_clean_plain( $value ) {
	$value = (string) $value;
	$value = preg_replace( '/<\s*\/?\s*o:p\b[^>]*>/i', '', $value );
	$value = preg_replace( '/\sclass=(["\'])MsoNormal\1/i', '', $value );
	$value = wp_strip_all_tags( $value );
	$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
	$value = str_replace( "\xc2\xa0", ' ', $value );
	return trim( $value );
}

/**
 * Heading tags and the overview label style, filterable per template.
 *
 * @return array<int,array<string,string>>
 */
function ww_heading_levels() {
	$levels   = array(
		1 => array(
			'title'    => 'h1',
			'subhead'  => 'h3',
			'location' => 'h4',
			'stat'     => 'h3',
			'overview' => 'h6',
		),
		2 => array(
			'title'    => 'h1',
			'subhead'  => 'h2',
			'location' => 'h4',
			'stat'     => 'h3',
			'overview' => 'h6',
		),
		3 => array(
			'title'    => 'h1',
			'subhead'  => 'h2',
			'location' => 'h4',
			'stat'     => 'h3',
			'overview' => 'h6',
		),
	);
	$filtered = apply_filters( 'ww_heading_levels', $levels );
	return is_array( $filtered ) ? $filtered : $levels;
}

/**
 * A safe tag for one heading slot.
 *
 * @param string $part title|subhead|location|stat|overview.
 * @return string
 */
function ww_heading_tag( $part ) {
	$levels   = ww_heading_levels();
	$template = ww_template_number();
	$tag      = 'p';
	if ( isset( $levels[ $template ][ $part ] ) ) {
		$tag = strtolower( (string) $levels[ $template ][ $part ] );
	}
	$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'strong' );
	return in_array( $tag, $allowed, true ) ? $tag : 'p';
}

/**
 * Picker images. Keys 1, 2, and 3 are the template numbers.
 *
 * @return string[]
 */
function ww_template_card_images() {
	$images   = array(
		1 => WW_PLUGIN_URL . 'assets/img/Template1.svg',
		2 => WW_PLUGIN_URL . 'assets/img/Template2.svg',
		3 => WW_PLUGIN_URL . 'assets/img/Template3.svg',
	);
	$filtered = apply_filters( 'ww_template_card_images', $images );
	if ( ! is_array( $filtered ) ) {
		return $images;
	}
	$normalized = array();
	foreach ( array( 1, 2, 3 ) as $template ) {
		if ( isset( $filtered[ $template ] ) && is_string( $filtered[ $template ] ) ) {
			$normalized[ $template ] = $filtered[ $template ];
		}
	}
	if ( 3 === count( $normalized ) ) {
		return $normalized;
	}
	$values = array_values( $filtered );
	foreach ( array( 1, 2, 3 ) as $index => $template ) {
		if ( isset( $values[ $index ] ) && is_string( $values[ $index ] ) ) {
			$normalized[ $template ] = $values[ $index ];
		} else {
			$normalized[ $template ] = $images[ $template ];
		}
	}
	return $normalized;
}

/**
 * Rich text with no visible characters.
 *
 * @param string $html HTML.
 * @return bool
 */
function ww_is_blank( $html ) {
	$text = html_entity_decode( (string) $html, ENT_QUOTES, 'UTF-8' );
	$text = str_replace( "\xc2\xa0", ' ', $text );
	$text = trim( wp_strip_all_tags( $text ) );
	return '' === $text;
}

/**
 * Editor prompt lines that should not render.
 *
 * @param string $value Plain text.
 * @return bool
 */
function ww_is_placeholder( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $value ) {
		return true;
	}
	$needles = array(
		'Include quote/testimonial here',
		'Add short headline and content here.',
		'Single sentence statistic here.',
		'Single sentence subhead here.',
		'Project description copy goes here.',
		'Project Name',
		'City, State',
	);
	foreach ( $needles as $needle ) {
		if ( $value === $needle || 0 === strpos( $value, $needle ) ) {
			return true;
		}
	}
	return (bool) preg_match( '/^\s*[\x{2013}\x{2014}]{2,}.*[\x{2013}\x{2014}]{2,}\s*$/u', $value );
}

/**
 * Front-end HTML with empty inline tags removed. Saved data is unchanged.
 *
 * @param string $html Stored HTML.
 * @return string
 */
function ww_output_html( $html ) {
	$html = str_replace( array( '&nbsp;', '&#160;', "\xc2\xa0" ), ' ', (string) $html );
	if ( ww_is_blank( $html ) || ww_is_placeholder( wp_strip_all_tags( $html ) ) ) {
		return '';
	}
	$previous = '';
	while ( $previous !== $html ) {
		$previous = $html;
		$html     = (string) preg_replace( '/<(strong|em|b|i|span)\b[^>]*>\s*<\/\1>/i', '', $html );
	}
	$html = trim( $html );
	if ( ww_is_blank( $html ) ) {
		return '';
	}
	return wp_kses_post( $html );
}

/**
 * A single-line field, or an empty string when it is blank or a prompt.
 *
 * @param string $key Meta key.
 * @return string
 */
function ww_plain_value( $key ) {
	$value = ww_clean_plain( (string) ww_meta( $key ) );
	if ( '' === $value || ww_is_placeholder( $value ) ) {
		return '';
	}
	return $value;
}

/**
 * Single stored image, or null.
 *
 * @param string $key Meta key.
 * @return array{id:int,alt:string,caption:string}|null
 */
function ww_image_meta( $key ) {
	$value = ww_meta( $key );
	if ( ! is_array( $value ) || empty( $value['id'] ) ) {
		return null;
	}
	return array(
		'id'      => (int) $value['id'],
		'alt'     => isset( $value['alt'] ) ? (string) $value['alt'] : '',
		'caption' => isset( $value['caption'] ) ? (string) $value['caption'] : '',
	);
}

/**
 * Stored image repeater.
 *
 * @param string $key slider|grid.
 * @return array<int,array{id:int,alt:string,caption:string}>
 */
function ww_image_list( $key ) {
	$value = ww_meta( $key );
	if ( ! is_array( $value ) ) {
		return array();
	}
	$images = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			continue;
		}
		$images[] = array(
			'id'      => (int) $row['id'],
			'alt'     => isset( $row['alt'] ) ? (string) $row['alt'] : '',
			'caption' => isset( $row['caption'] ) ? (string) $row['caption'] : '',
		);
	}
	return $images;
}

/**
 * Start photo numbers over. Called once per project render.
 */
function ww_image_alt_reset() {
	ww_image_alt_number( 0 );
}

/**
 * Next photo number, or set the counter when $set is not null.
 *
 * @param int|null $set Value to store, or null to increment.
 * @return int
 */
function ww_image_alt_number( $set = null ) {
	static $number = 0;
	if ( null !== $set ) {
		$number = (int) $set;
		return $number;
	}
	++$number;
	return $number;
}

/**
 * Alt text for one front-end project image.
 *
 * The per-image override wins, then the attachment alt. When both are empty
 * the fallback is the project title for the hero and "{title}, photo {n}"
 * for every later image, counted in render order. ww_image_alt_fallback can
 * replace that fallback. Attachment meta is only read.
 *
 * @param array{id:int,alt?:string,caption?:string} $image Image row.
 * @param string                                     $role  hero or photo.
 * @return string
 */
function ww_image_alt( array $image, $role ) {
	$attachment_id = (int) $image['id'];
	$alt           = trim( wp_strip_all_tags( (string) ( $image['alt'] ?? '' ) ) );
	if ( '' === $alt && $attachment_id > 0 ) {
		$alt = trim( wp_strip_all_tags( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
	}
	$is_hero = ( 'hero' === $role );
	$number  = $is_hero ? 0 : ww_image_alt_number();
	if ( '' !== $alt ) {
		return $alt;
	}
	$title = get_post_field( 'post_title', get_the_ID(), 'raw' );
	$title = trim( html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ) );
	if ( $is_hero ) {
		$fallback = $title;
	} else {
		$fallback = sprintf(
			/* translators: 1: project title, 2: photo number after the hero. */
			__( '%1$s, photo %2$d', 'ww-projects' ),
			$title,
			$number
		);
	}
	$filtered = apply_filters(
		'ww_image_alt_fallback',
		$fallback,
		array(
			'post_id'       => (int) get_the_ID(),
			'title'         => $title,
			'role'          => $is_hero ? 'hero' : 'photo',
			'number'        => $number,
			'attachment_id' => $attachment_id,
		)
	);
	return trim( (string) $filtered );
}

/**
 * Term archive URL when the taxonomy has a public archive.
 *
 * @param \WP_Term $term Term.
 * @return string
 */
function ww_term_archive_url( $term ) {
	$taxonomy = get_taxonomy( $term->taxonomy );
	if ( ! $taxonomy || empty( $taxonomy->public ) || empty( $taxonomy->rewrite ) ) {
		return '';
	}
	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return '';
	}
	return (string) $link;
}

/**
 * Print a heading when the text is not empty.
 *
 * @param string $part  Heading slot.
 * @param string $text  Text.
 * @param string $class CSS class.
 */
function ww_render_heading( $part, $text, $class ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return;
	}
	$tag = ww_heading_tag( $part );
	if ( 'strong' === $tag ) {
		echo '<p class="' . esc_attr( $class ) . '"><strong>' . esc_html( $text ) . '</strong></p>';
		return;
	}
	echo '<' . $tag . ' class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</' . $tag . '>';
}

/**
 * Project title.
 */
function ww_render_title() {
	ww_render_heading( 'title', get_the_title(), 'ww-project__title' );
}

/**
 * Hero image.
 */
function ww_render_hero() {
	$image = ww_image_meta( 'hero' );
	if ( ! $image ) {
		return;
	}
	$ww_image        = $image;
	$ww_image_role   = 'hero';
	$ww_figure_class = 'ww-project__hero';
	include WW_PLUGIN_DIR . 'templates/parts/image.php';
}

/**
 * Subhead.
 */
function ww_render_subhead() {
	ww_render_heading( 'subhead', ww_plain_value( 'subhead' ), 'ww-project__subhead' );
}

/**
 * Location line.
 */
function ww_render_location() {
	ww_render_heading( 'location', ww_location_text(), 'ww-project__location' );
}

/**
 * Assigned project-location names, in name order.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return string[]
 */
function ww_location_names( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) {
		return array();
	}
	$terms = wp_get_object_terms(
		$post_id,
		'project-location',
		array(
			'orderby' => 'name',
			'order'   => 'ASC',
		)
	);
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$names = array();
	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && '' !== trim( $term->name ) ) {
			$names[] = $term->name;
		}
	}
	return $names;
}

/**
 * Location line from the assigned terms. Stored _ww_location is not used.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return string
 */
function ww_location_text( $post_id = 0 ) {
	return implode( ' / ', ww_location_names( $post_id ) );
}

/**
 * Project description.
 */
function ww_render_description() {
	$html = ww_output_html( (string) ww_meta( 'description' ) );
	if ( '' === $html ) {
		return;
	}
	echo '<div class="ww-project__description">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ww_output_html() uses wp_kses_post().
}

/**
 * Selected markets and services.
 */
function ww_render_terms() {
	$markets  = ww_term_items( 'market' );
	$services = ww_term_items( 'service' );
	if ( '' === $markets && '' === $services ) {
		return;
	}
	echo '<div class="ww-project__terms">';
	if ( '' !== $markets ) {
		echo '<ul class="ww-project__markets">' . $markets . '</ul>';
	}
	if ( '' !== $services ) {
		echo '<ul class="ww-project__services">' . $services . '</ul>';
	}
	echo '</div>';
}

/**
 * List items for one taxonomy. Already escaped.
 *
 * @param string $taxonomy Taxonomy name.
 * @return string
 */
function ww_term_items( $taxonomy ) {
	$terms = get_the_terms( get_the_ID(), $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$html = '';
	foreach ( $terms as $term ) {
		$name = esc_html( $term->name );
		$link = ww_term_archive_url( $term );
		if ( $link ) {
			$html .= '<li><a href="' . esc_url( $link ) . '">' . $name . '</a></li>';
		} else {
			$html .= '<li>' . $name . '</li>';
		}
	}
	return $html;
}

/**
 * Slider or grid.
 *
 * @param string $kind slider|grid.
 */
function ww_render_gallery( $kind ) {
	$key    = ( 'grid' === $kind ) ? 'grid' : 'slider';
	$images = ww_image_list( $key );
	if ( ! $images ) {
		return;
	}
	$ww_images        = $images;
	$ww_gallery_kind  = ( 'grid' === $kind ) ? 'grid' : 'slider';
	$ww_gallery_count = count( $images );
	include WW_PLUGIN_DIR . 'templates/parts/gallery.php';
}

/**
 * Project overview.
 */
function ww_render_overview() {
	include WW_PLUGIN_DIR . 'templates/parts/overview.php';
}

/**
 * Statistic line.
 */
function ww_render_stat() {
	$text = ww_plain_value( 'stat' );
	if ( '' === $text ) {
		return;
	}
	echo '<section class="ww-project__stat-wrap">';
	ww_render_heading( 'stat', $text, 'ww-project__stat' );
	echo '</section>';
}

/**
 * Second rich text block.
 */
function ww_render_content_block() {
	$html = ww_output_html( (string) ww_meta( 'content_block' ) );
	if ( '' === $html ) {
		return;
	}
	echo '<div class="ww-project__content">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ww_output_html() uses wp_kses_post().
}

/**
 * The single image block.
 */
function ww_render_single_image() {
	$image = ww_image_meta( 'single_image' );
	if ( ! $image ) {
		return;
	}
	$ww_image        = $image;
	$ww_image_role   = 'photo';
	$ww_figure_class = 'ww-project__single';
	include WW_PLUGIN_DIR . 'templates/parts/image.php';
}

/**
 * Quote. The editor stores the quotation marks.
 */
function ww_render_quote() {
	$quote = trim( (string) ww_meta( 'quote' ) );
	if ( '' === $quote || ww_is_placeholder( $quote ) ) {
		return;
	}
	$lines = array();
	foreach ( preg_split( "/\r\n|\n|\r/", $quote ) as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line || ww_is_placeholder( $line ) ) {
			continue;
		}
		$lines[] = $line;
	}
	if ( ! $lines ) {
		return;
	}
	$first = array_shift( $lines );
	echo '<blockquote class="ww-project__quote">';
	echo '<p class="ww-project__quote-text">' . esc_html( $first ) . '</p>';
	if ( $lines ) {
		echo '<div class="ww-project__quote-rule" aria-hidden="true"></div>';
		echo '<p class="ww-project__quote-attr">' . nl2br( esc_html( implode( "\n", $lines ) ) ) . '</p>';
	}
	echo '</blockquote>';
}

/**
 * Escaped multiline text, or an empty string.
 *
 * @param string $key Meta key.
 * @return string
 */
function ww_overview_text( $key ) {
	$value = trim( (string) ww_meta( $key ) );
	if ( '' === $value || ww_is_placeholder( $value ) ) {
		return '';
	}
	return nl2br( esc_html( $value ) );
}

/**
 * Key contact lines. Person rows link to the leadership page.
 *
 * @return string
 */
function ww_overview_contacts() {
	$rows = ww_meta( 'key_contacts' );
	if ( ! is_array( $rows ) ) {
		return '';
	}
	$lines = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$person_id = isset( $row['person_id'] ) ? (int) $row['person_id'] : 0;
		$text      = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
		if ( '' !== $text && ww_is_placeholder( $text ) ) {
			$text = '';
		}
		if ( $person_id && WW_PEOPLE_POST_TYPE === get_post_type( $person_id ) && 'publish' === get_post_status( $person_id ) ) {
			$lines[] = '<a href="' . esc_url( get_permalink( $person_id ) ) . '">' . esc_html( get_the_title( $person_id ) ) . '</a>';
		} elseif ( '' !== $text ) {
			$lines[] = esc_html( $text );
		}
	}
	return implode( '<br>', $lines );
}

/**
 * One overview row, or an empty string.
 *
 * @param string $label      Row label.
 * @param string $value_html Escaped value HTML.
 * @return string
 */
function ww_overview_row( $label, $value_html ) {
	if ( '' === $value_html ) {
		return '';
	}
	$tag = ww_heading_tag( 'overview' );
	if ( 'strong' === $tag ) {
		return '<div class="ww-project__overview-row"><strong>' . esc_html( $label ) . ':</strong> ' . $value_html . '</div>';
	}
	return '<div class="ww-project__overview-row"><' . $tag . '>' . esc_html( $label ) . '</' . $tag . '><p>' . $value_html . '</p></div>';
}

/**
 * Three crumbs: Harriman, Work, and the project title.
 */
function ww_render_breadcrumb() {
	echo '<nav class="ww-project__breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'ww-projects' ) . '">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Harriman', 'ww-projects' ) . '</a>';
	echo '<span class="ww-project__breadcrumb-sep" aria-hidden="true"> › </span>';
	echo '<a href="' . esc_url( home_url( '/projects/' ) ) . '">' . esc_html__( 'Work', 'ww-projects' ) . '</a>';
	echo '<span class="ww-project__breadcrumb-sep" aria-hidden="true"> › </span>';
	echo '<span class="ww-project__breadcrumb-current">' . esc_html( get_the_title() ) . '</span>';
	echo '</nav>';
}

/**
 * Gray header band, plus the hero when one is set.
 */
function ww_render_header() {
	$hero  = ww_image_meta( 'hero' );
	$class = 'ww-project__header';
	if ( $hero ) {
		$class .= ' ww-project__header--hero';
	}
	echo '<header class="' . esc_attr( $class ) . '">';
	echo '<div class="ww-project__band">';
	ww_render_breadcrumb();
	ww_render_title();
	echo '</div>';
	if ( $hero ) {
		echo '<div class="ww-project__hero-wrap">';
		ww_render_hero();
		echo '</div>';
	}
	echo '</header>';
}

/**
 * Whether this project has a market or service term.
 *
 * @return bool
 */
function ww_project_has_terms() {
	foreach ( array( 'market', 'service' ) as $taxonomy ) {
		$terms = get_the_terms( get_the_ID(), $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Yellow mobile toggle. Hidden in CSS until the script marks the page.
 *
 * @param string $label    Visible label.
 * @param string $controls Panel id.
 */
function ww_render_toggle( $label, $controls ) {
	echo '<button type="button" class="ww-project__toggle" aria-expanded="true" aria-controls="' . esc_attr( $controls ) . '">';
	echo '<span class="ww-project__toggle-label">' . esc_html( $label ) . '</span>';
	echo '<span class="ww-project__toggle-icon" aria-hidden="true"><svg width="14" height="8" viewBox="0 0 14 8" focusable="false"><path d="M1 1.5 7 6.5 13 1.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></span>';
	echo '</button>';
}

/**
 * Intro columns. Omitted when subhead, location, tags, and description are all empty.
 */
function ww_render_intro() {
	$has_main    = '' !== ww_plain_value( 'subhead' ) || '' !== ww_location_text() || ww_project_has_terms();
	$description = ww_output_html( (string) ww_meta( 'description' ) );
	if ( ! $has_main && '' === $description ) {
		return;
	}
	$class = 'ww-project__intro';
	if ( ! $has_main || '' === $description ) {
		$class .= ' ww-project__intro--solo';
	}
	echo '<section class="' . esc_attr( $class ) . '">';
	echo '<div class="ww-project__frame ww-project__intro-grid">';
	if ( $has_main ) {
		echo '<div class="ww-project__intro-main">';
		ww_render_subhead();
		ww_render_location();
		ww_render_terms();
		echo '</div>';
	}
	if ( '' !== $description ) {
		$controls = 'ww-intro-' . (int) get_the_ID();
		echo '<div class="ww-project__intro-text">';
		ww_render_toggle( __( 'Project Introduction', 'ww-projects' ), $controls );
		echo '<div class="ww-project__description" id="' . esc_attr( $controls ) . '">' . $description . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ww_output_html() uses wp_kses_post().
		echo '</div>';
	}
	echo '</div></section>';
}

/**
 * Content block beside the single image. Either one may stand alone.
 */
function ww_render_feature() {
	$html  = ww_output_html( (string) ww_meta( 'content_block' ) );
	$image = ww_image_meta( 'single_image' );
	if ( '' === $html && ! $image ) {
		return;
	}
	$class = 'ww-project__feature';
	if ( '' !== $html && $image ) {
		$class .= ' ww-project__feature--pair';
	} elseif ( '' !== $html ) {
		$class .= ' ww-project__feature--text';
	} else {
		$class .= ' ww-project__feature--image';
	}
	echo '<section class="' . esc_attr( $class ) . '">';
	if ( '' !== $html ) {
		$controls = 'ww-more-' . (int) get_the_ID();
		echo '<div class="ww-project__feature-copy">';
		ww_render_toggle( __( 'Read More', 'ww-projects' ), $controls );
		echo '<div class="ww-project__content" id="' . esc_attr( $controls ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ww_output_html() uses wp_kses_post().
		echo '</div>';
	}
	if ( $image ) {
		ww_render_single_image();
	}
	echo '</section>';
}

/**
 * Template 3 statistic card. Text and image are each optional.
 */
function ww_render_stat_card() {
	$text  = ww_plain_value( 'stat' );
	$image = ww_image_meta( 'single_image' );
	if ( '' === $text && ! $image ) {
		return;
	}
	echo '<section class="ww-project__stat-card">';
	if ( $image ) {
		echo '<div class="ww-project__stat-media">';
		ww_render_single_image();
		echo '</div>';
	}
	if ( '' !== $text ) {
		ww_render_heading( 'stat', $text, 'ww-project__stat' );
	}
	echo '</section>';
}

/**
 * Theme button markup. Outline is the "See our projects" style.
 *
 * @param string $url     Destination.
 * @param string $label   Label.
 * @param bool   $outline Outline style.
 */
function ww_render_button( $url, $label, $outline ) {
	$class = 'wp-block-button';
	if ( $outline ) {
		$class .= ' is-style-outline';
	}
	echo '<div class="wp-block-buttons"><div class="' . esc_attr( $class ) . '"><a class="wp-block-button__link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div></div>';
}

/**
 * Featured image, or the stored hero.
 *
 * @param int $post_id Project ID.
 * @return int
 */
function ww_project_card_image_id( $post_id ) {
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		return $thumb;
	}
	$hero = get_post_meta( $post_id, '_ww_hero', true );
	if ( is_array( $hero ) && ! empty( $hero['id'] ) ) {
		return (int) $hero['id'];
	}
	return 0;
}

/**
 * Transient key. The option version changes on any project save.
 *
 * @param int $post_id Project ID.
 * @return string
 */
function ww_related_cache_key( $post_id ) {
	return 'ww_rel_' . (string) get_option( 'ww_related_cache', '1' ) . '_' . (int) $post_id;
}

/**
 * Drop related-project caches when any project is saved.
 *
 * @param int $post_id Saved post.
 */
function ww_clear_related_cache( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( WW_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	update_option( 'ww_related_cache', (string) microtime( true ), false );
}
add_action( 'save_post', 'ww_clear_related_cache', 20 );
add_action( 'deleted_post', 'ww_clear_related_cache', 20 );
add_action(
	'set_object_terms',
	static function ( $object_id ) {
		ww_clear_related_cache( (int) $object_id );
	},
	20
);

/**
 * Up to three related project IDs, cached for about 12 hours.
 *
 * @param int $post_id Current project.
 * @return int[]
 */
function ww_related_ids( $post_id ) {
	$post_id = (int) $post_id;
	$key     = ww_related_cache_key( $post_id );
	$cached  = get_transient( $key );
	if ( is_array( $cached ) ) {
		return array_values( array_filter( array_map( 'intval', $cached ) ) );
	}
	$ids = ww_query_related_ids( $post_id );
	set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
	return $ids;
}

/**
 * Published projects that share a service, ranked by overlap, then newest.
 *
 * @param int $post_id Current project.
 * @return int[]
 */
function ww_query_related_ids( $post_id ) {
	$terms = wp_get_object_terms( (int) $post_id, 'service', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}
	$terms = array_values( array_filter( array_map( 'intval', (array) $terms ) ) );
	if ( ! $terms ) {
		return array();
	}
	$args = apply_filters(
		'ww_related_projects_args',
		array(
			'post_type'              => WW_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 3,
			'no_found_rows'          => true,
			'fields'                 => 'ids',
			'post__not_in'           => array( (int) $post_id ),
			'ignore_sticky_posts'    => true,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => 'service',
					'field'    => 'term_id',
					'terms'    => $terms,
				),
			),
			'ww_rank_services'       => $terms,
		),
		(int) $post_id
	);
	if ( ! is_array( $args ) ) {
		return array();
	}
	$args['no_found_rows'] = true;
	$args['fields']        = 'ids';
	if ( empty( $args['posts_per_page'] ) || (int) $args['posts_per_page'] < 1 ) {
		$args['posts_per_page'] = 3;
	}

	$filter = static function ( $clauses, $query ) {
		if ( ! $query instanceof WP_Query || ! $query->get( 'ww_rank_services' ) ) {
			return $clauses;
		}
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', (array) $query->get( 'ww_rank_services' ) ) ) );
		if ( ! $ids ) {
			return $clauses;
		}
		$clauses['orderby'] = '(SELECT COUNT(DISTINCT ww_tt.term_id) FROM ' . $wpdb->term_relationships . ' ww_tr INNER JOIN ' . $wpdb->term_taxonomy . " ww_tt ON ww_tt.term_taxonomy_id = ww_tr.term_taxonomy_id AND ww_tt.taxonomy = 'service' AND ww_tt.term_id IN (" . implode( ',', $ids ) . ') WHERE ww_tr.object_id = ' . $wpdb->posts . '.ID) DESC, ' . $wpdb->posts . '.post_date DESC';
		return $clauses;
	};
	add_filter( 'posts_clauses', $filter, 10, 2 );
	$query = new WP_Query( $args );
	remove_filter( 'posts_clauses', $filter, 10 );

	$found = array();
	$ids   = array_values( array_filter( array_map( 'intval', (array) $query->posts ) ) );
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
		update_post_thumbnail_cache( $query );
	}
	foreach ( $ids as $id ) {
		if ( $id > 0 && ww_project_card_image_id( $id ) ) {
			$found[] = $id;
		}
	}
	wp_reset_postdata();
	return $found;
}

/**
 * One related card, or an empty string.
 *
 * @param int $post_id Project ID.
 * @return string
 */
function ww_related_card_html( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || WW_POST_TYPE !== $post->post_type ) {
		return '';
	}
	$image_id = ww_project_card_image_id( $post->ID );
	if ( ! $image_id ) {
		return '';
	}
	$alt = trim( wp_strip_all_tags( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) );
	if ( '' === $alt ) {
		$alt = trim( wp_strip_all_tags( $post->post_title ) );
	}
	$img = wp_get_attachment_image(
		$image_id,
		'large',
		false,
		array(
			'class'    => 'wp-post-image',
			'alt'      => $alt,
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => '(max-width: 899px) 100vw, 480px',
		)
	);
	if ( ! $img ) {
		return '';
	}
	$location = ww_location_text( $post->ID );
	$html  = '<div class="wp-block-anchour-card-block">';
	$html .= '<a class="wp-block-anchour-card-block__link" href="' . esc_url( get_permalink( $post ) ) . '">';
	$html .= '<div class="wp-block-anchour-card-block__header"><div class="wp-block-anchour-card-block__image-wrapper"><div class="wp-block-anchour-card-block__image-scaler">';
	$html .= $img;
	$html .= '</div></div></div>';
	$html .= '<div class="wp-block-anchour-card-block__card-content">';
	$html .= '<h4 class="has-heading-6-font-size">' . esc_html( get_the_title( $post ) ) . '<span class="ww-project__card-arrow" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" focusable="false"><path d="M2 7h9M8 3.5 11.5 7 8 10.5" fill="none" stroke="currentColor" stroke-width="1.4"/></svg></span></h4>';
	if ( '' !== $location ) {
		$html .= '<span class="has-paragraph-sm">' . esc_html( $location ) . '</span>';
	}
	$html .= '</div></a></div>';
	return $html;
}

/**
 * Related projects. Omitted when none qualify.
 */
function ww_render_related() {
	$cards = '';
	foreach ( ww_related_ids( get_the_ID() ) as $id ) {
		$cards .= ww_related_card_html( $id );
	}
	if ( '' === $cards ) {
		return;
	}
	echo '<section class="ww-project__related">';
	echo '<div class="ww-project__frame ww-project__related-layout">';
	echo '<h2 class="ww-project__related-title">' . esc_html__( 'Related Projects', 'ww-projects' ) . '</h2>';
	echo '<div class="ww-project__related-action">';
	ww_render_button( home_url( '/projects/' ), __( 'See our projects', 'ww-projects' ), true );
	echo '</div>';
	echo '<div class="wp-block-anchour-featured-projects">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card HTML is escaped in ww_related_card_html().
	echo '</div></section>';
}

/**
 * Get in Touch reusable block, or our own heading and button.
 */
function ww_render_cta() {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$busy         = true;
	$default_text = __( "We'd love to work on a project together.", 'ww-projects' );
	$default_url  = home_url( '/contact/' );
	$text         = apply_filters( 'ww_cta_text', $default_text );
	$url          = apply_filters( 'ww_cta_url', $default_url );
	$text         = trim( wp_strip_all_tags( (string) $text ) );
	$url          = (string) $url;
	if ( '' === $text ) {
		$text = $default_text;
	}
	$html = '';
	if ( $text === $default_text && $url === $default_url ) {
		$block = get_post( 11273 );
		if ( $block instanceof WP_Post && 'wp_block' === $block->post_type && '' !== trim( (string) $block->post_content ) ) {
			$rendered = do_blocks( '<!-- wp:block {"ref":11273} /-->' );
			if ( is_string( $rendered ) && ! ww_is_blank( $rendered ) ) {
				$html = $rendered;
			}
		}
	}
	echo '<section class="ww-project__cta">';
	if ( '' !== $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() returns rendered block HTML.
	} else {
		echo '<div class="ww-project__frame ww-project__cta-fallback">';
		echo '<h2 class="ww-project__cta-title">' . esc_html( $text ) . '</h2>';
		ww_render_button( $url, __( 'Get in touch', 'ww-projects' ), false );
		echo '</div>';
	}
	echo '</section>';
	$busy = false;
}
