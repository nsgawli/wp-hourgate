<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts between UTC timestamps and local wall-clock time in one time zone,
 * with the engine's daylight-saving rules (09 §16.3):
 * - a wall time that doesn't exist (spring forward) resolves to the
 *   transition instant, the first valid instant after the gap (02:30 → 03:00
 *   in `America/New_York`; PHP's own `DateTime` gives 03:30);
 * - an ambiguous wall time (fall back) resolves to its first occurrence.
 *
 * Offsets come from `DateTimeZone::getTransitions()`, loaded once for a span
 * of years and searched in memory. Offset zones (`+05:30`) have no transitions
 * and use their fixed offset. Never reads the server's time zone.
 *
 * PHP before 8.1 has no transitions after 2037 (its zone data stops there,
 * `getOffset()` included), so on PHP 7.4 dates past 2037 have no DST here
 * either: the clock always agrees with PHP's own `DateTime` (OW-050).
 */
final class LocalClock {

	/**
	 * Seconds in a wall-clock day.
	 *
	 * @var int
	 */
	const DAY = 86400;

	/**
	 * Years of transitions loaded before and after the first instant asked for.
	 *
	 * @var int
	 */
	const SPAN_YEARS = 3;

	/**
	 * The zone.
	 *
	 * @var \DateTimeZone
	 */
	private $timezone;

	/**
	 * Offset in seconds when the zone has no transitions; null otherwise.
	 *
	 * @var int|null
	 */
	private $fixed_offset;

	/**
	 * Transition instants (UTC), ascending. Entry 0 is the start of the loaded span.
	 *
	 * @var int[]
	 */
	private $times = array();

	/**
	 * Offset in seconds from each instant in `$times`.
	 *
	 * @var int[]
	 */
	private $offsets = array();

	/**
	 * Loaded span, inclusive.
	 *
	 * @var int
	 */
	private $loaded_from = 1;

	/**
	 * Loaded span, inclusive.
	 *
	 * @var int
	 */
	private $loaded_to = 0;

	/**
	 * Create a clock for a zone.
	 *
	 * @param \DateTimeZone $timezone Zone, named (`Europe/London`) or offset (`+05:30`).
	 */
	public function __construct( \DateTimeZone $timezone ) {

		$this->timezone = $timezone;

		// Offset zones (`+05:30`) and abbreviations without rules return false.
		if ( false === $timezone->getTransitions( 0, 0 ) ) {
			$this->fixed_offset = $timezone->getOffset( new \DateTimeImmutable( '@0' ) );
		}
	}

	/**
	 * UTC offset in seconds at an instant.
	 *
	 * @param int $ts UTC timestamp.
	 * @return int
	 */
	public function offset_at( int $ts ) {

		if ( null !== $this->fixed_offset ) {
			return $this->fixed_offset;
		}

		if ( $ts < $this->loaded_from || $ts > $this->loaded_to ) {
			$this->load( $ts );
		}

		// Last transition at or before $ts.
		$low  = 0;
		$high = count( $this->times ) - 1;

		while ( $low < $high ) {
			$mid = intdiv( $low + $high + 1, 2 );

			if ( $this->times[ $mid ] <= $ts ) {
				$low = $mid;
			} else {
				$high = $mid - 1;
			}
		}

		return $this->offsets[ $low ];
	}

	/**
	 * Local day number (days since 1970-01-01) of an instant.
	 *
	 * @param int $ts UTC timestamp.
	 * @return int
	 */
	public function local_day( int $ts ) {
		return (int) floor( ( $ts + $this->offset_at( $ts ) ) / self::DAY );
	}

	/**
	 * UTC timestamp of a local wall time, by the rules above.
	 *
	 * @param int $day     Local day number.
	 * @param int $minutes Minutes after that day's midnight; may exceed 1440 (the next day).
	 * @return int
	 */
	public function to_timestamp( int $day, int $minutes ) {

		$wall = $day * self::DAY + $minutes * 60;

		if ( null !== $this->fixed_offset ) {
			return $wall - $this->fixed_offset;
		}

		// The offsets in force a day either side cover any transition near this wall time.
		$before = $this->offset_at( $wall - self::DAY );
		$after  = $this->offset_at( $wall + self::DAY );

		// The instant this wall time would be under each offset, and whether that offset really applies then.
		$with_before  = $wall - $before;
		$with_after   = $wall - $after;
		$before_holds = $this->offset_at( $with_before ) === $before;
		$after_holds  = $this->offset_at( $with_after ) === $after;

		if ( $before_holds && $after_holds ) {
			// Ambiguous (or no transition nearby): the first occurrence.
			return min( $with_before, $with_after );
		}

		if ( $before_holds ) {
			return $with_before;
		}

		if ( $after_holds ) {
			return $with_after;
		}

		// In a gap: the transition between the two candidates.
		return $this->transition_between( min( $with_before, $with_after ), max( $with_before, $with_after ) );
	}

	/**
	 * The first transition instant in `( $from, $to ]`.
	 *
	 * @param int $from Exclusive.
	 * @param int $to   Inclusive.
	 * @return int
	 */
	private function transition_between( int $from, int $to ) {

		$this->offset_at( $from );
		$this->offset_at( $to );

		foreach ( $this->times as $index => $time ) {
			if ( $index > 0 && $time > $from && $time <= $to ) {
				return $time;
			}
		}

		// Unreachable for real zone data; the later candidate is the safe answer.
		return $to;
	}

	/**
	 * Load transitions for a span of years around an instant.
	 *
	 * @param int $ts UTC timestamp.
	 * @return void
	 */
	private function load( int $ts ) {

		$span = self::SPAN_YEARS * 366 * self::DAY;
		$from = min( $ts - $span, $this->loaded_from <= $this->loaded_to ? $this->loaded_from : PHP_INT_MAX );
		$to   = max( $ts + $span, $this->loaded_to );

		$transitions = $this->timezone->getTransitions( $from, $to );

		$this->times   = array();
		$this->offsets = array();

		foreach ( (array) $transitions as $transition ) {
			$this->times[]   = (int) $transition['ts'];
			$this->offsets[] = (int) $transition['offset'];
		}

		if ( ! $this->times ) {
			$this->times   = array( $from );
			$this->offsets = array( $this->timezone->getOffset( new \DateTimeImmutable( '@' . $ts ) ) );
		}

		$this->loaded_from = $from;
		$this->loaded_to   = $to;
	}
}
