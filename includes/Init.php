<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads each subsystem's autoloader for the current request type.
 */
class Init {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		// Load installation functionality.
		Installation\Autoloader::run();

		// Load core functionality on every request type, including the Store API and cron.
		Core\Autoloader::run();

		// Load ajax functionality.
		if ( wp_doing_ajax() ) {
			Ajax\Autoloader::run();
		}

		// Load rest api functionality.
		if ( HOURGATE_REST_REQUEST ) {
			RestApi\Autoloader::init();
		}

		// Load admin interface.
		if ( HOURGATE_ADMIN_INTERFACE ) {
			Admin\Autoloader::run();
		}

		// Load frontend interface.
		if ( HOURGATE_FRONTEND_INTERFACE ) {
			Frontend\Autoloader::run();
		}
	}
}
