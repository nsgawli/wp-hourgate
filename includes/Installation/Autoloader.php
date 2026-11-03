<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-time setup, upgrades, activation and deactivation.
 */
class Autoloader {

	/**
	 * Currently installed version
	 *
	 * @var integer
	 */
	public static $current_version;

	/**
	 * For checking whether upgrade available or not
	 *
	 * @var boolean
	 */
	public static $is_upgrade = false;

	/**
	 * Initialize the installation process.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		self::get_current_version();
		self::check_upgrade();

		if ( self::$is_upgrade ) {

			// Do not allow parallel process to run.
			if ( 'yes' === get_transient( 'hourgate_installing' ) ) {
				return;
			}

			// Set transient.
			set_transient( 'hourgate_installing', 'yes', MINUTE_IN_SECONDS * 10 );

			// Run installation.
			if ( 0 === self::$current_version ) {
				add_action( 'init', array( __CLASS__, 'initial_setup' ), 1 );
			} else {
				add_action( 'init', array( __CLASS__, 'upgrade' ), 1 );
			}

			// Delete transient.
			delete_transient( 'hourgate_installing' );
		}
	}

	/**
	 * Handle plugin activation.
	 *
	 * Called from `register_activation_hook()`, so this runs synchronously
	 * during activation itself rather than waiting on `init` like
	 * `run()` above does. On a fresh install it runs first-time setup
	 * right away instead of deferring it to the next request.
	 *
	 * @return void
	 */
	public static function activate() {

		self::get_current_version();

		if ( 0 === self::$current_version ) {
			self::initial_setup();
		}
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * Called from `register_deactivation_hook()`. Clear every cron hook the
	 * plugin schedules here with `wp_clear_scheduled_hook()`, so none of
	 * them keep firing once the plugin is off. None exist yet.
	 *
	 * @return void
	 */
	public static function deactivate() {
	}

	/**
	 * Check version
	 */
	public static function get_current_version() {
		self::$current_version = get_option( 'hourgate_current_version', 0 );
	}

	/**
	 * Check for upgrade
	 */
	public static function check_upgrade() {
		if ( HOURGATE_VERSION !== (string) self::$current_version ) {
			self::$is_upgrade = true;
		}
	}

	/**
	 * First time installation
	 */
	public static function initial_setup() {

		self::create_db_tables();

		// General settings.
		Settings\General::install();

		// Weekly hours (open 24/7 by default), closures and special hours.
		Settings\Schedule::install();
		Settings\Closures::install();

		// Enforcement switch (off by default), closed mode and message.
		Settings\Behaviour::install();

		// "Not paused", so the autoloaded pause option always exists.
		\HourGate\Core\Pause::install();

		self::set_installation_complete();
	}

	/**
	 * Create or update database tables.
	 *
	 * Add each `CREATE TABLE {$wpdb->prefix}hourgate_*` statement here
	 * and run it through `dbDelta()`. No tables exist yet.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function create_db_tables() {
	}

	/**
	 * Upgrade the version
	 */
	public static function upgrade() {

		Upgrades\Upgrade::run( self::$current_version );
		self::set_installation_complete();
	}

	/**
	 * Mark installation as complete
	 */
	public static function set_installation_complete() {

		update_option( 'hourgate_current_version', HOURGATE_VERSION );
		self::$current_version = HOURGATE_VERSION;
		self::$is_upgrade      = false;
	}
}
