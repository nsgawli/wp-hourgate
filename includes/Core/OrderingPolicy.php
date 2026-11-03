<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Core\Ordering\Decision;
use HourGate\Core\Ordering\Situation;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;
use HourGate\Installation\Settings\Closures as ClosuresSettings;
use HourGate\Installation\Settings\Schedule as ScheduleSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether shoppers may order right now: the one decision every enforcement
 * hook asks (`check( $context )`).
 *
 * It reads the pause, the Behaviour settings and the schedule, and leaves the
 * rules to the pure `Ordering\Situation`. The result is computed on first use
 * and memoized for the request only, never stored: a transient or object
 * cache could serve a stale open/closed answer. Any change to the inputs
 * resets it.
 *
 * It fails open: if its own code throws, ordering is allowed and the error is
 * logged (WooCommerce logger, source `hourgate`, once a day per
 * distinct problem). A store must never lose sales or get a fatal because of
 * HourGate.
 */
class OrderingPolicy {

	/**
	 * Transient holding a hash of the last problem logged.
	 *
	 * @var string
	 */
	const LOGGED_TRANSIENT = 'hourgate_policy_error_logged';

	/**
	 * The memoized state.
	 *
	 * @var Situation|null
	 */
	private static $situation = null;

	/**
	 * Memoized decisions by context.
	 *
	 * @var Decision[]
	 */
	private static $decisions = array();

	/**
	 * Register the hooks that keep the memo fresh. Called from `Core\Autoloader`; no queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		$options = array(
			BehaviourSettings::OPTION,
			Pause::OPTION,
			ScheduleSettings::OPTION,
			ClosuresSettings::OPTION,
			'timezone_string',
			'gmt_offset',
			'date_format',
			'time_format',
		);

		foreach ( $options as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
			add_action( 'delete_option_' . $option, array( __CLASS__, 'flush' ), 10, 0 );
		}
	}

	/**
	 * Forget the memoized decisions.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$situation = null;
		self::$decisions = array();
	}

	/**
	 * The decision for a shopper action now.
	 *
	 * Filtered by `hourgate_is_ordering_allowed`. A blocked decision
	 * carries the closed message as plain text: escape it at output.
	 *
	 * @param string $context `checkout` (default) or `add_to_cart`; anything else counts as `checkout`.
	 * @return Decision
	 */
	public static function check( $context = Situation::CONTEXT_CHECKOUT ) {

		if ( ! in_array( $context, Situation::CONTEXTS, true ) ) {
			$context = Situation::CONTEXT_CHECKOUT;
		}

		if ( isset( self::$decisions[ $context ] ) ) {
			return self::$decisions[ $context ];
		}

		try {
			$decision = self::situation()->decide( Behaviour::closed_mode(), $context );

			if ( $decision->is_blocked() ) {
				$decision = $decision->with_message( self::message( $decision ) );
			}
		} catch ( \Throwable $e ) {
			self::log_exception( $e );
			$decision = Decision::allowed( Decision::REASON_ERROR );
		}

		/**
		 * Filters whether shoppers may order now.
		 *
		 * Return a `Decision` (`Decision::allowed()`, `blocked()` or
		 * `scheduled()`); a blocked one should carry a plain-text message
		 * (`with_message()`). Any other return is ignored and logged.
		 *
		 * @param Decision $decision The decision.
		 * @param string   $context  `checkout` or `add_to_cart`.
		 */
		try {
			$filtered = apply_filters( 'hourgate_is_ordering_allowed', $decision, $context );

			if ( $filtered instanceof Decision ) {
				$decision = $filtered;
			} else {
				self::log(
					sprintf(
						/* translators: %s: PHP type or class name, e.g. boolean. */
						__( 'A hourgate_is_ordering_allowed filter returned %s instead of a Decision, so HourGate ignored it.', 'hourgate' ),
						is_object( $filtered ) ? get_class( $filtered ) : gettype( $filtered )
					)
				);
			}
		} catch ( \Throwable $e ) {
			// A filter callback that throws is ignored like a bad return value.
			self::log_exception( $e );
		}

		self::$decisions[ $context ] = $decision;

		return $decision;
	}

	/**
	 * The memoized state now.
	 *
	 * When ordering isn't paused and hours aren't enforced the schedule is
	 * never built, so a store that hasn't turned enforcement on pays only for
	 * the two autoloaded option reads.
	 *
	 * @return Situation
	 */
	private static function situation() {

		if ( null !== self::$situation ) {
			return self::$situation;
		}

		$now      = time();
		$pause    = Pause::get();
		$enforced = Behaviour::is_enforced();

		if ( ! $enforced && ! $pause->is_active_at( $now ) ) {
			self::$situation = new Situation( Decision::REASON_NOT_ENFORCED );
		} else {
			self::$situation = Situation::assess( $pause, $enforced, StoreSchedule::is_fallback(), StoreSchedule::engine(), wp_timezone(), $now );
		}

		return self::$situation;
	}

