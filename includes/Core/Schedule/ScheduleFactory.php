<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a `Schedule` from the stored settings arrays, or reports every
 * problem as a `ValidationError`.
 *
 * Input shape (09 §16.2; local wall-clock `HH:MM`, dates `Y-m-d`):
 * - schedule group: `{ weekly: { mon: [ { start, end } ], tue: [], … sun: [] } }`;
 * - closures group: `{ closures: [ { id, label, from, to, yearly } ],
 *   specialHours: [ { id, label, date, yearly, ranges: [ { start, end } ] } ] }`.
 *
 * Unknown keys inside a range or an entry, and unknown top-level keys, are
 * dropped. The weekday keys are a fixed set: an unknown or missing weekday is
 * an error. Missing `closures` / `specialHours` mean none. Limits are errors,
 * never truncation. Pure PHP, no WordPress.
 */
final class ScheduleFactory {

	/**
	 * Most ranges in one weekday or one special-hours entry.
	 *
	 * @var int
	 */
	const MAX_RANGES_PER_DAY = 24;

	/**
	 * Most closures.
	 *
	 * @var int
	 */
	const MAX_CLOSURES = 200;

	/**
	 * Most special-hours entries.
	 *
	 * @var int
	 */
	const MAX_SPECIAL_HOURS = 400;

	/**
	 * Longest label, in characters.
	 *
	 * @var int
	 */
	const MAX_LABEL_LENGTH = 100;

	/**
	 * Most errors reported; past this one `too_many_errors` closes the list.
	 *
	 * @var int
	 */
	const MAX_ERRORS = 100;

	/**
	 * Allowed ids: ASCII letters, digits, `_` and `-`, 1–64 characters.
	 *
	 * @var string
	 */
	const ID_PATTERN = '/\A[A-Za-z0-9_-]{1,64}\z/';

	/**
	 * Errors found so far.
	 *
	 * @var ValidationError[]
	 */
	private $errors = array();

	/**
	 * Use `from_arrays()`.
	 */
	private function __construct() {
	}

	/**
	 * Validate the two settings groups and build the schedule.
	 *
	 * @param mixed $schedule_settings The schedule group (`hourgate_schedule_settings`).
	 * @param mixed $closures_settings The closures group (`hourgate_closures_settings`).
	 * @return Schedule|ValidationError[] The schedule, or a non-empty list of errors.
	 */
	public static function from_arrays( $schedule_settings, $closures_settings ) {
		return ( new self() )->build( $schedule_settings, $closures_settings );
	}

	/**
	 * Validate and build.
	 *
	 * @param mixed $schedule_settings The schedule group.
	 * @param mixed $closures_settings The closures group.
	 * @return Schedule|ValidationError[]
	 */
	private function build( $schedule_settings, $closures_settings ) {

		$weekly        = null;
		$closures      = array();
		$special_hours = array();

		if ( ! is_array( $schedule_settings ) ) {
			$this->add_error( '', ValidationError::INVALID_TYPE, array( 'group' => 'schedule' ) );
		} else {
			$weekly = $this->parse_weekly( $schedule_settings );
		}

		if ( ! is_array( $closures_settings ) ) {
			$this->add_error( '', ValidationError::INVALID_TYPE, array( 'group' => 'closures' ) );
		} else {
			$closures      = $this->parse_closures( $closures_settings['closures'] ?? array() );
			$special_hours = $this->parse_special_hours( $closures_settings['specialHours'] ?? array() );
		}

		if ( $this->errors || null === $weekly ) {
			return $this->errors;
		}

		return new Schedule( $weekly, $closures, $special_hours );
	}

