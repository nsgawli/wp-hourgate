<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Settings;

use HourGate\Core\Behaviour as CoreBehaviour;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;
use HourGate\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Behaviour settings: `GET|PUT /settings/behaviour`, `GET /settings/behaviour/defaults`.
 *
 * Body: `{ enforce?: bool, closedMode?: 'block'|'schedule'|'catalogue', closedMessage?: string,
 * noticeBar?: bool, noticeBarScope?: 'woocommerce'|'all', scheduledOrderNote?: bool,
 * scheduledOrderNoteText?: string }`.
 * Shop managers may change every key (they run opening hours), so the
 * default manage capability applies throughout.
 */
class Behaviour extends BaseController {

	/**
	 * Route.
	 *
	 * @var string
	 */
	const ROUTE = '/settings/behaviour';

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
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'enforce'                => array(
							'description'       => 'Whether opening hours are enforced.',
							'type'              => 'boolean',
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'closedMode'             => array(
							'description'       => 'What happens to orders while closed.',
							'type'              => 'string',
							'enum'              => BehaviourSettings::MODES,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'closedMessage'          => array(
							'description'       => 'Plain-text closed message; empty means the default.',
							'type'              => 'string',
							'maxLength'         => BehaviourSettings::MESSAGE_MAX_LENGTH,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'noticeBar'              => array(
							'description'       => 'Whether the storefront notice bar is on.',
							'type'              => 'boolean',
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'noticeBarScope'         => array(
							'description'       => 'Where the notice bar shows.',
							'type'              => 'string',
							'enum'              => BehaviourSettings::BAR_SCOPES,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'scheduledOrderNote'     => array(
							'description'       => 'Whether orders accepted while closed get a note in the customer emails.',
							'type'              => 'boolean',
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
						'scheduledOrderNoteText' => array(
							'description'       => 'Plain-text note for orders accepted while closed; empty means the default.',
							'type'              => 'string',
							'maxLength'         => BehaviourSettings::MESSAGE_MAX_LENGTH,
							'required'          => false,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_arg' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			self::ROUTE . '/defaults',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_default_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Sanitize one arg, refusing null.
	 *
	 * WordPress skips `validate_callback` for a null value but still runs
	 * the sanitizer, which would turn `{ "enforce": null }` into `false`. A
	 * `WP_Error` from here is a 400 `rest_invalid_param`, and nothing is saved.
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

		if ( 'closedMessage' === $param || 'scheduledOrderNoteText' === $param ) {
			return CoreBehaviour::sanitize_message( $value );
		}

		return rest_sanitize_request_arg( $value, $request, $param );
	}

	/**
	 * Get the stored settings, merged with the defaults.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return $this->success_response( CoreBehaviour::get() );
	}

	/**
	 * Get the defaults.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {
		return $this->success_response( BehaviourSettings::get_default_settings() );
	}

	/**
	 * Save the settings. Keys not sent keep their stored value; unknown keys
	 * are ignored. The `args` schema has already rejected wrong types, an
	 * unknown mode and an over-long message with 400.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {

		// A JSON body must be an object; `"abc"` or `123` would otherwise be a silent no-op.
		$json = $request->get_json_params();

		if ( null !== $json && ! is_array( $json ) ) {
			return $this->error_response(
				'rest_invalid_param',
				__( 'The request body must be a JSON object.', 'hourgate' ),
				400
			);
		}

		$settings = CoreBehaviour::get();

		foreach ( array_keys( BehaviourSettings::get_default_settings() ) as $key ) {

			if ( $request->has_param( $key ) ) {
				$settings[ $key ] = $request->get_param( $key );
			}
		}

		// The schema coerced these; cast once more so the stored types are exact.
		$settings = array(
			'enforce'                => (bool) $settings['enforce'],
			'closedMode'             => (string) $settings['closedMode'],
			'closedMessage'          => (string) $settings['closedMessage'],
			'noticeBar'              => (bool) $settings['noticeBar'],
			'noticeBarScope'         => (string) $settings['noticeBarScope'],
			'scheduledOrderNote'     => (bool) $settings['scheduledOrderNote'],
			'scheduledOrderNoteText' => (string) $settings['scheduledOrderNoteText'],
		);

		update_option( BehaviourSettings::OPTION, $settings );

		return $this->success_response( CoreBehaviour::get() );
	}
}
