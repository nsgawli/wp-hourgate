<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General settings defaults.
 */
class General {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function install() {

		update_option( 'hourgate_general_settings', self::get_default_settings() );
	}

	/**
	 * Get default settings
	 *
	 * @version 1.0.0
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'removeDataOnUninstall' => false,
		);
	}
}
