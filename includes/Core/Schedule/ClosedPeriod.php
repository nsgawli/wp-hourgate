<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A closure: local dates `from`–`to` (inclusive), closed all day. Immutable.
 *
 * A yearly closure repeats every year on the same month and day, the year in
 * `from` ignored. It may wrap the year end (`2026-12-24`–`2027-01-02`) and lasts
 * less than a year. Matching by month and day means a yearly `Feb 29` only
 * exists in leap years (study 10, D6).
 */
final class ClosedPeriod {

	/**
	 * Id, unique among closures.
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
	 * First closed date, `Y-m-d`.
	 *
	 * @var string
	 */
	private $from;

	/**
	 * Last closed date, `Y-m-d`.
	 *
	 * @var string
	 */
	private $to;

	/**
	 * Whether it repeats every year.
	 *
	 * @var bool
	 */
	private $yearly;

	/**
	 * Create a closure. `ScheduleFactory` validates input and reports errors;
	 * this only guards the invariants the engine relies on.
	 *
	 * @param string $id     Id.
	 * @param string $label  Label.
	 * @param string $from   First date, `Y-m-d`.
	 * @param string $to     Last date, `Y-m-d`, not before `$from`.
	 * @param bool   $yearly Repeats every year.
	 * @throws \InvalidArgumentException For an empty id, an invalid date or `$to` before `$from`.
	 */
	public function __construct( string $id, string $label, string $from, string $to, bool $yearly ) {

		if ( '' === $id || ! Calendar::is_valid_date( $from ) || ! Calendar::is_valid_date( $to ) || $to < $from ) {
			throw new \InvalidArgumentException( 'Invalid closure.' );
		}

		$this->id     = $id;
		$this->label  = $label;
		$this->from   = $from;
		$this->to     = $to;
		$this->yearly = $yearly;
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
	 * First closed date, `Y-m-d`.
	 *
	 * @return string
	 */
	public function get_from() {
		return $this->from;
	}

	/**
	 * Last closed date, `Y-m-d`.
	 *
	 * @return string
	 */
	public function get_to() {
		return $this->to;
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
	 * The closure as stored.
	 *
	 * @return array{id: string, label: string, from: string, to: string, yearly: bool}
	 */
	public function to_array() {
		return array(
			'id'     => $this->id,
			'label'  => $this->label,
			'from'   => $this->from,
			'to'     => $this->to,
			'yearly' => $this->yearly,
		);
	}
}
