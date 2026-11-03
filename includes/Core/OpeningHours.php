<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Core\Schedule\Calendar;
use HourGate\Core\Schedule\DayPlan;
use HourGate\Core\Schedule\TimeRange;
use HourGate\Core\Schedule\WeeklyHours;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store's opening hours as shoppers read them: one row per weekday and
 * the closures and special hours coming up, preformatted in the site's
 * locale, time format and start of the week (OW-036).
 *
 * One builder for every consumer: `GET /status?include=hours`, and the
 * "Opening hours" block and shortcode (OW-041). Everything is plain text;
 * escape it at output. Built from the schedule the engine uses, so the
 * always-open fallback shows as open 24 hours on every day.
 */
class OpeningHours {

	/**
	 * How many local dates, from today, `upcoming()` looks at.
	 *
	 * @var int
	 */
	const UPCOMING_DAYS = 30;

	/**
	 * A day with ranges.
	 *
	 * @var string
	 */
	const STATUS_OPEN = 'open';

	/**
	 * A day with one range from midnight to midnight.
	 *
	 * @var string
	 */
	const STATUS_ALL_DAY = 'all_day';

	/**
	 * A day without ranges.
	 *
	 * @var string
	 */
	const STATUS_CLOSED = 'closed';

	/**
	 * Both parts: `{ days, upcoming }`.
	 *
	 * @param int|null $now UTC timestamp (default: now), for today and the upcoming dates.
	 * @return array
	 */
	public static function build( $now = null ) {

		$now = null === $now ? time() : (int) $now;

		return array(
			'days'     => self::days( $now ),
			'upcoming' => self::upcoming( $now ),
		);
	}

	/**
	 * The weekly hours, one row per weekday from the site's `start_of_week`:
	 * `{ day, name, today, status, label }`. `day` is the stored key
	 * (`mon`…`sun`), `name` the translated weekday, `today` whether it is the
	 * store's weekday now, `status` one of the `STATUS_*` constants and `label`
	 * the ranges ("9:00 am – 1:00 pm, 2:00 pm – 6:00 pm"), "Open 24 hours" or
	 * "Closed". An overnight range shows on the day it starts.
	 *
	 * @param int $now UTC timestamp.
	 * @return array[]
	 */
	public static function days( int $now ) {

		$weekly = StoreSchedule::schedule()->get_weekly();
		$start  = (int) get_option( 'start_of_week', 1 );
		$first  = 0 === $start ? 7 : max( 1, min( 7, $start ) );
		$today  = (int) wp_date( 'N', $now );
		$rows   = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$iso    = ( $first - 1 + $i ) % 7 + 1;
			$day    = WeeklyHours::DAYS[ $iso - 1 ];
			$ranges = $weekly->get_ranges( $day );
			$status = self::status( $ranges );

			$rows[] = array(
				'day'    => $day,
				'name'   => self::weekday_name( $iso ),
				'today'  => $iso === $today,
				'status' => $status,
				'label'  => self::label( $status, $ranges ),
			);
		}

