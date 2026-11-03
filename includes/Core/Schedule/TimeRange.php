<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An opening range in local wall-clock time, held as minutes from midnight.
 *
 * `start` is 0–1439. `end` is 0–1440 (1440 is `24:00`). When `end <= start`
 * the range runs past midnight into the next date (`22:00`–`02:00`). Immutable.
 */
final class TimeRange {

	/**
	 * Minutes in a wall-clock day.
	 *
	 * @var int
	 */
	const MINUTES_PER_DAY = 1440;

	/**
	 * Start, minutes from midnight.
	 *
	 * @var int
	 */
	private $start;

	/**
	 * End, minutes from midnight, as entered.
	 *
	 * @var int
	 */
	private $end;

	/**
	 * Create a range. `ScheduleFactory` validates input and reports errors; this
	 * only guards the invariants the engine relies on.
	 *
	 * @param int $start Start minute, 0–1439.
	 * @param int $end   End minute, 0–1440, not equal to `$start`.
	 * @throws \InvalidArgumentException When a value is out of range.
	 */
	public function __construct( int $start, int $end ) {

		if ( $start < 0 || $start >= self::MINUTES_PER_DAY || $end < 0 || $end > self::MINUTES_PER_DAY || $start === $end ) {
			throw new \InvalidArgumentException( 'Invalid time range.' );
		}

		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * Parse `HH:MM` (`00:00`–`23:59`, or `24:00`) into minutes from midnight.
	 *
	 * @param mixed $value Value to parse.
	 * @return int|null Minutes, or null when it isn't a valid time.
	 */
	public static function parse_time( $value ) {

		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( '24:00' === $value ) {
			return self::MINUTES_PER_DAY;
		}

		if ( ! preg_match( '/\A([01]\d|2[0-3]):([0-5]\d)\z/', $value, $m ) ) {
			return null;
		}

		return (int) $m[1] * 60 + (int) $m[2];
	}

	/**
	 * Format minutes from midnight as `HH:MM` (1440 as `24:00`).
	 *
	 * @param int $minutes Minutes, 0–1440.
	 * @return string
	 */
	public static function format_time( int $minutes ) {
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Start, minutes from midnight.
	 *
	 * @return int
	 */
	public function get_start() {
		return $this->start;
	}

	/**
	 * End, minutes from midnight, as entered (0–1440).
	 *
	 * @return int
	 */
	public function get_end() {
		return $this->end;
	}

	/**
	 * End, minutes from the start date's midnight: above 1440 when the range
	 * runs into the next date. `22:00`–`02:00` gives 1560, `22:00`–`00:00` gives 1440.
	 *
	 * @return int
	 */
	public function get_end_offset() {
		return $this->end <= $this->start ? $this->end + self::MINUTES_PER_DAY : $this->end;
	}

	/**
	 * Whether the range is still open after the start date's midnight.
	 *
	 * @return bool
	 */
	public function is_overnight() {
		return $this->get_end_offset() > self::MINUTES_PER_DAY;
	}

	/**
	 * The range as stored: `array( 'start' => 'HH:MM', 'end' => 'HH:MM' )`.
	 *
	 * @return array{start: string, end: string}
	 */
	public function to_array() {
		return array(
			'start' => self::format_time( $this->start ),
			'end'   => self::format_time( $this->end ),
		);
	}

	/**
	 * Sort ranges by start time.
	 *
	 * @param TimeRange[] $ranges Ranges.
	 * @return TimeRange[] A new list, sorted.
	 */
	public static function sort( array $ranges ) {

		usort(
			$ranges,
			function ( TimeRange $a, TimeRange $b ) {
				return $a->get_start() <=> $b->get_start();
			}
		);

		return $ranges;
	}
}
