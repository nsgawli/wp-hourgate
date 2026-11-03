<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi;

use HourGate\Core\OrderingPolicy;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /status`: whether the store is open, for shoppers (OW-036).
 *
 * Public and read-only: the storefront script reads it so pages served from a
 * page cache still show the current state (09 §16.4, §16.7). The body is
 * `OrderingPolicy::shopper_status()`, the same for every visitor, logged in
 * or not, and holds only what the storefront shows. Never cached
 * (`Cache-Control: no-store`). It costs the autoloaded option reads the
 * decision already makes, so it needs no rate limit.
 */
class Status extends BaseController {

	/**
	 * Route.
	 *
	 * @var string
	 */
	const ROUTE = '/status';

	/**
	 * Values `include` accepts.
	 *
	 * @var string[]
	 */
	const INCLUDES = array( 'hours' );

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
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'include' => array(
						'description'       => 'Extra parts to add: "hours" (the weekly hours and upcoming closures).',
						'type'              => 'string',
						'enum'              => self::INCLUDES,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'rest_sanitize_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Everyone may read the status: shoppers need it on every storefront page,
	 * logged in or not (09 §16.7). Safe because the route only reads, and its
	 * body is the same for every visitor and holds only what the storefront
	 * already shows (`OrderingPolicy::shopper_status()`).
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return true;
	}

	/**
	 * The status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status( WP_REST_Request $request ) {

		try {
			$status = OrderingPolicy::shopper_status( 'hours' === $request->get_param( 'include' ) );
		} catch ( \Throwable $e ) {
			OrderingPolicy::report_status( $e );

			$response = rest_convert_error_to_response( $this->error_response( 'hourgate_status_unavailable', __( 'The store status is not available right now.', 'hourgate' ), 500 ) );
			$response->header( 'Cache-Control', 'no-store, max-age=0' );

			return $response;
		}

		$response = $this->success_response( $status );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}
}
