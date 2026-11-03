<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Special hours: ranges that replace the weekly hours on one local date.
 * No ranges means closed that date. Immutable; ranges are sorted by start.
 *
 * A yearly entry repeats every year on the same month and day, the year in
 * `date` ignored, so a yearly `Feb 29` applies only in leap years (study 10, D6).
 */
final class SpecialHours {

	/**
	 * Id, unique among special-hours entries.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Merchant's label; may be empty.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * The date, `Y-m-d`.
	 *
	 * @var string
	 */
	private $date;

	/**
	 * Whether it repeats every year.
	 *
	 * @var bool
	 */
	private $yearly;

	/**
	 * Sorted ranges.
	 *
	 * @var TimeRange[]
	 */
	private $ranges;

	/**
	 * Create special hours. `ScheduleFactory` validates input and reports
	 * errors; this only guards the invariants the engine relies on.
	 *
	 * @param string      $id     Id.
	 * @param string      $label  Label.
	 * @param string      $date   Date, `Y-m-d`.
	 * @param bool        $yearly Repeats every year.
	 * @param TimeRange[] $ranges Ranges for the date; empty means closed.
	 * @throws \InvalidArgumentException For an empty id, an invalid date or a non-range.
	 */
	public function __construct( string $id, string $label, string $date, bool $yearly, array $ranges ) {

		if ( '' === $id || ! Calendar::is_valid_date( $date ) ) {
			throw new \InvalidArgumentException( 'Invalid special hours.' );
		}

		foreach ( $ranges as $range ) {
			if ( ! $range instanceof TimeRange ) {
				throw new \InvalidArgumentException( 'Special hours take TimeRange objects.' );
			}
		}

		$this->id     = $id;
		$this->label  = $label;
		$this->date   = $date;
		$this->yearly = $yearly;
		$this->ranges = TimeRange::sort( $ranges );
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Label; may be empty.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * The date, `Y-m-d`.
	 *
	 * @return string
	 */
	public function get_date() {
		return $this->date;
	}

	/**
	 * Whether it repeats every year.
	 *
	 * @return bool
	 */
	public function is_yearly() {
		return $this->yearly;
	}

	/**
	 * Ranges, sorted by start; empty means closed that date.
	 *
	 * @return TimeRange[]
	 */
	public function get_ranges() {
		return $this->ranges;
	}

	/**
	 * The entry as stored.
	 *
	 * @return array{id: string, label: string, date: string, yearly: bool, ranges: array}
	 */
	public function to_array() {
		return array(
			'id'     => $this->id,
			'label'  => $this->label,
			'date'   => $this->date,
			'yearly' => $this->yearly,
			'ranges' => array_map(
				function ( TimeRange $range ) {
					return $range->to_array();
				},
				$this->ranges
			),
		);
	}
}
