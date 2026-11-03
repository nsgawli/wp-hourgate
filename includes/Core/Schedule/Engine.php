<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers "is the store open?" from a `Schedule` in one time zone.
 * Timestamps are UTC Unix seconds. Pure PHP: no WordPress, no server time zone,
 * no I/O. Day plans are computed lazily and memoized for the engine's life.
 *
 * Rules (09 §16.3, study 10 D5–D6):
 * - **Precedence for a date:** special hours beat a closure, which beats weekly hours.
 * - **Overnight:** a range belongs to the date it starts on and runs past
 *   midnight into the next date, whatever that date's rule, unless the next
 *   date's winning rule is a closure: then it ends at midnight (D5).
 * - **Yearly** entries match by month and day; a yearly Feb 29 exists only in leap years (D6).
 * - **DST:** see `LocalClock` (gap → transition instant, ambiguous → first occurrence).
 * - **Horizon:** `next_open()` and `next_close()` look at most `horizon_days`
 *   local dates ahead, then return null, so a schedule that never opens (or
 *   never closes) can't loop.
 */
final class Engine {

	/**
	 * Default look-ahead for `next_open()` / `next_close()`, in days.
	 *
	 * @var int
	 */
	const DEFAULT_HORIZON_DAYS = 400;

	/**
	 * Largest horizon accepted (about ten years).
	 *
	 * @var int
	 */
	const MAX_HORIZON_DAYS = 3660;

	/**
	 * Memoized days kept before the memo is reset (long-running processes).
	 *
	 * @var int
	 */
	const MAX_CACHED_DAYS = 1000;

	/**
	 * Local-time conversions.
	 *
	 * @var LocalClock
	 */
	private $clock;

	/**
	 * Weekly hours.
	 *
	 * @var WeeklyHours
	 */
	private $weekly;

	/**
	 * Look-ahead in days.
	 *
	 * @var int
	 */
	private $horizon_days;

	/**
	 * One-off special hours by `Y-m-d`.
	 *
	 * @var array<string, SpecialHours>
	 */
	private $special_by_date = array();

	/**
	 * Yearly special hours by `m-d`.
	 *
	 * @var array<string, SpecialHours>
	 */
	private $special_by_month_day = array();

	/**
	 * Closures as plain arrays for a fast per-date scan.
	 *
	 * @var array[]
	 */
	private $closures = array();

	/**
	 * Whether the weekly hours have no ranges at all.
	 *
	 * @var bool
	 */
	private $weekly_empty;

	/**
	 * Whether the weekly hours cover every minute of every weekday (counting
	 * the previous night's past-midnight range), as with the 24/7 default.
	 *
	 * @var bool
	 */
	private $weekly_always_open;

	/**
	 * Whether any closure or special-hours entry repeats yearly.
	 *
	 * @var bool
	 */
	private $has_yearly = false;

	/**
	 * Last day number touched by a one-off closure or special-hours entry; PHP_INT_MIN when none.
	 *
	 * @var int
	 */
	private $last_one_off_day = PHP_INT_MIN;

	/**
	 * Day number => array( rule, rule id, label, ranges ).
	 *
	 * @var array<int, array>
	 */
	private $rules = array();

	/**
	 * Day number => that date's Window[].
	 *
	 * @var array<int, Window[]>
	 */
	private $windows = array();

