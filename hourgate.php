<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- The main plugin file is named after the plugin slug, not its bootstrap class.
/**
 * Plugin Name: HourGate – Store Hours for WooCommerce
 * Description: Store hours for WooCommerce: take orders only while you're open. Holiday closures, a pause switch, and a clear closed message for shoppers.
 * Version: 1.0.0
 * Author: Nikhil
 * Author URI: https://prefixwp.com/
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0
 * WC tested up to: 11.2
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: hourgate
 *
 * @package hourgate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap: constants, autoloader and activation hooks.
 */
final class HourGate {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public static $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var HourGate
	 */
	public static function init() {

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_links' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_compatibility' ) );
	}

	/**
	 * Declare compatibility with WooCommerce features: HPOS (custom order
	 * tables) and the Cart and Checkout blocks.
	 *
	 * @return void
	 */
	public static function declare_woocommerce_compatibility() {

		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}

	/**
	 * Plugin activation callback.
	 *
	 * Fires immediately when the plugin is (re)activated -- in the same
	 * request as activation itself, before `plugins_loaded` has ever run
	 * for this plugin, so `includes()` hasn't executed yet. Boots just
	 * enough (constants + autoloader) to run first-time setup right away.
	 *
	 * @return void
	 */
	public static function activate() {

		self::define_constants();

		require_once HOURGATE_ABSPATH . 'vendor/autoload.php';

		HourGate\Installation\Autoloader::activate();

		set_transient( 'hourgate_activation_redirect', get_current_user_id(), MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * Fires immediately when the plugin is deactivated, same boot
	 * pattern as activate() above (constants + autoloader only, since
	 * `plugins_loaded` hasn't necessarily run for this request).
	 *
	 * @return void
	 */
	public static function deactivate() {

		self::define_constants();

		require_once HOURGATE_ABSPATH . 'vendor/autoload.php';

		HourGate\Installation\Autoloader::deactivate();
	}

	/**
	 * Include the plugin files.
	 */
	public static function includes() {

		// define constants.
		self::define_constants();

		// load plugin files.
		require_once HOURGATE_ABSPATH . 'vendor/autoload.php';

		HourGate\Init::run();
	}

	/**
	 * Defines global constants that can be availabel anywhere in WordPress
	 *
	 * @return void
	 */
	public static function define_constants() {

		self::define( 'HOURGATE_PLUGIN_FILE', __FILE__ );
		self::define( 'HOURGATE_ABSPATH', __DIR__ . '/' );
		self::define( 'HOURGATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		self::define( 'HOURGATE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		self::define( 'HOURGATE_VERSION', self::$version );

		$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only searched for the REST prefix to choose which subsystems load; never stored or printed, and enforcement (Core) loads either way.
		$is_rest_request = ( false !== strpos( $request_uri, '/' . rest_get_url_prefix() . '/' ) )
			|| isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only, to choose which subsystems load; changes no state.

		self::define( 'HOURGATE_REST_REQUEST', $is_rest_request );

		// load admin user interface.
		if ( is_admin() && ! ( wp_doing_ajax() || HOURGATE_REST_REQUEST ) ) {
			self::define( 'HOURGATE_ADMIN_INTERFACE', true );
		} else {
			self::define( 'HOURGATE_ADMIN_INTERFACE', false );
		}

		// load frontend user interface.
		if ( ! ( is_admin() || defined( 'DOING_CRON' ) || HOURGATE_REST_REQUEST ) ) {
			self::define( 'HOURGATE_FRONTEND_INTERFACE', true );
		} else {
			self::define( 'HOURGATE_FRONTEND_INTERFACE', false );
		}
	}

	/**
	 * Define constants
	 *
	 * @param string $name - name of global constant.
	 * @param string $value - value of constant.
	 * @return void
	 */
	private static function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- Only called with HOURGATE_* names from define_constants().
		}
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links - array of plugin action links.
	 * @return array
	 */
	public static function add_plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . admin_url( 'admin.php?page=hourgate-settings' ) . '">' . __( 'Settings', 'hourgate' ) . '</a>',
		);
		return array_merge( $links, $custom_links );
	}
}

register_activation_hook( __FILE__, array( 'HourGate', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HourGate', 'deactivate' ) );

HourGate::init();
