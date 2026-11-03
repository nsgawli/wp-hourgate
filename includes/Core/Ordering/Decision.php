<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Ordering;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a shopper action may go ahead: immutable, pure PHP.
 *
 * Three outcomes: allowed, blocked (with a plain-text message) and scheduled
 * (accepted for a later time, closed mode 2). The reason says what state the
 * store is in, whatever the outcome: an add-to-cart allowed in mode 1 while
 * closed still has reason `outside_hours`.
 */
final class Decision {

	/**
	 * Outcome: go ahead.
	 *
	 * @var string
	 */
	const ALLOWED = 'allowed';

	/**
	 * Outcome: reject with the message.
	 *
	 * @var string
	 */
	const BLOCKED = 'blocked';

	/**
	 * Outcome: accept the order for `scheduled_for`.
	 *
	 * @var string
	 */
	const SCHEDULED = 'scheduled';

	/**
	 * Reason: within opening hours.
	 *
	 * @var string
	 */
	const REASON_OPEN = 'open';

	/**
	 * Reason: "Enforce opening hours" is off (and not paused).
	 *
	 * @var string
	 */
	const REASON_NOT_ENFORCED = 'not_enforced';

	/**
	 * Reason: the stored schedule is invalid, so it is treated as always open.
	 *
	 * @var string
	 */
	const REASON_FALLBACK = 'fallback';

	/**
	 * Reason: HourGate's own code failed; ordering is allowed (fail open).
	 *
	 * @var string
	 */
	const REASON_ERROR = 'error';

	/**
	 * Reason: ordering is paused.
	 *
	 * @var string
	 */
	const REASON_PAUSED = 'paused';

	/**
	 * Reason: outside the weekly or special hours.
	 *
	 * @var string
	 */
	const REASON_OUTSIDE_HOURS = 'outside_hours';

	/**
	 * Reason: today is a closure.
	 *
	 * @var string
	 */
	const REASON_CLOSURE = 'closure';

	/**
	 * Reasons that mean "closed to orders".
	 *
	 * @var string[]
	 */
	const CLOSED_REASONS = array( self::REASON_PAUSED, self::REASON_OUTSIDE_HOURS, self::REASON_CLOSURE );

	/**
	 * Outcome.
	 *
	 * @var string
	 */
	private $outcome;

	/**
	 * Reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Plain-text message, tokens replaced; '' when none.
	 *
	 * @var string
	 */
	private $message = '';

	/**
	 * When a scheduled order can be fulfilled (UTC); null unless scheduled.
	 *
	 * @var int|null
	 */
	private $scheduled_for;

	/**
	 * When ordering is next possible (UTC), for the message; null when unknown.
	 *
	 * @var int|null
	 */
	private $next_open;

	/**
	 * The closure covering now, for the message; '' otherwise.
	 *
	 * @var string
	 */
	private $closure_label;

	/**
	 * Use the named constructors.
	 *
	 * @param string   $outcome       Outcome.
	 * @param string   $reason        Reason.
	 * @param int|null $scheduled_for Scheduled time.
	 * @param int|null $next_open     Next opening.
	 * @param string   $closure_label Closure label.
	 */
	private function __construct( string $outcome, string $reason, $scheduled_for = null, $next_open = null, string $closure_label = '' ) {
		$this->outcome       = $outcome;
		$this->reason        = $reason;
		$this->scheduled_for = $scheduled_for;
		$this->next_open     = $next_open;
		$this->closure_label = $closure_label;
	}

	/**
	 * Go ahead.
	 *
	 * @param string   $reason        Reason (default `open`).
	 * @param int|null $next_open     Next opening, when closed.
	 * @param string   $closure_label Closure label, when closed by one.
	 * @return self
	 */
	public static function allowed( string $reason = self::REASON_OPEN, $next_open = null, string $closure_label = '' ) {
		return new self( self::ALLOWED, $reason, null, $next_open, $closure_label );
	}

	/**
	 * Reject. The message is added by `with_message()`.
	 *
	 * @param string   $reason        Reason.
	 * @param int|null $next_open     Next opening, or null when unknown.
	 * @param string   $closure_label Closure label.
	 * @return self
	 */
	public static function blocked( string $reason, $next_open = null, string $closure_label = '' ) {
		return new self( self::BLOCKED, $reason, null, $next_open, $closure_label );
	}

	/**
	 * Accept for later.
	 *
	 * @param string $reason        Reason.
	 * @param int    $scheduled_for When it can be fulfilled (UTC).
	 * @param string $closure_label Closure label.
	 * @return self
	 */
	public static function scheduled( string $reason, int $scheduled_for, string $closure_label = '' ) {
		return new self( self::SCHEDULED, $reason, $scheduled_for, $scheduled_for, $closure_label );
	}

	/**
	 * A copy with a plain-text message.
	 *
	 * @param string $message Message, tokens already replaced.
	 * @return self
	 */
	public function with_message( string $message ) {
		$copy          = clone $this;
		$copy->message = $message;
		return $copy;
	}

	/**
	 * Whether to go ahead.
	 *
	 * @return bool
	 */
	public function is_allowed() {
		return self::ALLOWED === $this->outcome;
	}

	/**
	 * Whether to reject.
	 *
	 * @return bool
	 */
	public function is_blocked() {
		return self::BLOCKED === $this->outcome;
	}

	/**
	 * Whether to accept for later.
	 *
	 * @return bool
	 */
	public function is_scheduled() {
		return self::SCHEDULED === $this->outcome;
	}

	/**
	 * Whether the store is closed to orders (paused, outside hours or a closure), whatever the outcome.
	 *
	 * @return bool
	 */
	public function is_closed() {
		return in_array( $this->reason, self::CLOSED_REASONS, true );
	}

	/**
	 * `allowed`, `blocked` or `scheduled`.
	 *
	 * @return string
	 */
	public function get_outcome() {
		return $this->outcome;
	}

	/**
	 * One of the `REASON_*` constants.
	 *
	 * @return string
	 */
	public function get_reason() {
		return $this->reason;
	}

	/**
	 * The plain-text message ('' when none). Escape it at output.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * When a scheduled order can be fulfilled (UTC), or null.
	 *
	 * @return int|null
	 */
	public function get_scheduled_for() {
		return $this->scheduled_for;
	}

	/**
	 * When ordering is next possible (UTC), or null when unknown.
	 *
	 * @return int|null
	 */
	public function get_next_open() {
		return $this->next_open;
	}

	/**
	 * The covering closure's label, or ''.
	 *
	 * @return string
	 */
	public function get_closure_label() {
		return $this->closure_label;
	}
}