	/**
	 * Create an engine.
	 *
	 * @param Schedule      $schedule     The schedule.
	 * @param \DateTimeZone $timezone     The store's zone (`wp_timezone()` in the adapter).
	 * @param int           $horizon_days Look-ahead for `next_open()` / `next_close()`, 1–`MAX_HORIZON_DAYS`.
	 * @throws \InvalidArgumentException For a horizon out of range.
	 */
	public function __construct( Schedule $schedule, \DateTimeZone $timezone, int $horizon_days = self::DEFAULT_HORIZON_DAYS ) {

		if ( $horizon_days < 1 || $horizon_days > self::MAX_HORIZON_DAYS ) {
			throw new \InvalidArgumentException( 'Horizon out of range.' );
		}

		$this->clock        = new LocalClock( $timezone );
		$this->weekly       = $schedule->get_weekly();
		$this->horizon_days = $horizon_days;

		$this->weekly_empty       = $this->weekly->is_empty();
		$this->weekly_always_open = $this->covers_every_minute( $this->weekly );

		// The factory allows one entry per date; built directly, the first one wins.
		foreach ( $schedule->get_special_hours() as $entry ) {
			$this->note_exception( $entry->is_yearly(), $entry->get_date() );

			if ( $entry->is_yearly() ) {
				$md                                = Calendar::month_day( $entry->get_date() );
				$this->special_by_month_day[ $md ] = $this->special_by_month_day[ $md ] ?? $entry;
			} else {
				$this->special_by_date[ $entry->get_date() ] = $this->special_by_date[ $entry->get_date() ] ?? $entry;
			}
		}

		foreach ( $schedule->get_closures() as $closure ) {
			$this->note_exception( $closure->is_yearly(), $closure->get_to() );

			$this->closures[] = array(
				'id'      => $closure->get_id(),
				'label'   => $closure->get_label(),
				'from'    => $closure->get_from(),
				'to'      => $closure->get_to(),
				'yearly'  => $closure->is_yearly(),
				'md_from' => Calendar::month_day( $closure->get_from() ),
				'md_to'   => Calendar::month_day( $closure->get_to() ),
				'wraps'   => Calendar::year( $closure->get_to() ) > Calendar::year( $closure->get_from() ),
			);
		}
	}

	/**
	 * Look-ahead in days.
	 *
	 * @return int
	 */
	public function get_horizon_days() {
		return $this->horizon_days;
	}

	/**
	 * The open parts of `[from, to)`: windows merged where they touch or
	 * overlap, then clipped to `[from, to)`. Sorted by start.
	 *
	 * @param int $from UTC timestamp, inclusive.
	 * @param int $to   UTC timestamp, exclusive.
	 * @return Window[] Empty when `$to <= $from`.
	 * @throws \InvalidArgumentException When the span is longer than the horizon.
	 */
	public function windows( int $from, int $to ) {

		if ( $to <= $from ) {
			return array();
		}

		// The day before contributes its past-midnight range.
		$first = $this->clock->local_day( $from ) - 1;
		$last  = $this->clock->local_day( $to - 1 );

		if ( $last - $first > $this->horizon_days + 2 ) {
			throw new \InvalidArgumentException( 'The span is longer than the horizon.' );
		}

		// A date's windows all start before the next date's, so this list is sorted by start.
		$merged = array();

		for ( $day = $first; $day <= $last; $day++ ) {
			foreach ( $this->windows_for_day( $day ) as $window ) {
				$end = count( $merged ) - 1;

				if ( $end >= 0 && $window->get_start() <= $merged[ $end ][1] ) {
					$merged[ $end ][1] = max( $merged[ $end ][1], $window->get_end() );
				} else {
					$merged[] = array( $window->get_start(), $window->get_end() );
				}
			}
		}

		$out = array();

		foreach ( $merged as list( $start, $end ) ) {
			$start = max( $start, $from );
			$end   = min( $end, $to );

			if ( $end > $start ) {
				$out[] = new Window( $start, $end );
			}
		}

		return $out;
	}

	/**
	 * Whether the store is open at an instant (`start <= ts < end`).
	 * Looks only at the windows of the instant's local date and the day before.
	 *
	 * @param int $ts UTC timestamp.
	 * @return bool
	 */
	public function is_open( int $ts ) {
		return null !== $this->containing_end( $ts );
	}

	/**
	 * When the store next opens: `$ts` itself when open, otherwise the next
	 * window start, or null when nothing opens within the horizon.
	 *
	 * @param int $ts UTC timestamp.
	 * @return int|null
	 */
	public function next_open( int $ts ) {

		if ( $this->is_open( $ts ) ) {
			return $ts;
		}

		$day = $this->clock->local_day( $ts );

		for ( $d = $day; $d <= $day + $this->horizon_days; $d++ ) {
			foreach ( $this->windows_for_day( $d ) as $window ) {
				if ( $window->get_start() > $ts ) {
					return $window->get_start();
				}
			}

			if ( $this->weekly_empty && $this->only_weekly_after( $d ) ) {
				// No weekly hours, and no exception left to open on: it never opens.
				return null;
			}
		}

		return null;
	}

