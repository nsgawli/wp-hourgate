<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Core\Ordering\PauseState;
use HourGate\Core\Schedule\LocalClock;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pause of ordering in WordPress: option `hourgate_pause_state`
 * over the pure `Ordering\PauseState`.
 *
 * The option is its own, outside the Behaviour group, because the admin bar
 * and the Settings screen change it without a form save; a settings PUT must
 * never overwrite it. It is autoloaded and always present (installed as "not
 * paused", and resuming writes that state back rather than deleting the
 * row), because a missing option costs a query on every request without a
 * persistent object cache.
 *
 * Auto-resume is lazy: an expired pause reads as not paused and stays stored
 * until the next write. No cron event exists.
 */
class Pause {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'hourgate_pause_state';

	/**
	 * The memoized state.
	 *
	 * @var PauseState|null
	 */
	private static $state = null;

	/**
	 * Register the hooks that keep the memo fresh. Called from `Core\Autoloader`; no queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
	}

	/**
	 * Install the "not paused" state, so the option always exists. Never overwrites a pause.
	 *
	 * @return void
	 */
	public static function install() {
		add_option( self::OPTION, PauseState::none()->to_array(), '', true );
	}

	/**
	 * Forget the memoized state.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$state = null;
	}

	/**
	 * The stored state, memoized; a corrupt option reads as not paused.
	 *
	 * @return PauseState
	 */
	public static function get() {

		if ( null === self::$state ) {
			self::$state = PauseState::from_array( get_option( self::OPTION, null ) );
		}

		return self::$state;
	}

	/**
	 * Whether ordering is paused at an instant (default: now).
	 *
	 * @param int|null $ts UTC timestamp, or null for now.
	 * @return bool
	 */
	public static function is_paused( $ts = null ) {
		return self::get()->is_active_at( null === $ts ? time() : (int) $ts );
	}

	/**
	 * Start a pause now, replacing any current one. Fires `hourgate_paused`.
	 *
	 * @param string   $mode    A `PauseState::MODE_*`.
	 * @param array    $input   `minutes` (int) for `duration`; `until` (store-local `Y-m-d H:i`) for `until_time`.
	 * @param int|null $user_id Who starts it; default the current user.
	 * @return PauseState|string The stored state, or a `PauseState::ERROR_*` code (nothing stored).
	 */
	public static function start( $mode, array $input = array(), $user_id = null ) {

		$state = PauseState::start(
			$mode,
			$input,
			time(),
			null === $user_id ? get_current_user_id() : (int) $user_id,
			StoreSchedule::engine(),
			new LocalClock( wp_timezone() )
		);

		if ( ! $state instanceof PauseState ) {
			return $state;
		}

		update_option( self::OPTION, $state->to_array(), true );

		/**
		 * Fires after ordering is paused (or a pause is replaced by a new one).
		 *
		 * @param array $state The stored state: `{ active, until, mode, startedAt, startedBy }`.
		 */
		do_action( 'hourgate_paused', $state->to_array() );

		return $state;
	}

	/**
	 * Resume ordering. Fires `hourgate_resumed` only when a pause was
	 * in force; resuming when not paused (or after a pause expired) is a no-op
	 * apart from clearing the expired row.
	 *
	 * @return bool Whether a pause in force was ended.
	 */
	public static function resume() {

		$state = self::get();

		if ( ! $state->is_started() ) {
			return false;
		}

		$was_active = $state->is_active_at( time() );

		update_option( self::OPTION, PauseState::none()->to_array(), true );

		if ( $was_active ) {
			/**
			 * Fires after a pause in force is ended by hand.
			 */
			do_action( 'hourgate_resumed' );
		}

		return $was_active;
	}
}
