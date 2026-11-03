<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Core\Ordering\Decision;
use HourGate\Core\Ordering\PauseState;
use HourGate\Core\Ordering\Situation;
use HourGate\RestApi\Pause as PauseRoute;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "HourGate" admin-bar node (OW-032): the ordering state, and one-click
 * pause and resume, on the front end and in admin. No JS and no asset.
 *
 * Each item is a link to `admin-post.php` with a nonce tied to that exact
 * action and the user. A GET link that changes state is acceptable here
 * because admin-bar items can't be forms without JS, and every handler checks
 * the capability and the nonce. Links under `/wp-admin/` are never
 * speculatively loaded by WordPress (and it doesn't speculate for logged-in
 * users at all); the links also carry `rel="nofollow"` and `no-prefetch`.
 *
 * Only users with the manage capability see the node. Nothing runs for
 * shoppers: `admin_bar_menu` only fires when the admin bar renders.
 */
class AdminBar {

	/**
	 * Node ID.
	 *
	 * @var string
	 */
	const NODE = 'hourgate';

	/**
	 * `admin-post.php` action that pauses.
	 *
	 * @var string
	 */
	const ACTION_PAUSE = 'hourgate_pause';

	/**
	 * `admin-post.php` action that resumes.
	 *
	 * @var string
	 */
	const ACTION_RESUME = 'hourgate_resume';

	/**
	 * Register the hooks. No queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'admin_bar_menu', array( __CLASS__, 'add_node' ), 100, 1 );
		add_action( 'admin_post_' . self::ACTION_PAUSE, array( __CLASS__, 'handle_pause' ) );
		add_action( 'admin_post_' . self::ACTION_RESUME, array( __CLASS__, 'handle_resume' ) );
	}

	/**
	 * The pause presets: `[ id, label, mode, minutes ]`.
	 *
	 * @return array[]
	 */
	private static function presets() {
		return array(
			array( '30m', __( 'Pause 30 minutes', 'hourgate' ), PauseState::MODE_DURATION, 30 ),
			array( '1h', __( 'Pause 1 hour', 'hourgate' ), PauseState::MODE_DURATION, 60 ),
			array( '2h', __( 'Pause 2 hours', 'hourgate' ), PauseState::MODE_DURATION, 120 ),
			array( 'today', __( 'Pause for the rest of today', 'hourgate' ), PauseState::MODE_END_OF_DAY, 0 ),
			array( 'next', __( 'Pause until the next opening', 'hourgate' ), PauseState::MODE_NEXT_OPENING, 0 ),
			array( 'resumed', __( 'Pause until resumed', 'hourgate' ), PauseState::MODE_UNTIL_RESUMED, 0 ),
		);
	}

	/**
	 * Nonce action for one preset: the mode and its minutes.
	 *
	 * @param string $mode    Mode.
	 * @param int    $minutes Minutes (0 when the mode has none).
	 * @return string
	 */
	private static function nonce_action( $mode, $minutes ) {
		return self::ACTION_PAUSE . ':' . $mode . ':' . (int) $minutes;
	}

