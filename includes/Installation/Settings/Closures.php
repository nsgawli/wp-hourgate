<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closures settings defaults: closures and special hours. None by default.
 */
class Closures {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'hourgate_closures_settings';

	/**
	 * Install the defaults. `add_option()` never overwrites saved entries.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function install() {

		add_option( self::OPTION, self::get_default_settings() );
	}

	/**
	 * Get default settings.
	 *
	 * @version 1.0.0
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'closures'     => array(),
			'specialHours' => array(),
		);
	}
}
