<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Schedule;

use HourGate\Core\Schedule\ValidationError;
use HourGate\Core\Schedule\WeeklyHours;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the engine's `ValidationError` codes into translated messages that
 * name the day, range or entry, e.g. "Monday, range 2, start time: must be a
 * time from 00:00 to 23:59 (or 24:00 as an end)."
 */
class ErrorMessages {

	/**
	 * Longest piece of a request key echoed back in a message.
	 *
	 * @var int
	 */
	const MAX_KEY_ECHO = 40;

	/**
	 * Errors as `{ path, code, message }` for the REST response.
	 *
	 * @param ValidationError[] $errors Errors from `ScheduleFactory`.
	 * @return array[]
	 */
	public static function to_response( array $errors ) {

		return array_map(
			function ( ValidationError $error ) {
				return array(
					'path'    => $error->get_path(),
					'code'    => $error->get_code(),
					'message' => self::message( $error ),
				);
			},
			$errors
		);
	}

	/**
	 * One translated message.
	 *
	 * @param ValidationError $error Error.
	 * @return string
	 */
	public static function message( ValidationError $error ) {

		$problem = self::problem( $error );
		$subject = self::subject( $error );

		if ( '' === $subject ) {
			return $problem;
		}

		/* translators: 1: what the problem is about, e.g. "Monday, range 2, start time". 2: the problem. */
		return sprintf( __( '%1$s: %2$s', 'hourgate' ), $subject, $problem );
	}

	/**
	 * What the error is about, from its path.
	 *
	 * @param ValidationError $error Error.
	 * @return string Empty for errors about the whole request.
	 */
	private static function subject( ValidationError $error ) {

		$parts = '' === $error->get_path() ? array() : explode( '.', $error->get_path() );

		if ( ! $parts ) {
			$group = $error->get_params()['group'] ?? '';

			if ( 'schedule' === $group ) {
				return __( 'Weekly hours settings', 'hourgate' );
			}

			return 'closures' === $group ? __( 'Closures settings', 'hourgate' ) : '';
		}

		$names = array();

		switch ( $parts[0] ) {
			case 'weekly':
				$names[] = isset( $parts[1] ) ? self::day_name( $parts[1] ) : __( 'Weekly hours', 'hourgate' );
				$names   = array_merge( $names, self::range_names( array_slice( $parts, 2 ) ) );
				break;

			case 'closures':
				if ( ! isset( $parts[1] ) ) {
					$names[] = __( 'Closures', 'hourgate' );
					break;
				}

				/* translators: %d: position of the closure in the list, from 1. */
				$names[] = sprintf( __( 'Closure %d', 'hourgate' ), (int) $parts[1] + 1 );

				if ( isset( $parts[2] ) ) {
					$names[] = self::field_name( $parts[2] );
				}
				break;

			case 'specialHours':
				if ( ! isset( $parts[1] ) ) {
					$names[] = __( 'Special hours', 'hourgate' );
					break;
				}

				/* translators: %d: position of the special-hours entry in the list, from 1. */
				$names[] = sprintf( __( 'Special hours %d', 'hourgate' ), (int) $parts[1] + 1 );

				if ( isset( $parts[2] ) && 'ranges' === $parts[2] ) {
					$names = array_merge( $names, isset( $parts[3] ) ? self::range_names( array_slice( $parts, 3 ) ) : array( __( 'ranges', 'hourgate' ) ) );
				} elseif ( isset( $parts[2] ) ) {
					$names[] = self::field_name( $parts[2] );
				}
				break;

			default:
				$names[] = self::echo_key( $error->get_path() );
		}

		return implode( ', ', $names );
	}

	/**
	 * Names for `[ index, start|end ]` path parts of a range.
	 *
	 * @param string[] $parts Remaining path parts.
	 * @return string[]
	 */
	private static function range_names( array $parts ) {

		$names = array();

		if ( isset( $parts[0] ) ) {
			/* translators: %d: position of the time range in the day, from 1. */
			$names[] = sprintf( __( 'range %d', 'hourgate' ), (int) $parts[0] + 1 );
		}

		if ( isset( $parts[1] ) ) {
			$names[] = self::field_name( $parts[1] );
		}

		return $names;
	}

