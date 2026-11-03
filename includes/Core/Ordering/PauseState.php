<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Ordering;

use HourGate\Core\Schedule\Calendar;
use HourGate\Core\Schedule\Engine;
use HourGate\Core\Schedule\LocalClock;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A pause of ordering: immutable, pure PHP (no WordPress calls), so PHPUnit
 * tests it without WordPress. `Core\Pause` stores it in an option.
 *
 * Auto-resume is lazy: `is_active_at( $ts )` compares `until` with the
 * instant asked about, so an expired pause reads as not paused without any
 * write or cron event.
 *
 * Stored shape: `{ active: bool, until: int|null, mode: string, startedAt: int, startedBy: int }`.
 */
final class PauseState {

	/**
	 * Until someone resumes (`until` null).
	 *
	 * @var string
	 */
	const MODE_UNTIL_RESUMED = 'until_resumed';

	/**
	 * For a number of minutes.
	 *
	 * @var string
	 */
	const MODE_DURATION = 'duration';

	/**
	 * Until the store's next local midnight ("rest of today").
	 *
	 * @var string
	 */
	const MODE_END_OF_DAY = 'end_of_day';

	/**
	 * Until a store-local date and time.
	 *
	 * @var string
	 */
	const MODE_UNTIL_TIME = 'until_time';

	/**
	 * Until the schedule's next opening after the current window.
	 *
	 * @var string
	 */
	const MODE_NEXT_OPENING = 'next_opening';

	/**
	 * Every mode.
	 *
	 * @var string[]
	 */
	const MODES = array( self::MODE_UNTIL_RESUMED, self::MODE_DURATION, self::MODE_END_OF_DAY, self::MODE_UNTIL_TIME, self::MODE_NEXT_OPENING );

	/**
	 * Shortest duration, in minutes.
	 *
	 * @var int
	 */
	const MIN_MINUTES = 1;

	/**
	 * Longest duration, in minutes (7 days).
	 *
	 * @var int
	 */
	const MAX_MINUTES = 10080;

	/**
	 * Furthest `until_time`, in days from now.
	 *
	 * @var int
	 */
	const MAX_UNTIL_DAYS = 400;

	/**
	 * Error: unknown mode.
	 *
	 * @var string
	 */
	const ERROR_INVALID_MODE = 'invalid_mode';

	/**
	 * Error: `minutes` missing for `duration`.
	 *
	 * @var string
	 */
	const ERROR_MISSING_MINUTES = 'missing_minutes';

	/**
	 * Error: `minutes` isn't an integer from 1 to 10080.
	 *
	 * @var string
	 */
	const ERROR_INVALID_MINUTES = 'invalid_minutes';

	/**
	 * Error: `until` missing for `until_time`.
	 *
	 * @var string
	 */
	const ERROR_MISSING_UNTIL = 'missing_until';

	/**
	 * Error: `until` isn't a valid local `Y-m-d H:i` (or `Y-m-dTH:i`).
	 *
	 * @var string
	 */
	const ERROR_INVALID_UNTIL = 'invalid_until';

	/**
	 * Error: `until` is now or in the past.
	 *
	 * @var string
	 */
	const ERROR_UNTIL_IN_PAST = 'until_in_past';

	/**
	 * Error: `until` is more than 400 days ahead.
	 *
	 * @var string
	 */
	const ERROR_UNTIL_TOO_FAR = 'until_too_far';

	/**
	 * Error: `next_opening` asked for, but nothing opens within the horizon.
	 *
	 * @var string
	 */
	const ERROR_NO_NEXT_OPENING = 'no_next_opening';

	/**
	 * Whether a pause was started.
	 *
	 * @var bool
	 */
	private $active;

	/**
	 * When it ends (UTC, exclusive), or null for until resumed.
	 *
	 * @var int|null
	 */
	private $until;

	/**
	 * How it was started.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * When it was started (UTC).
	 *
	 * @var int
	 */
	private $started_at;

	/**
	 * Who started it (user ID, 0 when unknown).
	 *
	 * @var int
	 */
	private $started_by;

	/**
	 * Use the named constructors.
	 *
	 * @param bool     $active     Active.
	 * @param int|null $until      End.
	 * @param string   $mode       Mode.
	 * @param int      $started_at Start.
	 * @param int      $started_by User ID.
	 */
	private function __construct( bool $active, $until, string $mode, int $started_at, int $started_by ) {
		$this->active     = $active;
		$this->until      = $until;
		$this->mode       = $mode;
		$this->started_at = $started_at;
		$this->started_by = $started_by;
	}

	/**
	 * Not paused.
	 *
	 * @return self
	 */
	public static function none() {
		return new self( false, null, self::MODE_UNTIL_RESUMED, 0, 0 );
	}

	/**
	 * Read a stored state. Anything that isn't exactly the stored shape
	 * (missing keys, wrong types, an unknown mode, an `until` that doesn't
	 * match the mode) reads as not paused: a corrupt option never blocks orders.
	 *
	 * @param mixed $raw Stored value.
	 * @return self
	 */
	public static function from_array( $raw ) {

		if ( ! is_array( $raw ) || ! isset( $raw['active'], $raw['mode'], $raw['startedAt'], $raw['startedBy'] ) || ! array_key_exists( 'until', $raw ) ) {
			return self::none();
		}

		$active     = $raw['active'];
		$until      = $raw['until'];
		$mode       = $raw['mode'];
		$started_at = $raw['startedAt'];
		$started_by = $raw['startedBy'];

		if ( true !== $active || ! in_array( $mode, self::MODES, true ) || ! is_int( $started_at ) || ! is_int( $started_by ) || $started_by < 0 ) {
			return self::none();
		}

		// Only "until resumed" is open-ended; every other mode has an integer end.
		if ( self::MODE_UNTIL_RESUMED === $mode ? null !== $until : ! is_int( $until ) ) {
			return self::none();
		}

		return new self( true, $until, $mode, $started_at, $started_by );
	}

