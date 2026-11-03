<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Settings;

use HourGate\Core\Capabilities;
use HourGate\Installation\Settings\General as InstallationGeneral;
use HourGate\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General settings REST controller.
 */
class General extends BaseController {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action(
			'rest_api_init',
			array( $instance, 'register_routes' )
		);
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/settings/general-settings',
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
						'removeDataOnUninstall' => array(
							'type'              => 'boolean',
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
			'settings/general-settings/defaults',
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
	 * the sanitizer, which would turn `{ "removeDataOnUninstall": null }`
	 * into `false` (OW-055). A `WP_Error` from here is a 400
	 * `rest_invalid_param`, and nothing is saved.
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
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {

		return $this->success_response( self::get_stored_settings() );
	}

	/**
	 * Update settings.
	 *
	 * Only keys present in the request change; the rest keep their stored
	 * value. `removeDataOnUninstall` deletes data, so only site admins
	 * (`manage_options`) may change it.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
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

		$settings = self::get_stored_settings();

		if ( $request->has_param( 'removeDataOnUninstall' ) ) {

			$remove_data = (bool) $request->get_param( 'removeDataOnUninstall' );

			if ( $remove_data !== $settings['removeDataOnUninstall'] && ! Capabilities::current_user_can_manage_site() ) {
				return $this->error_response(
					'hourgate_forbidden_setting',
					__( 'Only administrators can change "Remove Data on Uninstall".', 'hourgate' ),
					403
				);
			}

			$settings['removeDataOnUninstall'] = $remove_data;
		}

		update_option( 'hourgate_general_settings', $settings );

		return $this->success_response( $settings );
	}

	/**
	 * Stored settings merged with the defaults, limited to known keys and typed.
	 *
	 * @return array
	 */
	private static function get_stored_settings() {

		$defaults = InstallationGeneral::get_default_settings();
		$stored   = get_option( 'hourgate_general_settings', array() );
		$settings = array_intersect_key( wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults ), $defaults );

		$settings['removeDataOnUninstall'] = (bool) $settings['removeDataOnUninstall'];

		return $settings;
	}

	/**
	 * Get default settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {

		return $this->success_response(
			InstallationGeneral::get_default_settings()
		);
	}
}
