<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opening ranges for each weekday. A day with no ranges is closed all day.
 * Immutable; each day's ranges are sorted by start.
 */
final class WeeklyHours {

	/**
	 * Weekday keys, Monday first (ISO 8601 order: index + 1 is the ISO weekday).
	 *
	 * @var string[]
	 */
	const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * Day key => sorted ranges.
	 *
	 * @var array<string, TimeRange[]>
	 */
	private $days = array();

	/**
	 * Create the weekly hours. `ScheduleFactory` validates overlaps; this only
	 * guards the shape.
	 *
	 * @param array<string, TimeRange[]> $days Every key of `DAYS`, each a list of ranges.
	 * @throws \InvalidArgumentException When a day is missing, unknown or not a list of ranges.
	 */
	public function __construct( array $days ) {

		if ( count( $days ) !== count( self::DAYS ) ) {
			throw new \InvalidArgumentException( 'Weekly hours need exactly the seven weekdays.' );
		}

		foreach ( self::DAYS as $day ) {
			if ( ! isset( $days[ $day ] ) || ! is_array( $days[ $day ] ) ) {
				throw new \InvalidArgumentException( 'Weekly hours need exactly the seven weekdays.' );
			}

			foreach ( $days[ $day ] as $range ) {
				if ( ! $range instanceof TimeRange ) {
					throw new \InvalidArgumentException( 'Weekly hours take TimeRange objects.' );
				}
			}

			$this->days[ $day ] = TimeRange::sort( $days[ $day ] );
		}
	}

	/**
	 * A day's ranges, sorted by start.
	 *
	 * @param string $day Day key from `DAYS`.
	 * @return TimeRange[]
	 * @throws \InvalidArgumentException For an unknown day.
	 */
	public function get_ranges( string $day ) {

		if ( ! isset( $this->days[ $day ] ) ) {
			throw new \InvalidArgumentException( 'Unknown weekday.' );
		}

		return $this->days[ $day ];
	}

	/**
	 * A day's ranges by ISO 8601 weekday number (1 = Monday … 7 = Sunday, as `DateTimeInterface::format( 'N' )`).
	 *
	 * @param int $iso_day 1–7.
	 * @return TimeRange[]
	 * @throws \InvalidArgumentException Outside 1–7.
	 */
	public function get_ranges_for_iso_day( int $iso_day ) {

		if ( $iso_day < 1 || $iso_day > 7 ) {
			throw new \InvalidArgumentException( 'ISO weekday must be 1-7.' );
		}

		return $this->days[ self::DAYS[ $iso_day - 1 ] ];
	}

	/**
	 * Whether every day is closed.
	 *
	 * @return bool
	 */
	public function is_empty() {

		foreach ( $this->days as $ranges ) {
			if ( $ranges ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The weekly hours as stored: day key => list of `{ start, end }`.
	 *
	 * @return array<string, array>
	 */
	public function to_array() {

		$out = array();

		foreach ( $this->days as $day => $ranges ) {
			$out[ $day ] = array_map(
				function ( TimeRange $range ) {
					return $range->to_array();
				},
				$ranges
			);
		}

		return $out;
	}
}
