<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Settings;

use HourGate\Core\Schedule\Schedule as ValidSchedule;
use HourGate\Core\Schedule\ScheduleFactory;
use HourGate\Core\StoreSchedule;
use HourGate\Installation\Settings\Closures as ClosuresSettings;
use HourGate\Installation\Settings\Schedule as ScheduleSettings;
use HourGate\RestApi\Schedule\SettingsController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closures and special hours: `GET|PUT /settings/closures`, `GET /settings/closures/defaults`.
 *
 * Body: `{ closures: [ { id?, label?, from, to, yearly? } ], specialHours: [ { id?, label?, date, yearly?, ranges } ] }`.
 * Either list may be left out to keep its stored value. Missing ids are generated and returned.
 */
class Closures extends SettingsController {

	/**
	 * Route.
	 *
	 * @return string
	 */
	protected function route() {
		return '/settings/closures';
	}

	/**
	 * Option name.
	 *
	 * @return string
	 */
	protected function option() {
		return ClosuresSettings::OPTION;
	}

	/**
	 * Group name for whole-request errors.
	 *
	 * @return string
	 */
	protected function group() {
		return 'closures';
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	protected function defaults() {
		return ClosuresSettings::get_default_settings();
	}

	/**
	 * Stored group.
	 *
	 * @return array
	 */
	protected function stored() {
		return StoreSchedule::get_closures_settings();
	}

	/**
	 * Validate with weekly hours at their defaults, so bad stored hours can't block saving closures.
	 *
	 * @param array $group Group.
	 * @return ValidSchedule|\HourGate\Core\Schedule\ValidationError[]
	 */
	protected function build( array $group ) {
		return ScheduleFactory::from_arrays( ScheduleSettings::get_default_settings(), $group );
	}

	/**
	 * Normalized group.
	 *
	 * @param ValidSchedule $schedule Schedule.
	 * @return array
	 */
	protected function normalized( ValidSchedule $schedule ) {
		return $schedule->to_closures_settings();
	}
}