	/**
	 * Start a pause, validated.
	 *
	 * @param string     $mode     One of `MODES`.
	 * @param array      $input    `minutes` (int) for `duration`; `until` (local `Y-m-d H:i` or `Y-m-dTH:i`) for `until_time`.
	 * @param int        $now      UTC timestamp.
	 * @param int        $user_id  Who starts it.
	 * @param Engine     $engine   The store's schedule (for `next_opening`).
	 * @param LocalClock $clock    The store's clock (for `end_of_day`, `until_time`).
	 * @return self|string The state, or an `ERROR_*` code.
	 */
	public static function start( $mode, array $input, int $now, int $user_id, Engine $engine, LocalClock $clock ) {

		if ( ! is_string( $mode ) || ! in_array( $mode, self::MODES, true ) ) {
			return self::ERROR_INVALID_MODE;
		}

		$user_id = max( 0, $user_id );

		switch ( $mode ) {
			case self::MODE_DURATION:
				if ( ! array_key_exists( 'minutes', $input ) || null === $input['minutes'] ) {
					return self::ERROR_MISSING_MINUTES;
				}

				$minutes = $input['minutes'];

				if ( ! is_int( $minutes ) || $minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES ) {
					return self::ERROR_INVALID_MINUTES;
				}

				$until = $now + $minutes * 60;
				break;

			case self::MODE_END_OF_DAY:
				$until = $clock->to_timestamp( $clock->local_day( $now ) + 1, 0 );
				break;

			case self::MODE_UNTIL_TIME:
				if ( ! array_key_exists( 'until', $input ) || null === $input['until'] || '' === $input['until'] ) {
					return self::ERROR_MISSING_UNTIL;
				}

				$until = self::parse_local( $input['until'], $clock );

				if ( null === $until ) {
					return self::ERROR_INVALID_UNTIL;
				}

				if ( $until <= $now ) {
					return self::ERROR_UNTIL_IN_PAST;
				}

				if ( $until > $now + self::MAX_UNTIL_DAYS * LocalClock::DAY ) {
					return self::ERROR_UNTIL_TOO_FAR;
				}
				break;

			case self::MODE_NEXT_OPENING:
				$until = self::next_opening( $engine, $now );

				if ( null === $until ) {
					return self::ERROR_NO_NEXT_OPENING;
				}
				break;

			default:
				$until = null;
		}

		return new self( true, $until, $mode, $now, $user_id );
	}

	/**
	 * The schedule's first opening after the current window: while closed,
	 * the next opening; while open, the opening after the next close. Null
	 * when there is none within the horizon (including "open past the horizon").
	 *
	 * @param Engine $engine Engine.
	 * @param int    $now    UTC timestamp.
	 * @return int|null
	 */
	public static function next_opening( Engine $engine, int $now ) {

		if ( ! $engine->is_open( $now ) ) {
			return $engine->next_open( $now );
		}

		$close = $engine->next_close( $now );

		return null === $close ? null : $engine->next_open( $close );
	}

	/**
	 * Whether ordering is paused at an instant (`until` is exclusive).
	 *
	 * @param int $ts UTC timestamp.
	 * @return bool
	 */
	public function is_active_at( int $ts ) {
		return $this->active && ( null === $this->until || $ts < $this->until );
	}

	/**
	 * Whether a pause was started, expired or not (a stored row exists).
	 *
	 * @return bool
	 */
	public function is_started() {
		return $this->active;
	}

	/**
	 * When it ends (UTC, exclusive); null for until resumed or not paused.
	 *
	 * @return int|null
	 */
	public function get_until() {
		return $this->until;
	}

	/**
	 * The mode it was started with.
	 *
	 * @return string
	 */
	public function get_mode() {
		return $this->mode;
	}

	/**
	 * When it was started (UTC); 0 when not paused.
	 *
	 * @return int
	 */
	public function get_started_at() {
		return $this->started_at;
	}

	/**
	 * Who started it; 0 when not paused or unknown.
	 *
	 * @return int
	 */
	public function get_started_by() {
		return $this->started_by;
	}

	/**
	 * The stored shape.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'active'    => $this->active,
			'until'     => $this->until,
			'mode'      => $this->mode,
			'startedAt' => $this->started_at,
			'startedBy' => $this->started_by,
		);
	}

	/**
	 * A store-local `Y-m-d H:i` / `Y-m-dTH:i` to a UTC timestamp, by the
	 * engine's DST rules (a time in a gap resolves to the transition, an
	 * ambiguous one to its first occurrence). Null when malformed.
	 *
	 * @param mixed      $value Value.
	 * @param LocalClock $clock Clock.
	 * @return int|null
	 */
	private static function parse_local( $value, LocalClock $clock ) {

		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4}-\d{2}-\d{2})[T ]([01]\d|2[0-3]):([0-5]\d)$/D', $value, $m ) || ! Calendar::is_valid_date( $m[1] ) ) {
			return null;
		}

		return $clock->to_timestamp( Calendar::to_day( $m[1] ), (int) $m[2] * 60 + (int) $m[3] );
	}
}
