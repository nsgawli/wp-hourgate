<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Schedule;

use HourGate\Core\Schedule\Schedule;
use HourGate\Core\Schedule\ValidationError;
use HourGate\RestApi\BaseController;
use WP_Error;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared GET / PUT / defaults routes for a schedule settings group
 * (`Settings\Schedule`, `Settings\Closures`).
 *
 * PUT validates the whole resulting group with `ScheduleFactory`. Top-level
 * keys not sent keep their stored value; unknown keys are dropped; nothing is
 * stored when there is any error. What is stored is the factory's normalized
 * output (ranges sorted, labels trimmed, generated ids kept).
 *
 * The nested values deliberately have no WordPress `type` schema: WordPress's
 * array sanitizer would reindex objects into lists and split comma-separated
 * strings, hiding mistakes. `ScheduleFactory` checks every type instead, and
 * every problem comes back in one format: 400 `hourgate_invalid_schedule`
 * with `data.errors: [ { path, code, message } ]`.
 */
abstract class SettingsController extends BaseController {

	/**
	 * Error code for an invalid schedule.
	 *
	 * @var string
	 */
	const INVALID_CODE = 'hourgate_invalid_schedule';

	/**
	 * Route, e.g. `/settings/schedule`.
	 *
	 * @return string
	 */
	abstract protected function route();

	/**
	 * Option name.
	 *
	 * @return string
	 */
	abstract protected function option();

	/**
	 * Group name for whole-request errors: `schedule` or `closures`.
	 *
	 * @return string
	 */
	abstract protected function group();

	/**
	 * The group's defaults; their keys are the only keys accepted.
	 *
	 * @return array
	 */
	abstract protected function defaults();

	/**
	 * The stored group, merged with its defaults.
	 *
	 * @return array
	 */
	abstract protected function stored();

	/**
	 * Validate the group on its own (the other group at its defaults).
	 *
	 * @param array $group The group to validate.
	 * @return Schedule|ValidationError[]
	 */
	abstract protected function build( array $group );

	/**
	 * This group's normalized arrays from a validated schedule.
	 *
	 * @param Schedule $schedule Schedule.
	 * @return array
	 */
	abstract protected function normalized( Schedule $schedule );

	/**
	 * Register the routes on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new static();

		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$args = array();

		foreach ( array_keys( $this->defaults() ) as $key ) {
			$args[ $key ] = array(
				'description' => sprintf( 'The `%s` list or map; validated by ScheduleFactory.', $key ),
				'required'    => false,
			);
		}

		register_rest_route(
			$this->namespace,
			$this->route(),
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => $args,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->route() . '/defaults',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_default_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Get the stored settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return $this->success_response( $this->stored() );
	}

	/**
	 * Get the defaults.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {
		return $this->success_response( $this->defaults() );
	}

	/**
	 * Validate and save the group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {

		// A JSON body must be an object; `"abc"` or `123` would otherwise be a silent no-op.
		$json = $request->get_json_params();

		if ( null !== $json && ! is_array( $json ) ) {
			return $this->invalid( array( new ValidationError( '', ValidationError::INVALID_TYPE, array( 'group' => $this->group() ) ) ) );
		}

		// Whitelisted keys only; keys not sent keep their stored value.
		$group = $this->stored();

		foreach ( array_keys( $this->defaults() ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$group[ $key ] = $request->get_param( $key );
			}
		}

		$schedule = $this->build( $group );

		if ( ! $schedule instanceof Schedule ) {
			return $this->invalid( $schedule );
		}

		$normalized = $this->normalized( $schedule );

		update_option( $this->option(), $normalized );

		return $this->success_response( $normalized );
	}

	/**
	 * The 400 response for invalid input. Nothing has been stored.
	 *
	 * @param ValidationError[] $errors Errors.
	 * @return WP_Error
	 */
	protected function invalid( array $errors ) {

		return new WP_Error(
			self::INVALID_CODE,
			__( 'The schedule was not saved because some entries are invalid.', 'hourgate' ),
			array(
				'status' => 400,
				'errors' => ErrorMessages::to_response( $errors ),
			)
		);
	}
}