	/**
	 * When the store next closes: the end of the merged window containing
	 * `$ts` (following it across midnights), or null when closed at `$ts`, or
	 * when it stays open past the horizon. Use `is_open()` to tell those apart.
	 *
	 * @param int $ts UTC timestamp.
	 * @return int|null
	 */
	public function next_close( int $ts ) {

		$end = $this->containing_end( $ts );

		if ( null === $end ) {
			return null;
		}

		$day = $this->clock->local_day( $ts );

		for ( $d = $day - 1; $d <= $day + $this->horizon_days; $d++ ) {
			foreach ( $this->windows_for_day( $d ) as $window ) {
				if ( $window->get_start() <= $end && $window->get_end() > $end ) {
					$end = $window->get_end();
				}
			}

			// Later dates' windows start at or after the next midnight.
			if ( $this->clock->to_timestamp( $d + 1, 0 ) > $end ) {
				return $end;
			}

			if ( $this->weekly_always_open && $this->only_weekly_after( $d ) ) {
				// Open through date $d, and weekly hours that cover every minute from here on: it never closes.
				return null;
			}
		}

		return null;
	}

	/**
	 * Whether every date after `$d` is ruled by weekly hours: no yearly
	 * entries, and no one-off entry on `$d` or later. Then the weekly pattern
	 * decides everything after `$d`, so a scan can stop early.
	 *
	 * @param int $d Day number just scanned.
	 * @return bool
	 */
	private function only_weekly_after( int $d ) {
		return ! $this->has_yearly && $this->last_one_off_day < $d;
	}