	/**
	 * The closed message with its tokens replaced, plain text:
	 * `{next_open}` is the next opening in the site's date and time format
	 * and zone ("later" when unknown); `{closures_label}` is the covering
	 * closure's label, or nothing. Other text in braces stays as typed.
	 *
	 * @param Decision $decision Decision.
	 * @return string
	 */
	public static function message( Decision $decision ) {

		return self::fill( Behaviour::closed_message(), self::tokens( $decision->get_next_open(), $decision->get_closure_label() ) );
	}

	/**
	 * The token values for a message: `{ next_open, closures_label }`, plain
	 * text, plus `closes_at` when `$closes_at` is given (text shown while
	 * open, such as the status block's; OW-036).
	 *
	 * @param int|null $next_open     Next opening (UTC), or null ("later").
	 * @param string   $closure_label Covering closure's label, or ''.
	 * @param int|null $closes_at     When the store next closes (UTC), short form (`format_when()`); null leaves `{closes_at}` as typed.
	 * @return array
	 */
	public static function tokens( $next_open, $closure_label, $closes_at = null ) {

		$tokens = array(
			'next_open'      => null === $next_open ? __( 'later', 'hourgate' ) : self::format_time( (int) $next_open ),
			'closures_label' => (string) $closure_label,
		);

		if ( null !== $closes_at ) {
			$tokens['closes_at'] = self::format_when( (int) $closes_at );
		}

		return $tokens;
	}

	/**
	 * A message template with its tokens replaced; other braces stay; trimmed.
	 * `{closes_at}` is replaced only when `$tokens` has it, so it stays as
	 * typed in the closed message (which shows while closed). The same goes
	 * for `{scheduled_for}` (an order's scheduled time, `format_time()`; the
	 * scheduled-order note, OW-062). One pass (`strtr()`), so a replaced
	 * value is never read again for tokens.
	 *
	 * @param string $template Plain-text template.
	 * @param array  $tokens   From `tokens()`, optionally with `scheduled_for`.
	 * @return string
	 */
	public static function fill( $template, array $tokens ) {

		$pairs = array(
			'{next_open}'      => (string) $tokens['next_open'],
			'{closures_label}' => (string) $tokens['closures_label'],
		);

		if ( isset( $tokens['closes_at'] ) ) {
			$pairs['{closes_at}'] = (string) $tokens['closes_at'];
		}

		if ( isset( $tokens['scheduled_for'] ) ) {
			$pairs['{scheduled_for}'] = (string) $tokens['scheduled_for'];
		}

		return trim( strtr( (string) $template, $pairs ) );
	}

	/**
	 * What the storefront shows (OW-036): the public `GET /status` body, and
	 * what the PHP renderers (status and hours blocks, shortcodes) print, so
	 * a page rendered by PHP and the same page refreshed by the status script
	 * agree. Only what a shopper may see: never who paused, the pause mode,
	 * settings, raw schedule data or closure ids.
	 *
	 * - `state`: `open`, `closed` or `paused`, from the checkout decision
	 *   (`closed` too when a filter blocks checkout while the hours are open);
	 * - `checkout`: `allowed`, `blocked` or `scheduled`; `addToCart`: `allowed` or `blocked`;
	 * - `addToCartLabel`: while add-to-cart is blocked, the label the status
	 *   script puts on add-to-cart buttons ("Closed", OW-039); null otherwise;
	 * - `bar`: whether the notice bar setting is on (OW-038), so a bar on a cached page hides when the owner turns it off;
	 * - `message`: the closed message, filled, plain text; null while open;
	 * - `closesAtLabel`: while open by the hours, when they next close
	 *   (`format_when()`); null when they don't close within the look-ahead,
	 *   while hours aren't enforced, and with the always-open fallback;
	 * - `nextOpenLabel`: while closed, when ordering is next possible; null when unknown;
	 * - `scheduledForLabel`: in closed mode 2, when an order placed now is scheduled for;
	 * - `version`: a short hash of the state and the message (the notice bar's dismissal key);
	 * - `hours` (only with `$include_hours`): `OpeningHours::build()`.
	 *
	 * @param bool $include_hours Whether to add `hours`.
	 * @return array
	 */
	public static function shopper_status( $include_hours = false ) {

		$checkout = self::check( Situation::CONTEXT_CHECKOUT );
		$cart     = self::check( Situation::CONTEXT_ADD_TO_CART );
		$closed   = $checkout->is_closed() || $checkout->is_blocked();

		if ( Decision::REASON_PAUSED === $checkout->get_reason() ) {
			$state = 'paused';
		} else {
			$state = $closed ? 'closed' : 'open';
		}

		$message = null;

		if ( $closed ) {
			$message = $checkout->is_blocked() && '' !== $checkout->get_message() ? $checkout->get_message() : self::message( $checkout );
		}

		$closes_at = null;

		if ( ! $closed && Decision::REASON_OPEN === $checkout->get_reason() ) {
			$closes_at = StoreSchedule::engine()->next_close( time() );
		}

		$next_open     = $closed ? $checkout->get_next_open() : null;
		$scheduled_for = $checkout->get_scheduled_for();

		$status = array(
			'state'             => $state,
			'checkout'          => $checkout->get_outcome(),
			'addToCart'         => $cart->is_blocked() ? Decision::BLOCKED : Decision::ALLOWED,
			'addToCartLabel'    => $cart->is_blocked() ? _x( 'Closed', 'add-to-cart button while the store is closed', 'hourgate' ) : null,
			'bar'               => Behaviour::notice_bar(),
			'message'           => $message,
			'closesAtLabel'     => null === $closes_at ? null : self::format_when( $closes_at ),
			'nextOpenLabel'     => null === $next_open ? null : self::format_when( (int) $next_open ),
			'scheduledForLabel' => null === $scheduled_for ? null : self::format_when( (int) $scheduled_for ),
		);

		$status['version'] = substr( md5( (string) wp_json_encode( array( $status['state'], $status['checkout'], $status['addToCart'], $status['message'] ) ) ), 0, 12 );

		if ( $include_hours ) {
			$status['hours'] = OpeningHours::build();
		}

		return $status;
	}