	/**
	 * Weekly hours: all seven days, each a list of ranges, no overlaps within a
	 * day or with the previous day's past-midnight range.
	 *
	 * @param array $settings The schedule group.
	 * @return WeeklyHours|null Null when invalid.
	 */
	private function parse_weekly( array $settings ) {

		if ( ! isset( $settings['weekly'] ) ) {
			$this->add_error( 'weekly', ValidationError::REQUIRED );
			return null;
		}

		$weekly = $settings['weekly'];

		if ( ! is_array( $weekly ) ) {
			$this->add_error( 'weekly', ValidationError::INVALID_TYPE );
			return null;
		}

		$valid = true;

		foreach ( array_keys( $weekly ) as $key ) {
			if ( ! in_array( $key, WeeklyHours::DAYS, true ) ) {
				$this->add_error( 'weekly.' . $key, ValidationError::UNKNOWN_KEY );
				$valid = false;
			}
		}

		// Day key => ranges keyed by their index in the input.
		$days = array();

		foreach ( WeeklyHours::DAYS as $day ) {
			if ( ! array_key_exists( $day, $weekly ) ) {
				$this->add_error( 'weekly.' . $day, ValidationError::REQUIRED );
				$valid = false;
				continue;
			}

			$ranges = $this->parse_ranges( $weekly[ $day ], 'weekly.' . $day );

			if ( null === $ranges ) {
				$valid = false;
				continue;
			}

			$days[ $day ] = $ranges;

			if ( ! $this->check_overlaps( $ranges, 'weekly.' . $day ) ) {
				$valid = false;
			}
		}

		if ( ! $valid ) {
			return null;
		}

		if ( ! $this->check_previous_day_overlaps( $days ) ) {
			return null;
		}

		return new WeeklyHours( array_map( 'array_values', $days ) );
	}

	/**
	 * A list of `{ start, end }` ranges.
	 *
	 * @param mixed  $value List to parse.
	 * @param string $path  Path of the list.
	 * @return TimeRange[]|null Ranges keyed by input index, or null when invalid.
	 */
	private function parse_ranges( $value, string $path ) {

		if ( ! $this->is_list( $value ) ) {
			$this->add_error( $path, ValidationError::INVALID_TYPE );
			return null;
		}

		if ( count( $value ) > self::MAX_RANGES_PER_DAY ) {
			$this->add_error( $path, ValidationError::TOO_MANY, array( 'max' => self::MAX_RANGES_PER_DAY ) );
			return null;
		}

		$ranges = array();
		$valid  = true;

		foreach ( $value as $index => $item ) {
			$item_path = $path . '.' . $index;

			if ( ! is_array( $item ) ) {
				$this->add_error( $item_path, ValidationError::INVALID_TYPE );
				$valid = false;
				continue;
			}

			$start = $this->parse_range_time( $item, 'start', $item_path );
			$end   = $this->parse_range_time( $item, 'end', $item_path );

			if ( null === $start || null === $end ) {
				$valid = false;
				continue;
			}

			if ( $start === $end ) {
				$this->add_error( $item_path, ValidationError::EMPTY_RANGE );
				$valid = false;
				continue;
			}

			$ranges[ $index ] = new TimeRange( $start, $end );
		}

		return $valid ? $ranges : null;
	}

	/**
	 * One time of a range.
	 *
	 * @param array  $item      The range.
	 * @param string $key       `start` or `end`.
	 * @param string $item_path Path of the range.
	 * @return int|null Minutes from midnight, or null when invalid.
	 */
	private function parse_range_time( array $item, string $key, string $item_path ) {

		$path = $item_path . '.' . $key;

		if ( ! isset( $item[ $key ] ) ) {
			$this->add_error( $path, ValidationError::REQUIRED );
			return null;
		}

		if ( ! is_string( $item[ $key ] ) ) {
			$this->add_error( $path, ValidationError::INVALID_TYPE );
			return null;
		}

		$minutes = TimeRange::parse_time( $item[ $key ] );

		if ( null === $minutes ) {
			$this->add_error( $path, ValidationError::INVALID_TIME );
			return null;
		}

		if ( 'start' === $key && TimeRange::MINUTES_PER_DAY === $minutes ) {
			$this->add_error( $path, ValidationError::INVALID_START );
			return null;
		}

		return $minutes;
	}