	/**
	 * Whether weekly hours leave no minute of any weekday closed, in wall
	 * time. Checked on the hours themselves, not on one week's windows: a DST
	 * gap can hide a short closed gap in the week it happens.
	 *
	 * @param WeeklyHours $weekly Weekly hours.
	 * @return bool
	 */
	private function covers_every_minute( WeeklyHours $weekly ) {

		for ( $iso = 1; $iso <= 7; $iso++ ) {
			$intervals = array();

			foreach ( $weekly->get_ranges_for_iso_day( 1 === $iso ? 7 : $iso - 1 ) as $range ) {
				if ( $range->is_overnight() ) {
					$intervals[] = array( 0, $range->get_end_offset() - TimeRange::MINUTES_PER_DAY );
				}
			}

			foreach ( $weekly->get_ranges_for_iso_day( $iso ) as $range ) {
				$intervals[] = array( $range->get_start(), min( $range->get_end_offset(), TimeRange::MINUTES_PER_DAY ) );
			}

			sort( $intervals );

			$covered = 0;

			foreach ( $intervals as list( $start, $end ) ) {
				if ( $start > $covered ) {
					return false;
				}

				$covered = max( $covered, $end );
			}

			if ( $covered < TimeRange::MINUTES_PER_DAY ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Track exceptions for `only_weekly_after()`.
	 *
	 * @param bool   $yearly  Whether it repeats yearly.
	 * @param string $last    Its last date, `Y-m-d`.
	 * @return void
	 */
	private function note_exception( bool $yearly, string $last ) {

		if ( $yearly ) {
			$this->has_yearly = true;
			return;
		}

		$this->last_one_off_day = max( $this->last_one_off_day, Calendar::to_day( $last ) );
	}

	/**
	 * A local date's plan: winning rule, its label, ranges and UTC windows.
	 *
	 * @param string $ymd Local date, `Y-m-d`.
	 * @return DayPlan
	 * @throws \InvalidArgumentException For an invalid date.
	 */
	public function day_plan( string $ymd ) {

		if ( ! Calendar::is_valid_date( $ymd ) ) {
			throw new \InvalidArgumentException( 'Invalid date.' );
		}

		$day  = Calendar::to_day( $ymd );
		$rule = $this->rule_for_day( $day );

		return new DayPlan( $ymd, $rule[0], $rule[1], $rule[2], $rule[3], $this->windows_for_day( $day ) );
	}

	/**
	 * The latest end among windows containing `$ts` (from its local date or
	 * the day before), or null when none does.
	 *
	 * @param int $ts UTC timestamp.
	 * @return int|null
	 */
	private function containing_end( int $ts ) {

		$day = $this->clock->local_day( $ts );
		$end = null;

		foreach ( array( $day - 1, $day ) as $d ) {
			foreach ( $this->windows_for_day( $d ) as $window ) {
				if ( $window->contains( $ts ) && ( null === $end || $window->get_end() > $end ) ) {
					$end = $window->get_end();
				}
			}
		}

		return $end;
	}

	/**
	 * Which rule wins a local date, by precedence: special hours, closure, weekly.
	 *
	 * @param int $day Local day number.
	 * @return array array( rule, rule id or null, label, TimeRange[] ).
	 */
	private function rule_for_day( int $day ) {

		if ( isset( $this->rules[ $day ] ) ) {
			return $this->rules[ $day ];
		}

		if ( count( $this->rules ) >= self::MAX_CACHED_DAYS ) {
			$this->rules   = array();
			$this->windows = array();
		}

		$ymd     = Calendar::from_day( $day );
		$md      = Calendar::month_day( $ymd );
		$special = $this->special_by_date[ $ymd ] ?? $this->special_by_month_day[ $md ] ?? null;

		if ( null !== $special ) {
			$this->rules[ $day ] = array( DayPlan::RULE_SPECIAL, $special->get_id(), $special->get_label(), $special->get_ranges() );
			return $this->rules[ $day ];
		}

		foreach ( $this->closures as $closure ) {
			if ( $closure['yearly'] ) {
				$covers = $closure['wraps']
					? ( $md >= $closure['md_from'] || $md <= $closure['md_to'] )
					: ( $md >= $closure['md_from'] && $md <= $closure['md_to'] );
			} else {
				$covers = $ymd >= $closure['from'] && $ymd <= $closure['to'];
			}

			if ( $covers ) {
				$this->rules[ $day ] = array( DayPlan::RULE_CLOSURE, $closure['id'], $closure['label'], array() );
				return $this->rules[ $day ];
			}
		}

		$this->rules[ $day ] = array( DayPlan::RULE_WEEKLY, null, '', $this->weekly->get_ranges_for_iso_day( Calendar::iso_weekday( $day ) ) );

		return $this->rules[ $day ];
	}

	/**
	 * UTC windows of a local date's own ranges, sorted by start. A range past
	 * midnight ends on the next date, or at midnight when the next date is a
	 * closure (D5). A range that DST leaves empty (inside a spring-forward gap)
	 * is dropped.
	 *
	 * @param int $day Local day number.
	 * @return Window[]
	 */
	private function windows_for_day( int $day ) {

		if ( isset( $this->windows[ $day ] ) ) {
			return $this->windows[ $day ];
		}

		$rule        = $this->rule_for_day( $day );
		$windows     = array();
		$next_closed = null;

		foreach ( $rule[3] as $range ) {
			$end_offset = $range->get_end_offset();

			if ( $end_offset > TimeRange::MINUTES_PER_DAY ) {
				if ( null === $next_closed ) {
					$next_closed = DayPlan::RULE_CLOSURE === $this->rule_for_day( $day + 1 )[0];
				}

				if ( $next_closed ) {
					$end_offset = TimeRange::MINUTES_PER_DAY;
				}
			}

			$start = $this->clock->to_timestamp( $day, $range->get_start() );
			$end   = $this->clock->to_timestamp( $day, $end_offset );

			if ( $end > $start ) {
				$windows[] = new Window( $start, $end );
			}
		}

		// rule_for_day( $day + 1 ) may have reset the memo; store after it.
		$this->windows[ $day ] = $windows;

		return $windows;
	}
}
