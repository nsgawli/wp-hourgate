<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Core\Schedule\Engine;
use HourGate\Core\Schedule\Schedule;
use HourGate\Core\Schedule\ScheduleFactory;
use HourGate\Core\Schedule\ValidationError;
use HourGate\Core\Schedule\Window;
use HourGate\Installation\Settings\Closures as ClosuresSettings;
use HourGate\Installation\Settings\Schedule as ScheduleSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store's schedule in WordPress: reads the settings, builds the pure
 * `Schedule\Engine` with `wp_timezone()`, and exposes it as HourGate's
 * public PHP API.
 *
 * Nothing runs at boot. The engine is built on first use and memoized for the
 * request; saving the settings or the site's time zone resets it. A stored
 * schedule that fails validation never blocks the store: it falls back to
 * "always open" and is logged (WooCommerce logger, source `hourgate`).
 */
class StoreSchedule {

	/**
	 * Transient holding a hash of the last invalid settings logged, so the
	 * same problem is logged once a day rather than on every request.
	 *
	 * @var string
	 */
	const LOGGED_TRANSIENT = 'hourgate_invalid_schedule_logged';

	/**
	 * WooCommerce log source.
	 *
	 * @var string
	 */
	const LOG_SOURCE = 'hourgate';

	/**
	 * The memoized engine.
	 *
	 * @var Engine|null
	 */
	private static $engine = null;

	/**
	 * The schedule the memoized engine was built from.
	 *
	 * @var Schedule|null
	 */
	private static $schedule = null;

	/**
	 * Whether the memoized engine came from the always-open fallback.
	 *
	 * @var bool
	 */
	private static $is_fallback = false;

	/**
	 * Register the hooks that keep the memo fresh. Called from `Core\Autoloader`; no queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		foreach ( array( ScheduleSettings::OPTION, ClosuresSettings::OPTION, 'timezone_string', 'gmt_offset' ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
			add_action( 'delete_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
		}
	}

	/**
	 * Forget the memoized engine, e.g. after the settings change.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$engine      = null;
		self::$schedule    = null;
		self::$is_fallback = false;
	}

	/**
	 * The schedule group, merged with its defaults and type-checked at the top
	 * level: a missing or non-array option, or a `weekly` that isn't an array,
	 * gives the default. Deeper problems are left to `ScheduleFactory`.
	 *
	 * @return array
	 */
	public static function get_schedule_settings() {
		return self::read( ScheduleSettings::OPTION, ScheduleSettings::get_default_settings() );
	}

	/**
	 * The closures group, merged with its defaults and type-checked at the top
	 * level (each list must be an array, else it's empty).
	 *
	 * @return array
	 */
	public static function get_closures_settings() {
		return self::read( ClosuresSettings::OPTION, ClosuresSettings::get_default_settings() );
	}

	/**
	 * The engine for the store's schedule and time zone, memoized.
	 *
	 * @return Engine
	 */
	public static function engine() {

		if ( null !== self::$engine ) {
			return self::$engine;
		}

		$timezone = wp_timezone();
		$horizon  = self::get_horizon_days();

		try {
			$schedule = ScheduleFactory::from_arrays( self::get_schedule_settings(), self::get_closures_settings() );

			if ( $schedule instanceof Schedule ) {
				self::$engine   = new Engine( $schedule, $timezone, $horizon );
				self::$schedule = $schedule;
				return self::$engine;
			}

			self::log_invalid( $schedule );
		} catch ( \Throwable $e ) {
			self::log_invalid( array(), $e );
		}

		self::$schedule    = self::always_open();
		self::$engine      = new Engine( self::$schedule, $timezone, $horizon );
		self::$is_fallback = true;

		return self::$engine;
	}

	/**
	 * The schedule `engine()` uses: the stored one, or the always-open
	 * fallback when it is invalid. Memoized with the engine.
	 *
	 * @return Schedule
	 */
	public static function schedule() {
		self::engine();
		return self::$schedule;
	}

	/**
	 * Whether the engine in use is the always-open fallback for invalid stored settings.
	 *
	 * @return bool
	 */
	public static function is_fallback() {
		self::engine();
		return self::$is_fallback;
	}

	/**
	 * Whether the store's schedule is open at an instant (default: now).
	 *
	 * Only the schedule: whether orders are accepted also depends on the
	 * Behaviour settings and pause (sprint 4).
	 *
	 * @param int|null $ts UTC timestamp, or null for now.
	 * @return bool
	 */
	public static function is_open( $ts = null ) {
		return self::engine()->is_open( self::timestamp( $ts ) );
	}

	/**
	 * When the schedule next opens: `$ts` itself when open, or null when it
	 * doesn't open within the horizon.
	 *
	 * @param int|null $ts UTC timestamp, or null for now.
	 * @return int|null UTC timestamp.
	 */
	public static function next_open( $ts = null ) {
		return self::engine()->next_open( self::timestamp( $ts ) );
	}