	/**
	 * An instant in the site's date and time format, zone and locale.
	 *
	 * @param int $ts UTC timestamp.
	 * @return string
	 */
	public static function format_time( int $ts ) {

		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );

		return (string) wp_date( ( '' === $date_format ? 'F j, Y' : $date_format ) . ' ' . ( '' === $time_format ? 'g:i a' : $time_format ), $ts );
	}

	/**
	 * A short "when" for status text: the time alone today, the weekday and
	 * time within the next six days, else the full date and time.
	 *
	 * @param int $ts UTC timestamp.
	 * @return string
	 */
	public static function format_when( int $ts ) {

		$time_format = (string) get_option( 'time_format' );
		$time_format = '' === $time_format ? 'g:i a' : $time_format;
		$day         = wp_date( 'Y-m-d', $ts );
		$today       = wp_date( 'Y-m-d' );

		if ( $day === $today ) {
			return (string) wp_date( $time_format, $ts );
		}

		$days_ahead = (int) ( new \DateTimeImmutable( $today ) )->diff( new \DateTimeImmutable( $day ) )->format( '%r%a' );

		if ( $days_ahead > 0 && $days_ahead < 7 ) {
			return (string) wp_date( 'D ' . $time_format, $ts );
		}

		return self::format_time( $ts );
	}

	/**
	 * Log an exception from HourGate's ordering code, once a day per
	 * distinct problem, so a hook can fail open without hiding the error.
	 *
	 * @param \Throwable $e Exception.
	 * @return void
	 */
	public static function report( \Throwable $e ) {
		self::log(
			sprintf(
				/* translators: %s: technical details of the error. */
				__( 'HourGate hit an error in its ordering code and let the order through unchanged. Details: %s', 'hourgate' ),
				get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
			)
		);
	}

	/**
	 * Log an exception from building the shopper status (`GET /status`),
	 * once a day per distinct problem. The storefront keeps what it shows.
	 *
	 * @param \Throwable $e Exception.
	 * @return void
	 */
	public static function report_status( \Throwable $e ) {
		self::log(
			sprintf(
				/* translators: %s: technical details of the error. */
				__( 'HourGate could not build the store status for shoppers, so the storefront kept what it showed. Ordering itself is unaffected. Details: %s', 'hourgate' ),
				get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
			)
		);
	}

	/**
	 * Log an exception from showing the scheduled time on an order (OW-043),
	 * once a day per distinct problem. The order is shown without the row.
	 *
	 * @param \Throwable $e Exception.
	 * @return void
	 */
	public static function report_display( \Throwable $e ) {
		self::log(
			sprintf(
				/* translators: %s: technical details of the error. */
				__( 'HourGate could not show the scheduled time on an order, so the order was shown without it. Details: %s', 'hourgate' ),
				get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
			)
		);
	}

	/**
	 * Log an exception from the decision (ours or a filter callback's).
	 *
	 * @param \Throwable $e Exception.
	 * @return void
	 */
	private static function log_exception( \Throwable $e ) {
		self::log(
			sprintf(
				/* translators: %s: technical details of the error. */
				__( 'HourGate could not decide whether the store is open, so it allowed ordering. Details: %s', 'hourgate' ),
				get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
			)
		);
	}

	/**
	 * Log a problem once a day per distinct text. Never includes shopper data.
	 *
	 * @param string $message What went wrong.
	 * @return void
	 */
	private static function log( $message ) {

		try {
			$hash = md5( $message );

			if ( get_transient( self::LOGGED_TRANSIENT ) === $hash ) {
				return;
			}

			set_transient( self::LOGGED_TRANSIENT, $hash, DAY_IN_SECONDS );

			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( $message, array( 'source' => StoreSchedule::LOG_SOURCE ) );
			}
		} catch ( \Throwable $e ) {
			// Logging must never break checkout.
			return;
		}
	}
}
