<?php
/**
 * Updates from GitHub releases. WordPress shows them on the Plugins screen.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Updater {

	const REPO = 'scotthill04210/harriman-projects';

	/**
	 * Hooks. Runs even when the project post type is missing.
	 */
	public static function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'source' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'request_args' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Add a release newer than this copy to the plugin update list.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}
		$plugin  = plugin_basename( WW_PLUGIN_FILE );
		$release = self::latest_release();
		if ( is_wp_error( $release ) || '' === $release['version'] || '' === $release['package'] ) {
			return $transient;
		}
		$item = self::item( $release );
		if ( version_compare( WW_VERSION, $release['version'], '>=' ) ) {
			$transient->no_update[ $plugin ] = $item;
			unset( $transient->response[ $plugin ] );
			return $transient;
		}
		$transient->response[ $plugin ] = $item;
		unset( $transient->no_update[ $plugin ] );
		return $transient;
	}

	/**
	 * Details for the WordPress update modal.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action Requested action.
	 * @param object $args   Request arguments.
	 * @return mixed
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'harriman-projects' !== $args->slug ) {
			return $result;
		}
		$release = self::latest_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}
		$notes = '' !== $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '<p>' . esc_html__( 'Release notes are on GitHub.', 'ww-projects' ) . '</p>';
		return (object) array(
			'name'          => 'Harriman Projects',
			'slug'          => 'harriman-projects',
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/scotthill04210">Warp+Weft</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'download_link' => $release['package'],
			'trunk'         => $release['package'],
			'sections'      => array(
				'description' => esc_html__( 'Per-project templates for the project post type.', 'ww-projects' ),
				'changelog'   => $notes,
			),
		);
	}

	/**
	 * GitHub archives wrap the plugin in one folder. Step into it.
	 *
	 * @param string       $source        Extracted path.
	 * @param string       $remote_source Working directory.
	 * @param \WP_Upgrader $upgrader      Upgrader.
	 * @param array        $args          Extra arguments.
	 * @return string|\WP_Error
	 */
	public static function source( $source, $remote_source, $upgrader, $args ) {
		unset( $remote_source, $upgrader );
		if ( empty( $args['plugin'] ) || plugin_basename( WW_PLUGIN_FILE ) !== $args['plugin'] ) {
			return $source;
		}
		if ( file_exists( $source . 'harriman-projects.php' ) ) {
			return $source;
		}
		$dirs = glob( trailingslashit( $source ) . '*', GLOB_ONLYDIR );
		if ( is_array( $dirs ) && 1 === count( $dirs ) && file_exists( trailingslashit( $dirs[0] ) . 'harriman-projects.php' ) ) {
			return trailingslashit( $dirs[0] );
		}
		return $source;
	}

	/**
	 * GitHub rejects downloads that have no user agent.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function request_args( $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! in_array( $host, array( 'github.com', 'api.github.com', 'codeload.github.com' ), true ) ) {
			return $args;
		}
		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['User-Agent'] = 'Harriman-Projects/' . WW_VERSION;
		return $args;
	}

	/**
	 * Check for updates link on the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}
		$url = wp_nonce_url( admin_url( 'plugins.php?ww_check_updates=1' ), 'ww_check_updates' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'ww-projects' ) . '</a>';
		return $links;
	}

	/**
	 * Clear the cached release and ask WordPress to check again.
	 */
	public static function handle_check() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		global $pagenow;
		if ( 'plugins.php' !== $pagenow || empty( $_GET['ww_check_updates'] ) ) {
			return;
		}
		check_admin_referer( 'ww_check_updates' );
		delete_site_transient( 'ww_github_release' );
		delete_site_transient( 'update_plugins' );
		$release = self::latest_release();
		wp_update_plugins();
		$message = is_wp_error( $release ) ? $release->get_error_message() : '';
		set_transient( 'ww_update_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'plugins.php?ww_update_checked=1' ) );
		exit;
	}

	/**
	 * Result of the manual check.
	 */
	public static function notice() {
		if ( ! current_user_can( 'update_plugins' ) || empty( $_GET['ww_update_checked'] ) ) {
			return;
		}
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}
		$error = get_transient( 'ww_update_notice_' . get_current_user_id() );
		delete_transient( 'ww_update_notice_' . get_current_user_id() );
		if ( is_string( $error ) && '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			return;
		}
		$plugin  = plugin_basename( WW_PLUGIN_FILE );
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->response[ $plugin ] ) ) {
			$version = (string) $updates->response[ $plugin ]->new_version;
			echo '<div class="notice notice-warning"><p>';
			echo esc_html( sprintf(
				/* translators: %s: version number. */
				__( 'Harriman Projects %s is available. Use the update link on this page.', 'ww-projects' ),
				$version
			) );
			echo '</p></div>';
			return;
		}
		echo '<div class="notice notice-success"><p>';
		esc_html_e( 'Harriman Projects is up to date.', 'ww-projects' );
		echo '</p></div>';
	}

	/**
	 * Latest GitHub release, cached for 12 hours.
	 *
	 * @return array{version:string,package:string,url:string,notes:string}|\WP_Error
	 */
	private static function latest_release() {
		$cached = get_site_transient( 'ww_github_release' );
		if ( is_array( $cached ) && isset( $cached['version'], $cached['package'] ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Harriman-Projects/' . WW_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'ww_update',
				sprintf(
					/* translators: %d: HTTP status. */
					__( 'GitHub did not return a release (HTTP %d).', 'ww-projects' ),
					$code
				)
			);
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'ww_update', __( 'GitHub returned an unreadable release.', 'ww-projects' ) );
		}
		$package = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'harriman-projects.zip' === $asset['name'] ) {
					$package = self::package_url( (string) $asset['browser_download_url'] );
					break;
				}
			}
		}
		if ( '' === $package && ! empty( $data['zipball_url'] ) ) {
			$package = self::package_url( (string) $data['zipball_url'] );
		}
		$release = array(
			'version' => ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ),
			'package' => $package,
			'url'     => esc_url_raw( (string) ( $data['html_url'] ?? '' ) ),
			'notes'   => (string) ( $data['body'] ?? '' ),
		);
		if ( '' === $release['version'] || '' === $release['package'] ) {
			return new \WP_Error( 'ww_update', __( 'The latest GitHub release has no installable package.', 'ww-projects' ) );
		}
		set_site_transient( 'ww_github_release', $release, 12 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Object WordPress stores in the update transient.
	 *
	 * @param array{version:string,package:string,url:string,notes:string} $release Release.
	 * @return object
	 */
	private static function item( array $release ) {
		return (object) array(
			'id'           => 'https://github.com/' . self::REPO,
			'slug'         => 'harriman-projects',
			'plugin'       => plugin_basename( WW_PLUGIN_FILE ),
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => '6.0',
			'requires_php' => '8.0',
		);
	}

	/**
	 * Accept only a GitHub package URL.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	private static function package_url( $url ) {
		$url  = esc_url_raw( $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$allowed = array(
			'github.com',
			'api.github.com',
			'codeload.github.com',
			'objects.githubusercontent.com',
			'release-assets.githubusercontent.com',
		);
		if ( ! wp_http_validate_url( $url ) || ! in_array( $host, $allowed, true ) ) {
			return '';
		}
		return $url;
	}
}
