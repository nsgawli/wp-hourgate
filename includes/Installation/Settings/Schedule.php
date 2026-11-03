<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation\Settings;

use HourGate\Core\Schedule\WeeklyHours;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule settings defaults: the weekly hours.
 *
 * Open 24 hours every day, so activating HourGate changes nothing for
 * shoppers until the merchant sets hours (study 10, D4).
 */
class Schedule {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'hourgate_schedule_settings';

	/**
	 * Install the defaults. `add_option()` never overwrites hours already saved,
	 * even if first-time setup runs again (it does whenever the version option
	 * is missing).
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
			'weekly' => array_fill_keys(
				WeeklyHours::DAYS,
				array(
					array(
						'start' => '00:00',
						'end'   => '24:00',
					),
				)
			),
		);
	}
}
