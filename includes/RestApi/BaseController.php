<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi;

use HourGate\Core\Capabilities;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base REST API controller.
 */
class BaseController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'hourgate/v1';

	/**
	 * Permission callback: HourGate's manage capability
	 * (`manage_woocommerce` by default, filterable).
	 *
	 * Controllers for site-wide or destructive actions override this with
	 * `Capabilities::current_user_can_manage_site()`.
	 *
	 * @return bool
	 */
	public function permission_callback() {

		return Capabilities::current_user_can_manage();
	}

	/**
	 * Success response.
	 *
	 * @param mixed $data Response data.
	 * @return \WP_REST_Response
	 */
	protected function success_response( $data = array() ) {

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Error response.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $status Status code.
	 *
	 * @return WP_Error
	 */
	protected function error_response( $code, $message, $status = 400 ) {

		return new WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
			)
		);
	}
}