		return $rows;
	}

	/**
	 * Closures and special hours in the next `UPCOMING_DAYS` local dates, in
	 * date order: `{ type, label, from, to, dates, hours }`. `type` is
	 * `closure` or `special`; `from` / `to` are `Y-m-d`, clipped to the
	 * period looked at (a closure that started yesterday starts today here);
	 * `dates` is them in the site's date format; `hours` is the special
	 * hours' label (`null` for a closure). Consecutive dates of one closure
	 * are one entry.
	 *
	 * @param int $now UTC timestamp.
	 * @return array[]
	 */
	public static function upcoming( int $now ) {

		$engine  = StoreSchedule::engine();
		$today   = Calendar::to_day( wp_date( 'Y-m-d', $now ) );
		$entries = array();
		$last    = null;

		for ( $day = $today; $day < $today + self::UPCOMING_DAYS; $day++ ) {
			$plan = $engine->day_plan( Calendar::from_day( $day ) );

			if ( DayPlan::RULE_WEEKLY === $plan->get_rule() ) {
				$last = null;
				continue;
			}

			$key = $plan->get_rule_key();

			if ( null !== $last && $key === $last && DayPlan::RULE_CLOSURE === $plan->get_rule() ) {
				$entries[ count( $entries ) - 1 ]['to'] = $plan->get_date();
				continue;
			}

			$closure   = DayPlan::RULE_CLOSURE === $plan->get_rule();
			$entries[] = array(
				'type'  => $closure ? 'closure' : 'special',
				'label' => $plan->get_label(),
				'from'  => $plan->get_date(),
				'to'    => $plan->get_date(),
				'hours' => $closure ? null : self::label( self::status( $plan->get_ranges() ), $plan->get_ranges() ),
			);
			$last      = $key;
		}

		foreach ( $entries as $i => $entry ) {
			$entries[ $i ]['dates'] = $entry['from'] === $entry['to']
				? self::format_date( $entry['from'] )
				/* translators: 1: first date, 2: last date of a closure. */
				: sprintf( _x( '%1$s – %2$s', 'date range', 'hourgate' ), self::format_date( $entry['from'] ), self::format_date( $entry['to'] ) );
		}

		return $entries;
	}

	/**
	 * A day's status from its ranges.
	 *
	 * @param TimeRange[] $ranges Ranges.
	 * @return string
	 */
	private static function status( array $ranges ) {

		if ( ! $ranges ) {
			return self::STATUS_CLOSED;
		}

		if ( 1 === count( $ranges ) && 0 === $ranges[0]->get_start() && TimeRange::MINUTES_PER_DAY === $ranges[0]->get_end() ) {
			return self::STATUS_ALL_DAY;
		}

		return self::STATUS_OPEN;
	}

	/**
	 * A day's label.
	 *
	 * @param string      $status `STATUS_*`.
	 * @param TimeRange[] $ranges Ranges.
	 * @return string
	 */
	private static function label( $status, array $ranges ) {

		if ( self::STATUS_CLOSED === $status ) {
			return __( 'Closed', 'hourgate' );
		}

		if ( self::STATUS_ALL_DAY === $status ) {
			return __( 'Open 24 hours', 'hourgate' );
		}

		$parts = array_map(
			function ( TimeRange $range ) {
				return sprintf(
					/* translators: 1: opening time, 2: closing time, e.g. "9:00 am – 5:00 pm". */
					_x( '%1$s – %2$s', 'time range', 'hourgate' ),
					self::format_minutes( $range->get_start() ),
					self::format_minutes( $range->get_end() )
				);
			},
			$ranges
		);

		/* translators: separator between a day's time ranges, e.g. "9:00 am – 1:00 pm, 2:00 pm – 6:00 pm". */
		return implode( __( ', ', 'hourgate' ), $parts );
	}

	/**
	 * A time of day in the site's time format and locale. Computed in UTC, so
	 * the store's zone and DST never move it; 1440 (`24:00`) shows as midnight.
	 *
	 * @param int $minutes Minutes from midnight, 0–1440.
	 * @return string
	 */
	private static function format_minutes( int $minutes ) {

		$format = (string) get_option( 'time_format' );

		return (string) wp_date( '' === $format ? 'g:i a' : $format, $minutes * MINUTE_IN_SECONDS, new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * A local date in the site's date format and locale, without zone conversion.
	 *
	 * @param string $ymd `Y-m-d`.
	 * @return string
	 */
	private static function format_date( string $ymd ) {

		$format = (string) get_option( 'date_format' );
		$utc    = new \DateTimeZone( 'UTC' );

		return (string) wp_date( '' === $format ? 'F j, Y' : $format, ( new \DateTimeImmutable( $ymd . ' 12:00:00', $utc ) )->getTimestamp(), $utc );
	}

	/**
	 * The translated weekday name.
	 *
	 * @param int $iso ISO weekday, 1 (Monday) to 7 (Sunday).
	 * @return string
	 */
	private static function weekday_name( int $iso ) {

		// 2024-01-01 was a Monday.
		$utc = new \DateTimeZone( 'UTC' );

		return (string) wp_date( 'l', ( new \DateTimeImmutable( '2024-01-01 12:00:00', $utc ) )->modify( '+' . ( $iso - 1 ) . ' days' )->getTimestamp(), $utc );
	}
}