	/**
	 * When the schedule next closes, or null when closed now or open past the horizon.
	 *
	 * @param int|null $ts UTC timestamp, or null for now.
	 * @return int|null UTC timestamp.
	 */
	public static function next_close( $ts = null ) {
		return self::engine()->next_close( self::timestamp( $ts ) );
	}

	/**
	 * Open windows within `[from, to)`, merged and clipped, filtered by
	 * `hourgate_schedule_windows`.
	 *
	 * @param int $from UTC timestamp, inclusive.
	 * @param int $to   UTC timestamp, exclusive.
	 * @return Window[]
	 * @throws \InvalidArgumentException When the span is longer than the horizon.
	 */
	public static function get_windows( int $from, int $to ) {

		$windows = self::engine()->windows( $from, $to );

		/**
		 * Filters the open windows `StoreSchedule::get_windows()` returns.
		 *
		 * Affects only `get_windows()`, not `is_open()` / `next_open()` /
		 * `next_close()`. A return that isn't a list of `Window` objects is ignored.
		 *
		 * @param Window[] $windows Open windows, merged, clipped to [from, to), sorted.
		 * @param int      $from    UTC timestamp, inclusive.
		 * @param int      $to      UTC timestamp, exclusive.
		 */
		$filtered = apply_filters( 'hourgate_schedule_windows', $windows, $from, $to );

		if ( ! is_array( $filtered ) ) {
			return $windows;
		}

		foreach ( $filtered as $window ) {
			if ( ! $window instanceof Window ) {
				return $windows;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Look-ahead for `next_open()` / `next_close()`, filtered by
	 * `hourgate_schedule_horizon_days`. A return that isn't an integer
	 * from 1 to `Engine::MAX_HORIZON_DAYS` falls back to the default (400).
	 *
	 * @return int
	 */
	public static function get_horizon_days() {

		/**
		 * Filters how many days ahead `next_open()` / `next_close()` look.
		 *
		 * @param int $days Default 400. Must be an integer from 1 to 3660.
		 */
		$days = apply_filters( 'hourgate_schedule_horizon_days', Engine::DEFAULT_HORIZON_DAYS );

		if ( ! is_int( $days ) || $days < 1 || $days > Engine::MAX_HORIZON_DAYS ) {
			return Engine::DEFAULT_HORIZON_DAYS;
		}

		return $days;
	}

	/**
	 * Read an option merged with its defaults: known top-level keys only, each
	 * an array, else the default value for that key.
	 *
	 * @param string $option   Option name.
	 * @param array  $defaults Defaults.
	 * @return array
	 */
	private static function read( $option, array $defaults ) {

		$stored   = get_option( $option, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array();

		foreach ( $defaults as $key => $default ) {
			$settings[ $key ] = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : $default;
		}

		return $settings;
	}

	/**
	 * The always-open fallback schedule (the defaults).
	 *
	 * @return Schedule
	 * @throws \LogicException If the shipped defaults are invalid (a bug the unit tests guard).
	 */
	private static function always_open() {

		$schedule = ScheduleFactory::from_arrays( ScheduleSettings::get_default_settings(), ClosuresSettings::get_default_settings() );

		if ( ! $schedule instanceof Schedule ) {
			throw new \LogicException( 'HourGate default schedule is invalid.' );
		}

		return $schedule;
	}

	/**
	 * Log invalid stored settings once a day per distinct problem.
	 *
	 * Only error paths and codes are logged, never labels or other stored text.
	 *
	 * @param ValidationError[] $errors    Validation errors.
	 * @param \Throwable|null   $exception Unexpected exception, if that's what happened.
	 * @return void
	 */
	private static function log_invalid( array $errors, $exception = null ) {

		$details = array_map(
			function ( ValidationError $error ) {
				return $error->get_path() . ' ' . $error->get_code();
			},
			$errors
		);

		if ( null !== $exception ) {
			$details[] = get_class( $exception ) . ': ' . $exception->getMessage();
		}

		$hash = md5( implode( "\n", $details ) );

		if ( get_transient( self::LOGGED_TRANSIENT ) === $hash ) {
			return;
		}

		set_transient( self::LOGGED_TRANSIENT, $hash, DAY_IN_SECONDS );

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->warning(
			sprintf(
				/* translators: %s: list of problems, e.g. "weekly.mon.0.start invalid_time". */
				__( 'The saved opening hours are invalid, so HourGate treats the store as open at all times until they are saved again. Problems: %s', 'hourgate' ),
				implode( '; ', $details )
			),
			array( 'source' => self::LOG_SOURCE )
		);
	}

	/**
	 * A timestamp argument, defaulting to now.
	 *
	 * @param int|null $ts UTC timestamp or null.
	 * @return int
	 */
	private static function timestamp( $ts ) {
		return null === $ts ? time() : (int) $ts;
	}
}
