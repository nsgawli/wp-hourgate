<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A validated schedule: weekly hours, closures and special hours. Immutable.
 * Build it with `ScheduleFactory::from_arrays()`.
 */
final class Schedule {

	/**
	 * Weekly hours.
	 *
	 * @var WeeklyHours
	 */
	private $weekly;

	/**
	 * Closures.
	 *
	 * @var ClosedPeriod[]
	 */
	private $closures;

	/**
	 * Special hours.
	 *
	 * @var SpecialHours[]
	 */
	private $special_hours;

	/**
	 * Create a schedule.
	 *
	 * @param WeeklyHours    $weekly        Weekly hours.
	 * @param ClosedPeriod[] $closures      Closures.
	 * @param SpecialHours[] $special_hours Special hours.
	 * @throws \InvalidArgumentException When a list holds the wrong type.
	 */
	public function __construct( WeeklyHours $weekly, array $closures = array(), array $special_hours = array() ) {

		foreach ( $closures as $closure ) {
			if ( ! $closure instanceof ClosedPeriod ) {
				throw new \InvalidArgumentException( 'Closures must be ClosedPeriod objects.' );
			}
		}

		foreach ( $special_hours as $entry ) {
			if ( ! $entry instanceof SpecialHours ) {
				throw new \InvalidArgumentException( 'Special hours must be SpecialHours objects.' );
			}
		}

		$this->weekly        = $weekly;
		$this->closures      = array_values( $closures );
		$this->special_hours = array_values( $special_hours );
	}

	/**
	 * Weekly hours.
	 *
	 * @return WeeklyHours
	 */
	public function get_weekly() {
		return $this->weekly;
	}

	/**
	 * Closures, in stored order.
	 *
	 * @return ClosedPeriod[]
	 */
	public function get_closures() {
		return $this->closures;
	}

	/**
	 * Special hours, in stored order.
	 *
	 * @return SpecialHours[]
	 */
	public function get_special_hours() {
		return $this->special_hours;
	}

	/**
	 * The `hourgate_schedule_settings` group: `array( 'weekly' => … )`.
	 *
	 * @return array
	 */
	public function to_schedule_settings() {
		return array(
			'weekly' => $this->weekly->to_array(),
		);
	}

	/**
	 * The `hourgate_closures_settings` group: `array( 'closures' => …, 'specialHours' => … )`.
	 * Generated ids are included, so saving this keeps them stable.
	 *
	 * @return array
	 */
	public function to_closures_settings() {
		return array(
			'closures'     => array_map(
				function ( ClosedPeriod $closure ) {
					return $closure->to_array();
				},
				$this->closures
			),
			'specialHours' => array_map(
				function ( SpecialHours $entry ) {
					return $entry->to_array();
				},
				$this->special_hours
			),
		);
	}
}
