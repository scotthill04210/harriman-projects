<?php
/**
 * Tools → Slickplan import. Dry run until an administrator applies checked rows.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class SlickplanAdmin {

	const OPTION = 'ww_slickplan_dry_run';
	const LOG    = 'ww_slickplan_apply_log';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'wp_ajax_ww_slickplan_batch', array( __CLASS__, 'batch' ) );
		add_action( 'admin_post_ww_slickplan_revert', array( __CLASS__, 'revert' ) );
		add_action( 'admin_post_ww_slickplan_csv', array( __CLASS__, 'csv' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Tools page.
	 */
	public static function menu() {
		add_management_page(
			__( 'Slickplan import', 'ww-projects' ),
			__( 'Slickplan import', 'ww-projects' ),
			'manage_options',
			'ww-slickplan-import',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Progress script.
	 *
	 * @param string $hook Current page.
	 */
	public static function assets( $hook ) {
		if ( 'tools_page_ww-slickplan-import' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'ww-slickplan-admin',
			WW_PLUGIN_URL . 'assets/js/ww-slickplan-admin.js',
			array(),
			WW_VERSION,
			true
		);
		wp_localize_script(
			'ww-slickplan-admin',
			'wwSlickplan',
			array(
				'nonce' => wp_create_nonce( 'ww_slickplan_batch' ),
			)
		);
	}

	/**
	 * Page body.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$files  = Slickplan::xml_files();
		$result = null;
		$error  = '';
		if ( isset( $_POST['ww_slickplan_dry'] ) ) {
			check_admin_referer( 'ww_slickplan_dry' );
			$uploaded = self::store_upload();
			if ( is_wp_error( $uploaded ) ) {
				$error = $uploaded->get_error_message();
			} else {
				$files    = Slickplan::xml_files();
				$basename = '' !== $uploaded ? $uploaded : ( isset( $_POST['ww_xml'] ) ? wp_unslash( $_POST['ww_xml'] ) : '' );
				$path     = Slickplan::xml_path( $basename );
				if ( is_wp_error( $path ) ) {
					$error = $path->get_error_message();
				} else {
					$result = Slickplan::dry_run( $path );
					if ( is_wp_error( $result ) ) {
						$error  = $result->get_error_message();
						$result = null;
					} else {
						update_option( self::OPTION, $result, false );
					}
				}
			}
		} else {
			$stored = get_option( self::OPTION );
			if ( is_array( $stored ) && isset( $stored['rows'] ) ) {
				$result = $stored;
			}
		}

		$fresh = Slickplan::backup_is_fresh();
		echo '<div class="wrap">';
		if ( isset( $_GET['ww_revert'] ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['ww_revert'] ) ) ) . '</p></div>';
		}
		echo '<h1>' . esc_html__( 'Slickplan import', 'ww-projects' ) . '</h1>';
		echo '<p>' . esc_html__( 'Dry run reads the XML and matches projects. Nothing is written until you import the checked rows.', 'ww-projects' ) . '</p>';
		if ( ! $fresh ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Apply stays off until Tools → Project backup has been run in the last 24 hours.', 'ww-projects' ) . '</p></div>';
		}
		if ( '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'ww_slickplan_dry' );
		echo '<p><label for="ww_xml">' . esc_html__( 'XML file', 'ww-projects' ) . '</label> ';
		echo '<select id="ww_xml" name="ww_xml">';
		$private = trailingslashit( Slickplan::private_dir() );
		foreach ( $files as $name => $path ) {
			$where = 0 === strpos( (string) $path, $private ) ? __( 'private', 'ww-projects' ) : __( 'docs', 'ww-projects' );
			echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name . ' (' . $where . ')' ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="ww_xml_upload">' . esc_html__( 'Upload a replacement', 'ww-projects' ) . '</label> ';
		echo '<input id="ww_xml_upload" type="file" name="ww_xml_upload" accept=".xml,text/xml,application/xml" /></p>';
		echo '<p class="description">' . esc_html__( 'The export is read from the private import folder, outside the web root. An upload is stored there and is not publicly served.', 'ww-projects' ) . '</p>';
		if ( ! $files ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No XML is in the private import folder yet. Upload the Slickplan export.', 'ww-projects' ) . '</p></div>';
		}
		submit_button( __( 'Dry run', 'ww-projects' ), 'secondary', 'ww_slickplan_dry', false );
		echo '</form>';

		if ( ! is_array( $result ) ) {
			echo '</div>';
			return;
		}

		$counts = $result['counts'];
		echo '<h2>' . esc_html( sprintf( '56 expected. XML has %d projects (T1 %d, T2 %d, T3 %d) and %d empty stubs.', (int) $counts[1] + (int) $counts[2] + (int) $counts[3], (int) $counts[1], (int) $counts[2], (int) $counts[3], count( $result['stubs'] ) ) ) . '</h2>';
		if ( ! empty( $result['manifest'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Manifest differences:', 'ww-projects' ) . '</p><ul>';
			foreach ( $result['manifest'] as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></div>';
		} else {
			echo '<p>' . esc_html__( 'The manifest matches the XML.', 'ww-projects' ) . '</p>';
		}

		$csv = wp_nonce_url( admin_url( 'admin-post.php?action=ww_slickplan_csv&report=dry' ), 'ww_slickplan_csv' );
		echo '<p><a class="button" href="' . esc_url( $csv ) . '">' . esc_html__( 'Download report', 'ww-projects' ) . '</a></p>';
		echo '<p><button type="button" class="button button-primary" id="ww-slickplan-apply" ' . disabled( ! $fresh, true, false ) . '>' . esc_html__( 'Import selected', 'ww-projects' ) . '</button></p>';
		echo '<div class="ww-slickplan-progress" style="height:12px;background:#dcdcde;max-width:480px;margin:8px 0"><div id="ww-slickplan-bar" style="height:12px;width:0;background:#2271b1"></div></div>';
		echo '<p id="ww-slickplan-status"></p>';

		echo '<table class="widefat striped"><thead><tr>';
		$heads = array( '', 'Cell', 'Title', 'Template', 'Match', 'Title change', 'Markets / services', 'Images', 'Warnings', 'Status' );
		foreach ( $heads as $head ) {
			echo '<th>' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $result['rows'] as $row ) {
			self::row( $row );
		}
		echo '</tbody></table></div>';
	}

	/**
	 * One dry-run row.
	 *
	 * @param array $row Row.
	 */
	private static function row( array $row ) {
		$post_id = (int) $row['post_id'];
		echo '<tr class="ww-slickplan-row" data-cell="' . esc_attr( $row['cell_id'] ) . '" data-post="' . esc_attr( (string) $post_id ) . '">';
		echo '<td><input type="checkbox" class="ww-slickplan-check" ' . checked( ! empty( $row['checked'] ), true, false ) . disabled( ! empty( $row['flag_off'] ), true, false ) . ' /></td>';
		echo '<td><code>' . esc_html( $row['cell_id'] ) . '</code></td>';
		echo '<td>' . esc_html( $row['sitemap_title'] );
		if ( '' !== $row['headline'] && $row['headline'] !== $row['sitemap_title'] ) {
			echo '<br><span class="description">' . esc_html( $row['headline'] ) . '</span>';
		}
		echo '</td>';
		echo '<td>' . esc_html( (string) $row['template'] ) . '</td>';
		$picked = $post_id;
		if ( ! $picked && 'needs review' === $row['status'] && ! empty( $row['candidates'] ) ) {
			$picked = (int) $row['candidates'][0]['post_id'];
		}
		echo '<td>';
		if ( $post_id ) {
			echo esc_html( $row['post_title'] . ' #' . $post_id . ' (' . $row['confidence'] . ')' );
		} else {
			echo esc_html( 'No match' );
		}
		if ( ! empty( $row['candidates'] ) ) {
			echo '<br><select>';
			if ( ! $picked ) {
				echo '<option value="0">' . esc_html__( 'Create draft', 'ww-projects' ) . '</option>';
			}
			foreach ( $row['candidates'] as $candidate ) {
				echo '<option value="' . esc_attr( (string) $candidate['post_id'] ) . '" ' . selected( (int) $candidate['post_id'], $picked, false ) . '>' . esc_html( $candidate['title'] . ' #' . $candidate['post_id'] . ' (' . $candidate['score'] . ')' ) . '</option>';
			}
			if ( $picked ) {
				echo '<option value="0">' . esc_html__( 'Create draft instead', 'ww-projects' ) . '</option>';
			}
			echo '</select>';
		}
		echo '</td>';
		echo '<td>';
		if ( '' !== $row['title_new'] ) {
			echo '<label><input type="checkbox" class="ww-slickplan-title" /> ' . esc_html__( 'Update title', 'ww-projects' ) . '</label><br>';
			echo esc_html( $row['title_old'] . ' → ' . $row['title_new'] );
		}
		echo '</td>';
		$market_names = self::term_names( 'market', (array) $row['market_ids'] );
		$service_names = self::term_names( 'service', (array) $row['service_ids'] );
		$market_now = self::current_term_names( $post_id, 'market' );
		$service_now = self::current_term_names( $post_id, 'service' );
		echo '<td>' . esc_html( 'Markets: ' . self::term_list( $market_now ) . ' → ' . self::term_list( $market_names ) );
		echo '<br>' . esc_html( 'Services: ' . self::term_list( $service_now ) . ' → ' . self::term_list( $service_names ) ) . '</td>';
		$counts = $row['image_counts'];
		echo '<td>' . esc_html( sprintf( 'hero %d, slider %d, grid %d, single %d; %d in library, %d to fetch, %d without alt', (int) $counts['hero'], (int) $counts['slider'], (int) $counts['grid'], (int) $counts['single_image'], (int) $row['images_library'], (int) $row['images_missing'], (int) $row['images_no_alt'] ) ) . '</td>';
		echo '<td>' . esc_html( implode( '; ', array_unique( (array) $row['warnings'] ) ) ) . '</td>';
		echo '<td class="ww-slickplan-state">' . esc_html( $row['status'] );
		if ( $post_id && ( get_post_meta( $post_id, '_ww_import_prev', true ) || get_post_meta( $post_id, '_ww_import_created', true ) ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ww_slickplan_revert&post_id=' . $post_id ), 'ww_slickplan_revert_' . $post_id );
			echo '<br><a href="' . esc_url( $url ) . '">' . esc_html__( 'Revert import', 'ww-projects' ) . '</a>';
		}
		echo '</td></tr>';
	}

	/**
	 * Apply up to five checked rows.
	 */
	public static function batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot import projects.', 'ww-projects' ) ), 403 );
		}
		check_ajax_referer( 'ww_slickplan_batch' );
		if ( ! Slickplan::backup_is_fresh() ) {
			wp_send_json_error( array( 'message' => __( 'Run Tools → Project backup in the last 24 hours before applying.', 'ww-projects' ) ), 400 );
		}
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) || empty( $stored['rows'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Run a dry run first.', 'ww-projects' ) ), 400 );
		}
		$by_cell = array();
		foreach ( $stored['rows'] as $row ) {
			$by_cell[ $row['cell_id'] ] = $row;
		}
		$items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		$items  = array_slice( $items, 0, 5 );
		if ( isset( $_POST['reset_fails'] ) && '1' === (string) wp_unslash( $_POST['reset_fails'] ) ) {
			Slickplan::reset_failed_images();
		}
		Slickplan::reset_download_budget();
		$out    = array();
		$log    = get_option( self::LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		foreach ( $items as $item ) {
			$cell_id = isset( $item['cell_id'] ) ? (string) $item['cell_id'] : '';
			$post_id = isset( $item['post_id'] ) ? (int) $item['post_id'] : 0;
			if ( ! isset( $by_cell[ $cell_id ] ) ) {
				$out[] = array( 'cell_id' => $cell_id, 'error' => 'Unknown row' );
				continue;
			}
			$row = $by_cell[ $cell_id ];
			$row['update_title'] = isset( $item['update_title'] ) && '1' === (string) $item['update_title'];
			$result = Slickplan::apply_row( $row, $post_id );
			if ( is_wp_error( $result ) ) {
				$out[] = array( 'cell_id' => $cell_id, 'error' => $result->get_error_message() );
				$log[] = array( 'cell_id' => $cell_id, 'status' => $result->get_error_message() );
				continue;
			}
			$out[] = array_merge( array( 'cell_id' => $cell_id ), $result );
			$log[] = array_merge( array( 'cell_id' => $cell_id ), $result );
			foreach ( (array) ( $result['images'] ?? array() ) as $note ) {
				$log[] = array(
					'cell_id' => $cell_id,
					'post_id' => $result['post_id'] ?? $post_id,
					'status'  => $note,
				);
			}
		}
		update_option( self::LOG, $log, false );
		wp_send_json_success( array( 'results' => $out ) );
	}

	/**
	 * Restore one post.
	 */
	public static function revert() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot revert this import.', 'ww-projects' ) );
		}
		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		check_admin_referer( 'ww_slickplan_revert_' . $post_id );
		$result = Slickplan::revert_post( $post_id );
		$notice = is_wp_error( $result ) ? $result->get_error_message() : $result['status'];
		wp_safe_redirect( add_query_arg( array( 'page' => 'ww-slickplan-import', 'ww_revert' => rawurlencode( $notice ) ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Download the dry run or the apply log.
	 */
	public static function csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot download this report.', 'ww-projects' ) );
		}
		check_admin_referer( 'ww_slickplan_csv' );
		$report = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : 'dry';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="slickplan-' . $report . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( 'apply' === $report ) {
			fputcsv( $out, array( 'cell_id', 'post_id', 'status' ) );
			foreach ( (array) get_option( self::LOG, array() ) as $line ) {
				fputcsv( $out, array( $line['cell_id'] ?? '', $line['post_id'] ?? '', $line['status'] ?? ( $line['error'] ?? '' ) ) );
			}
			fclose( $out );
			exit;
		}
		fputcsv( $out, array( 'cell_id', 'sitemap_title', 'headline', 'template', 'post_id', 'post_title', 'confidence', 'status', 'title_new', 'warnings' ) );
		$stored = get_option( self::OPTION, array() );
		foreach ( (array) ( $stored['rows'] ?? array() ) as $row ) {
			fputcsv(
				$out,
				array(
					self::csv_cell( $row['cell_id'] ),
					self::csv_cell( $row['sitemap_title'] ),
					self::csv_cell( $row['headline'] ),
					$row['template'],
					$row['post_id'],
					self::csv_cell( $row['post_title'] ),
					$row['confidence'],
					$row['status'],
					self::csv_cell( $row['title_new'] ),
					self::csv_cell( implode( ' | ', (array) $row['warnings'] ) ),
				)
			);
		}
		fputcsv( $out, array() );
		fputcsv( $out, array( 'Fuzzy matches' ) );
		fputcsv( $out, array( 'match', 'post_id', 'score' ) );
		foreach ( (array) ( $stored['rows'] ?? array() ) as $row ) {
			if ( 'fuzzy' !== ( $row['confidence'] ?? '' ) ) {
				continue;
			}
			$candidates = (array) ( $row['candidates'] ?? array() );
			if ( ! $candidates && ! empty( $row['post_id'] ) ) {
				$candidates[] = array(
					'title'   => $row['post_title'],
					'post_id' => $row['post_id'],
					'score'   => '',
				);
			}
			foreach ( $candidates as $candidate ) {
				fputcsv(
					$out,
					array(
						self::csv_cell( $row['sitemap_title'] . ' → ' . ( $candidate['title'] ?? '' ) ),
						$candidate['post_id'] ?? '',
						$candidate['score'] ?? '',
					)
				);
			}
		}
		fclose( $out );
		exit;
	}

	/**
	 * Save an uploaded export outside the web root.
	 *
	 * @return string|\WP_Error Basename, or an empty string when nothing was uploaded.
	 */
	private static function store_upload() {
		if ( empty( $_FILES['ww_xml_upload']['name'] ) ) {
			return '';
		}
		$file = $_FILES['ww_xml_upload'];
		if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return '';
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'ww_xml', __( 'The XML upload failed.', 'ww-projects' ) );
		}
		$name = basename( str_replace( '\\', '/', (string) $file['name'] ) );
		if ( ! preg_match( '/\.xml$/i', $name ) ) {
			return new \WP_Error( 'ww_xml', __( 'Upload an XML file.', 'ww-projects' ) );
		}
		$head = (string) file_get_contents( $file['tmp_name'], false, null, 0, 800 );
		if ( false === stripos( $head, '<sitemap' ) && false === stripos( $head, '<?xml' ) ) {
			return new \WP_Error( 'ww_xml', __( 'That file is not a Slickplan XML export.', 'ww-projects' ) );
		}
		$dir = Slickplan::private_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'ww_xml', __( 'The private import folder is not writable.', 'ww-projects' ) );
		}
		$dest = trailingslashit( $dir ) . $name;
		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new \WP_Error( 'ww_xml', __( 'The XML could not be stored outside the web root.', 'ww-projects' ) );
		}
		return $name;
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @param array  $ids      Term IDs.
	 * @return string[]
	 */
	private static function term_names( $taxonomy, array $ids ) {
		$names = array();
		foreach ( $ids as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	/**
	 * @param int    $post_id  Project ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private static function current_term_names( $post_id, $taxonomy ) {
		if ( ! $post_id ) {
			return array();
		}
		$terms = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * @param string[] $names Term names.
	 * @return string
	 */
	private static function term_list( array $names ) {
		$names = array_values( array_filter( array_map( 'strval', $names ) ) );
		natcasesort( $names );
		$names = array_values( $names );
		return $names ? implode( ', ', $names ) : __( 'none', 'ww-projects' );
	}

	/**
	 * @param string $value Cell.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