	/**
	 * Report ranges of one day that overlap. Touching ranges (`12:00` end,
	 * `12:00` start) are fine; the engine merges them.
	 *
	 * @param TimeRange[] $ranges Ranges keyed by input index.
	 * @param string      $path   Path of the list.
	 * @return bool Whether there was no overlap.
	 */
	private function check_overlaps( array $ranges, string $path ) {

		$valid     = true;
		$max_end   = -1;
		$max_index = null;

		foreach ( $this->sorted_indexes( $ranges ) as $index ) {
			$range = $ranges[ $index ];

			if ( null !== $max_index && $range->get_start() < $max_end ) {
				$this->add_error( $path . '.' . $index, ValidationError::OVERLAP, array( 'index' => $max_index ) );
				$valid = false;
			}

			if ( $range->get_end_offset() > $max_end ) {
				$max_end   = $range->get_end_offset();
				$max_index = $index;
			}
		}

		return $valid;
	}

	/**
	 * Report a day's first range that starts before the previous day's
	 * past-midnight range ends (`Mon 22:00–02:00` + `Tue 01:00–05:00`).
	 * Sunday's range runs into Monday.
	 *
	 * @param array<string, TimeRange[]> $days Day key => ranges keyed by input index, all valid.
	 * @return bool Whether there was no overlap.
	 */
	private function check_previous_day_overlaps( array $days ) {

		$valid = true;
		$count = count( WeeklyHours::DAYS );

		foreach ( WeeklyHours::DAYS as $position => $day ) {
			$next = WeeklyHours::DAYS[ ( $position + 1 ) % $count ];

			// Ranges of one day don't overlap, so at most one runs past midnight.
			$spill       = 0;
			$spill_index = null;

			foreach ( $days[ $day ] as $index => $range ) {
				if ( $range->is_overnight() ) {
					$spill       = $range->get_end_offset() - TimeRange::MINUTES_PER_DAY;
					$spill_index = $index;
				}
			}

			if ( null === $spill_index || ! $days[ $next ] ) {
				continue;
			}

			$first = $this->sorted_indexes( $days[ $next ] )[0];

			if ( $days[ $next ][ $first ]->get_start() < $spill ) {
				$this->add_error(
					'weekly.' . $next . '.' . $first,
					ValidationError::OVERLAP_PREVIOUS_DAY,
					array(
						'day'   => $day,
						'index' => $spill_index,
					)
				);
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Closures.
	 *
	 * @param mixed $value The `closures` list.
	 * @return ClosedPeriod[]
	 */
	private function parse_closures( $value ) {

		if ( ! $this->is_list( $value ) ) {
			$this->add_error( 'closures', ValidationError::INVALID_TYPE );
			return array();
		}

		if ( count( $value ) > self::MAX_CLOSURES ) {
			$this->add_error( 'closures', ValidationError::TOO_MANY, array( 'max' => self::MAX_CLOSURES ) );
			return array();
		}

		$entries = array();

		foreach ( $value as $index => $item ) {
			$path = 'closures.' . $index;

			if ( ! is_array( $item ) ) {
				$this->add_error( $path, ValidationError::INVALID_TYPE );
				continue;
			}

			$id     = $this->parse_id( $item, $path );
			$label  = $this->parse_label( $item, $path );
			$from   = $this->parse_date( $item, 'from', $path );
			$to     = $this->parse_date( $item, 'to', $path );
			$yearly = $this->parse_yearly( $item, $path );

			if ( null === $id || null === $label || null === $from || null === $to || null === $yearly ) {
				continue;
			}

			if ( $to < $from ) {
				$this->add_error( $path . '.to', ValidationError::DATE_ORDER );
				continue;
			}

			if ( $yearly && $this->is_a_year_or_more( $from, $to ) ) {
				$this->add_error( $path . '.to', ValidationError::YEARLY_TOO_LONG );
				continue;
			}

			$entries[ $index ] = array(
				'id'     => $id,
				'label'  => $label,
				'from'   => $from,
				'to'     => $to,
				'yearly' => $yearly,
				'seed'   => '' === $id ? implode( "\x1f", array( $from, $to, $yearly ? '1' : '0', $label ) ) : '',
			);
		}

		$ids = $this->assign_ids( $entries, 'closures', 'c_' );

		$closures = array();

		// An entry whose id was a duplicate has no id: an error was recorded, so skip it.
		foreach ( array_intersect_key( $entries, $ids ) as $index => $entry ) {
			$closures[] = new ClosedPeriod( $ids[ $index ], $entry['label'], $entry['from'], $entry['to'], $entry['yearly'] );
		}

		return $closures;
	}

	/**
	 * Special hours. Two entries may not fall on the same date: same `date`,
	 * two yearly entries on the same month and day, or a one-off date on the
	 * month and day of a yearly entry.
	 *
	 * @param mixed $value The `specialHours` list.
	 * @return SpecialHours[]
	 */
	private function parse_special_hours( $value ) {

		if ( ! $this->is_list( $value ) ) {
			$this->add_error( 'specialHours', ValidationError::INVALID_TYPE );
			return array();
		}

		if ( count( $value ) > self::MAX_SPECIAL_HOURS ) {
			$this->add_error( 'specialHours', ValidationError::TOO_MANY, array( 'max' => self::MAX_SPECIAL_HOURS ) );
			return array();
		}

		$entries = array();

		// First entry index per one-off date, per month-day of one-off dates, and per month-day of yearly entries.
		$by_date       = array();
		$by_one_off_md = array();
		$by_yearly_md  = array();

		foreach ( $value as $index => $item ) {
			$path = 'specialHours.' . $index;

			if ( ! is_array( $item ) ) {
				$this->add_error( $path, ValidationError::INVALID_TYPE );
				continue;
			}

			$id     = $this->parse_id( $item, $path );
			$label  = $this->parse_label( $item, $path );
			$date   = $this->parse_date( $item, 'date', $path );
			$yearly = $this->parse_yearly( $item, $path );

			if ( ! array_key_exists( 'ranges', $item ) ) {
				$this->add_error( $path . '.ranges', ValidationError::REQUIRED );
				$ranges = null;
			} else {
				$ranges = $this->parse_ranges( $item['ranges'], $path . '.ranges' );
			}

			if ( null !== $ranges && ! $this->check_overlaps( $ranges, $path . '.ranges' ) ) {
				$ranges = null;
			}

			if ( null === $date || null === $yearly ) {
				continue;
			}

			$md    = Calendar::month_day( $date );
			$other = $yearly
				? ( $by_yearly_md[ $md ] ?? $by_one_off_md[ $md ] ?? null )
				: ( $by_date[ $date ] ?? $by_yearly_md[ $md ] ?? null );

			if ( null !== $other ) {
				$this->add_error( $path . '.date', ValidationError::DUPLICATE_DATE, array( 'index' => $other ) );
				continue;
			}

			if ( $yearly ) {
				$by_yearly_md[ $md ] = $index;
			} else {
				$by_date[ $date ]     = $index;
				$by_one_off_md[ $md ] = $by_one_off_md[ $md ] ?? $index;
			}

			if ( null === $id || null === $label || null === $ranges ) {
				continue;
			}

			$entries[ $index ] = array(
				'id'     => $id,
				'label'  => $label,
				'date'   => $date,
				'yearly' => $yearly,
				'ranges' => $ranges,
				'seed'   => '' === $id ? implode( "\x1f", array( $date, $yearly ? '1' : '0', $label, $this->ranges_seed( $ranges ) ) ) : '',
			);
		}

		$ids = $this->assign_ids( $entries, 'specialHours', 's_' );

		$special_hours = array();

		// An entry whose id was a duplicate has no id: an error was recorded, so skip it.
		foreach ( array_intersect_key( $entries, $ids ) as $index => $entry ) {
			$special_hours[] = new SpecialHours( $ids[ $index ], $entry['label'], $entry['date'], $entry['yearly'], array_values( $entry['ranges'] ) );
		}

		return $special_hours;
	}

	/**
	 * An entry's id. Missing, null or empty means one is generated later.
	 *
	 * @param array  $item Entry.
	 * @param string $path Path of the entry.
	 * @return string|null The id (`''` when it must be generated), or null when invalid.
	 */
	private function parse_id( array $item, string $path ) {

		if ( ! isset( $item['id'] ) || '' === $item['id'] ) {
			return '';
		}

		if ( ! is_string( $item['id'] ) ) {
			$this->add_error( $path . '.id', ValidationError::INVALID_TYPE );
			return null;
		}

		if ( ! preg_match( self::ID_PATTERN, $item['id'] ) ) {
			$this->add_error( $path . '.id', ValidationError::INVALID_ID );
			return null;
		}

		return $item['id'];
	}

	/**
	 * An entry's label, trimmed. Missing or null means empty.
	 *
	 * @param array  $item Entry.
	 * @param string $path Path of the entry.
	 * @return string|null The label, or null when invalid.
	 */
	private function parse_label( array $item, string $path ) {

		if ( ! isset( $item['label'] ) ) {
			return '';
		}

		if ( ! is_string( $item['label'] ) ) {
			$this->add_error( $path . '.label', ValidationError::INVALID_TYPE );
			return null;
		}

		$label = trim( $item['label'] );

		// Counts UTF-8 characters without mbstring; false means invalid UTF-8.
		$length = preg_match_all( '/./su', $label );

		if ( false === $length ) {
			$this->add_error( $path . '.label', ValidationError::INVALID_ENCODING );
			return null;
		}

		if ( $length > self::MAX_LABEL_LENGTH ) {
			$this->add_error( $path . '.label', ValidationError::TOO_LONG, array( 'max' => self::MAX_LABEL_LENGTH ) );
			return null;
		}

		return $label;
	}

	/**
	 * A required `Y-m-d` date.
	 *
	 * @param array  $item Entry.
	 * @param string $key  Key of the date.
	 * @param string $path Path of the entry.
	 * @return string|null The date, or null when invalid.
	 */
	private function parse_date( array $item, string $key, string $path ) {

		if ( ! isset( $item[ $key ] ) ) {
			$this->add_error( $path . '.' . $key, ValidationError::REQUIRED );
			return null;
		}

		if ( ! is_string( $item[ $key ] ) ) {
			$this->add_error( $path . '.' . $key, ValidationError::INVALID_TYPE );
			return null;
		}

		if ( ! Calendar::is_valid_date( $item[ $key ] ) ) {
			$this->add_error( $path . '.' . $key, ValidationError::INVALID_DATE );
			return null;
		}

		return $item[ $key ];
	}

	/**
	 * The optional `yearly` flag (default false). Must be a boolean.
	 *
	 * @param array  $item Entry.
	 * @param string $path Path of the entry.
	 * @return bool|null The flag, or null when invalid.
	 */
	private function parse_yearly( array $item, string $path ) {

		if ( ! isset( $item['yearly'] ) ) {
			return false;
		}

		if ( ! is_bool( $item['yearly'] ) ) {
			$this->add_error( $path . '.yearly', ValidationError::INVALID_TYPE );
			return null;
		}

		return $item['yearly'];
	}

	/**
	 * Whether `$from`–`$to` reaches the same month and day a year later, so it
	 * can't repeat yearly without covering every day.
	 *
	 * @param string $from First date, `Y-m-d`.
	 * @param string $to   Last date, `Y-m-d`, not before `$from`.
	 * @return bool
	 */
	private function is_a_year_or_more( string $from, string $to ) {

		$years = Calendar::year( $to ) - Calendar::year( $from );

		return $years > 1 || ( 1 === $years && Calendar::month_day( $to ) >= Calendar::month_day( $from ) );
	}

	/**
	 * Keep valid ids, report duplicates, and generate the missing ones.
	 *
	 * A generated id is `$prefix` plus 8 hex characters hashed from the entry's
	 * content, so the same stored entry gets the same id on every read. A clash
	 * with another id is resolved by rehashing with a counter.
	 *
	 * @param array  $entries Entries keyed by input index: `id` (`''` when missing) and `seed` (the content to hash, set only when `id` is missing).
	 * @param string $path    Path of the list.
	 * @param string $prefix  `c_` or `s_`.
	 * @return array<int, string> Input index => id.
	 */
	private function assign_ids( array $entries, string $path, string $prefix ) {

		$ids  = array();
		$used = array();

		foreach ( $entries as $index => $entry ) {
			if ( '' === $entry['id'] ) {
				continue;
			}

			if ( isset( $used[ $entry['id'] ] ) ) {
				$this->add_error( $path . '.' . $index . '.id', ValidationError::DUPLICATE_ID, array( 'index' => $used[ $entry['id'] ] ) );
				continue;
			}

			$used[ $entry['id'] ] = $index;
			$ids[ $index ]        = $entry['id'];
		}

		foreach ( $entries as $index => $entry ) {
			if ( '' !== $entry['id'] ) {
				continue;
			}

			$seed = $prefix . $entry['seed'];
			$id   = $prefix . substr( md5( $seed ), 0, 8 );

			for ( $n = 1; isset( $used[ $id ] ); $n++ ) {
				$id = $prefix . substr( md5( $seed . '#' . $n ), 0, 8 );
			}

			$used[ $id ]   = $index;
			$ids[ $index ] = $id;
		}

		return $ids;
	}

	/**
	 * Ranges as one string for an id seed, e.g. `09:00-12:00,14:00-18:00`.
	 *
	 * @param TimeRange[] $ranges Ranges keyed by input index.
	 * @return string
	 */
	private function ranges_seed( array $ranges ) {

		$parts = array();

		foreach ( $this->sorted_indexes( $ranges ) as $index ) {
			$range   = $ranges[ $index ]->to_array();
			$parts[] = $range['start'] . '-' . $range['end'];
		}

		return implode( ',', $parts );
	}

	/**
	 * Input index order of ranges sorted by start (ties by index, so the
	 * result doesn't depend on PHP's sort stability).
	 *
	 * @param TimeRange[] $ranges Ranges keyed by input index.
	 * @return int[]
	 */
	private function sorted_indexes( array $ranges ) {

		$indexes = array_keys( $ranges );

		if ( count( $indexes ) < 2 ) {
			return $indexes;
		}

		usort(
			$indexes,
			function ( $a, $b ) use ( $ranges ) {
				return array( $ranges[ $a ]->get_start(), $a ) <=> array( $ranges[ $b ]->get_start(), $b );
			}
		);

		return $indexes;
	}

	/**
	 * Whether a value is an array with keys 0…n-1 in order (a JSON array).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function is_list( $value ) {
		return is_array( $value ) && array_values( $value ) === $value;
	}

	/**
	 * Record an error, up to `MAX_ERRORS`.
	 *
	 * @param string $path   Dot path.
	 * @param string $code   Error code.
	 * @param array  $params Message values.
	 * @return void
	 */
	private function add_error( string $path, string $code, array $params = array() ) {

		$count = count( $this->errors );

		if ( $count > self::MAX_ERRORS ) {
			return;
		}

		if ( self::MAX_ERRORS === $count ) {
			$this->errors[] = new ValidationError( '', ValidationError::TOO_MANY_ERRORS, array( 'max' => self::MAX_ERRORS ) );
			return;
		}

		$this->errors[] = new ValidationError( $path, $code, $params );
	}
}