	/**
	 * Add the node and its items.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function add_node( $bar ) {

		if ( ! $bar instanceof \WP_Admin_Bar || ! Capabilities::current_user_can_manage() ) {
			return;
		}

		try {
			$decision = OrderingPolicy::check( Situation::CONTEXT_CHECKOUT );
			$pause    = Pause::get();
			$now      = time();
			$paused   = $pause->is_active_at( $now );

			$bar->add_node(
				array(
					'id'    => self::NODE,
					'title' => esc_html( self::title( $decision, $pause, $paused ) ),
					'href'  => admin_url( 'admin.php?page=hourgate-settings#/ordering' ),
				)
			);

			$link_meta = array(
				'rel'   => 'nofollow',
				'class' => 'no-prefetch',
			);

			if ( $paused ) {
				$bar->add_node(
					array(
						'parent' => self::NODE,
						'id'     => self::NODE . '-resume',
						'title'  => esc_html__( 'Resume ordering', 'hourgate' ),
						'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_RESUME ), self::ACTION_RESUME ),
						'meta'   => $link_meta,
					)
				);
			}

			foreach ( self::presets() as list( $id, $label, $mode, $minutes ) ) {
				// "Until the next opening" only when there is one to wait for.
				if ( PauseState::MODE_NEXT_OPENING === $mode && null === PauseState::next_opening( StoreSchedule::engine(), $now ) ) {
					continue;
				}

				$url = add_query_arg(
					array_filter(
						array(
							'action'  => self::ACTION_PAUSE,
							'mode'    => $mode,
							'minutes' => $minutes ? $minutes : null,
						)
					),
					admin_url( 'admin-post.php' )
				);

				$bar->add_node(
					array(
						'parent' => self::NODE,
						'id'     => self::NODE . '-pause-' . $id,
						'title'  => esc_html( $label ),
						'href'   => wp_nonce_url( $url, self::nonce_action( $mode, $minutes ) ),
						'meta'   => $link_meta,
					)
				);
			}
		} catch ( \Throwable $e ) {
			// The admin bar must never break a page.
			OrderingPolicy::report( $e );
		}
	}

	/**
	 * The node title: the ordering state, never any stored text.
	 *
	 * @param Decision   $decision Checkout decision now.
	 * @param PauseState $pause    Pause.
	 * @param bool       $paused   Whether paused now.
	 * @return string Plain text.
	 */
	private static function title( Decision $decision, PauseState $pause, $paused ) {

		if ( $paused ) {
			$until = $pause->get_until();

			if ( null === $until ) {
				return __( 'HourGate: Paused', 'hourgate' );
			}

			/* translators: %s: when ordering resumes, e.g. "2:30 pm" today or "Tue 9:00 am". */
			return sprintf( __( 'HourGate: Paused until %s', 'hourgate' ), OrderingPolicy::format_when( $until ) );
		}

		return $decision->is_closed()
			? __( 'HourGate: Closed', 'hourgate' )
			: __( 'HourGate: Open', 'hourgate' );
	}

	/**
	 * `admin-post.php?action=hourgate_pause`: capability, nonce, validation, then back.
	 *
	 * @return void
	 */
	public static function handle_pause() {

		self::require_capability();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is checked below, against an action built from these same values.
		$mode    = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
		$minutes = isset( $_GET['minutes'] ) ? (string) wp_unslash( $_GET['minutes'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only digits are accepted just below.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$minutes = preg_match( '/^[1-9]\d{0,5}$/D', $minutes ) ? (int) $minutes : 0;

		check_admin_referer( self::nonce_action( $mode, $minutes ) );

		$input = PauseState::MODE_DURATION === $mode ? array( 'minutes' => $minutes ? $minutes : null ) : array();
		$state = Pause::start( $mode, $input );

		if ( ! $state instanceof PauseState ) {
			wp_die(
				esc_html( PauseRoute::error_message( (string) $state ) ),
				esc_html__( 'Ordering not paused', 'hourgate' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		self::redirect_back();
	}

	/**
	 * `admin-post.php?action=hourgate_resume`: capability, nonce, then back.
	 *
	 * @return void
	 */
	public static function handle_resume() {

		self::require_capability();
		check_admin_referer( self::ACTION_RESUME );

		Pause::resume();

		self::redirect_back();
	}

	/**
	 * Stop with 403 unless the user has the manage capability.
	 *
	 * @return void
	 */
	private static function require_capability() {

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to pause ordering.', 'hourgate' ),
				esc_html__( 'Not allowed', 'hourgate' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Back to the page the link was clicked on (same site only), else the Ordering tab.
	 *
	 * @return void
	 */
	private static function redirect_back() {

		$referer = wp_get_referer();

		wp_safe_redirect( $referer ? $referer : admin_url( 'admin.php?page=hourgate-settings#/ordering' ) );
		exit;
	}
}
