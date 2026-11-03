<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi;

use HourGate\Core\Ordering\PauseState;
use HourGate\Core\Pause as CorePause;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pause ordering: `GET /pause`, `POST /pause` (start), `DELETE /pause` (resume).
 *
 * Uses the manage capability (`manage_woocommerce` by default, filterable),
 * so shop managers can pause. The pause works whether or not opening hours
 * are enforced (sprint 4, decision 1). Starting and resuming fire
 * `hourgate_paused` / `hourgate_resumed` (from `Core\Pause`).
 */
class Pause extends BaseController {

	/**
	 * Route.
	 *
	 * @var string
	 */
	const ROUTE = '/pause';

	/**
	 * Register the routes on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			self::ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_pause' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'mode'    => array(
							'description'       => 'How long to pause.',
							'type'              => 'string',
							'enum'              => PauseState::MODES,
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'minutes' => array(
							'description'       => 'For `duration`: minutes, 1 to 10080.',
							'type'              => 'integer',
							'minimum'           => PauseState::MIN_MINUTES,
							'maximum'           => PauseState::MAX_MINUTES,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'until'   => array(
							'description'       => 'For `until_time`: store-local `Y-m-d H:i` or `Y-m-dTH:i`.',
							'type'              => 'string',
							'maxLength'         => 16,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'resume' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * Sanitize one arg, refusing null (WordPress skips validation for null but still sanitizes it).
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Arg name.
	 * @return mixed|\WP_Error
	 */
	public function sanitize_arg( $value, $request, $param ) {

		if ( null === $value ) {
			return new \WP_Error(
				'rest_invalid_type',
				/* translators: %s: the name of a request parameter or setting, e.g. closedMode. */
				sprintf( __( '%s must not be null.', 'hourgate' ), $param )
			);
		}

		return rest_sanitize_request_arg( $value, $request, $param );
	}

	/**
	 * The current pause.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_pause() {
		return $this->success_response( self::describe( CorePause::get() ) );
	}

	/**
	 * Start a pause now, replacing any current one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( WP_REST_Request $request ) {

		$input = array();

		foreach ( array( 'minutes', 'until' ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$input[ $key ] = $request->get_param( $key );
			}
		}

		$state = CorePause::start( (string) $request->get_param( 'mode' ), $input );

		if ( ! $state instanceof PauseState ) {
			return $this->error_response( 'hourgate_' . $state, self::error_message( (string) $state ), 400 );
		}

		return $this->success_response( self::describe( $state ) );
	}

	/**
	 * Resume. Resuming when not paused is a no-op (`resumed: false`).
	 *
	 * @return \WP_REST_Response
	 */
	public function resume() {

		$resumed = CorePause::resume();

		return $this->success_response( array_merge( self::describe( CorePause::get() ), array( 'resumed' => $resumed ) ) );
	}

	/**
	 * The pause as the API shows it.
	 *
	 * `active` is whether it is in force now (an expired pause is not);
	 * `until` is a UTC timestamp, `untilIso` the same in the store's zone.
	 *
	 * @param PauseState $state State.
	 * @return array
	 */
	public static function describe( PauseState $state ) {

		$active = $state->is_active_at( time() );
		$until  = $active ? $state->get_until() : null;

		return array(
			'active'    => $active,
			'mode'      => $active ? $state->get_mode() : null,
			'until'     => $until,
			'untilIso'  => null === $until ? null : ( new \DateTimeImmutable( '@' . $until ) )->setTimezone( wp_timezone() )->format( DATE_ATOM ),
			'startedAt' => $active ? $state->get_started_at() : null,
			'startedBy' => $active ? $state->get_started_by() : null,
		);
	}

	/**
	 * A translated message for a `PauseState::ERROR_*` code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function error_message( $code ) {

		switch ( $code ) {
			case PauseState::ERROR_INVALID_MODE:
				return __( 'Choose how long to pause ordering.', 'hourgate' );
			case PauseState::ERROR_MISSING_MINUTES:
			case PauseState::ERROR_INVALID_MINUTES:
				return __( 'Pause for 1 minute to 7 days.', 'hourgate' );
			case PauseState::ERROR_MISSING_UNTIL:
			case PauseState::ERROR_INVALID_UNTIL:
				return __( 'Enter the date and time ordering should resume.', 'hourgate' );
			case PauseState::ERROR_UNTIL_IN_PAST:
				return __( 'The time ordering resumes must be in the future.', 'hourgate' );
			case PauseState::ERROR_UNTIL_TOO_FAR:
				return __( 'Ordering can be paused for at most 400 days.', 'hourgate' );
			case PauseState::ERROR_NO_NEXT_OPENING:
				return __( 'The store has no opening in the schedule after the current one, so it can’t pause until the next opening.', 'hourgate' );
			default:
				return __( 'Ordering could not be paused.', 'hourgate' );
		}
	}
}
