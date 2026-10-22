<?php
/**
 * Read a Slickplan export into project field values. Dry run writes nothing.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Slickplan {

	const TEMPLATES = array(
		'ntgqklyujqem'   => 1,
		'jta9kgzihmry0ba0' => 2,
		'b9o8qbm6'       => 3,
	);

	/**
	 * Folder outside the web root for the Slickplan export.
	 *
	 * @return string
	 */
	public static function private_dir() {
		return dirname( ABSPATH ) . '/ww-private';
	}

	/**
	 * XML files the admin may choose. Basename => absolute path.
	 *
	 * Private copies win over a file of the same name under docs.
	 *
	 * @return array<string,string>
	 */
	public static function xml_files() {
		$dirs = array(
			WW_PLUGIN_DIR . 'docs',
			self::private_dir(),
		);
		$files = array();
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$found = glob( trailingslashit( $dir ) . '*.xml' );
			if ( ! is_array( $found ) ) {
				continue;
			}
			foreach ( $found as $path ) {
				if ( is_readable( $path ) ) {
					$files[ basename( $path ) ] = $path;
				}
			}
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Resolve a chosen basename to a readable file inside the allowed directories.
	 *
	 * @param string $basename File name.
	 * @return string|\WP_Error
	 */
	public static function xml_path( $basename ) {
		$basename = basename( str_replace( '\\', '/', (string) $basename ) );
		$files    = self::xml_files();
		if ( isset( $files[ $basename ] ) ) {
			return $files[ $basename ];
		}
		$wanted = sanitize_file_name( $basename );
		foreach ( $files as $name => $path ) {
			if ( sanitize_file_name( $name ) === $wanted ) {
				return $path;
			}
		}
		return new \WP_Error( 'ww_xml', __( 'That XML file was not found in the private import folder.', 'ww-projects' ) );
	}

	/**
	 * Start a fresh image-download budget for one Apply request.
	 */
	public static function reset_download_budget() {
		self::$download_count = 0;
	}

	/**
	 * Forget image failures from an earlier Apply click.
	 */
	public static function reset_failed_images() {
		self::$failed_images = array();
		update_option( 'ww_slickplan_failed_images', array(), false );
	}

	/**
	 * Parse, match, and compare with the manifest. Does not write.
	 *
	 * @param string $path Absolute XML path.
	 * @return array|\WP_Error
	 */
	public static function dry_run( $path ) {
		$parsed = self::parse_file( $path );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$rows = array();
		foreach ( $parsed['projects'] as $project ) {
			$rows[] = self::match_project( $project );
		}
		return array(
			'counts'   => $parsed['counts'],
			'stubs'    => $parsed['stubs'],
			'manifest' => self::manifest_diff( $parsed ),
			'rows'     => $rows,
		);
	}

	/**
	 * A fresh Tools → Project backup is required before apply.
	 *
	 * @return bool
	 */
	public static function backup_is_fresh() {
		$path = (string) get_option( 'ww_project_backup_file', '' );
		if ( '' === $path || ! is_readable( $path ) ) {
			return false;
		}
		$mtime = filemtime( $path );
		return false !== $mtime && ( time() - $mtime ) < DAY_IN_SECONDS;
	}

	/**
	 * Write one checked dry-run row.
	 *
	 * @param array $row     Stored dry-run row.
	 * @param int   $post_id Existing project, or 0 to create a draft.
	 * @return array|\WP_Error
	 */
	public static function apply_row( array $row, $post_id ) {
		if ( ! self::backup_is_fresh() ) {
			return new \WP_Error( 'ww_backup', __( 'Run Tools → Project backup in the last 24 hours before applying.', 'ww-projects' ) );
		}
		if ( ! empty( $row['flag_off'] ) ) {
			return new \WP_Error( 'ww_flag', __( 'This project is explicitly reverted. It was left alone.', 'ww-projects' ) );
		}

		$post_id = (int) $post_id;
		if ( self::download_budget_spent() && self::missing_images( $row ) ) {
			return array(
				'post_id' => $post_id,
				'created' => false,
				'status'  => 'deferred',
				'images'  => array(),
			);
		}

		$created = false;
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || WW_POST_TYPE !== $post->post_type ) {
				return new \WP_Error( 'ww_post', __( 'The selected project was not found.', 'ww-projects' ) );
			}
		} else {
			$title = '' !== $row['headline'] ? $row['headline'] : $row['sitemap_title'];
			$post_id = wp_insert_post(
				array(
					'post_type'    => WW_POST_TYPE,
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_content' => '',
					'post_author'  => get_current_user_id(),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			$post_id = (int) $post_id;
			$created = true;
			update_post_meta( $post_id, '_ww_import_created', '1' );
		}

		$fields      = self::fields_for_save( $row, $post_id );
		$image_notes = isset( $fields['_image_notes'] ) ? $fields['_image_notes'] : array();
		$pending     = ! empty( $fields['_image_pending'] );
		unset( $fields['_image_notes'], $fields['_image_pending'] );
		if ( $pending ) {
			return array(
				'post_id' => $post_id,
				'created' => $created,
				'status'  => 'downloading',
				'images'  => $image_notes,
			);
		}

		$post     = get_post( $post_id );
		$previous = self::capture_previous( $post_id, $row, $created, $fields );
		self::snapshot_legacy( $post );

		Save::$importing = true;
		if ( ! $created && ! empty( $row['update_title'] ) && '' !== $row['title_new'] && $row['title_new'] !== $post->post_title ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $row['title_new'],
				)
			);
		}
		Save::save_template_fields( $post_id, (int) $row['template'], $fields );
		update_post_meta( $post_id, '_ww_slickplan_page_id', $row['cell_id'] );
		update_post_meta( $post_id, '_ww_import_prev', wp_json_encode( $previous ) );

		if ( get_post_meta( $post_id, '_ww_import_created', true ) && ! empty( $fields['hero']['id'] ) && ! has_post_thumbnail( $post_id ) ) {
			set_post_thumbnail( $post_id, (int) $fields['hero']['id'] );
		}
		Save::$importing = false;

		return array(
			'post_id' => $post_id,
			'created' => $created,
			'status'  => $created ? 'created draft' : 'updated',
			'images'  => $image_notes,
		);
	}

	/**
	 * Restore the values saved before the last apply.
	 *
	 * A draft this import created is moved to the trash. Anything else is left in place.
	 *
	 * @param int $post_id Project ID.
	 * @return array|\WP_Error
	 */
	public static function revert_post( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || WW_POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'ww_post', __( 'Project not found.', 'ww-projects' ) );
		}
		if ( get_post_meta( $post_id, '_ww_import_created', true ) ) {
			if ( 'draft' === $post->post_status ) {
				wp_trash_post( $post_id );
				return array( 'status' => 'trashed' );
			}
			return array( 'status' => 'left in place because it is no longer a draft' );
		}

		$raw  = (string) get_post_meta( $post_id, '_ww_import_prev', true );
		$prev = json_decode( $raw, true );
		if ( ! is_array( $prev ) ) {
			return new \WP_Error( 'ww_prev', __( 'This project has no import snapshot to restore.', 'ww-projects' ) );
		}

		Save::$importing = true;
		if ( isset( $prev['meta'] ) && is_array( $prev['meta'] ) ) {
			foreach ( $prev['meta'] as $key => $value ) {
				if ( ! is_string( $key ) || 0 !== strpos( $key, '_ww_' ) || '_ww_import_prev' === $key ) {
					continue;
				}
				if ( null === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			}
		}
		if ( isset( $prev['post_title'] ) && is_string( $prev['post_title'] ) && '' !== $prev['post_title'] ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $prev['post_title'],
				)
			);
		}
		foreach ( array( 'market' => 'markets', 'service' => 'services' ) as $taxonomy => $key ) {
			if ( isset( $prev[ $key ] ) && is_array( $prev[ $key ] ) ) {
				wp_set_object_terms( $post_id, array_map( 'intval', $prev[ $key ] ), $taxonomy, false );
			}
		}
		delete_post_meta( $post_id, '_ww_import_prev' );
		Save::$importing = false;

		return array( 'status' => 'restored' );
	}

	/**
	 * Parse the export. Does not read or write posts.
	 *
	 * @param string $path Absolute path.
	 * @return array|\WP_Error
	 */
	public static function parse_file( $path ) {
		$doc = new \DOMDocument();
		$ok  = $doc->load( $path, LIBXML_NONET | LIBXML_COMPACT );
		if ( ! $ok ) {
			return new \WP_Error( 'ww_xml', __( 'The XML file could not be read.', 'ww-projects' ) );
		}
		$xpath = new \DOMXPath( $doc );
		$cells = $xpath->query( '//cells/cell' );
		$index = array();
		foreach ( $cells as $cell ) {
			$id           = $cell->getAttribute( 'id' );
			$index[ $id ] = array(
				'text'   => self::decode( self::child_text( $cell, 'text' ) ),
				'level'  => self::child_text( $cell, 'level' ),
				'parent' => self::child_text( $cell, 'parent' ),
			);
		}

		$projects = array();
		$stubs    = array();
		$counts   = array( 1 => 0, 2 => 0, 3 => 0 );
		foreach ( $cells as $cell ) {
			$id    = $cell->getAttribute( 'id' );
			$level = self::child_text( $cell, 'level' );
			if ( '3' !== $level ) {
				continue;
			}
			$contents = self::child( $cell, 'contents' );
			$tpl_id   = $contents ? self::child_text( $contents, 'template' ) : '';
			if ( ! $contents || ! isset( self::TEMPLATES[ $tpl_id ] ) ) {
				$stubs[] = array(
					'cell_id' => $id,
					'title'   => $index[ $id ]['text'],
				);
				continue;
			}
			$template = self::TEMPLATES[ $tpl_id ];
			$parent   = $index[ $id ]['parent'];
			$market   = isset( $index[ $parent ] ) ? $index[ $parent ]['text'] : '';
			$project  = self::parse_project( $cell, $template, $index[ $id ]['text'], $market );
			$projects[] = $project;
			++$counts[ $template ];
		}

		return array(
			'projects' => $projects,
			'stubs'    => $stubs,
			'counts'   => $counts,
		);
	}

	/**
	 * @param \DOMElement $cell     Cell node.
	 * @param int         $template Template number.
	 * @param string      $title    Sitemap title.
	 * @param string      $market   Parent market name.
	 * @return array
	 */
	private static function parse_project( $cell, $template, $title, $market ) {
		$body     = self::child( self::child( $cell, 'contents' ), 'body' );
		$blocks   = array();
		$warnings = array();
		if ( $body ) {
			foreach ( $body->childNodes as $node ) {
				if ( ! $node instanceof \DOMElement ) {
					continue;
				}
				$label = self::decode( self::child_text( self::child( $node, 'options' ), 'label' ) );
				if ( '' === $label ) {
					continue;
				}
				$blocks[ $label ] = $node;
			}
		}

		$fields   = array();
		$images   = array( 'hero' => array(), 'slider' => array(), 'grid' => array(), 'single_image' => array() );
		$contacts = array();
		$markets  = array();
		$services = array();
		$unknown  = array();
		$joined   = false;
		$quote_marks = true;

		$headline = self::heading_text( self::block_html( $blocks, 'Headline / Project Name' ) );
		if ( self::is_placeholder( $headline ) ) {
			$headline = '';
		}

		$subhead = self::heading_text( self::block_html( $blocks, 'Subhead' ) );
		if ( ! self::is_placeholder( $subhead ) ) {
			$fields['subhead'] = $subhead;
		}

		$places = self::location_places( self::block_html( $blocks, 'Location' ) );
		if ( count( $places ) > 1 ) {
			$joined = true;
			$warnings[] = 'Location has ' . count( $places ) . ' places: ' . implode( ' / ', $places );
		}
		$location = implode( ' / ', $places );
		if ( ! self::is_placeholder( $location ) ) {
			$fields['location'] = $location;
		}

		$description_html = self::block_html( $blocks, 'Project Description' );
		$description     = self::clean_rich( $description_html, false );
		if ( self::rich_text_dropped( $description_html, $description ) ) {
			$warnings[] = 'Project Description had text, but the cleaned field is empty';
		}
		if ( ! self::is_placeholder( self::plain( $description ) ) ) {
			$fields['description'] = $description;
		}

		$content_html = self::block_html( $blocks, 'Content Block' );
		$content      = self::clean_rich( $content_html, true );
		if ( self::rich_text_dropped( $content_html, $content ) ) {
			$warnings[] = 'Content Block had text, but the cleaned field is empty';
		}
		if ( ! self::is_placeholder( self::plain( $content ) ) ) {
			$fields['content_block'] = $content;
		}

		$stat = self::statistic_text( self::block_html( $blocks, 'Subhead or Statistic' ) );
		if ( ! self::is_placeholder( $stat ) ) {
			$fields['stat'] = $stat;
		}

		$quote = self::quote_text( self::block_html( $blocks, 'Quote/Testimonial' ) );
		if ( ! self::is_placeholder( $quote ) ) {
			$fields['quote'] = $quote;
			$quote_marks = ( false !== strpos( $quote, '"' ) || false !== strpos( $quote, '“' ) || false !== strpos( $quote, '”' ) );
			if ( ! $quote_marks ) {
				$warnings[] = 'Quote has no quotation marks';
			}
		}

		$term_lists = self::highlighted_terms( self::block_html( $blocks, 'Markets + Services' ) );
		$markets    = $term_lists['markets'];
		$services   = $term_lists['services'];
		$from_sitemap = false;
		if ( ! $markets && '' !== $market ) {
			$markets      = array( $market );
			$from_sitemap = true;
		} elseif ( $markets && '' !== $market ) {
			$aliased_parent = self::alias_term( $market );
			$aliased        = array_map( array( __CLASS__, 'alias_term' ), $markets );
			if ( ! in_array( $aliased_parent, $aliased, true ) || count( $aliased ) > 1 ) {
				$warnings[] = 'Highlighted markets (' . implode( ', ', $markets ) . ') differ from sitemap ' . $market;
			}
		}

		$overview = self::overview_values( self::block_html( $blocks, 'Project Overview' ) );
		foreach ( $overview['unknown'] as $label ) {
			$unknown[]  = $label;
			$warnings[] = 'Overview label not imported: ' . $label;
		}
		foreach ( $overview['values'] as $key => $value ) {
			if ( 'key_contacts' === $key ) {
				continue;
			}
			if ( ! self::is_placeholder( $value ) && '' !== $value ) {
				$fields[ $key ] = $value;
			}
		}
		$contacts = $overview['contacts'];

		foreach ( array( 'Hero Image' => 'hero', 'Image Slider' => 'slider', 'Image Grid' => 'grid', 'Single Image' => 'single_image' ) as $label => $key ) {
			if ( ! isset( $blocks[ $label ] ) ) {
				continue;
			}
			$images[ $key ] = self::image_items( $blocks[ $label ], $warnings );
		}
		if ( in_array( 'slider', Fields::blocks( $template ), true ) ) {
			self::image_count_warning( $template, 'slider', $images['slider'], 3, 5, $warnings );
		}
		if ( in_array( 'grid', Fields::blocks( $template ), true ) ) {
			$grid_exact = ( 3 === $template ) ? 4 : 3;
			self::image_count_warning( $template, 'grid', $images['grid'], $grid_exact, $grid_exact, $warnings );
		}

		return array(
			'cell_id'        => $cell->getAttribute( 'id' ),
			'sitemap_title'  => $title,
			'sitemap_market' => $market,
			'template'       => $template,
			'headline'       => $headline,
			'fields'         => $fields,
			'images'         => $images,
			'contacts'       => $contacts,
			'markets'           => $markets,
			'services'          => $services,
			'market_checklist'  => $term_lists['market_checklist'],
			'service_checklist' => $term_lists['service_checklist'],
			'market_fallback'   => $from_sitemap,
			'warnings'       => $warnings,
			'unknown'        => $unknown,
		);
	}

	/**
	 * Attach a WordPress project, terms, and image library matches.
	 *
	 * @param array $project Parsed project.
	 * @return array
	 */
	private static function match_project( array $project ) {
		$catalog = self::catalog();
		$match   = self::find_post( $project, $catalog );
		$terms   = self::resolve_terms( $project );
		$terms['market_selected_ids']  = $terms['market_ids'];
		$terms['service_selected_ids'] = $terms['service_ids'];
		$terms['market_ids']           = self::merge_term_ids( (int) $match['post_id'], 'market', $terms['market_selected_ids'], $terms['market_checklist_ids'] );
		$terms['service_ids']          = self::merge_term_ids( (int) $match['post_id'], 'service', $terms['service_selected_ids'], $terms['service_checklist_ids'] );
		$images  = self::count_images( $project['images'] );
		$contacts = self::resolve_contacts( $project['contacts'] );

		$title_new = '';
		$title_old = '';
		if ( $match['post_id'] ) {
			$title_old = get_the_title( $match['post_id'] );
			$title_new = $project['headline'];
			if ( '' === $title_new || self::normalize_title( $title_new ) === self::normalize_title( $title_old ) ) {
				$title_new = '';
			}
		}

		$flag_off = false;
		if ( $match['post_id'] && metadata_exists( 'post', $match['post_id'], '_ww_use_new_template' ) ) {
			$flag_off = ! ww_flag_is_on( get_post_meta( $match['post_id'], '_ww_use_new_template', true ) );
		}

		$checked = in_array( $match['confidence'], array( 'map', 'page-id', 'exact-title', 'slug', 'contains' ), true ) && ! $flag_off;
		$status  = $flag_off ? 'left alone' : $match['status'];

		return array_merge(
			$project,
			$match,
			$terms,
			$images,
			array(
				'contact_rows' => $contacts['rows'],
				'contact_notes'=> $contacts['notes'],
				'title_old'    => $title_old,
				'title_new'    => $title_new,
				'flag_off'     => $flag_off,
				'checked'      => $checked,
				'status'       => $status,
				'warnings'     => array_merge( $project['warnings'], $terms['term_warnings'], $images['image_warnings'], $contacts['notes'] ),
			)
		);
	}

	/**
	 * @param array $project Parsed project.
	 * @param array $catalog Match indexes.
	 * @return array
	 */
	private static function find_post( array $project, array $catalog ) {
		$cell = $project['cell_id'];
		if ( isset( $catalog['map'][ $cell ] ) ) {
			$post_id = (int) $catalog['map'][ $cell ];
			if ( isset( $catalog['posts'][ $post_id ] ) ) {
				return self::match_result( $post_id, 'map', 'mapped', $catalog, array() );
			}
		}
		if ( isset( $catalog['by_page'][ $cell ] ) && 1 === count( $catalog['by_page'][ $cell ] ) ) {
			return self::match_result( $catalog['by_page'][ $cell ][0], 'page-id', 'matched', $catalog, array() );
		}

		$title = self::normalize_title( $project['sitemap_title'] );
		$head  = self::normalize_title( $project['headline'] );
		if ( isset( $catalog['by_title'][ $title ] ) && 1 === count( $catalog['by_title'][ $title ] ) ) {
			return self::match_result( $catalog['by_title'][ $title ][0], 'exact-title', 'matched', $catalog, array() );
		}
		$slug = sanitize_title( $title );
		if ( isset( $catalog['by_slug'][ $slug ] ) && 1 === count( $catalog['by_slug'][ $slug ] ) ) {
			return self::match_result( $catalog['by_slug'][ $slug ][0], 'slug', 'matched', $catalog, array() );
		}

		$contained = array();
		foreach ( $catalog['posts'] as $post_id => $post ) {
			$hit = self::phrase_contains( $post['normal'], $title ) || self::phrase_contains( $title, $post['normal'] );
			if ( ! $hit && '' !== $head ) {
				$hit = self::phrase_contains( $post['normal'], $head ) || self::phrase_contains( $head, $post['normal'] );
			}
			if ( $hit ) {
				$contained[] = array(
					'post_id' => $post_id,
					'score'   => max( self::similarity( $title, $post['normal'] ), '' !== $head ? self::similarity( $head, $post['normal'] ) : 0 ),
				);
			}
		}
		if ( 1 === count( $contained ) ) {
			return self::match_result( $contained[0]['post_id'], 'contains', 'matched', $catalog, $contained );
		}
		if ( count( $contained ) > 1 ) {
			return self::match_result( 0, 'none', 'needs review', $catalog, array_slice( $contained, 0, 3 ) );
		}

		$scored = array();
		foreach ( $catalog['posts'] as $post_id => $post ) {
			$score = self::similarity( $title, $post['normal'] );
			$head_score = '' !== $head ? self::similarity( $head, $post['normal'] ) : 0;
			$best = max( $score, $head_score );
			if ( $best >= 0.85 || self::close_edit( $title, $post['normal'] ) || ( '' !== $head && self::close_edit( $head, $post['normal'] ) ) ) {
				$scored[] = array( 'post_id' => $post_id, 'score' => $best );
			}
		}
		usort( $scored, static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );
		if ( $scored ) {
			$top = $scored[0];
			$second = isset( $scored[1] ) ? $scored[1]['score'] : 0;
			if ( ( $top['score'] - $second ) < 0.05 && $second >= 0.85 ) {
				return self::match_result( 0, 'none', 'needs review', $catalog, array_slice( $scored, 0, 3 ) );
			}
			return self::match_result( $top['post_id'], 'fuzzy', 'needs review', $catalog, array_slice( $scored, 0, 3 ) );
		}

		$closest = array();
		foreach ( $catalog['posts'] as $post_id => $post ) {
			$closest[] = array(
				'post_id' => $post_id,
				'score'   => self::similarity( '' !== $head ? $head : $title, $post['normal'] ),
			);
		}
		usort( $closest, static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );
		return self::match_result( 0, 'none', 'will create as draft', $catalog, array_slice( $closest, 0, 3 ) );
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param string $confidence Match kind.
	 * @param string $status     Row status.
	 * @param array  $catalog    Catalog.
	 * @param array  $candidates Scored candidates.
	 * @return array
	 */
	private static function match_result( $post_id, $confidence, $status, array $catalog, array $candidates ) {
		$out = array();
		foreach ( $candidates as $candidate ) {
			$id = (int) $candidate['post_id'];
			if ( ! isset( $catalog['posts'][ $id ] ) ) {
				continue;
			}
			$out[] = array(
				'post_id' => $id,
				'title'   => $catalog['posts'][ $id ]['title'],
				'score'   => round( (float) $candidate['score'], 2 ),
			);
		}
		return array(
			'post_id'     => (int) $post_id,
			'post_title'  => ( $post_id && isset( $catalog['posts'][ $post_id ] ) ) ? $catalog['posts'][ $post_id ]['title'] : '',
			'confidence'  => $confidence,
			'status'      => $status,
			'candidates'  => $out,
		);
	}

	/**
	 * Remember an attachment added during this request.
	 *
	 * @param string $filename File name.
	 * @param int    $id       Attachment ID.
	 */
	private static function remember_file( $filename, $id ) {
		self::catalog();
		self::$catalog['files'][ self::normalize_filename( $filename ) ] = (int) $id;
	}

	/**
	 * @var array|null
	 */
	private static $catalog = null;

	/**
	 * Downloads started in this Apply request.
	 *
	 * @var int
	 */
	private static $download_count = 0;

	/**
	 * URLs that already failed during this Apply click. Null until loaded.
	 *
	 * @var array|null
	 */
	private static $failed_images = null;

	/**
	 * Posts and indexes used for every row.
	 *
	 * @return array
	 */
	private static function catalog() {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}
		global $wpdb;
		$posts = get_posts(
			array(
				'post_type'      => WW_POST_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		self::$catalog = array(
			'posts'    => array(),
			'by_title' => array(),
			'by_slug'  => array(),
			'by_page'  => array(),
			'map'      => self::mapping_file(),
			'files'    => self::attachment_index(),
			'people'   => self::people_index(),
			'terms'    => array(
				'market'  => self::term_index( 'market' ),
				'service' => self::term_index( 'service' ),
			),
		);
		foreach ( $posts as $post ) {
			$normal = self::normalize_title( $post->post_title );
			self::$catalog['posts'][ $post->ID ] = array(
				'title'  => $post->post_title,
				'normal' => $normal,
				'slug'   => $post->post_name,
			);
			self::$catalog['by_title'][ $normal ][] = $post->ID;
			self::$catalog['by_slug'][ $post->post_name ][] = $post->ID;
		}
		$page_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_ww_slickplan_page_id'
			)
		);
		foreach ( (array) $page_rows as $page_row ) {
			if ( isset( self::$catalog['posts'][ (int) $page_row->post_id ] ) ) {
				self::$catalog['by_page'][ (string) $page_row->meta_value ][] = (int) $page_row->post_id;
			}
		}
		return self::$catalog;
	}

	/**
	 * @return array<string,int>
	 */
	private static function mapping_file() {
		$map  = array();
		$path = WW_PLUGIN_DIR . 'docs/ww_project_import_map.csv';
		if ( ! is_readable( $path ) ) {
			return $map;
		}
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return $map;
		}
		$header = fgetcsv( $handle );
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( isset( $row[0], $row[1] ) && '' !== trim( $row[1] ) ) {
				$map[ trim( $row[0] ) ] = (int) $row[1];
			}
		}
		fclose( $handle );
		unset( $header );
		return $map;
	}

	/**
	 * @param array $project Parsed project.
	 * @return array
	 */
	private static function resolve_terms( array $project ) {
		$catalog  = self::catalog();
		$warnings = array();
		$unused   = array();
		$markets  = self::resolve_term_ids( $catalog['terms']['market'], $project['markets'], 'Market', $warnings );
		$services = self::resolve_term_ids( $catalog['terms']['service'], $project['services'], 'Service', $warnings );
		$market_check  = self::resolve_term_ids( $catalog['terms']['market'], $project['market_checklist'] ?? array(), '', $unused );
		$service_check = self::resolve_term_ids( $catalog['terms']['service'], $project['service_checklist'] ?? array(), '', $unused );
		if ( ! empty( $project['market_fallback'] ) ) {
			foreach ( $markets as $id ) {
				if ( ! in_array( $id, $market_check, true ) ) {
					$market_check[] = $id;
				}
			}
		}
		return array(
			'market_ids'            => $markets,
			'service_ids'           => $services,
			'market_checklist_ids'  => $market_check,
			'service_checklist_ids' => $service_check,
			'term_warnings'         => $warnings,
		);
	}

	/**
	 * Term IDs for Slickplan labels. Unknown selected labels are warned about.
	 *
	 * @param array    $index    Normalized name => id.
	 * @param string[] $names    Labels.
	 * @param string   $kind     Warning prefix, or empty to stay quiet.
	 * @param array    $warnings Warnings to append.
	 * @return int[]
	 */
	private static function resolve_term_ids( array $index, array $names, $kind, array &$warnings ) {
		$ids = array();
		foreach ( $names as $name ) {
			$id = self::term_id( $index, $name );
			if ( $id ) {
				if ( ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			} elseif ( '' !== $kind ) {
				$warnings[] = $kind . ' not found: ' . $name;
			}
		}
		return $ids;
	}

	/**
	 * Keep terms the page does not list. Add or remove only checklist terms.
	 *
	 * @param int    $post_id        Project, or 0 when the row will create one.
	 * @param string $taxonomy       market or service.
	 * @param int[]  $selected_ids   Checklist terms the page highlights.
	 * @param int[]  $checklist_ids  Terms the page offers.
	 * @return int[]
	 */
	private static function merge_term_ids( $post_id, $taxonomy, array $selected_ids, array $checklist_ids ) {
		$selected  = array_fill_keys( self::term_id_list( $selected_ids ), true );
		$checklist = array_fill_keys( self::term_id_list( $checklist_ids ), true );
		$merged    = array();
		if ( (int) $post_id > 0 ) {
			$current = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			foreach ( is_array( $current ) ? $current : array() as $id ) {
				$id = (int) $id;
				if ( $id <= 0 ) {
					continue;
				}
				if ( isset( $checklist[ $id ] ) && ! isset( $selected[ $id ] ) ) {
					continue;
				}
				if ( ! in_array( $id, $merged, true ) ) {
					$merged[] = $id;
				}
			}
		}
		foreach ( array_keys( $selected ) as $id ) {
			if ( isset( $checklist[ $id ] ) && ! in_array( (int) $id, $merged, true ) ) {
				$merged[] = (int) $id;
			}
		}
		return $merged;
	}

	/**
	 * @param array $ids Raw IDs.
	 * @return int[]
	 */
	private static function term_id_list( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param array $images Image lists.
	 * @return array
	 */
	private static function count_images( array $images ) {
		$catalog = self::catalog();
		$counts  = array();
		$in_library = 0;
		$missing = 0;
		$no_alt  = 0;
		$warnings = array();
		foreach ( $images as $key => $items ) {
			$counts[ $key ] = count( $items );
			foreach ( $items as $item ) {
				$found = self::find_attachment( $item, $catalog['files'] );
				if ( $found ) {
					++$in_library;
					$alt = (string) get_post_meta( $found, '_wp_attachment_image_alt', true );
					if ( '' === trim( $alt ) ) {
						++$no_alt;
					}
				} else {
					++$missing;
					if ( 'url' === $item['type'] && empty( $item['allowed'] ) ) {
						$warnings[] = 'Image host is not harriman.com: ' . $item['url'];
					}
				}
			}
		}
		return array(
			'image_counts'   => $counts,
			'images_library' => $in_library,
			'images_missing' => $missing,
			'images_no_alt'  => $no_alt,
			'image_warnings' => $warnings,
		);
	}

	/**
	 * @param array $contacts Parsed contacts.
	 * @return array{rows:array,notes:array}
	 */
	private static function resolve_contacts( array $contacts ) {
		$people = self::catalog()['people'];
		$rows   = array();
		$notes  = array();
		$seen   = array();
		foreach ( $contacts as $contact ) {
			$person = self::find_person( $contact, $people );
			$key    = $person ? 'p' . $person : 't' . strtolower( $contact['text'] );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( $person ) {
				$rows[] = array(
					'mode'      => 'person',
					'person_id' => $person,
					'text'      => '',
				);
			} else {
				if ( '' !== $contact['text'] ) {
					$rows[] = array(
						'mode'      => 'text',
						'person_id' => 0,
						'text'      => $contact['text'],
					);
					if ( '' !== $contact['href'] ) {
						$notes[] = 'Unresolved contact: ' . $contact['text'];
					}
				}
			}
		}
		return array( 'rows' => $rows, 'notes' => $notes );
	}

	/**
	 * @param array $contact href, id, text.
	 * @param array $people  People index.
	 * @return int
	 */
	private static function find_person( array $contact, array $people ) {
		$attr_id = (int) $contact['id'];
		if ( $attr_id && isset( $people['by_id'][ $attr_id ] ) ) {
			return $attr_id;
		}
		$slug = '';
		if ( '' !== $contact['href'] ) {
			$path = (string) wp_parse_url( $contact['href'], PHP_URL_PATH );
			$slug = trim( basename( $path ) );
			if ( isset( $people['by_slug'][ $slug ] ) ) {
				return $people['by_slug'][ $slug ];
			}
			foreach ( $people['by_slug'] as $candidate => $person_id ) {
				if ( '' !== $slug && ( false !== strpos( $candidate, $slug ) || false !== strpos( $slug, $candidate ) ) ) {
					return $person_id;
				}
			}
		}
		$names = self::first_last( $contact['text'] );
		if ( ! $names ) {
			return 0;
		}
		$key = $names[0] . '|' . $names[1];
		$hits = isset( $people['by_name'][ $key ] ) ? $people['by_name'][ $key ] : array();
		if ( ! $hits ) {
			foreach ( $people['by_name'] as $name_key => $ids ) {
				list( $first, $last ) = explode( '|', $name_key );
				if ( $last !== $names[1] ) {
					continue;
				}
				if ( 0 === strpos( $first, $names[0] ) || 0 === strpos( $names[0], $first ) ) {
					$shorter = min( strlen( $first ), strlen( $names[0] ) );
					if ( $shorter >= 3 ) {
						$hits = array_merge( $hits, $ids );
					}
				}
			}
		}
		$hits = array_values( array_unique( $hits ) );
		return 1 === count( $hits ) ? (int) $hits[0] : 0;
	}

	/**
	 * @return array
	 */
	private static function people_index() {
		$posts = get_posts(
			array(
				'post_type'      => WW_PEOPLE_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
			)
		);
		$index = array( 'by_id' => array(), 'by_slug' => array(), 'by_name' => array() );
		foreach ( $posts as $post ) {
			$index['by_id'][ $post->ID ] = $post->ID;
			$index['by_slug'][ $post->post_name ] = $post->ID;
			$names = self::first_last( $post->post_title );
			if ( $names ) {
				$index['by_name'][ $names[0] . '|' . $names[1] ][] = $post->ID;
			}
		}
		return $index;
	}

	/**
	 * @param string $name Person name.
	 * @return array{0:string,1:string}|null
	 */
	private static function first_last( $name ) {
		$name = preg_replace( '/,.*$/', '', (string) $name );
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		$parts = array_values(
			array_filter(
				(array) $parts,
				static function ( $part ) {
					return ! preg_match( '/^[A-Za-z]\.?$/', $part );
				}
			)
		);
		if ( count( $parts ) < 2 ) {
			return null;
		}
		return array( strtolower( $parts[0] ), strtolower( $parts[ count( $parts ) - 1 ] ) );
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,int>
	 */
	private static function term_index( $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		$index = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$index[ self::normalize_term( $term->name ) ] = (int) $term->term_id;
			}
		}
		return $index;
	}

	/**
	 * @param array  $index Normalized name => id.
	 * @param string $name  Slickplan label.
	 * @return int
	 */
	private static function term_id( array $index, $name ) {
		$key = self::normalize_term( self::alias_term( $name ) );
		if ( isset( $index[ $key ] ) ) {
			return $index[ $key ];
		}
		$key = self::normalize_term( $name );
		return isset( $index[ $key ] ) ? $index[ $key ] : 0;
	}

	/**
	 * @param string $name Slickplan name.
	 * @return string
	 */
	private static function alias_term( $name ) {
		$needle = self::normalize_term( $name );
		foreach ( \ww_term_aliases() as $from => $to ) {
			if ( self::normalize_term( $from ) === $needle ) {
				return $to;
			}
		}
		return $name;
	}

	/**
	 * @param string $name Term name.
	 * @return string
	 */
	private static function normalize_term( $name ) {
		$name = strtolower( self::decode( $name ) );
		$name = str_replace( array( '–', '—', '-' ), '-', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		return trim( (string) $name );
	}

	/**
	 * @return array<string,int> Normalized filename => attachment ID.
	 */
	private static function attachment_index() {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_wp_attached_file'
			)
		);
		$index = array();
		foreach ( (array) $rows as $row ) {
			$index[ self::normalize_filename( basename( (string) $row->meta_value ) ) ] = (int) $row->post_id;
		}
		return $index;
	}

	/**
	 * @param string $filename File name.
	 * @return string
	 */
	public static function normalize_filename( $filename ) {
		$name = strtolower( (string) $filename );
		$name = preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/', '', $name );
		$name = preg_replace( '/-scaled(?=\.[a-z0-9]+$)/', '', $name );
		$name = preg_replace( '/-e\d+(?=\.[a-z0-9]+$)/', '', $name );
		return (string) $name;
	}

	/**
	 * @param array $item  Image descriptor.
	 * @param array $files Filename index.
	 * @return int
	 */
	private static function find_attachment( array $item, array $files ) {
		$names = array();
		if ( ! empty( $item['file_name'] ) ) {
			$names[] = $item['file_name'];
		}
		if ( ! empty( $item['url'] ) ) {
			$names[] = basename( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
		}
		foreach ( $names as $name ) {
			foreach ( self::filename_keys( $name ) as $key ) {
				if ( isset( $files[ $key ] ) ) {
					return $files[ $key ];
				}
			}
		}
		return 0;
	}

	/**
	 * Names a file may be stored under after WordPress sanitizes spaces.
	 *
	 * @param string $filename File name.
	 * @return string[]
	 */
	private static function filename_keys( $filename ) {
		$filename = (string) $filename;
		$keys     = array( self::normalize_filename( $filename ) );
		if ( function_exists( 'sanitize_file_name' ) ) {
			$keys[] = self::normalize_filename( sanitize_file_name( $filename ) );
		}
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/**
	 * Download or reuse one image. Failures are listed and the image is skipped.
	 *
	 * @param array $item  Image descriptor.
	 * @param array $notes Failure notes.
	 * @return array{id:int,alt:string,caption:string}
	 */
	private static function resolve_image( array $item, array &$notes ) {
		$empty = array( 'id' => 0, 'alt' => '', 'caption' => '' );
		if ( 'url' === $item['type'] && empty( $item['allowed'] ) ) {
			$notes[] = 'Skipped image from a host other than harriman.com';
			return $empty;
		}
		$found = self::find_attachment( $item, self::catalog()['files'] );
		if ( $found ) {
			return array( 'id' => $found, 'alt' => '', 'caption' => '' );
		}
		if ( empty( $item['url'] ) ) {
			$notes[] = 'Image has no URL';
			return $empty;
		}
		$url = (string) $item['url'];
		if ( isset( self::failed_map()[ $url ] ) ) {
			$notes[] = self::failed_map()[ $url ];
			return $empty;
		}
		++self::$download_count;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$name  = ! empty( $item['file_name'] ) ? $item['file_name'] : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$tmp   = null;
		$error = '';
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$tmp = download_url( $url, 20 );
			if ( ! is_wp_error( $tmp ) ) {
				$error = '';
				break;
			}
			$error = $tmp->get_error_message();
			$tmp   = null;
		}
		if ( null === $tmp ) {
			$message = 'Download failed for ' . $name . ': ' . $error;
			self::mark_image_failed( $url, $message );
			$notes[] = $message;
			return $empty;
		}
		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			$message = 'Could not add ' . $name . ': ' . $id->get_error_message();
			self::mark_image_failed( $url, $message );
			$notes[] = $message;
			return $empty;
		}
		self::remember_file( $name, (int) $id );
		$stored = (string) get_post_meta( (int) $id, '_wp_attached_file', true );
		if ( '' !== $stored ) {
			self::remember_file( basename( $stored ), (int) $id );
		}
		return array( 'id' => (int) $id, 'alt' => '', 'caption' => '' );
	}

	/**
	 * @param array $row     Dry-run row.
	 * @param int   $post_id Project the row will update.
	 * @return array
	 */
	private static function fields_for_save( array $row, $post_id ) {
		$notes   = array();
		$pending = false;
		$fields  = $row['fields'];
		foreach ( array( 'hero', 'single_image' ) as $key ) {
			if ( empty( $row['images'][ $key ] ) ) {
				$fields[ $key ] = array( 'id' => 0, 'alt' => '', 'caption' => '' );
				continue;
			}
			$image = self::use_or_download( $row['images'][ $key ][0], $notes );
			if ( ! empty( $image['pending'] ) ) {
				$pending = true;
				continue;
			}
			unset( $image['pending'] );
			$fields[ $key ] = $image;
		}
		foreach ( array( 'slider', 'grid' ) as $key ) {
			$rows = array();
			foreach ( (array) $row['images'][ $key ] as $item ) {
				$image = self::use_or_download( $item, $notes );
				if ( ! empty( $image['pending'] ) ) {
					$pending = true;
					continue;
				}
				if ( $image['id'] ) {
					$rows[] = $image;
				}
			}
			$fields[ $key ] = $rows;
		}
		$fields['key_contacts'] = $row['contact_rows'];
		$markets = self::terms_for_save( $row, $post_id, 'market' );
		if ( null !== $markets ) {
			$fields['markets'] = $markets;
		}
		$services = self::terms_for_save( $row, $post_id, 'service' );
		if ( null !== $services ) {
			$fields['services'] = $services;
		}
		$fields['_image_notes']   = $notes;
		$fields['_image_pending'] = $pending;
		return $fields;
	}

	/**
	 * @param array $item  Image descriptor.
	 * @param array $notes Notes.
	 * @return array{id:int,alt:string,caption:string}
	 */
	private static function use_or_download( array $item, array &$notes ) {
		$found = self::find_attachment( $item, self::catalog()['files'] );
		if ( $found ) {
			return array( 'id' => $found, 'alt' => '', 'caption' => '' );
		}
		$url = isset( $item['url'] ) ? (string) $item['url'] : '';
		if ( '' !== $url && isset( self::failed_map()[ $url ] ) ) {
			$notes[] = self::failed_map()[ $url ];
			return array( 'id' => 0, 'alt' => '', 'caption' => '' );
		}
		$blocked = ( 'url' === ( $item['type'] ?? '' ) && empty( $item['allowed'] ) ) || '' === $url;
		if ( ! $blocked && self::download_budget_spent() ) {
			return array( 'id' => 0, 'alt' => '', 'caption' => '', 'pending' => true );
		}
		return self::resolve_image( $item, $notes );
	}

	/**
	 * @return bool
	 */
	private static function download_budget_spent() {
		return self::$download_count >= 5;
	}

	/**
	 * @param array $row Dry-run row.
	 * @return bool
	 */
	private static function missing_images( array $row ) {
		$failed = self::failed_map();
		foreach ( array( 'hero', 'slider', 'grid', 'single_image' ) as $key ) {
			foreach ( (array) ( $row['images'][ $key ] ?? array() ) as $item ) {
				if ( 'url' === ( $item['type'] ?? '' ) && empty( $item['allowed'] ) ) {
					continue;
				}
				$url = isset( $item['url'] ) ? (string) $item['url'] : '';
				if ( '' === $url || isset( $failed[ $url ] ) ) {
					continue;
				}
				if ( ! self::find_attachment( $item, self::catalog()['files'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return array<string,string>
	 */
	private static function failed_map() {
		if ( null === self::$failed_images ) {
			$stored = get_option( 'ww_slickplan_failed_images', array() );
			self::$failed_images = is_array( $stored ) ? $stored : array();
		}
		return self::$failed_images;
	}

	/**
	 * @param string $url     Image URL.
	 * @param string $message Failure note.
	 */
	private static function mark_image_failed( $url, $message ) {
		self::failed_map();
		self::$failed_images[ $url ] = $message;
		update_option( 'ww_slickplan_failed_images', self::$failed_images, false );
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	private static function snapshot_legacy( $post ) {
		if ( ! $post instanceof \WP_Post || '' === $post->post_content ) {
			return;
		}
		if ( metadata_exists( 'post', $post->ID, '_ww_legacy_content' ) ) {
			return;
		}
		update_post_meta( $post->ID, '_ww_legacy_content', $post->post_content );
	}

	/**
	 * @param int   $post_id Post ID.
	 * @param array $row     Dry-run row.
	 * @param bool  $created Whether this apply created the post.
	 * @param array $fields  Values about to be saved.
	 * @return array
	 */
	private static function capture_previous( $post_id, array $row, $created, array $fields ) {
		$meta = array();
		$keys = array( '_ww_template', '_ww_slickplan_page_id', '_ww_legacy_content' );
		foreach ( Fields::meta_keys( (int) $row['template'] ) as $key ) {
			$keys[] = '_ww_' . $key;
		}
		foreach ( $keys as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		$prev = array( 'meta' => $meta );
		if ( ! $created && ! empty( $row['update_title'] ) && '' !== $row['title_new'] ) {
			$prev['post_title'] = get_the_title( $post_id );
		}
		foreach ( array( 'market' => 'markets', 'service' => 'services' ) as $taxonomy => $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
				$prev[ $key ] = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
			}
		}
		return $prev;
	}

	/**
	 * Merged term IDs when this row knows the page checklist, otherwise the old list.
	 *
	 * @param array  $row      Dry-run row.
	 * @param int    $post_id  Project.
	 * @param string $taxonomy market or service.
	 * @return int[]|null Null leaves the taxonomy alone.
	 */
	private static function terms_for_save( array $row, $post_id, $taxonomy ) {
		$selected_key  = $taxonomy . '_selected_ids';
		$checklist_key = $taxonomy . '_checklist_ids';
		$id_key        = ( 'market' === $taxonomy ) ? 'market_ids' : 'service_ids';
		if ( array_key_exists( $checklist_key, $row ) ) {
			$checklist = (array) $row[ $checklist_key ];
			$selected  = (array) ( $row[ $selected_key ] ?? array() );
			if ( ! $checklist && ! $selected ) {
				return null;
			}
			return self::merge_term_ids( (int) $post_id, $taxonomy, $selected, $checklist );
		}
		if ( empty( $row[ $id_key ] ) ) {
			return null;
		}
		return self::term_id_list( (array) $row[ $id_key ] );
	}

	/**
	 * Manifest rows that do not agree with the XML.
	 *
	 * @param array $parsed Parse result.
	 * @return array
	 */
	private static function manifest_diff( array $parsed ) {
		$path = WW_PLUGIN_DIR . 'docs/slickplan-projects-manifest.csv';
		$diff = array();
		if ( ! is_readable( $path ) ) {
			return array( 'missing file' );
		}
		$handle = fopen( $path, 'rb' );
		fgetcsv( $handle );
		$seen = array();
		foreach ( $parsed['projects'] as $project ) {
			$seen[ $project['cell_id'] ] = $project;
		}
		foreach ( $parsed['stubs'] as $stub ) {
			$seen[ $stub['cell_id'] ] = array( 'stub' => true );
		}
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$id     = isset( $row[0] ) ? $row[0] : '';
			$tpl    = isset( $row[2] ) ? (int) $row[2] : 0;
			$status = isset( $row[4] ) ? $row[4] : '';
			if ( ! isset( $seen[ $id ] ) ) {
				$diff[] = $id . ' is in the manifest but not the XML';
				continue;
			}
			$item = $seen[ $id ];
			unset( $seen[ $id ] );
			if ( 'skip-empty' === $status || ! empty( $item['stub'] ) ) {
				continue;
			}
			if ( (int) $item['template'] !== $tpl ) {
				$diff[] = $id . ' template is ' . $item['template'] . ' in the XML and ' . $tpl . ' in the manifest';
			}
		}
		fclose( $handle );
		foreach ( $seen as $id => $project ) {
			$diff[] = $id . ' is in the XML but not the manifest';
		}
		return $diff;
	}

	/**
	 * @param string $html        Decoded HTML.
	 * @param bool   $content_block Convert h2 and h4 to h3.
	 * @return string
	 */
	private static function clean_rich( $html, $content_block ) {
		$html = self::decode( $html );
		if ( '' === trim( $html ) ) {
			return '';
		}
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = preg_replace( '/<\/?o:p\b[^>]*>/i', '', (string) $html );
		$doc  = self::load_html( $html );
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return '';
		}
		self::clean_nodes( $body, $content_block );
		$out = '';
		foreach ( $body->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		$out = preg_replace( '/<p>(?:\s|&nbsp;|&#160;)*<\/p>/i', '', (string) $out );
		$out = trim( (string) $out );
		if ( function_exists( 'wp_kses' ) ) {
			$out = wp_kses( $out, self::allowed_html() );
		}
		return trim( $out );
	}

	/**
	 * @param \DOMNode $node          Node.
	 * @param bool     $content_block Content Block rules.
	 */
	private static function clean_nodes( $node, $content_block ) {
		$children = array();
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}
		foreach ( $children as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			self::clean_nodes( $child, $content_block );
			$tag = strtolower( $child->nodeName );
			if ( in_array( $tag, array( 'script', 'style', 'img', 'button', 'kbd' ), true ) ) {
				if ( 'kbd' === $tag ) {
					self::unwrap( $child );
				} else {
					$child->parentNode->removeChild( $child );
				}
				continue;
			}
			if ( self::is_spacer( $child ) ) {
				$child->parentNode->removeChild( $child );
				continue;
			}
			if ( in_array( $tag, array( 'div', 'span', 'section', 'article' ), true ) ) {
				self::unwrap( $child );
				continue;
			}
			if ( in_array( $tag, array( 'h2', 'h4' ), true ) && $content_block ) {
				self::rename_tag( $child, 'h3' );
				$tag = 'h3';
			} elseif ( in_array( $tag, array( 'h1', 'h2', 'h4', 'h5', 'h6' ), true ) ) {
				self::rename_tag( $child, 'p' );
				$tag = 'p';
			}
			if ( 'h3' === $tag && '.' === trim( $child->textContent ) ) {
				$child->parentNode->removeChild( $child );
				continue;
			}
			if ( 'p' === $tag && '' === trim( str_replace( "\xc2\xa0", '', $child->textContent ) ) ) {
				$child->parentNode->removeChild( $child );
				continue;
			}
			if ( 'a' === $tag ) {
				$href = $child->getAttribute( 'href' );
				while ( $child->attributes->length ) {
					$child->removeAttribute( $child->attributes->item( 0 )->nodeName );
				}
				if ( '' !== $href ) {
					$child->setAttribute( 'href', $href );
				}
			} else {
				while ( $child->attributes->length ) {
					$child->removeAttribute( $child->attributes->item( 0 )->nodeName );
				}
			}
		}
	}

	/**
	 * Real spacer blocks only. has-column-spacer is a text column, not a spacer.
	 *
	 * @param \DOMElement $node Node.
	 * @return bool
	 */
	private static function is_spacer( $node ) {
		$class = strtolower( $node->getAttribute( 'class' ) );
		foreach ( preg_split( '/\s+/', trim( $class ) ) as $token ) {
			if ( 'wp-block-spacer' === $token || 'spacer' === $token ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Raw words survived the markup, and the cleaned field did not.
	 *
	 * @param string $raw     Block HTML before cleaning.
	 * @param string $cleaned Cleaned HTML.
	 * @return bool
	 */
	private static function rich_text_dropped( $raw, $cleaned ) {
		$raw_text = self::plain( self::decode( $raw ) );
		return '' !== $raw_text && '' === self::plain( $cleaned );
	}

	/**
	 * @param \DOMElement $node Node.
	 */
	private static function unwrap( $node ) {
		$parent = $node->parentNode;
		while ( $node->firstChild ) {
			$parent->insertBefore( $node->firstChild, $node );
		}
		$parent->removeChild( $node );
	}

	/**
	 * @param \DOMElement $node Node.
	 * @param string      $tag  New tag.
	 */
	private static function rename_tag( $node, $tag ) {
		$doc = $node->ownerDocument;
		$new = $doc->createElement( $tag );
		while ( $node->firstChild ) {
			$new->appendChild( $node->firstChild );
		}
		$node->parentNode->replaceChild( $new, $node );
	}

	/**
	 * @return array<string,array>
	 */
	private static function allowed_html() {
		return array(
			'p'      => array(),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'a'      => array( 'href' => true ),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h3'     => array(),
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function heading_text( $html ) {
		$html = preg_replace( '/<br\s*\/?>/i', ' ', self::decode( $html ) );
		$doc  = self::load_html( $html );
		$list = $doc->getElementsByTagName( '*' );
		foreach ( $list as $node ) {
			if ( in_array( strtolower( $node->nodeName ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
				return self::plain( $node->textContent );
			}
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @return string[]
	 */
	private static function location_places( $html ) {
		$doc    = self::load_html( self::decode( $html ) );
		$places = array();
		foreach ( $doc->getElementsByTagName( '*' ) as $node ) {
			if ( ! in_array( strtolower( $node->nodeName ), array( 'span', 'a', 'h4', 'h5', 'p' ), true ) ) {
				continue;
			}
			if ( false !== stripos( $node->getAttribute( 'class' ), 'separator' ) ) {
				continue;
			}
			if ( 'span' !== strtolower( $node->nodeName ) && 'a' !== strtolower( $node->nodeName ) && $node->getElementsByTagName( 'span' )->length ) {
				continue;
			}
			$text = self::plain( $node->textContent );
			if ( '' === $text || self::is_placeholder( $text ) ) {
				continue;
			}
			if ( ! in_array( $text, $places, true ) ) {
				$places[] = $text;
			}
		}
		if ( ! $places ) {
			$text = self::plain( self::decode( $html ) );
			if ( '' !== $text && ! self::is_placeholder( $text ) ) {
				$places[] = $text;
			}
		}
		return $places;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function statistic_text( $html ) {
		$doc   = self::load_html( self::decode( $html ) );
		$items = array();
		foreach ( $doc->getElementsByTagName( 'li' ) as $li ) {
			$text = self::plain( $li->textContent );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
		if ( $items ) {
			return implode( ' ', $items );
		}
		return self::plain( self::decode( $html ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function quote_text( $html ) {
		$html = self::decode( $html );
		$html = preg_replace( '/<\s*br\s*\/?\s*>/i', "\n", $html );
		$html = preg_replace( '/<\/\s*p\s*>/i', "\n", (string) $html );
		return self::plain( $html );
	}

	/**
	 * Highlighted labels, plus every option the page itself lists.
	 *
	 * A note that says an item is landscape architecture offers Landscape
	 * Architecture instead of that item's own words.
	 *
	 * @param string $html HTML.
	 * @return array{markets:string[],services:string[],market_checklist:string[],service_checklist:string[]}
	 */
	private static function highlighted_terms( $html ) {
		$empty = array(
			'markets'           => array(),
			'services'          => array(),
			'market_checklist'  => array(),
			'service_checklist' => array(),
		);
		$doc   = self::load_html( self::decode( $html ) );
		$lists = $doc->getElementsByTagName( 'ul' );
		if ( $lists->length > 0 ) {
			list( $empty['markets'], $empty['market_checklist'] ) = self::list_terms( $lists->item( 0 ) );
		}
		if ( $lists->length > 1 ) {
			list( $empty['services'], $empty['service_checklist'] ) = self::list_terms( $lists->item( 1 ) );
		}
		return $empty;
	}

	/**
	 * @param \DOMElement $list List.
	 * @return array{0:string[],1:string[]}
	 */
	private static function list_terms( $list ) {
		$selected  = array();
		$checklist = array();
		foreach ( $list->getElementsByTagName( 'li' ) as $li ) {
			if ( $li->parentNode !== $list ) {
				continue;
			}
			$label = self::plain( $li->textContent );
			if ( '' === $label ) {
				continue;
			}
			if ( false !== stripos( $label, 'listed as landscape architecture' ) ) {
				$checklist[] = 'Landscape Architecture';
				$selected[]  = 'Landscape Architecture';
				continue;
			}
			$checklist[] = $label;
			$highlighted = '';
			foreach ( $li->getElementsByTagName( 'span' ) as $span ) {
				$style = strtolower( $span->getAttribute( 'style' ) );
				if ( false !== strpos( $style, 'background' ) ) {
					$highlighted .= $span->textContent;
				}
			}
			$label_len = mb_strlen( preg_replace( '/\s+/', '', $label ) );
			$high_len  = mb_strlen( preg_replace( '/\s+/', '', $highlighted ) );
			if ( $label_len > 0 && ( $high_len / $label_len ) >= 0.6 ) {
				$selected[] = $label;
			}
		}
		return array( $selected, $checklist );
	}

	/**
	 * Link text plus the rest of that line, up to a break or the next link.
	 *
	 * Bigelow's contact is `<a>Mark</a> D. LEE, AIA, LEED AP`.
	 *
	 * @param \DOMElement $anchor Link.
	 * @return string
	 */
	private static function contact_line_text( $anchor ) {
		$text    = $anchor->textContent;
		$sibling = $anchor->nextSibling;
		while ( $sibling ) {
			if ( $sibling instanceof \DOMElement ) {
				$tag = strtolower( $sibling->nodeName );
				if ( 'a' === $tag || 'br' === $tag ) {
					break;
				}
			}
			$text   .= $sibling->textContent;
			$sibling = $sibling->nextSibling;
		}
		return self::plain( $text );
	}

	/**
	 * @param string $html HTML.
	 * @return array{values:array,unknown:array,contacts:array}
	 */
	private static function overview_values( $html ) {
		$map = array(
			'client'       => 'client',
			'type'         => 'type',
			'project type' => 'type',
			'size'         => 'size',
			'program'             => 'program',
			'program components'  => 'components',
			'project components'  => 'components',
			'key contact'         => 'key_contacts',
			'key contacts' => 'key_contacts',
			'recognition'  => 'recognition',
		);
		$doc = self::load_html( self::decode( $html ) );
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		$values = array();
		$unknown = array();
		$contacts = array();
		$current = '';
		$chunks = array();
		$contact_nodes = array();

		$flush = static function () use ( &$current, &$chunks, &$values, &$contact_nodes, &$contacts ) {
			if ( '' === $current ) {
				$chunks = array();
				$contact_nodes = array();
				return;
			}
			$text = trim( implode( "\n", array_filter( array_map( 'trim', $chunks ), 'strlen' ) ) );
			if ( 'key_contacts' === $current ) {
				$contacts = array_merge( $contacts, $contact_nodes );
				if ( ! $contact_nodes && '' !== $text && ! self::is_placeholder( $text ) ) {
					foreach ( preg_split( '/\s+and\s+|\n+/i', $text ) as $part ) {
						$part = trim( $part );
						if ( '' !== $part && ! self::is_placeholder( $part ) ) {
							$contacts[] = array( 'id' => '', 'href' => '', 'text' => $part );
						}
					}
				}
			} elseif ( '' !== $text && ! self::is_placeholder( $text ) ) {
				$values[ $current ] = isset( $values[ $current ] ) ? $values[ $current ] . "\n" . $text : $text;
			}
			$chunks = array();
			$contact_nodes = array();
		};

		$walk = static function ( $node ) use ( &$walk, &$flush, &$current, &$chunks, &$contact_nodes, &$unknown, $map ) {
			foreach ( $node->childNodes as $child ) {
				if ( ! $child instanceof \DOMElement ) {
					$text = trim( $child->textContent );
					if ( '' !== $text && '' !== $current ) {
						$chunks[] = self::plain( $text );
					}
					continue;
				}
				$tag = strtolower( $child->nodeName );
				if ( ( 'h6' === $tag || 'strong' === $tag ) && 'key_contacts' === $current && $child->getElementsByTagName( 'a' )->length ) {
					foreach ( $child->getElementsByTagName( 'a' ) as $anchor ) {
						if ( ! $anchor instanceof \DOMElement ) {
							continue;
						}
						$contact_nodes[] = array(
							'id'   => $anchor->getAttribute( 'id' ),
							'href' => $anchor->getAttribute( 'href' ),
							'text' => self::contact_line_text( $anchor ),
						);
					}
					continue;
				}
				if ( 'h6' === $tag || 'strong' === $tag ) {
					$label = strtolower( rtrim( self::plain( $child->textContent ), ':' ) );
					if ( 'project overview' === $label || '' === $label || 0 === strpos( $label, 'please ' ) ) {
						continue;
					}
					$flush();
					if ( isset( $map[ $label ] ) ) {
						$current = $map[ $label ];
					} else {
						$unknown[] = self::plain( $child->textContent );
						$current = '';
					}
					continue;
				}
				if ( 'br' === $tag ) {
					$chunks[] = '';
					continue;
				}
				if ( 'a' === $tag && 'key_contacts' === $current ) {
					$contact_nodes[] = array(
						'id'   => $child->getAttribute( 'id' ),
						'href' => $child->getAttribute( 'href' ),
						'text' => self::contact_line_text( $child ),
					);
					continue;
				}
				if ( 'p' === $tag && 'key_contacts' === $current && $child->getElementsByTagName( 'a' )->length ) {
					$walk( $child );
					continue;
				}
				if ( 'p' === $tag ) {
					$label_node = null;
					foreach ( $child->childNodes as $inner ) {
						if ( $inner instanceof \DOMElement && 'strong' === strtolower( $inner->nodeName ) ) {
							$label_node = $inner;
							break;
						}
					}
					if ( $label_node ) {
						$label = strtolower( rtrim( self::plain( $label_node->textContent ), ':' ) );
						if ( '' !== $label && 0 !== strpos( $label, 'please ' ) && 'project overview' !== $label ) {
							$flush();
							if ( isset( $map[ $label ] ) ) {
								$current = $map[ $label ];
							} else {
								$unknown[] = self::plain( $label_node->textContent );
								$current   = '';
							}
							$rest = '';
							$passed = false;
							foreach ( $child->childNodes as $inner ) {
								if ( $inner === $label_node ) {
									$passed = true;
									continue;
								}
								if ( $passed ) {
									$rest .= $inner->textContent;
								}
							}
							$rest = self::plain( $rest );
							if ( '' !== $rest ) {
								$chunks[] = $rest;
							}
						}
						continue;
					}
				}
				if ( in_array( $tag, array( 'p', 'li' ), true ) ) {
					$text = self::plain( str_ireplace( array( '<br>', '<br/>', '<br />' ), "\n", $child->ownerDocument->saveHTML( $child ) ) );
					if ( '' !== $text ) {
						$chunks[] = $text;
					}
					continue;
				}
				$walk( $child );
			}
		};
		if ( $body ) {
			$walk( $body );
			$flush();
		}
		return array(
			'values'   => $values,
			'unknown'  => $unknown,
			'contacts' => $contacts,
		);
	}

	/**
	 * @param \DOMElement $node  Image block.
	 * @param array       $warnings Warnings.
	 * @return array
	 */
	private static function image_items( $node, array &$warnings ) {
		$items = array();
		foreach ( $node->getElementsByTagName( 'contentelement' ) as $element ) {
			$type = strtolower( self::child_text( $element, 'type' ) );
			$url  = self::child_text( $element, 'url' );
			$name = self::child_text( $element, 'file_name' );
			$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
			$allowed = in_array( $host, array( 'harriman.com', 'www.harriman.com', 'warpweft.slickplan.com' ), true );
			if ( 'url' === $type ) {
				$allowed = in_array( $host, array( 'harriman.com', 'www.harriman.com' ), true );
			}
			if ( '' === $url ) {
				$warnings[] = 'Image is missing a URL';
				continue;
			}
			$items[] = array(
				'type'      => $type ? $type : 'library',
				'url'       => $url,
				'file_name' => $name,
				'allowed'   => $allowed,
			);
		}
		return $items;
	}

	/**
	 * @param int    $template Template number.
	 * @param string $key      slider|grid.
	 * @param array  $items    Images.
	 * @param int    $min      Minimum.
	 * @param int    $max      Maximum.
	 * @param array  $warnings Warnings.
	 */
	private static function image_count_warning( $template, $key, array $items, $min, $max, array &$warnings ) {
		$count = count( $items );
		if ( 0 === $count ) {
			return;
		}
		if ( $count < $min || $count > $max ) {
			$warnings[] = 'Template ' . $template . ' ' . $key . ' has ' . $count . ' images; guidance is ' . $min . ( $min === $max ? '' : '–' . $max );
		}
	}

	/**
	 * @param string $value Text.
	 * @return bool
	 */
	private static function is_placeholder( $value ) {
		$value = self::plain( $value );
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
	 * @param string $title Title.
	 * @return string
	 */
	public static function normalize_title( $title ) {
		$title = strtolower( self::decode( $title ) );
		$title = str_replace( array( '’', '‘', '`', '´', '\'' ), '', $title );
		$title = str_replace( '&', ' and ', $title );
		$expanded = array();
		foreach ( preg_split( '/\s+/', $title ) as $token ) {
			$token = trim( $token, " \t\n\r\0\x0B.,:;\"“”" );
			$upper = strtoupper( $token );
			$map   = function_exists( 'ww_title_abbreviations' ) ? \ww_title_abbreviations() : array();
			if ( isset( $map[ $upper ] ) ) {
				$expanded[] = strtolower( $map[ $upper ] );
			} elseif ( '' !== $token ) {
				$expanded[] = $token;
			}
		}
		$title = implode( ' ', $expanded );
		$title = preg_replace( '/[^a-z0-9]+/', ' ', $title );
		return trim( (string) $title );
	}

	/**
	 * One title contains the other as a whole-word phrase of at least two words.
	 *
	 * @param string $haystack Normalized title.
	 * @param string $needle   Normalized title.
	 * @return bool
	 */
	private static function phrase_contains( $haystack, $needle ) {
		$haystack = trim( (string) $haystack );
		$needle   = trim( (string) $needle );
		if ( '' === $needle || '' === $haystack || $needle === $haystack ) {
			return false;
		}
		$tokens = preg_split( '/\s+/', $needle );
		if ( ! is_array( $tokens ) || count( $tokens ) < 2 ) {
			return false;
		}
		return 1 === preg_match( '/(?:^| )' . preg_quote( $needle, '/' ) . '(?: |$)/', $haystack );
	}

	/**
	 * @param string $left  Left title.
	 * @param string $right Right title.
	 * @return float
	 */
	private static function similarity( $left, $right ) {
		$a = array_values( array_filter( explode( ' ', $left ) ) );
		$b = array_values( array_filter( explode( ' ', $right ) ) );
		if ( ! $a || ! $b ) {
			return 0;
		}
		$inter = count( array_intersect( $a, $b ) );
		return ( 2 * $inter ) / ( count( $a ) + count( $b ) );
	}

	/**
	 * @param string $left  Left.
	 * @param string $right Right.
	 * @return bool
	 */
	private static function close_edit( $left, $right ) {
		if ( '' === $left || '' === $right || strlen( $left ) > 255 || strlen( $right ) > 255 ) {
			return false;
		}
		$distance = levenshtein( $left, $right );
		return $distance >= 0 && $distance <= 2 && $left !== $right;
	}

	/**
	 * @param string $html HTML.
	 * @return \DOMDocument
	 */
	private static function load_html( $html ) {
		$doc = new \DOMDocument();
		$old = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $old );
		return $doc;
	}

	/**
	 * @param string $value Text.
	 * @return string
	 */
	private static function decode( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * @param string $value Text.
	 * @return string
	 */
	private static function plain( $value ) {
		$value = self::decode( strip_tags( (string) $value ) );
		$value = str_replace( "\xc2\xa0", ' ', $value );
		$value = preg_replace( '/[ \t]+/', ' ', $value );
		$value = preg_replace( "/\n{3,}/", "\n\n", (string) $value );
		return trim( (string) $value );
	}

	/**
	 * @param \DOMElement|null $node Node.
	 * @param string           $tag  Child tag.
	 * @return string
	 */
	private static function child_text( $node, $tag ) {
		$child = self::child( $node, $tag );
		return $child ? $child->textContent : '';
	}

	/**
	 * @param \DOMNode|null $node Node.
	 * @param string        $tag  Child tag.
	 * @return \DOMElement|null
	 */
	private static function child( $node, $tag ) {
		if ( ! $node instanceof \DOMElement ) {
			return null;
		}
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && strtolower( $child->nodeName ) === strtolower( $tag ) ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * @param array  $blocks Blocks by label.
	 * @param string $label  Label.
	 * @return string
	 */
	private static function block_html( array $blocks, $label ) {
		if ( ! isset( $blocks[ $label ] ) ) {
			return '';
		}
		$content = self::child( $blocks[ $label ], 'content' );
		return $content ? $content->textContent : '';
	}

	/**
	 * Fix apply so image notes are not passed into sanitize, and found images are used.
	 * fields_for_save is the writer. prepared_fields is unused; apply calls fields_for_save
	 * and must drop the note key.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function writable_fields( array $row ) {
		$fields = self::fields_for_save( $row, (int) ( $row['post_id'] ?? 0 ) );
		unset( $fields['_image_notes'], $fields['_image_pending'] );
		return $fields;
	}
}
