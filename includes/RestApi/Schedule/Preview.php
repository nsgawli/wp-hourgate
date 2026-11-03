<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi\Schedule;

use HourGate\Core\Schedule\Calendar;
use HourGate\Core\Schedule\DayPlan;
use HourGate\Core\Schedule\Window;
use HourGate\Core\StoreSchedule;
use HourGate\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /schedule/preview?days=14`: the next days of the stored schedule, from
 * the same engine as `StoreSchedule::is_open()`, with the rule that won each
 * date (the sprint 3 preview's "why" column, 09 §11).
 *
 * Read-only, manage capability (not public). The shopper-facing status route
 * is a separate `GET /status` with `Cache-Control: no-store` (sprint 5).
 */
class Preview extends BaseController {

	/**
	 * Default number of days.
	 *
	 * @var int
	 */
	const DEFAULT_DAYS = 14;

	/**
	 * Most days per request.
	 *
	 * @var int
	 */
	const MAX_DAYS = 60;

	/**
	 * Register the route on `rest_api_init`.
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
			'/schedule/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_preview' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'days' => array(
						'description'       => 'Number of local dates from today, 1-60.',
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => self::MAX_DAYS,
						'default'           => self::DEFAULT_DAYS,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'rest_sanitize_request_arg',
					),
				),
			)
		);
	}

	/**
	 * The preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_preview( WP_REST_Request $request ) {

		$engine = StoreSchedule::engine();
		$now    = time();
		$days   = (int) $request->get_param( 'days' );
		$today  = Calendar::to_day( wp_date( 'Y-m-d', $now ) );
		$dates  = array();

		// The day before the first date, for the first row's carried-over window (one extra `day_plan()`).
		$previous = $engine->day_plan( Calendar::from_day( $today - 1 ) );

		for ( $day = $today; $day < $today + $days; $day++ ) {
			$plan     = $engine->day_plan( Calendar::from_day( $day ) );
			$dates[]  = $this->format_plan( $plan, $previous );
			$previous = $plan;
		}

		$next_open  = $engine->next_open( $now );
		$next_close = $engine->next_close( $now );

		return $this->success_response(
			array(
				'timezone' => wp_timezone_string(),
				'fallback' => StoreSchedule::is_fallback(),
				'now'      => array(
					'time'      => wp_date( 'c', $now ),
					'open'      => $engine->is_open( $now ),
					'nextOpen'  => null === $next_open ? null : wp_date( 'c', $next_open ),
					'nextClose' => null === $next_close ? null : wp_date( 'c', $next_close ),
				),
				'days'     => $dates,
			)
		);
	}

	/**
	 * One date of the preview.
	 *
	 * @param DayPlan $plan     Day plan.
	 * @param DayPlan $previous The previous date's plan.
	 * @return array
	 */
	private function format_plan( DayPlan $plan, DayPlan $previous ) {

		return array(
			'date'        => $plan->get_date(),
			'rule'        => $plan->get_rule(),
			'ruleId'      => $plan->get_rule_id(),
			'label'       => $plan->get_label(),
			'carriedOver' => $this->carried_over( $plan, $previous ),
			'windows'     => array_map(
				function ( Window $window ) use ( $plan ) {
					return array(
						'start'       => wp_date( 'c', $window->get_start() ),
						'end'         => wp_date( 'c', $window->get_end() ),
						'startTime'   => wp_date( 'H:i', $window->get_start() ),
						'endTime'     => wp_date( 'H:i', $window->get_end() ),
						'endsNextDay' => wp_date( 'Y-m-d', $window->get_end() ) > $plan->get_date(),
					);
				},
				$plan->get_windows()
			),
		);
	}

	/**
	 * The window from the previous date that is still running after this
	 * date's midnight, or null: what makes the row say "open until 2:00 am"
	 * between midnight and the end of last night's overnight range. Windows
	 * are the engine's own, so a closure on this date has already cut it at
	 * midnight (D5, nothing carried over) and special hours on this date do
	 * not (only closures cut); a range ending exactly at midnight carries
	 * nothing.
	 *
	 * @param DayPlan $plan     This date's plan.
	 * @param DayPlan $previous The previous date's plan.
	 * @return array|null `{ start, end, startTime, endTime, fromDate }`.
	 */
	private function carried_over( DayPlan $plan, DayPlan $previous ) {

		foreach ( $previous->get_windows() as $window ) {

			if ( wp_date( 'Y-m-d', $window->get_end() ) === $plan->get_date() && '00:00' !== wp_date( 'H:i', $window->get_end() ) ) {
				return array(
					'start'     => wp_date( 'c', $window->get_start() ),
					'end'       => wp_date( 'c', $window->get_end() ),
					'startTime' => wp_date( 'H:i', $window->get_start() ),
					'endTime'   => wp_date( 'H:i', $window->get_end() ),
					'fromDate'  => $previous->get_date(),
				);
			}
		}

		return null;
	}
}
