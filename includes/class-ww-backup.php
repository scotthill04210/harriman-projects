<?php
/**
 * One-shot inventory of every project. Writes a file. Does not change posts.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Backup {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ww_download_project_backup', array( __CLASS__, 'download' ) );
	}

	/**
	 * Tools → Project backup.
	 */
	public static function menu() {
		add_management_page(
			__( 'Project backup', 'ww-projects' ),
			__( 'Project backup', 'ww-projects' ),
			'manage_options',
			'ww-project-backup',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Button, then the file path, size, and per-key row counts.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Project backup', 'ww-projects' ) . '</h1>';
		echo '<p>' . esc_html__( 'Writes a JSON copy of every project post and all of its meta. It does not change the database.', 'ww-projects' ) . '</p>';

		if ( isset( $_POST['ww_project_backup'] ) ) {
			check_admin_referer( 'ww_project_backup' );
			$result = self::create();
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>';
				echo esc_html(
					sprintf(
						/* translators: 1: absolute path, 2: file size, 3: project count */
						__( 'Backup written to %1$s (%2$s). %3$d projects.', 'ww-projects' ),
						$result['path'],
						size_format( $result['bytes'] ),
						$result['count']
					)
				);
				if ( ! empty( $result['public'] ) ) {
					echo ' ' . esc_html__( 'The public URL served the file, so it was moved out of the web root. Download it from this screen, then delete the file.', 'ww-projects' );
				}
				echo '</p></div>';
				echo '<h2>' . esc_html__( 'Posts by status', 'ww-projects' ) . '</h2>';
				echo '<ul>';
				foreach ( $result['statuses'] as $status => $count ) {
					echo '<li>' . esc_html( $status . ': ' . $count ) . '</li>';
				}
				echo '</ul>';
				echo '<h2>' . esc_html__( 'Meta row counts', 'ww-projects' ) . '</h2>';
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Meta key', 'ww-projects' ) . '</th><th>' . esc_html__( 'Rows', 'ww-projects' ) . '</th></tr></thead><tbody>';
				foreach ( $result['keys'] as $key => $count ) {
					echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td>' . esc_html( (string) $count ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}

		echo '<form method="post">';
		wp_nonce_field( 'ww_project_backup' );
		submit_button( __( 'Create project backup', 'ww-projects' ), 'primary', 'ww_project_backup', false );
		$stored = (string) get_option( 'ww_project_backup_file', '' );
		if ( '' !== $stored && is_readable( $stored ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ww_download_project_backup' ), 'ww_download_project_backup' );
			echo ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Download backup', 'ww-projects' ) . '</a>';
		}
		echo '</form></div>';
	}

	/**
	 * Write the JSON backup and the directory guards.
	 *
	 * @return array{path:string,bytes:int,count:int,statuses:array<string,int>,keys:array<string,int>}|\WP_Error
	 */
	public static function create() {
		global $wpdb;

		$dir = self::directory();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$statuses = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft' );
		$posts    = get_posts(
			array(
				'post_type'      => WW_POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$by_status = array_fill_keys( $statuses, 0 );
		$records   = array();
		foreach ( $posts as $post ) {
			if ( ! isset( $by_status[ $post->post_status ] ) ) {
				$by_status[ $post->post_status ] = 0;
			}
			++$by_status[ $post->post_status ];

			$terms = array();
			foreach ( array( 'market', 'service', 'project-location' ) as $taxonomy ) {
				$ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
				$terms[ $taxonomy ] = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
			}

			$records[] = array(
				'ID'           => (int) $post->ID,
				'post_title'   => $post->post_title,
				'post_name'    => $post->post_name,
				'post_status'  => $post->post_status,
				'post_content' => $post->post_content,
				'terms'        => $terms,
				'meta'         => get_post_meta( $post->ID ),
			);
		}

		$key_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key, COUNT(*) AS row_count
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				GROUP BY pm.meta_key
				ORDER BY pm.meta_key ASC",
				WW_POST_TYPE
			),
			ARRAY_A
		);
		$keys = array();
		foreach ( (array) $key_rows as $row ) {
			$keys[ $row['meta_key'] ] = (int) $row['row_count'];
		}

		$payload = array(
			'generated'      => gmdate( 'c' ),
			'project_count'  => count( $records ),
			'statuses'       => $by_status,
			'meta_key_counts'=> $keys,
			'posts'          => $records,
		);

		$token = wp_generate_password( 32, false, false );
		$path  = $dir . '/project-backup-' . gmdate( 'Ymd-Hi' ) . '-' . $token . '.json';
		$json  = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || false === file_put_contents( $path, $json ) ) {
			return new \WP_Error( 'ww_backup_write', __( 'Could not write the backup file.', 'ww-projects' ) );
		}
		chmod( $path, 0640 );

		$public = self::public_url_serves_file( $path );
		if ( $public ) {
			$moved = self::move_out_of_web_root( $path );
			if ( is_wp_error( $moved ) ) {
				wp_delete_file( $path );
				return $moved;
			}
			$path = $moved;
		}

		update_option( 'ww_project_backup_file', $path, false );

		return array(
			'path'     => $path,
			'bytes'    => (int) filesize( $path ),
			'count'    => count( $records ),
			'statuses' => $by_status,
			'keys'     => $keys,
			'public'   => $public,
		);
	}

	/**
	 * Stream the latest backup to a logged-in administrator.
	 */
	public static function download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot download this backup.', 'ww-projects' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ww_download_project_backup' );
		$path = (string) get_option( 'ww_project_backup_file', '' );
		if ( '' === $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'No backup file is available.', 'ww-projects' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path );
		exit;
	}

	/**
	 * Prefer a directory outside the web root. Fall back to wp-content/ww-backups.
	 *
	 * @return string|\WP_Error
	 */
	private static function directory() {
		$candidates = array(
			dirname( ABSPATH ) . '/ww-private',
			sys_get_temp_dir() . '/ww-private',
		);
		foreach ( $candidates as $private ) {
			if ( wp_mkdir_p( $private ) && is_writable( $private ) ) {
				return $private;
			}
		}
		$dir = WP_CONTENT_DIR . '/ww-backups';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'ww_backup_dir', __( 'Could not create a backup directory.', 'ww-projects' ) );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		return $dir;
	}

	/**
	 * Ask the public site for this file with no cookies.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private static function public_url_serves_file( $path ) {
		if ( 0 !== strpos( wp_normalize_path( $path ), wp_normalize_path( ABSPATH ) ) ) {
			return false;
		}
		$relative = ltrim( substr( wp_normalize_path( $path ), strlen( wp_normalize_path( ABSPATH ) ) ), '/' );
		$url      = home_url( '/' . $relative );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'cookies'     => array(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		return 200 === $code && false !== strpos( $body, '"project_count"' );
	}

	/**
	 * Move a web-readable backup outside the document root.
	 *
	 * @param string $path Absolute path inside the web root.
	 * @return string|\WP_Error New path.
	 */
	private static function move_out_of_web_root( $path ) {
		$dir = dirname( ABSPATH ) . '/ww-private';
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) {
			return new \WP_Error( 'ww_backup_public', __( 'The backup was reachable without a login, and it could not be moved out of the web root.', 'ww-projects' ) );
		}
		$target = $dir . '/' . basename( $path );
		if ( ! rename( $path, $target ) ) {
			return new \WP_Error( 'ww_backup_public', __( 'The backup was reachable without a login, and moving it failed.', 'ww-projects' ) );
		}
		return $target;
	}
}
