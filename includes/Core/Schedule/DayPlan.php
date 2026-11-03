<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One local date's plan: the rule that won, its ranges and the resulting UTC
 * windows. The schedule preview (sprint 3) shows it, with the rule as the
 * "why" column (09 §11). Immutable.
 */
final class DayPlan {

	/**
	 * Weekly hours apply.
	 *
	 * @var string
	 */
	const RULE_WEEKLY = 'weekly';

	/**
	 * A special-hours entry replaces the weekly hours.
	 *
	 * @var string
	 */
	const RULE_SPECIAL = 'special';

	/**
	 * A closure closes the whole date.
	 *
	 * @var string
	 */
	const RULE_CLOSURE = 'closure';

	/**
	 * Local date, `Y-m-d`.
	 *
	 * @var string
	 */
	private $date;

	/**
	 * One of the RULE_* constants.
	 *
	 * @var string
	 */
	private $rule;

	/**
	 * Id of the special-hours entry or closure; null for weekly hours.
	 *
	 * @var string|null
	 */
	private $rule_id;

	/**
	 * Label of the special-hours entry or closure; empty for weekly hours.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * The date's own local ranges (empty for a closure).
	 *
	 * @var TimeRange[]
	 */
	private $ranges;

	/**
	 * UTC windows of those ranges, after the overnight and DST rules.
	 *
	 * @var Window[]
	 */
	private $windows;

	/**
	 * Create a day plan.
	 *
	 * @param string      $date    Local date, `Y-m-d`.
	 * @param string      $rule    RULE_* constant.
	 * @param string|null $rule_id Id of the winning entry, or null for weekly hours.
	 * @param string      $label   Label of the winning entry.
	 * @param TimeRange[] $ranges  The date's ranges.
	 * @param Window[]    $windows Their UTC windows.
	 */
	public function __construct( string $date, string $rule, $rule_id, string $label, array $ranges, array $windows ) {
		$this->date    = $date;
		$this->rule    = $rule;
		$this->rule_id = $rule_id;
		$this->label   = $label;
		$this->ranges  = $ranges;
		$this->windows = $windows;
	}

	/**
	 * Local date, `Y-m-d`.
	 *
	 * @return string
	 */
	public function get_date() {
		return $this->date;
	}

	/**
	 * The winning rule type: `weekly`, `special` or `closure`.
	 *
	 * @return string
	 */
	public function get_rule() {
		return $this->rule;
	}

	/**
	 * Id of the winning special-hours entry or closure; null for weekly hours.
	 *
	 * @return string|null
	 */
	public function get_rule_id() {
		return $this->rule_id;
	}

	/**
	 * The winning rule as one key: `weekly`, `special:{id}` or `closure:{id}`.
	 *
	 * @return string
	 */
	public function get_rule_key() {
		return null === $this->rule_id ? $this->rule : $this->rule . ':' . $this->rule_id;
	}

	/**
	 * Label of the winning entry; empty for weekly hours or an unlabelled entry.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * The date's own local ranges, sorted by start. Empty when closed.
	 *
	 * @return TimeRange[]
	 */
	public function get_ranges() {
		return $this->ranges;
	}

	/**
	 * UTC windows of the date's own ranges, sorted by start. A range running
	 * past midnight ends on the next date (or at midnight, when the next date
	 * is a closure). The previous night's spill-over is not included.
	 *
	 * @return Window[]
	 */
	public function get_windows() {
		return $this->windows;
	}
}
