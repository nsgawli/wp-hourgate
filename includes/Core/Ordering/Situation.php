<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Ordering;

use HourGate\Core\Schedule\DayPlan;
use HourGate\Core\Schedule\Engine;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store's ordering state at one instant, and the rules that turn it into
 * a `Decision` for a closed mode and a context. Pure PHP, so the whole
 * decision table is unit-tested; `Core\OrderingPolicy` feeds it from WordPress.
 *
 * Order (sprint 4, OW-028): paused → closed whatever the switch says
 * (decision 1); else enforcement off → open; else an invalid stored schedule
 * → open (its always-open fallback); else the schedule decides, and a closed
 * date ruled by a closure is reason `closure` rather than `outside_hours`.
 */
final class Situation {

	/**
	 * Context: completing an order (checkout, Store API checkout).
	 *
	 * @var string
	 */
	const CONTEXT_CHECKOUT = 'checkout';

	/**
	 * Context: adding a product to the cart.
	 *
	 * @var string
	 */
	const CONTEXT_ADD_TO_CART = 'add_to_cart';

	/**
	 * Every context.
	 *
	 * @var string[]
	 */
	const CONTEXTS = array( self::CONTEXT_CHECKOUT, self::CONTEXT_ADD_TO_CART );

	/**
	 * A `Decision::REASON_*`.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * When an order placed now could be fulfilled (closed mode 2), or null when never within the horizon.
	 *
	 * @var int|null
	 */
	private $scheduled_for;

	/**
	 * When ordering is next possible, for the message; null when unknown.
	 *
	 * @var int|null
	 */
	private $next_open;

	/**
	 * The closure covering now, or ''.
	 *
	 * @var string
	 */
	private $closure_label;

	/**
	 * Use `assess()`, or this directly in tests.
	 *
	 * @param string   $reason        Reason.
	 * @param int|null $scheduled_for Scheduled time for mode 2.
	 * @param int|null $next_open     Next opening for the message.
	 * @param string   $closure_label Closure label.
	 */
	public function __construct( string $reason, $scheduled_for = null, $next_open = null, string $closure_label = '' ) {
		$this->reason        = $reason;
		$this->scheduled_for = $scheduled_for;
		$this->next_open     = $next_open;
		$this->closure_label = $closure_label;
	}

	/**
	 * Work out the state at `$now`.
	 *
	 * While paused, an order in mode 2 is scheduled for:
	 * - a pause with an end: the end, moved to the next opening when hours
	 *   are enforced and the store is closed then (so an order is never
	 *   scheduled into closed hours);
	 * - an open-ended pause: the schedule's first opening after the current window.
	 *
	 * @param PauseState    $pause    Pause.
	 * @param bool          $enforced "Enforce opening hours".
	 * @param bool          $fallback Whether `$engine` is the always-open fallback for an invalid stored schedule.
	 * @param Engine        $engine   The store's schedule.
	 * @param \DateTimeZone $timezone The store's zone (for today's local date).
	 * @param int           $now      UTC timestamp.
	 * @return self
	 */
	public static function assess( PauseState $pause, bool $enforced, bool $fallback, Engine $engine, \DateTimeZone $timezone, int $now ) {

		if ( $pause->is_active_at( $now ) ) {
			$until = $pause->get_until();

			if ( null === $until ) {
				return new self( Decision::REASON_PAUSED, PauseState::next_opening( $engine, $now ), null );
			}

			$resume = $enforced && ! $fallback ? $engine->next_open( $until ) : $until;

			return new self( Decision::REASON_PAUSED, $resume, $resume );
		}

		if ( ! $enforced ) {
			return new self( Decision::REASON_NOT_ENFORCED );
		}

		if ( $fallback ) {
			return new self( Decision::REASON_FALLBACK );
		}

		if ( $engine->is_open( $now ) ) {
			return new self( Decision::REASON_OPEN );
		}

		$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $timezone )->format( 'Y-m-d' );
		$plan  = $engine->day_plan( $today );
		$next  = $engine->next_open( $now );

		if ( DayPlan::RULE_CLOSURE === $plan->get_rule() ) {
			return new self( Decision::REASON_CLOSURE, $next, $next, trim( $plan->get_label() ) );
		}

		return new self( Decision::REASON_OUTSIDE_HOURS, $next, $next );
	}

	/**
	 * The decision for a closed mode and a context:
	 * - open (or not enforced, or fallback): allowed;
	 * - closed, `catalogue`: add-to-cart and checkout blocked;
	 * - closed, `block`: checkout blocked, add-to-cart allowed;
	 * - closed, `schedule`: add-to-cart allowed; checkout scheduled for the
	 *   next possible time, or blocked when there is none (decision 4).
	 *
	 * An unknown mode is treated as `block`, an unknown context as checkout.
	 *
	 * @param string $mode    Closed mode.
	 * @param string $context One of `CONTEXTS`.
	 * @return Decision
	 */
	public function decide( string $mode, string $context ) {

		if ( ! in_array( $this->reason, Decision::CLOSED_REASONS, true ) ) {
			return Decision::allowed( $this->reason );
		}

		$checkout = self::CONTEXT_ADD_TO_CART !== $context;

		if ( BehaviourSettings::MODE_CATALOGUE === $mode || ( $checkout && BehaviourSettings::MODE_SCHEDULE !== $mode ) ) {
			return Decision::blocked( $this->reason, $this->next_open, $this->closure_label );
		}

		if ( ! $checkout ) {
			return Decision::allowed( $this->reason, $this->next_open, $this->closure_label );
		}

		if ( null === $this->scheduled_for ) {
			return Decision::blocked( $this->reason, $this->next_open, $this->closure_label );
		}

		return Decision::scheduled( $this->reason, $this->scheduled_for, $this->closure_label );
	}

	/**
	 * A `Decision::REASON_*`.
	 *
	 * @return string
	 */
	public function get_reason() {
		return $this->reason;
	}

	/**
	 * When an order placed now could be fulfilled, or null.
	 *
	 * @return int|null
	 */
	public function get_scheduled_for() {
		return $this->scheduled_for;
	}

	/**
	 * When ordering is next possible, or null.
	 *
	 * @return int|null
	 */
	public function get_next_open() {
		return $this->next_open;
	}

	/**
	 * The closure covering now, or ''.
	 *
	 * @return string
	 */
	public function get_closure_label() {
		return $this->closure_label;
	}
}
