<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The storefront status script (OW-037): loaded only on pages that show
 * something from it.
 *
 * Each storefront component (the notice bar, the status and hours blocks and
 * shortcodes, catalogue buttons) calls `need()` when it renders. Nothing is
 * registered, read or printed otherwise: the script is registered and
 * enqueued at `wp_print_footer_scripts` (priority 1, so anything rendered up
 * to the footer counts), in the footer, deferred, with no dependency. Its
 * settings are a small inline JSON, not `wp_localize_script()`: the strings
 * it shows come translated in the `/status` response.
 */
class Assets {

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	const HANDLE = 'hourgate-storefront-status';

	/**
	 * Extra parts of the status a component can ask for (`/status?include=`).
	 *
	 * @var string[]
	 */
	const PARTS = array( 'hours' );

	/**
	 * The script's styles, printed inline with it. Add-to-cart controls the
	 * script disables keep their place and size, dimmed as WooCommerce dims
	 * its own disabled buttons; themes can restyle the class.
	 *
	 * @var string
	 */
	const CSS = '.hourgate-closed{opacity:.5;cursor:not-allowed}';

	/**
	 * Whether something on the page needs the script.
	 *
	 * @var bool
	 */
	private static $needed = false;

	/**
	 * Extra parts asked for, as keys.
	 *
	 * @var array<string, true>
	 */
	private static $parts = array();

	/**
	 * Extra CSS components added for this page (the notice bar's), printed
	 * with the script's inline style.
	 *
	 * @var string[]
	 */
	private static $styles = array();

	/**
	 * Register the hook that prints the script. Called from
	 * `Frontend\Autoloader`; no I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'enqueue' ), 1 );
	}

	/**
	 * Ask for the status script on this page.
	 *
	 * @param string $part Optional extra part from `PARTS` (`hours`); anything else just asks for the script.
	 * @return void
	 */
	public static function need( $part = '' ) {

		self::$needed = true;

		if ( in_array( $part, self::PARTS, true ) ) {
			self::$parts[ $part ] = true;
		}
	}

	/**
	 * Add CSS for a component that asked for the script, printed inline with
	 * it. The same CSS added twice is printed once.
	 *
	 * @param string $css Static CSS from the plugin (never user data).
	 * @return void
	 */
	public static function add_style( $css ) {

		self::$styles[ md5( $css ) ] = $css;
	}

	/**
	 * Whether something asked for the script on this page.
	 *
	 * @return bool
	 */
	public static function is_needed() {
		return self::$needed;
	}

	/**
	 * Forget what was asked (between requests in tests and long-running processes).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$needed = false;
		self::$parts  = array();
		self::$styles = array();
	}

	/**
	 * The `/status` URL the script reads.
	 *
	 * @return string
	 */
	public static function status_url() {

		$url = rest_url( 'hourgate/v1/status' );

		if ( self::$parts ) {
			$url = add_query_arg( 'include', implode( ',', array_keys( self::$parts ) ), $url );
		}

		return $url;
	}

	/**
	 * Register and enqueue the script when the page needs it.
	 *
	 * @return void
	 */
	public static function enqueue() {

		if ( ! self::$needed || wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		$file = HOURGATE_ABSPATH . 'build/storefront-status/index.asset.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$asset = require $file;

		wp_register_script(
			self::HANDLE,
			HOURGATE_PLUGIN_URL . 'build/storefront-status/index.js',
			isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : HOURGATE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.hourGateStorefront = ' . wp_json_encode( array( 'url' => self::status_url() ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);

		wp_enqueue_script( self::HANDLE );

		// What the script's markup changes need, inline (no request): disabled add-to-cart controls (OW-039).
		wp_register_style( self::HANDLE, false, array(), HOURGATE_VERSION );
		wp_add_inline_style( self::HANDLE, self::CSS . implode( '', self::$styles ) );
		wp_enqueue_style( self::HANDLE );
	}
}
