<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One problem found by `ScheduleFactory`, as data rather than a message.
 *
 * The engine stays free of WordPress, so it never translates. The REST layer
 * turns `code` and `params` into a translated message for `path`.
 */
final class ValidationError {

	/** The value has the wrong type (e.g. a time sent as a number, `ranges` as a string). */
	const INVALID_TYPE = 'invalid_type';

	/** A required key is missing. */
	const REQUIRED = 'required';

	/** A key that must be one of a fixed set isn't (an unknown weekday). */
	const UNKNOWN_KEY = 'unknown_key';

	/** A time isn't `HH:MM` from `00:00` to `23:59` (or `24:00` as an end). */
	const INVALID_TIME = 'invalid_time';

	/** `24:00` used as a start. */
	const INVALID_START = 'invalid_start';

	/** A range whose start equals its end. */
	const EMPTY_RANGE = 'empty_range';

	/** Overlaps another range of the same day. `params.index`: the other range. */
	const OVERLAP = 'overlap';

	/** Overlaps the previous day's past-midnight range. `params.day`, `params.index`: that range. */
	const OVERLAP_PREVIOUS_DAY = 'overlap_previous_day';

	/** A date isn't a real `Y-m-d` calendar date. */
	const INVALID_DATE = 'invalid_date';

	/** A closure ends before it starts. */
	const DATE_ORDER = 'date_order';

	/** A yearly closure lasts a year or more. */
	const YEARLY_TOO_LONG = 'yearly_too_long';

	/** Two special-hours entries fall on the same date. `params.index`: the other entry. */
	const DUPLICATE_DATE = 'duplicate_date';

	/** An id has characters other than `A-Z a-z 0-9 _ -`, or is longer than 64. */
	const INVALID_ID = 'invalid_id';

	/** Two entries of one list share an id. `params.index`: the other entry. */
	const DUPLICATE_ID = 'duplicate_id';

	/** A label is longer than allowed. `params.max`. */
	const TOO_LONG = 'too_long';

	/** A label isn't valid UTF-8. */
	const INVALID_ENCODING = 'invalid_encoding';

	/** A list is longer than allowed. `params.max`. */
	const TOO_MANY = 'too_many';

	/** More errors than `ScheduleFactory::MAX_ERRORS`; the rest weren't reported. `params.max`. */
	const TOO_MANY_ERRORS = 'too_many_errors';

	/**
	 * Dot path to the value, e.g. `weekly.mon.0.start` or `closures.3.to`.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * One of the constants above.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Values for the message, e.g. `array( 'max' => 24 )`.
	 *
	 * @var array
	 */
	private $params;

	/**
	 * Create an error.
	 *
	 * @param string $path   Dot path to the value.
	 * @param string $code   Error code.
	 * @param array  $params Message values.
	 */
	public function __construct( string $path, string $code, array $params = array() ) {
		$this->path   = $path;
		$this->code   = $code;
		$this->params = $params;
	}

	/**
	 * Dot path to the value.
	 *
	 * @return string
	 */
	public function get_path() {
		return $this->path;
	}

	/**
	 * Error code.
	 *
	 * @return string
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Message values.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * The error as an array.
	 *
	 * @return array{path: string, code: string, params: array}
	 */
	public function to_array() {
		return array(
			'path'   => $this->path,
			'code'   => $this->code,
			'params' => $this->params,
		);
	}
}
