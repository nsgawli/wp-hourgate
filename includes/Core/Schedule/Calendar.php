<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local calendar-date helpers for `Y-m-d` strings. No time zone involved.
 */
final class Calendar {

	/**
	 * Whether a value is a real calendar date written as `Y-m-d` (`2027-02-29` is not).
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_valid_date( $value ) {

		if ( ! is_string( $value ) || ! preg_match( '/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * The `m-d` part of a `Y-m-d` date, used to match yearly entries.
	 *
	 * @param string $ymd Valid `Y-m-d` date.
	 * @return string
	 */
	public static function month_day( string $ymd ) {
		return substr( $ymd, 5 );
	}

	/**
	 * The year of a `Y-m-d` date.
	 *
	 * @param string $ymd Valid `Y-m-d` date.
	 * @return int
	 */
	public static function year( string $ymd ) {
		return (int) substr( $ymd, 0, 4 );
	}

	/**
	 * Day number of a `Y-m-d` date: days since 1970-01-01 in the proleptic
	 * Gregorian calendar (H. Hinnant's `days_from_civil`). Integer maths only, so
	 * the engine can walk dates without building `DateTime` objects.
	 *
	 * @param string $ymd Valid `Y-m-d` date.
	 * @return int
	 */
	public static function to_day( string $ymd ) {

		$y = (int) substr( $ymd, 0, 4 );
		$m = (int) substr( $ymd, 5, 2 );
		$d = (int) substr( $ymd, 8, 2 );

		$y  -= $m <= 2 ? 1 : 0;
		$era = intdiv( $y >= 0 ? $y : $y - 399, 400 );
		$yoe = $y - $era * 400;
		$doy = intdiv( 153 * ( $m + ( $m > 2 ? -3 : 9 ) ) + 2, 5 ) + $d - 1;
		$doe = $yoe * 365 + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) + $doy;

		return $era * 146097 + $doe - 719468;
	}

	/**
	 * `Y-m-d` date of a day number (inverse of `to_day()`).
	 *
	 * @param int $day Days since 1970-01-01.
	 * @return string
	 */
	public static function from_day( int $day ) {

		$z   = $day + 719468;
		$era = intdiv( $z >= 0 ? $z : $z - 146096, 146097 );
		$doe = $z - $era * 146097;
		$yoe = intdiv( $doe - intdiv( $doe, 1460 ) + intdiv( $doe, 36524 ) - intdiv( $doe, 146096 ), 365 );
		$doy = $doe - ( 365 * $yoe + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) );
		$mp  = intdiv( 5 * $doy + 2, 153 );
		$d   = $doy - intdiv( 153 * $mp + 2, 5 ) + 1;
		$m   = $mp + ( $mp < 10 ? 3 : -9 );
		$y   = $yoe + $era * 400 + ( $m <= 2 ? 1 : 0 );

		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	/**
	 * ISO 8601 weekday of a day number: 1 = Monday … 7 = Sunday.
	 *
	 * @param int $day Days since 1970-01-01 (a Thursday).
	 * @return int
	 */
	public static function iso_weekday( int $day ) {
		return ( ( $day % 7 ) + 10 ) % 7 + 1;
	}
}