	/**
	 * Translated name of an entry field.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	private static function field_name( $field ) {

		$fields = array(
			'start'  => __( 'start time', 'hourgate' ),
			'end'    => __( 'end time', 'hourgate' ),
			'from'   => __( 'first date', 'hourgate' ),
			'to'     => __( 'last date', 'hourgate' ),
			'date'   => __( 'date', 'hourgate' ),
			'label'  => __( 'label', 'hourgate' ),
			'id'     => __( 'id', 'hourgate' ),
			'yearly' => __( 'repeats yearly', 'hourgate' ),
		);

		return $fields[ $field ] ?? self::echo_key( $field );
	}

	/**
	 * Translated weekday name for a day key; an unknown key is quoted back, shortened.
	 *
	 * @param string $day Day key.
	 * @return string
	 */
	public static function day_name( $day ) {

		$days = array(
			'mon' => __( 'Monday', 'hourgate' ),
			'tue' => __( 'Tuesday', 'hourgate' ),
			'wed' => __( 'Wednesday', 'hourgate' ),
			'thu' => __( 'Thursday', 'hourgate' ),
			'fri' => __( 'Friday', 'hourgate' ),
			'sat' => __( 'Saturday', 'hourgate' ),
			'sun' => __( 'Sunday', 'hourgate' ),
		);

		return $days[ $day ] ?? self::echo_key( $day );
	}

	/**
	 * A request key quoted back, shortened. The response is JSON; the admin UI escapes on output.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function echo_key( $key ) {

		$key = (string) $key;

		if ( strlen( $key ) > self::MAX_KEY_ECHO ) {
			$key = substr( $key, 0, self::MAX_KEY_ECHO ) . '…';
		}

		/* translators: Quotation marks around a key sent in the request; use your language's. %s: the key. */
		return sprintf( __( '“%s”', 'hourgate' ), wp_check_invalid_utf8( $key, true ) );
	}

	/**
	 * The problem, by code.
	 *
	 * @param ValidationError $error Error.
	 * @return string
	 */
	private static function problem( ValidationError $error ) {

		$params = $error->get_params();
		$other  = isset( $params['index'] ) ? (int) $params['index'] + 1 : 0;
		$max    = isset( $params['max'] ) ? (int) $params['max'] : 0;
		$parts  = explode( '.', $error->get_path() );

		switch ( $error->get_code() ) {
			case ValidationError::INVALID_TYPE:
				return __( 'has the wrong format.', 'hourgate' );

			case ValidationError::REQUIRED:
				return __( 'is missing.', 'hourgate' );

			case ValidationError::UNKNOWN_KEY:
				/* translators: %s: the seven weekday keys. */
				return sprintf( __( 'is not a weekday. Use %s.', 'hourgate' ), implode( ', ', WeeklyHours::DAYS ) );

			case ValidationError::INVALID_TIME:
				return __( 'must be a time from 00:00 to 23:59 (or 24:00 as an end).', 'hourgate' );

			case ValidationError::INVALID_START:
				return __( 'can\'t be 24:00. Use 00:00 of the next day.', 'hourgate' );

			case ValidationError::EMPTY_RANGE:
				return __( 'starts and ends at the same time.', 'hourgate' );

			case ValidationError::OVERLAP:
				/* translators: %d: position of the other range, from 1. */
				return sprintf( __( 'overlaps range %d.', 'hourgate' ), $other );

			case ValidationError::OVERLAP_PREVIOUS_DAY:
				return sprintf(
					/* translators: 1: position of the other range, from 1. 2: weekday name. */
					__( 'overlaps range %1$d of %2$s, which runs past midnight.', 'hourgate' ),
					$other,
					self::day_name( $params['day'] ?? '' )
				);

			case ValidationError::INVALID_DATE:
				return __( 'must be a real date written as YYYY-MM-DD.', 'hourgate' );

			case ValidationError::DATE_ORDER:
				return __( 'is before the first date.', 'hourgate' );

			case ValidationError::YEARLY_TOO_LONG:
				return __( 'makes a yearly closure last a year or more.', 'hourgate' );

			case ValidationError::DUPLICATE_DATE:
				/* translators: %d: position of the other special-hours entry, from 1. */
				return sprintf( __( 'falls on the same date as special hours %d.', 'hourgate' ), $other );

			case ValidationError::INVALID_ID:
				return __( 'may use only letters, digits, "-" and "_", up to 64 characters.', 'hourgate' );

			case ValidationError::DUPLICATE_ID:
				return 'specialHours' === $parts[0]
					/* translators: %d: position of the other special-hours entry, from 1. */
					? sprintf( __( 'is already used by special hours %d.', 'hourgate' ), $other )
					/* translators: %d: position of the other closure, from 1. */
					: sprintf( __( 'is already used by closure %d.', 'hourgate' ), $other );

			case ValidationError::TOO_LONG:
				/* translators: %d: maximum number of characters. */
				return sprintf( __( 'is longer than %d characters.', 'hourgate' ), $max );

			case ValidationError::INVALID_ENCODING:
				return __( 'isn\'t valid text.', 'hourgate' );

			case ValidationError::TOO_MANY:
				/* translators: %d: maximum number of entries. */
				return sprintf( __( 'has more than %d entries.', 'hourgate' ), $max );

			case ValidationError::TOO_MANY_ERRORS:
				/* translators: %d: number of problems shown. */
				return sprintf( __( 'There are more than %d problems. Fix these first.', 'hourgate' ), $max );
		}

		return __( 'is not valid.', 'hourgate' );
	}
}
