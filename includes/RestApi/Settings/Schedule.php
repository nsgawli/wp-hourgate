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
 * Weekly hours: `GET|PUT /settings/schedule`, `GET /settings/schedule/defaults`.
 *
 * Body: `{ weekly: { mon: [ { start, end } ], …, sun: [] } }`, all seven days.
 */
class Schedule extends SettingsController {

	/**
	 * Route.
	 *
	 * @return string
	 */
	protected function route() {
		return '/settings/schedule';
	}

	/**
	 * Option name.
	 *
	 * @return string
	 */
	protected function option() {
		return ScheduleSettings::OPTION;
	}

	/**
	 * Group name for whole-request errors.
	 *
	 * @return string
	 */
	protected function group() {
		return 'schedule';
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	protected function defaults() {
		return ScheduleSettings::get_default_settings();
	}

	/**
	 * Stored group.
	 *
	 * @return array
	 */
	protected function stored() {
		return StoreSchedule::get_schedule_settings();
	}

	/**
	 * Validate with the closures group at its defaults, so a bad stored closure can't block saving hours.
	 *
	 * @param array $group Group.
	 * @return ValidSchedule|\HourGate\Core\Schedule\ValidationError[]
	 */
	protected function build( array $group ) {
		return ScheduleFactory::from_arrays( $group, ClosuresSettings::get_default_settings() );
	}

	/**
	 * Normalized group.
	 *
	 * @param ValidSchedule $schedule Schedule.
	 * @return array
	 */
	protected function normalized( ValidSchedule $schedule ) {
		return $schedule->to_schedule_settings();
	}
}
