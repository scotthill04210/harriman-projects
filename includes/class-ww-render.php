<?php
/**
 * Front-end output for flagged projects.
 *
 * The theme renders Blade inside template_include at PHP_INT_MAX, so a plugin
 * template path never reaches WordPress. content-single-project.blade.php is
 * only the_content(), and the layout prints no title or hero, so replacing
 * the_content keeps the header and footer.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Render {

	/**
	 * Prevent the_content from calling itself while a template renders.
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'the_content', array( __CLASS__, 'skip_legacy_blocks' ), 0 );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Replace the legacy block layout with the chosen template.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	/**
	 * The saved block layout is replaced below. Skip parsing it on the way there.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public static function skip_legacy_blocks( $content ) {
		if ( self::replaces_content() ) {
			remove_filter( 'the_content', 'do_blocks', 9 );
		}
		return $content;
	}

	/**
	 * Whether this request renders a flagged project template.
	 *
	 * @return bool
	 */
	private static function replaces_content() {
		if ( self::$rendering || is_admin() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ) ) {
			return false;
		}
		if ( ! is_singular( WW_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		if ( post_password_required() ) {
			return false;
		}
		return (bool) ww_uses_new_template( get_the_ID() );
	}

	public static function content( $content ) {
		if ( ! self::replaces_content() ) {
			return $content;
		}
		$post_id = get_the_ID();

		ww_prime_project_media( $post_id );
		$template = ww_active_template( $post_id );
		self::$rendering = true;
		ob_start();
		if ( $template ) {
			include WW_PLUGIN_DIR . 'templates/single-project.php';
		} else {
			ww_render_header();
			ww_render_terms();
		}
		$html = ob_get_clean();
		self::$rendering = false;
		add_filter( 'the_content', 'do_blocks', 9 );

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Body classes for the active template only.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( is_admin() || ! is_singular( WW_POST_TYPE ) ) {
			return $classes;
		}
		if ( ! ww_uses_new_template( get_queried_object_id() ) ) {
			return $classes;
		}
		$classes[] = 'ww-project';
		$template  = ww_active_template( get_queried_object_id() );
		if ( $template ) {
			$classes[] = 'ww-template-' . $template;
		}
		return $classes;
	}

	/**
	 * Base styles plus the one template's stylesheet and script.
	 *
	 * Front end only. Runs after the theme registers its stylesheet.
	 */
	public static function enqueue() {
		if ( is_admin() || ! is_singular( WW_POST_TYPE ) || ! ww_uses_new_template( get_queried_object_id() ) ) {
			return;
		}
		$template = ww_active_template( get_queried_object_id() );
		wp_enqueue_style(
			'ww-project-base',
			WW_PLUGIN_URL . 'assets/css/ww-project-base.css',
			self::theme_style_deps(),
			self::asset_version( 'assets/css/ww-project-base.css' )
		);
		if ( ! $template ) {
			return;
		}
		$css_relative = 'assets/css/ww-template-' . $template . '.css';
		$css_path     = WW_PLUGIN_DIR . $css_relative;
		$css_body     = is_readable( $css_path ) ? (string) file_get_contents( $css_path ) : '';
		if ( false !== strpos( $css_body, '{' ) ) {
			wp_enqueue_style(
				'ww-template-' . $template,
				WW_PLUGIN_URL . $css_relative,
				array( 'ww-project-base' ),
				self::asset_version( $css_relative )
			);
		}
		$js_relative = 'assets/js/ww-template-' . $template . '.js';
		if ( is_readable( WW_PLUGIN_DIR . $js_relative ) ) {
			wp_enqueue_script(
				'ww-template-' . $template,
				WW_PLUGIN_URL . $js_relative,
				array(),
				self::asset_version( $js_relative ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Theme stylesheet handle. Sage registers it as sage/main.css.
	 *
	 * @return string[]
	 */
	private static function theme_style_deps() {
		return array( 'sage/main.css' );
	}

	/**
	 * filemtime query arg so a deploy busts the host and browser cache.
	 *
	 * @param string $relative Path under the plugin root.
	 * @return string
	 */
	private static function asset_version( $relative ) {
		$path  = WW_PLUGIN_DIR . ltrim( $relative, '/' );
		$mtime = file_exists( $path ) ? filemtime( $path ) : false;
		return $mtime ? (string) $mtime : WW_VERSION;
	}
}
